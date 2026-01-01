<?php
require_once '../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    // Get next DSH preprinted code
    $result = $DB->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(preprinted_code, 4) AS UNSIGNED)), 0) + 1 AS next_number
        FROM second_hand_items
        WHERE preprinted_code LIKE 'DSH%'
    ");

    $next_number = $result[0]['next_number'] ?? 1;
    $preprinted_code = 'DSH' . $next_number;

    echo json_encode([
        'success' => true,
        'preprinted_code' => $preprinted_code
    ]);
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating preprinted code: ' . $e->getMessage()
    ]);
}
?>