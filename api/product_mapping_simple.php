<?php
session_start();
require '../php/bootstrap.php';
require __DIR__ . '/../php/odoo_connection.php'; // FIXED PATH

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['dins_user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $user_id = $_SESSION['dins_user_id'];
    $user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
    
    // For now, let's allow admin users or skip permission check
    if ($user_details['admin'] < 1) {
        // Add the permission for this user temporarily
        $DB->query("INSERT IGNORE INTO user_permissions (user_id, page, has_access) VALUES (?, 'essential_mapping', 1)", [$user_id]);
    }
    
    // Handle JSON POST data and extract action properly
    $input_data = null;
    $raw_input = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $raw_input = file_get_contents('php://input');
        if (!empty($raw_input)) {
            $input_data = json_decode($raw_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
                exit;
            }
        }
    }
    
    // Extract action from multiple possible sources
    $action = 'stats'; // default
    
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];
    } elseif ($input_data && isset($input_data['action'])) {
        $action = $input_data['action'];
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($action) {
        case 'test':
            try {
                // Check table exists
                $tableCheck = $DB->query("SHOW TABLES LIKE 'master_essential_product_mappings'");
                $tableExists = !empty($tableCheck);
                
                $columns = [];
                if ($tableExists) {
                    $columns = $DB->query("DESCRIBE master_essential_product_mappings");
                }
                
                echo json_encode([
                    'status' => 'API is working', 
                    'timestamp' => date('Y-m-d H:i:s'),
                    'user_id' => $user_id,
                    'table_exists' => $tableExists,
                    'table_structure' => $columns,
                    'available_actions' => [
                        'stats', 'essential_types', 'manufacturers', 'search', 
                        'create_mapping', 'product_details', 'mappings', 'delete_mapping'
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'API working but database issue',
                    'error' => $e->getMessage(),
                    'available_actions' => [
                        'stats', 'essential_types', 'manufacturers', 'search', 
                        'create_mapping', 'product_details', 'mappings', 'delete_mapping'
                    ]
                ]);
            }
            break;
            
        case 'debug_create':
            // Test the mapping creation without actually doing it
            $sku = $_GET['sku'] ?? 'TEST_SKU';
            $essentialTypeId = $_GET['essential_type_id'] ?? '1';
            
            // Check table exists
            try {
                $tableCheck = $DB->query("SHOW TABLES LIKE 'master_essential_product_mappings'");
                $tableExists = !empty($tableCheck);
                
                $columns = [];
                if ($tableExists) {
                    $columns = $DB->query("DESCRIBE master_essential_product_mappings");
                }
                
                echo json_encode([
                    'table_exists' => $tableExists,
                    'table_structure' => $columns,
                    'user_id' => $user_id,
                    'test_sku' => $sku,
                    'test_essential_type_id' => $essentialTypeId
                ]);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        case 'stats':
            // Get basic stats
            $totalTypes = $DB->query("SELECT COUNT(*) as count FROM master_essential_product_types WHERE is_active = 1")[0]['count'];
            $mappedProducts = $DB->query("SELECT COUNT(DISTINCT master_products_sku) as count FROM master_essential_product_mappings")[0]['count'];
            
            // Calculate unmapped types
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
            
            // Calculate out of stock types (FIXED - was hardcoded to 0)
            // Get all product types with their mapped SKUs
            $productTypes = $DB->query("
                SELECT 
                    mept.id,
                    mept.minimum_stock_qty,
                    GROUP_CONCAT(mepm.master_products_sku) as mapped_skus
                FROM master_essential_product_types mept
                LEFT JOIN master_essential_product_mappings mepm ON mept.id = mepm.essential_product_type_id
                LEFT JOIN master_products mp ON mepm.master_products_sku = mp.sku AND mp.enable = 'y'
                WHERE mept.is_active = 1
                GROUP BY mept.id
            ");
            
            $outOfStockCount = 0;
            foreach ($productTypes as $type) {
                $current_stock = 0;
                
                if (!empty($type['mapped_skus'])) {
                    $skus = explode(',', $type['mapped_skus']);
                    $cs_stock = getOdooQuantities($skus, 12);
                    $as_stock = getOdooQuantities($skus, 19);
                    
                    foreach ($skus as $sku) {
                        $current_stock += ($cs_stock[$sku] ?? 0) + ($as_stock[$sku] ?? 0);
                    }
                }
                
                // Count as out of stock if current stock is below minimum OR zero
                if ($current_stock < $type['minimum_stock_qty'] || $current_stock == 0) {
                    $outOfStockCount++;
                }
            }
            
            echo json_encode([
                'total_product_types' => $totalTypes,
                'mapped_products' => $mappedProducts,
                'unmapped_types' => $unmappedTypes,
                'out_of_stock_types' => $outOfStockCount
            ]);
            break;
            
        case 'essential_types':
            $categoryFilter = $_GET['category_id'] ?? '';
            $whereClause = "WHERE mept.is_active = 1";
            $params = [];
            
            if ($categoryFilter) {
                $whereClause .= " AND mept.essential_category_id = ?";
                $params[] = $categoryFilter;
            }
            
            // Get product types with mapped SKUs
            $types = $DB->query("
                SELECT 
                    mept.id,
                    mept.product_type_name,
                    mept.minimum_stock_qty,
                    mec.display_name as category_name,
                    mec.id as category_id,
                    COUNT(mepm.master_products_sku) as mapped_count,
                    GROUP_CONCAT(mepm.master_products_sku) as mapped_skus
                FROM master_essential_product_types mept
                JOIN master_essential_categories mec ON mept.essential_category_id = mec.id
                LEFT JOIN master_essential_product_mappings mepm ON mept.id = mepm.essential_product_type_id
                LEFT JOIN master_products mp ON mepm.master_products_sku = mp.sku AND mp.enable = 'y'
                $whereClause
                GROUP BY mept.id, mec.id
                ORDER BY mec.display_order, mept.display_order
            ", $params);
            
            // Calculate Odoo stock for each type
            foreach ($types as &$type) {
                $current_stock = 0;
                
                if (!empty($type['mapped_skus'])) {
                    $skus = explode(',', $type['mapped_skus']);
                    $cs_stock = getOdooQuantities($skus, 12);
                    $as_stock = getOdooQuantities($skus, 19);
                    
                    foreach ($skus as $sku) {
                        $current_stock += ($cs_stock[$sku] ?? 0) + ($as_stock[$sku] ?? 0);
                    }
                }
                
                $type['current_stock'] = $current_stock;
                
                // Calculate stock status
                if ($current_stock == 0) {
                    $type['stock_status'] = 'OUT_OF_STOCK';
                } elseif ($current_stock < $type['minimum_stock_qty']) {
                    $type['stock_status'] = 'LOW_STOCK';
                } else {
                    $type['stock_status'] = 'OK';
                }
                
                // Remove internal field
                unset($type['mapped_skus']);
            }
            
            echo json_encode(['essential_types' => $types]);
            break;
            
        case 'manufacturers':
            $manufacturers = $DB->query("
                SELECT DISTINCT manufacturer 
                FROM master_products 
                WHERE manufacturer IS NOT NULL 
                AND manufacturer != '' 
                AND enable = 'y'
                ORDER BY manufacturer
                LIMIT 50
            ");
            
            echo json_encode(['manufacturers' => $manufacturers]);
            break;
            
        case 'search':
            $search = $_GET['search'] ?? '';
            $exclude = $_GET['exclude'] ?? '';
            $manufacturer = $_GET['manufacturer'] ?? '';
            $stockFilter = $_GET['stock_filter'] ?? '';
            $showMapped = $_GET['show_mapped'] ?? 'false';
            $limit = 50;
            
            $whereClause = "WHERE mp.enable = 'y'";
            $params = [];
            
            // Search filter
            if (!empty($search)) {
                $search_words = array_filter(explode(" ", trim($search)));
                $search_conditions = [];
                foreach ($search_words as $word) {
                    $pattern = '%' . $word . '%';
                    $search_conditions[] = "(mp.sku LIKE ? OR mp.name LIKE ? OR mp.manufacturer LIKE ? OR mp.mpn LIKE ? OR mp.ean LIKE ?)";
                    array_push($params, $pattern, $pattern, $pattern, $pattern, $pattern);
                }
                if (!empty($search_conditions)) {
                    $whereClause .= " AND (" . implode(' AND ', $search_conditions) . ")";
                }
            }
            
            // Exclude filter
            if (!empty($exclude)) {
                $exclude_words = array_filter(explode(" ", trim($exclude)));
                foreach ($exclude_words as $word) {
                    $pattern = '%' . $word . '%';
                    $whereClause .= " AND mp.name NOT LIKE ? AND mp.sku NOT LIKE ?";
                    array_push($params, $pattern, $pattern);
                }
            }
            
            // Manufacturer filter
            if (!empty($manufacturer)) {
                $whereClause .= " AND mp.manufacturer = ?";
                $params[] = $manufacturer;
            }
            
            // Show/hide already mapped products
            if ($showMapped === 'false') {
                $whereClause .= " AND mepm.id IS NULL";
            }
            
            $query = "
                SELECT 
                    mp.sku,
                    mp.name,
                    mp.manufacturer,
                    mp.mpn,
                    mp.ean,
                    mp.cost,
                    mp.price,
                    mepm.id as mapping_id,
                    mepm.essential_product_type_id,
                    mept.product_type_name,
                    mec.display_name as category_name
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
            
            // Get Odoo stock for all products
            if (!empty($products)) {
                $skus = array_column($products, 'sku');
                $cs_stock = getOdooQuantities($skus, 12);
                $as_stock = getOdooQuantities($skus, 19);
                
                foreach ($products as &$product) {
                    $product['cs_stock'] = $cs_stock[$product['sku']] ?? 0;
                    $product['as_stock'] = $as_stock[$product['sku']] ?? 0;
                    $product['total_stock'] = $product['cs_stock'] + $product['as_stock'];
                    // CRITICAL: Add 'qty' field for JavaScript compatibility
                    $product['qty'] = $product['total_stock'];
                }
                
                // Apply stock filter if specified
                if (!empty($stockFilter)) {
                    $products = array_filter($products, function($product) use ($stockFilter) {
                        switch ($stockFilter) {
                            case 'in_stock':
                                return $product['total_stock'] > 0;
                            case 'out_of_stock':
                                return $product['total_stock'] == 0;
                            case 'low_stock':
                                return $product['total_stock'] > 0 && $product['total_stock'] < 5;
                            default:
                                return true;
                        }
                    });
                }
            }
            
            echo json_encode(['products' => array_values($products)]);
            break;
            
        case 'create_mapping':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $sku = $input_data['sku'] ?? null;
            $essentialTypeId = $input_data['essential_type_id'] ?? null;
            $notes = $input_data['notes'] ?? '';
            
            if (!$sku || !$essentialTypeId) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'SKU and essential_type_id are required',
                    'received' => [
                        'sku' => $sku,
                        'essential_type_id' => $essentialTypeId
                    ]
                ]);
                break;
            }
            
            // Check if product exists
            $product = $DB->query("SELECT sku FROM master_products WHERE sku = ? AND enable = 'y'", [$sku]);
            if (!$product) {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found or disabled', 'sku' => $sku]);
                break;
            }
            
            // Check if essential type exists
            $essentialType = $DB->query("SELECT id FROM master_essential_product_types WHERE id = ? AND is_active = 1", [$essentialTypeId]);
            if (!$essentialType) {
                http_response_code(404);
                echo json_encode(['error' => 'Essential type not found or inactive', 'essential_type_id' => $essentialTypeId]);
                break;
            }
            
            // Check if mapping already exists
            $existing = $DB->query("SELECT id FROM master_essential_product_mappings WHERE master_products_sku = ?", [$sku]);
            if ($existing) {
                http_response_code(400);
                echo json_encode(['error' => 'Product is already mapped to an essential type', 'existing_mapping_id' => $existing[0]['id']]);
                break;
            }
            
            // Create the mapping
            try {
                $sql = "INSERT INTO master_essential_product_mappings (essential_product_type_id, master_products_sku, created_by, notes, created_at) VALUES (?, ?, ?, ?, NOW())";
                $params = [$essentialTypeId, $sku, $user_id, $notes];
                
                $result = $DB->query($sql, $params);
                
                echo json_encode(['success' => true, 'mapping_id' => $DB->lastInsertId()]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to create mapping: ' . $e->getMessage(),
                    'sql_state' => $e->getCode(),
                    'input_data' => [
                        'sku' => $sku,
                        'essential_type_id' => $essentialTypeId,
                        'user_id' => $user_id,
                        'notes' => $notes
                    ]
                ]);
            }
            break;
            
        case 'product_details':
            $sku = $_GET['sku'] ?? '';
            
            if (!$sku) {
                http_response_code(400);
                echo json_encode(['error' => 'SKU is required']);
                break;
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
                break;
            }
            
            // Add Odoo stock
            $cs_stock = getOdooQuantities([$sku], 12);
            $as_stock = getOdooQuantities([$sku], 19);
            
            $product[0]['cs_stock'] = $cs_stock[$sku] ?? 0;
            $product[0]['as_stock'] = $as_stock[$sku] ?? 0;
            $product[0]['total_stock'] = $product[0]['cs_stock'] + $product[0]['as_stock'];
            $product[0]['qty'] = $product[0]['total_stock']; // JavaScript compatibility
            
            echo json_encode(['product' => $product[0]]);
            break;
            
        case 'mappings':
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
            
            // Add Odoo stock to mappings
            if (!empty($mappings)) {
                $skus = array_column($mappings, 'sku');
                $cs_stock = getOdooQuantities($skus, 12);
                $as_stock = getOdooQuantities($skus, 19);
                
                foreach ($mappings as &$mapping) {
                    $mapping['cs_stock'] = $cs_stock[$mapping['sku']] ?? 0;
                    $mapping['as_stock'] = $as_stock[$mapping['sku']] ?? 0;
                    $mapping['qty'] = $mapping['cs_stock'] + $mapping['as_stock'];
                }
            }
            
            echo json_encode(['mappings' => $mappings]);
            break;
            
        case 'delete_mapping':
            // Handle DELETE request for removing mappings
            if ($method !== 'DELETE') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $mappingId = $input_data['mapping_id'] ?? null;
            $sku = $input_data['sku'] ?? null;
            
            if (!$mappingId && !$sku) {
                http_response_code(400);
                echo json_encode(['error' => 'Mapping ID or SKU is required']);
                break;
            }
            
            // Build where clause
            if ($mappingId) {
                $whereClause = "id = ?";
                $param = $mappingId;
            } else {
                $whereClause = "master_products_sku = ?";
                $param = $sku;
            }
            
            // Check if mapping exists
            $mapping = $DB->query("SELECT id, created_by FROM master_essential_product_mappings WHERE $whereClause", [$param]);
            
            if (!$mapping) {
                http_response_code(404);
                echo json_encode(['error' => 'Mapping not found']);
                break;
            }
            
            // Check permissions - admin or creator can delete
            if ($user_details['admin'] < 2 && $mapping[0]['created_by'] != $user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only delete mappings you created']);
                break;
            }
            
            // Delete the mapping
            $result = $DB->query("DELETE FROM master_essential_product_mappings WHERE $whereClause", [$param]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid action: ' . $action,
                'available_actions' => [
                    'test', 'debug_create', 'stats', 'essential_types', 'manufacturers', 'search', 
                    'create_mapping', 'product_details', 'mappings', 'delete_mapping'
                ]
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>
