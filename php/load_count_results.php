<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent any output before this point
ob_start();
require_once 'bootstrap.php';
require_once 'odoo_connection.php';
// Clean any unwanted output
ob_end_clean();

$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

// Only admin can view results
if (empty($user_details) || $user_details['admin'] < 1) {
    echo '<p>Access denied</p>';
    exit();
}

// Get count results with product details
$count_results = $DB->query("
    SELECT 
        ce.sku,
        ce.counted_stock,
        ce.system_cs_stock,
        ce.system_as_stock,
        ce.count_date,
        mp.name,
        mp.manufacturer,
        mp.pos_category,
        mp.cost,
        u.username as counted_by,
        (ce.counted_stock - (ce.system_cs_stock + ce.system_as_stock)) as variance,
        ((ce.counted_stock - (ce.system_cs_stock + ce.system_as_stock)) * mp.cost) as variance_value
    FROM stock_count_entries ce
    LEFT JOIN master_products mp ON ce.sku = mp.sku
    LEFT JOIN users u ON ce.counted_by_user_id = u.id
    ORDER BY ce.count_date DESC
");

if (empty($count_results)) {
    echo '<p class="text-muted">No count results yet. Items will appear here after staff submit their counts.</p>';
    exit();
}

// Calculate totals
$total_variance_value = array_sum(array_column($count_results, 'variance_value'));
$total_counted = count($count_results);
$positive_variances = count(array_filter($count_results, function($r) { return $r['variance'] > 0; }));
$negative_variances = count(array_filter($count_results, function($r) { return $r['variance'] < 0; }));
?>

<div style="margin-bottom: 0.5rem;">
    <h5 style="margin: 0 0 0.25rem 0; font-size: 0.8rem; font-weight: 600;">Count Results Summary</h5>
    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
        <span style="font-size: 0.7rem; padding: 0.125rem 0.25rem; background: #f3f4f6; border-radius: 0.125rem;">
            Total Items: <strong><?= $total_counted ?></strong>
        </span>
        <span style="font-size: 0.7rem; padding: 0.125rem 0.25rem; background: #fef3c7; border-radius: 0.125rem;">
            Overages: <strong><?= $positive_variances ?></strong>
        </span>
        <span style="font-size: 0.7rem; padding: 0.125rem 0.25rem; background: #fecaca; border-radius: 0.125rem;">
            Shortages: <strong><?= $negative_variances ?></strong>
        </span>
        <span style="font-size: 0.7rem; padding: 0.125rem 0.25rem; background: <?= $total_variance_value >= 0 ? '#d1fae5' : '#fecaca' ?>; border-radius: 0.125rem;">
            Value Impact: <strong>£<?= number_format($total_variance_value, 2) ?></strong>
        </span>
    </div>
</div>

<table class="table" style="font-size: 0.7rem;">
    <thead>
        <tr>
            <th>SKU</th>
            <th>Product Name</th>
            <th>Category</th>
            <th>System CS</th>
            <th>System AS</th>
            <th>Total System</th>
            <th>Counted</th>
            <th>Variance</th>
            <th>Cost</th>
            <th>Value Impact</th>
            <th>Counted By</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($count_results as $result): ?>
        <?php 
        $total_system = $result['system_cs_stock'] + $result['system_as_stock'];
        $variance_class = '';
        if ($result['variance'] > 0) $variance_class = 'style="background-color: #fef3c7;"'; // Yellow for overage
        else if ($result['variance'] < 0) $variance_class = 'style="background-color: #fecaca;"'; // Red for shortage
        else $variance_class = 'style="background-color: #d1fae5;"'; // Green for exact match
        ?>
        <tr <?= $variance_class ?>>
            <td><strong><?= htmlspecialchars($result['sku']) ?></strong></td>
            <td><?= htmlspecialchars($result['name']) ?></td>
            <td><?= htmlspecialchars($result['pos_category']) ?></td>
            <td><?= number_format($result['system_cs_stock']) ?></td>
            <td><?= number_format($result['system_as_stock']) ?></td>
            <td><strong><?= number_format($total_system) ?></strong></td>
            <td><strong><?= number_format($result['counted_stock']) ?></strong></td>
            <td><strong <?= $result['variance'] != 0 ? 'style="color: ' . ($result['variance'] > 0 ? '#d97706' : '#dc2626') . ';"' : '' ?>>
                <?= $result['variance'] > 0 ? '+' : '' ?><?= number_format($result['variance']) ?>
            </strong></td>
            <td>£<?= number_format($result['cost'], 2) ?></td>
            <td><strong <?= $result['variance_value'] != 0 ? 'style="color: ' . ($result['variance_value'] > 0 ? '#d97706' : '#dc2626') . ';"' : '' ?>>
                £<?= $result['variance_value'] > 0 ? '+' : '' ?><?= number_format($result['variance_value'], 2) ?>
            </strong></td>
            <td><?= htmlspecialchars($result['counted_by']) ?></td>
            <td><?= date('M j, H:i', strtotime($result['count_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<style>
.table th:nth-child(1) { width: 60px; }   /* SKU */
.table th:nth-child(2) { width: 180px; }  /* Product Name */
.table th:nth-child(3) { width: 100px; }  /* Category */
.table th:nth-child(4) { width: 60px; }   /* System CS */
.table th:nth-child(5) { width: 60px; }   /* System AS */
.table th:nth-child(6) { width: 70px; }   /* Total System */
.table th:nth-child(7) { width: 60px; }   /* Counted */
.table th:nth-child(8) { width: 60px; }   /* Variance */
.table th:nth-child(9) { width: 60px; }   /* Cost */
.table th:nth-child(10) { width: 80px; }  /* Value Impact */
.table th:nth-child(11) { width: 80px; }  /* Counted By */
.table th:nth-child(12) { width: 80px; }  /* Date */
</style>