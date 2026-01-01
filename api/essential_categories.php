<?php
session_start();
require '../php/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_super_admin = $user_details['admin'] >= 2;

// Check if user has essential categories permission
$categories_permission = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_categories'", 
    [$user_id]
);
$has_categories_access = !empty($categories_permission) && $categories_permission[0]['has_access'];

if (!$has_categories_access) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet() {
    global $DB;
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // Get essential categories with original category info (LEFT JOIN to show orphaned categories)
            $categories = $DB->query("
                SELECT 
                    mec.id,
                    mec.master_category_id,
                    mec.display_name,
                    mec.display_order,
                    mec.is_active,
                    mec.notes,
                    COALESCE(mc.pless_main_category, 'MISSING CATEGORY') as pless_main_category,
                    COALESCE(mc.pos_category, 'DELETED') as pos_category
                FROM master_essential_categories mec
                LEFT JOIN master_categories mc ON mec.master_category_id = mc.id
                ORDER BY mec.display_order, mec.display_name
            ");
            echo json_encode(['categories' => $categories]);
            break;
            
        case 'available_categories':
            // Get ALL master categories (not just unused ones) so user can remap broken references
            $available = $DB->query("
                SELECT mc.id, mc.pless_main_category, mc.pos_category
                FROM master_categories mc
                ORDER BY mc.pless_main_category, mc.pos_category
            ");
            echo json_encode(['categories' => $available]);
            break;
            
        case 'details':
            // Get specific essential category details
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Category ID is required']);
                return;
            }
            
            $category = $DB->query("
                SELECT 
                    mec.id,
                    mec.master_category_id,
                    mec.display_name,
                    mec.display_order,
                    mec.is_active,
                    mec.notes,
                    mc.pless_main_category,
                    mc.pos_category
                FROM master_essential_categories mec
                LEFT JOIN master_categories mc ON mec.master_category_id = mc.id
                WHERE mec.id = ?
            ", [$id]);
            
            if (empty($category)) {
                http_response_code(404);
                echo json_encode(['error' => 'Category not found']);
                return;
            }
            
            echo json_encode(['category' => $category[0]]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePost() {
    global $DB, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    $action = $input['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            $master_category_id = $input['master_category_id'] ?? null;
            $display_name = trim($input['display_name'] ?? '');
            $display_order = $input['display_order'] ?? 0;
            $notes = $input['notes'] ?? '';
            $is_active = $input['is_active'] ?? 1;
            
            // Validate: master_category_id must be a positive number and display_name must not be empty
            if (!is_numeric($master_category_id) || $master_category_id <= 0 || empty($display_name)) {
                http_response_code(400);
                echo json_encode(['error' => 'Master category and display name are required']);
                return;
            }
            
            // Check if category already exists
            $existing = $DB->query(
                "SELECT id FROM master_essential_categories WHERE master_category_id = ?", 
                [$master_category_id]
            );
            
            if ($existing) {
                http_response_code(400);
                echo json_encode(['error' => 'Category already exists in essentials']);
                return;
            }
            
            $result = $DB->query("
                INSERT INTO master_essential_categories 
                (master_category_id, display_name, display_order, is_active, notes) 
                VALUES (?, ?, ?, ?, ?)
            ", [$master_category_id, $display_name, $display_order, $is_active, $notes]);
            
            echo json_encode(['success' => true, 'id' => $DB->lastInsertId()]);
            break;
            
        case 'reorder':
            global $is_super_admin;
            if (!$is_super_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Only super admins can reorder categories']);
                return;
            }
            
            $order = $input['order'] ?? [];
            
            foreach ($order as $index => $category_id) {
                $DB->query(
                    "UPDATE master_essential_categories SET display_order = ? WHERE id = ?",
                    [$index + 1, $category_id]
                );
            }
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePut() {
    global $DB;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $category_id = $input['id'] ?? null;
    
    if (!$category_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Category ID is required']);
        return;
    }
    
    $master_category_id = $input['master_category_id'] ?? null;
    $display_name = trim($input['display_name'] ?? '');
    $display_order = $input['display_order'] ?? 0;
    $notes = $input['notes'] ?? '';
    $is_active = $input['is_active'] ?? 1;
    
    if (empty($display_name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Display name is required']);
        return;
    }
    
    // If master_category_id is being changed, validate and check if the new category is already in use
    if ($master_category_id !== null && is_numeric($master_category_id) && $master_category_id > 0) {
        $existing = $DB->query(
            "SELECT id FROM master_essential_categories WHERE master_category_id = ? AND id != ?", 
            [$master_category_id, $category_id]
        );
        
        if ($existing) {
            http_response_code(400);
            echo json_encode(['error' => 'Another essential category is already using this master category']);
            return;
        }
        
        // Update with new master category
        $result = $DB->query("
            UPDATE master_essential_categories 
            SET master_category_id = ?, display_name = ?, display_order = ?, is_active = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ", [$master_category_id, $display_name, $display_order, $is_active, $notes, $category_id]);
    } else {
        // Update without changing master category
        $result = $DB->query("
            UPDATE master_essential_categories 
            SET display_name = ?, display_order = ?, is_active = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ", [$display_name, $display_order, $is_active, $notes, $category_id]);
    }
    
    echo json_encode(['success' => true]);
}

function handleDelete() {
    global $DB, $is_super_admin;
    
    if (!$is_super_admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Only super admins can delete categories']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $category_id = $input['id'] ?? null;
    
    if (!$category_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Category ID is required']);
        return;
    }
    
    // Check if category has product types
    $product_types = $DB->query(
        "SELECT COUNT(*) as count FROM master_essential_product_types WHERE essential_category_id = ?",
        [$category_id]
    );
    
    if ($product_types[0]['count'] > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete category with existing product types']);
        return;
    }
    
    $result = $DB->query("DELETE FROM master_essential_categories WHERE id = ?", [$category_id]);
    
    echo json_encode(['success' => true]);
}
?>