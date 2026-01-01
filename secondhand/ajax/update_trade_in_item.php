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
    $item_id = $_POST['item_id'] ?? 0;
    $item_status = $_POST['item_status'] ?? '';
    $condition = $_POST['condition'] ?? null;
    $revised_price = $_POST['revised_price'] ?? null;
    $test_notes = $_POST['test_notes'] ?? null;
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    
    if(!$item_id) {
        throw new Exception('Item ID is required');
    }
    
    // Get item and check if parent trade-in is completed
    $item = $DB->query("
        SELECT tid.*, ti.status as trade_in_status 
        FROM trade_in_items_details tid
        JOIN trade_in_items ti ON tid.trade_in_id = ti.id
        WHERE tid.id = ?
    ", [$item_id])[0] ?? null;
    
    if(!$item) {
        throw new Exception('Item not found');
    }
    
    if($item['trade_in_status'] === 'completed') {
        throw new Exception('Cannot modify items in completed trade-in. Once a trade-in is completed and signed, it cannot be changed.');
    }
    
    // Validate item status
    $valid_statuses = ['pending', 'accepted', 'rejected', 'price_revised'];
    if(!in_array($item_status, $valid_statuses)) {
        throw new Exception('Invalid item status');
    }
    
    // Calculate price to use
    $price_paid = $revised_price ?: $item['price_offered'];
    
    // Update item
    $sql = "UPDATE trade_in_items_details SET 
                item_status = ?,
                `condition` = ?,
                price_paid = ?,
                test_notes = ?,
                rejection_reason = ?
            WHERE id = ?";
    
    $DB->query($sql, [
        $item_status,
        $condition,
        $price_paid,
        $test_notes,
        $rejection_reason,
        $item_id
    ]);
    
    // Calculate new total for this trade-in
    $total = $DB->query("
        SELECT SUM(price_paid) as total
        FROM trade_in_items_details
        WHERE trade_in_id = ?
        AND item_status IN ('accepted', 'price_revised')
    ", [$item['trade_in_id']])[0]['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Item updated successfully',
        'new_total' => floatval($total),
        'item_data' => [
            'item_id' => $item_id,
            'item_status' => $item_status,
            'condition' => $condition,
            'price_paid' => floatval($price_paid),
            'test_notes' => $test_notes,
            'rejection_reason' => $rejection_reason
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
