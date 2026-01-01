<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

if($user_details['admin'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

try {
    $changes = [];
    
    // These fields were for single-item trade-ins, but now we use trade_in_items_details
    // So make them nullable in the parent table
    $fields_to_nullable = [
        'sku' => "MODIFY COLUMN `sku` varchar(9) NULL",
        'item_name' => "MODIFY COLUMN `item_name` varchar(255) NULL",
        'condition' => "MODIFY COLUMN `condition` enum('excellent','good','fair','poor') NULL",
        'purchase_price' => "MODIFY COLUMN `purchase_price` decimal(10,2) NULL",
        'trade_in_date' => "MODIFY COLUMN `trade_in_date` date NULL"
    ];
    
    foreach($fields_to_nullable as $field => $sql) {
        try {
            $DB->query("ALTER TABLE trade_in_items " . $sql);
            $changes[] = "Made $field nullable";
        } catch (Exception $e) {
            // Field might already be nullable or not exist
            $changes[] = "Skipped $field: " . $e->getMessage();
        }
    }
    
    // Also make sure customer_id can be null (for customers not in repairs DB)
    try {
        $DB->query("ALTER TABLE trade_in_items MODIFY COLUMN `customer_id` bigint(20) unsigned NULL");
        $changes[] = "Made customer_id nullable";
    } catch (Exception $e) {
        $changes[] = "Skipped customer_id: " . $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Schema updated to support multi-item trade-ins',
        'changes' => $changes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration error: ' . $e->getMessage()
    ]);
}
