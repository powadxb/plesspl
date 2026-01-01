<?php
// ajax/get_quote.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Set JSON header first
header('Content-Type: application/json');

// Start output buffering to catch any unexpected output
ob_start();

try {
    require '../php/bootstrap.php';
    
    // Ensure user is logged in
    if (!isset($_SESSION['dins_user_id'])) {
        ob_end_clean();
        http_response_code(401);
        exit(json_encode(['success' => false, 'error' => 'Not authorized']));
    }

    $quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$quote_id) {
        throw new Exception('Quote ID is required');
    }

    // Fetch quote master data - WITHOUT the customers join since customer data is stored in quote
    $quote = $DB->query("
        SELECT q.*
        FROM quotation_master q
        WHERE q.id = ?
    ", [$quote_id]);

    if (empty($quote)) {
        throw new Exception('Quote not found');
    }

    $quote = $quote[0];

    // Check if quote can be edited (not converted to order)
    if ($quote['status'] === 'converted') {
        throw new Exception('Cannot edit a quote that has been converted to an order');
    }

    // Fetch quote items
    $items = $DB->query("
        SELECT * 
        FROM quotation_items 
        WHERE quote_id = ?
        ORDER BY line_order
    ", [$quote_id]);

    // Format the response to match the structure expected by pc_quote.php
    $response = [
        'success' => true,
        'quote' => [
            'id' => $quote['id'],
            'customer' => [
                'id' => $quote['customer_id'],
                'name' => $quote['customer_name'],
                'email' => $quote['customer_email'],
                'phone' => $quote['customer_phone'],
                'address' => $quote['customer_address']
            ],
            'priceType' => $quote['price_type'],
            'buildCharge' => floatval($quote['build_charge']),
            'status' => $quote['status'],
            'components' => []
        ]
    ];

    // Format items
    foreach ($items as $item) {
        $response['quote']['components'][] = [
            'sku' => $item['product_sku'],
            'name' => $item['product_name'],
            'quantity' => intval($item['quantity']),
            'cost' => floatval($item['unit_cost']),
            'basePrice' => floatval($item['unit_price']),
            'isManual' => intval($item['manual_entry']) === 1
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load quote',
        'message' => $e->getMessage()
    ]);
}