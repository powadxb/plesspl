<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Configuration
$config = [
    'filename' => 'Magento_Product_Update_' . date('ymd') . '.csv',  // Creates format yymmdd
    'batch_size' => 1000
];
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
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$config['filename'].'"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers - simplified to 3 columns
    fputcsv($output, ['sku', 'price', 'product_online']);
    
    // Process in batches for better memory management
    $offset = 0;
    
    while(true) {
        $products = $DB->query(
            "SELECT sku, price, stock_status, enable FROM master_products 
            WHERE export_to_magento = 'y' 
            LIMIT ? OFFSET ?", 
            [$config['batch_size'], $offset]
        );
        
        if(empty($products)) {
            if($offset === 0) {
                die('No products found for export');
            }
            break;
        }
        
        foreach($products as $product) {
            // Validate required fields
            if(empty($product['sku'])) {
                continue; // Skip invalid products
            }
            
            // Ensure price is numeric
            $price = is_numeric($product['price']) ? $product['price'] : 0;
            
            // Skip products with zero price
            if($price <= 0) {
                continue;
            }
            
            // Product is online ONLY if BOTH enable='y' AND stock_status=1
            // If enable='n' OR stock_status=0, then product_online=0
            $product_online = ($product['enable'] == 'y' && $product['stock_status'] == 1) ? '1' : '0';
            
            fputcsv($output, [
                $product['sku'],
                number_format($price, 2, '.', ''),
                $product_online
            ]);
            
            // COMMENTED OUT - Previous Magento 2 stock fields
            // $stock_status = ($product['stock_status'] == 1) ? '1' : '0';
            // $qty = 0;
            // $manage_stock = ($product['stock_status'] == 1) ? '0' : '1';
        }
        
        $offset += $config['batch_size'];
    }
    
    fclose($output);
    exit();
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    die("An error occurred during export. Please check the error logs.");
}
?>