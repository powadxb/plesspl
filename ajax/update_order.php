<?php
// ajax/update_order.php
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
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $due_date = isset($_POST['dueDate']) ? $_POST['dueDate'] : null;
    $priority_level = isset($_POST['priorityLevel']) ? intval($_POST['priorityLevel']) : 0;

    // Validate inputs
    if (!$order_id) {
        throw new Exception('Invalid order ID');
    }

    $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status');
    }

    // Start transaction
    $DB->beginTransaction();

    // Check if order exists
    $order = $DB->query("SELECT id, status FROM system_orders WHERE id = ?", [$order_id]);
    if (empty($order)) {
        throw new Exception('Order not found');
    }

    // Update the order
    $DB->query("
        UPDATE system_orders 
        SET status = ?, 
            due_date = ?, 
            order_level = ?,
            modified_by = ?,
            date_modified = NOW()
        WHERE id = ?
    ", [$status, $due_date ?: null, $priority_level, $user_id, $order_id]);

    // Add a comment about the status change if status changed
    $old_status = $order[0]['status'];
    if ($old_status !== $status) {
        $comment = "Status changed from '" . ucfirst(str_replace('_', ' ', $old_status)) . 
                   "' to '" . ucfirst(str_replace('_', ' ', $status)) . "'";
        
        $DB->query("
            INSERT INTO system_order_comments (order_id, user_id, comment, date_created)
            VALUES (?, ?, ?, NOW())
        ", [$order_id, $user_id, $comment]);
    }

    $DB->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully'
    ]);

} catch (Exception $e) {
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update order',
        'message' => $e->getMessage()
    ]);
}
?>