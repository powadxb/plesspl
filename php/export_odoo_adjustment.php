<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'bootstrap.php';

$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

// Only admin can export
if (empty($user_details) || $user_details['admin'] < 1) {
    header('Location: ../no_access.php');
    exit();
}

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    header('Location: ../count_results.php?error=invalid_session');
    exit();
}

// Get session details
$session = $DB->query("SELECT * FROM stock_count_sessions WHERE id = ?", [$session_id]);

if (empty($session)) {
    header('Location: ../count_results.php?error=session_not_found');
    exit();
}

$session = $session[0];

// Get only the SKU and counted stock for Odoo import
$results = $DB->query("
    SELECT 
        e.sku,
        e.counted_stock
    FROM stock_count_entries e
    WHERE e.session_id = ?
    ORDER BY e.sku ASC
", [$session_id]);

// Prepare filename: Location + "Inventory Adjustment" + session date/time
$location = strtoupper($session['location']);
$session_datetime = date('Y-m-d_H-i-s', strtotime($session['created_date']));
$filename = $location . '_Inventory_Adjustment_' . $session_datetime . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// NO HEADERS - directly output data rows only
foreach ($results as $row) {
    fputcsv($output, [
        $row['sku'],
        (int)$row['counted_stock']  // Convert to integer to remove decimals
    ]);
}

fclose($output);
exit();
?>