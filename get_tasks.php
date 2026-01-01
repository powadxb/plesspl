<?php
require 'php/bootstrap.php';

header('Content-Type: application/json');

$user_id = $_GET['user_id'] ?? null;
$show_completed = $_GET['show_completed'] ?? 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID is missing']);
    exit;
}

try {
    $query = "SELECT t.*, c.category_name 
              FROM tasks t 
              LEFT JOIN task_categories c ON t.category_id = c.id 
              WHERE t.user_id = ?";

    if (!$show_completed) {
        $query .= " AND t.is_completed = 0";
    }

    $query .= " ORDER BY t.due_date ASC";

    $tasks = $DB->query($query, [$user_id]);

    if (!empty($tasks)) {
        echo json_encode(['success' => true, 'tasks' => $tasks]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No tasks found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
