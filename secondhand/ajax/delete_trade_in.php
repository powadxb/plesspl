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
    echo json_encode(['success' => false, 'message' => 'Invalid trade-in ID']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Load permission functions
$permissions_file = __DIR__.'/../php/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

// Check permissions
$is_admin = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);
$can_manage = $is_admin;

// Only check the permission function if it exists
if (!$is_admin && function_exists('hasSecondHandPermission')) {
    $can_manage = hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB);
}

if (!$can_manage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$trade_in_id = (int)$_POST['id'];

try {
    // Get the trade-in before deleting for logging
    $trade_in = $DB->query("SELECT id, item_name, customer_name, `condition` FROM trade_in_items WHERE id = ?", [$trade_in_id]);
    
    if (empty($trade_in)) {
        echo json_encode(['success' => false, 'message' => 'Trade-in not found']);
        exit();
    }
    
    // Delete the trade-in
    $DB->query("DELETE FROM trade_in_items WHERE id = ?", [$trade_in_id]);
    
    // Log the deletion
    $log_sql = "INSERT INTO second_hand_audit_log (user_id, item_id, action, action_details) 
                VALUES (?, ?, 'delete_trade_in', ?)";
    $log_details = json_encode([
        'deleted_by' => $user_id,
        'deleted_at' => date('Y-m-d H:i:s'),
        'item_name' => $trade_in[0]['item_name']
    ]);
    $DB->query($log_sql, [$user_id, $trade_in_id, $log_details]);
    
    echo json_encode(['success' => true, 'message' => 'Trade-in deleted successfully']);
} catch (Exception $e) {
    error_log("Error deleting trade-in: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting trade-in: ' . $e->getMessage()]);
}
?>