<?php
require 'php/bootstrap.php';

header('Content-Type: application/json');

// Ensure the session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// Get task ID from POST data
$taskId = $_POST['task_id'] ?? null;

// Validate the task ID
if (empty($taskId) || !is_numeric($taskId)) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing task ID.']);
    exit;
}

// Ensure the user is authenticated
if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit;
}

$userId = $_SESSION['dins_user_id'];

try {
    // Check if the task exists and belongs to the user
    $affectedRows = $DB->query(
        "DELETE FROM tasks WHERE id = ? AND user_id = ?",
        [$taskId, $userId]
    );

    if ($affectedRows > 0) {
        echo json_encode(['success' => true, 'message' => 'Task deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Task not found or not authorised to delete.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
