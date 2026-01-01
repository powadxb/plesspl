<?php
session_start();
require 'php/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];

// Check if user has supplier availability permission
$supplier_permission = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'supplier_availability'", 
    [$user_id]
);

if (empty($supplier_permission) || !$supplier_permission[0]['has_access']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Get EAN from request
$ean = trim($_GET['ean'] ?? '');

if (empty($ean)) {
    echo json_encode([]);
    exit();
}

try {
    // Query all_supplier_stock table for suppliers with this EAN
    $query = "
        SELECT 
            supplier,
            supplier_sku,
            qty,
            FORMAT(cost, 2) as cost,
            DATE_FORMAT(time_recorded, '%d/%m/%Y %H:%i') as time_recorded,
            name,
            manufacturer
        FROM all_supplier_stock 
        WHERE ean = ? 
        AND qty > 0 
        ORDER BY cost ASC, qty DESC
    ";
    
    $suppliers = $DB->query($query, [$ean]);
    
    // Format the response
    $response = [];
    foreach ($suppliers as $supplier) {
        $response[] = [
            'supplier' => $supplier['supplier'],
            'supplier_sku' => $supplier['supplier_sku'],
            'qty' => (int)$supplier['qty'],
            'cost' => $supplier['cost'],
            'time_recorded' => $supplier['time_recorded'],
            'name' => $supplier['name'],
            'manufacturer' => $supplier['manufacturer']
        ];
    }
    
    // Set appropriate headers
    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=1800'); // Cache for 30 minutes
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>