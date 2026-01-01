<?php
session_start();
$page_title = 'Orders';
require 'php/bootstrap.php';

// Ensure session is active
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(q.customer_name LIKE ? OR q.customer_email LIKE ? OR o.id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch orders
$orders = $DB->query("
    SELECT o.*, 
           q.customer_name,
           q.customer_email,
           q.total_price,
           u.username as created_by_name,
           (SELECT COUNT(*) FROM system_order_comments WHERE order_id = o.id) as comment_count
    FROM system_orders o
    LEFT JOIN quotation_master q ON o.quote_id = q.id
    LEFT JOIN users u ON o.created_by = u.id
    {$where_sql}
    ORDER BY 
        CASE o.status
            WHEN 'in_progress' THEN 1
            WHEN 'pending' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'cancelled' THEN 4
        END,
        o.order_level DESC,
        o.due_date ASC,
        o.date_created DESC
", $params);

// Get status counts for filter badges
$status_counts_raw = $DB->query("
    SELECT status, COUNT(*) as count
    FROM system_orders
    GROUP BY status
");

$status_counts = [];
foreach ($status_counts_raw as $row) {
    $status_counts[$row['status']] = $row['count'];
}

require 'assets/header.php';
require 'assets/navbar.php';
?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-boxes"></i> Orders</h2>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="form-inline">
                <div class="form-group mr-3">
                    <label for="status" class="mr-2">Status:</label>
                    <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                            Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                        </option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>
                            In Progress (<?php echo $status_counts['in_progress'] ?? 0; ?>)
                        </option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>
                            Completed (<?php echo $status_counts['completed'] ?? 0; ?>)
                        </option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>
                            Cancelled (<?php echo $status_counts['cancelled'] ?? 0; ?>)
                        </option>
                    </select>
                </div>
                <div class="form-group mr-3 flex-grow-1">
                    <input type="text" name="search" class="form-control w-100" placeholder="Search by order ID, customer name or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-primary mr-2">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Orders List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No orders found matching your criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Created</th>
                                <th class="text-center">Comments</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="<?php echo $order['order_level'] >= 2 ? 'table-danger' : ($order['order_level'] == 1 ? 'table-warning' : ''); ?>">
                                    <td>
                                        <strong>#<?php echo $order['id']; ?></strong>
                                        <?php if ($order['order_level'] >= 2): ?>
                                            <i class="fas fa-exclamation-triangle text-danger ml-1" title="Urgent"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    </td>
                                    <td><strong>£<?php echo number_format($order['total_price'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $order['status'] == 'completed' ? 'success' : 
                                                ($order['status'] == 'in_progress' ? 'warning' : 
                                                ($order['status'] == 'cancelled' ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $order['order_level'] >= 2 ? 'danger' : 
                                                ($order['order_level'] == 1 ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo $order['order_level'] == 0 ? 'Normal' : 
                                                ($order['order_level'] == 1 ? 'High' : 'Urgent'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['due_date']): ?>
                                            <?php
                                                $due = strtotime($order['due_date']);
                                                $today = strtotime(date('Y-m-d'));
                                                $days_until = floor(($due - $today) / 86400);
                                                $overdue = $days_until < 0;
                                                $text_class = $overdue ? 'text-danger' : ($days_until <= 3 ? 'text-warning' : '');
                                            ?>
                                            <span class="<?php echo $text_class; ?>">
                                                <?php echo date('d/m/Y', $due); ?>
                                                <?php if ($overdue): ?>
                                                    <br><small>(<?php echo abs($days_until); ?> days overdue)</small>
                                                <?php elseif ($days_until <= 7): ?>
                                                    <br><small>(<?php echo $days_until; ?> days)</small>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($order['date_created'])); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['created_by_name']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($order['comment_count'] > 0): ?>
                                            <span class="badge badge-info">
                                                <i class="fas fa-comments"></i> <?php echo $order['comment_count']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary" title="View Order">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="view_quote.php?id=<?php echo $order['quote_id']; ?>" class="btn btn-sm btn-secondary" title="View Quote">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5>Pending</h5>
                    <h2><?php echo $status_counts['pending'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>In Progress</h5>
                    <h2><?php echo $status_counts['in_progress'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Completed</h5>
                    <h2><?php echo $status_counts['completed'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5>Cancelled</h5>
                    <h2><?php echo $status_counts['cancelled'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,.075);
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}

.badge {
    font-size: 0.85em;
    padding: 0.35em 0.65em;
}
</style>

<?php require 'assets/footer.php'; ?>