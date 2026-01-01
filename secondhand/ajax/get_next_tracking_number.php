<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    // Get the last used tracking code
    $last_item = $DB->query("
        SELECT tracking_code 
        FROM trade_in_items_details 
        WHERE tracking_code LIKE 'SH%' 
        ORDER BY tracking_code DESC 
        LIMIT 1
    ");
    
    $next_number = 1;
    
    if(!empty($last_item) && !empty($last_item[0]['tracking_code'])) {
        $last_code = $last_item[0]['tracking_code'];
        
        // Extract number from SH123 format
        if(preg_match('/SH(\d+)/', $last_code, $matches)) {
            $next_number = intval($matches[1]) + 1;
        }
    }
    
    echo json_encode([
        'success' => true,
        'next_number' => $next_number,
        'next_code' => 'SH' . $next_number
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'next_number' => 1  // Fallback to 1 if error
    ]);
}
