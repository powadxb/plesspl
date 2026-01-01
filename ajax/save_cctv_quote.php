<?php
// ajax/save_cctv_quote.php
session_start();
require '../php/bootstrap.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

$user_id = $_SESSION['dins_user_id'];

try {
    // Get and decode quote data
    if (!isset($_POST['quote'])) {
        throw new Exception('No quote data provided');
    }
    
    $quoteData = json_decode($_POST['quote'], true);
    if (!$quoteData) {
        throw new Exception('Invalid quote data');
    }

    // Validate customer data
    if (!isset($quoteData['customer']) || empty($quoteData['customer']['name'])) {
        throw new Exception('Customer information is required');
    }

    // Validate components
    if (!isset($quoteData['components']) || empty($quoteData['components'])) {
        throw new Exception('At least one component is required');
    }

    // Start transaction
    $DB->beginTransaction();

    // Check if we're updating an existing quote
    $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : null;

    if ($quote_id) {
        // Update existing quote
        $updateData = [
            'customer_id' => $quoteData['customer']['id'] ?? null,
            'customer_name' => $quoteData['customer']['name'],
            'customer_email' => $quoteData['customer']['email'] ?? null,
            'customer_phone' => $quoteData['customer']['phone'] ?? null,
            'customer_address' => $quoteData['customer']['address'] ?? null,
            'modified_by' => $user_id,
            'date_modified' => date('Y-m-d H:i:s'),
            'installation_charge' => $quoteData['services']['installation'] ?? 0,
            'config_charge' => $quoteData['services']['configuration'] ?? 0,
            'testing_charge' => $quoteData['services']['testing'] ?? 0,
            'price_type' => $quoteData['priceType'] ?? 'R',
            'total_cost' => $quoteData['totals']['profit'] ?? 0,
            'total_price' => $quoteData['totals']['total'] ?? 0,
            'total_profit' => $quoteData['totals']['profit'] ?? 0,
            'status' => 'draft',
            'template_name' => $quoteData['templateName'] ?? null,
            'is_template' => $quoteData['isTemplate'] ?? 0
        ];

        $fields = [];
        $values = [];
        foreach ($updateData as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $quote_id;

        $query = "UPDATE cctv_quotation_master SET " . implode(', ', $fields) . " WHERE id = ?";
        $DB->query($query, $values);

        // Delete existing items
        $DB->query("DELETE FROM cctv_quotation_items WHERE quote_id = ?", [$quote_id]);
    } else {
        // Insert new quote
        $masterData = [
            'customer_id' => $quoteData['customer']['id'] ?? null,
            'customer_name' => $quoteData['customer']['name'],
            'customer_email' => $quoteData['customer']['email'] ?? null,
            'customer_phone' => $quoteData['customer']['phone'] ?? null,
            'customer_address' => $quoteData['customer']['address'] ?? null,
            'date_created' => date('Y-m-d H:i:s'),
            'created_by' => $user_id,
            'modified_by' => $user_id,
            'installation_charge' => $quoteData['services']['installation'] ?? 0,
            'config_charge' => $quoteData['services']['configuration'] ?? 0,
            'testing_charge' => $quoteData['services']['testing'] ?? 0,
            'price_type' => $quoteData['priceType'] ?? 'R',
            'total_cost' => 0, // Will calculate from items
            'total_price' => $quoteData['totals']['total'] ?? 0,
            'total_profit' => $quoteData['totals']['profit'] ?? 0,
            'status' => 'draft',
            'template_name' => $quoteData['templateName'] ?? null,
            'is_template' => $quoteData['isTemplate'] ?? 0
        ];

        $query = "INSERT INTO cctv_quotation_master (" . implode(',', array_keys($masterData)) . ") 
                  VALUES (" . str_repeat('?,', count($masterData) - 1) . "?)";
        
        $DB->query($query, array_values($masterData));
        $quote_id = $DB->lastInsertId();
    }

    // Insert quote items
    $totalCost = 0;
    foreach ($quoteData['components'] as $index => $component) {
        if (empty($component['name'])) continue;

        $itemCost = floatval($component['cost'] ?? 0) * intval($component['quantity'] ?? 1);
        $totalCost += $itemCost;

        $itemData = [
            'quote_id' => $quote_id,
            'component_type' => $component['type'] ?? 'unknown',
            'product_sku' => $component['isManual'] ? null : ($component['sku'] ?? null),
            'product_name' => $component['name'],
            'quantity' => $component['quantity'] ?? 1,
            'unit_cost' => $component['cost'] ?? 0,
            'unit_price' => $component['basePrice'] ?? 0,
            'price_inc_vat' => $component['priceIncVat'] ?? 0,
            'line_order' => $index,
            'manual_entry' => $component['isManual'] ? 1 : 0,
            'price_edited' => ($component['priceEdited'] ?? false) ? 1 : 0
        ];

        $query = "INSERT INTO cctv_quotation_items (" . implode(',', array_keys($itemData)) . ") 
                  VALUES (" . str_repeat('?,', count($itemData) - 1) . "?)";
        
        $DB->query($query, array_values($itemData));
    }

    // Update total cost in master record
    $DB->query("UPDATE cctv_quotation_master SET total_cost = ? WHERE id = ?", [$totalCost, $quote_id]);

    // Commit transaction
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
    
    error_log('Save CCTV Quote Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}