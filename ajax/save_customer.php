<?php
// ajax/save_customer.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authorized']));
}

try {
    // Connect to repairs database
    $repairsDB = new PDO(
        "mysql:host=localhost;dbname=sitegroundrepairs;charset=utf8mb4",
        "pcquote",  // Replace with actual username
        "FPxUMQ5e4JxTWMIvFgO7"      // Replace with actual password
    );
    $repairsDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['dins_user_id'];

    $data = [
        'user_id' => $user_id,
        'name' => $_POST['name'],
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'address' => $_POST['address'] ?? '',
        'post_code' => $_POST['post_code'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    error_log("Prepared data: " . print_r($data, true));

    $fields = implode(',', array_keys($data));
    $placeholders = str_repeat('?,', count($data) - 1) . '?';
    
    $query = "INSERT INTO customers ($fields) VALUES ($placeholders)";
    error_log("SQL Query: " . $query);

    $stmt = $repairsDB->prepare($query);
    $stmt->execute(array_values($data));
    $customerId = $repairsDB->lastInsertId();
    
    $data['id'] = $customerId;

    error_log("Customer saved successfully with ID: " . $customerId);

    echo json_encode([
        'success' => true,
        'customer' => $data,
        'message' => 'Customer saved successfully'
    ]);

} catch (Exception $e) {
    error_log("Save Customer Error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save customer',
        'message' => $e->getMessage()
    ]);
}