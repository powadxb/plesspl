<?php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = (int)$_POST['task_id'];

try {
    // Subtasks will be automatically deleted due to ON DELETE CASCADE
    $DB->query(
        "DELETE FROM tasks 
         WHERE id = ? AND user_id = ?",
        [$task_id, $_SESSION['dins_user_id']]
    );
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
}