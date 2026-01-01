<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'bootstrap.php';

$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

// Only admin can view queue
if (empty($user_details) || $user_details['admin'] < 1) {
    echo '<p>Access denied</p>';
    exit();
}

// Get queue items with product details
$queue_items = $DB->query("
    SELECT 
        q.id,
        q.sku,
        q.added_date,
        q.status,
        q.priority,
        mp.name,
        mp.manufacturer,
        mp.pos_category,
        u.username as added_by
    FROM stock_count_queue q
    LEFT JOIN master_products mp ON q.sku = mp.sku
    LEFT JOIN users u ON q.added_by_user_id = u.id
    ORDER BY q.priority DESC, q.added_date ASC
");

if (empty($queue_items)) {
    echo '<p class="text-muted">No items in counting queue. Go to <a href="zindex.php">Stock View</a> to add items.</p>';
    exit();
}
?>

<h4>Queue Management (<?= count($queue_items) ?> items)</h4>

<table class="table">
    <thead>
        <tr>
            <th>SKU</th>
            <th>Product Name</th>
            <th>Manufacturer</th>
            <th>Category</th>
            <th>Added By</th>
            <th>Added Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($queue_items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['sku']) ?></td>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= htmlspecialchars($item['manufacturer']) ?></td>
            <td><?= htmlspecialchars($item['pos_category']) ?></td>
            <td><?= htmlspecialchars($item['added_by']) ?></td>
            <td><?= date('M j, Y H:i', strtotime($item['added_date'])) ?></td>
            <td>
                <span class="badge badge-<?= $item['status'] === 'pending' ? 'warning' : ($item['status'] === 'counted' ? 'info' : 'success') ?>">
                    <?= ucfirst($item['status']) ?>
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-danger remove-from-queue" data-sku="<?= $item['sku'] ?>">
                    Remove
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<style>
.badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 0.25rem;
    color: white;
}
.badge-warning { background: #f59e0b; }
.badge-info { background: #3b82f6; }
.badge-success { background: #10b981; }
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>