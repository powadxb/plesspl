<?php
session_start();
$page_title = 'Manage Product Types';
require 'php/bootstrap.php';
require 'assets/header.php';

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;
$is_super_admin = $user_details['admin'] >= 2;

// Check if user has essential product types permission
$product_types_permission = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_product_types'", 
    [$user_id]
);
$has_product_types_access = !empty($product_types_permission) && $product_types_permission[0]['has_access'];

// Must have specific permission to access this page
if (!$has_product_types_access) {
    header('Location: no_access.php');
    exit();
}
?>

<link rel="stylesheet" href="assets/css/essentials.css">
<link rel="stylesheet" href="assets/css/compact.css">

<style>
/* Additional ultra-compact styles for product types */
.categories-container {
    background: white;
    border-radius: 3px;
    padding: 4px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.card {
    margin-bottom: 3px;
    border: 1px solid #e9ecef;
    border-radius: 3px;
}

.card-header {
    padding: 3px 6px;
    background-color: #f8f9fa;
}

.card-header h5 {
    margin: 0;
    font-size: 0.8rem;
}

.card-header .btn-link {
    padding: 2px 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.card-body {
    padding: 4px;
}

.table {
    font-size: 0.7rem;
    margin-bottom: 0;
}

.table th {
    padding: 0.2rem 0.3rem;
    font-size: 0.65rem;
    font-weight: 600;
    white-space: nowrap;
}

.table td {
    padding: 0.2rem 0.3rem;
    font-size: 0.7rem;
    line-height: 1.2;
}

.table td strong {
    font-size: 0.75rem;
    display: block;
    margin-bottom: 1px;
}

.table td small {
    font-size: 0.6rem;
    line-height: 1.1;
    display: block;
}

.badge {
    font-size: 0.6rem;
    padding: 0.15em 0.25em;
}

.btn-sm {
    padding: 0.1rem 0.2rem;
    font-size: 0.65rem;
    line-height: 1.2;
}

.category-actions {
    gap: 2px;
}

.category-actions .btn {
    padding: 0.15rem 0.25rem;
    font-size: 0.7rem;
}

/* Quick keyboard shortcuts hint */
.keyboard-shortcuts {
    position: fixed;
    bottom: 5px;
    right: 5px;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 0.6rem;
    display: none;
}

.keyboard-shortcuts.show {
    display: block;
}
/* Drag and drop styles */
.drag-handle {
    cursor: grab;
    text-align: center;
    padding: 0.2rem 0.1rem !important;
    width: 30px;
    vertical-align: middle;
}

.drag-handle:active {
    cursor: grabbing;
}

.drag-handle i {
    font-size: 0.8rem;
    opacity: 0.6;
}

.drag-handle:hover i {
    opacity: 1;
    color: #007bff;
}

/* Sortable states */
.sortable-row.ui-sortable-helper {
    background-color: #e3f2fd !important;
    border: 1px solid #2196f3 !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
    opacity: 0.9;
}

.sortable-row.dragging {
    background-color: #e3f2fd;
}

.sortable-placeholder {
    height: 35px;
    background-color: #f0f8ff;
    border: 2px dashed #007bff !important;
    border-radius: 3px;
}

.sortable-placeholder td {
    border: none !important;
    background: transparent !important;
}

/* Visual feedback for reordering */
.card.reordering {
    opacity: 0.8;
    pointer-events: none;
}

.card.reorder-success {
    border-left: 4px solid #28a745;
    transition: border-left 0.3s ease;
}

/* Improved sortable tbody */
.sortable-tbody {
    position: relative;
}

.sortable-tbody.ui-sortable-disabled .drag-handle {
    cursor: not-allowed;
    opacity: 0.3;
}

/* Table row hover states */
.table tbody .sortable-row:hover {
    background-color: #f8f9fa;
}

.table tbody .sortable-row:hover .drag-handle i {
    opacity: 1;
    color: #007bff;
}

/* Compact drag handle for mobile */
@media (max-width: 768px) {
    .drag-handle {
        width: 20px;
        padding: 0.1rem !important;
    }
    
    .drag-handle i {
        font-size: 0.7rem;
    }
}

/* Disable text selection during drag */
.sortable-tbody.ui-sortable-disabled {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}

/* Visual indicators */
.reordering-indicator {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 123, 255, 0.9);
    color: white;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 0.7rem;
    z-index: 1000;
    pointer-events: none;
}
</style>

<?php require 'assets/navbar.php'; ?>

<div class="page-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-controls">
            <h1 class="page-title">
                <i class="fas fa-tags"></i>
                Product Types Management
            </h1>
            <div class="header-buttons">
                <button id="addProductTypeBtn" class="btn btn-success">
                    <i class="fas fa-plus"></i>
                    Add Type
                </button>
                <a href="manage_essential_categories.php" class="btn btn-outline-secondary">
                    <i class="fas fa-sitemap"></i>
                    Categories
                </a>
                <button id="toggleKeyboardHelp" class="btn btn-outline-info" title="Keyboard Shortcuts">
                    <i class="fas fa-keyboard"></i>
                </button>
            </div>
        </div>
        
        <!-- Category Filter -->
        <div class="filter-section mt-3">
            <div class="row">
                <div class="col-md-6">
                    <label for="categoryFilter">Filter by Category:</label>
                    <select id="categoryFilter" class="form-control">
                        <option value="">All Categories</option>
                        <!-- Populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="statusFilter">Filter by Status:</label>
                    <select id="statusFilter" class="form-control">
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Types by Category -->
    <div class="categories-container">
        <div id="categoriesAccordion">
            <!-- Populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Keyboard Shortcuts Help -->
<div class="keyboard-shortcuts" id="keyboardShortcuts">
    <strong>Shortcuts:</strong> Ctrl+N=New | E=Edit | Del=Delete | Space=Toggle | ↑↓=Navigate
</div>

<!-- Add/Edit Product Type Modal -->
<div class="modal fade" id="productTypeModal" tabindex="-1" aria-labelledby="productTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productTypeModalLabel">Add Product Type</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="productTypeForm">
                <div class="modal-body">
                    <input type="hidden" id="productTypeId" name="product_type_id">
                    
                    <div class="form-group">
                        <label for="essentialCategorySelect">Category:</label>
                        <select id="essentialCategorySelect" name="essential_category_id" class="form-control" required>
                            <option value="">Choose a category...</option>
                            <!-- Populated by JavaScript -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="productTypeName">Product Type Name:</label>
                        <input type="text" id="productTypeName" name="product_type_name" class="form-control" required>
                        <small class="form-text text-muted">e.g., "RTX 5060", "8GB DDR4 Desktop", "HDMI Cable 1m"</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="minimumStockQty">Min Stock:</label>
                                <input type="number" id="minimumStockQty" name="minimum_stock_qty" class="form-control" min="0" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="displayOrder">Order:</label>
                                <input type="number" id="displayOrder" name="display_order" class="form-control" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" id="isActive" name="is_active" class="form-check-input" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reorder Modal -->
<div class="modal fade" id="reorderModal" tabindex="-1" aria-labelledby="reorderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reorderModalLabel">Reorder Product Types</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Drag and drop to reorder product types within <strong id="reorderCategoryName"></strong>:</p>
                <ul id="sortableProductTypes" class="list-group">
                    <!-- Populated by JavaScript -->
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" id="saveOrderBtn" class="btn btn-primary">Save Order</button>
            </div>
        </div>
    </div>
</div>

<!-- Product Type Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Product Type Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detailsModalBody">
                <!-- Populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" id="editFromDetailsBtn" class="btn btn-primary">Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div id="spinner" style="display: none; position: fixed; top: 50%; left: 50%; z-index: 9999;">
    <i class="fas fa-spinner fa-spin fa-3x"></i>
</div>

<script>
// Global configuration
window.PRODUCT_TYPES_CONFIG = {
    isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
    isSuperAdmin: <?php echo $is_super_admin ? 'true' : 'false'; ?>,
    hasProductTypesAccess: <?php echo $has_product_types_access ? 'true' : 'false'; ?>,
    canEdit: <?php echo $has_product_types_access ? 'true' : 'false'; ?>,
    canReorder: <?php echo $is_super_admin ? 'true' : 'false'; ?>
};

// Keyboard shortcuts
$(document).ready(function() {
    let selectedRow = null;
    
    // Keyboard navigation
    $(document).keydown(function(e) {
        // Only if no modal is open and no input is focused
        if ($('.modal.show').length === 0 && !$('input, textarea, select').is(':focus')) {
            switch(e.which) {
                case 78: // N key
                    if (e.ctrlKey) {
                        e.preventDefault();
                        $('#addProductTypeBtn').click();
                    }
                    break;
                case 69: // E key
                    if (selectedRow) {
                        e.preventDefault();
                        selectedRow.find('.edit-product-type').click();
                    }
                    break;
                case 46: // Delete key
                    if (selectedRow && window.PRODUCT_TYPES_CONFIG.isSuperAdmin) {
                        e.preventDefault();
                        selectedRow.find('.delete-product-type').click();
                    }
                    break;
                case 32: // Space key
                    if (selectedRow) {
                        e.preventDefault();
                        selectedRow.find('.view-details').click();
                    }
                    break;
                case 38: // Up arrow
                    e.preventDefault();
                    navigateRows(-1);
                    break;
                case 40: // Down arrow
                    e.preventDefault();
                    navigateRows(1);
                    break;
            }
        }
    });
    
    // Row selection
    $(document).on('click', '.table tbody tr', function() {
        $('.table tbody tr').removeClass('table-active');
        $(this).addClass('table-active');
        selectedRow = $(this);
    });
    
    // Navigation function
    function navigateRows(direction) {
        const rows = $('.table tbody tr:visible');
        if (rows.length === 0) return;
        
        if (!selectedRow || selectedRow.length === 0) {
            selectedRow = rows.first();
        } else {
            const currentIndex = rows.index(selectedRow);
            let newIndex = currentIndex + direction;
            
            if (newIndex < 0) newIndex = rows.length - 1;
            if (newIndex >= rows.length) newIndex = 0;
            
            selectedRow = rows.eq(newIndex);
        }
        
        $('.table tbody tr').removeClass('table-active');
        selectedRow.addClass('table-active');
        
        // Scroll into view
        selectedRow[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    // Toggle keyboard help
    $('#toggleKeyboardHelp').click(function() {
        $('#keyboardShortcuts').toggleClass('show');
    });
    
    // Auto-hide keyboard help after 5 seconds
    setTimeout(function() {
        $('#keyboardShortcuts').removeClass('show');
    }, 5000);
});
</script>

<?php require 'assets/footer.php'; ?>

<!-- jQuery UI for drag and drop (loaded after jQuery from footer) -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="assets/js/manage_product_types.js"></script>

<script>
// Modal accessibility fix
$(document).ready(function() {
    $('.modal').on('show.bs.modal', function() {
        $(this).removeAttr('aria-hidden');
    }).on('shown.bs.modal', function() {
        $(this).attr('aria-hidden', 'false');
    }).on('hide.bs.modal', function() {
        $(this).removeAttr('aria-hidden');
    }).on('hidden.bs.modal', function() {
        $(this).attr('aria-hidden', 'true');
    });
});
</script>

</body>
</html>