<?php
session_start();
require_once '../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id'])){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check permissions - ONLY use control panel permissions
$permissions_file = __DIR__.'/php/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

$can_view = (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-View', $DB));

if (!$can_view) {
    die("Access denied");
}

$effective_location = $user_details['user_location'] ?? 'cs';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$location_filter = $_GET['location'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'DESC';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Validate sort column
$allowed_sorts = ['trade_in_reference', 'customer_name', 'customer_phone', 'total_value', 'status', 'created_at', 'location'];
if(!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}

// Validate sort direction
$sort_dir = strtoupper($sort_dir) === 'ASC' ? 'ASC' : 'DESC';

// Build query
$where = ['1=1'];
$params = [];

if($status_filter !== 'all') {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

if($location_filter !== 'all') {
    $where[] = "location = ?";
    $params[] = $location_filter;
}

if(!empty($search)) {
    // Search in customer details, trade-in reference, AND item details
    $where[] = "(
        customer_name LIKE ? OR 
        trade_in_reference LIKE ? OR 
        customer_phone LIKE ? OR
        ti.id IN (
            SELECT trade_in_id FROM trade_in_items_details 
            WHERE item_name LIKE ? 
            OR preprinted_code LIKE ? 
            OR tracking_code LIKE ?
        )
    )";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT ti.id) as total
        FROM trade_in_items ti
        WHERE " . implode(' AND ', $where);
$total_count = $DB->query($count_sql, $params)[0]['total'];
$total_pages = ceil($total_count / $per_page);

// Get paginated results
$sql = "SELECT ti.*, 
        u.username as created_by_name,
        COUNT(tid.id) as item_count,
        GROUP_CONCAT(DISTINCT 
            CASE 
                WHEN tid.preprinted_code IS NOT NULL THEN tid.preprinted_code
                WHEN tid.tracking_code IS NOT NULL THEN tid.tracking_code
                ELSE NULL
            END
            ORDER BY tid.id 
            SEPARATOR ', '
        ) as item_codes
        FROM trade_in_items ti
        LEFT JOIN users u ON ti.created_by = u.id
        LEFT JOIN trade_in_items_details tid ON ti.id = tid.trade_in_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY ti.id
        ORDER BY ti.$sort_by $sort_dir
        LIMIT $per_page OFFSET $offset";

$trade_ins = $DB->query($sql, $params);

// Get status counts for badges
$status_counts = $DB->query("
    SELECT status, COUNT(*) as count
    FROM trade_in_items
    GROUP BY status
");
$counts = [];
foreach($status_counts as $row) {
    $counts[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade-In Management - Priceless Computing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-card {
            border-left: 4px solid;
            cursor: pointer;
            transition: all 0.2s;
        }
        .stats-card:hover {
            transform: translateX(5px);
        }
        
        .trade-in-table {
            font-size: 0.95rem;
        }
        .trade-in-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        .trade-in-table th:hover {
            background-color: #e9ecef;
        }
        .trade-in-table tbody tr {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .trade-in-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .sort-icon {
            font-size: 0.8em;
            margin-left: 5px;
        }
        .status-badge {
            padding: 0.25em 0.6em;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-testing { background-color: #0dcaf0; color: #000; }
        .status-accepted { background-color: #198754; color: #fff; }
        .status-rejected { background-color: #dc3545; color: #fff; }
        .status-customer_withdrew { background-color: #6c757d; color: #fff; }
        .status-completed { background-color: #0d6efd; color: #fff; }
        .status-cancelled { background-color: #6c757d; color: #fff; }
        
        .code-badge {
            font-size: 0.8em;
            padding: 0.2em 0.4em;
            margin-right: 3px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-exchange-alt"></i> Trade-In Management</h2>
                <p class="text-muted">View and manage all trade-in transactions</p>
            </div>
            <div class="col-md-6 text-end">
                <a href="trade_in_workflow.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Trade-In
                </a>
                <a href="secondhand.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Second Hand
                </a>
            </div>
        </div>

        <!-- Status Overview -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #ffc107;" onclick="filterByStatus('pending')">
                    <div class="card-body">
                        <h6 class="text-muted">Pending</h6>
                        <h3><?=$counts['pending'] ?? 0?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #0dcaf0;" onclick="filterByStatus('testing')">
                    <div class="card-body">
                        <h6 class="text-muted">Testing</h6>
                        <h3><?=$counts['testing'] ?? 0?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #198754;" onclick="filterByStatus('accepted')">
                    <div class="card-body">
                        <h6 class="text-muted">Accepted</h6>
                        <h3><?=$counts['accepted'] ?? 0?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #dc3545;" onclick="filterByStatus('rejected')">
                    <div class="card-body">
                        <h6 class="text-muted">Rejected</h6>
                        <h3><?=$counts['rejected'] ?? 0?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #0d6efd;" onclick="filterByStatus('completed')">
                    <div class="card-body">
                        <h6 class="text-muted">Completed</h6>
                        <h3><?=$counts['completed'] ?? 0?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #6c757d;" onclick="filterByStatus('all')">
                    <div class="card-body">
                        <h6 class="text-muted">All</h6>
                        <h3><?=array_sum($counts)?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?=$status_filter === 'all' ? 'selected' : ''?>>All Statuses</option>
                            <option value="pending" <?=$status_filter === 'pending' ? 'selected' : ''?>>Pending</option>
                            <option value="testing" <?=$status_filter === 'testing' ? 'selected' : ''?>>Testing</option>
                            <option value="accepted" <?=$status_filter === 'accepted' ? 'selected' : ''?>>Accepted</option>
                            <option value="rejected" <?=$status_filter === 'rejected' ? 'selected' : ''?>>Rejected</option>
                            <option value="customer_withdrew" <?=$status_filter === 'customer_withdrew' ? 'selected' : ''?>>Customer Withdrew</option>
                            <option value="completed" <?=$status_filter === 'completed' ? 'selected' : ''?>>Completed</option>
                            <option value="cancelled" <?=$status_filter === 'cancelled' ? 'selected' : ''?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <select name="location" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?=$location_filter === 'all' ? 'selected' : ''?>>All Locations</option>
                            <option value="cs" <?=$location_filter === 'cs' ? 'selected' : ''?>>Commerce Street</option>
                            <option value="as" <?=$location_filter === 'as' ? 'selected' : ''?>>Argyle Street</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Customer, reference, item, code..." value="<?=htmlspecialchars($search)?>">
                        <small class="form-text text-muted">Search customer name, phone, items, or tracking codes</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Trade-Ins Table -->
        <div class="card">
            <div class="card-body p-0">
                <?php if(empty($trade_ins)): ?>
                    <div class="p-4">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No trade-ins found matching your criteria.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover trade-in-table mb-0">
                            <thead>
                                <tr>
                                    <th onclick="sortBy('trade_in_reference')">
                                        Reference
                                        <?php if($sort_by === 'trade_in_reference'): ?>
                                            <i class="fas fa-sort-<?=$sort_dir === 'ASC' ? 'up' : 'down'?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th onclick="sortBy('customer_name')">
                                        Customer
                                        <?php if($sort_by === 'customer_name'): ?>
                                            <i class="fas fa-sort-<?=$sort_dir === 'ASC' ? 'up' : 'down'?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th onclick="sortBy('customer_phone')">
                                        Phone
                                        <?php if($sort_by === 'customer_phone'): ?>
                                            <i class="fas fa-sort-<?=$sort_dir === 'ASC' ? 'up' : 'down'?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Items / Codes</th>
                                    <th onclick="sortBy('total_value')">
                                        Value
                                        <?php if($sort_by === 'total_value'): ?>
                                            <i class="fas fa-sort-<?=$sort_dir === 'ASC' ? 'up' : 'down'?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th onclick="sortBy('status')">
                                        Status
                                        <?php if($sort_by === 'status'): ?>
                                            <i class="fas fa-sort-<?=$sort_dir === 'ASC' ? 'up' : 'down'?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th onclick="sortBy('location')">
                                        Location
                                        <?php if($sort_by === 'location'): ?>
                                            <i class="fas fa-sort-<?=$sort_dir === 'ASC' ? 'up' : 'down'?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th onclick="sortBy('created_at')">
                                        Created
                                        <?php if($sort_by === 'created_at'): ?>
                                            <i class="fas fa-sort-<?=$sort_dir === 'ASC' ? 'up' : 'down'?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($trade_ins as $ti): ?>
                                <tr onclick="window.location.href='trade_in_detail.php?id=<?=$ti['id']?>'">
                                    <td>
                                        <strong><?=htmlspecialchars($ti['trade_in_reference'])?></strong>
                                    </td>
                                    <td><?=htmlspecialchars($ti['customer_name'])?></td>
                                    <td><?=htmlspecialchars($ti['customer_phone'] ?: '-')?></td>
                                    <td>
                                        <small class="text-muted"><?=$ti['item_count']?> items</small>
                                        <?php if($ti['item_codes']): ?>
                                        <br>
                                        <?php 
                                        $codes = explode(', ', $ti['item_codes']);
                                        $display_codes = array_slice($codes, 0, 3);
                                        foreach($display_codes as $code):
                                            $is_preprinted = strpos($code, 'DSH') !== false;
                                        ?>
                                            <span class="badge code-badge <?=$is_preprinted ? 'bg-warning text-dark' : 'bg-secondary'?>">
                                                <?=htmlspecialchars($code)?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if(count($codes) > 3): ?>
                                            <span class="text-muted small">+<?=(count($codes) - 3)?> more</span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>£<?=number_format($ti['total_value'], 2)?></strong></td>
                                    <td>
                                        <span class="badge status-badge status-<?=$ti['status']?>">
                                            <?=ucfirst(str_replace('_', ' ', $ti['status']))?>
                                        </span>
                                    </td>
                                    <td><?=$ti['location'] === 'cs' ? 'Commerce St' : 'Argyle St'?></td>
                                    <td>
                                        <small>
                                            <?=date('d/m/Y', strtotime($ti['created_at']))?><br>
                                            <span class="text-muted"><?=date('H:i', strtotime($ti['created_at']))?></span>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <div class="card-footer">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    Showing <?=$offset + 1?> to <?=min($offset + $per_page, $total_count)?> of <?=$total_count?> trade-ins
                                </small>
                            </div>
                            <div class="col-md-6">
                                <nav>
                                    <ul class="pagination pagination-sm justify-content-end mb-0">
                                        <?php if($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?=http_build_query(array_merge($_GET, ['page' => $page - 1]))?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        if($start_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?=http_build_query(array_merge($_GET, ['page' => 1]))?>">1</a>
                                            </li>
                                            <?php if($start_page > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?=$i === $page ? 'active' : ''?>">
                                            <a class="page-link" href="?<?=http_build_query(array_merge($_GET, ['page' => $i]))?>"><?=$i?></a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <?php if($end_page < $total_pages): ?>
                                            <?php if($end_page < $total_pages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?=http_build_query(array_merge($_GET, ['page' => $total_pages]))?>"><?=$total_pages?></a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?=http_build_query(array_merge($_GET, ['page' => $page + 1]))?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByStatus(status) {
            window.location.href = '?status=' + status;
        }
        
        function sortBy(column) {
            const params = new URLSearchParams(window.location.search);
            const currentSort = params.get('sort');
            const currentDir = params.get('dir');
            
            // Toggle direction if clicking same column
            if(currentSort === column) {
                params.set('dir', currentDir === 'ASC' ? 'DESC' : 'ASC');
            } else {
                params.set('sort', column);
                params.set('dir', column === 'created_at' ? 'DESC' : 'ASC');
            }
            
            // Reset to page 1 when sorting
            params.set('page', '1');
            
            window.location.href = '?' + params.toString();
        }
    </script>
</body>
</html>
