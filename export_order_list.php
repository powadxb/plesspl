<?php
session_start();
require 'php/bootstrap.php';

// Check if the user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    die('Unauthorized access');
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;

if (!$is_admin) {
    die('You do not have permission to access this resource');
}

// Fetch the order list
$orders = $DB->query("SELECT * FROM order_list ORDER BY added_at DESC");

if (!$orders) {
    die('No data available to export');
}

// Set headers for CSV file download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=order_list.csv');

// Open output stream for writing CSV data
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, ['SKU', 'Name', 'Manufacturer', 'EAN', 'Quantity', 'Status', 'Added At', 'Requested By']);

// Write rows to CSV
foreach ($orders as $order) {
    fputcsv($output, [
        $order['sku'] ?? '',
        $order['name'],
        $order['manufacturer'] ?? '',
        $order['ean'] ?? '',
        $order['quantity'],
        $order['status'],
        $order['added_at'],
        $order['user_id']
    ]);
}

// Close output stream
fclose($output);
