<?php
// ajax/update_quote.php
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
    // Get quote data from POST
    $quoteData = json_decode($_POST['quote'], true);
    if (!$quoteData) {
        throw new Exception('Invalid quote data: ' . json_last_error_msg());
    }

    $quote_id = isset($_POST['quoteId']) ? intval($_POST['quoteId']) : 0;
    
    if (!$quote_id) {
        throw new Exception('Quote ID is required for update');
    }

    // Check if quote exists and can be edited
    $existingQuote = $DB->query("SELECT status FROM quotation_master WHERE id = ?", [$quote_id]);
    
    if (empty($existingQuote)) {
        throw new Exception('Quote not found');
    }

    if ($existingQuote[0]['status'] === 'converted') {
        throw new Exception('Cannot edit a quote that has been converted to an order');
    }

    // Start transaction
    $DB->beginTransaction();

    // Update quotation_master
    $masterData = [
        'customer_id' => $quoteData['customer']['id'],
        'customer_name' => $quoteData['customer']['name'],
        'customer_email' => $quoteData['customer']['email'],
        'customer_phone' => $quoteData['customer']['phone'],
        'customer_address' => $quoteData['customer']['address'],
        'date_modified' => date('Y-m-d H:i:s'),
        'modified_by' => $user_id,
        'build_charge' => $quoteData['buildCharge'],
        'price_type' => $quoteData['priceType'],
        'total_cost' => $quoteData['totals']['cost'] ?? 0,
        'total_price' => $quoteData['totals']['total'] ?? 0,
        'total_profit' => $quoteData['totals']['profit'] ?? 0
    ];

    $setClause = implode(', ', array_map(function($key) {
        return "$key = ?";
    }, array_keys($masterData)));

    $query = "UPDATE quotation_master SET $setClause WHERE id = ?";
    $params = array_merge(array_values($masterData), [$quote_id]);
    
    $DB->query($query, $params);

    // Delete existing items
    $DB->query("DELETE FROM quotation_items WHERE quote_id = ?", [$quote_id]);

    // Insert updated components
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

    $DB->commit();

    echo json_encode([
        'success' => true,
        'quoteId' => $quote_id,
        'message' => 'Quote updated successfully'
    ]);

} catch (Exception $e) {
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }
    error_log("Quote Update Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update quote',
        'message' => $e->getMessage()
    ]);
}