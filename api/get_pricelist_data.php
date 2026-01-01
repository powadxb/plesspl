<?php
session_start();
require '../php/bootstrap.php';
require __DIR__ . '/../php/odoo_connection.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['dins_user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $user_id = $_SESSION['dins_user_id'];
    $user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
    
    // Check permission
    $has_access = $DB->query(
        "SELECT has_access FROM user_permissions WHERE user_id = ? AND (page = 'essential_product_types' OR page = 'essential_categories')", 
        [$user_id]
    );
    
    if (empty($has_access) || !$has_access[0]['has_access']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Get tax rates for VAT calculation
    $tax_rates = [];
    $tax_response = $DB->query("SELECT tax_rate_id, tax_rate FROM tax_rates");
    foreach ($tax_response as $row) {
        $tax_rates[$row['tax_rate_id']] = floatval($row['tax_rate']);
    }
    
    // Get all active essential categories
    $categories = $DB->query("
        SELECT id, display_name, display_order
        FROM master_essential_categories
        WHERE is_active = 1
        ORDER BY display_order, display_name
    ");
    
    // Get all active product types with their mapped products
    $productTypes = $DB->query("
        SELECT 
            mept.id,
            mept.essential_category_id,
            mept.product_type_name,
            mept.minimum_stock_qty,
            mept.display_order,
            GROUP_CONCAT(DISTINCT mepm.master_products_sku) as mapped_skus,
            GROUP_CONCAT(DISTINCT mp.tax_rate_id) as tax_rate_ids,
            MIN(mp.price) as min_price,
            COUNT(DISTINCT mepm.master_products_sku) as mapped_count
        FROM master_essential_product_types mept
        LEFT JOIN master_essential_product_mappings mepm ON mept.id = mepm.essential_product_type_id
        LEFT JOIN master_products mp ON mepm.master_products_sku = mp.sku AND mp.enable = 'y'
        WHERE mept.is_active = 1
        GROUP BY mept.id
        ORDER BY mept.display_order, mept.product_type_name
    ");
    
    // Calculate stock and prices for each product type
    $products = [];
    
    foreach ($productTypes as $type) {
        $current_stock = 0;
        $retail_price = 0;
        
        // Calculate Odoo stock
        if (!empty($type['mapped_skus'])) {
            $skus = explode(',', $type['mapped_skus']);
            
            // Get Odoo stock from both locations
            $cs_stock = getOdooQuantities($skus, 12); // Commerce Street
            $as_stock = getOdooQuantities($skus, 19); // Argyle Street
            
            // Sum up total stock
            foreach ($skus as $sku) {
                $current_stock += ($cs_stock[$sku] ?? 0) + ($as_stock[$sku] ?? 0);
            }
        }
        
        // Calculate minimum retail price with VAT
        if (!empty($type['min_price']) && !empty($type['tax_rate_ids'])) {
            $tax_rate_id_array = explode(',', $type['tax_rate_ids']);
            $tax_rate_id = $tax_rate_id_array[0]; // Use first product's tax rate
            $tax_rate = $tax_rates[$tax_rate_id] ?? 0;
            
            // Price with VAT
            $retail_price = floatval($type['min_price']) * (1 + $tax_rate);
        }
        
        // Calculate stock status
        if ($current_stock == 0) {
            $stock_status = 'OUT_OF_STOCK';
        } elseif ($current_stock < $type['minimum_stock_qty']) {
            $stock_status = 'LOW_STOCK';
        } else {
            $stock_status = 'OK';
        }
        
        $products[] = [
            'id' => $type['id'],
            'essential_category_id' => $type['essential_category_id'],
            'product_type_name' => $type['product_type_name'],
            'minimum_stock_qty' => intval($type['minimum_stock_qty']),
            'current_stock' => $current_stock,
            'stock_status' => $stock_status,
            'retail_price' => $retail_price,
            'mapped_count' => intval($type['mapped_count'])
        ];
    }
    
    // Return structured data
    echo json_encode([
        'success' => true,
        'data' => [
            'categories' => $categories,
            'products' => $products
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>
