<?php
// Error logging only (don't display errors as they break JSON)
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', 0);

require '../../php/bootstrap.php';

// Load audit logger
$audit_logger_file = __DIR__.'/audit_logger.php';
if (file_exists($audit_logger_file)) {
    require $audit_logger_file;
    // Create audit log table if it doesn't exist
    createAuditLogTable($DB);
    $audit_logger = new SecondHandAuditLogger($DB);
} else {
    $audit_logger = null;
}

// Check permissions directly from database
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

$manage_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'",
    [$user_id]
);
$can_manage = !empty($manage_check) && $manage_check[0]['has_access'];

if(!$can_manage) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Determine user's location for location-based restrictions
$effective_location = $user_details['user_location'];
if(!empty($user_details['temp_location']) &&
   !empty($user_details['temp_location_expires']) &&
   strtotime($user_details['temp_location_expires']) > time()) {
    $effective_location = $user_details['temp_location'];
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : null;

// Get all the form data
$data = [
    'preprinted_code' => !empty(trim($_POST['preprinted_code'] ?? '')) ? trim($_POST['preprinted_code']) : null,
    'tracking_code' => !empty(trim($_POST['tracking_code'] ?? '')) ? trim($_POST['tracking_code']) : null,
    'item_name' => isset($_POST['item_name']) ? trim($_POST['item_name']) : '',
    '`condition`' => isset($_POST['condition']) ? trim($_POST['condition']) : 'good', // Note the backticks
    'item_source' => isset($_POST['item_source']) ? trim($_POST['item_source']) : 'other',
    'serial_number' => isset($_POST['serial_number']) ? trim($_POST['serial_number']) : null,
    'status' => isset($_POST['status']) ? trim($_POST['status']) : 'in_stock',
    'purchase_price' => isset($_POST['purchase_price']) && $_POST['purchase_price'] !== '' ?
                       (float)$_POST['purchase_price'] : null,
    'estimated_sale_price' => isset($_POST['estimated_sale_price']) && $_POST['estimated_sale_price'] !== '' ?
                             (float)$_POST['estimated_sale_price'] : null,
    'estimated_value' => isset($_POST['estimated_value']) && $_POST['estimated_value'] !== '' ?
                        (float)$_POST['estimated_value'] : null,
    'customer_id' => isset($_POST['customer_id']) ? trim($_POST['customer_id']) : null,
    'customer_name' => isset($_POST['customer_name']) ? trim($_POST['customer_name']) : null,
    'customer_contact' => isset($_POST['customer_contact']) ? trim($_POST['customer_contact']) : null,
    'category' => isset($_POST['category']) ? trim($_POST['category']) : null,
    'detailed_condition' => isset($_POST['detailed_condition']) ? trim($_POST['detailed_condition']) : null,
    'location' => isset($_POST['location']) ? trim($_POST['location']) : $effective_location, // Default to user's location
    'acquisition_date' => isset($_POST['acquisition_date']) ? trim($_POST['acquisition_date']) : null,
    'warranty_info' => isset($_POST['warranty_info']) ? trim($_POST['warranty_info']) : null,
    'supplier_info' => isset($_POST['supplier_info']) ? trim($_POST['supplier_info']) : null,
    'model_number' => isset($_POST['model_number']) ? trim($_POST['model_number']) : null,
    'brand' => isset($_POST['brand']) ? trim($_POST['brand']) : null,
    'purchase_document' => isset($_POST['purchase_document']) ? trim($_POST['purchase_document']) : null,
    'status_detail' => isset($_POST['status_detail']) ? trim($_POST['status_detail']) : null,
    'notes' => isset($_POST['notes']) ? trim($_POST['notes']) : null,
    'selling_price' => isset($_POST['selling_price']) && $_POST['selling_price'] !== '' ?
                      (float)$_POST['selling_price'] : null,
    'lowest_price' => isset($_POST['lowest_price']) && $_POST['lowest_price'] !== '' ?
                     (float)$_POST['lowest_price'] : null
];

// Generate tracking codes if not provided
if (empty($data['tracking_code']) && empty($data['preprinted_code'])) {
    // Generate SH tracking code
    $last_sh_item = $DB->query("SELECT tracking_code FROM second_hand_items WHERE tracking_code LIKE 'SH%' ORDER BY tracking_code DESC LIMIT 1");
    $next_sh_number = 1;
    if (!empty($last_sh_item)) {
        $last_code = $last_sh_item[0]['tracking_code'];
        if (preg_match('/SH(\d+)/', $last_code, $matches)) {
            $next_sh_number = (int)$matches[1] + 1;
        }
    }
    $data['tracking_code'] = "SH{$next_sh_number}";
} elseif (empty($data['tracking_code']) && !empty($data['preprinted_code'])) {
    // If only preprinted code is provided, use it as the tracking code too
    $data['tracking_code'] = $data['preprinted_code'];
} elseif (empty($data['preprinted_code']) && !empty($data['tracking_code']) && strpos($data['tracking_code'], 'DSH') === 0) {
    // If tracking code starts with DSH, it's also the preprinted code
    $data['preprinted_code'] = $data['tracking_code'];
}

// Validate required fields
if(empty($data['item_name'])) {
    echo json_encode(['success' => false, 'message' => 'Item name is required']);
    exit;
}

// Location validation - ensure user can only modify items in locations they have access to
$all_locations_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View All Locations'",
    [$user_id]
);
$can_view_all_locations = !empty($all_locations_check) && $all_locations_check[0]['has_access'];

