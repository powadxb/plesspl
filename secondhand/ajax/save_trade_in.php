<?php
session_start();
require_once '../../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
$can_manage = $is_admin;

// Only check the permission function if it exists
if (!$is_admin && function_exists('hasSecondHandPermission')) {
    $can_manage = hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB);
}

if (!$can_manage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get form data
$item_id = $_POST['id'] ?? null;
$item_name = $_POST['item_name'] ?? '';
$customer_name = $_POST['customer_name'] ?? '';
$customer_phone = $_POST['customer_phone'] ?? '';
$customer_email = $_POST['customer_email'] ?? '';
$customer_address = $_POST['customer_address'] ?? '';
$category = $_POST['category'] ?? '';
$brand = $_POST['brand'] ?? '';
$model_number = $_POST['model_number'] ?? '';
$serial_number = $_POST['serial_number'] ?? '';
$condition = $_POST['condition'] ?? 'good';
$detailed_condition = $_POST['detailed_condition'] ?? '';
$offered_price = $_POST['offered_price'] ?? null;
$location = $_POST['location'] ?? $user_details['user_location'];
$status = $_POST['status'] ?? 'pending';
$collection_date = $_POST['collection_date'] ?? null;
$notes = $_POST['notes'] ?? '';
$preprinted_code = $_POST['preprinted_code'] ?? null;
$tracking_code = $_POST['tracking_code'] ?? null;
$import_to_second_hand = isset($_POST['import_to_second_hand']) ? 1 : 0;

// Scottish compliance fields
$id_document_type = $_POST['id_document_type'] ?? null;
$id_document_number = $_POST['id_document_number'] ?? null;
$compliance_notes = $_POST['compliance_notes'] ?? null;
$compliance_status = $_POST['compliance_status'] ?? 'pending';

// Validate required fields
if (empty($item_name) || empty($customer_name)) {
    echo json_encode(['success' => false, 'message' => 'Item name and customer name are required']);
    exit();
}

// Validate location
if (!in_array($location, ['cs', 'as'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid location']);
    exit();
}

// Handle file uploads
$uploaded_photos = [];
if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
    $upload_dir = __DIR__ . '/../uploads/trade_in_photos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_count = count($_FILES['photos']['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['photos']['tmp_name'][$i];
            $file_name = $_FILES['photos']['name'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file type
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $new_filename = 'trade_in_' . time() . '_' . $i . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $uploaded_photos[] = 'uploads/trade_in_photos/' . $new_filename;
                }
            }
        }
    }
}

