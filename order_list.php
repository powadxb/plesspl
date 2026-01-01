<?php
session_start();
$page_title = 'Order List';
require 'php/bootstrap.php';
require 'assets/header.php';

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;

// Check if user has supplier availability permission
$supplier_permission = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'supplier_availability'", 
    [$user_id]
);
$has_supplier_access = !empty($supplier_permission) && $supplier_permission[0]['has_access'];
?>

<link rel="stylesheet" href="assets/css/order_list.css">

<!-- Load jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Load required external libraries for products functionality -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js"></script>

<?php require 'assets/navbar.php'; ?>

<div class="page-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-controls">
            <h1 class="page-title">Order List</h1>
            <?php if ($is_admin): ?>
                <a href="export_order_list.php" class="btn-excel">
                    <i class="fas fa-file-excel"></i>
                    Export CSV
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Add Product Form -->
        <form id="addToOrderListForm" class="add-form">
            <div class="form-row">
                <div class="input-group sku-input">
                    <input type="text" name="sku" id="sku" class="form-input" placeholder="SKU">
                </div>
                
                <div class="input-group name-input search-container">
                    <input type="text" name="name" id="name" class="form-input" placeholder="Product Name">
                    <div id="productSuggestions" class="suggestions-dropdown"></div>
                </div>
                
                <div class="input-group nondb-input">
                    <input type="text" name="non_db_item" id="non_db_item" class="form-input" placeholder="Non Database Item">
                </div>
                
                <div class="input-group qty-input">
                    <input type="number" name="quantity" id="quantity" class="form-input" value="1" min="1">
                </div>
                
                <button type="submit" class="btn-add">
                    <i class="fas fa-plus"></i>
                    Add
                </button>
                
                <!-- Order Type Selection - Inline -->
                <div class="order-type-section">
                    <span class="order-type-label">Type:</span>
                    <div class="radio-group">
                        <label class="radio-option low-stock">
                            <input type="radio" name="order_type" value="low_stock" checked>
                            <span class="radio-custom"></span>
                            <span class="radio-text">Low Stock</span>
                        </label>
                        <label class="radio-option no-stock">
                            <input type="radio" name="order_type" value="no_stock">
                            <span class="radio-custom"></span>
                            <span class="radio-text">No Stock</span>
                        </label>
                        <label class="radio-option for-customer">
                            <input type="radio" name="order_type" value="for_customer">
                            <span class="radio-custom"></span>
                            <span class="radio-text">Customer</span>
                        </label>
                        <label class="radio-option urgent">
                            <input type="radio" name="order_type" value="urgent">
                            <span class="radio-custom"></span>
                            <span class="radio-text">Urgent</span>
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Desktop Table View -->
    <div class="desktop-view">
        <div class="table-container">
            <div id="categorizedOrderList">
                <!-- Populated by JavaScript with categorized sections -->
            </div>
        </div>
    </div>

    <!-- Mobile List View -->
    <div class="mobile-view">
        <div class="mobile-controls">
            <div class="sort-controls">
                <select id="mobileSortSelect" class="mobile-sort">
                    <option value="added_at-ASC">Newest First</option>
                    <option value="added_at-DESC">Oldest First</option>
                    <option value="last_ordered-DESC">Last Ordered</option>
                </select>
            </div>
            <div class="view-toggle">
                <button id="compactViewBtn" class="view-btn">
                    <i class="fas fa-th-list"></i>
                </button>
            </div>
        </div>
        
        <div id="mobileOrderList" class="mobile-order-list">
            <!-- Populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Mobile Detail Modal -->
<div id="mobileDetailModal" class="mobile-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Product Details</h3>
            <button id="closeModal" class="close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="modalBody" class="modal-body">
            <!-- Populated by JavaScript -->
        </div>
        <div class="modal-actions">
            <div id="modalActionButtons">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Comments Edit Modal -->
<div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commentsModalLabel">Edit Comments</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="commentsForm">
                    <input type="hidden" id="commentOrderId" name="id">
                    
                    <div class="form-group">
                        <label for="publicComment">Comment</label>
                        <small class="form-text text-muted">Visible to all users (e.g., "Available from 25/08/2025")</small>
                        <textarea class="form-control" id="publicComment" name="public_comment" rows="2" 
                                  placeholder="Enter comment visible to all users..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="privateComment">Private Comment (Admin Only)</label>
                        <small class="form-text text-muted">Only visible to admin level 1+</small>
                        <textarea class="form-control" id="privateComment" name="private_comment" rows="3"
                                  placeholder="Enter private admin comment..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCommentsBtn">Save Comments</button>
            </div>
        </div>
    </div>
</div>

<!-- Supplier Details Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierModalLabel">Supplier Availability</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="supplierModalBody">
                <!-- Populated by AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Hidden elements needed for functionality -->
<div id="spinner" style="display: none; position: fixed; top: 50%; left: 50%; z-index: 9999;">
    <i class="fas fa-spinner fa-spin fa-3x"></i>
</div>

<script>
// Global configuration
window.ORDER_LIST_CONFIG = {
    isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
    hasSupplierAccess: <?php echo $has_supplier_access ? 'true' : 'false'; ?>
};
</script>

<script src="assets/js/order_list.js"></script>

<?php require 'assets/footer.php'; ?>