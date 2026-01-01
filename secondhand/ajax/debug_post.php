<?php
session_start();
header('Content-Type: application/json');

// Log everything we receive
error_log("=== DEBUG POST DATA ===");
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r(array_keys($_FILES), true));
error_log("======================");

// Return it as JSON
echo json_encode([
    'success' => true,
    'message' => 'Debug endpoint - data received',
    'post_data' => $_POST,
    'files_received' => array_keys($_FILES),
    'post_count' => count($_POST),
    'files_count' => count($_FILES)
]);
