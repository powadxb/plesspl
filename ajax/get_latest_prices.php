<?php
// ajax/get_latest_prices.php
session_start();
header('Content-Type: application/json');

require '../php/bootstrap.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Not authorized']));
}

try {
    $skus = json_decode($_POST['skus'], true);
    
    if (!$skus || !is_array($skus)) {
        throw new Exception('Invalid SKU list');
    }
    
    $prices = [];
    
    // Fetch current prices for each SKU from your products table
    foreach ($skus as $sku) {
        // Adjust table/column names to match your database
        $product = $DB->query("
            SELECT 
                sku,
                retail_price,
                trade_price,
                cost
            FROM master_products 
            WHERE sku = ?
            LIMIT 1
        ", [$sku]);
        
        if (!empty($product)) {
            $prices[$sku] = [
                'sku' => $product[0]['sku'],
                'retail_price' => floatval($product[0]['retail_price'] ?? 0),
                'trade_price' => floatval($product[0]['trade_price'] ?? 0),
                'cost' => floatval($product[0]['cost'] ?? 0)
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'prices' => $prices
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}