<?php
session_start();
$page_title = 'Manage Essential Categories';
require 'php/bootstrap.php';
require 'assets/header.php';

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;
$is_super_admin = $user_details['admin'] >= 2;

// Check if user has essential categories permission
$categories_permission = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_categories'", 
    [$user_id]
);
$has_categories_access = !empty($categories_permission) && $categories_permission[0]['has_access'];

// Must have specific permission to access this page
if (!$has_categories_access) {
    header('Location: no_access.php');
    exit();
}
?>

<link rel="stylesheet" href="assets/css/essentials.css">
<link rel="stylesheet" href="assets/css/compact.css">

<style>
/* Ultra-compact categories management */
.page-container {
    padding: 4px !important;
    max-width: 100% !important;
}

.header-section {
    padding: 6px 8px !important;
    margin-bottom: 4px !important;
}

.page-title {
    font-size: 1.1rem !important;
    margin: 0 !important;
}

.header-buttons {
    gap: 4px !important;
}

.header-buttons .btn {
    padding: 0.2rem 0.4rem !important;
    font-size: 0.75rem !important;
}

.table-container {
    padding: 4px !important;
    background: white;
    border-radius: 3px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.table {
    font-size: 0.75rem !important;
    margin-bottom: 0 !important;
}

.table th {
    padding: 0.3rem 0.4rem !important;
    font-size: 0.7rem !important;
    font-weight: 600;
    background-color: #f8f9fa;
    border-top: none;
    white-space: nowrap;
}

.table td {
    padding: 0.3rem 0.4rem !important;
    vertical-align: middle;
    font-size: 0.75rem !important;
    line-height: 1.2;
}

.table tbody tr:hover {
    background-color: #e3f2fd !important;
}

.badge {
    font-size: 0.65rem !important;
    padding: 0.15em 0.25em !important;
}

.btn-sm {
    padding: 0.1rem 0.2rem !important;
    font-size: 0.65rem !important;
    line-height: 1.2;
}

.drag-handle {
    cursor: grab;
    text-align: center;
    padding: 0.2rem 0.1rem !important;
    width: 30px;
}

.drag-handle:active {
    cursor: grabbing;
}

.drag-handle i {
    font-size: 0.7rem;
    opacity: 0.6;
}

.drag-handle:hover i {
    opacity: 1;
    color: #007bff;
}

.sortable-row.ui-sortable-helper {
    background-color: #e3f2fd !important;
    border: 1px solid #2196f3 !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
}

.sortable-placeholder {
    height: 35px;
    background-color: #f0f8ff;
    border: 2px dashed #007bff !important;
    border-radius: 3px;
}

.reordering {
    opacity: 0.8;
}

.reorder-success {
    border-left: 4px solid #28a745;
    transition: border-left 0.3s ease;
}

/* Keyboard shortcuts */
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

/* Modal improvements */
.modal-header {
    padding: 0.4rem 0.6rem !important;
}

.modal-body {
    padding: 0.6rem !important;
}

.modal-footer {
    padding: 0.4rem 0.6rem !important;
}

.modal-title {
    font-size: 0.9rem !important;
}

.form-group {
    margin-bottom: 0.4rem !important;
}

.form-group label {
    font-size: 0.75rem !important;
    margin-bottom: 0.1rem !important;
    font-weight: 600;
}

.form-control {
    height: calc(1.2em + 0.4rem + 2px) !important;
    padding: 0.2rem 0.4rem !important;
    font-size: 0.75rem !important;
}

.form-text {
    font-size: 0.65rem !important;
    margin-top: 0.1rem !important;
}

/* Searchable dropdown styling */
#masterCategorySelect {
    height: auto !important;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
}

#masterCategorySelect option {
    padding: 0.25rem 0.4rem;
    font-size: 0.75rem;
}

#masterCategorySelect option:hover {
    background-color: #e3f2fd;
}

#masterCategoryFilter {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    border-bottom: none;
    margin-bottom: 0 !important;
}

#masterCategorySelect {
    border-top-left-radius: 0;
    border-top-right-radius: 0;
    border-top: 1px solid #ced4da;
}

/* Focus states */
#masterCategoryFilter:focus {
    border-bottom: 1px solid #80bdff;
    box-shadow: none;
}

#masterCategorySelect:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.25);
}

/* Excel-like selection */
.table tbody tr:focus,
.table tbody tr.selected {
    background-color: #e3f2fd !important;
    outline: 1px solid #2196f3;
    outline-offset: -1px;
}

@media (max-width: 768px) {
    .page-container {
        padding: 2px !important;
    }
    
    .table th,
    .table td {
        padding: 0.2rem 0.3rem !important;
        font-size: 0.7rem !important;
    }
    
    .btn-sm {
        padding: 0.05rem 0.1rem !important;
        font-size: 0.6rem !important;
    }
}

/* ERROR VALIDATION STYLING */
.is-invalid {
    border: 2px solid #dc3545 !important;
    background-color: #fff5f5 !important;
}

