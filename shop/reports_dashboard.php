<?php
// Reports Dashboard (reports_dashboard.php)
require_once 'config.php';
require_once 'auth.php';
require_once 'reports.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated() || !$auth->hasPermission('manager')) {
    header('Location: login.php');
    exit();
}

$reportManager = new ReportManager(getDB());

// Get date range from query parameters or default to last 30 days
$endDate = date('Y-m-d');
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : $endDate;

// Get report data
$ticketStats = $reportManager->getTicketStats($_SESSION['location_id'], $startDate, $endDate);
$inventoryUsage = $reportManager->getInventoryUsage($_SESSION['location_id'], $startDate, $endDate);
$technicianStats = $reportManager->getTechnicianPerformance($_SESSION['location_id'], $startDate, $endDate);
$outsourcedStats = $reportManager->getOutsourcedRepairStats($_SESSION['location_id'], $startDate, $endDate);
?>

<?php include 'header.php'; ?>

<div class="container-fluid my-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Reports Dashboard</h2>
        </div>
        <div class="col-auto">
            <form method="GET" class="row g-3">
                <div class="col-auto">
                    <input type="date" class="form-control" name="start_date"
                           value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control" name="end_date"
                           value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Ticket Statistics -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Ticket Statistics</h5>
                </div>
                <div class="card-body">
                    <div id="ticketTypeChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>

        <!-- Technician Performance -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
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
                                    <th>Avg. Time (hrs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($technicianStats as $tech): ?>
                                <tr>
                                    <td><?= htmlspecialchars($tech['username']) ?></td>
                                    <td><?= $tech['total_tickets'] ?></td>
                                    <td><?= $tech['completed_tickets'] ?></td>
                                    <td><?= round($tech['avg_completion_time'], 1) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Inventory Usage -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Used Parts</h5>
                </div>
                <div class="card-body">
                    <div id="inventoryUsageChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>

        <!-- Outsourced Repairs -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Outsourced Repairs</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Total Repairs</th>
                                    <th>Avg. Days</th>
                                    <th>Avg. Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($outsourcedStats as $vendor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($vendor['vendor_name']) ?></td>
                                    <td><?= $vendor['vendor_count'] ?></td>
                                    <td><?= round($vendor['avg_turnaround_days'], 1) ?></td>
                                    <td>$<?= number_format($vendor['avg_cost'], 2) ?></td>
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

<!-- Charts JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
// Prepare data for charts
const ticketData = <?= json_encode($ticketStats) ?>;
const inventoryData = <?= json_encode($inventoryUsage) ?>;

// Ticket Type Chart
const ticketChart = echarts.init(document.getElementById('ticketTypeChart'));
ticketChart.setOption({
    title: {
        text: 'Repairs by Device Type'
    },
    tooltip: {
        trigger: 'item',
        formatter: '{b}: {c} ({d}%)'
    },
    legend: {
        orient: 'vertical',
        left: 'left'
    },
    series: [{
        type: 'pie',
        radius: '50%',
        data: ticketData.map(item => ({
            name: item.device_type,
            value: parseInt(item.type_count)
        })),
        emphasis: {
            itemStyle: {
                shadowBlur: 10,
                shadowOffsetX: 0,
                shadowColor: 'rgba(0, 0, 0, 0.5)'
            }
        }
    }]
});

// Inventory Usage Chart
const inventoryChart = echarts.init(document.getElementById('inventoryUsageChart'));
inventoryChart.setOption({
    title: {
        text: 'Most Used Parts'
    },
    tooltip: {
        trigger: 'axis',
        axisPointer: {
            type: 'shadow'
        }
    },
    xAxis: {
        type: 'category',
        data: inventoryData.slice(0, 10).map(item => item.name),
        axisLabel: {
            rotate: 45
        }
    },
    yAxis: {
        type: 'value'
    },
    series: [{
        data: inventoryData.slice(0, 10).map(item => parseInt(item.total_quantity_used)),
        type: 'bar'
    }]
});

// Handle window resize
window.addEventListener('resize', function() {
    ticketChart.resize();
    inventoryChart.resize();
});
</script>
