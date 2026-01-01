<?php
session_start();
require 'php/bootstrap.php';
require 'php/odoo_connection.php';

if (!isset($_SESSION['dins_user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user details to check admin access
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

if ($user_details['admin'] == 0) {
    die('Unauthorized access');
}

// Check for export password
if (!isset($_POST['export_password']) || empty($_POST['export_password'])) {
    die('Export password required');
}

// Verify export password (change this to your secure password)
$admin_export_password = 'ExportPass2024!'; // Change this password!
if ($admin_export_password !== $_POST['export_password']) {
    die('Invalid export password');
}

// Fetch tax rates
$tax_rates = [];
$response = $DB->query("SELECT tax_rate_id, tax_rate FROM tax_rates");
foreach ($response as $row) {
    $tax_rates[$row['tax_rate_id']] = floatval($row['tax_rate']);
}

// Get filter parameters from POST data (same as zlist_products.php)
$searchQuery = $_POST['searchQuery'] ?? '';
$skuSearchQuery = $_POST['skuSearchQuery'] ?? '';
$enabledProducts = $_POST['enabledProducts'] ?? '';
$wwwFilter = $_POST['wwwFilter'] ?? '';
$categoryFilter = $_POST['category'] ?? '';

// Stock filters
$csNegativeStock = $_POST['csNegativeStock'] ?? '';
$csZeroStock = $_POST['csZeroStock'] ?? '';
$csAboveStock = $_POST['csAboveStock'] ?? '';
$csAboveValue = $_POST['csAboveValue'] ?? '0';
$csBelowStock = $_POST['csBelowStock'] ?? '';
$csBelowValue = $_POST['csBelowValue'] ?? '0';

$asNegativeStock = $_POST['asNegativeStock'] ?? '';
$asZeroStock = $_POST['asZeroStock'] ?? '';
$asAboveStock = $_POST['asAboveStock'] ?? '';
$asAboveValue = $_POST['asAboveValue'] ?? '0';
$asBelowStock = $_POST['asBelowStock'] ?? '';
$asBelowValue = $_POST['asBelowValue'] ?? '0';

// Build WHERE clause (same logic as zlist_products.php)
$where_conditions = [];
$where_params = [];

// General text search
if (!empty($searchQuery)) {
    $search_words = array_filter(explode(" ", trim($searchQuery)));
    foreach ($search_words as $word) {
        $search_pattern = '%' . trim($word) . '%';
        $where_conditions[] = "(sku LIKE ? OR name LIKE ? OR manufacturer LIKE ? OR mpn LIKE ? OR ean LIKE ? OR pos_category LIKE ?)";
        array_push($where_params, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern);
    }
}

// SKU-specific search
if (!empty($skuSearchQuery)) {
    $where_conditions[] = "sku LIKE ?";
    $where_params[] = '%' . trim($skuSearchQuery) . '%';
}

// Enabled filter
if ($enabledProducts === '1') {
    $where_conditions[] = "enable = ?";
    $where_params[] = 'y';
}

// WWW filter
if ($wwwFilter === '1') {
    $where_conditions[] = "export_to_magento = ?";
    $where_params[] = 'y';
}

// Category filter
if (!empty($categoryFilter)) {
    $where_conditions[] = "pos_category = ?";
    $where_params[] = $categoryFilter;
}

// Build the query
$query = "SELECT * FROM master_products";
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(' AND ', $where_conditions);
}

$sortCol = $_POST['sortCol'] ?? '';
if (!empty($sortCol)) {
    $query .= " " . $sortCol;
} else {
    $query .= " ORDER BY sku ASC";
}

// Fetch all records
$all_records = $DB->query($query, $where_params);

if (empty($all_records)) {
    die('No records found with current filters');
}

// Get Odoo quantities for all SKUs (same as zlist_products.php)
$skus = array_column($all_records, 'sku');
$cs_quantities = getOdooQuantities($skus, 12); // CS warehouse
$as_quantities = getOdooQuantities($skus, 19); // AS warehouse

// Apply stock filters (same logic as zlist_products.php)
$filtered_records = [];
foreach ($all_records as $row) {
    $cs_qty = floatval($cs_quantities[$row['sku']] ?? 0);
    $as_qty = floatval($as_quantities[$row['sku']] ?? 0);
    
    // Default to including the record
    $include = true;

    // CS Stock Filters
    if ($csNegativeStock === '1') {
        if ($cs_qty >= 0) $include = false;
    }
    if ($csZeroStock === '1') {
        if ($cs_qty !== 0) $include = false;
    }
    if ($csAboveStock === '1') {
        $threshold = floatval($csAboveValue);
        if ($cs_qty < $threshold) $include = false;
    }
    if ($csBelowStock === '1') {
        $threshold = floatval($csBelowValue);
        if ($cs_qty > $threshold) $include = false;
    }

    // AS Stock Filters
    if ($asNegativeStock === '1') {
        if ($as_qty >= 0) $include = false;
    }
    if ($asZeroStock === '1') {
        if ($as_qty !== 0) $include = false;
    }
    if ($asAboveStock === '1') {
        $threshold = floatval($asAboveValue);
        if ($as_qty < $threshold) $include = false;
    }
    if ($asBelowStock === '1') {
        $threshold = floatval($asBelowValue);
        if ($as_qty > $threshold) $include = false;
    }

    if ($include) {
        $filtered_records[] = $row;
    }
}

// Set headers for CSV download
$filename = 'stock_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open the output stream
$output = fopen('php://output', 'w');

// CSV headers with all columns including calculated ones
$headers = [
    'SKU',
    'Product Name',
    'Manufacturer',
    'MPN',
    'EAN/Barcode',
    'Enabled',
    'WWW Enabled',
    'Main Category',
    'POS Category',
    'Cost (Ex VAT)',
    'Pricing Cost (Ex VAT)',
    'Pricing Method',
    'Pricing Method Name',
    'Retail Markup %',
    'Trade Markup %',
    'Fixed Retail Price',
    'Fixed Trade Price',
    'Retail Price (Ex VAT)',
    'Trade Price (Ex VAT)',
    'Retail Price (Inc VAT)',
    'Trade Price (Inc VAT)',
    'CS Stock Level',
    'AS Stock Level',
    'Total Stock',
    'CS Stock Value',
    'AS Stock Value',
    'Total Stock Value',
    'Retail Profit (Ex VAT)',
    'Trade Profit (Ex VAT)',
    'Tax Rate ID',
    'Last Modified'
];

// Write the CSV headers
fputcsv($output, $headers);

// Helper function for pricing method text
function getPricingMethodText($method) {
    switch ($method) {
        case 0: return "Markup on cost";
        case 1: return "Markup on P'Cost";
        case 2: return "Fixed Price";
        default: return "Unknown";
    }
}

// Write the data rows
foreach ($filtered_records as $row) {
    // Calculate stock values (same as zlist_products.php)
    $cs_qty = floatval($cs_quantities[$row['sku']] ?? 0);
    $as_qty = floatval($as_quantities[$row['sku']] ?? 0);
    
    // Ensure all values are properly typed
    $row['cost'] = floatval($row['cost']);
    $row['price'] = floatval($row['price']);
    $row['trade'] = floatval($row['trade']);
    
    // Calculate stock values
    $cs_total = round($cs_qty * $row['cost'], 2);
    $as_total = round($as_qty * $row['cost'], 2);
    $combined_total = round($cs_total + $as_total, 2);
    $total_stock = $cs_qty + $as_qty;

    // Apply VAT calculations
    $tax_rate = floatval($tax_rates[$row['tax_rate_id']] ?? 0);
    $price_with_vat = round($row['price'] * (1 + $tax_rate), 2);
    $trade_with_vat = round($row['trade'] * (1 + $tax_rate), 2);
    
    // Calculate profits
    $retail_profit = $row['price'] - $row['cost'];
    $trade_profit = $row['trade'] - $row['cost'];

    $csvRow = [
        $row['sku'],
        $row['name'],
        $row['manufacturer'],
        $row['mpn'],
        $row['ean'],
        $row['enable'] === 'y' ? 'Yes' : 'No',
        $row['export_to_magento'] === 'y' ? 'Yes' : 'No',
        $row['pless_main_category'],
        $row['pos_category'],
        number_format($row['cost'], 2),
        number_format($row['pricing_cost'], 2),
        $row['pricing_method'],
        getPricingMethodText($row['pricing_method']),
        number_format($row['retail_markup'], 2),
        number_format($row['trade_markup'], 2),
        number_format($row['fixed_retail'], 2),
        number_format($row['fixed_trade'], 2),
        number_format($row['price'], 2),
        number_format($row['trade'], 2),
        number_format($price_with_vat, 2),
        number_format($trade_with_vat, 2),
        number_format($cs_qty),
        number_format($as_qty),
        number_format($total_stock),
        number_format($cs_total, 2),
        number_format($as_total, 2),
        number_format($combined_total, 2),
        number_format($retail_profit, 2),
        number_format($trade_profit, 2),
        $row['tax_rate_id'],
        $row['modification_date']
    ];
    
    fputcsv($output, $csvRow);
}

// Add summary section
$totalProducts = count($filtered_records);
$totalCSStock = array_sum(array_map(function($row) use ($cs_quantities) {
    return floatval($cs_quantities[$row['sku']] ?? 0);
}, $filtered_records));
$totalASStock = array_sum(array_map(function($row) use ($as_quantities) {
    return floatval($as_quantities[$row['sku']] ?? 0);
}, $filtered_records));
$totalStockValue = array_sum(array_map(function($row) use ($cs_quantities, $as_quantities) {
    $cs_qty = floatval($cs_quantities[$row['sku']] ?? 0);
    $as_qty = floatval($as_quantities[$row['sku']] ?? 0);
    return ($cs_qty + $as_qty) * floatval($row['cost']);
}, $filtered_records));

// Empty row for separation
fputcsv($output, []);

// Summary
fputcsv($output, ['EXPORT SUMMARY']);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, ['Exported By User ID', $user_id]);
fputcsv($output, ['Total Products Exported', $totalProducts]);
fputcsv($output, ['Total CS Stock', number_format($totalCSStock)]);
fputcsv($output, ['Total AS Stock', number_format($totalASStock)]);
fputcsv($output, ['Total Combined Stock', number_format($totalCSStock + $totalASStock)]);
fputcsv($output, ['Total Stock Value', '£' . number_format($totalStockValue, 2)]);

// Add filter information
if (!empty($searchQuery)) {
    fputcsv($output, ['Search Query Applied', $searchQuery]);
}
if (!empty($skuSearchQuery)) {
    fputcsv($output, ['SKU Search Applied', $skuSearchQuery]);
}
if ($enabledProducts === '1') {
    fputcsv($output, ['Filter Applied', 'Enabled Products Only']);
}
if ($wwwFilter === '1') {
    fputcsv($output, ['Filter Applied', 'WWW Products Only']);
}
if (!empty($categoryFilter)) {
    fputcsv($output, ['Category Filter Applied', $categoryFilter]);
}

// Close the output stream
fclose($output);
exit();
?>