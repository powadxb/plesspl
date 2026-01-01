<?php
// ajax/save_quote.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require '../php/bootstrap.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authorized']));
}

$user_id = $_SESSION['dins_user_id'];

try {
    // Log the incoming data for debugging
    error_log("Quote Data: " . print_r($_POST['quote'], true));

    // Get quote data from POST
    $quoteData = json_decode($_POST['quote'], true);
    if (!$quoteData) {
        throw new Exception('Invalid quote data: ' . json_last_error_msg());
    }

    // Start transaction
    $DB->beginTransaction();

    // Log the parsed data
    error_log("Parsed Quote Data: " . print_r($quoteData, true));

    // Insert into quotation_master
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
        'total_cost' => $quoteData['totals']['cost'] ?? 0,
        'total_price' => $quoteData['totals']['total'] ?? 0,
        'total_profit' => $quoteData['totals']['profit'] ?? 0,
        'status' => 'draft'
    ];

    $query = "INSERT INTO quotation_master (" . implode(',', array_keys($masterData)) . ") 
              VALUES (" . str_repeat('?,', count($masterData) - 1) . "?)";
    
    $DB->query($query, array_values($masterData));
    $quote_id = $DB->lastInsertId();

    // Insert components
    foreach ($quoteData['components'] as $index => $component) {
        if (empty($component['name'])) continue;

        $itemData = [
            'quote_id' => $quote_id,
            'product_sku' => $component['isManual'] ? null : $component['sku'],
            'product_name' => $component['name'],
            'quantity' => $component['quantity'] ?? 1,
            'unit_cost' => $component['cost'],
            'unit_price' => $component['basePrice'], // Store pre-VAT price
            'line_order' => $index,
            'manual_entry' => $component['isManual'] ? 1 : 0
        ];

        $query = "INSERT INTO quotation_items (" . implode(',', array_keys($itemData)) . ") 
                  VALUES (" . str_repeat('?,', count($itemData) - 1) . "?)";
        
        $DB->query($query, array_values($itemData));
    }

    $DB->commit();

    echo json_encode([
        'success' => true,
        'quoteId' => $quote_id,
        'message' => 'Quote saved successfully'
    ]);

} catch (Exception $e) {
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }
    error_log("Quote Save Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save quote',
        'message' => $e->getMessage()
    ]);
}