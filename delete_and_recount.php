<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Prevent any output before this point
ob_start();
require_once 'bootstrap.php';
// Clean any unwanted output
ob_end_clean();

// Check if user is logged in
$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

if (empty($user_details)) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

$is_admin = $user_details['admin'] >= 1;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get and validate parameters
$sku = $_POST['sku'] ?? '';
$session_id = $_POST['session_id'] ?? '';

if (empty($sku) || empty($session_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

try {
    // Start transaction
    $DB->query("START TRANSACTION");
    
    // First, verify the count entry exists and get details
    $count_entry = $DB->query("
        SELECT ce.*, u.username as counted_by_username, s.status as session_status
        FROM stock_count_entries ce
        LEFT JOIN users u ON ce.counted_by_user_id = u.id
        LEFT JOIN stock_count_sessions s ON ce.session_id = s.id
        WHERE ce.sku = ? AND ce.session_id = ?
        ORDER BY ce.count_date DESC
        LIMIT 1
    ", [$sku, $session_id]);
    
    if (empty($count_entry)) {
        $DB->query("ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'Count entry not found']);
        exit();
    }
    
    $entry = $count_entry[0];
    
    // Check if session is still active
    if ($entry['session_status'] !== 'active') {
        $DB->query("ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'Cannot recount items from completed sessions']);
        exit();
    }
    
    // Permission check - staff can only recount their own entries, admin can recount any
    if (!$is_admin && $entry['counted_by_user_id'] != $user_id) {
        $DB->query("ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'You can only recount your own entries']);
        exit();
    }
    
    // Check if item is already back in the queue (prevent duplicates)
    $existing_queue = $DB->query("
        SELECT id FROM stock_count_queue 
        WHERE sku = ? AND session_id = ? AND status = 'pending'
    ", [$sku, $session_id]);
    
    if (!empty($existing_queue)) {
        $DB->query("ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'Item is already in the counting queue']);
        exit();
    }
    
    // Delete the count entry
    $delete_result = $DB->query("
        DELETE FROM stock_count_entries 
        WHERE sku = ? AND session_id = ? AND counted_by_user_id = ?
        ORDER BY count_date DESC 
        LIMIT 1
    ", [$sku, $session_id, $entry['counted_by_user_id']]);
    
    if (!$delete_result) {
        $DB->query("ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'Failed to delete count entry']);
        exit();
    }
    
    // Add item back to the counting queue
    $queue_result = $DB->query("
        INSERT INTO stock_count_queue (sku, session_id, status, added_date, added_by_user_id)
        VALUES (?, ?, 'pending', NOW(), ?)
    ", [$sku, $session_id, $user_id]);
    
    if (!$queue_result) {
        $DB->query("ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'Failed to add item back to queue']);
        exit();
    }
    
    // Commit the transaction
    $DB->query("COMMIT");
    
    // Get product name for success message
    $product = $DB->query("SELECT name FROM master_products WHERE sku = ?", [$sku])[0] ?? [];
    $product_name = !empty($product) ? $product['name'] : "SKU $sku";
    
    echo json_encode([
        'success' => true,
        'message' => "Count deleted and item added back to queue for recounting",
        'sku' => $sku,
        'product_name' => $product_name,
        'original_counter' => $entry['counted_by_username'],
        'original_count' => $entry['counted_stock']
    ]);
    
} catch (Exception $e) {
    $DB->query("ROLLBACK");
    error_log("Delete and recount error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>