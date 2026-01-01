<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: ../login.php");
  exit();
}

require __DIR__.'/../php/bootstrap.php';
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Load permission functions
$permissions_file = __DIR__.'/php/rma-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

// Check if user has access to RMA system
// Admin and useradmin bypass permission check
$is_admin = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);
$has_rma_access = $is_admin || (function_exists('hasRMAPermission') && hasRMAPermission($user_id, 'RMA-View', $DB));

if (!$has_rma_access) {
    header("Location: ../no_access.php");
    exit();
}

// Check if user has location assigned
if(empty($user_details['user_location'])){
    die("Error: Your user account does not have a location assigned. Please contact administrator.");
}

// Determine effective location (considering temp location)
$effective_location = $user_details['user_location'];
if(!empty($user_details['temp_location']) && 
   !empty($user_details['temp_location_expires']) && 
   strtotime($user_details['temp_location_expires']) > time()) {
    $effective_location = $user_details['temp_location'];
}

$location_name = ($effective_location == 'cs') ? 'Commerce Street' : 'Argyle Street';

// Set authorization flag (for legacy code compatibility)
$is_authorized = $is_admin;

// Load permission functions if not already loaded
if (!function_exists('canViewSupplierData')) {
    function canViewSupplierData($user_id, $DB) {
        try {
            $result = $DB->query("SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Supplier'", [$user_id]);
            return !empty($result) && $result[0]['has_access'] == 1;
        } catch (Exception $e) {
            return false;
        }
    }
}
if (!function_exists('canViewFinancialData')) {
    function canViewFinancialData($user_id, $DB) {
        try {
            $result = $DB->query("SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Financial'", [$user_id]);
            return !empty($result) && $result[0]['has_access'] == 1;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Check specific permissions
$can_view_supplier = canViewSupplierData($user_id, $DB);
$can_view_financial = canViewFinancialData($user_id, $DB);
$can_manage_rma = function_exists('hasRMAPermission') ? hasRMAPermission($user_id, 'RMA-Manage', $DB) : false;

// Get fault types for dropdown
$fault_types = $DB->query("SELECT id, fault_name FROM rma_fault_types WHERE is_active = 1 ORDER BY 
    CASE fault_name
        WHEN 'DOA' THEN 1
        WHEN 'Dead' THEN 2
        WHEN 'No Power' THEN 3
        WHEN 'Other' THEN 99
        ELSE 50
    END, fault_name");

// Get status counts for dashboard
if($is_authorized) {
    $value_field = $can_view_financial ? 'SUM(cost_at_creation) as total_value' : '0 as total_value';
    $status_counts = $DB->query("
        SELECT 
            status,
            location,
            COUNT(*) as count,
            $value_field
        FROM rma_items
        GROUP BY status, location
    ");
    
    $needs_review_count = $DB->query("SELECT COUNT(*) as count FROM rma_items WHERE needs_review = 1");
    $needs_review = $needs_review_count[0]['count'] ?? 0;
} else {
    $status_counts = $DB->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM rma_items
        WHERE location = ?
        GROUP BY status
    ", [$effective_location]);
    $needs_review = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RMA System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Material Design Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/material-design-iconic-font/2.2.0/css/material-design-iconic-font.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/rma.css?<?=time()?>">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 0;
            margin: 0;
        }
        
        .top-bar {
            background: #2c3e50;
            color: white;
            padding: 15px 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .top-bar a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }
        
        .top-bar a:hover {
            color: #3498db;
        }
        
        .container-fluid {
            padding: 0 30px;
            max-width: 100%;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .location-badge {
            background: #4CAF50;
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .gap-2 > * {
            margin-right: 8px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<div class="top-bar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Main Menu</a>
        </div>
        <div>
            <strong><?=htmlspecialchars($user_details['first_name'] ?? 'User')?> <?=htmlspecialchars($user_details['last_name'] ?? '')?></strong>
            <span class="ml-3">|</span>
            <a href="../logout.php" class="ml-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="page-title">
                RMA System
                <span class="location-badge"><?=$location_name?></span>
            </h1>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php if($is_authorized && $needs_review > 0): ?>
                <div class="stat-card" style="background: #ff9800; color: white; padding: 15px; border-radius: 8px; cursor: pointer;" onclick="filterByNeedsReview()">
                    <div style="font-size: 2em; font-weight: bold;"><?=$needs_review?></div>
                    <div>Needs Review</div>
                </div>
                <?php endif; ?>
                
                <?php 
                // Group counts by status
                $status_summary = [];
                foreach($status_counts as $row) {
                    $status = $row['status'];
                    if(!isset($status_summary[$status])) {
                        $status_summary[$status] = ['count' => 0, 'value' => 0];
                    }
                    $status_summary[$status]['count'] += $row['count'];
                    if($is_authorized && isset($row['total_value'])) {
                        $status_summary[$status]['value'] += $row['total_value'];
                    }
                }
                
                $status_colors = [
                    'unprocessed' => '#f44336',
                    'rma_number_issued' => '#ff9800',
                    'applied_for' => '#2196F3',
                    'sent' => '#9C27B0',
                    'credited' => '#4CAF50',
                    'exchanged' => '#4CAF50',
                    'rejected' => '#607D8B'
                ];
                
                $status_labels = [
                    'unprocessed' => 'Unprocessed',
                    'rma_number_issued' => 'RMA Issued',
                    'applied_for' => 'Applied For',
                    'sent' => 'Sent',
                    'credited' => 'Credited',
                    'exchanged' => 'Exchanged',
                    'rejected' => 'Rejected'
                ];
                
                foreach($status_summary as $status => $data):
                    $color = $status_colors[$status] ?? '#607D8B';
                    $label = $status_labels[$status] ?? ucfirst($status);
                ?>
                <div class="stat-card" style="background: <?=$color?>; color: white; padding: 15px; border-radius: 8px; cursor: pointer; transition: transform 0.2s;" onclick="filterByStatus('<?=$status?>')">
                    <div style="font-size: 2em; font-weight: bold;"><?=$data['count']?></div>
                    <div><?=$label?></div>
                    <?php if($can_view_financial && $data['value'] > 0): ?>
                    <div style="font-size: 0.9em; margin-top: 5px;">£<?=number_format($data['value'], 2)?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tool Section -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3" style="gap: 10px;">
                        <button class="btn btn-success btn-sm" id="quickEntryBtn">
                            <i class="zmdi zmdi-plus"></i> Quick Entry
                        </button>
                        <?php if($is_authorized): ?>
                        <a href="/rma/batches.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-box-open"></i> Supplier Batches
                        </a>
                        <?php endif; ?>
                        <input type="text" class="form-control form-control-sm" id="searchQuery" 
                               placeholder="Search by Barcode, Tracking #, Serial, SKU, Product Name" style="flex: 1; min-width: 300px;">
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                        <div>
                            <?php if($is_authorized && $needs_review > 0): ?>
                            <button class="btn btn-warning btn-sm" id="reviewItemsBtn">
                                <i class="fas fa-exclamation-triangle"></i> Review Items (<?=$needs_review?>)
                            </button>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex" style="gap: 10px;">
                            <select id="filterLocation" class="form-control form-control-sm" <?=(!$is_authorized) ? 'disabled' : ''?>>
                                <?php if($is_authorized): ?>
                                <option value="all">All Locations</option>
                                <?php endif; ?>
                                <option value="cs" <?=($effective_location == 'cs' && !$is_authorized) ? 'selected' : ''?>>Commerce Street</option>
                                <option value="as" <?=($effective_location == 'as' && !$is_authorized) ? 'selected' : ''?>>Argyle Street</option>
                            </select>
                            
                            <select id="filterStatus" class="form-control form-control-sm">
                                <option value="all">All Status</option>
                                <option value="unprocessed">Unprocessed</option>
                                <option value="rma_number_issued">RMA Issued</option>
                                <option value="applied_for">Applied For</option>
                                <option value="sent">Sent</option>
                                <option value="credited">Credited</option>
                                <option value="exchanged">Exchanged</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover table-sm">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 80px;">Actions</th>
                                    <th>Barcode</th>
                                    <th>Tracking #</th>
                                    <th>Serial #</th>
                                    <th>SKU</th>
                                    <th>Product</th>
                                    <th>Fault</th>
                                    <?php if($can_view_supplier): ?>
                                    <th>Supplier</th>
                                    <?php endif; ?>
                                    <?php if($can_view_financial): ?>
                                    <th>Cost</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th>Discovered</th>
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody id="rmaRecords">
                                <tr>
                                    <td colspan="<?php 
                                        $col_count = 11; // Base columns
                                        if($can_view_supplier) $col_count++;
                                        if($can_view_financial) $col_count++;
                                        echo $col_count;
                                    ?>" class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin"></i> Loading RMAs...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (must load before modals for inline scripts) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<!-- Include Modals -->
<?php 
include 'php/modals/quick-entry-modal.php';
include 'php/modals/view-rma-modal.php';
if($is_authorized) {
    include 'php/modals/review-items-modal.php';
}
?>

<!-- Hidden form values -->
<input type="hidden" id="limit" value="50">
<input type="hidden" id="offset" value="0">
<input type="hidden" id="userLocation" value="<?=$effective_location?>">
<input type="hidden" id="isAuthorized" value="<?=$is_authorized ? '1' : '0'?>">
<input type="hidden" id="canViewSupplier" value="<?=$can_view_supplier ? '1' : '0'?>">
<input type="hidden" id="canViewFinancial" value="<?=$can_view_financial ? '1' : '0'?>">>

<!-- RMA JS -->
<script src="assets/js/rma.js?<?=time()?>"></script>

</body>
</html>