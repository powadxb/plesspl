<?php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $categories = $DB->query("SELECT * FROM task_categories ORDER BY category_name");
    echo json_encode(['success' => true, 'categories' => $categories]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}