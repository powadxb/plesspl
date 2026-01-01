<?php
require_once '../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    // Get next SH tracking number
    $result = $DB->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_code, 3) AS UNSIGNED)), 0) + 1 AS next_number
        FROM second_hand_items
        WHERE tracking_code LIKE 'SH%'
    ");

    $next_number = $result[0]['next_number'] ?? 1;
    $tracking_number = 'SH' . $next_number;

    echo json_encode([
        'success' => true,
        'tracking_number' => $tracking_number
    ]);
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating tracking number: ' . $e->getMessage()
    ]);
}
?>