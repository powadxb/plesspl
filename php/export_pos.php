<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    session_start();
    require __DIR__.'/bootstrap.php';
    
    // Check for logged in user and get details
    if(!isset($_SESSION['dins_user_id'])) {
        die('Not logged in');
    }
    
    $user_id = $_SESSION['dins_user_id'];
    $user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];
    
    // Check admin access
    if(!isset($user_details['admin']) || $user_details['admin'] == 0) {
        die('Access Denied');
    }

    // Get tax rates
    $tax_rates = $DB->query("SELECT * FROM tax_rates ORDER BY tax_rate_id");
    $tax_rate_map = [];
    foreach($tax_rates as $rate) {
        $tax_rate_map[$rate['tax_rate_id']] = $rate['tax_rate'];
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="POS_Product_Import_' . date('ymd') . '.csv"');

    // Get products
    $products = $DB->query("SELECT sku, name, enable, ean, cost, trade, trade_markup, price, retail_markup, 
                           pos_category, tax_rate_id, pricing_method, pricing_cost FROM master_products 
                           WHERE export_to_magento = 'y'");
    
    $output = fopen('php://output', 'w');
    
    // Write headers (ONLY ONCE)
    fputcsv($output, ['sku', 'name', 'active', 'barcode', 'cost', 'trade_price', 'retail_price', 
                      'category', 'pos_category', 'available_pos', 'sale_tax', 'purchase_tax']);
    
    foreach($products as $product) {
        // Get VAT rate
        $vat_rate = isset($tax_rate_map[$product['tax_rate_id']]) ? $tax_rate_map[$product['tax_rate_id']] : 0.2;
        
        // Calculate VAT inclusive prices based on pricing method
        $base_cost = ($product['pricing_method'] == 1) ? $product['pricing_cost'] : $product['cost'];
        
        $trade_inc_vat = $product['trade_markup'] ? 
            ($base_cost + ($base_cost * ($product['trade_markup']/100))) * (1 + $vat_rate) :
            $product['trade'];
            
        $retail_inc_vat = $product['retail_markup'] ? 
            ($base_cost + ($base_cost * ($product['retail_markup']/100))) * (1 + $vat_rate) :
            $product['price'];

        // Map tax codes
        $sale_tax = $product['tax_rate_id'] == 1 ? 'sales20' : 'vatmarg';
        $purchase_tax = $product['tax_rate_id'] == 1 ? 'purchase20' : 'purchaseze';
        
        // First row (without barcode)
        fputcsv($output, [
            $product['sku'],
            $product['name'],
            ($product['enable'] == 'y' ? 'A' : ''),
            '', // Empty barcode
            $product['cost'],
            number_format($trade_inc_vat, 2, '.', ''),
            number_format($retail_inc_vat, 2, '.', ''),
            $product['pos_category'],
            $product['pos_category'],
            '1', // available_pos
            $sale_tax,
            $purchase_tax
        ]);
        
        // Second row if barcode exists
        if(!empty($product['ean'])) {
            fputcsv($output, [
                $product['sku'],
                $product['name'],
                ($product['enable'] == 'y' ? 'A' : ''),
                $product['ean'],
                $product['cost'],
                number_format($trade_inc_vat, 2, '.', ''),
                number_format($retail_inc_vat, 2, '.', ''),
                $product['pos_category'],
                $product['pos_category'],
                '1',
                $sale_tax,
                $purchase_tax
            ]);
        }
    }
    
    fclose($output);
    exit();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    throw $e;
}