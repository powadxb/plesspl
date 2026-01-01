<?php
require __DIR__.'/../../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    // Get next tracking number
    $result = $DB->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_number, 3) AS UNSIGNED)), 0) + 1 AS next_number
        FROM rma_items
        WHERE tracking_number LIKE 'DR%'
    ");

    $next_number = $result[0]['next_number'] ?? 1;
    $tracking_number = 'DR' . $next_number;

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
