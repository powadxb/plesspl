<?php
// ajax/get_cctv_quote.php
session_start();
require '../php/bootstrap.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

try {
    if (!isset($_GET['id'])) {
        throw new Exception('No quote ID provided');
    }
    
    $quote_id = intval($_GET['id']);
    
    // Fetch quote master data
    $quote = $DB->query("
        SELECT * FROM cctv_quotation_master WHERE id = ?
    ", [$quote_id]);
    
    if (empty($quote)) {
        throw new Exception('Quote not found');
    }
    
    $quote = $quote[0];
    
    // Fetch quote items
    $items = $DB->query("
        SELECT * FROM cctv_quotation_items 
        WHERE quote_id = ?
        ORDER BY line_order
    ", [$quote_id]);
    
    // Build quote data structure
    $quoteData = [
        'customer' => [
            'id' => $quote['customer_id'],
            'name' => $quote['customer_name'],
            'email' => $quote['customer_email'],
            'phone' => $quote['customer_phone'],
            'address' => $quote['customer_address']
        ],
        'priceType' => $quote['price_type'],
        'services' => [
            'installation' => floatval($quote['installation_charge']),
            'configuration' => floatval($quote['config_charge']),
            'testing' => floatval($quote['testing_charge'])
        ],
        'components' => [],
        'totals' => [
            'total' => floatval($quote['total_price']),
            'profit' => floatval($quote['total_profit'])
        ]
    ];
    
    // Add components
    foreach ($items as $item) {
        $quoteData['components'][] = [
            'type' => $item['component_type'],
            'sku' => $item['product_sku'],
            'name' => $item['product_name'],
            'quantity' => intval($item['quantity']),
            'basePrice' => floatval($item['unit_price']),
            'priceIncVat' => floatval($item['price_inc_vat']),
            'cost' => floatval($item['unit_cost']),
            'isManual' => $item['manual_entry'] == 1,
            'priceEdited' => $item['price_edited'] == 1
        ];
    }
    
    echo json_encode([
        'success' => true,
        'quote' => $quoteData
    ]);

} catch (Exception $e) {
    error_log('Get CCTV Quote Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}