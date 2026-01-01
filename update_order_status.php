<?php
session_start();
require 'php/bootstrap.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Retrieve user details
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

if ($user_details['admin'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id = (int)$_POST['id'];
$status = $_POST['status'] == 'ordered' ? 'ordered' : 'pending';
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($status == 'ordered') {
    $DB->query("UPDATE order_list SET status = 'ordered', last_ordered_at = NOW(), quantity = ? WHERE id = ?", [$quantity, $id]);
} else {
    $DB->query("UPDATE order_list SET status = 'pending', quantity = ? WHERE id = ?", [$quantity, $id]);
}

echo json_encode(['success' => true]);
?>
