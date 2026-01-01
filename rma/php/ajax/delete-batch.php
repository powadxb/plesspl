<?php
/**
 * Delete a batch (only if in draft status)
 * Unlinks all items from the batch
 */

require __DIR__.'/../../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get user details from database
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Authorization check (admin only for deletes)
if ($user_details['admin'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$batch_id = $data['batch_id'] ?? null;

if (!$batch_id) {
    echo json_encode(['success' => false, 'message' => 'Batch ID required']);
    exit;
}

try {
    $DB->query("START TRANSACTION");
    
    // Check batch status - only allow deleting draft batches
    $checkQuery = "SELECT batch_status FROM rma_supplier_batches WHERE id = ?";
    $result = $DB->query($checkQuery, [$batch_id]);
    
    if (empty($result)) {
        $DB->query("ROLLBACK");
        echo json_encode(['success' => false, 'message' => 'Batch not found']);
        exit;
    }
    
    $batch_status = $result[0]['batch_status'];
    
    if ($batch_status !== 'draft') {
        $DB->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => 'Only draft batches can be deleted. Current status: ' . $batch_status
        ]);
        exit;
    }
    
    // Unlink items from batch and reset status to unprocessed
    $unlinkQuery = "
        UPDATE rma_items 
        SET supplier_rma_batch_id = NULL,
            status = 'unprocessed'
        WHERE supplier_rma_batch_id = ?
    ";
    $DB->query($unlinkQuery, [$batch_id]);
    $items_unlinked = $DB->affected_rows();
    
    // Delete the batch
    $deleteQuery = "DELETE FROM rma_supplier_batches WHERE id = ?";
    $DB->query($deleteQuery, [$batch_id]);
    
    $DB->query("COMMIT");
    
    echo json_encode([
        'success' => true,
        'message' => "Batch #{$batch_id} deleted. {$items_unlinked} items reset to unprocessed status."
    ]);
    
} catch (Exception $e) {
    $DB->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
