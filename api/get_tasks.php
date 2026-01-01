<?php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$show_completed = isset($_GET['show_completed']) ? (int)$_GET['show_completed'] : 0;
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

$offset = ($page - 1) * $per_page;
$where_conditions = ["user_id = ?"];
$params = [$user_id];

if (!$show_completed) {
    $where_conditions[] = "is_completed = 0";
}

if ($category !== 'all') {
    $where_conditions[] = "category_id = ?";
    $params[] = $category;
}

$where_clause = implode(" AND ", $where_conditions);

try {
    $total = $DB->query("SELECT COUNT(*) as count FROM tasks WHERE $where_clause", $params)[0]['count'];
    $total_pages = ceil($total / $per_page);

    $tasks = $DB->query(
        "SELECT t.*, tc.category_name 
         FROM tasks t 
         LEFT JOIN task_categories tc ON t.category_id = tc.id 
         WHERE $where_clause 
         ORDER BY t.task_order, t.added_on DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$per_page, $offset])
    );

    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'total_pages' => $total_pages
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to load tasks']);
}