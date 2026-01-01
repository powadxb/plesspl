<?php
/**
 * RMA PHASE 2: Supplier Batch Management
 * List and manage supplier RMA batches
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: ../login.php");
  exit();
}

require __DIR__.'/../php/bootstrap.php';
require __DIR__.'/php/rma-permissions.php';

$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Get RMA permissions for this user
$rma_permissions = getRMAPermissions($user_id, $DB);

// Check if user has batch management access
if (!$rma_permissions['batches']) {
    header("Location: ../no_access.php");
    exit();
}

// Set permission flags for use in page and JavaScript
$can_view_financials = $rma_permissions['view_financial']; // NEW: Separate financial permission
$can_edit_completed = $rma_permissions['batch_admin'];

$page_title = 'RMA Supplier Batches';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Priceless Computing</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <!-- Custom RMA CSS -->
    <link rel="stylesheet" href="assets/css/rma.css?v=<?=time()?>">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
        }
        
        .batch-draft { background-color: #f8f9fa; }
        .batch-submitted { background-color: #cfe2ff; }
        .batch-shipped { background-color: #fff3cd; }
        .batch-completed { background-color: #d1e7dd; }
        .batch-cancelled { background-color: #f8d7da; }
        
        .stat-card {
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .stat-card.draft { border-left-color: #6c757d; }
        .stat-card.submitted { border-left-color: #0d6efd; }
        .stat-card.shipped { border-left-color: #ffc107; }
        .stat-card.completed { border-left-color: #198754; }
        
        .batch-value {
            font-size: 1.1em;
            font-weight: bold;
            color: #0d6efd;
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
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <div class="top-bar">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="/rma/"><i class="fas fa-arrow-left"></i> Back to RMA</a>
            </div>
            <div>
                <strong><?=htmlspecialchars($user_details['first_name'] ?? 'User')?> <?=htmlspecialchars($user_details['last_name'] ?? '')?></strong>
                <span class="ml-3">|</span>
                <a href="../logout.php" class="ml-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="fas fa-box-open"></i> Supplier RMA Batches</h1>
                <p class="text-muted mb-0">Group and track items sent to suppliers</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" id="btnCreateBatch">
                    <i class="fas fa-plus"></i> Create New Batch
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card draft">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Draft Batches</h6>
                        <h3 class="mb-0" id="stat-draft">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card submitted">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Submitted</h6>
                        <h3 class="mb-0" id="stat-submitted">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shipped">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Shipped</h6>
                        <h3 class="mb-0" id="stat-shipped">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card completed">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Completed</h6>
                        <h3 class="mb-0" id="stat-completed">-</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" id="filterStatus">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="shipped">Shipped</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Supplier</label>
                        <select class="form-control" id="filterSupplier">
                            <option value="">All Suppliers</option>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" id="filterSearch" 
                               placeholder="RMA#, tracking, notes...">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-secondary w-100" id="btnResetFilters">
                            <i class="fas fa-redo"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batches Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="batchesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Supplier</th>
                                <th>RMA Number</th>
                                <th>Items</th>
                                <th>Value</th>
                                <th>Created</th>
                                <th>Submitted</th>
                                <th>Shipped</th>
                                <th>Age</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Hidden values for JavaScript -->
    <input type="hidden" id="canEditCompleted" value="<?=$can_edit_completed ? '1' : '0'?>">

    <!-- Modals -->
    <?php include 'php/modals/create-batch-modal.php'; ?>
    <?php include 'php/modals/view-batch-modal.php'; ?>
    <?php include 'php/modals/view-rma-modal.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/batches.js?v=<?=time()?>"></script>
</body>
</html>