<?php
/**
 * Get available items for batch creation
 * Returns unprocessed items with supplier info, grouped by supplier
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

$supplier = $_GET['supplier'] ?? null;

try {
    if ($supplier) {
        // Get items for specific supplier
        $query = "
            SELECT 
                i.*,
                f.fault_name
            FROM rma_items i
            LEFT JOIN rma_fault_types f ON i.fault_type_id = f.id
            WHERE i.status = 'unprocessed'
              AND i.supplier_name = ?
              AND i.supplier_rma_batch_id IS NULL
            ORDER BY i.date_discovered DESC, i.id DESC
        ";
        
        $items = $DB->query($query, [$supplier]);
        
        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
        
    } else {
        // Get list of suppliers with unprocessed items
        $query = "
            SELECT 
                supplier_name,
                COUNT(*) AS item_count,
                SUM(CASE WHEN cost_at_creation IS NOT NULL THEN cost_at_creation ELSE 0 END) AS total_value
            FROM rma_items
            WHERE status = 'unprocessed'
              AND supplier_name IS NOT NULL
              AND supplier_name != ''
              AND supplier_rma_batch_id IS NULL
            GROUP BY supplier_name
            ORDER BY supplier_name
        ";
        
        $suppliers = $DB->query($query);
        
        echo json_encode([
            'success' => true,
            'suppliers' => $suppliers
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
