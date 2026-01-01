<?php
ob_start();
session_start();
$page_title = 'Generate Pricelist';
require 'assets/header.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

// Bootstrap & DB
require __DIR__ . '/php/bootstrap.php';

// Retrieve user info
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? null;

// Check if user exists
if (empty($user_details)) {
    header("Location: login.php");
    exit();
}

// Check if user has permission for essential products
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND (page = 'essential_product_types' OR page = 'essential_categories')", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Get company settings for header
require 'php/settings.php';
?>

<!-- CSS Files -->
<link rel="stylesheet" href="assets/css/tables.css?<?=time()?>">
<style>
/* Screen Styles */
.pricelist-controls {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.control-group {
    display: inline-block;
    margin-right: 30px;
    margin-bottom: 10px;
}

.control-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}

.control-group select,
.control-group input[type="checkbox"] {
    margin-right: 5px;
}

.generate-btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    margin-right: 10px;
}

.generate-btn:hover {
    background: #218838;
}

.print-btn {
    background: #007bff;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
}

.print-btn:hover {
    background: #0056b3;
}

.preview-area {
    background: white;
    padding: 40px;
    min-height: 400px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.pricelist-header {
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 3px solid #333;
    padding-bottom: 20px;
}

.pricelist-header h1 {
    margin: 0;
    font-size: 28px;
    color: #333;
}

.pricelist-header .subtitle {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.pricelist-content {
    column-gap: 30px;
}

.pricelist-content.cols-2 {
    columns: 2;
}

.pricelist-content.cols-3 {
    columns: 3;
}

.category-section {
    break-inside: avoid;
    margin-bottom: 25px;
}

.category-header {
    background: #f8f9fa;
    padding: 8px 12px;
    font-weight: bold;
    font-size: 16px;
    color: #333;
    border-left: 4px solid #007bff;
    margin-bottom: 10px;
}

.product-item {
    padding: 8px 12px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-item:last-child {
    border-bottom: none;
}

.product-name {
    font-size: 14px;
    color: #333;
    flex: 1;
}

.product-price {
    font-weight: bold;
    color: #28a745;
    margin-left: 10px;
    white-space: nowrap;
}

.stock-indicator {
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 8px;
    white-space: nowrap;
}

.stock-ok {
    background: #d4edda;
    color: #155724;
}

.stock-low {
    background: #fff3cd;
    color: #856404;
}

.stock-out {
    background: #f8d7da;
    color: #721c24;
}

.pricelist-footer {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #333;
    font-size: 12px;
    color: #666;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    display: block;
}

/* Print Styles */
@media print {
    body * {
        visibility: hidden;
    }
    
    .preview-area,
    .preview-area * {
        visibility: visible;
    }
    
    .preview-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
        box-shadow: none;
    }
    
    .pricelist-content.cols-2 {
        columns: 2;
        column-gap: 20px;
    }
    
    .pricelist-content.cols-3 {
        columns: 3;
        column-gap: 15px;
    }
    
    .category-section {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    .pricelist-header {
        border-bottom: 2px solid #000;
    }
    
    .category-header {
        background: #f0f0f0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

@page {
    size: A4;
    margin: 15mm;
}
</style>

<div class="page-wrapper">
    <?php require 'assets/navbar.php'; ?>
    <div class="page-content--bgf7">
        <section class="au-breadcrumb2 p-0 pt-4"></section>

        <section class="welcome p-t-10">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="title-4">
                            <i class="fas fa-file-invoice"></i> <?= $page_title ?>
                        </h1>
                        <hr class="line-seprate">
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Section -->
        <section class="p-t-20">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        
                        <!-- Controls -->
                        <div class="pricelist-controls">
                            <div class="control-group">
                                <label>
                                    <input type="checkbox" id="includeOutOfStock" checked>
                                    Include Out of Stock Items
                                </label>
                            </div>
                            
                            <div class="control-group">
                                <label>
                                    <input type="checkbox" id="showStock" checked>
                                    Show Stock Levels
                                </label>
                            </div>
                            
                            <div class="control-group">
                                <label for="columnCount">Columns:</label>
                                <select id="columnCount">
                                    <option value="2" selected>2 Columns</option>
                                    <option value="3">3 Columns</option>
                                </select>
                            </div>
                            
                            <div class="control-group">
                                <button class="generate-btn" id="generateBtn">
                                    <i class="fas fa-sync"></i> Generate Pricelist
                                </button>
                                <button class="print-btn" id="printBtn">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>

                        <!-- Preview Area -->
                        <div class="preview-area" id="previewArea">
                            <div class="empty-state">
                                <i class="fas fa-file-invoice"></i>
                                <h4>Click "Generate Pricelist" to preview</h4>
                                <p>Configure your options above and click generate to see the pricelist</p>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </section>

        <section class="p-t-60 p-b-20">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="copyright">
                            <p>Copyright © <?=date("Y")?> <?=$website_name?>. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Loading Spinner -->
<div id="spinner" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:9999;">
    <i class="fas fa-spinner fa-spin fa-3x"></i>
</div>

<?php require 'assets/footer.php'; ?>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    let pricelistData = null;
    
    // Generate pricelist
    $('#generateBtn').click(function() {
        generatePricelist();
    });
    
    // Print button
    $('#printBtn').click(function() {
        window.print();
    });
    
    // Auto-regenerate when options change
    $('#columnCount').change(function() {
        if (pricelistData) {
            renderPricelist(pricelistData);
        }
    });
    
    function generatePricelist() {
        $('#spinner').show();
        
        $.ajax({
            url: 'api/get_pricelist_data.php',
            method: 'GET',
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                pricelistData = response.data;
                renderPricelist(pricelistData);
            } else {
                alert('Error: ' + (response.error || 'Failed to load data'));
            }
        })
        .fail(function(xhr) {
            console.error('Failed to load pricelist data:', xhr);
            alert('Failed to load pricelist data. Please try again.');
        })
        .always(function() {
            $('#spinner').hide();
        });
    }
    
    function renderPricelist(data) {
        const includeOutOfStock = $('#includeOutOfStock').is(':checked');
        const showStock = $('#showStock').is(':checked');
        const columns = $('#columnCount').val();
        
        let html = '';
        
        // Header
        html += '<div class="pricelist-header">';
        html += '<h1><?= $website_name ?></h1>';
        html += '<div class="subtitle">Price List - Retail Prices Inc. VAT</div>';
        html += '<div class="subtitle">Generated: ' + new Date().toLocaleDateString('en-GB') + '</div>';
        html += '</div>';
        
        // Content
        html += '<div class="pricelist-content cols-' + columns + '">';
        
        let hasItems = false;
        
        // Loop through categories
        data.categories.forEach(function(category) {
            const categoryProducts = data.products.filter(p => p.essential_category_id == category.id);
            
            // Filter out of stock if needed
            const visibleProducts = includeOutOfStock 
                ? categoryProducts 
                : categoryProducts.filter(p => p.current_stock > 0);
            
            if (visibleProducts.length > 0) {
                hasItems = true;
                
                html += '<div class="category-section">';
                html += '<div class="category-header">' + escapeHtml(category.display_name) + '</div>';
                
                visibleProducts.forEach(function(product) {
                    html += '<div class="product-item">';
                    html += '<span class="product-name">' + escapeHtml(product.product_type_name) + '</span>';
                    html += '<span class="product-price">£' + formatPrice(product.retail_price) + '</span>';
                    
                    if (showStock) {
                        let stockClass = 'stock-out';
                        let stockText = 'Out';
                        
                        if (product.current_stock >= product.minimum_stock_qty) {
                            stockClass = 'stock-ok';
                            stockText = product.current_stock;
                        } else if (product.current_stock > 0) {
                            stockClass = 'stock-low';
                            stockText = product.current_stock;
                        }
                        
                        html += '<span class="stock-indicator ' + stockClass + '">' + stockText + '</span>';
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
            }
        });
        
        html += '</div>';
        
        // Footer
        html += '<div class="pricelist-footer">';
        html += 'Prices and availability subject to change. E&OE.<br>';
        html += 'For latest stock levels and pricing, please contact us.';
        html += '</div>';
        
        if (!hasItems) {
            html = '<div class="empty-state">';
            html += '<i class="fas fa-exclamation-triangle"></i>';
            html += '<h4>No Products Available</h4>';
            html += '<p>No products match your filter criteria</p>';
            html += '</div>';
        }
        
        $('#previewArea').html(html);
    }
    
    function formatPrice(price) {
        return parseFloat(price).toFixed(2);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

