<?php
session_start();
require '../php/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false]));
}

$quote_id = intval($_POST['quote_id']);
$status = $_POST['status'];
$user_id = $_SESSION['dins_user_id'];

$valid_statuses = ['draft', 'sent', 'accepted', 'rejected', 'converted'];
if (!in_array($status, $valid_statuses)) {
    exit(json_encode(['success' => false, 'message' => 'Invalid status']));
}

$DB->query("UPDATE cctv_quotation_master SET status = ?, modified_by = ?, date_modified = NOW() WHERE id = ?", 
    [$status, $user_id, $quote_id]);

echo json_encode(['success' => true]);