.is-invalid:focus {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

/* Shake animation for validation errors */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.is-invalid {
    animation: shake 0.3s ease-in-out;
}

/* Error text helper */
.invalid-feedback {
    display: block;
    color: #dc3545;
    font-size: 0.65rem;
    margin-top: 0.1rem;
}
</style>

<?php require 'assets/navbar.php'; ?>

<div class="page-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-controls">
            <h1 class="page-title">
                <i class="fas fa-sitemap"></i>
                Essential Categories
            </h1>
            <div class="header-buttons">
                <button id="addCategoryBtn" class="btn btn-success">
                    <i class="fas fa-plus"></i>
                    Add Category
                </button>
                <?php if ($is_super_admin): ?>
                    <button id="reorderCategoriesBtn" class="btn btn-warning">
                        <i class="fas fa-sort"></i>
                        Reorder Modal
                    </button>
                <?php endif; ?>
                <button id="toggleKeyboardHelp" class="btn btn-outline-info" title="Keyboard Shortcuts">
                    <i class="fas fa-keyboard"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table id="categoriesTable" class="table table-striped">
                <thead>
                    <tr>
                        <?php if ($is_super_admin): ?>
                            <th width="30px">Drag</th>
                        <?php endif; ?>
                        <th width="50px">Order</th>
                        <th>Display Name</th>
                        <th>Original Category</th>
                        <th width="80px">Active</th>
                        <th width="120px">Actions</th>
                    </tr>
                </thead>
                <tbody id="categoriesTableBody" class="sortable-tbody">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Keyboard Shortcuts Help -->
<div class="keyboard-shortcuts" id="keyboardShortcuts">
    <strong>Shortcuts:</strong> Ctrl+N=New | E=Edit | Del=Delete | ↑↓=Navigate | Drag=Reorder
</div>

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel">Add Essential Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="categoryForm">
                <div class="modal-body">
                    <input type="hidden" id="categoryId" name="category_id">
                    
                    <div class="form-group">
                        <label for="masterCategorySelect">Select Category:</label>
                        <input type="text" id="masterCategoryFilter" class="form-control mb-2" 
                               placeholder="Type to filter categories..." style="font-size: 0.75rem;">
                        <select id="masterCategorySelect" name="master_category_id" class="form-control" 
                                required size="8" style="font-size: 0.75rem;">
                            <option value="">Choose a category...</option>
                            <!-- Populated by JavaScript -->
                        </select>
                        <input type="hidden" id="originalMasterCategoryId" name="original_master_category_id">
                    </div>
                    
                    <div class="form-group">
                        <label for="displayName">Display Name:</label>
                        <input type="text" id="displayName" name="display_name" class="form-control" required>
                        <small class="form-text text-muted">How this category appears in dashboard</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="displayOrder">Order:</label>
                                <input type="number" id="displayOrder" name="display_order" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="isActive">Status:</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" id="isActive" name="is_active" class="form-check-input" checked>
                                    <label class="form-check-label" for="isActive">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
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
                <h5 class="modal-title" id="reorderModalLabel">Reorder Categories</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Drag and drop to reorder categories:</p>
                <ul id="sortableCategories" class="list-group">
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

<!-- Loading Spinner -->
<div id="spinner" style="display: none; position: fixed; top: 50%; left: 50%; z-index: 9999;">
    <i class="fas fa-spinner fa-spin fa-3x"></i>
</div>

<?php require 'assets/footer.php'; ?>

<!-- jQuery UI for drag and drop (loaded after jQuery from footer) -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<script>
// Global configuration
window.ESSENTIALS_CONFIG = {
    isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
    isSuperAdmin: <?php echo $is_super_admin ? 'true' : 'false'; ?>,
    hasCategoriesAccess: <?php echo $has_categories_access ? 'true' : 'false'; ?>,
    canEdit: <?php echo $has_categories_access ? 'true' : 'false'; ?>,
    canReorder: <?php echo $is_super_admin ? 'true' : 'false'; ?>
};

// Keyboard shortcuts and navigation
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
                        $('#addCategoryBtn').click();
                    }
                    break;
                case 69: // E key
                    if (selectedRow) {
                        e.preventDefault();
                        selectedRow.find('.edit-category').click();
                    }
                    break;
                case 46: // Delete key
                    if (selectedRow && window.ESSENTIALS_CONFIG.isSuperAdmin) {
                        e.preventDefault();
                        selectedRow.find('.delete-category').click();
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
        $('.table tbody tr').removeClass('selected');
        $(this).addClass('selected');
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
        
        $('.table tbody tr').removeClass('selected');
        selectedRow.addClass('selected');
        
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
    
    // Modal accessibility fix
    $('.modal').on('show.bs.modal', function() {
        $(this).removeAttr('aria-hidden');
    }).on('shown.bs.modal', function() {
        // Don't set aria-hidden when modal is visible and has focus
        $(this).removeAttr('aria-hidden');
    }).on('hide.bs.modal', function() {
        $(this).removeAttr('aria-hidden');
    }).on('hidden.bs.modal', function() {
        $(this).attr('aria-hidden', 'true');
    });
});
</script>

<script src="assets/js/manage_essential_categories.js?v=<?=time()?>"></script>

</body>
</html>