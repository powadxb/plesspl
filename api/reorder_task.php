// api/reorder_task.php
header('Content-Type: application/json');
require_once '../php/bootstrap.php';
session_start();

if (!isset($_SESSION['dins_user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_id = $_SESSION['dins_user_id'];
$task_id = (int)$_POST['task_id'];
$prev_id = (int)$_POST['prev_id'];
$next_id = (int)$_POST['next_id'];

// Get orders of surrounding tasks
$orders = $DB->query(
    "SELECT task_order FROM tasks 
     WHERE id IN (?, ?) AND user_id = ?",
    [$prev_id, $next_id, $user_id]
);

$prev_order = $prev_id ? ($orders[0]['task_order'] ?? 0) : 0;
$next_order = $next_id ? ($orders[1]['task_order'] ?? 0) : $prev_order + 2;

// Calculate new order
$new_order = ($prev_order + $next_order) / 2;

$DB->query(
    "UPDATE tasks SET task_order = ? WHERE id = ? AND user_id = ?",
    [$new_order, $task_id, $user_id]
);

echo json_encode(['success' => true]);