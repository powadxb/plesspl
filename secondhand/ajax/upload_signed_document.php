<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['dins_user_id'];

try {
    $trade_in_id = $_POST['trade_in_id'] ?? 0;
    
    if(!$trade_in_id) {
        throw new Exception('Trade-in ID is required');
    }
    
    // Verify trade-in exists and is not already completed
    $trade_in = $DB->query("SELECT * FROM trade_in_items WHERE id = ?", [$trade_in_id])[0] ?? null;
    if(!$trade_in) {
        throw new Exception('Trade-in not found');
    }
    
    if($trade_in['status'] === 'completed') {
        throw new Exception('Trade-in is already completed and cannot be modified');
    }
    
    // Handle signed document upload
    if(!isset($_FILES['signed_document']) || $_FILES['signed_document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Signed document file is required');
    }
    
    $file = $_FILES['signed_document'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if(!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, PDF');
    }
    
    $upload_dir = __DIR__ . '/../uploads/trade_in_signatures/';
    if(!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = 'trade_in_' . $trade_in_id . '_signed_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    if(!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to upload signed document');
    }
    
    // Get all accepted items for transfer
    $items = $DB->query("
        SELECT * FROM trade_in_items_details
        WHERE trade_in_id = ?
        AND item_status IN ('accepted', 'price_revised')
    ", [$trade_in_id]);
    
    if(empty($items)) {
        // Delete uploaded file if no items to transfer
        unlink($filepath);
        throw new Exception('No accepted items found in trade-in');
    }
    
    // Begin transaction - all or nothing
    $DB->query("START TRANSACTION");
    
    try {
        // Save signature to database
        $sql = "INSERT INTO trade_in_signatures (
                    trade_in_id, file_path, uploaded_by, created_at
                ) VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    file_path = VALUES(file_path),
                    uploaded_by = VALUES(uploaded_by),
                    created_at = NOW()";
        
        $DB->query($sql, [
            $trade_in_id,
            'uploads/trade_in_signatures/' . $filename,
            $user_id
        ]);
        
        // Get the starting tracking code number ONCE before loop
        // Check BOTH tables separately and take the maximum to avoid duplicates
        $max_details = $DB->query("
            SELECT MAX(CAST(SUBSTRING(tracking_code, 3) AS UNSIGNED)) as max_num 
            FROM trade_in_items_details 
            WHERE tracking_code LIKE 'SH%' AND tracking_code REGEXP '^SH[0-9]+$'
        ")[0]['max_num'] ?? 0;
        
        $max_second_hand = $DB->query("
            SELECT MAX(CAST(SUBSTRING(tracking_code, 3) AS UNSIGNED)) as max_num 
            FROM second_hand_items 
            WHERE tracking_code LIKE 'SH%' AND tracking_code REGEXP '^SH[0-9]+$'
        ")[0]['max_num'] ?? 0;
        
        $next_num = max($max_details, $max_second_hand) + 1;
        
        $items_moved = 0;
        
        // Transfer each item to second_hand_items
        foreach($items as $item) {
            // Use existing tracking codes from item, or generate new ones
            $tracking_code = $item['tracking_code'];
            $preprinted_code = $item['preprinted_code'];
            
            // If no tracking code, generate one and increment for next item
            if(empty($tracking_code)) {
                $tracking_code = "SH{$next_num}";
                $next_num++; // Increment for next item without tracking code
            }
            
            // Insert into second_hand_items
           $sql = "INSERT INTO second_hand_items (
            trade_in_reference, trade_in_item_detail_id,
            item_name, category, brand, model_number, serial_number,
            `condition`, purchase_price, 
            customer_id, customer_name, customer_contact,
            location, acquisition_date, status, item_source,
            notes, preprinted_code, tracking_code,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_stock', 'trade_in', ?, ?, ?, NOW())";

$DB->query($sql, [
    $trade_in['trade_in_reference'],
    $item['id'],
    $item['item_name'],
    $item['category'],
    $item['brand'],
    $item['model_number'],
    $item['serial_number'],
    $item['condition'],
    $item['price_paid'],
    null, // customer_id
    $trade_in['customer_name'],
    $trade_in['customer_phone'],
    $trade_in['location'],
    $trade_in['collection_date'] ?: date('Y-m-d'),
    $item['notes'],
    $preprinted_code,
    $tracking_code
]);
            
            $items_moved++;
        }
        
        // Mark trade-in as completed
        $DB->query("
            UPDATE trade_in_items 
            SET status = 'completed',
                completed_at = NOW()
            WHERE id = ?
        ", [$trade_in_id]);
        
        // Commit transaction
        $DB->query("COMMIT");
        
        echo json_encode([
            'success' => true,
            'message' => 'Trade-in completed successfully. ' . $items_moved . ' items transferred to secondhand inventory.',
            'items_moved' => $items_moved
        ]);
        
    } catch (Exception $e) {
        $DB->query("ROLLBACK");
        // Delete uploaded file on failure
        if(file_exists($filepath)) {
            unlink($filepath);
        }
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
