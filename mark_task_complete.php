<?php
require 'php/bootstrap.php';

header('Content-Type: application/json');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method not allowed
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate user authentication
if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit;
}

$userId = $_SESSION['dins_user_id'];

// Validate task ID
$taskId = $_POST['task_id'] ?? null;
if (!$taskId || !is_numeric($taskId)) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing task ID.']);
    exit;
}

try {
    // Check if the task exists and belongs to the user
    $task = $DB->query("SELECT id FROM tasks WHERE id = ? AND user_id = ?", [$taskId, $userId]);
    if (empty($task)) {
        echo json_encode(['success' => false, 'error' => 'Task not found or not authorised to modify.']);
        exit;
    }

    // Update the task to mark it as completed
    $DB->query(
        "UPDATE tasks SET is_completed = 1, completed_on = NOW() WHERE id = ? AND user_id = ?",
        [$taskId, $userId]
    );

    echo json_encode(['success' => true, 'message' => 'Task marked as complete.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
