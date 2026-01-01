<?php
session_start();
require_once '../../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Load permission functions
$permissions_file = __DIR__.'/../php/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

// Check permissions
$is_admin = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);
$has_tradein_access = $is_admin;

// Only check the permission function if it exists
if (!$is_admin && function_exists('hasSecondHandPermission')) {
    $has_tradein_access = hasSecondHandPermission($user_id, 'SecondHand-View', $DB);
}

if (!$has_tradein_access) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Determine view_all_locations based on admin status and permissions
$view_all_locations = $is_admin;
if (!$is_admin && function_exists('canViewAllLocations')) {
    $view_all_locations = canViewAllLocations($user_id, $DB);
} else if (!$is_admin) {
    // Default to false if not admin and function doesn't exist
    $view_all_locations = false;
}

$user_location = $user_details['user_location'];

// Build query based on permissions
// Use backticks around 'condition' as it's a MySQL reserved word
$sql = "SELECT id, preprinted_code, tracking_code, item_name, customer_name, customer_phone, customer_email, customer_address, category, brand, model_number, serial_number, `condition`, detailed_condition, offered_price, location, status, collection_date, notes, id_document_type, id_document_number, compliance_notes, compliance_status, created_at FROM trade_in_items WHERE 1=1";
$params = [];

if (!$view_all_locations) {
    $sql .= " AND location = ?";
    $params[] = $user_location;
}

$sql .= " ORDER BY created_at DESC";
$trade_ins = $DB->query($sql, $params);

header('Content-Type: application/json');
echo json_encode(['trade_ins' => $trade_ins]);
?>