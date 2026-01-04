<?php
session_start();
require '../php/bootstrap.php';

// Simple authentication check
if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check permissions directly from database
$secondhand_view_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View'",
    [$user_id]
);
$has_secondhand_access = !empty($secondhand_view_check) && $secondhand_view_check[0]['has_access'];

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

// Check specific permissions from database
$financial_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Financial'",
    [$user_id]
);
$can_view_financial = !empty($financial_check) && $financial_check[0]['has_access'];

$customer_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Customer Data'",
    [$user_id]
);
$can_view_seller = !empty($customer_check) && $customer_check[0]['has_access']; // Changed from can_view_customer to can_view_seller

$documents_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Documents'",
    [$user_id]
);
$can_view_documents = !empty($documents_check) && $documents_check[0]['has_access'];

$manage_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'",
    [$user_id]
);
$can_manage = !empty($manage_check) && $manage_check[0]['has_access'];

$all_locations_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View All Locations'",
    [$user_id]
);
$can_view_all_locations = !empty($all_locations_check) && $all_locations_check[0]['has_access'];

// Get all categories for the form
$categories = $DB->query("SELECT DISTINCT pos_category FROM master_categories WHERE pos_category IS NOT NULL AND pos_category != '' ORDER BY pos_category ASC");
?>
<script>
// Permission flags - available globally in JavaScript
const can_view_seller = <?php echo $can_view_seller ? 'true' : 'false'; ?>;
const can_view_financial = <?php echo $can_view_financial ? 'true' : 'false'; ?>;
const can_view_documents = <?php echo $can_view_documents ? 'true' : 'false'; ?>;
const can_manage = <?php echo $can_manage ? 'true' : 'false'; ?>;
const user_location = '<?php echo $effective_location; ?>';
</script>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Second Hand Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }
        /* Excel-like table styling */
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-wrapper {
            max-height: calc(100vh - 280px);
            overflow-y: auto;
            overflow-x: auto;
        }
        #itemsTable {
            font-size: 0.85rem;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        #itemsTable thead th {
            background-color: #4a5568;
            color: white;
            border: 1px solid #2d3748;
            padding: 10px 12px;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
            font-size: 0.8rem;
            text-align: left;
        }
        #itemsTable tbody td {
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            background: white;
        }
        #itemsTable tbody tr {
            cursor: pointer;
            transition: background-color 0.15s;
        }
        #itemsTable tbody tr:hover {
            background-color: #ebf8ff;
        }
        #itemsTable tbody tr:nth-child(even) {
            background-color: #f7fafc;
        }
        #itemsTable tbody tr:nth-child(even):hover {
            background-color: #ebf8ff;
        }
        .search-filter-bar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-in_stock {
            background-color: #c6f6d5;
            color: #22543d;
        }
        .status-sold {
            background-color: #fed7d7;
            color: #742a2a;
        }
        .status-reserved {
            background-color: #feebc8;
            color: #7c2d12;
        }
        .stats-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-item {
            display: inline-block;
            margin-right: 30px;
        }
        .stat-label {
            font-size: 0.75rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .search-input {
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .search-input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .filter-checkbox {
            cursor: pointer;
            user-select: none;
            padding: 8px 15px;
            border-radius: 6px;
            border: 2px solid #e2e8f0;
            transition: all 0.2s;
            background: white;
        }
        .filter-checkbox:hover {
            border-color: #cbd5e0;
        }
        .filter-checkbox input[type="checkbox"] {
            margin-right: 8px;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .header-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        /* Compact form styling */
        .compact-form .row {
            row-gap: 0.75rem;
        }
        .compact-form label {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #4a5568;
        }
        .compact-form .form-control, .compact-form .form-select {
            font-size: 0.9rem;
            padding: 0.5rem 0.75rem;
        }
        .section-divider {
            border-top: 2px solid #e2e8f0;
            margin: 1.5rem 0;
            padding-top: 1rem;
        }
        .permission-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge-restricted {
            background-color: #fed7d7;
            color: #742a2a;
        }
        .view-only-notice {
            background: #e6f2ff;
            border-left: 4px solid #3182ce;
            padding: 12px;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        /* Pricing Highlight Box */
        .pricing-highlight-box {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            border: 3px solid #fdcb6e;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(253, 203, 110, 0.3);
        }
        .pricing-highlight-box label {
            color: #2d3436;
            font-weight: 700;
        }
        .pricing-highlight-box .form-control {
            border: 2px solid #fff;
            background: #fff;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .pricing-highlight-box .form-control:focus {
            border-color: #e17055;
            box-shadow: 0 0 0 0.2rem rgba(253, 203, 110, 0.25);
        }
        .pricing-highlight-box small {
            color: #2d3436;
            font-weight: 500;
        }
        /* Tracking Code Section */
        .tracking-code-section {
            background: linear-gradient(135deg, #fff5e6 0%, #ffe6cc 100%);
            padding: 20px;
            border-radius: 8px;
            border: 3px solid #ff9800;
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.2);
        }
        .tracking-code-section h6 {
            color: #e65100;
            font-weight: 700;
        }
        .preprinted-highlight {
            border: 3px solid #ff9800 !important;
            background-color: #fff8e1;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .preprinted-highlight:focus {
            border-color: #f57c00 !important;
            box-shadow: 0 0 0 0.25rem rgba(255, 152, 0, 0.25) !important;
        }
        
        /* Required field highlighting */
        .required-field {
            border-left: 3px solid #667eea;
        }
        #sourceDetailsRow {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }
        
        /* Wizard Progress Indicator */
        .wizard-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0 -10px;
            padding: 0;
        }
        .wizard-step {
            flex: 1;
            text-align: center;
            position: relative;
            opacity: 0.5;
        }
        .wizard-step.active {
            opacity: 1;
        }
        .wizard-step.completed {
            opacity: 0.8;
        }
        .wizard-step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #4a5568;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 700;
            transition: all 0.3s;
        }
        .wizard-step.active .wizard-step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.1);
        }
        .wizard-step.completed .wizard-step-number {
            background: #48bb78;
            color: white;
        }
        .wizard-step-label {
            font-size: 0.75rem;
            color: #718096;
            font-weight: 500;
        }
        .wizard-step.active .wizard-step-label {
            color: #667eea;
            font-weight: 600;
        }
        
        /* Stage Headers */
        .stage-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .stage-header h6 {
            color: #2d3748;
            font-weight: 700;
            margin: 0;
        }
        
        /* Wizard Stages */
        .wizard-stage {
            min-height: 300px;
        }
        
        /* Photo Upload Section */
        .photo-upload-section {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
        }
        .camera-section, .file-upload-section {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 2px dashed #cbd5e0;
        }
        .camera-preview {
            background: #000;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
            max-width: 100%;
        }
        .camera-preview video {
            width: 100%;
            height: auto;
            display: block;
        }
        .camera-controls {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        /* Photo Grid */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .photo-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            border: 2px solid #e2e8f0;
            aspect-ratio: 1;
        }
        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-item-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.2s;
        }
        .photo-item-remove:hover {
            background: #c53030;
            transform: scale(1.1);
        }
        
        /* Pricing Box */
        .pricing-box {
            background: #f7fafc;
            padding: 15px;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }
        
        /* Wizard Navigation */
        .wizard-navigation {
            display: flex;
            gap: 10px;
        }
        
        /* Wide SweetAlert for tracking code display */
        .swal-wide {
            width: 600px !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-arrow-left me-2"></i> Back to Main Menu
            </a>
            <span class="navbar-text text-white">
                <i class="fas fa-map-marker-alt me-2"></i><?=$location_name?>
            </span>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header Section -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-box me-2"></i>Second Hand Inventory</h2>
                    <p class="text-muted mb-0">Manage your pre-owned stock - Click any item to view<?= $can_manage ? '/edit' : '' ?></p>
                </div>
                <div>
                    <a href="trade_in_management.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-exchange-alt me-1"></i> Trade-Ins
                    </a>
                    <?php if ($can_manage): ?>
                    <button class="btn btn-primary" id="addItemBtn">
                        <i class="fas fa-plus me-1"></i> Add Item
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Bar -->
        <div class="stats-bar" id="statsBar">
            <div class="stat-item">
                <div class="stat-label">In Stock</div>
                <div class="stat-value" id="inStockCount">-</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Sold</div>
                <div class="stat-value" id="soldCount">-</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Total Value</div>
                <div class="stat-value" id="totalValue">-</div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="search-filter-bar">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="searchInput" class="form-label fw-bold">
                        <i class="fas fa-search me-1"></i> Search
                    </label>
                    <input 
                        type="text" 
                        class="form-control search-input" 
                        id="searchInput" 
                        placeholder="Search by name, serial number, brand, model, category, tracking code..."
                    >
                    <small class="text-muted">Searches in any order across multiple fields</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">
                        <i class="fas fa-filter me-1"></i> Filter Options
                    </label>
                    <div class="d-flex gap-3">
                        <label class="filter-checkbox">
                            <input type="checkbox" id="showOutOfStock"> Show Out of Stock Items
                        </label>
                        <?php if ($can_view_all_locations): ?>
                        <label class="filter-checkbox">
                            <input type="checkbox" id="allLocationsToggle" checked> All Locations
                        </label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-container">
            <div class="table-wrapper">
                <table class="table table-sm" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Tracking</th>
                            <th>Item Name</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Serial #</th>
                            <th>Category</th>
                            <th>Condition</th>
                            <th>Source</th>
                            <th>Selling £</th>
                            <th>Status</th>
                            <?php if ($can_view_financial): ?>
                            <th>Purchase £</th>
                            <th>Est. Sale £</th>
                            <?php endif; ?>
                            <th>Location</th>
                            <th>Acquired</th>
                        </tr>
                    </thead>
                    <tbody id="itemsList">
                        <tr>
                            <td colspan="<?= 15 + ($can_view_financial ? 2 : 0) ?>" class="text-center">
                                <i class="fas fa-spinner fa-spin me-2"></i> Loading items...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Item Details/Edit Modal - Multi-Stage Wizard -->
    <div class="modal fade" id="itemModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="w-100">
                        <h5 class="modal-title" id="itemModalLabel">Add New Item</h5>
                        <!-- Progress Indicator -->
                        <div class="wizard-progress mt-3" id="wizardProgress">
                            <div class="wizard-step active" data-step="1">
                                <div class="wizard-step-number">1</div>
                                <div class="wizard-step-label">Essential Info</div>
                            </div>
                            <div class="wizard-step" data-step="2">
                                <div class="wizard-step-number">2</div>
                                <div class="wizard-step-label">Product Details</div>
                            </div>
                            <div class="wizard-step" data-step="3">
                                <div class="wizard-step-number">3</div>
                                <div class="wizard-step-label">Pricing</div>
                            </div>
                            <div class="wizard-step" data-step="4">
                                <div class="wizard-step-number">4</div>
                                <div class="wizard-step-label">Photos</div>
                            </div>
                            <div class="wizard-step" data-step="5">
                                <div class="wizard-step-number">5</div>
                                <div class="wizard-step-label">Additional</div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$can_manage): ?>
                    <div class="view-only-notice">
                        <i class="fas fa-eye me-2"></i>
                        <strong>View Only:</strong> You do not have permission to edit items.
                    </div>
                    <?php endif; ?>
                    
                    <form id="itemForm" class="compact-form">
                        <input type="hidden" id="itemId" name="id">
                        
                        <!-- STAGE 1: Essential Information (Required Fields) -->
                        <div class="wizard-stage" id="stage1" style="display: block;">
                            <div class="stage-header">
                                <h6><i class="fas fa-exclamation-circle me-2"></i>Required Information</h6>
                                <p class="text-muted mb-3">All fields on this page are required</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Item Name *</label>
                                    <input type="text" class="form-control" id="itemName" name="item_name" required <?= !$can_manage ? 'readonly' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" id="itemCategory" name="category" <?= !$can_manage ? 'disabled' : '' ?>>
                                        <option value="">Select Category</option>
                                        <?php foreach($categories as $category): ?>
                                        <option value="<?=htmlspecialchars($category['pos_category'])?>"><?=htmlspecialchars($category['pos_category'])?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">Source *</label>
                                    <select class="form-select" id="itemSource" name="item_source" required <?= !$can_manage ? 'disabled' : '' ?>>
                                        <option value="">Select Source</option>
                                        <option value="trade_in">Trade-In</option>
                                        <option value="donation">Donation</option>
                                        <option value="abandoned">Customer Abandoned Item</option>
                                        <option value="purchase">Purchase</option>
                                        <option value="ebay">eBay</option>
                                        <option value="parts_dismantle">Parts/Salvage</option>
                                        <option value="stock_transfer">Stock Transfer</option>
                                        <option value="returned_item">Returned Item</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Condition *</label>
                                    <select class="form-select" id="itemCondition" name="condition" required <?= !$can_manage ? 'disabled' : '' ?>>
                                        <option value="excellent">Excellent</option>
                                        <option value="good">Good</option>
                                        <option value="fair">Fair</option>
                                        <option value="poor">Poor</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Source Details (Dynamic) -->
                            <div class="row mt-3" id="sourceDetailsRow" style="display: none;">
                                <div class="col-12">
                                    <label class="form-label" id="sourceDetailsLabel">Source Details *</label>
                                    <input type="text" class="form-control" id="sourceDetails" name="supplier_info" placeholder="" <?= !$can_manage ? 'readonly' : '' ?>>
                                    <small class="text-muted" id="sourceDetailsHint"></small>
                                </div>
                            </div>
                            
                            <?php if ($can_view_financial): ?>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">Purchase Price (£) *</label>
                                    <input type="number" class="form-control" id="purchasePrice" name="purchase_price" step="0.01" min="0" placeholder="Enter amount paid" required <?= !$can_manage ? 'readonly' : '' ?>>
                                    <small class="text-muted">How much was paid for this item (enter 0 if free)</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Location *</label>
                                    <select class="form-select" id="itemLocation" name="location" required <?= !$can_manage ? 'disabled' : '' ?>>
                                        <option value="cs">Commerce Street</option>
                                        <option value="as">Argyle Street</option>
                                    </select>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <label class="form-label">Location *</label>
                                    <select class="form-select" id="itemLocation" name="location" required <?= !$can_manage ? 'disabled' : '' ?>>
                                        <option value="cs">Commerce Street</option>
                                        <option value="as">Argyle Street</option>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Tracking Codes - Highlighted and Required -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="tracking-code-section">
                                        <h6 class="mb-3"><i class="fas fa-barcode me-2"></i>Item Tracking Code *</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Pre-printed Barcode (DSH) - SCAN OR ENTER</label>
                                                <input type="text" class="form-control form-control-lg preprinted-highlight" id="preprintedCode" name="preprinted_code" placeholder="DSH1, DSH2, DSH3..." <?= !$can_manage ? 'readonly' : '' ?>>
                                                <small class="text-muted"><strong>Scan the pre-printed barcode or type it manually</strong></small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Generated Tracking Code (SH)</label>
                                                <input type="text" class="form-control form-control-lg" id="trackingCode" name="tracking_code" placeholder="Will be generated" readonly>
                                                <small class="text-muted">Auto-generated if no pre-printed code used</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- STAGE 2: Product Details -->
                        <div class="wizard-stage" id="stage2" style="display: none;">
                            <div class="stage-header">
                                <h6><i class="fas fa-box me-2"></i>Product Details</h6>
                                <p class="text-muted mb-3">Physical characteristics and identifiers</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="itemBrand" name="brand" <?= !$can_manage ? 'readonly' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Model</label>
                                    <input type="text" class="form-control" id="itemModel" name="model_number" <?= !$can_manage ? 'readonly' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" id="itemSerial" name="serial_number" <?= !$can_manage ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">Acquisition Date</label>
                                    <input type="date" class="form-control" id="acquisitionDate" name="acquisition_date" value="<?=date('Y-m-d')?>" <?= !$can_manage ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <label class="form-label">Condition Notes</label>
                                    <textarea class="form-control" id="detailedCondition" name="detailed_condition" rows="3" placeholder="Describe the item's condition, any defects, wear, or notable features..." <?= !$can_manage ? 'readonly' : '' ?>></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- STAGE 3: Pricing -->
                        <div class="wizard-stage" id="stage3" style="display: none;">
                            <div class="stage-header">
                                <h6><i class="fas fa-pound-sign me-2"></i>Pricing Information</h6>
                                <p class="text-muted mb-3">Set prices for this item</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="pricing-box">
                                        <label class="form-label"><strong>Selling Price (£) *</strong></label>
                                        <input type="number" class="form-control form-control-lg" id="sellingPrice" name="selling_price" step="0.01" min="0" placeholder="0.00" required <?= !$can_manage ? 'readonly' : '' ?>>
                                        <small class="text-muted">The asking price shown to customers</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="pricing-box">
                                        <label class="form-label"><strong>Lowest Price (£)</strong></label>
                                        <input type="number" class="form-control form-control-lg" id="lowestPrice" name="lowest_price" step="0.01" min="0" placeholder="0.00" <?= !$can_manage ? 'readonly' : '' ?>>
                                        <small class="text-muted">Minimum price staff can negotiate to</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- STAGE 4: Photos -->
                        <div class="wizard-stage" id="stage4" style="display: none;">
                            <div class="stage-header">
                                <h6><i class="fas fa-camera me-2"></i>Item Photos</h6>
                                <p class="text-muted mb-3">Add photos of the item (optional)</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="photo-upload-section">
                                        <!-- Camera Capture -->
                                        <div class="camera-section mb-4">
                                            <button type="button" class="btn btn-primary btn-lg w-100" id="openCameraBtn">
                                                <i class="fas fa-camera me-2"></i> Take Photo with Camera
                                            </button>
                                            
                                            <div id="cameraContainer" style="display: none;" class="mt-3">
                                                <div class="camera-preview">
                                                    <video id="cameraVideo" autoplay playsinline></video>
                                                    <canvas id="cameraCanvas" style="display: none;"></canvas>
                                                </div>
                                                <div class="camera-controls mt-2">
                                                    <button type="button" class="btn btn-success me-2" id="capturePhotoBtn">
                                                        <i class="fas fa-circle me-1"></i> Capture
                                                    </button>
                                                    <button type="button" class="btn btn-secondary" id="closeCameraBtn">
                                                        <i class="fas fa-times me-1"></i> Close Camera
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- File Upload -->
                                        <div class="file-upload-section mb-4">
                                            <label class="btn btn-outline-primary btn-lg w-100">
                                                <i class="fas fa-upload me-2"></i> Upload Photos from Device
                                                <input type="file" id="photoFileInput" accept="image/*" multiple style="display: none;">
                                            </label>
                                        </div>
                                        
                                        <!-- Photo Preview Grid -->
                                        <div id="photoPreviewGrid" class="photo-grid">
                                            <!-- Photos will be displayed here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- STAGE 5: Additional Information -->
                        <div class="wizard-stage" id="stage5" style="display: none;">
                            <div class="stage-header">
                                <h6><i class="fas fa-info-circle me-2"></i>Additional Information</h6>
                                <p class="text-muted mb-3">Optional notes and details</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">General Notes</label>
                                    <textarea class="form-control" id="itemNotes" name="notes" rows="4" placeholder="Any additional information about this item..." <?= !$can_manage ? 'readonly' : '' ?>></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Warranty Information</label>
                                    <textarea class="form-control" id="warrantyInfo" name="warranty_info" rows="4" placeholder="Warranty details, duration, coverage..." <?= !$can_manage ? 'readonly' : '' ?>></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <div class="wizard-navigation">
                        <button type="button" class="btn btn-outline-primary" id="prevStageBtn" style="display: none;">
                            <i class="fas fa-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextStageBtn">
                            Next <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                        <?php if ($can_manage): ?>
                        <button type="button" class="btn btn-success" id="saveBtn" style="display: none;">
                            <i class="fas fa-save me-1"></i> Save Item
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        let allItems = [];
        let filteredItems = [];
        let itemModal;
        let isEditMode = false;
        let originalFormData = {};
        const canManage = <?= $can_manage ? 'true' : 'false' ?>;

        // Initialize modal
        itemModal = new bootstrap.Modal(document.getElementById('itemModal'));

        // Load items on page load
        loadItems();

        // Search input with debounce
        let searchTimeout;
        $('#searchInput').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                filterAndDisplayItems();
            }, 300);
        });

        // Filter checkboxes
        $('#showOutOfStock, #allLocationsToggle').on('change', function() {
            loadItems(); // Reload items when location filter changes
        });

        // Add item button
        $('#addItemBtn').on('click', function() {
            openItemModal(null, true); // null item, addMode = true
        });

        // Edit button
        $('#editBtn').on('click', function() {
            toggleEditMode(true);
        });

        // Save button
        $('#saveBtn').on('click', function() {
            saveItem();
        });

        // Cancel button
        $('#cancelBtn').on('click', function() {
            cancelEdit();
        });

        // Calculate profit when financial fields change
        $('#purchasePrice, #estimatedSalePrice').on('input', function() {
            calculateProfit();
        });
        
        // Handle source selection change to show/hide source details
        $('#itemSource').on('change', function() {
            updateSourceDetails($(this).val());
        });

        function updateSourceDetails(source) {
            const sourceDetailsRow = $('#sourceDetailsRow');
            const sourceDetailsLabel = $('#sourceDetailsLabel');
            const sourceDetailsInput = $('#sourceDetails');
            const sourceDetailsHint = $('#sourceDetailsHint');
            
            // Configuration for each source type
            const sourceConfig = {
                'ebay': {
                    label: 'eBay Item Number *',
                    placeholder: 'e.g., 123456789012',
                    hint: 'Enter the eBay item/listing number',
                    required: true
                },
                'purchase': {
                    label: 'Supplier Name *',
                    placeholder: 'e.g., Supplier Ltd',
                    hint: 'Enter the name of the supplier',
                    required: true
                },
                'trade_in': {
                    label: 'Trade-In Reference',
                    placeholder: 'Auto-filled from trade-in',
                    hint: 'This should be filled automatically from trade-in workflow',
                    required: false
                },
                'donation': {
                    label: 'Donor Reference',
                    placeholder: 'Reference or details',
                    hint: 'Optional reference for the donation',
                    required: false
                },
                'abandoned': {
                    label: 'Original Customer Reference',
                    placeholder: 'Reference or details',
                    hint: 'Optional reference for abandoned item',
                    required: false
                },
                'stock_transfer': {
                    label: 'Transfer Reference *',
                    placeholder: 'e.g., Transfer from AS to CS',
                    hint: 'Enter transfer details or reference',
                    required: true
                },
                'returned_item': {
                    label: 'Return Reference *',
                    placeholder: 'e.g., Original order number',
                    hint: 'Enter the return or order reference',
                    required: true
                },
                'parts_dismantle': {
                    label: 'Source Item Reference',
                    placeholder: 'e.g., Parent item tracking code',
                    hint: 'Optional reference to the original item',
                    required: false
                },
                'other': {
                    label: 'Source Details',
                    placeholder: 'Enter source details',
                    hint: 'Optional additional details',
                    required: false
                }
            };
            
            if (source && sourceConfig[source]) {
                const config = sourceConfig[source];
                sourceDetailsLabel.text(config.label);
                sourceDetailsInput.attr('placeholder', config.placeholder);
                sourceDetailsHint.text(config.hint);
                
                // Set required attribute
                if (config.required) {
                    sourceDetailsInput.attr('required', 'required');
                    sourceDetailsInput.addClass('required-field');
                } else {
                    sourceDetailsInput.removeAttr('required');
                    sourceDetailsInput.removeClass('required-field');
                }
                
                sourceDetailsRow.show();
            } else {
                sourceDetailsRow.hide();
                sourceDetailsInput.removeAttr('required');
                sourceDetailsInput.val(''); // Clear value when hidden
            }
        }
        
        // Wizard Navigation
        let currentStage = 1;
        const totalStages = 5;
        let capturedPhotos = []; // Store captured/uploaded photos
        let cameraStream = null;
        
        // Stage navigation
        $('#nextStageBtn').on('click', async function() {
            const validationResult = await validateCurrentStage();
            if (validationResult) {
                if (currentStage < totalStages) {
                    goToStage(currentStage + 1);
                }
            }
        });
        
        $('#prevStageBtn').on('click', function() {
            if (currentStage > 1) {
                goToStage(currentStage - 1);
            }
        });
        
        function goToStage(stageNumber) {
            // Hide current stage
            $('#stage' + currentStage).hide();
            $('.wizard-step[data-step="' + currentStage + '"]').removeClass('active').addClass('completed');
            
            // Show new stage
            currentStage = stageNumber;
            $('#stage' + currentStage).show();
            $('.wizard-step[data-step="' + currentStage + '"]').addClass('active').removeClass('completed');
            
            // Update navigation buttons
            $('#prevStageBtn').toggle(currentStage > 1);
            $('#nextStageBtn').toggle(currentStage < totalStages);
            $('#saveBtn').toggle(currentStage === totalStages);
        }
        
        function validateCurrentStage() {
            let isValid = true;
            let errorMessage = '';
            
            if (currentStage === 1) {
                // Validate essential information
                if (!$('#itemName').val()) {
                    errorMessage = 'Item name is required';
                    isValid = false;
                }
                else if (!$('#itemSource').val()) {
                    errorMessage = 'Source must be selected';
                    isValid = false;
                }
                else if ($('#sourceDetails').attr('required') && !$('#sourceDetails').val().trim()) {
                    const labelText = $('#sourceDetailsLabel').text().replace(' *', '');
                    errorMessage = `${labelText} is required for this source type`;
                    isValid = false;
                }
                <?php if ($can_view_financial): ?>
                else if ($('#purchasePrice').val() === '' || $('#purchasePrice').val() === null) {
                    errorMessage = 'Purchase price is required (enter 0 if item was free)';
                    isValid = false;
                }
                <?php endif; ?>
                
                // Check for preprinted code - warn if not provided
                if (isValid && !$('#preprintedCode').val().trim()) {
                    return new Promise((resolve) => {
                        Swal.fire({
                            title: 'No Pre-printed Barcode Scanned',
                            html: '<p><strong>You have not entered a pre-printed barcode (DSH code).</strong></p>' +
                                  '<p>If you proceed without scanning a barcode:</p>' +
                                  '<ul style="text-align: left; margin: 10px 40px;">' +
                                  '<li>A tracking code will be generated automatically</li>' +
                                  '<li>You will need to write this code on a label</li>' +
                                  '<li>You must attach the label to the item</li>' +
                                  '</ul>',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Proceed Without Barcode',
                            cancelButtonText: 'Go Back to Scan',
                            confirmButtonColor: '#f57c00',
                            cancelButtonColor: '#6c757d'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                resolve(true); // Proceed without preprinted code
                            } else {
                                // Go back to let them scan
                                $('#preprintedCode').focus();
                                resolve(false);
                            }
                        });
                    });
                }
            }
            
            if (!isValid) {
                Swal.fire('Error', errorMessage, 'error');
            }
            
            return Promise.resolve(isValid);
        }
        
        // Camera and Photo Handling
        $('#openCameraBtn').on('click', async function() {
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' }
                });
                $('#cameraVideo')[0].srcObject = cameraStream;
                $('#cameraContainer').show();
                $(this).hide();
            } catch(err) {
                Swal.fire('Error', 'Unable to access camera: ' + err.message, 'error');
            }
        });
        
        $('#closeCameraBtn').on('click', function() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            $('#cameraContainer').hide();
            $('#openCameraBtn').show();
        });
        
        $('#capturePhotoBtn').on('click', function() {
            const video = $('#cameraVideo')[0];
            const canvas = $('#cameraCanvas')[0];
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            
            canvas.toBlob(function(blob) {
                const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
                addPhotoToGrid(file);
            }, 'image/jpeg', 0.85);
        });
        
        $('#photoFileInput').on('change', function(e) {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                if (file.type.startsWith('image/')) {
                    addPhotoToGrid(file);
                }
            });
            $(this).val(''); // Clear input
        });
        
        function addPhotoToGrid(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const photoIndex = capturedPhotos.length;
                capturedPhotos.push(file);
                
                const photoHtml = `
                    <div class="photo-item" data-index="${photoIndex}">
                        <img src="${e.target.result}" alt="Item photo">
                        <button type="button" class="photo-item-remove" onclick="removePhoto(${photoIndex})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                $('#photoPreviewGrid').append(photoHtml);
            };
            reader.readAsDataURL(file);
        }
        
        window.removePhoto = function(index) {
            capturedPhotos[index] = null; // Mark as deleted
            $(`.photo-item[data-index="${index}"]`).fadeOut(300, function() {
                $(this).remove();
            });
        };

        function loadItems() {
            const viewAllLocations = $('#allLocationsToggle').is(':checked');
            
            $.ajax({
                url: 'php/list_second_hand_items.php',
                method: 'GET',
                data: {
                    location: '<?=$effective_location?>',
                    view_all_locations: viewAllLocations
                },
                success: function(response) {
                    allItems = response.items || [];
                    filterAndDisplayItems();
                    updateStats();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading items:', error);
                    Swal.fire('Error', 'Failed to load items', 'error');
                }
            });
        }

        function filterAndDisplayItems() {
            const searchTerm = $('#searchInput').val().toLowerCase().trim();
            const showOutOfStock = $('#showOutOfStock').is(':checked');
            
            // Split search term into words for multi-field search
            const searchWords = searchTerm.split(/\s+/).filter(w => w.length > 0);
            
            filteredItems = allItems.filter(function(item) {
                // Status filter
                if (!showOutOfStock && item.status !== 'in_stock') {
                    return false;
                }
                
                // Search filter - all words must match at least one field
                if (searchWords.length > 0) {
                    return searchWords.every(function(word) {
                        return (item.item_name || '').toLowerCase().includes(word) ||
                               (item.serial_number || '').toLowerCase().includes(word) ||
                               (item.brand || '').toLowerCase().includes(word) ||
                               (item.model_number || '').toLowerCase().includes(word) ||
                               (item.category || '').toLowerCase().includes(word) ||
                               (item.preprinted_code || '').toLowerCase().includes(word) ||
                               (item.tracking_code || '').toLowerCase().includes(word) ||
                               (item.item_source || '').toLowerCase().includes(word);
                    });
                }
                
                return true;
            });
            
            displayItems(filteredItems);
        }

        function displayItems(items) {
            const tbody = $('#itemsList');
            
            if (items.length === 0) {
                const colspan = <?= 15 + ($can_view_financial ? 2 : 0) ?>;
                tbody.html(`<tr><td colspan="${colspan}" class="text-center py-4">
                    <i class="fas fa-inbox fa-2x text-muted mb-2"></i><br>
                    No items found
                </td></tr>`);
                return;
            }
            
            let html = '';
            items.forEach(function(item) {
                const statusClass = 'status-' + (item.status || 'in_stock').replace(' ', '_');
                const statusLabel = (item.status || 'in_stock').replace('_', ' ').toUpperCase();
                
                html += `
                    <tr class="item-row" data-id="${item.id}" title="Click to ${canManage ? 'edit' : 'view'}">
                        <td>${item.preprinted_code || '-'}</td>
                        <td>${item.tracking_code || '-'}</td>
                        <td><strong>${item.item_name || 'Unnamed Item'}</strong></td>
                        <td>${item.brand || '-'}</td>
                        <td>${item.model_number || '-'}</td>
                        <td>${item.serial_number || '-'}</td>
                        <td>${item.category || '-'}</td>
                        <td>${item.condition || '-'}</td>
                        <td>${item.item_source || '-'}</td>
                        <td>${item.selling_price ? '£' + parseFloat(item.selling_price).toFixed(2) : '-'}</td>
                        <td><span class="status-badge ${statusClass}">${statusLabel}</span></td>
                        <?php if ($can_view_financial): ?>
                        <td>£${parseFloat(item.purchase_price || 0).toFixed(2)}</td>
                        <td>£${parseFloat(item.estimated_sale_price || 0).toFixed(2)}</td>
                        <?php endif; ?>
                        <td>${item.location === 'cs' ? 'Commerce St' : 'Argyle St'}</td>
                        <td>${item.acquisition_date ? new Date(item.acquisition_date).toLocaleDateString('en-GB') : '-'}</td>
                    </tr>
                `;
            });
            
            tbody.html(html);
            
            // Add click handlers for rows - click anywhere on row to open
            $('.item-row').on('click', function() {
                const itemId = $(this).data('id');
                viewItem(itemId);
            });
        }

        function updateStats() {
            const inStock = allItems.filter(i => i.status === 'in_stock').length;
            const sold = allItems.filter(i => i.status === 'sold').length;
            
            <?php if ($can_view_financial): ?>
            const totalValue = allItems
                .filter(i => i.status === 'in_stock')
                .reduce((sum, i) => sum + parseFloat(i.estimated_sale_price || 0), 0);
            
            $('#totalValue').text('£' + totalValue.toFixed(2));
            <?php else: ?>
            $('#totalValue').text('N/A');
            <?php endif; ?>
            
            $('#inStockCount').text(inStock);
            $('#soldCount').text(sold);
        }

        function openItemModal(item = null, addMode = false) {
            // Reset form
            $('#itemForm')[0].reset();
            $('#itemId').val('');
            $('#trackingCode').val('');
            $('#sourceDetails').val('');
            $('#sourceDetailsRow').hide();
            isEditMode = false;
            
            // Reset wizard
            goToStage(1);
            $('.wizard-step').removeClass('completed');
            capturedPhotos = [];
            $('#photoPreviewGrid').empty();
            $('#cameraContainer').hide();
            $('#openCameraBtn').show();
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            
            // Hide all buttons initially
            $('#editBtn, #saveBtn, #cancelBtn').hide();
            
            // Enable all source options first
            $('#itemSource option').prop('disabled', false);
            
            if (addMode && canManage) {
                // New item - start in wizard mode
                $('#itemModalLabel').text('Add New Item');
                $('#itemLocation').val('<?=$effective_location?>');
                $('#acquisitionDate').val('<?=date('Y-m-d')?>');
                $('#itemStatus').val('in_stock'); // Default to in stock
                
                // Disable "Trade-In" option for new items (must use trade-in workflow)
                $('#itemSource option[value="trade_in"]').prop('disabled', true);
                
                // Show wizard progress
                $('#wizardProgress').show();
                toggleEditMode(true);
            } else if (item) {
                // Viewing existing item - hide wizard, show all fields
                $('#itemModalLabel').text('Item Details');
                $('#wizardProgress').hide();
                
                // Show all stages at once when viewing
                $('.wizard-stage').show();
                
                populateForm(item);
                toggleEditMode(false);
                if (canManage) {
                    $('#editBtn').show();
                }
            }
            
            itemModal.show();
        }

        function viewItem(itemId) {
            // Find item in allItems array
            const item = allItems.find(i => i.id == itemId);
            if (item) {
                openItemModal(item);
            } else {
                Swal.fire('Error', 'Item not found', 'error');
            }
        }

        function populateForm(item) {
            $('#itemId').val(item.id);
            $('#itemName').val(item.item_name);
            $('#itemBrand').val(item.brand);
            $('#itemModel').val(item.model_number);
            $('#itemSerial').val(item.serial_number);
            $('#itemCategory').val(item.category);
            $('#itemCondition').val(item.condition);
            $('#itemSource').val(item.item_source);
            $('#itemStatus').val(item.status);
            $('#itemLocation').val(item.location);
            $('#acquisitionDate').val(item.acquisition_date);
            $('#preprintedCode').val(item.preprinted_code);
            $('#trackingCode').val(item.tracking_code);
            $('#detailedCondition').val(item.detailed_condition);
            $('#itemNotes').val(item.notes);
            $('#warrantyInfo').val(item.warranty_info);
            
            // Populate source details and trigger source details update
            $('#sourceDetails').val(item.supplier_info);
            updateSourceDetails(item.item_source);
            
            // Pricing fields (visible to all)
            $('#sellingPrice').val(item.selling_price);
            $('#lowestPrice').val(item.lowest_price);
            
            <?php if ($can_view_financial): ?>
            $('#purchasePrice').val(item.purchase_price);
            $('#estimatedValue').val(item.estimated_value);
            $('#estimatedSalePrice').val(item.estimated_sale_price);
            calculateProfit();
            <?php endif; ?>
            
            <?php if ($can_view_seller): ?>
            $('#sellerName').val(item.customer_name);
            $('#sellerContact').val(item.customer_contact);
            $('#sellerId').val(item.customer_id);
            <?php endif; ?>
        }

        function calculateProfit() {
            const purchase = parseFloat($('#purchasePrice').val()) || 0;
            const sale = parseFloat($('#estimatedSalePrice').val()) || 0;
            const profit = sale - purchase;
            const margin = purchase > 0 ? ((profit / purchase) * 100).toFixed(1) : 0;
            
            $('#profitCalc').html(`
                <strong>Profit:</strong> £${profit.toFixed(2)} 
                <span class="ms-3"><strong>Margin:</strong> ${margin}%</span>
            `);
        }

        function toggleEditMode(enable) {
            isEditMode = enable;
            
            if (isEditMode) {
                // Save original form data for cancel
                originalFormData = $('#itemForm').serializeArray();
                
                const isExistingItem = $('#itemId').val() !== '';
                const hasPreprintedCode = $('#preprintedCode').val() !== '';
                
                // Enable editing
                $('#itemForm input:not(#trackingCode):not(#itemId), #itemForm select, #itemForm textarea')
                    .prop('readonly', false)
                    .prop('disabled', false);
                
                // For existing items, keep source, seller fields, and acquisition date readonly (they cannot be changed)
                if (isExistingItem) {
                    $('#itemSource, #acquisitionDate').prop('readonly', true).prop('disabled', true);
                    <?php if ($can_view_seller): ?>
                    $('#sellerName, #sellerContact, #sellerId').prop('readonly', true).prop('disabled', true);
                    <?php endif; ?>
                }
                
                // If preprinted code is already set, it cannot be changed
                if (hasPreprintedCode) {
                    $('#preprintedCode').prop('readonly', true).prop('disabled', true);
                }
                
                // Show Save/Cancel, hide Edit
                $('#editBtn').hide();
                $('#saveBtn, #cancelBtn').show();
            } else {
                // Disable editing (but never disable itemId as it needs to be submitted)
                $('#itemForm input:not(#itemId), #itemForm select, #itemForm textarea')
                    .prop('readonly', true)
                    .prop('disabled', true);
                
                // Always keep tracking code and item ID readonly (but not disabled)
                $('#trackingCode, #itemId').prop('readonly', true).prop('disabled', false);
                
                // Show Edit, hide Save/Cancel
                $('#editBtn').show();
                $('#saveBtn, #cancelBtn').hide();
            }
        }

        function cancelEdit() {
            // Restore original values
            originalFormData.forEach(function(field) {
                $('[name="' + field.name + '"]').val(field.value);
            });
            
            toggleEditMode(false);
            calculateProfit(); // Recalculate profit with original values
        }

        function saveItem() {
            // Validate required fields
            if (!$('#itemName').val()) {
                Swal.fire('Error', 'Item name is required', 'error');
                return;
            }
            
            // Validate source is selected
            if (!$('#itemSource').val()) {
                Swal.fire('Error', 'Source must be selected', 'error');
                return;
            }
            
            // Validate source details if required
            const sourceDetailsInput = $('#sourceDetails');
            if (sourceDetailsInput.attr('required') && !sourceDetailsInput.val().trim()) {
                const labelText = $('#sourceDetailsLabel').text().replace(' *', '');
                Swal.fire('Error', `${labelText} is required for this source type`, 'error');
                return;
            }
            
            // Validate purchase price is provided if financial fields are visible
            <?php if ($can_view_financial): ?>
            if ($('#purchasePrice').val() === '' || $('#purchasePrice').val() === null) {
                Swal.fire('Error', 'Purchase price is required (enter 0 if item was free)', 'error');
                return;
            }
            <?php endif; ?>
            
            // Validate selling price is provided
            if ($('#sellingPrice').val() === '' || $('#sellingPrice').val() === null) {
                Swal.fire('Error', 'Selling price is required', 'error');
                return;
            }
            
            // Validate preprinted code format if provided
            const preprintedCode = $('#preprintedCode').val().trim();
            if (preprintedCode && !preprintedCode.toUpperCase().startsWith('DSH')) {
                Swal.fire('Error', 'Preprinted code must start with "DSH"', 'error');
                return;
            }
            
            // Create FormData to handle both form data and photos
            const formData = new FormData();
            
            // Add all form fields
            $('#itemForm').serializeArray().forEach(function(field) {
                formData.append(field.name, field.value);
            });
            
            // Add photos
            capturedPhotos.forEach(function(photo, index) {
                if (photo !== null) { // Skip deleted photos
                    formData.append('item_photos[]', photo);
                }
            });
            
            Swal.fire({
                title: 'Saving...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'php/save_second_hand_item.php',
                method: 'POST',
                data: formData,
                processData: false, // Don't process the FormData
                contentType: false, // Let browser set correct content type
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const usedPreprintedCode = $('#preprintedCode').val().trim();
                        
                        // If no preprinted code was used, show the generated tracking code
                        if (!usedPreprintedCode && response.tracking_code) {
                            Swal.fire({
                                title: '<strong>Item Saved Successfully!</strong>',
                                html: `
                                    <div style="margin: 20px 0;">
                                        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; border: 2px solid #ffc107; margin-bottom: 20px;">
                                            <p style="font-size: 1.1em; margin-bottom: 10px;"><strong>Generated Tracking Code:</strong></p>
                                            <p style="font-size: 2em; font-weight: bold; color: #d63384; margin: 0;">${response.tracking_code}</p>
                                        </div>
                                        <div style="text-align: left; background: #f8f9fa; padding: 15px; border-radius: 6px;">
                                            <p style="margin: 0 0 10px 0;"><strong>⚠️ IMPORTANT: Next Steps</strong></p>
                                            <ol style="margin: 0; padding-left: 20px;">
                                                <li>Write <strong>${response.tracking_code}</strong> on a label</li>
                                                <li>Attach the label to the item</li>
                                                <li>Ensure the label is clearly visible</li>
                                            </ol>
                                        </div>
                                    </div>
                                `,
                                icon: 'warning',
                                confirmButtonText: 'I Have Labeled the Item',
                                confirmButtonColor: '#28a745',
                                allowOutsideClick: false,
                                customClass: {
                                    popup: 'swal-wide'
                                }
                            }).then(() => {
                                itemModal.hide();
                                loadItems();
                            });
                        } else {
                            Swal.fire('Success', 'Item saved successfully', 'success');
                            itemModal.hide();
                            loadItems();
                        }
                    } else {
                        Swal.fire('Error', response.message || 'Failed to save item', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Save error:', error);
                    console.error('Response:', xhr.responseText);
                    
                    let errorMessage = 'Failed to save item';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                        if (response.debug) {
                            console.error('Debug info:', response.debug);
                            errorMessage += '\n\nDebug: ' + response.debug.file + ' on line ' + response.debug.line;
                        }
                    } catch(e) {
                        // If response is not JSON, show raw response
                        console.error('Raw response:', xhr.responseText);
                        errorMessage = xhr.responseText.substring(0, 500);
                    }
                    
                    Swal.fire('Error', errorMessage, 'error');
                }
            });
        }
    });
    </script>
</body>
</html>
