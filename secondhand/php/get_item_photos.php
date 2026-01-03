<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $item_id = $_GET['item_id'] ?? 0;
    
    if(!$item_id) {
        throw new Exception('Item ID is required');
    }
    
    // Get photos for this item
    $photos = $DB->query("
        SELECT id, file_path
        FROM second_hand_item_photos
        WHERE item_id = ? AND file_type = 'item_photo'
        ORDER BY upload_date ASC
    ", [$item_id]);
    
    echo json_encode([
        'success' => true,
        'photos' => $photos
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
