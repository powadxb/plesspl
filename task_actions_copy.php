<?php
session_start();
require 'php/bootstrap.php';

$user_id = $_SESSION['dins_user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'list':
        $show_completed = isset($_GET['show_completed']) && $_GET['show_completed'] === 'true';
        $category_id = $_GET['category_id'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        // First get total count
        $count_sql = "SELECT COUNT(*) as total FROM tasks t WHERE t.user_id = ?";
        $count_params = [$user_id];
        
        if (!$show_completed) {
            $count_sql .= " AND (t.is_completed = 0 OR t.is_completed IS NULL)";
        }
        
        if ($category_id) {
            $count_sql .= " AND t.category_id = ?";
            $count_params[] = $category_id;
        }
        
        $total_count = $DB->query($count_sql, $count_params)[0]['total'];
        $total_pages = ceil($total_count / $per_page);

        // Then get paginated data
        $sql = "SELECT t.*, tc.category_name 
                FROM tasks t 
                LEFT JOIN task_categories tc ON t.category_id = tc.id 
                WHERE t.user_id = ?";
        $params = [$user_id];
        
        if (!$show_completed) {
            $sql .= " AND (t.is_completed = 0 OR t.is_completed IS NULL)";
        }
        
        if ($category_id) {
            $sql .= " AND t.category_id = ?";
            $params[] = $category_id;
        }
        
        $sql .= " ORDER BY t.task_order, t.added_on DESC LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;

        $tasks = $DB->query($sql, $params);
        $response = [
            'success' => true, 
            'data' => $tasks,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_items' => $total_count,
                'total_pages' => $total_pages
            ]
        ];
        break;

    case 'save':
        try {
            $id = $_POST['id'] ?? null;
            $data = [
                'description' => $_POST['description'],
                'priority' => $_POST['priority'],
                'category_id' => $_POST['category_id'] ?: null,
                'due_date' => $_POST['due_date'] ?: null,
                'parent_id' => $_POST['parent_id'] ?: null,
                'user_id' => $user_id
            ];
            
            if ($id) {
                $DB->query("UPDATE tasks SET 
                           description = ?, 
                           priority = ?, 
                           category_id = ?,
                           due_date = ?,
                           parent_id = ? 
                           WHERE id = ? AND user_id = ?",
                    [$data['description'], $data['priority'], $data['category_id'], 
                     $data['due_date'], $data['parent_id'], $id, $user_id]);
            } else {
                $DB->query("INSERT INTO tasks 
                           (user_id, description, priority, category_id, due_date, parent_id, added_on) 
                           VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$data['user_id'], $data['description'], $data['priority'],
                     $data['category_id'], $data['due_date'], $data['parent_id']]);
            }
            $response = ['success' => true];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;

    case 'complete':
        try {
            $DB->query("UPDATE tasks SET 
                       is_completed = ?, 
                       completed_on = ? 
                       WHERE id = ? AND user_id = ?",
                [$_POST['completed'], 
                 $_POST['completed'] ? date('Y-m-d H:i:s') : null,
                 $_POST['id'], 
                 $user_id]);
            $response = ['success' => true];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;

    case 'delete':
        try {
            $DB->query("DELETE FROM tasks WHERE id = ? AND user_id = ?", 
                [$_POST['id'], $user_id]);
            $response = ['success' => true];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;

    case 'get':
        $task = $DB->query("SELECT * FROM tasks WHERE id = ? AND user_id = ?", 
            [$_GET['id'], $user_id]);
        $response = ['success' => true, 'data' => $task[0] ?? null];
        break;
}

header('Content-Type: application/json');
echo json_encode($response);