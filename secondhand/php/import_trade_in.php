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
$can_import_trade_ins = $is_admin || (function_exists('canImportTradeIns') && canImportTradeIns($user_id, $DB));

if(!$can_import_trade_ins) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Get trade-in item ID
$trade_in_id = isset($_POST['trade_in_id']) ? (int)$_POST['trade_in_id'] : 0;

if (!$trade_in_id) {
    echo json_encode(['success' => false, 'message' => 'Trade-in ID is required']);
    exit();
}

// Get the trade-in item details
$trade_in_item = $DB->query("SELECT * FROM trade_in_items WHERE id = ?", [$trade_in_id])[0];

if (!$trade_in_item) {
    echo json_encode(['success' => false, 'message' => 'Trade-in item not found']);
    exit();
}

// Check if this trade-in item has already been imported
$existing = $DB->query("SELECT id FROM second_hand_items WHERE trade_in_reference = ?", [$trade_in_item['trade_in_reference']]);

if (!empty($existing)) {
    echo json_encode(['success' => false, 'message' => 'This trade-in item has already been imported']);
    exit();
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

    // Determine user's location for location-based restrictions
    $effective_location = $user_details['user_location'];
    if(!empty($user_details['temp_location']) &&
       !empty($user_details['temp_location_expires']) &&
       strtotime($user_details['temp_location_expires']) > time()) {
        $effective_location = $user_details['temp_location'];
    }

    // Import the trade-in item to second-hand inventory
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
        notes,
        trade_in_reference
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $preprinted_code, // preprinted_code
        $tracking_code, // tracking_code
        $trade_in_item['item_name'],
        $trade_in_item['condition_rating'],
        'trade_in',
        $trade_in_item['serial_number'],
        'in_stock', // status
        $trade_in_item['purchase_price'],
        $trade_in_item['purchase_price'] * 1.2, // estimated sale price (20% markup as example)
        $trade_in_item['purchase_price'], // estimated value same as purchase price
        $trade_in_item['customer_id'],
        $trade_in_item['customer_name'] ?? null,
        $trade_in_item['customer_phone'] ?? null, // customer contact
        $trade_in_item['category'],
        'Traded in item - ' . $trade_in_item['condition_rating'], // detailed_condition
        $effective_location, // location - use user's location
        $trade_in_item['trade_in_date'],
        null, // warranty_info
        null, // supplier_info
        null, // model_number
        null, // brand
        $trade_in_item['trade_in_reference'],
        null, // status_detail
        $trade_in_item['notes'],
        $trade_in_item['trade_in_reference']
    ];

    $DB->query($query, $params);

    echo json_encode([
        'success' => true,
        'message' => 'Trade-in item successfully imported to second-hand inventory',
        'tracking_code' => $tracking_code,
        'preprinted_code' => $preprinted_code
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error importing trade-in item: ' . $e->getMessage()]);
}
?>