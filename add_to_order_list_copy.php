<?php
session_start();
require 'php/bootstrap.php';

// Check if the user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['dins_user_id']; // Get the logged-in user's ID
$sku = $_POST['sku'] ?? null;
$name = $_POST['name'] ?? null;
$quantity = $_POST['quantity'] ?? 1;

// Validate input
if (empty($sku) && empty($name)) {
    echo json_encode(['success' => false, 'message' => 'SKU or Product Name is required']);
    exit();
}

// Check if the product exists in the master_products table by SKU
if ($sku) {
    $product = $DB->query("SELECT name, manufacturer, ean FROM master_products WHERE sku = ?", [$sku]);

    if ($product) {
        // Populate fields from master_products
        $name = $product[0]['name'];
        $manufacturer = $product[0]['manufacturer'];
        $ean = $product[0]['ean'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid SKU: Product not found']);
        exit();
    }
} else {
    $manufacturer = null;
    $ean = null;
}

// Check if SKU already exists in the order list
$existing = $DB->query("SELECT * FROM order_list WHERE sku = ? AND status = 'pending'", [$sku]);

if ($existing) {
    echo json_encode(['success' => false, 'message' => 'SKU already exists in the order list']);
    exit();
}

// Insert new item into the order list
$DB->query(
    "INSERT INTO order_list (sku, name, manufacturer, ean, quantity, user_id, added_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')",
    [$sku, $name, $manufacturer, $ean, $quantity, $user_id]
);

echo json_encode(['success' => true, 'message' => 'Item added to the order list']);
