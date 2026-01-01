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
                            <td colspan="<?= 14 + ($can_view_financial ? 2 : 0) ?>" class="text-center">
                                <i class="fas fa-spinner fa-spin me-2"></i> Loading items...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Item Details/Edit Modal -->
    <div class="modal fade" id="itemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalLabel">Item Details</h5>
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
                        
                        <!-- Basic Information -->
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
                        
                        <div class="row mt-2">
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
                        
                        <div class="row mt-2">
                            <div class="col-md-3">
                                <label class="form-label">Condition *</label>
                                <select class="form-select" id="itemCondition" name="condition" required <?= !$can_manage ? 'disabled' : '' ?>>
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Source *</label>
                                <select class="form-select" id="itemSource" name="item_source" required <?= !$can_manage ? 'disabled' : '' ?>>
                                    <option value="trade_in">Trade-In</option>
                                    <option value="donation">Donation</option>
                                    <option value="purchase">Purchase</option>
                                    <option value="abandoned">Abandoned</option>
                                    <option value="parts_dismantle">Parts (Dismantle)</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" id="itemStatus" name="status" required <?= !$can_manage ? 'disabled' : '' ?>>
                                    <option value="in_stock">In Stock</option>
                                    <option value="sold">Sold</option>
                                    <option value="reserved">Reserved</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Location *</label>
                                <select class="form-select" id="itemLocation" name="location" required <?= !$can_manage ? 'disabled' : '' ?>>
                                    <option value="cs">Commerce Street</option>
                                    <option value="as">Argyle Street</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Tracking & Dates Section -->
                        <div class="section-divider">
                            <h6 class="text-muted mb-2"><i class="fas fa-barcode me-2"></i>Tracking & Dates</h6>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Preprinted Code (DSH)</label>
                                <input type="text" class="form-control" id="preprintedCode" name="preprinted_code" placeholder="DSH1, DSH2..." <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tracking Code (SH)</label>
                                <input type="text" class="form-control" id="trackingCode" name="tracking_code" placeholder="Auto-generated" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" id="acquisitionDate" name="acquisition_date" value="<?=date('Y-m-d')?>" <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                        </div>
                        
                        <!-- Notes Section -->
                        <div class="section-divider">
                            <h6 class="text-muted mb-2"><i class="fas fa-sticky-note me-2"></i>Notes & Details</h6>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <label class="form-label">Condition Notes</label>
                                <textarea class="form-control" id="detailedCondition" name="detailed_condition" rows="2" <?= !$can_manage ? 'readonly' : '' ?>></textarea>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <label class="form-label">General Notes</label>
                                <textarea class="form-control" id="itemNotes" name="notes" rows="2" <?= !$can_manage ? 'readonly' : '' ?>></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Warranty Information</label>
                                <textarea class="form-control" id="warrantyInfo" name="warranty_info" rows="2" <?= !$can_manage ? 'readonly' : '' ?>></textarea>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-12">
                                <label class="form-label">Supplier Information</label>
                                <input type="text" class="form-control" id="supplierInfo" name="supplier_info" <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                        </div>
                        
                        <!-- Financial Section (Permission Required) -->
                        <?php if ($can_view_financial): ?>
                        <div class="section-divider">
                            <h6 class="text-muted mb-2">
                                <i class="fas fa-pound-sign me-2"></i>Financial Information
                                <span class="permission-badge badge-restricted"><i class="fas fa-lock"></i> Restricted</span>
                            </h6>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Purchase Price (£)</label>
                                <input type="number" class="form-control" id="purchasePrice" name="purchase_price" step="0.01" min="0" <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Estimated Value (£)</label>
                                <input type="number" class="form-control" id="estimatedValue" name="estimated_value" step="0.01" min="0" <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Estimated Sale Price (£)</label>
                                <input type="number" class="form-control" id="estimatedSalePrice" name="estimated_sale_price" step="0.01" min="0" <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                        </div>
                        
                        <?php if ($can_manage): ?>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <strong id="profitCalc">Enter prices to see potential profit</strong>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Customer Section (Permission Required) -->
                        <?php if ($can_view_seller): ?>
                        <div class="section-divider">
                            <h6 class="text-muted mb-2">
                                <i class="fas fa-user me-2"></i>Seller Information
                                <span class="permission-badge badge-restricted"><i class="fas fa-lock"></i> Restricted</span>
                            </h6>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Seller Name</label>
                                <input type="text" class="form-control" id="sellerName" name="customer_name" <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Seller Contact</label>
                                <input type="text" class="form-control" id="sellerContact" name="customer_contact" <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Seller ID</label>
                                <input type="text" class="form-control" id="sellerId" name="customer_id" <?= !$can_manage ? 'readonly' : '' ?>>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="section-divider">
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-lock me-2"></i>
                                <strong>Seller Information:</strong> You need "View Customer Data" permission to see customer details.
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($can_manage): ?>
                    <button type="button" class="btn btn-primary" id="editBtn">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button type="button" class="btn btn-success" id="saveBtn" style="display:none;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" id="cancelBtn" style="display:none;">
                        Cancel
                    </button>
                    <?php endif; ?>
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
                const colspan = <?= 14 + ($can_view_financial ? 2 : 0) ?>;
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
            isEditMode = false;
            
            // Hide all buttons initially
            $('#editBtn, #saveBtn, #cancelBtn').hide();
            
            if (addMode && canManage) {
                // New item - start in edit mode
                $('#itemModalLabel').text('Add New Item');
                $('#itemLocation').val('<?=$effective_location?>');
                $('#acquisitionDate').val('<?=date('Y-m-d')?>');
                toggleEditMode(true);
            } else if (item) {
                // Viewing existing item - start in view mode
                $('#itemModalLabel').text('Item Details');
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
            $('#supplierInfo').val(item.supplier_info);
            
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
                
                // Enable editing
                $('#itemForm input:not(#trackingCode):not(#itemId), #itemForm select, #itemForm textarea')
                    .prop('readonly', false)
                    .prop('disabled', false);
                
                // Show Save/Cancel, hide Edit
                $('#editBtn').hide();
                $('#saveBtn, #cancelBtn').show();
            } else {
                // Disable editing
                $('#itemForm input, #itemForm select, #itemForm textarea')
                    .prop('readonly', true)
                    .prop('disabled', true);
                
                // Always keep tracking code and item ID readonly
                $('#trackingCode, #itemId').prop('readonly', true);
                
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
            
            const formData = $('#itemForm').serialize();
            
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
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', 'Item saved successfully', 'success');
                        itemModal.hide();
                        loadItems();
                    } else {
                        Swal.fire('Error', response.message || 'Failed to save item', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Save error:', error);
                    Swal.fire('Error', 'Failed to save item', 'error');
                }
            });
        }
    });
    </script>
</body>
</html>
