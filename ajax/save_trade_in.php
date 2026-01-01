<?php
session_start();
require '../php/bootstrap.php';

/**
 * Custom base33 character set excluding ambiguous characters (0,1,I,O)
 */
function getBase33Chars() {
    return '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
}

/**
 * Convert a number to our custom base33
 */
function toBase33($num, $pad = 0) {
    $chars = getBase33Chars();
    $base = strlen($chars);
    $result = '';
    
    do {
        $result = $chars[$num % $base] . $result;
        $num = floor($num / $base);
    } while ($num > 0);
    
    return str_pad($result, $pad, $chars[0], STR_PAD_LEFT);
}

/**
 * Generate a unique SKU for trade-in items using date and sequence
 */
function generateUniqueTradeInSKU($DB) {
    $date = new DateTime();
    
    // Convert year to base33 (e.g., 2024 = "PQ")
    $year = intval($date->format('Y'));
    $yearBase33 = toBase33($year - 2000, 2);
    
    // Convert month to base33 (1-12 = single char)
    $month = intval($date->format('n'));
    $monthBase33 = toBase33($month, 1);
    
    // Convert day to base33 (1-31 = single char)
    $day = intval($date->format('j'));
    $dayBase33 = toBase33($day, 1);
    
    // Get the base date part of the SKU
    $datePart = $yearBase33 . $monthBase33 . $dayBase33;
    
    // Find the last sequence number used today
    $lastSKU = $DB->query(
        "SELECT sku FROM trade_in_items 
         WHERE sku LIKE ? 
         ORDER BY created_at DESC, id DESC
         LIMIT 1", 
        [$datePart . '%']
    );

    // Start sequence
    $sequence = 0;
    
    if (!empty($lastSKU)) {
        // Extract the last 3 characters (sequence part)
        $lastSequence = substr($lastSKU[0]['sku'], -3);
        
        // Convert each character to its position in our base33 character set
        $chars = getBase33Chars();
        $value = 0;
        for ($i = 0; $i < strlen($lastSequence); $i++) {
            $pos = strpos($chars, $lastSequence[$i]);
            if ($pos !== false) {
                $value = $value * 33 + $pos;
            }
        }
        $sequence = $value + 1;
    }

    // Make sure the sequence doesn't exceed our 3-character limit
    if ($sequence >= (33 * 33 * 33)) {
        throw new Exception("Maximum sequence number reached for today");
    }
    
    // Convert sequence to base33 and pad to 3 characters
    $sequencePart = toBase33($sequence, 3);
    
    // Verify uniqueness
    $sku = $datePart . $sequencePart;
    $exists = $DB->query("SELECT COUNT(*) as count FROM trade_in_items WHERE sku = ?", [$sku])[0]['count'];
    
    if ($exists > 0) {
        throw new Exception("Generated SKU already exists: " . $sku);
    }
    
    return $sku;
}

// Check authorization
if (!isset($_SESSION['dins_user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

try {
    // Get form data
    $id = $_POST['id'] ?? null;
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $custom_sku = trim($_POST['custom_sku'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $condition_rating = $_POST['condition_rating'] ?? '';
    $purchase_price = filter_var($_POST['purchase_price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $customer_id = filter_var($_POST['customer_id'] ?? 0, FILTER_VALIDATE_INT);
    $trade_in_date = $_POST['trade_in_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate required fields
    if (empty($item_name) || empty($condition_rating) || 
        empty($purchase_price) || empty($customer_id) || 
        empty($trade_in_date) || empty($category)) {
        exit(json_encode([
            'success' => false, 
            'message' => 'Please fill in all required fields'
        ]));
    }

    // Validate category exists
    $categoryExists = $DB->query(
        "SELECT COUNT(*) as count FROM master_categories WHERE pless_main_category = ?",
        [$category]
    )[0]['count'];

    if (!$categoryExists) {
        exit(json_encode([
            'success' => false,
            'message' => 'Invalid category selected'
        ]));
    }

    // Start transaction
    $DB->beginTransaction();

    if ($id) {
        // Verify item exists and can be edited
        $existing = $DB->query(
            "SELECT id FROM trade_in_items WHERE id = ?", 
            [$id]
        )[0] ?? null;

        if (!$existing) {
            throw new Exception('Trade-in item not found');
        }

        // Update existing trade-in
        $result = $DB->query(
            "UPDATE trade_in_items SET 
                item_name = ?,
                category = ?,
                custom_sku = ?,
                serial_number = ?,
                condition_rating = ?,
                purchase_price = ?,
                customer_id = ?,
                trade_in_date = ?,
                notes = ?
            WHERE id = ?",
            [
                $item_name,
                $category,
                $custom_sku,
                $serial_number,
                $condition_rating,
                $purchase_price,
                $customer_id,
                $trade_in_date,
                $notes,
                $id
            ]
        );
    } else {
        // Handle custom SKU if provided
        if (!empty($custom_sku)) {
            // Check if custom SKU already exists
            $exists = $DB->query(
                "SELECT COUNT(*) as count FROM trade_in_items WHERE sku = ?", 
                [$custom_sku]
            )[0]['count'];
            
            if ($exists > 0) {
                throw new Exception('Custom SKU already exists');
            }
            $sku = $custom_sku;
        } else {
            // Generate new SKU using our base33 system
            $sku = generateUniqueTradeInSKU($DB);
        }

        // Generate trade-in reference
        $trade_in_ref = 'TI' . date('ymd') . substr(uniqid(), -4);
        
        // Insert new trade-in
        $result = $DB->query(
            "INSERT INTO trade_in_items 
                (sku, custom_sku, serial_number, item_name, category, condition_rating, 
                purchase_price, customer_id, trade_in_date, trade_in_reference, 
                notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $sku,
                $custom_sku,
                $serial_number,
                $item_name,
                $category,
                $condition_rating,
                $purchase_price,
                $customer_id,
                $trade_in_date,
                $trade_in_ref,
                $notes,
                $_SESSION['dins_user_id']
            ]
        );

        $id = $DB->lastInsertId();
    }

    // Commit transaction
    $DB->commit();

    echo json_encode([
        'success' => true,
        'message' => $id ? 'Trade-in item updated successfully' : 'Trade-in item added successfully',
        'id' => $id
    ]);

} catch (Exception $e) {
    // Rollback transaction if active
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }

    error_log("Trade-in save error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}