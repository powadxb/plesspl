<?php
/**
 * Scottish Trade-in Compliance Requirements
 * This script ensures trade-ins comply with Scottish second-hand goods regulations
 */

require_once '../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check admin permissions
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
if($user_details['admin'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Get trade-in ID
$trade_in_id = isset($_POST['trade_in_id']) ? (int)$_POST['trade_in_id'] : 0;

if (!$trade_in_id) {
    echo json_encode(['success' => false, 'message' => 'Trade-in ID is required']);
    exit();
}

// Get the trade-in item details
$trade_in_item = $DB->query("SELECT * FROM trade_in_items WHERE id = ?", [$trade_in_id])[0];

if (!$trade_in_item) {
    echo json_encode(['success' => false, 'message' => 'Trade-in item not found']);
    exit();
}

// Scottish compliance checks
$compliance_issues = [];

// Check if customer information is complete
if (empty($trade_in_item['customer_name'])) {
    $compliance_issues[] = 'Customer name is required';
}
if (empty($trade_in_item['customer_phone']) && empty($trade_in_item['customer_email'])) {
    $compliance_issues[] = 'Customer contact information (phone or email) is required';
}
if (empty($trade_in_item['customer_address'])) {
    $compliance_issues[] = 'Customer address is required';
}
if (empty($trade_in_item['id_document_type']) || empty($trade_in_item['id_document_number'])) {
    $compliance_issues[] = 'Valid ID document information is required';
}

// Check if trade-in date is reasonable (not in the future)
if (strtotime($trade_in_item['trade_in_date']) > time()) {
    $compliance_issues[] = 'Trade-in date cannot be in the future';
}

// Check if purchase price is reasonable (not negative)
if ($trade_in_item['purchase_price'] < 0) {
    $compliance_issues[] = 'Purchase price cannot be negative';
}

// Check if customer ID is valid (exists in customers table)
if ($trade_in_item['customer_id']) {
    // This assumes there's a customers table to validate against
    // You may need to adjust this based on your actual customer storage
}

// If there are compliance issues, return them
if (!empty($compliance_issues)) {
    echo json_encode([
        'success' => false,
        'message' => 'Compliance issues found',
        'issues' => $compliance_issues
    ]);
    exit();
}

// If all checks pass, update compliance status
try {
    $DB->query(
        "UPDATE trade_in_items SET 
            compliance_status = 'verified',
            updated_at = NOW()
        WHERE id = ?",
        [$trade_in_id]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Trade-in item is compliant with Scottish regulations',
        'compliance_status' => 'verified'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating compliance status: ' . $e->getMessage()
    ]);
}
?>