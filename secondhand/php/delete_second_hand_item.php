<?php
session_start();
require_once '../../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check permissions directly from database
$manage_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'",
    [$user_id]
);
$can_manage = !empty($manage_check) && $manage_check[0]['has_access'];

if (!$can_manage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$item_id = (int)$_POST['id'];

try {
    // Get the item before deleting for logging
    $item = $DB->query("SELECT * FROM second_hand_items WHERE id = ?", [$item_id]);
    
    if (empty($item)) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit();
    }
    
    // Delete the item
    $DB->query("DELETE FROM second_hand_items WHERE id = ?", [$item_id]);
    
    // Log the deletion
    $log_sql = "INSERT INTO second_hand_audit_log (user_id, item_id, action, action_details) 
                VALUES (?, ?, 'delete_second_hand_item', ?)";
    $log_details = json_encode([
        'deleted_by' => $user_id,
        'deleted_at' => date('Y-m-d H:i:s'),
        'item_name' => $item[0]['item_name']
    ]);
    $DB->query($log_sql, [$user_id, $item_id, $log_details]);
    
    echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
} catch (Exception $e) {
    error_log("Error deleting item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting item: ' . $e->getMessage()]);
}
?>