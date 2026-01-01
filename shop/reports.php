<?php
// reports.php
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit();
}

// Get date range from query parameters or default to last 30 days
$end_date = date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

try {
    $db = getDB();
    
    // Repair Volume by Device Type
    $device_stats = $db->prepare("
        SELECT 
            device_type,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM repair_tickets
        WHERE created_at BETWEEN ? AND ?
        GROUP BY device_type
    ");
    $device_stats->execute([$start_date, $end_date]);
    $device_data = $device_stats->fetchAll(PDO::FETCH_ASSOC);

    // Technician Performance
    $tech_stats = $db->prepare("
        SELECT 
            u.username,
            COUNT(t.id) as total_tickets,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tickets,
            AVG(TIMESTAMPDIFF(HOUR, t.created_at, 
                CASE WHEN t.status = 'completed' 
                    THEN t.updated_at 
                    ELSE CURRENT_TIMESTAMP 
                END)) as avg_completion_time
        FROM users u
        LEFT JOIN repair_tickets t ON u.id = t.assigned_to
        WHERE u.role = 'technician'
        AND t.created_at BETWEEN ? AND ?
        GROUP BY u.id
    ");
    $tech_stats->execute([$start_date, $end_date]);
    $tech_data = $tech_stats->fetchAll(PDO::FETCH_ASSOC);

    // Revenue Analysis
    $revenue_stats = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_repairs,
            SUM(cost) as total_revenue,
            AVG(cost) as avg_repair_cost
        FROM repair_tickets
        WHERE status = 'completed'
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $revenue_stats->execute([$start_date, $end_date]);
    $revenue_data = $revenue_stats->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Reports query error: " . $e->getMessage());
    $error = "Error loading report data";
}
?>

<?php include 'header.php'; ?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Reports</h2>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="date" class="form-control" name="start_date" 
                       value="<?= htmlspecialchars($start_date) ?>">
                <input type="date" class="form-control" name="end_date" 
                       value="<?= htmlspecialchars($end_date) ?>">
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
            <button onclick="window.print()" class="btn btn-secondary">Print Report</button>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Device Type Analysis -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Repair Volume by Device Type</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Device Type</th>
                            <th>Total Repairs</th>
                            <th>Completed</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($device_data as $device): ?>
                        <tr>
                            <td><?= ucfirst(htmlspecialchars($device['device_type'])) ?></td>
                            <td><?= $device['total'] ?></td>
                            <td><?= $device['completed'] ?></td>
                            <td>
                                <?= round(($device['completed'] / $device['total']) * 100, 1) ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Technician Performance -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Technician Performance</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Technician</th>
                            <th>Total Tickets</th>
                            <th>Completed</th>
                            <th>Completion Rate</th>
                            <th>Avg Time (hours)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tech_data as $tech): ?>
                        <tr>
                            <td><?= htmlspecialchars($tech['username']) ?></td>
                            <td><?= $tech['total_tickets'] ?></td>
                            <td><?= $tech['completed_tickets'] ?></td>
                            <td>
                                <?= round(($tech['completed_tickets'] / $tech['total_tickets']) * 100, 1) ?>%
                            </td>
                            <td><?= round($tech['avg_completion_time'], 1) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Revenue Analysis -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Revenue Analysis</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Repairs</th>
                            <th>Total Revenue</th>
                            <th>Average Repair Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($revenue_data as $revenue): ?>
                        <tr>
                            <td><?= date('F Y', strtotime($revenue['month'] . '-01')) ?></td>
                            <td><?= $revenue['total_repairs'] ?></td>
                            <td>$<?= number_format($revenue['total_revenue'], 2) ?></td>
                            <td>$<?= number_format($revenue['avg_repair_cost'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Export Buttons -->
    <div class="text-end mb-4">
        <div class="btn-group">
            <a href="export_report.php?format=csv&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
               class="btn btn-outline-primary">
                Export to CSV
            </a>
            <a href="export_report.php?format=pdf&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
               class="btn btn-outline-primary">
                Export to PDF
            </a>
        </div>
    </div>
</div>

<!-- Add Chart.js for visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Add any additional JavaScript for dynamic charts if needed
</script>

<?php include 'footer.php'; ?>