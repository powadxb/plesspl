<?php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = (int)$_POST['task_id'];
$description = trim($_POST['description']);
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$priority = in_array($_POST['priority'], ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

try {
    $DB->query(
        "UPDATE tasks 
         SET description = ?,
             category_id = ?,
             priority = ?,
             due_date = ?
         WHERE id = ? AND user_id = ?",
        [$description, $category_id, $priority, $due_date, $task_id, $_SESSION['dins_user_id']]
    );
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update task']);
}