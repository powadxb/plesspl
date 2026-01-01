<?php
session_start();
require 'php/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;

if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
    exit;
}

$id = $_POST['id'] ?? null;
$public_comment = trim($_POST['public_comment'] ?? '');
$private_comment = trim($_POST['private_comment'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID']);
    exit;
}

try {
    // Convert empty strings to NULL for database
    $public_comment = $public_comment === '' ? null : $public_comment;
    $private_comment = $private_comment === '' ? null : $private_comment;
    
    $result = $DB->query(
        "UPDATE order_list SET public_comment = ?, private_comment = ? WHERE id = ?", 
        [$public_comment, $private_comment, $id]
    );

    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Comments updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update comments'
        ]);
    }

} catch (Exception $e) {
    error_log("Error updating order comments: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>