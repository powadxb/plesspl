<?php
require 'bootstrap.php';
require 'permissions_helper.php'; // Include the shared permission functions

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

// Make sure $user_id is set (should be set in bootstrap.php)
if (!isset($user_id) || !$user_id) {
    http_response_code(400);
    die('User ID not available');
}

// Get user details
try {
    $user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id]);
    if (empty($user_details)) {
        http_response_code(404);
        die('User not found');
    }
    $user_details = $user_details[0];
} catch (Exception $e) {
    error_log("User lookup error: " . $e->getMessage());
    http_response_code(500);
    die('Database error');
}

// Check permission using the shared function
if (!hasPermission($user_details['id'], 'edit_stock_status', $DB)) {
    http_response_code(403);
    die('Insufficient permissions - edit_stock_status required');
}

// Check if required POST data is present
if (!isset($_POST['sku'], $_POST['stock_status'])) {
    http_response_code(400);
    die('Missing required data: sku and stock_status required');
}

$sku = trim($_POST['sku']);
$stock_status = (int)$_POST['stock_status'];

// Validate inputs
if (empty($sku)) {
    http_response_code(400);
    die('Invalid SKU');
}

if ($stock_status !== 0 && $stock_status !== 1) {
    http_response_code(400);
    die('Invalid stock status value: must be 0 or 1');
}

try {
    // First check if the product exists
    $product_check = $DB->query("SELECT COUNT(*) as count FROM master_products WHERE sku = ?", [$sku]);
    if ($product_check[0]['count'] == 0) {
        http_response_code(404);
        die('Product not found');
    }

    // Update only the stock_status field for the specific SKU
    $affected_rows = $DB->query(
        "UPDATE master_products SET stock_status = ? WHERE sku = ?", 
        [$stock_status, $sku]
    );

    if ($affected_rows > 0) {
        // Log the change for audit purposes
        error_log("Stock status updated: SKU=$sku, Status=$stock_status, User={$user_details['id']}");
        echo 'updated';
    } else {
        error_log("No rows affected for stock status update: SKU=$sku");
        echo 'No changes made';
    }
} catch (Exception $e) {
    error_log("Stock status update error: " . $e->getMessage());
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
}
?>