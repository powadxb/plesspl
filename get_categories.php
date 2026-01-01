<?php
require 'php/bootstrap.php';

header('Content-Type: application/json');

try {
    $categories = $DB->query("SELECT id, category_name FROM task_categories");

    if (!empty($categories)) {
        echo json_encode(['success' => true, 'categories' => $categories]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No categories found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
