<?php
require_once(__DIR__ . '/../lib/ripcord/ripcord.php');

$odoo_url = 'http://192.168.1.133:8069';
$odoo_db = 'commercest_v7';
$odoo_username = 'odooreadonly';
$odoo_password = 'Amjadis$2';

try {
    echo "Step 1: Creating XML-RPC client for common endpoint...<br>";
    $common = ripcord::client("$odoo_url/xmlrpc/2/common");
    echo "Common endpoint client created.<br>";
    
    echo "Step 2: Testing server version...<br>";
    $version = $common->version();
    echo "Server version info: <pre>" . print_r($version, true) . "</pre><br>";

    echo "Step 3: Attempting authentication...<br>";
    echo "Database: $odoo_db<br>";
    echo "Username: $odoo_username<br>";
    
    $uid = $common->authenticate($odoo_db, $odoo_username, $odoo_password, array());
    
    if ($uid) {
        echo "Successfully authenticated with UID: $uid<br>";
        
        echo "Step 4: Testing product data access...<br>";
        $models = ripcord::client("$odoo_url/xmlrpc/2/object");
        
        // Fetch products
        $products = $models->execute_kw(
            $odoo_db, 
            $uid, 
            $odoo_password,
            'product.product',
            'search_read',
            array(array()),
            array('fields' => array('id', 'default_code', 'name'), 'limit' => 5)
        );
        
        echo "First 5 products:<pre>";
        print_r($products);
        echo "</pre><br>";

        // Fetch stock quantities by location
        echo "Step 5: Testing stock quantities by location...<br>";

        $locations = [19, 12]; // 960 Argyle St and Commerce St
        $quants = $models->execute_kw(
            $odoo_db,
            $uid,
            $odoo_password,
            'stock.quant',
            'search_read',
            array(array(array('location_id', 'in', $locations))),
            array('fields' => array('product_id', 'quantity', 'reserved_quantity', 'location_id'))
        );
        
        echo "Stock quantities for specified locations:<pre>";
        print_r($quants);
        echo "</pre>";

    } else {
        echo "Authentication failed - invalid credentials<br>";
    }
    
} catch (Exception $e) {
    echo "Error occurred: " . $e->getMessage() . "<br>";
    echo "Error trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
