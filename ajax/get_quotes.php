<?php
// ajax/get_quotes.php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authorized']));
}

try {
    $conditions = [];
    $params = [];

    if (!empty($_POST['customer'])) {
        $conditions[] = "q.customer_name LIKE ?";
        $params[] = "%" . $_POST['customer'] . "%";
    }

    if (!empty($_POST['status'])) {
        $conditions[] = "q.status = ?";
        $params[] = $_POST['status'];
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    $query = "
        SELECT 
            q.*,
            u.username as created_by_name
        FROM quotation_master q
        LEFT JOIN users u ON q.created_by = u.id
        $whereClause
        ORDER BY q.id DESC
        LIMIT 100
    ";

    $quotes = $DB->query($query, $params);
    echo json_encode($quotes);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load quotes', 'message' => $e->getMessage()]);
}