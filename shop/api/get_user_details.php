<?php
// API endpoint for user details (api/get_user_details.php)
require_once '../config.php';
require_once '../auth.php';
require_once '../user_manager.php';

header('Content-Type: application/json');

$auth = new Auth(getDB());
if (!$auth->isAuthenticated() || !$auth->hasPermission('admin')) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user ID']);
    exit();
}

$userManager = new UserManager(getDB());
$user = $userManager->getUserDetails($_GET['id']);

if ($user) {
    echo json_encode($user);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
}
