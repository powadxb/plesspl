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

// Get session results
$results = $DB->query("
    SELECT 
        e.sku,
        mp.name as product_name,
        mp.manufacturer,
        mp.pos_category,
        e.system_cs_stock,
        e.system_as_stock,
        (e.system_cs_stock + e.system_as_stock) as total_system_stock,
        e.counted_stock,
        (e.counted_stock - (e.system_cs_stock + e.system_as_stock)) as variance,
        mp.cost,
        ((e.counted_stock - (e.system_cs_stock + e.system_as_stock)) * mp.cost) as variance_value,
        u.username as counted_by,
        e.count_date,
        e.applied_to_odoo,
        e.applied_date
    FROM stock_count_entries e
    LEFT JOIN master_products mp ON e.sku = mp.sku
    LEFT JOIN users u ON e.counted_by_user_id = u.id
    WHERE e.session_id = ?
    ORDER BY e.count_date ASC
", [$session_id]);

// Prepare CSV
$filename = 'stock_count_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $session['name']) . '_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add session header information
fputcsv($output, ['Stock Count Export']);
fputcsv($output, ['Session Name:', $session['name']]);
fputcsv($output, ['Session Description:', $session['description']]);
fputcsv($output, ['Created Date:', $session['created_date']]);
fputcsv($output, ['Status:', $session['status']]);
fputcsv($output, ['Location:', $session['location']]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Exported By:', $user_details['username']]);
fputcsv($output, []); // Empty row

// Add summary statistics
$total_items = count($results);
$total_variance_value = array_sum(array_column($results, 'variance_value'));
$positive_variances = count(array_filter($results, function($r) { return $r['variance'] > 0; }));
$negative_variances = count(array_filter($results, function($r) { return $r['variance'] < 0; }));
$exact_matches = count(array_filter($results, function($r) { return $r['variance'] == 0; }));

fputcsv($output, ['SUMMARY STATISTICS']);
fputcsv($output, ['Total Items Counted:', $total_items]);
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
    'System CS Stock',
    'System AS Stock',
    'Total System Stock',
    'Counted Stock',
    'Variance',
    'Cost per Unit',
    'Variance Value',
    'Counted By',
    'Count Date',
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
        $row['system_cs_stock'],
        $row['system_as_stock'],
        $row['total_system_stock'],
        $row['counted_stock'],
        $row['variance'],
        '£' . number_format($row['cost'], 2),
        '£' . number_format($row['variance_value'], 2),
        $row['counted_by'],
        $row['count_date'],
        $row['applied_to_odoo'] === 'yes' ? 'Yes' : 'No',
        $row['applied_date'] ?? 'Not Applied'
    ]);
}

fclose($output);
exit();
?>