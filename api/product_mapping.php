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

// Check if user has essential mapping permission
$mapping_permission = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_mapping'", 
    [$user_id]
);
$has_mapping_access = !empty($mapping_permission) && $mapping_permission[0]['has_access'];

if (!$has_mapping_access) {
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
    
    $action = $_GET['action'] ?? 'search';
    
    switch ($action) {
        case 'search':
            searchProducts();
            break;
            
        case 'stats':
            getStats();
            break;
            
        case 'essential_types':
            getEssentialTypes();
            break;
            
        case 'manufacturers':
            getManufacturers();
            break;
            
        case 'mappings':
            getAllMappings();
            break;
            
        case 'product_details':
            getProductDetails();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function searchProducts() {
    global $DB;
    
    $search = $_GET['search'] ?? '';
    $manufacturer = $_GET['manufacturer'] ?? '';
    $stockFilter = $_GET['stock_filter'] ?? '';
    $showMapped = $_GET['show_mapped'] ?? 'false';
    $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 results
    
    $whereClause = "WHERE mp.enable = 'y'";
    $params = [];
    
    // Search term
    if ($search) {
        $whereClause .= " AND (
            mp.sku LIKE ? OR 
            mp.name LIKE ? OR 
            mp.manufacturer LIKE ? OR 
            mp.ean LIKE ?
        )";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Manufacturer filter
    if ($manufacturer) {
        $whereClause .= " AND mp.manufacturer = ?";
        $params[] = $manufacturer;
    }
    
    // Stock filter
    switch ($stockFilter) {
        case 'in_stock':
            $whereClause .= " AND mp.qty > 0";
            break;
        case 'out_of_stock':
            $whereClause .= " AND mp.qty = 0";
            break;
        case 'unmapped':
            $whereClause .= " AND mepm.essential_product_type_id IS NULL";
            break;
    }
    
    // Include/exclude mapped products
    if ($showMapped === 'false' && $stockFilter !== 'unmapped') {
        $whereClause .= " AND mepm.essential_product_type_id IS NULL";
    }
    
    $query = "
        SELECT 
            mp.sku,
            mp.name,
            mp.manufacturer,
            mp.ean,
            mp.qty,
            mp.cost,
            mp.price,
            mepm.essential_product_type_id,
            mept.product_type_name,
            mec.display_name as category_name,
            mepm.created_at as mapped_at
        FROM master_products mp
        LEFT JOIN master_essential_product_mappings mepm ON mp.sku = mepm.master_products_sku
        LEFT JOIN master_essential_product_types mept ON mepm.essential_product_type_id = mept.id
        LEFT JOIN master_essential_categories mec ON mept.essential_category_id = mec.id
        $whereClause
        ORDER BY mp.name
        LIMIT ?
    ";
    
    $params[] = $limit;
    
    $products = $DB->query($query, $params);
    
    echo json_encode(['products' => $products]);
}

function getStats() {
    global $DB;
    
    // Total product types
    $totalTypes = $DB->query("SELECT COUNT(*) as count FROM master_essential_product_types WHERE is_active = 1")[0]['count'];
    
    // Mapped products count
    $mappedProducts = $DB->query("SELECT COUNT(DISTINCT master_products_sku) as count FROM master_essential_product_mappings")[0]['count'];
    
function getStats() {
    global $DB;
    
    // Total product types
    $totalTypes = $DB->query("SELECT COUNT(*) as count FROM master_essential_product_types WHERE is_active = 1")[0]['count'];
    
    // Mapped products count
    $mappedProducts = $DB->query("SELECT COUNT(DISTINCT master_products_sku) as count FROM master_essential_product_mappings")[0]['count'];
    
    // Unmapped types (types with no products mapped)
    $unmappedTypes = $DB->query("
        SELECT COUNT(*) as count 
        FROM master_essential_product_types mept 
        WHERE mept.is_active = 1 
        AND mept.id NOT IN (
            SELECT DISTINCT essential_product_type_id 
            FROM master_essential_product_mappings 
            WHERE essential_product_type_id IS NOT NULL
        )
    ")[0]['count'];
    
    // Out of stock types (types with 0 total stock)
    $outOfStockTypes = $DB->query("
        SELECT COUNT(*) as count
        FROM (
            SELECT mept.id
            FROM master_essential_product_types mept
            LEFT JOIN master_essential_product_mappings mepm ON mept.id = mepm.essential_product_type_id
            LEFT JOIN master_products mp ON mepm.master_products_sku = mp.sku AND mp.enable = 'y'
            WHERE mept.is_active = 1
            GROUP BY mept.id
            HAVING COALESCE(SUM(mp.qty), 0) = 0
        ) as zero_stock_types
    ")[0]['count'];
    
    echo json_encode([
        'total_product_types' => $totalTypes,
        'mapped_products' => $mappedProducts,
        'unmapped_types' => $unmappedTypes,
        'out_of_stock_types' => $outOfStockTypes
    ]);
}

function getEssentialTypes() {
    global $DB;
    
    $categoryId = $_GET['category_id'] ?? '';
    
    $whereClause = "WHERE mept.is_active = 1";
    $params = [];
    
    if ($categoryId) {
        $whereClause .= " AND mept.essential_category_id = ?";
        $params[] = $categoryId;
    }
    
    $essentialTypes = $DB->query("
        SELECT 
            mept.id,
            mept.product_type_name,
            mept.minimum_stock_qty,
            mec.display_name as category_name,
            mec.id as category_id,
            COALESCE(SUM(mp.qty), 0) as current_stock,
            COUNT(mepm.master_products_sku) as mapped_count,
            CASE 
                WHEN COALESCE(SUM(mp.qty), 0) = 0 THEN 'OUT_OF_STOCK'
                WHEN COALESCE(SUM(mp.qty), 0) < mept.minimum_stock_qty THEN 'LOW_STOCK'
                ELSE 'OK'
            END as stock_status
        FROM master_essential_product_types mept
        JOIN master_essential_categories mec ON mept.essential_category_id = mec.id
        LEFT JOIN master_essential_product_mappings mepm ON mept.id = mepm.essential_product_type_id
        LEFT JOIN master_products mp ON mepm.master_products_sku = mp.sku AND mp.enable = 'y'
        $whereClause
        GROUP BY mept.id, mec.id
        ORDER BY mec.display_order, mept.display_order, mept.product_type_name
    ", $params);
    
    echo json_encode(['essential_types' => $essentialTypes]);
}

function getManufacturers() {
    global $DB;
    
    $manufacturers = $DB->query("
        SELECT DISTINCT manufacturer 
        FROM master_products 
        WHERE manufacturer IS NOT NULL 
        AND manufacturer != '' 
        AND enable = 'y'
        ORDER BY manufacturer
    ");
    
    echo json_encode(['manufacturers' => $manufacturers]);
}

function getAllMappings() {
    global $DB;
    
    $categoryId = $_GET['category_id'] ?? '';
    $essentialTypeId = $_GET['essential_type_id'] ?? '';
    
    $whereClause = "WHERE mp.enable = 'y'";
    $params = [];
    
    if ($categoryId) {
        $whereClause .= " AND mec.id = ?";
        $params[] = $categoryId;
    }
    
    if ($essentialTypeId) {
        $whereClause .= " AND mept.id = ?";
        $params[] = $essentialTypeId;
    }
    
    $mappings = $DB->query("
        SELECT 
            mepm.id as mapping_id,
            mp.sku,
            mp.name as product_name,
            mp.manufacturer,
            mp.qty,
            mp.cost,
            mp.price,
            mept.product_type_name,
            mec.display_name as category_name,
            mepm.created_at as mapped_at,
            mepm.notes as mapping_notes,
            u.username as mapped_by
        FROM master_essential_product_mappings mepm
        JOIN master_products mp ON mepm.master_products_sku = mp.sku
        JOIN master_essential_product_types mept ON mepm.essential_product_type_id = mept.id
        JOIN master_essential_categories mec ON mept.essential_category_id = mec.id
        LEFT JOIN users u ON mepm.created_by = u.id
        $whereClause
        ORDER BY mec.display_order, mept.display_order, mp.name
    ", $params);
    
    echo json_encode(['mappings' => $mappings]);
}

function getProductDetails() {
    global $DB;
    
    $sku = $_GET['sku'] ?? '';
    
    if (!$sku) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU is required']);
        return;
    }
    
    $product = $DB->query("
        SELECT 
            mp.*,
            mepm.essential_product_type_id,
            mept.product_type_name,
            mec.display_name as mapped_category,
            mepm.created_at as mapped_at,
            mepm.notes as mapping_notes,
            u.username as mapped_by
        FROM master_products mp
        LEFT JOIN master_essential_product_mappings mepm ON mp.sku = mepm.master_products_sku
        LEFT JOIN master_essential_product_types mept ON mepm.essential_product_type_id = mept.id
        LEFT JOIN master_essential_categories mec ON mept.essential_category_id = mec.id
        LEFT JOIN users u ON mepm.created_by = u.id
        WHERE mp.sku = ? AND mp.enable = 'y'
    ", [$sku]);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        return;
    }
    
    echo json_encode(['product' => $product[0]]);
}

function handlePost() {
    global $DB, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    $action = $input['action'] ?? 'create_mapping';
    
    switch ($action) {
        case 'create_mapping':
            createMapping($input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function createMapping($input) {
    global $DB, $user_id;
    
    $sku = $input['sku'] ?? '';
    $essentialTypeId = $input['essential_type_id'] ?? '';
    $notes = $input['notes'] ?? '';
    
    if (!$sku || !$essentialTypeId) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU and essential type ID are required']);
        return;
    }
    
    // Check if product exists and is enabled
    $product = $DB->query("SELECT sku FROM master_products WHERE sku = ? AND enable = 'y'", [$sku]);
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found or disabled']);
        return;
    }
    
    // Check if essential type exists
    $essentialType = $DB->query("SELECT id FROM master_essential_product_types WHERE id = ? AND is_active = 1", [$essentialTypeId]);
    if (!$essentialType) {
        http_response_code(404);
        echo json_encode(['error' => 'Essential type not found or inactive']);
        return;
    }
    
    // Check if mapping already exists
    $existing = $DB->query(
        "SELECT id FROM master_essential_product_mappings WHERE master_products_sku = ?", 
        [$sku]
    );
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['error' => 'Product is already mapped to an essential type']);
        return;
    }
    
    // Create the mapping
    $result = $DB->query("
        INSERT INTO master_essential_product_mappings 
        (essential_product_type_id, master_products_sku, created_by, notes) 
        VALUES (?, ?, ?, ?)
    ", [$essentialTypeId, $sku, $user_id, $notes]);
    
    echo json_encode(['success' => true, 'mapping_id' => $DB->lastInsertId()]);
}

function handleDelete() {
    global $DB, $is_super_admin, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $mappingId = $input['mapping_id'] ?? null;
    $sku = $input['sku'] ?? null;
    
    if (!$mappingId && !$sku) {
        http_response_code(400);
        echo json_encode(['error' => 'Mapping ID or SKU is required']);
        return;
    }
    
    // Build where clause
    if ($mappingId) {
        $whereClause = "id = ?";
        $param = $mappingId;
    } else {
        $whereClause = "master_products_sku = ?";
        $param = $sku;
    }
    
    // Check if mapping exists and get details
    $mapping = $DB->query("
        SELECT id, created_by FROM master_essential_product_mappings WHERE $whereClause
    ", [$param]);
    
    if (!$mapping) {
        http_response_code(404);
        echo json_encode(['error' => 'Mapping not found']);
        return;
    }
    
    // Check permissions - super admin or creator can delete
    if (!$is_super_admin && $mapping[0]['created_by'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'You can only delete mappings you created']);
        return;
    }
    
    // Delete the mapping
    $result = $DB->query("DELETE FROM master_essential_product_mappings WHERE $whereClause", [$param]);
    
    echo json_encode(['success' => true]);
}
?>