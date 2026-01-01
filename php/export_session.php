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

// Get session results - FIXED to use location-specific variance calculation
$results = $DB->query("
    SELECT 
        e.sku,
        mp.name as product_name,
        mp.manufacturer,
        mp.pos_category,
        e.system_cs_stock,
        e.system_as_stock,
        e.system_stock_used,
        e.target_location,
        e.counted_stock,
        e.variance_amount,
        mp.cost,
        (e.variance_amount * mp.cost) as variance_value,
        u.username as counted_by,
        e.count_date,
        e.applied_to_odoo,
        e.applied_date,
        e.auto_zeroed
    FROM stock_count_entries e
    LEFT JOIN master_products mp ON e.sku = mp.sku
    LEFT JOIN users u ON e.counted_by_user_id = u.id
    WHERE e.session_id = ?
    ORDER BY e.count_date ASC
", [$session_id]);

// Prepare CSV
$session_name_clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $session['name']);
$filename = 'stock_count_' . $session_name_clean . '_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fputs($output, "\xEF\xBB\xBF");

// Add session header information
fputcsv($output, ['Stock Count Export']);
fputcsv($output, ['Session Name:', $session['name']]);
fputcsv($output, ['Session Description:', $session['description'] ?? 'No description']);
fputcsv($output, ['Created Date:', $session['created_date']]);
fputcsv($output, ['Status:', ucfirst($session['status'])]);
fputcsv($output, ['Target Location:', strtoupper($session['location'])]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Exported By:', $user_details['username']]);
fputcsv($output, []); // Empty row

// Add summary statistics
$total_items = count($results);
$auto_zeroed_count = count(array_filter($results, function($r) { return $r['auto_zeroed'] == 1; }));
$manually_counted = $total_items - $auto_zeroed_count;
$total_variance_value = array_sum(array_column($results, 'variance_value'));
$positive_variances = count(array_filter($results, function($r) { return $r['variance_amount'] > 0; }));
$negative_variances = count(array_filter($results, function($r) { return $r['variance_amount'] < 0; }));
$exact_matches = count(array_filter($results, function($r) { return $r['variance_amount'] == 0 && $r['auto_zeroed'] == 0; }));

fputcsv($output, ['SUMMARY STATISTICS']);
fputcsv($output, ['Total Items Counted:', $total_items]);
fputcsv($output, ['Manually Counted Items:', $manually_counted]);
fputcsv($output, ['Auto-Zeroed Items:', $auto_zeroed_count]);
fputcsv($output, ['Items with Overages:', $positive_variances]);
fputcsv($output, ['Items with Shortages:', $negative_variances]);
fputcsv($output, ['Items with Exact Match:', $exact_matches]);
fputcsv($output, ['Total Value Impact:', '£' . number_format($total_variance_value, 2)]);
fputcsv($output, []); // Empty row

// Add column headers
fputcsv($output, [
    'SKU',
    'Product Name',
    'Manufacturer',
    'Category',
    'Target Location',
    'System CS Stock',
    'System AS Stock',
    'Target Location Stock',
    'Counted Stock',
    'Variance (vs Target)',
    'Cost per Unit',
    'Variance Value',
    'Counted By',
    'Count Date',
    'Auto-Zeroed',
    'Applied to Odoo',
    'Applied Date'
]);

// Add data rows
foreach ($results as $row) {
    fputcsv($output, [
        $row['sku'],
        $row['product_name'],
        $row['manufacturer'],
        $row['pos_category'],
        strtoupper($row['target_location']),
        number_format($row['system_cs_stock'], 0),
        number_format($row['system_as_stock'], 0),
        number_format($row['system_stock_used'], 0),
        number_format($row['counted_stock'], 0),
        ($row['variance_amount'] > 0 ? '+' : '') . number_format($row['variance_amount'], 0),
        '£' . number_format($row['cost'], 2),
        '£' . ($row['variance_value'] > 0 ? '+' : '') . number_format($row['variance_value'], 2),
        $row['counted_by'],
        $row['count_date'],
        $row['auto_zeroed'] == 1 ? 'Yes' : 'No',
        $row['applied_to_odoo'] === 'yes' ? 'Yes' : 'No',
        $row['applied_date'] ?? 'Not Applied'
    ]);
}

fclose($output);
exit();
?>