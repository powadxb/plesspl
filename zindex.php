<?php
ob_start();
session_start();
$page_title = 'Stock View';
require 'assets/header.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

// Bootstrap & DB
require __DIR__ . '/php/bootstrap.php';

// Retrieve user info (admin or has permission)
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? null;

// Check if user exists
if (empty($user_details)) {
    header("Location: login.php");
    exit();
}

// Check if user has permission for this page
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'zindex'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Pre-fetch data
$all_manufacturers = $DB->query("SELECT * FROM master_pless_manufacturers ORDER BY manufacturer_name ASC");
$tax_rates         = $DB->query("SELECT * FROM tax_rates ORDER BY name ASC");
$categories        = $DB->query("SELECT * FROM master_categories ORDER BY pos_category ASC");

// Settings
require 'php/settings.php';
?>

<!-- CSS Files -->
<link rel="stylesheet" href="assets/css/tables.css?<?=time()?>">
<link rel="stylesheet" href="assets/css/stock-view.css?<?=time()?>">
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

<div class="page-wrapper">
    <?php require 'assets/navbar.php'; ?>
    <div class="page-content--bgf7">
        <section class="au-breadcrumb2 p-0 pt-4"></section>

        <section class="welcome p-t-10">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="title-4"><?= $page_title ?></h1>
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

                <!-- Toolbar -->
                <div class="table-data__tool">
                    <!-- Controls Container -->
                    <div class="controls-container">
                        <button class="btn btn-success btn-sm" id="newItemBtn">
                            <i class="zmdi zmdi-plus"></i> Add
                        </button>
                        
                        <div class="btn-group">
                            <button class="btn btn-primary btn-sm" id="exportToCsv">CSV</button>
                            <button class="btn btn-success btn-sm" id="exportToPos">POS</button>
                            <button class="btn btn-warning btn-sm" id="exportToMagento">MAG</button>
                            <button class="btn btn-warning btn-sm" id="exportToMagentoFiltered">FMAG</button>
                        </div>
                        
                        <?php if ($user_details['admin'] >= 1): ?>
                        <div class="btn-group">
                            <button class="btn btn-info btn-sm" id="addSelectedToCount">Add Selected to Count</button>
                            <button class="btn btn-secondary btn-sm" id="addAllToCount">Add All Filtered to Count</button>
                        </div>
                        <?php endif; ?>
                        
                        <label class="filter-toggle">
                            <input type="checkbox" id="enabledProducts" class="filterRecords" checked>
                            <span>Active</span>
                        </label>
                        <label class="filter-toggle">
                            <input type="checkbox" id="wwwFilter" class="filterRecords">
                            <span>WWW</span>
                        </label>
                        
                        <input type="text" class="sku-input" id="skuSearchQuery" placeholder="SKU Search">
                        <input type="text" class="main-search" id="searchQuery" placeholder="Search Products">
                        <select id="categoryFilter" class="category-select">
                            <option value="">All Categories</option>
                        </select>
                        
                        <label class="edit-control">
                            <input type="checkbox" id="enableEditing" class="edit-checkbox">
                            <span class="edit-label">Enable Editing</span>
                        </label>
                    </div>

                    <!-- Stock Filters -->
                    <div class="stock-container">
                        <div class="cs-stock">
                            <span>CS Stock:</span>
                            <label class="filter-toggle">
                                <input type="checkbox" id="csNegativeStock" class="filterRecords">
                                <span>-ve</span>
                            </label>
                            <label class="filter-toggle">
                                <input type="checkbox" id="csZeroStock" class="filterRecords">
                                <span>0</span>
                            </label>
                            <div class="number-filter">
                                <label class="filter-toggle">
                                    <input type="checkbox" id="csAboveStock" class="filterRecords">
                                    <span>≥</span>
                                </label>
                                <input type="number" id="csAboveValue" class="number-input" value="0">
                            </div>
                            <div class="number-filter">
                                <label class="filter-toggle">
                                    <input type="checkbox" id="csBelowStock" class="filterRecords">
                                    <span>≤</span>
                                </label>
                                <input type="number" id="csBelowValue" class="number-input" value="0">
                            </div>
                        </div>

                        <div class="as-stock">
                            <span>AS Stock:</span>
                            <label class="filter-toggle">
                                <input type="checkbox" id="asNegativeStock" class="filterRecords">
                                <span>-ve</span>
                            </label>
                            <label class="filter-toggle">
                                <input type="checkbox" id="asZeroStock" class="filterRecords">
                                <span>0</span>
                            </label>
                            <div class="number-filter">
                                <label class="filter-toggle">
                                    <input type="checkbox" id="asAboveStock" class="filterRecords">
                                    <span>≥</span>
                                </label>
                                <input type="number" id="asAboveValue" class="number-input" value="0">
                            </div>
                            <div class="number-filter">
                                <label class="filter-toggle">
                                    <input type="checkbox" id="asBelowStock" class="filterRecords">
                                    <span>≤</span>
                                </label>
                                <input type="number" id="asBelowValue" class="number-input" value="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="table-responsive table-responsive-data2">
                  <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2">
                    <thead>
                      <tr>
                        <?php if ($user_details['admin'] >= 1): ?>
                        <th style="width: 60px;">
                            <div style="font-size: 9px; margin-bottom: 2px;">Count</div>
                            <input type="checkbox" id="selectAll">
                        </th>
                        <?php endif; ?>
                        <th>Enable</th>
                        <th>WWW</th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="sku" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="sku" data-order="DESC"></i>
                          </div>
                          sku
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="name" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="name" data-order="DESC"></i>
                          </div>
                          name
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="manufacturer" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="manufacturer" data-order="DESC"></i>
                          </div>
                          manufacturer
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="mpn" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="mpn" data-order="DESC"></i>
                          </div>
                          mpn
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="categories" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="categories" data-order="DESC"></i>
                          </div>
                          category
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="ean" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="ean" data-order="DESC"></i>
                          </div>
                          ean
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="price" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="price" data-order="DESC"></i>
                          </div>
                          retail inc
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="trade" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="trade" data-order="DESC"></i>
                          </div>
                          trade inc
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="cs_stock" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="cs_stock" data-order="DESC"></i>
                          </div>
                          cs stock
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="as_stock" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="as_stock" data-order="DESC"></i>
                          </div>
                          as stock
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="pricing_method" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="pricing_method" data-order="DESC"></i>
                          </div>
                          pricing method
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="cost" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="cost" data-order="DESC"></i>
                          </div>
                          cost
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="pricing_cost" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="pricing_cost" data-order="DESC"></i>
                          </div>
                          p'cost
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="retail_markup" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="retail_markup" data-order="DESC"></i>
                          </div>
                          retail %
                        </th>
                        <th>
                          <div class="sortWrap">
                            <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="trade_markup" data-order="ASC"></i>
                            <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="trade_markup" data-order="DESC"></i>
                          </div>
                          trade %
                        </th>
                        <th>cs value</th>
                        <th>as value</th>
                        <th>combined</th>
                        <th>edit</th>
                      </tr>
                    </thead>
                    <tbody id="records"></tbody>
                  </table>
                  <div id="pagination"></div>
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

<!-- Modals -->
<?php include 'php/modals/add-item-modal.php'; ?>
<?php include 'php/modals/update-item-modal.php'; ?>

<!-- Hidden Fields -->
<input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
<input type="hidden" id="offset" value="0">
<input type="hidden" id="sortCol">

<?php require 'assets/footer.php'; ?>

<!-- JavaScript -->
<script src="assets/js/stock-view.js?<?=time()?>"></script>