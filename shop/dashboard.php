<?php
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit();
}

// Check if user is admin
if (!$auth->hasPermission('admin')) {
    header('Location: tickets.php');
    exit();
}

$db = getDB();

// Get date range
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

try {
    // Get repair statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tickets,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
            SUM(repair_cost) as total_revenue,
            AVG(repair_cost) as avg_repair_cost
        FROM repair_tickets
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get repairs by device type
    $stmt = $db->prepare("
        SELECT 
            device_type,
            COUNT(*) as count,
            SUM(repair_cost) as revenue
        FROM repair_tickets
        WHERE created_at BETWEEN ? AND ?
        GROUP BY device_type
        ORDER BY count DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $device_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get outsourced repair stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_outsourced,
            SUM(cost) as total_outsource_cost,
            AVG(DATEDIFF(COALESCE(returned_date, CURRENT_DATE), sent_date)) as avg_turnaround
        FROM outsourced_repairs
        WHERE sent_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $outsource_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent tickets
    $stmt = $db->prepare("
        SELECT 
            t.*,
            c.first_name,
            c.last_name
        FROM repair_tickets t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.created_at BETWEEN ? AND ?
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $recent_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $error = "Error loading dashboard data";
}
?>

<?php include 'header.php'; ?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Dashboard</h2>
        <form method="GET" class="d-flex gap-2">
            <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Revenue</h6>
                    <h3>£<?= number_format($stats['total_revenue'] ?? 0, 2) ?></h3>
                    <small>Avg. £<?= number_format($stats['avg_repair_cost'] ?? 0, 2) ?> per repair</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Completed Repairs</h6>
                    <h3><?= $stats['completed_tickets'] ?? 0 ?></h3>
                    <small>Out of <?= $stats['total_tickets'] ?? 0 ?> total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-title">In Progress</h6>
                    <h3><?= $stats['in_progress_tickets'] ?? 0 ?></h3>
                    <small><?= $stats['pending_tickets'] ?? 0 ?> pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Outsourced Repairs</h6>
                    <h3><?= $outsource_stats['total_outsourced'] ?? 0 ?></h3>
                    <small>Avg. <?= round($outsource_stats['avg_turnaround'] ?? 0, 1) ?> days turnaround</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Device Type Breakdown -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Repairs by Device Type</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Device Type</th>
                                    <th>Repairs</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($device_stats as $device): ?>
                                <tr>
                                    <td><?= ucfirst(htmlspecialchars($device['device_type'])) ?></td>
                                    <td><?= $device['count'] ?></td>
                                    <td>£<?= number_format($device['revenue'] ?? 0, 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Tickets -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Tickets</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Device</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_tickets as $ticket): ?>
                                <tr>
                                    <td>#<?= $ticket['id'] ?></td>
                                    <td><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></td>
                                    <td><?= htmlspecialchars($ticket['device_type']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $ticket['status'] === 'completed' ? 'success' : 
                                            ($ticket['status'] === 'in_progress' ? 'primary' : 'warning') 
                                        ?>">
                                            <?= ucfirst($ticket['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="ticket_details.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-info">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>