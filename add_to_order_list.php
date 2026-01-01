<?php
session_start();
require 'php/bootstrap.php';

// Check if the user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['dins_user_id']; // Get the logged-in user's ID
$sku = trim($_POST['sku'] ?? '');
$name = trim($_POST['name'] ?? '');
$non_db_item = trim($_POST['non_db_item'] ?? '');
$quantity = intval($_POST['quantity'] ?? 1);
$order_type = $_POST['order_type'] ?? 'low_stock';

// Validate order_type
$valid_order_types = ['low_stock', 'no_stock', 'for_customer', 'urgent'];
if (!in_array($order_type, $valid_order_types)) {
    $order_type = 'low_stock'; // Default fallback
}

// Validate input
$hasDbInput = !empty($sku) || !empty($name);
$hasNonDbInput = !empty($non_db_item);

if (!$hasDbInput && !$hasNonDbInput) {
    echo json_encode(['success' => false, 'message' => 'SKU, Product Name, or Non-Database Item is required']);
    exit();
}

if ($hasDbInput && $hasNonDbInput) {
    echo json_encode(['success' => false, 'message' => 'Please use either database product fields OR non-database field, not both']);
    exit();
}

if ($quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1']);
    exit();
}

try {
    if ($hasNonDbInput) {
        // Handle non-database item
        
        // Check if this non-database item already exists for this user
        $existing = $DB->query(
            "SELECT * FROM order_list WHERE name = ? AND sku IS NULL AND user_id = ? AND status = 'pending'", 
            [$non_db_item, $user_id]
        );
        
        if ($existing) {
            // Update existing quantity
            $new_quantity = $existing[0]['quantity'] + $quantity;
            $DB->query(
                "UPDATE order_list SET quantity = ?, order_type = ?, added_at = NOW() WHERE id = ?", 
                [$new_quantity, $order_type, $existing[0]['id']]
            );
            echo json_encode([
                'success' => true, 
                'message' => "Updated existing non-database item. New quantity: {$new_quantity}"
            ]);
        } else {
            // Insert new non-database item
            $DB->query(
                "INSERT INTO order_list (sku, name, manufacturer, ean, quantity, user_id, added_at, status, order_type) VALUES (NULL, ?, NULL, NULL, ?, ?, NOW(), 'pending', ?)",
                [$non_db_item, $quantity, $user_id, $order_type]
            );
            echo json_encode([
                'success' => true, 
                'message' => 'Non-database item added to order list successfully'
            ]);
        }
        
    } else {
        // Handle database item (existing logic)
        $product = null;

        // If SKU is provided, validate it exists in master_products
        if (!empty($sku)) {
            $product = $DB->query("SELECT sku, name, manufacturer, ean FROM master_products WHERE sku = ?", [$sku]);
            
            if (empty($product)) {
                echo json_encode(['success' => false, 'message' => 'Invalid SKU: Product not found in database']);
                exit();
            }
            
            $product = $product[0]; // Get the first result
        }

        // If only Name is provided, validate it exists in master_products
        if (empty($sku) && !empty($name)) {
            $product = $DB->query("SELECT sku, name, manufacturer, ean FROM master_products WHERE name = ?", [$name]);
            
            if (empty($product)) {
                echo json_encode(['success' => false, 'message' => 'Invalid Product Name: Product not found in database']);
                exit();
            }
            
            $product = $product[0]; // Get the first result
            $sku = $product['sku']; // Set SKU from database
        }

        // Check if SKU already exists in the order list for this user
        $existing = $DB->query("SELECT * FROM order_list WHERE sku = ? AND user_id = ? AND status = 'pending'", [$product['sku'], $user_id]);
        if ($existing) {
            // Update existing quantity instead of creating duplicate
            $new_quantity = $existing[0]['quantity'] + $quantity;
            $DB->query("UPDATE order_list SET quantity = ?, order_type = ?, added_at = NOW() WHERE id = ?", [$new_quantity, $order_type, $existing[0]['id']]);
            echo json_encode(['success' => true, 'message' => "Updated existing order. New quantity: {$new_quantity}"]);
            exit();
        }

        // Insert the item into the order list using validated product data
        $DB->query(
            "INSERT INTO order_list (sku, name, manufacturer, ean, quantity, user_id, added_at, status, order_type) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending', ?)",
            [$product['sku'], $product['name'], $product['manufacturer'], $product['ean'], $quantity, $user_id, $order_type]
        );

        echo json_encode(['success' => true, 'message' => 'Product added to the order list']);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>