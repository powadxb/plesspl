<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$config = [
    'filename' => 'pless_product_update_' . date('dmyHi') . '.csv',  // Creates format ddmmyyhhmm
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
    
    // Write headers - including stock_status for Magento 2
    fputcsv($output, ['sku', 'price', 'product_online', 'stock_status']);
    
    // Process in batches for better memory management
    $offset = 0;
    
    while(true) {
        $products = $DB->query(
            "SELECT sku, enable, price, stock_status FROM master_products 
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
            
            // Ensure stock_status is 1 or 0
            $stock_status = ($product['stock_status'] == 1) ? '1' : '0';
            
            fputcsv($output, [
                $product['sku'],
                number_format($price, 2, '.', ''),
                ($product['enable'] == 'y' ? '1' : '0'),  // product_online
                $stock_status  // stock_status for Magento 2
            ]);
        }
        
        $offset += $config['batch_size'];
    }
    
    fclose($output);
    exit();
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    die("An error occurred during export. Please check the error logs.");
}