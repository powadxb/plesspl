<?php
/**
 * Get detailed information about a specific batch
 */

require __DIR__.'/../../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get user details from database
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Authorization check
$is_authorized = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);
if (!$is_authorized) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$batch_id = $_GET['batch_id'] ?? null;

if (!$batch_id) {
    echo json_encode(['success' => false, 'message' => 'Batch ID required']);
    exit;
}

try {
    // Get batch details
    $batchQuery = "
        SELECT 
            b.*,
            CONCAT(u.first_name, ' ', u.last_name) AS creator_name
        FROM rma_supplier_batches b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.id = ?
    ";
    
    $batches = $DB->query($batchQuery, [$batch_id]);
    
    if (empty($batches)) {
        echo json_encode(['success' => false, 'message' => 'Batch not found']);
        exit;
    }
    
    $batch = $batches[0];
    
    // Get items in this batch
    $itemsQuery = "
        SELECT 
            i.*,
            f.fault_name
        FROM rma_items i
        LEFT JOIN rma_fault_types f ON i.fault_type_id = f.id
        WHERE i.supplier_rma_batch_id = ?
        ORDER BY i.id
    ";
    
    $items = $DB->query($itemsQuery, [$batch_id]);
    
    // Calculate statistics
    $total_items = count($items);
    $total_value = 0;
    $total_credited = 0;
    $status_counts = [
        'rma_number_issued' => 0,
        'applied_for' => 0,
        'sent' => 0,
        'credited' => 0,
        'exchanged' => 0,
        'rejected' => 0
    ];
    
    foreach ($items as $item) {
        $total_value += $item['cost_at_creation'] ?? 0;
        $total_credited += $item['credited_amount'] ?? 0;
        
        if (isset($status_counts[$item['status']])) {
            $status_counts[$item['status']]++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'batch' => $batch,
        'items' => $items,
        'statistics' => [
            'total_items' => $total_items,
            'total_value' => $total_value,
            'total_credited' => $total_credited,
            'pending_value' => $total_value - $total_credited,
            'status_counts' => $status_counts
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
