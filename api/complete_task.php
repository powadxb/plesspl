<?php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = (int)$_POST['task_id'];

try {
    // Begin transaction for completing task and subtasks
    $DB->beginTransaction();
    
    // Get all subtask IDs
    $subtask_ids = $DB->query(
        "SELECT id FROM tasks WHERE parent_id = ?",
        [$task_id]
    );
    
    // Complete main task
    $DB->query(
        "UPDATE tasks 
         SET is_completed = 1, completed_on = NOW() 
         WHERE id = ? AND user_id = ?",
        [$task_id, $_SESSION['dins_user_id']]
    );
    
    // Complete all subtasks
    if (!empty($subtask_ids)) {
        $subtask_id_list = array_column($subtask_ids, 'id');
        foreach ($subtask_id_list as $sid) {
            $DB->query(
                "UPDATE tasks 
                 SET is_completed = 1, completed_on = NOW() 
                 WHERE id = ? AND user_id = ?",
                [$sid, $_SESSION['dins_user_id']]
            );
        }
    }
    
    $DB->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $DB->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to complete task']);
}