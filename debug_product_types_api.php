<?php
// Simple debug file to test the API
session_start();
require 'php/bootstrap.php';

header('Content-Type: application/json');

try {
    // Test database connection
    $test = $DB->query("SELECT 1 as test");
    
    // Test if tables exist
    $tables = $DB->query("SHOW TABLES LIKE 'master_essential_%'");
    
    // Test if essential categories exist
    $categories = $DB->query("SELECT COUNT(*) as count FROM master_essential_categories");
    
    echo json_encode([
        'status' => 'success',
        'db_test' => $test,
        'tables_found' => count($tables),
        'categories_count' => $categories[0]['count'] ?? 0,
        'user_id' => $_SESSION['dins_user_id'] ?? 'not logged in'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>