<?php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

try {
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        exit(json_encode(['success' => false, 'message' => 'Invalid ID']));
    }

    // Get trade-in details
    $item = $DB->query("
        SELECT ti.*, u.username as created_by_name
        FROM trade_in_items ti
        LEFT JOIN users u ON ti.created_by = u.id
        WHERE ti.id = ?
    ", [$id])[0] ?? null;

    if (!$item) {
        exit(json_encode(['success' => false, 'message' => 'Item not found']));
    }

    // Get customer details from repairs database
    $repairsDB = new PDO(
        "mysql:host=localhost;dbname=sitegroundrepairs;charset=utf8mb4",
        "pcquote",
        "FPxUMQ5e4JxTWMIvFgO7"
    );
    $repairsDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $customer = $repairsDB->prepare("
        SELECT name as customer_name, post_code, phone, email, address
        FROM customers 
        WHERE id = ?
    ");
    $customer->execute([$item['customer_id']]);
    $customer_data = $customer->fetch(PDO::FETCH_ASSOC);
    
    if ($customer_data) {
        $item['customer_name'] = $customer_data['customer_name'];
        $item['customer_post_code'] = $customer_data['post_code'];
        $item['customer_phone'] = $customer_data['phone'];
        $item['customer_email'] = $customer_data['email'];
        $item['customer_address'] = $customer_data['address'];
    } else {
        $item['customer_name'] = 'Unknown Customer';
    }
    
    echo json_encode($item);

} catch (Exception $e) {
    error_log("Get trade-in error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}