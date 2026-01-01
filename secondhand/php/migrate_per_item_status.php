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
    
    // Get current columns in trade_in_items_details
    $columns = $DB->query("SHOW COLUMNS FROM trade_in_items_details");
    $column_names = array_column($columns, 'Field');
    
    // Add item_status column for per-item workflow
    if(!in_array('item_status', $column_names)) {
        $DB->query("ALTER TABLE trade_in_items_details 
            ADD COLUMN `item_status` ENUM(
                'pending_test',
                'testing', 
                'accepted',
                'price_revised',
                'rejected',
                'customer_kept'
            ) NULL DEFAULT 'pending_test' AFTER `notes`");
        $changes[] = "Added item_status column - tracks each item individually";
    }
    
    // Add original_price to track price changes
    if(!in_array('original_price', $column_names)) {
        $DB->query("ALTER TABLE trade_in_items_details 
            ADD COLUMN `original_price` DECIMAL(10,2) NULL AFTER `price_paid`");
        $changes[] = "Added original_price column - tracks price revisions";
    }
    
    // Add test_notes per item
    if(!in_array('test_notes', $column_names)) {
        $DB->query("ALTER TABLE trade_in_items_details 
            ADD COLUMN `test_notes` TEXT NULL AFTER `item_status`");
        $changes[] = "Added test_notes per item";
    }
    
    // Add rejection_reason per item
    if(!in_array('rejection_reason', $column_names)) {
        $DB->query("ALTER TABLE trade_in_items_details 
            ADD COLUMN `rejection_reason` TEXT NULL AFTER `test_notes`");
        $changes[] = "Added rejection_reason per item";
    }
    
    // Copy original prices if not already set
    $DB->query("UPDATE trade_in_items_details 
                SET original_price = price_paid 
                WHERE original_price IS NULL AND price_paid IS NOT NULL");
    $changes[] = "Copied existing prices to original_price field";
    
    echo json_encode([
        'success' => true,
        'message' => 'Per-item status tracking added successfully',
        'changes' => $changes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration error: ' . $e->getMessage()
    ]);
}
