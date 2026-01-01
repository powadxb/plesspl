<?php
// ajax/create_order.php
session_start();
require '../php/bootstrap.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authorized']));
}

$user_id = $_SESSION['dins_user_id'];

try {
    // Check if this is a simple quote conversion (from view_quote.php)
    if (isset($_POST['quoteId']) && !isset($_POST['quote'])) {
        // Simple conversion: just convert existing quote to order
        $quote_id = intval($_POST['quoteId']);
        
        if (!$quote_id) {
            throw new Exception('Invalid quote ID');
        }
        
        // Start transaction
        $DB->beginTransaction();
        
        // Get quote details and verify it exists and isn't already converted
        $quote = $DB->query("
            SELECT * FROM quotation_master 
            WHERE id = ? AND status != 'converted'
        ", [$quote_id]);
        
        if (empty($quote)) {
            throw new Exception('Quote not found or already converted');
        }
        
        $quote = $quote[0];
        
        // Update quote status to converted
        $DB->query("
            UPDATE quotation_master 
            SET status = 'converted', modified_by = ?, date_modified = NOW() 
            WHERE id = ?
        ", [$user_id, $quote_id]);
        
        // Create system order
        $orderData = [
            'quote_id' => $quote_id,
            'due_date' => date('Y-m-d', strtotime('+7 days')),
            'order_level' => 0,
            'status' => 'pending',
            'date_created' => date('Y-m-d H:i:s'),
            'created_by' => $user_id,
            'modified_by' => $user_id
        ];
        
        $query = "INSERT INTO system_orders (" . implode(',', array_keys($orderData)) . ") 
                  VALUES (" . str_repeat('?,', count($orderData) - 1) . "?)";
        
        $DB->query($query, array_values($orderData));
        $order_id = $DB->lastInsertId();
        
        // Add initial comment
        $initialComment = [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'comment' => 'Order created from quote #' . $quote_id,
            'date_created' => date('Y-m-d H:i:s')
        ];
        
        $query = "INSERT INTO system_order_comments (" . implode(',', array_keys($initialComment)) . ") 
                  VALUES (" . str_repeat('?,', count($initialComment) - 1) . "?)";
        
        $DB->query($query, array_values($initialComment));
        
        $DB->commit();
        
        echo json_encode([
            'success' => true,
            'quoteId' => $quote_id,
            'orderId' => $order_id,
            'message' => 'Order created successfully'
        ]);
        exit;
    }
    
    // Otherwise, handle the full quote data workflow (from quote builder)
    $quoteData = json_decode($_POST['quote'], true);
    if (!$quoteData) {
        throw new Exception('Invalid quote data');
    }

    // Start transaction
    $DB->beginTransaction();

    // First save or update the quote
    $quote_id = $quoteData['quoteId'] ?? null;
    if (!$quote_id) {
        // Save quote first
        $masterData = [
            'customer_id' => $quoteData['customer']['id'],
            'customer_name' => $quoteData['customer']['name'],
            'customer_email' => $quoteData['customer']['email'],
            'customer_phone' => $quoteData['customer']['phone'],
            'customer_address' => $quoteData['customer']['address'],
            'date_created' => date('Y-m-d H:i:s'),
            'created_by' => $user_id,
            'modified_by' => $user_id,
            'build_charge' => $quoteData['buildCharge'],
            'price_type' => $quoteData['priceType'],
            'total_cost' => $quoteData['totals']['cost'],
            'total_price' => $quoteData['totals']['total'],
            'total_profit' => $quoteData['totals']['profit'],
            'status' => 'converted'
        ];

        $query = "INSERT INTO quotation_master (" . implode(',', array_keys($masterData)) . ") 
                  VALUES (" . str_repeat('?,', count($masterData) - 1) . "?)";
        
        $DB->query($query, array_values($masterData));
        $quote_id = $DB->lastInsertId();

        // Save quote items
        foreach ($quoteData['components'] as $index => $component) {
            if (empty($component['name'])) continue;

            $itemData = [
                'quote_id' => $quote_id,
                'product_sku' => $component['isManual'] ? null : $component['sku'],
                'product_name' => $component['name'],
                'quantity' => $component['quantity'] ?? 1,
                'unit_cost' => $component['cost'],
                'unit_price' => $component['basePrice'],
                'line_order' => $index,
                'manual_entry' => $component['isManual'] ? 1 : 0
            ];

            $query = "INSERT INTO quotation_items (" . implode(',', array_keys($itemData)) . ") 
                      VALUES (" . str_repeat('?,', count($itemData) - 1) . "?)";
            
            $DB->query($query, array_values($itemData));
        }
    } else {
        // Update existing quote status to converted
        $DB->query("UPDATE quotation_master SET status = 'converted' WHERE id = ?", [$quote_id]);
    }

    // Create system order
    $orderData = [
        'quote_id' => $quote_id,
        'due_date' => date('Y-m-d', strtotime('+7 days')),
        'order_level' => 0,
        'status' => 'pending',
        'date_created' => date('Y-m-d H:i:s'),
        'created_by' => $user_id,
        'modified_by' => $user_id
    ];

    $query = "INSERT INTO system_orders (" . implode(',', array_keys($orderData)) . ") 
              VALUES (" . str_repeat('?,', count($orderData) - 1) . "?)";
    
    $DB->query($query, array_values($orderData));
    $order_id = $DB->lastInsertId();

    // Add initial comment
    $initialComment = [
        'order_id' => $order_id,
        'user_id' => $user_id,
        'comment' => 'Order created from quote #' . $quote_id,
        'date_created' => date('Y-m-d H:i:s')
    ];

    $query = "INSERT INTO system_order_comments (" . implode(',', array_keys($initialComment)) . ") 
              VALUES (" . str_repeat('?,', count($initialComment) - 1) . "?)";
    
    $DB->query($query, array_values($initialComment));

    $DB->commit();

    echo json_encode([
        'success' => true,
        'quoteId' => $quote_id,
        'orderId' => $order_id,
        'message' => 'Order created successfully'
    ]);

} catch (Exception $e) {
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create order',
        'message' => $e->getMessage()
    ]);
}
?>