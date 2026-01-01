<?php
// Login endpoint (api/auth/login.php)
require_once '../core/config.php';
require_once '../core/ApiAuth.php';
require_once '../core/ApiResponse.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    ApiResponse::error('Missing credentials', 400);
}

try {
    $auth = new Auth(getDB());
    if ($auth->login($input['username'], $input['password'])) {
        $apiAuth = new ApiAuth(getDB());
        $token = $apiAuth->generateToken($_SESSION['user_id']);
        ApiResponse::send($token);
    } else {
        ApiResponse::error('Invalid credentials', 401);
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
