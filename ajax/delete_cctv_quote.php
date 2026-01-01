<?php
session_start();
require '../php/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false]));
}

$user = $DB->query("SELECT admin FROM users WHERE id = ?", [$_SESSION['dins_user_id']])[0];
if ($user['admin'] < 1) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Permission denied']));
}

$quote_id = intval($_POST['quote_id']);

$DB->beginTransaction();
$DB->query("DELETE FROM cctv_quotation_items WHERE quote_id = ?", [$quote_id]);
$DB->query("DELETE FROM cctv_quotation_master WHERE id = ?", [$quote_id]);
$DB->commit();

echo json_encode(['success' => true]);