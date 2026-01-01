<?php
ob_start();
session_start();
$page_title = 'Second Hand Items';
require '../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}

// Load permission functions
$permissions_file = __DIR__.'/php/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Check if user has access to Second-Hand system
// Admin and useradmin bypass permission check
$is_admin = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);
$has_secondhand_access = $is_admin || (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-View', $DB));

if (!$has_secondhand_access) {
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

// Check specific permissions
$can_view_financial = $is_admin || (function_exists('canViewFinancialData') && canViewFinancialData($user_id, $DB));
$can_view_customer = $is_admin || (function_exists('canViewCustomerData') && canViewCustomerData($user_id, $DB));
$can_view_documents = $is_admin || (function_exists('canViewDocuments') && canViewDocuments($user_id, $DB));
$can_manage = $is_admin || (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB));
$can_view_all_locations = $is_admin || (function_exists('canViewAllLocations') && canViewAllLocations($user_id, $DB));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Second Hand Items</title>
    <!-- Include necessary CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body>
    <!-- Back to Main Menu Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-arrow-left"></i> Back to Main Menu
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Second Hand Inventory</h1>
        <p>Welcome to the Second Hand Inventory Management System</p>
        
        <!-- Add your content here -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Inventory Overview</h5>
                    </div>
                    <div class="card-body">
                        <p>Manage your second hand inventory items here.</p>
                        
                        <?php if ($can_manage): ?>
                        <button class="btn btn-primary" id="addItemBtn">
                            <i class="fas fa-plus"></i> Add New Item
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include necessary JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    $(document).ready(function(){
        // Add your JavaScript here
        console.log("Second Hand Inventory System Loaded");
    });
    </script>
</body>
</html>