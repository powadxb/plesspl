<?php
// ajax/add_order_comment.php
session_start();
require '../php/bootstrap.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Not authorized']));
}

$user_id = $_SESSION['dins_user_id'];

try {
    // Get POST data
    $order_id = isset($_POST['orderId']) ? intval($_POST['orderId']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    // Validate inputs
    if (!$order_id) {
        throw new Exception('Invalid order ID');
    }

    if (empty($comment)) {
        throw new Exception('Comment cannot be empty');
    }

    // Check if order exists
    $order = $DB->query("SELECT id FROM system_orders WHERE id = ?", [$order_id]);
    if (empty($order)) {
        throw new Exception('Order not found');
    }

    // Insert the comment
    $DB->query("
        INSERT INTO system_order_comments (order_id, user_id, comment, date_created)
        VALUES (?, ?, ?, NOW())
    ", [$order_id, $user_id, $comment]);

    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to add comment',
        'message' => $e->getMessage()
    ]);
}
?>