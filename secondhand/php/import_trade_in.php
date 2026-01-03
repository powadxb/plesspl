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

// Get trade-in ITEM DETAIL ID (not the parent trade_in_id)
$item_detail_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$item_detail_id) {
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
    exit();
}

try {
    // Get the item details from trade_in_items_details
    $item = $DB->query("SELECT * FROM trade_in_items_details WHERE id = ?", [$item_detail_id])[0];
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit();
    }
    
    // Get the parent trade-in record for customer information
    $trade_in = $DB->query("SELECT * FROM trade_in_items WHERE id = ?", [$item['trade_in_id']])[0];
    
    if (!$trade_in) {
        echo json_encode(['success' => false, 'message' => 'Trade-in record not found']);
        exit();
    }

    // Check if this item has already been imported
    $existing = $DB->query("SELECT id FROM second_hand_items WHERE tracking_code = ?", [$item['tracking_code']]);
    
    if (!empty($existing)) {
        echo json_encode(['success' => false, 'message' => 'This item has already been imported (tracking code: ' . $item['tracking_code'] . ')']);
        exit();
    }

    // Generate preprinted code if needed
    $preprinted_code = $item['preprinted_code'];
    if (empty($preprinted_code)) {
        $preprinted_result = $DB->query("
            SELECT COALESCE(MAX(CAST(SUBSTRING(preprinted_code, 4) AS UNSIGNED)), 0) + 1 AS next_number
            FROM second_hand_items
            WHERE preprinted_code LIKE 'DSH%'
        ");
        $next_preprinted_number = $preprinted_result[0]['next_number'] ?? 1;
        $preprinted_code = 'DSH' . $next_preprinted_number;
    }

    // Use existing tracking code from item
    $tracking_code = $item['tracking_code'];

    // Determine user's location
    $effective_location = $user_details['user_location'];
    if(!empty($user_details['temp_location']) &&
       !empty($user_details['temp_location_expires']) &&
       strtotime($user_details['temp_location_expires']) > time()) {
        $effective_location = $user_details['temp_location'];
    }

    // Import the item to second-hand inventory
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
        trade_in_reference,
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $preprinted_code,
        $tracking_code,
        $item['item_name'],
        $item['condition'],
        'trade_in',
        $item['serial_number'],
        'in_stock',
        $item['price_paid'],
        $item['price_paid'] * 1.2, // estimated sale price (20% markup)
        $item['price_paid'], // estimated value
        null, // customer_id - not used
        $trade_in['customer_name'], // FROM parent trade_in record
        $trade_in['customer_phone'], // FROM parent trade_in record
        $item['category'],
        $item['notes'], // detailed_condition
        $trade_in['location'] ?: $effective_location, // location from trade-in or user location
        $trade_in['collection_date'] ?: date('Y-m-d'), // acquisition_date from trade-in
        null, // warranty_info
        null, // supplier_info
        null, // model_number
        null, // brand
        null, // purchase_document
        null, // status_detail
        $item['notes'], // notes
        $item['trade_in_id'], // trade_in_reference
        $user_id
    ];

    $DB->query($query, $params);
    $second_hand_id = $DB->lastInsertId();

    // Log the import
    if ($DB->query("SHOW TABLES LIKE 'second_hand_audit_log'")) {
        $log_sql = "INSERT INTO second_hand_audit_log (user_id, item_id, action, action_details)
                    VALUES (?, ?, 'import_trade_in_to_second_hand', ?)";
        $log_details = json_encode([
            'imported_by' => $user_id,
            'imported_at' => date('Y-m-d H:i:s'),
            'trade_in_detail_id' => $item_detail_id,
            'second_hand_id' => $second_hand_id,
            'item_name' => $item['item_name']
        ]);
        $DB->query($log_sql, [$user_id, $second_hand_id, $log_details]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Item successfully imported to second-hand inventory',
        'tracking_code' => $tracking_code,
        'preprinted_code' => $preprinted_code,
        'customer_name' => $trade_in['customer_name']
    ]);

} catch (Exception $e) {
    error_log("Error importing trade-in item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error importing item: ' . $e->getMessage()]);
}
?>
