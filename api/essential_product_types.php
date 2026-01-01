<?php
session_start();
require '../php/bootstrap.php';
require __DIR__ . '/../php/odoo_connection.php'; // FIXED PATH

// Check if user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_super_admin = $user_details['admin'] >= 2;

// Check if user has essential product types permission
$product_types_permission = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_product_types'", 
    [$user_id]
);
$has_product_types_access = !empty($product_types_permission) && $product_types_permission[0]['has_access'];

if (!$has_product_types_access) {
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
            // Get product types grouped by category
            $categoryId = $_GET['category_id'] ?? null;
            $status = $_GET['status'] ?? null;
            
            $whereClause = "WHERE mept.id IS NOT NULL";
            $params = [];
            
            if ($categoryId) {
                $whereClause .= " AND mept.essential_category_id = ?";
                $params[] = $categoryId;
            }
            
            if ($status !== null && $status !== '') {
                $whereClause .= " AND mept.is_active = ?";
                $params[] = $status;
            }
            
            // First, get product types with their mapped SKUs
            $productTypes = $DB->query("
                SELECT 
                    mept.id,
                    mept.essential_category_id,
                    mept.product_type_name,
                    mept.minimum_stock_qty,
                    mept.display_order,
                    mept.is_active,
                    mept.notes,
                    mept.created_at,
                    mec.display_name as category_name,
                    mec.display_order as category_order,
                    COUNT(mepm.master_products_sku) as mapped_products,
                    GROUP_CONCAT(mepm.master_products_sku) as mapped_skus
                FROM master_essential_product_types mept
                JOIN master_essential_categories mec ON mept.essential_category_id = mec.id
                LEFT JOIN master_essential_product_mappings mepm ON mept.id = mepm.essential_product_type_id
                LEFT JOIN master_products mp ON mepm.master_products_sku = mp.sku AND mp.enable = 'y'
                $whereClause
                GROUP BY mept.id, mec.id, mec.display_name, mec.display_order
                ORDER BY mec.display_order, mept.display_order, mept.product_type_name
            ", $params);
            
            // Calculate Odoo stock for each product type
            foreach ($productTypes as &$type) {
                if (!empty($type['mapped_skus'])) {
                    $skus = explode(',', $type['mapped_skus']);
                    
                    // Get Odoo stock from both locations
                    $cs_stock = getOdooQuantities($skus, 12); // Commerce Street
                    $as_stock = getOdooQuantities($skus, 19); // Argyle Street
                    
                    // Sum up total stock
                    $total_stock = 0;
                    foreach ($skus as $sku) {
                        $total_stock += ($cs_stock[$sku] ?? 0) + ($as_stock[$sku] ?? 0);
                    }
                    
                    $type['current_stock'] = $total_stock;
                } else {
                    $type['current_stock'] = 0;
                }
                
                // Calculate stock status
                if ($type['current_stock'] == 0) {
                    $type['stock_status'] = 'OUT_OF_STOCK';
                } elseif ($type['current_stock'] < $type['minimum_stock_qty']) {
                    $type['stock_status'] = 'LOW_STOCK';
                } else {
                    $type['stock_status'] = 'OK';
                }
                
                // Remove mapped_skus from output (internal use only)
                unset($type['mapped_skus']);
            }
            
            echo json_encode(['product_types' => $productTypes]);
            break;
            
        case 'categories':
            // Get essential categories for dropdowns
            $categories = $DB->query("
                SELECT id, display_name, display_order
                FROM master_essential_categories
                WHERE is_active = 1
                ORDER BY display_order, display_name
            ");
            echo json_encode(['categories' => $categories]);
            break;
            
        case 'details':
            $productTypeId = $_GET['id'] ?? null;
            if (!$productTypeId) {
                http_response_code(400);
                echo json_encode(['error' => 'Product type ID is required']);
                return;
            }
            
            // Get detailed info including mapped products
            $details = $DB->query("
                SELECT 
                    mept.*,
                    mec.display_name as category_name,
                    COUNT(mepm.master_products_sku) as mapped_products,
                    GROUP_CONCAT(mepm.master_products_sku) as mapped_skus
                FROM master_essential_product_types mept
                JOIN master_essential_categories mec ON mept.essential_category_id = mec.id
                LEFT JOIN master_essential_product_mappings mepm ON mept.id = mepm.essential_product_type_id
                LEFT JOIN master_products mp ON mepm.master_products_sku = mp.sku AND mp.enable = 'y'
                WHERE mept.id = ?
                GROUP BY mept.id
            ", [$productTypeId]);
            
            if (!$details) {
                http_response_code(404);
                echo json_encode(['error' => 'Product type not found']);
                return;
            }
            
            // Calculate Odoo stock
            if (!empty($details[0]['mapped_skus'])) {
                $skus = explode(',', $details[0]['mapped_skus']);
                $cs_stock = getOdooQuantities($skus, 12);
                $as_stock = getOdooQuantities($skus, 19);
                
                $total_stock = 0;
                foreach ($skus as $sku) {
                    $total_stock += ($cs_stock[$sku] ?? 0) + ($as_stock[$sku] ?? 0);
                }
                
                $details[0]['current_stock'] = $total_stock;
            } else {
                $details[0]['current_stock'] = 0;
            }
            
            unset($details[0]['mapped_skus']);
            
            // Get mapped products with Odoo stock
            $mappedProducts = $DB->query("
                SELECT 
                    mp.sku,
                    mp.name,
                    mp.manufacturer,
                    mp.cost,
                    mepm.created_at as mapped_at
                FROM master_essential_product_mappings mepm
                JOIN master_products mp ON mepm.master_products_sku = mp.sku
                WHERE mepm.essential_product_type_id = ? AND mp.enable = 'y'
                ORDER BY mp.name
            ", [$productTypeId]);
            
            // Add Odoo stock to each mapped product
            if (!empty($mappedProducts)) {
                $skus = array_column($mappedProducts, 'sku');
                $cs_stock = getOdooQuantities($skus, 12);
                $as_stock = getOdooQuantities($skus, 19);
                
                foreach ($mappedProducts as &$product) {
                    $product['cs_stock'] = $cs_stock[$product['sku']] ?? 0;
                    $product['as_stock'] = $as_stock[$product['sku']] ?? 0;
                    $product['total_stock'] = $product['cs_stock'] + $product['as_stock'];
                }
            }
            
            echo json_encode([
                'details' => $details[0],
                'mapped_products' => $mappedProducts
            ]);
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
            $essential_category_id = $input['essential_category_id'] ?? null;
            $product_type_name = $input['product_type_name'] ?? '';
            $minimum_stock_qty = $input['minimum_stock_qty'] ?? 1;
            $display_order = $input['display_order'] ?? 0;
            $notes = $input['notes'] ?? '';
            $is_active = $input['is_active'] ?? 1;
            
            if (!$essential_category_id || !$product_type_name) {
                http_response_code(400);
                echo json_encode(['error' => 'Category and product type name are required']);
                return;
            }
            
            // Check if product type already exists in this category
            $existing = $DB->query(
                "SELECT id FROM master_essential_product_types WHERE essential_category_id = ? AND product_type_name = ?", 
                [$essential_category_id, $product_type_name]
            );
            
            if ($existing) {
                http_response_code(400);
                echo json_encode(['error' => 'Product type already exists in this category']);
                return;
            }
            
            $result = $DB->query("
                INSERT INTO master_essential_product_types 
                (essential_category_id, product_type_name, minimum_stock_qty, display_order, is_active, notes) 
                VALUES (?, ?, ?, ?, ?, ?)
            ", [$essential_category_id, $product_type_name, $minimum_stock_qty, $display_order, $is_active, $notes]);
            
            echo json_encode(['success' => true, 'id' => $DB->lastInsertId()]);
            break;
            
        case 'reorder':
            global $is_super_admin;
            if (!$is_super_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Only super admins can reorder product types']);
                return;
            }
            
            $order = $input['order'] ?? [];
            
            foreach ($order as $index => $product_type_id) {
                $DB->query(
                    "UPDATE master_essential_product_types SET display_order = ? WHERE id = ?",
                    [$index + 1, $product_type_id]
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
    $product_type_id = $input['id'] ?? null;
    
    if (!$product_type_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Product type ID is required']);
        return;
    }
    
    $product_type_name = $input['product_type_name'] ?? '';
    $minimum_stock_qty = $input['minimum_stock_qty'] ?? 1;
    $display_order = $input['display_order'] ?? 0;
    $notes = $input['notes'] ?? '';
    $is_active = $input['is_active'] ?? 1;
    
    if (!$product_type_name) {
        http_response_code(400);
        echo json_encode(['error' => 'Product type name is required']);
        return;
    }
    
    $result = $DB->query("
        UPDATE master_essential_product_types 
        SET product_type_name = ?, minimum_stock_qty = ?, display_order = ?, is_active = ?, notes = ?, updated_at = NOW()
        WHERE id = ?
    ", [$product_type_name, $minimum_stock_qty, $display_order, $is_active, $notes, $product_type_id]);
    
    echo json_encode(['success' => true]);
}

function handleDelete() {
    global $DB, $is_super_admin;
    
    if (!$is_super_admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Only super admins can delete product types']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $product_type_id = $input['id'] ?? null;
    
    if (!$product_type_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Product type ID is required']);
        return;
    }
    
    // Check if product type has mapped products
    $mappings = $DB->query(
        "SELECT COUNT(*) as count FROM master_essential_product_mappings WHERE essential_product_type_id = ?",
        [$product_type_id]
    );
    
    if ($mappings[0]['count'] > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete product type with mapped products. Remove mappings first.']);
        return;
    }
    
    $result = $DB->query("DELETE FROM master_essential_product_types WHERE id = ?", [$product_type_id]);
    
    echo json_encode(['success' => true]);
}
?>
