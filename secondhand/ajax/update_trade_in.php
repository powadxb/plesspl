<?php
session_start();
require '../php/bootstrap.php';

// Check authorization
if (!isset($_SESSION['dins_user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

try {
    // Get and validate required fields
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    $item_name = trim($_POST['item_name'] ?? '');
    $custom_sku = trim($_POST['custom_sku'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $condition_rating = $_POST['condition_rating'] ?? '';
    $purchase_price = filter_var($_POST['purchase_price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $customer_id = filter_var($_POST['customer_id'] ?? 0, FILTER_VALIDATE_INT);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $id_document_type = trim($_POST['id_document_type'] ?? '');
    $id_document_number = trim($_POST['id_document_number'] ?? '');
    $compliance_notes = trim($_POST['compliance_notes'] ?? '');
    $collection_date = $_POST['collection_date'] ?? null;
    $compliance_status = $_POST['compliance_status'] ?? 'pending';
    
    // Validate required fields
    if (!$id || empty($item_name) || empty($category) ||
        empty($condition_rating) || empty($purchase_price) || empty($customer_name)) {
        throw new Exception('Please fill in all required fields');
    }

    // Start transaction
    $DB->beginTransaction();

    // Verify item exists and can be edited
    $existing = $DB->query(
        "SELECT id FROM trade_in_items WHERE id = ?", 
        [$id]
    )[0] ?? null;

    if (!$existing) {
        throw new Exception('Trade-in item not found');
    }

    // Check if custom SKU is unique if provided
    if (!empty($custom_sku)) {
        $sku_exists = $DB->query(
            "SELECT COUNT(*) as count FROM trade_in_items WHERE custom_sku = ? AND id != ?",
            [$custom_sku, $id]
        )[0]['count'];

        if ($sku_exists > 0) {
            throw new Exception('Custom SKU already exists');
        }
    }

    // Update item
    $DB->query(
        "UPDATE trade_in_items SET
            item_name = ?,
            custom_sku = ?,
            category = ?,
            serial_number = ?,
            condition_rating = ?,
            purchase_price = ?,
            customer_id = ?,
            customer_name = ?,
            customer_phone = ?,
            customer_email = ?,
            customer_address = ?,
            id_document_type = ?,
            id_document_number = ?,
            compliance_notes = ?,
            collection_date = ?,
            compliance_status = ?,
            updated_at = NOW()
         WHERE id = ?",
        [
            $item_name,
            $custom_sku,
            $category,
            $serial_number,
            $condition_rating,
            $purchase_price,
            $customer_id,
            $customer_name,
            $customer_phone,
            $customer_email,
            $customer_address,
            $id_document_type,
            $id_document_number,
            $compliance_notes,
            $collection_date,
            $compliance_status,
            $id
        ]
    );

    // Handle any new photos
    if (!empty($_FILES['new_photos'])) {
        $upload_dir = '../uploads/trade_ins/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        foreach ($_FILES['new_photos']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name)) {
                $filename = uniqid() . '_' . $_FILES['new_photos']['name'][$key];
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $filepath)) {
                    $DB->query(
                        "INSERT INTO trade_in_files (trade_in_id, file_path, file_type, created_by) 
                         VALUES (?, ?, 'item_photo', ?)",
                        [$id, $filename, $_SESSION['dins_user_id']]
                    );
                }
            }
        }
    }

    // Remove any deleted photos
    $existing_photos = $_POST['existing_photos'] ?? [];
    if (!empty($existing_photos)) {
        $photo_ids = implode(',', array_map('intval', $existing_photos));
        $DB->query(
            "DELETE FROM trade_in_files 
             WHERE trade_in_id = ? AND file_type = 'item_photo' 
             AND id NOT IN ($photo_ids)",
            [$id]
        );
    } else {
        // If no existing photos were kept, delete all photos
        $DB->query(
            "DELETE FROM trade_in_files 
             WHERE trade_in_id = ? AND file_type = 'item_photo'",
            [$id]
        );
    }

    $DB->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Item updated successfully'
    ]);

} catch (Exception $e) {
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }
    
    error_log("Update trade-in error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}