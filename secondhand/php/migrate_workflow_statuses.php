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
    
    // Get current columns
    $columns = $DB->query("SHOW COLUMNS FROM trade_in_items");
    $column_names = array_column($columns, 'Field');
    
    // Add new fields for workflow
    $new_fields = [
        'test_notes' => "ADD COLUMN `test_notes` TEXT NULL AFTER `compliance_notes`",
        'rejection_reason' => "ADD COLUMN `rejection_reason` TEXT NULL AFTER `test_notes`",
        'agreed_at' => "ADD COLUMN `agreed_at` TIMESTAMP NULL AFTER `completed_at`",
        'paid_at' => "ADD COLUMN `paid_at` TIMESTAMP NULL AFTER `agreed_at`"
    ];
    
    foreach($new_fields as $field => $sql) {
        if(!in_array($field, $column_names)) {
            $DB->query("ALTER TABLE trade_in_items " . $sql);
            $changes[] = "Added column: $field";
        }
    }
    
    // Update status enum to include new workflow statuses
    $status_column = array_filter($columns, function($col) {
        return $col['Field'] === 'status';
    });
    
    if(!empty($status_column)) {
        $status_column = array_values($status_column)[0];
        $type = $status_column['Type'];
        
        // Check if new statuses are present
        if(strpos($type, 'testing') === false || strpos($type, 'customer_withdrew') === false) {
            $DB->query("ALTER TABLE trade_in_items 
                MODIFY COLUMN `status` ENUM(
                    'pending',
                    'testing',
                    'accepted',
                    'rejected',
                    'customer_withdrew',
                    'completed',
                    'cancelled'
                ) NOT NULL DEFAULT 'pending'");
            $changes[] = "Updated status enum with workflow statuses";
        }
    }
    
    // Ensure second_hand_items table has trade_in_item_id reference
    $sh_columns = $DB->query("SHOW COLUMNS FROM second_hand_items");
    $sh_column_names = array_column($sh_columns, 'Field');
    
    if(!in_array('trade_in_item_detail_id', $sh_column_names)) {
        $DB->query("ALTER TABLE second_hand_items 
            ADD COLUMN `trade_in_item_detail_id` INT NULL AFTER `trade_in_reference`,
            ADD KEY `idx_trade_in_item_detail_id` (`trade_in_item_detail_id`)");
        $changes[] = "Added trade_in_item_detail_id to second_hand_items";
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Workflow schema updated successfully',
        'changes' => $changes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration error: ' . $e->getMessage()
    ]);
}