if (!$can_view_all_locations && $data['location'] !== $effective_location) {
    echo json_encode(['success' => false, 'message' => 'You can only modify items in your assigned location']);
    exit;
}

// Check for duplicate tracking codes if provided
if(!empty($data['tracking_code'])) {
    $check = $DB->query("SELECT COUNT(*) as count FROM second_hand_items WHERE tracking_code = ? AND id != ?", [$data['tracking_code'], $id]);
    if($check[0]['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Tracking code already exists']);
        exit;
    }
}

// Check for duplicate preprinted codes if provided
if(!empty($data['preprinted_code'])) {
    $check = $DB->query("SELECT COUNT(*) as count FROM second_hand_items WHERE preprinted_code = ? AND id != ?", [$data['preprinted_code'], $id]);
    if($check[0]['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Preprinted code already exists']);
        exit;
    }
}

try {
    if($id) {
        // Update - check if the item exists and if user has permission to modify it
        $existing_item = $DB->query("SELECT * FROM second_hand_items WHERE id = ?", [$id])[0];
        if (!$can_view_all_locations && $existing_item['location'] !== $effective_location) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this item']);
            exit;
        }

        // Prepare update query
        $set_clause = [];
        $params = [];
        foreach($data as $key => $value) {
            $set_clause[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $query = "UPDATE second_hand_items SET " . implode(", ", $set_clause) . " WHERE id = ?";
        $DB->query($query, $params);

        // Log the update action
        if ($audit_logger) {
            $audit_logger->logAction($user_id, $id, 'update', $existing_item, $data);
        }
    } else {
        // Insert - set acquisition date if not provided
        if(empty($data['acquisition_date'])) {
            $data['acquisition_date'] = date('Y-m-d');
        }

        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');

        $query = "INSERT INTO second_hand_items (" . implode(", ", $fields) . ")
                  VALUES (" . implode(", ", $placeholders) . ")";
        $DB->query($query, array_values($data));

        $new_item_id = $DB->lastInsertId();

        // Log the create action
        if ($audit_logger) {
            $audit_logger->logAction($user_id, $new_item_id, 'create', null, $data);
        }
    }

    // Handle photo uploads if any
    if (!empty($_FILES['item_photos'])) {
        $upload_dir = __DIR__ . '/../uploads/second_hand_items/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        foreach ($_FILES['item_photos']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name)) {
                $filename = uniqid() . '_' . $_FILES['item_photos']['name'][$key];
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($tmp_name, $filepath)) {
                    $DB->query(
                        "INSERT INTO second_hand_item_photos (item_id, file_path, file_type, uploaded_by)
                         VALUES (?, ?, 'item_photo', ?)",
                        [$id ?: $new_item_id, $filename, $user_id]
                    );
                }
            }
        }
    }

    // Handle existing photos (remove any that were not kept)
    if ($id) { // Only for updates, not inserts
        $existing_photos = $_POST['existing_photos'] ?? [];
        if (!empty($existing_photos)) {
            $photo_ids = implode(',', array_map('intval', $existing_photos));
            $DB->query(
                "DELETE FROM second_hand_item_photos
                 WHERE item_id = ? AND file_type = 'item_photo'
                 AND id NOT IN ($photo_ids)",
                [$id]
            );
        } else {
            // If no existing photos were kept, delete all photos
            $DB->query(
                "DELETE FROM second_hand_item_photos
                 WHERE item_id = ? AND file_type = 'item_photo'",
                [$id]
            );
        }
    }

    echo json_encode(['success' => true]);
} catch(Exception $e) {
    // Log the full error for debugging
    error_log("Second Hand Item Save Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>