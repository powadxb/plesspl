<?php
// ajax/search_customers.php
session_start();
require '../php/bootstrap.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authorized']));
}

$search = $_POST['search'] ?? '';

if (empty($search)) {
    exit(json_encode([]));
}

try {
    // Connect to repairs database
    $repairsDB = new PDO(
        "mysql:host=localhost;dbname=sitegroundrepairs;charset=utf8mb4",
        "root",
        ""
    );
    $repairsDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Split search terms
    $terms = preg_split('/\s+/', trim($search));
    $conditions = [];
    $params = [];

    // Build search conditions
    foreach ($terms as $term) {
        $conditions[] = "(
            name LIKE ? OR 
            phone LIKE ? OR 
            mobile LIKE ? OR
            email LIKE ? OR
            post_code LIKE ?
        )";
        $pattern = "%{$term}%";
        $params = array_merge($params, [$pattern, $pattern, $pattern, $pattern, $pattern]);
    }

    $query = "
        SELECT 
            id,
            name,
            phone,
            mobile,
            email,
            address,
            post_code,
            customer_type
        FROM customers
        WHERE " . implode(" AND ", $conditions) . "
        ORDER BY name ASC
        LIMIT 10";

    $stmt = $repairsDB->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}