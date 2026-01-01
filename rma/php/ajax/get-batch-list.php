<?php
/**
 * Get list of all RMA batches with statistics
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

try {
    // Get all batches with item counts and values
    $query = "
        SELECT 
            b.*,
            CONCAT(u.first_name, ' ', u.last_name) AS creator_name,
            COUNT(i.id) AS item_count,
            SUM(CASE WHEN i.cost_at_creation IS NOT NULL THEN i.cost_at_creation ELSE 0 END) AS total_value,
            SUM(CASE WHEN i.credited_amount IS NOT NULL THEN i.credited_amount ELSE 0 END) AS total_credited,
            DATEDIFF(CURDATE(), b.date_created) AS age_days
        FROM rma_supplier_batches b
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN rma_items i ON i.supplier_rma_batch_id = b.id
        GROUP BY b.id
        ORDER BY b.id DESC
    ";
    
    $batches = $DB->query($query);
    
    // Get statistics
    $statsQuery = "
        SELECT 
            batch_status,
            COUNT(*) AS count
        FROM rma_supplier_batches
        GROUP BY batch_status
    ";
    $stats_raw = $DB->query($statsQuery);
    
    $stats = [];
    foreach($stats_raw as $row) {
        $stats[$row['batch_status']] = $row['count'];
    }
    
    // Get unique suppliers for filter
    $suppliersQuery = "
        SELECT DISTINCT supplier_name 
        FROM rma_supplier_batches 
        ORDER BY supplier_name
    ";
    $suppliers = $DB->query($suppliersQuery);
    $supplier_list = array_column($suppliers, 'supplier_name');
    
    echo json_encode([
        'success' => true,
        'batches' => $batches,
        'stats' => [
            'draft' => $stats['draft'] ?? 0,
            'submitted' => $stats['submitted'] ?? 0,
            'shipped' => $stats['shipped'] ?? 0,
            'completed' => $stats['completed'] ?? 0,
            'cancelled' => $stats['cancelled'] ?? 0
        ],
        'suppliers' => $supplier_list
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
