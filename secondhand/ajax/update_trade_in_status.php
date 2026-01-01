<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['dins_user_id'];

// Check permissions
$manage_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'",
    [$user_id]
);
$can_manage = !empty($manage_check) && $manage_check[0]['has_access'];

if (!$can_manage) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Access denied']));
}

try {
    $trade_in_id = $_POST['trade_in_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $test_notes = $_POST['test_notes'] ?? null;
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    
    if(!$trade_in_id) {
        throw new Exception('Trade-in ID is required');
    }
    
    // Check if trade-in is already completed
    $trade_in = $DB->query("SELECT status FROM trade_in_items WHERE id = ?", [$trade_in_id])[0] ?? null;
    
    if(!$trade_in) {
        throw new Exception('Trade-in not found');
    }
    
    if($trade_in['status'] === 'completed') {
        throw new Exception('Cannot modify completed trade-in. Once a trade-in is completed and signed, it cannot be changed.');
    }
    
    // Validate status
    $valid_statuses = ['pending', 'testing', 'accepted', 'rejected', 'completed'];
    if(!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status');
    }
    
    // Update trade-in
    $sql = "UPDATE trade_in_items SET 
                status = ?,
                test_notes = ?,
                rejection_reason = ?
            WHERE id = ?";
    
    $DB->query($sql, [$status, $test_notes, $rejection_reason, $trade_in_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
