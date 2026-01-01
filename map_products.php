<?php
session_start();
$page_title = 'Map Products to Essential Types';
require 'php/bootstrap.php';
require 'assets/header.php';

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;
$is_super_admin = $user_details['admin'] >= 2;

// Check if user has essential mapping permission
$mapping_permission = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_mapping'", 
    [$user_id]
);
$has_mapping_access = !empty($mapping_permission) && $mapping_permission[0]['has_access'];

// Must have specific permission to access this page
if (!$has_mapping_access) {
    header('Location: no_access.php');
    exit();
}
?>

<link rel="stylesheet" href="assets/css/essentials.css">
<link rel="stylesheet" href="assets/css/product_mapping.css">
<link rel="stylesheet" href="assets/css/compact.css">

<style>
/* Exclude search field styling */
.exclude-field {
    max-width: 150px;
    border-left: 2px solid #dc3545;
    border-radius: 0;
    font-size: 0.75rem;
    background-color: #fff5f5;
}

.exclude-field:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.1rem rgba(220, 53, 69, 0.25);
    background-color: #fff;
}

.exclude-field::placeholder {
    color: #dc3545;
    opacity: 0.7;
}

.input-group .exclude-field {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.input-group-append .btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

/* Compact search help text */
.form-text {
    font-size: 0.65rem !important;
    margin-top: 0.15rem !important;
    line-height: 1.2;
}

.form-text strong {
    color: #495057;
}
.bulk-selection-header {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 8px 12px;
    margin-bottom: 8px;
    font-size: 0.8rem;
}

.bulk-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.bulk-select-all {
    margin: 0;
    font-weight: 600;
}

.bulk-select-all input {
    margin-right: 6px;
}

.bulk-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.selected-count {
    color: #007bff;
    font-weight: 600;
    font-size: 0.85rem;
}

.product-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.product-checkbox-wrapper {
    padding-top: 2px;
}

.product-checkbox {
    margin: 0;
    transform: scale(1.1);
}

.product-content {
    flex: 1;
}

.product-item.already-mapped .product-checkbox-wrapper {
    display: none;
}

.bulk-mapping-summary {
    background: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 4px;
    padding: 8px 12px;
    margin-bottom: 15px;
    font-size: 0.85rem;
}

.bulk-mapping-summary strong {
    color: #1976d2;
}
</style>

<?php require 'assets/navbar.php'; ?>

<div class="page-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-controls">
            <h1 class="page-title">
                <i class="fas fa-link"></i>
                Product Mapping
            </h1>
            <div class="header-buttons">
                <button id="viewMappingsBtn" class="btn btn-info">
                    <i class="fas fa-list"></i>
                    View All Mappings
                </button>
                <a href="manage_product_types.php" class="btn btn-outline-secondary">
                    <i class="fas fa-tags"></i>
                    Manage Product Types
                </a>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="stats-section mt-3">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number" id="totalProductTypes">-</div>
                        <div class="stat-label">Product Types</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number" id="mappedProducts">-</div>
                        <div class="stat-label">Mapped Products</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number" id="unmappedTypes">-</div>
                        <div class="stat-label">Unmapped Types</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number" id="outOfStockTypes">-</div>
                        <div class="stat-label">Out of Stock Types</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <!-- Left Panel - Product Search -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-search"></i>
                        Search Products to Map
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <div class="search-form">
                        <div class="form-group">
                            <label for="productSearch">Search Products:</label>
                            <div class="input-group">
                                <input type="text" id="productSearch" class="form-control" 
                                       placeholder="Search by SKU, name, manufacturer, or EAN...">
                                <div class="input-group-append">
                                    <input type="text" id="excludeSearch" class="form-control exclude-field" 
                                           placeholder="Exclude terms..." title="Exclude products containing these terms">
                                    <button id="searchBtn" class="btn btn-primary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                <strong>Search:</strong> Find products containing these terms 
                                <strong>• Exclude:</strong> Hide products containing these terms
                            </small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="manufacturerFilter">Manufacturer:</label>
                                    <select id="manufacturerFilter" class="form-control">
                                        <option value="">All Manufacturers</option>
                                        <!-- Populated by JavaScript -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="stockFilter">Stock Status:</label>
                                    <select id="stockFilter" class="form-control">
                                        <option value="">All Stock Levels</option>
                                        <option value="in_stock">In Stock (>0)</option>
                                        <option value="out_of_stock">Out of Stock (0)</option>
                                        <option value="unmapped">Unmapped Only</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="showMappedProducts" class="form-check-input">
                            <label class="form-check-label" for="showMappedProducts">
                                Include already mapped products
                            </label>
                        </div>
                    </div>
                    
                    <!-- Search Results -->
                    <div id="searchResults" class="search-results mt-4">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-search fa-2x mb-2"></i><br>
                            Enter search terms to find products
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Essential Types -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tags"></i>
                        Essential Product Types
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Category Filter -->
                    <div class="form-group">
                        <label for="essentialCategoryFilter">Filter by Category:</label>
                        <select id="essentialCategoryFilter" class="form-control">
                            <option value="">All Categories</option>
                            <!-- Populated by JavaScript -->
                        </select>
                    </div>
                    
                    <!-- Essential Types List -->
                    <div id="essentialTypesList" class="essential-types-list">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Single Product Map Modal -->
<div class="modal fade" id="mapProductModal" tabindex="-1" aria-labelledby="mapProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mapProductModalLabel">Map Product to Essential Type</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Product Details</h6>
                        <div id="selectedProductDetails">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Select Essential Type</h6>
                        <div class="form-group">
                            <label for="essentialTypeSelect">Essential Product Type:</label>
                            <select id="essentialTypeSelect" class="form-control" required>
                                <option value="">Choose a product type...</option>
                                <!-- Populated by JavaScript -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="mappingNotes">Notes (optional):</label>
                            <textarea id="mappingNotes" class="form-control" rows="3" 
                                      placeholder="Additional notes about this mapping..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" id="confirmMappingBtn" class="btn btn-primary">
                    <i class="fas fa-link"></i>
                    Create Mapping
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Mapping Modal -->
<div class="modal fade" id="bulkMapProductModal" tabindex="-1" aria-labelledby="bulkMapProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkMapProductModalLabel">
                    <i class="fas fa-link"></i>
                    Bulk Map Products to Essential Type
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="bulk-mapping-summary">
                    <i class="fas fa-info-circle"></i>
                    You are about to map <strong><span id="bulkSelectedCount">0</span> products</strong> to the same essential type.
                </div>
                
                <div class="form-group">
                    <label for="bulkEssentialTypeSelect">Essential Product Type:</label>
                    <select id="bulkEssentialTypeSelect" class="form-control" required>
                        <option value="">Choose a product type...</option>
                        <!-- Populated by JavaScript -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bulkMappingNotes">Notes (optional):</label>
                    <textarea id="bulkMappingNotes" class="form-control" rows="3" 
                              placeholder="These notes will be applied to all selected products..."></textarea>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will map all selected products to the chosen essential type. This cannot be easily undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" id="confirmBulkMappingBtn" class="btn btn-primary">
                    <i class="fas fa-link"></i>
                    Map All Selected Products
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Mappings Modal -->
<div class="modal fade" id="viewMappingsModal" tabindex="-1" aria-labelledby="viewMappingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMappingsModalLabel">All Product Mappings</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="mappingsTable" class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product Type</th>
                                <th>Category</th>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Stock</th>
                                <th>Mapped Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" id="exportMappingsBtn" class="btn btn-success">
                    <i class="fas fa-file-excel"></i>
                    Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Product Details Modal -->
<div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailsModalLabel">Product Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="productDetailsBody">
                <!-- Populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" id="mapFromDetailsBtn" class="btn btn-primary">
                    <i class="fas fa-link"></i>
                    Map This Product
                </button>
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
window.MAPPING_CONFIG = {
    isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
    isSuperAdmin: <?php echo $is_super_admin ? 'true' : 'false'; ?>,
    hasMappingAccess: <?php echo $has_mapping_access ? 'true' : 'false'; ?>,
    canEdit: <?php echo $has_mapping_access ? 'true' : 'false'; ?>
};
</script>

<?php require 'assets/footer.php'; ?>

<script src="assets/js/map_products.js"></script>

<script>
// Fix modal aria-hidden accessibility issues
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