<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent any output before this point
ob_start();

require_once 'bootstrap.php';
require_once 'odoo_connection.php';

// Clean any unwanted output
ob_end_clean();

$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

if (empty($user_details)) {
    echo 'Access denied';
    exit();
}

$sku = $_POST['sku'] ?? '';
$counted_stock = $_POST['counted_stock'] ?? '';
$session_id = $_POST['session_id'] ?? '';

if (empty($sku) || $counted_stock === '') {
    echo 'Missing required data (SKU or count)';
    exit();
}

// Validate count is numeric and not negative
if (!is_numeric($counted_stock) || $counted_stock < 0) {
    echo 'Invalid count value';
    exit();
}

// Get session details to determine target location
if (empty($session_id)) {
    echo 'Session ID is required';
    exit();
}

$session = $DB->query("SELECT * FROM stock_count_sessions WHERE id = ? AND status = 'active'", [$session_id]);
if (empty($session)) {
    echo 'Invalid or inactive session';
    exit();
}

$session_data = $session[0];
$session_location = $session_data['location']; // Should be 'cs' or 'as'

// Validate that this SKU is in the pending queue for this session
$queue_item = $DB->query("SELECT id FROM stock_count_queue WHERE sku = ? AND session_id = ? AND status = 'pending'", [$sku, $session_id]);

if (empty($queue_item)) {
    echo 'Item not found in counting queue for this session or already counted';
    exit();
}

// Get user's effective location (including temporary assignments)
function getUserEffectiveLocation($user_id, $DB) {
    $user = $DB->query("
        SELECT user_location, temp_location, temp_location_expires 
        FROM users 
        WHERE id = ?
    ", [$user_id]);
    
    if (empty($user)) {
        return null;
    }
    
    $user_data = $user[0];
    
    // Check if temporary location is active
    if (!empty($user_data['temp_location']) && 
        !empty($user_data['temp_location_expires']) && 
        strtotime($user_data['temp_location_expires']) > time()) {
        return $user_data['temp_location'];
    }
    
    return $user_data['user_location'];
}

// Check if user is authorized for this session's location
$user_location = getUserEffectiveLocation($user_id, $DB);
$is_admin = $user_details['admin'] >= 1;

// Admin can count for any location, staff can only count for their assigned location
if (!$is_admin && $user_location !== $session_location) {
    echo 'You are not authorized to count for this location. Your location: ' . ($user_location ?? 'Not Set') . ', Session location: ' . $session_location;
    exit();
}

try {
    // Get current system stock from Odoo for both locations
    $cs_stock = getOdooQuantities([$sku], 12)[$sku] ?? 0;
    $as_stock = getOdooQuantities([$sku], 19)[$sku] ?? 0;
    
    // Determine which system stock to use for variance calculation based on session location
    $target_system_stock = ($session_location === 'cs') ? $cs_stock : $as_stock;
    $variance = $counted_stock - $target_system_stock;
    
    // Start transaction
    $DB->beginTransaction();
    
    try {
        // Insert count entry with location-specific variance calculation
        $DB->query(
            "INSERT INTO stock_count_entries (
                sku, 
                counted_by_user_id, 
                counted_stock, 
                system_cs_stock, 
                system_as_stock, 
                session_id, 
                target_location,
                variance_amount,
                system_stock_used,
                count_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $sku, 
                $user_id, 
                $counted_stock, 
                $cs_stock, 
                $as_stock, 
                $session_id,
                $session_location,
                $variance,
                $target_system_stock
            ]
        );
        
        // Update queue status to 'counted'
        $DB->query("UPDATE stock_count_queue SET status = 'counted' WHERE sku = ? AND session_id = ?", [$sku, $session_id]);
        
        // Commit transaction
        $DB->commit();
        
        echo 'success';
        
    } catch (Exception $e) {
        $DB->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Stock count error for SKU $sku in session $session_id: " . $e->getMessage());
    echo 'Error processing count: ' . $e->getMessage();
}
?>