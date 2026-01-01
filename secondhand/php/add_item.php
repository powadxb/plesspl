<?php
require_once '../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Load permission functions
$permissions_file = __DIR__.'/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

// Check permissions
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = ($user_details['admin'] != 0 || $user_details['useradmin'] >= 1);
$can_manage = $is_admin || (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB));

if(!$can_manage) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Get form data
$item_source = $_POST['item_source'] ?? '';
$item_name = trim($_POST['item_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$condition = $_POST['condition'] ?? '';
$serial_number = trim($_POST['serial_number'] ?? '');
$status = $_POST['status'] ?? 'in_stock';
$purchase_price = filter_var($_POST['purchase_price'] ?? 0, FILTER_VALIDATE_FLOAT);
$estimated_sale_price = filter_var($_POST['estimated_sale_price'] ?? 0, FILTER_VALIDATE_FLOAT);
$estimated_value = filter_var($_POST['estimated_value'] ?? 0, FILTER_VALIDATE_FLOAT);
$customer_id = trim($_POST['customer_id'] ?? '');
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_contact = trim($_POST['customer_contact'] ?? '');
$detailed_condition = trim($_POST['detailed_condition'] ?? '');
$location = trim($_POST['location'] ?? '');
$acquisition_date = $_POST['acquisition_date'] ?? date('Y-m-d');
$warranty_info = trim($_POST['warranty_info'] ?? '');
$supplier_info = trim($_POST['supplier_info'] ?? '');
$model_number = trim($_POST['model_number'] ?? '');
$brand = trim($_POST['brand'] ?? '');
$purchase_document = trim($_POST['purchase_document'] ?? '');
$status_detail = trim($_POST['status_detail'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Validate required fields
if (empty($item_name) || empty($item_source) || empty($condition)) {
    echo json_encode(['success' => false, 'message' => 'Item name, source, and condition are required']);
    exit();
}

// Determine user's location for location-based restrictions
$effective_location = $user_details['user_location'];
if(!empty($user_details['temp_location']) &&
   !empty($user_details['temp_location_expires']) &&
   strtotime($user_details['temp_location_expires']) > time()) {
    $effective_location = $user_details['temp_location'];
}

// Check if user can view all locations
$can_view_all_locations = $is_admin || (function_exists('canViewAllLocations') && canViewAllLocations($user_id, $DB));

// If user doesn't have all locations permission, force location to their assigned location
if (!$can_view_all_locations) {
    $location = $effective_location;
} elseif (empty($location)) {
    $location = $effective_location; // Default to user's location if none specified
}

try {
    // Generate tracking codes for the second-hand item
    $tracking_result = $DB->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_code, 3) AS UNSIGNED)), 0) + 1 AS next_number
        FROM second_hand_items
        WHERE tracking_code LIKE 'SH%'
    ");
    $next_tracking_number = $tracking_result[0]['next_number'] ?? 1;
    $tracking_code = 'SH' . $next_tracking_number;

    $preprinted_result = $DB->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(preprinted_code, 4) AS UNSIGNED)), 0) + 1 AS next_number
        FROM second_hand_items
        WHERE preprinted_code LIKE 'DSH%'
    ");
    $next_preprinted_number = $preprinted_result[0]['next_number'] ?? 1;
    $preprinted_code = 'DSH' . $next_preprinted_number;

    // Insert the item to second-hand inventory
    $query = "INSERT INTO second_hand_items (
        preprinted_code,
        tracking_code,
        item_name,
        `condition`,
        item_source,
        serial_number,
        status,
        purchase_price,
        estimated_sale_price,
        estimated_value,
        customer_id,
        customer_name,
        customer_contact,
        category,
        detailed_condition,
        location,
        acquisition_date,
        warranty_info,
        supplier_info,
        model_number,
        brand,
        purchase_document,
        status_detail,
        notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $preprinted_code, // preprinted_code
        $tracking_code, // tracking_code
        $item_name,
        $condition,
        $item_source,
        $serial_number,
        $status,
        $purchase_price,
        $estimated_sale_price,
        $estimated_value,
        $customer_id,
        $customer_name,
        $customer_contact,
        $category,
        $detailed_condition,
        $location,
        $acquisition_date,
        $warranty_info,
        $supplier_info,
        $model_number,
        $brand,
        $purchase_document,
        $status_detail,
        $notes
    ];

    $DB->query($query, $params);

    echo json_encode([
        'success' => true,
        'message' => 'Item successfully added to second-hand inventory',
        'tracking_code' => $tracking_code,
        'preprinted_code' => $preprinted_code,
        'id' => $DB->lastInsertId()
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error adding item: ' . $e->getMessage()]);
}
?>