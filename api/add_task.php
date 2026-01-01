<?php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$description = trim($_POST['description']);
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$priority = in_array($_POST['priority'], ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

try {
    $sql = "INSERT INTO tasks (user_id, description, priority, category_id, due_date, parent_id, added_on) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $DB->query($sql, [$user_id, $description, $priority, $category_id, $due_date, $parent_id]);
    echo json_encode(['success' => true, 'message' => 'Task added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to add task']);
}