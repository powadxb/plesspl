<?php
require '../php/bootstrap.php';

// Check admin permissions
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
if($user_details['admin'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$item = $DB->query("SELECT * FROM second_hand_items WHERE id = ?", [$id])[0];

echo json_encode(['success' => true, 'item' => $item]);
?>