try {
    if ($item_id) {
        // Update existing trade-in
        $sql = "UPDATE trade_in_items SET
                    item_name = ?, customer_name = ?, customer_phone = ?, customer_email = ?,
                    customer_address = ?, category = ?, brand = ?, model_number = ?,
                    serial_number = ?, `condition` = ?, detailed_condition = ?, offered_price = ?,
                    location = ?, status = ?, collection_date = ?, notes = ?,
                    preprinted_code = ?, tracking_code = ?,
                    id_document_type = ?, id_document_number = ?, compliance_notes = ?,
                    compliance_status = ? WHERE id = ?";

        $params = [
            $item_name, $customer_name, $customer_phone, $customer_email,
            $customer_address, $category, $brand, $model_number,
            $serial_number, $condition, $detailed_condition, $offered_price,
            $location, $status, $collection_date, $notes,
            $preprinted_code, $tracking_code,
            $id_document_type, $id_document_number, $compliance_notes,
            $compliance_status, $item_id
        ];

        $DB->query($sql, $params);

        // Log the update
        $log_sql = "INSERT INTO second_hand_audit_log (user_id, item_id, action, action_details)
                    VALUES (?, ?, 'update_trade_in', ?)";
        $log_details = json_encode([
            'updated_by' => $user_id,
            'updated_at' => date('Y-m-d H:i:s'),
            'fields' => ['item_name', 'customer_name', 'status']
        ]);
        $DB->query($log_sql, [$user_id, $item_id, $log_details]);

        $message = 'Trade-in updated successfully';
    } else {
        // Insert new trade-in
        $sql = "INSERT INTO trade_in_items (
                    item_name, customer_name, customer_phone, customer_email,
                    customer_address, category, brand, model_number,
                    serial_number, `condition`, detailed_condition, offered_price,
                    location, status, collection_date, notes,
                    preprinted_code, tracking_code,
                    id_document_type, id_document_number, compliance_notes,
                    compliance_status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $item_name, $customer_name, $customer_phone, $customer_email,
            $customer_address, $category, $brand, $model_number,
            $serial_number, $condition, $detailed_condition, $offered_price,
            $location, $status, $collection_date, $notes,
            $preprinted_code, $tracking_code,
            $id_document_type, $id_document_number, $compliance_notes,
            $compliance_status, $user_id
        ];

        $result = $DB->query($sql, $params);
        $new_id = $DB->lastInsertId();

        // Log the creation
        $log_sql = "INSERT INTO second_hand_audit_log (user_id, item_id, action, action_details)
                    VALUES (?, ?, 'create_trade_in', ?)";
        $log_details = json_encode([
            'created_by' => $user_id,
            'created_at' => date('Y-m-d H:i:s'),
            'item_name' => $item_name
        ]);
        $DB->query($log_sql, [$user_id, $new_id, $log_details]);

        $message = 'Trade-in created successfully';
    }

    // If import to second-hand is requested, add the item to second-hand inventory
    if ($import_to_second_hand) {
        // Generate tracking codes if not provided
        if (empty($preprinted_code) && empty($tracking_code)) {
            // Generate SH tracking code
            $last_sh_item = $DB->query("SELECT tracking_code FROM second_hand_items WHERE tracking_code LIKE 'SH%' ORDER BY tracking_code DESC LIMIT 1");
            $next_sh_number = 1;
            if (!empty($last_sh_item)) {
                $last_code = $last_sh_item[0]['tracking_code'];
                if (preg_match('/SH(\d+)/', $last_code, $matches)) {
                    $next_sh_number = (int)$matches[1] + 1;
                }
            }
            $tracking_code = "SH{$next_sh_number}";
        } elseif (empty($tracking_code) && !empty($preprinted_code)) {
            // If only preprinted code is provided, use it as the tracking code too
            $tracking_code = $preprinted_code;
        }

        // Insert into second-hand inventory
        $sql = "INSERT INTO second_hand_items (
                    preprinted_code, tracking_code, item_name, `condition`, item_source,
                    serial_number, status, purchase_price, estimated_value, estimated_sale_price,
                    customer_name, customer_contact, category, detailed_condition, location,
                    acquisition_date, warranty_info, supplier_info, model_number, brand,
                    notes, trade_in_reference, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $preprinted_code, $tracking_code, $item_name, $condition, 'trade_in',
            $serial_number, 'in_stock', $offered_price, $offered_price, $offered_price,
            $customer_name, $customer_phone, $category, $detailed_condition, $location,
            date('Y-m-d'), null, null, $model_number, $brand,
            $notes, $new_id, $user_id
        ];

        $DB->query($sql, $params);
        $second_hand_id = $DB->lastInsertId();

        // Log the import
        $log_sql = "INSERT INTO second_hand_audit_log (user_id, item_id, action, action_details)
                    VALUES (?, ?, 'import_trade_in_to_second_hand', ?)";
        $log_details = json_encode([
            'imported_by' => $user_id,
            'imported_at' => date('Y-m-d H:i:s'),
            'trade_in_id' => $new_id,
            'second_hand_id' => $second_hand_id,
            'item_name' => $item_name
        ]);
        $DB->query($log_sql, [$user_id, $second_hand_id, $log_details]);

        $message .= ' and imported to second-hand inventory.';
    }

    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    error_log("Error saving trade-in: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error saving trade-in: ' . $e->getMessage()]);
}
?>