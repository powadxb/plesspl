<?php
require __DIR__.'/../../../php/bootstrap.php';
require __DIR__.'/../rma-permissions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get user ID from session or cookie
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'];

// Get user details - check authorization
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Check if user has rma_manage permission (required to update item statuses)
if(!hasRMAPermission($user_id, 'rma_manage', $DB)) {
    echo json_encode(['success' => false, 'message' => 'Not authorized to update RMA statuses']);
    exit();
}

$rma_id = $_POST['rma_id'] ?? '';
$status = $_POST['status'] ?? '';
$credited_amount = $_POST['credited_amount'] ?? null;
$shipping_tracking = $_POST['shipping_tracking'] ?? null;
$date_sent = $_POST['date_sent'] ?? null;

if(empty($rma_id) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'RMA ID and status required']);
    exit();
}

try {
    // Build update query based on status
    $update_fields = ["status = ?"];
    $params = [$status];

    // Set date_applied if moving to 'applied_for'
    if($status === 'applied_for') {
        $update_fields[] = "date_applied = CURDATE()";
    }

    // Set date_sent and shipping_tracking if moving to 'sent'
    if($status === 'sent') {
        if(!empty($date_sent)) {
            $update_fields[] = "date_sent = ?";
            $params[] = $date_sent;
        } else {
            $update_fields[] = "date_sent = CURDATE()";
        }
        
        if(!empty($shipping_tracking)) {
            $update_fields[] = "shipping_tracking = ?";
            $params[] = $shipping_tracking;
        }
    }

    // Set credited_amount if moving to 'credited'
    if($status === 'credited') {
        if(!empty($credited_amount)) {
            $update_fields[] = "credited_amount = ?";
            $params[] = $credited_amount;
        }
        $update_fields[] = "date_resolved = CURDATE()";
    }

    // Set date_resolved for 'exchanged' and 'rejected'
    if($status === 'exchanged' || $status === 'rejected') {
        $update_fields[] = "date_resolved = CURDATE()";
    }

    // Always update updated_at
    $update_fields[] = "updated_at = NOW()";

    // Add RMA ID to params
    $params[] = $rma_id;

    $update_sql = implode(", ", $update_fields);

    // Execute update
    $DB->query("
        UPDATE rma_items
        SET {$update_sql}
        WHERE id = ?
    ", $params);

    // Check if this item is in a batch and if batch should be auto-completed
    $item_batch = $DB->query("
        SELECT supplier_rma_batch_id 
        FROM rma_items 
        WHERE id = ?
    ", [$rma_id]);
    
    if (!empty($item_batch) && !empty($item_batch[0]['supplier_rma_batch_id'])) {
        $batch_id = $item_batch[0]['supplier_rma_batch_id'];
        
        // Check if ALL items in this batch are now in completed states
        $batch_items = $DB->query("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN status IN ('credited', 'exchanged', 'rejected') THEN 1 ELSE 0 END) as completed_items
            FROM rma_items
            WHERE supplier_rma_batch_id = ?
        ", [$batch_id]);
        
        if (!empty($batch_items)) {
            $total = $batch_items[0]['total_items'];
            $completed = $batch_items[0]['completed_items'];
            
            // If all items are completed, auto-update batch to completed
            if ($total > 0 && $total == $completed) {
                $DB->query("
                    UPDATE rma_supplier_batches
                    SET batch_status = 'completed',
                        date_completed = CURDATE()
                    WHERE id = ?
                    AND batch_status != 'completed'
                ", [$batch_id]);
                
                // Note: batch was auto-completed
                echo json_encode([
                    'success' => true,
                    'message' => 'Status updated successfully. Batch #' . $batch_id . ' is now completed!',
                    'batch_completed' => true,
                    'batch_id' => $batch_id
                ]);
                exit;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>