<?php
/**
 * Update batch information
 * Supports updating various batch fields
 */

require __DIR__.'/../../../php/bootstrap.php';
require __DIR__.'/../rma-permissions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get user ID from session
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'];

// Get user details from database
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Check if user has batch management permission
if (!hasRMAPermission($user_id, 'rma_batches', $DB)) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$batch_id = $data['batch_id'] ?? null;
$update_type = $data['update_type'] ?? null;

if (!$batch_id || !$update_type) {
    echo json_encode(['success' => false, 'message' => 'Batch ID and update type required']);
    exit;
}

try {
    // First check if batch exists and get current status
    $checkQuery = "SELECT batch_status FROM rma_supplier_batches WHERE id = ?";
    $result = $DB->query($checkQuery, [$batch_id]);
    
    if (empty($result)) {
        echo json_encode(['success' => false, 'message' => 'Batch not found']);
        exit;
    }
    
    $current_status = $result[0]['batch_status'];
    
    // PERMISSION CHECK: Prevent editing completed batches unless user has rma_batch_admin permission
    $can_edit_completed = hasRMAPermission($user_id, 'rma_batch_admin', $DB);
    
    if ($current_status === 'completed' && !$can_edit_completed) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot edit completed batch. Contact administrator if changes are needed.'
        ]);
        exit;
    }
    
    // Handle different update types
    switch ($update_type) {
        
        case 'rma_number':
            $rma_number = $data['rma_number'] ?? null;
            $query = "UPDATE rma_supplier_batches SET supplier_rma_number = ? WHERE id = ?";
            $params = [$rma_number, $batch_id];
            $message = 'RMA number updated';
            break;
            
        case 'status':
            $new_status = $data['status'] ?? null;
            $valid_statuses = ['draft', 'submitted', 'shipped', 'completed', 'cancelled'];
            
            if (!in_array($new_status, $valid_statuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            // Build update query with auto-dates
            $updates = ["batch_status = ?"];
            $params = [$new_status];
            
            if ($new_status === 'submitted' && $current_status === 'draft') {
                $updates[] = "date_submitted = CURDATE()";
            }
            if ($new_status === 'shipped' && !in_array($current_status, ['shipped', 'completed'])) {
                $updates[] = "date_shipped = CURDATE()";
            }
            if ($new_status === 'completed') {
                $updates[] = "date_completed = CURDATE()";
            }
            
            $query = "UPDATE rma_supplier_batches SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $batch_id;
            $message = 'Status updated to ' . $new_status;
            break;
            
        case 'shipping':
            $date_submitted = $data['date_submitted'] ?? null;
            $date_shipped = $data['date_shipped'] ?? null;
            $shipping_tracking = $data['shipping_tracking'] ?? null;
            $shipping_cost = $data['shipping_cost'] ?? null;
            
            $query = "
                UPDATE rma_supplier_batches 
                SET date_submitted = ?,
                    date_shipped = ?,
                    shipping_tracking = ?,
                    shipping_cost = ?
                WHERE id = ?
            ";
            $params = [
                $date_submitted ?: null,
                $date_shipped ?: null,
                $shipping_tracking,
                $shipping_cost ?: null,
                $batch_id
            ];
            $message = 'Shipping information updated';
            break;
            
        case 'notes':
            $notes = $data['notes'] ?? null;
            $query = "UPDATE rma_supplier_batches SET notes = ? WHERE id = ?";
            $params = [$notes, $batch_id];
            $message = 'Notes updated';
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown update type']);
            exit;
    }
    
    $DB->query($query, $params);
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}