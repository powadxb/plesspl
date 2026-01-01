<?php
/**
 * Create new supplier RMA batch
 */
require __DIR__.'/../../../php/bootstrap.php';
require __DIR__.'/../rma-permissions.php';
header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get user ID from session or cookie
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'];

// Get user details from database
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Authorization check - must have rma_batches permission
if (!hasRMAPermission($user_id, 'rma_batches', $DB)) {
    echo json_encode(['success' => false, 'message' => 'Not authorized to create batches']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$supplier = $data['supplier'] ?? null;
$rma_number = $data['rma_number'] ?? null;
$status = $data['status'] ?? 'draft';
$notes = $data['notes'] ?? null;
$item_ids = $data['item_ids'] ?? [];

// Validation
if (!$supplier || empty($item_ids)) {
    echo json_encode([
        'success' => false,
        'message' => 'Supplier and at least one item required'
    ]);
    exit;
}

if (!in_array($status, ['draft', 'submitted'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]);
    exit;
}

try {
    $DB->query("START TRANSACTION");
    
    // Create batch record
    $insertQuery = "
        INSERT INTO rma_supplier_batches (
            supplier_name,
            supplier_rma_number,
            batch_status,
            date_created,
            date_submitted,
            notes,
            created_by
        ) VALUES (
            ?, ?, ?, CURDATE(), ?, ?, ?
        )
    ";
    
    $DB->query($insertQuery, [
        $supplier,
        $rma_number,
        $status,
        ($status === 'submitted') ? date('Y-m-d') : null,
        $notes,
        $user_id
    ]);
    
    // Get the last inserted batch ID (DO NOT use $DB->insert_id() - method doesn't exist)
    $batch_id = $DB->query("SELECT LAST_INSERT_ID() as id")[0]['id'];
    
    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    
    // Link items to batch and update their status
    $updateQuery = "
        UPDATE rma_items 
        SET supplier_rma_batch_id = ?,
            status = 'rma_number_issued'
        WHERE id IN ($placeholders)
          AND status = 'unprocessed'
          AND supplier_name = ?
          AND supplier_rma_batch_id IS NULL
    ";
    
    $params = array_merge([$batch_id], $item_ids, [$supplier]);
    $DB->query($updateQuery, $params);
    
    // Verify items were updated by counting them in the batch
    $verify_result = $DB->query(
        "SELECT COUNT(*) as count FROM rma_items WHERE supplier_rma_batch_id = ?",
        [$batch_id]
    );
    $updated_count = $verify_result[0]['count'];
    
    if ($updated_count !== count($item_ids)) {
        $DB->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => 'Some items could not be added (already processed or from different supplier)'
        ]);
        exit;
    }
    
    $DB->query("COMMIT");
    
    echo json_encode([
        'success' => true,
        'message' => "Batch #{$batch_id} created with {$updated_count} items",
        'batch_id' => $batch_id
    ]);
    
} catch (Exception $e) {
    $DB->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}