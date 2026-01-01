<?php
require 'php/bootstrap.php';

header('Content-Type: application/json');

// Ensure user is authenticated
if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User is not authenticated.']);
    exit;
}

$userId = $_SESSION['dins_user_id']; // Get user_id from session

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// Validate required fields
$taskDescription = trim($_POST['task_description'] ?? '');

// Optional fields
$taskCategory = isset($_POST['task_category']) && !empty($_POST['task_category']) ? $_POST['task_category'] : null;
$dueDate = isset($_POST['due_date']) && !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$priority = isset($_POST['priority']) && !empty($_POST['priority']) ? $_POST['priority'] : 'Medium';

// Ensure mandatory field is provided
if (empty($taskDescription)) {
    echo json_encode(['success' => false, 'error' => 'Task description is required.']);
    exit;
}

try {
    // Insert task into the database
    $query = "INSERT INTO tasks (user_id, description, added_on, category_id, due_date, priority) 
              VALUES (?, ?, NOW(), ?, ?, ?)";
    $DB->query($query, [
        $userId,
        $taskDescription,
        $taskCategory, // NULL if not set
        $dueDate,      // NULL if not set
        $priority      // Defaults to 'Medium' if not set
    ]);

    echo json_encode(['success' => true, 'message' => 'Task added successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
