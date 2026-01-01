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
    
    // Get current columns in trade_in_items
    $current_columns = $DB->query("SHOW COLUMNS FROM trade_in_items");
    $column_names = array_column($current_columns, 'Field');
    
    // Add missing columns
    $columns_to_add = [
        'total_value' => "ADD COLUMN `total_value` decimal(10,2) DEFAULT NULL AFTER `status`",
        'payment_method' => "ADD COLUMN `payment_method` enum('cash','bank_transfer','cash_bank') DEFAULT 'cash' AFTER `total_value`",
        'cash_amount' => "ADD COLUMN `cash_amount` decimal(10,2) DEFAULT 0.00 AFTER `payment_method`",
        'bank_amount' => "ADD COLUMN `bank_amount` decimal(10,2) DEFAULT 0.00 AFTER `cash_amount`",
        'bank_account_name' => "ADD COLUMN `bank_account_name` varchar(255) DEFAULT NULL AFTER `bank_amount`",
        'bank_account_number' => "ADD COLUMN `bank_account_number` varchar(50) DEFAULT NULL AFTER `bank_account_name`",
        'bank_sort_code' => "ADD COLUMN `bank_sort_code` varchar(20) DEFAULT NULL AFTER `bank_account_number`",
        'bank_reference' => "ADD COLUMN `bank_reference` varchar(255) DEFAULT NULL AFTER `bank_sort_code`",
        'completed_at' => "ADD COLUMN `completed_at` timestamp NULL DEFAULT NULL AFTER `created_at`"
    ];
    
    foreach($columns_to_add as $col => $sql) {
        if(!in_array($col, $column_names)) {
            $DB->query("ALTER TABLE trade_in_items " . $sql);
            $changes[] = "Added column: $col";
        }
    }
    
    // Modify status enum to include 'completed' and 'cancelled' if needed
    $status_column = array_filter($current_columns, function($col) {
        return $col['Field'] === 'status';
    });
    
    if(!empty($status_column)) {
        $status_column = array_values($status_column)[0];
        $type = $status_column['Type'];
        
        // Check if 'completed' and 'cancelled' are in the enum
        if(strpos($type, 'completed') === false || strpos($type, 'cancelled') === false) {
            $DB->query("ALTER TABLE trade_in_items 
                MODIFY COLUMN `status` enum('pending','accepted','rejected','processed','completed','cancelled') NOT NULL DEFAULT 'pending'");
            $changes[] = "Updated status enum to include 'completed' and 'cancelled'";
        }
    }
    
    // Update existing records to have total_value = offered_price
    $DB->query("UPDATE trade_in_items SET total_value = offered_price WHERE total_value IS NULL AND offered_price IS NOT NULL");
    $changes[] = "Migrated offered_price to total_value for existing records";
    
    if(empty($changes)) {
        echo json_encode([
            'success' => true,
            'message' => 'Schema is already up to date',
            'changes' => []
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Schema updated successfully',
            'changes' => $changes
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration error: ' . $e->getMessage()
    ]);
}
