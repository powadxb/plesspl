<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check permissions from database
$manage_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'",
    [$user_id]
);
$can_manage = !empty($manage_check) && $manage_check[0]['has_access'];

if (!$can_manage) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Access denied']));
}

try {
    $trade_in_id = $_POST['trade_in_id'] ?? 0;
    
    // Validate
    if(empty($trade_in_id)) {
        throw new Exception('Trade-in ID required');
    }
    
    // Get trade-in
    $trade_in = $DB->query("SELECT * FROM trade_in_items WHERE id = ?", [$trade_in_id])[0] ?? null;
    if(!$trade_in) {
        throw new Exception('Trade-in not found');
    }
    
    // Validate status
    if($trade_in['status'] !== 'accepted') {
        throw new Exception('Trade-in must be in accepted status to complete');
    }
    
    // Check payment details entered
    if($trade_in['payment_method'] === 'cash' && $trade_in['cash_amount'] <= 0) {
        throw new Exception('Cash payment amount not entered');
    }
    if($trade_in['payment_method'] === 'bank_transfer' && $trade_in['bank_amount'] <= 0) {
        throw new Exception('Bank payment amount not entered');
    }
    if($trade_in['payment_method'] === 'cash_bank') {
        if($trade_in['cash_amount'] <= 0 || $trade_in['bank_amount'] <= 0) {
            throw new Exception('Payment amounts not fully entered');
        }
    }
    
    // Check signed document uploaded
    $signed_doc = $DB->query("
        SELECT * FROM trade_in_signatures
        WHERE trade_in_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ", [$trade_in_id])[0] ?? null;
    
    if(!$signed_doc) {
        throw new Exception('Signed document must be uploaded before completing');
    }
    
    // Get all items
    $items = $DB->query("
        SELECT * FROM trade_in_items_details
        WHERE trade_in_id = ?
        AND item_status IN ('accepted', 'price_revised')
    ", [$trade_in_id]);
    
    if(empty($items)) {
        throw new Exception('No accepted items found in trade-in');
    }
    
    // Begin transaction
    $DB->query("START TRANSACTION");
    
    try {
        $items_moved = 0;
        
        // Copy each item to second_hand_items
        foreach($items as $item) {
            // Use existing tracking codes from item, or generate new ones
            $tracking_code = $item['tracking_code'];
            $preprinted_code = $item['preprinted_code'];
            
            // If no tracking code, generate one
            if(empty($tracking_code)) {
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
                $tracking_code = "SH{$next_num}";
            }
            
            // Insert into second_hand_items
            $sql = "INSERT INTO second_hand_items (
                        trade_in_reference, trade_in_item_detail_id,
                        item_name, category, serial_number,
                        `condition`, purchase_price, 
                        location, status, item_source,
                        notes, preprinted_code, tracking_code,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_stock', 'trade_in', ?, ?, ?, NOW())";
            
            $DB->query($sql, [
                $trade_in['trade_in_reference'],
                $item['id'],
                $item['item_name'],
                $item['category'],
                $item['serial_number'],
                $item['condition'],
                $item['price_paid'],
                $trade_in['location'],
                $item['notes'],
                $preprinted_code,
                $tracking_code
            ]);
            
            $items_moved++;
        }
        
        // Update trade-in status to completed
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
            'message' => 'Trade-in completed successfully',
            'items_moved' => $items_moved
        ]);
        
    } catch (Exception $e) {
        $DB->query("ROLLBACK");
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
