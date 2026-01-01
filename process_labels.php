<?php
session_start();
require 'php/bootstrap.php';

// Check if the user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;

if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$products = $_POST['products'] ?? [];
if (empty($products)) {
    echo json_encode(['success' => false, 'message' => 'No products selected']);
    exit();
}

// Generate ZPL code
$zpl = '';
foreach ($products as $product) {
    $sku = $product['sku'];
    $quantity = intval($product['quantity']);

    // Validate SKU
    $product_data = $DB->query("SELECT name FROM master_products WHERE sku = ?", [$sku]);
    if (empty($product_data)) {
        echo json_encode(['success' => false, 'message' => "Invalid SKU: $sku"]);
        exit();
    }

    $name = $product_data[0]['name'];

    // Generate ZPL for each quantity
    for ($i = 0; $i < $quantity; $i++) {
        $zpl .= "^XA
^FO10,10^A0N,30,30^FD$name^FS
^FO10,120^A0N,60,60^FD$sku^FS
^XZ
";
    }
}

// Save ZPL to a file (adjust the path to your printer's location)
file_put_contents('/path/to/printer/output.zpl', $zpl);

// Optional: Log the print request
foreach ($products as $product) {
    $DB->query(
        "INSERT INTO label_prints (product_sku, quantity) VALUES (?, ?)",
        [$product['sku'], $product['quantity']]
    );
}

echo json_encode(['success' => true, 'message' => 'Labels sent to printer']);
?>
