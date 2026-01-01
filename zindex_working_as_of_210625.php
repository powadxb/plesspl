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
<link rel="stylesheet" href="assets/css/tables.css?<?=time()?>">
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

<style>
/* Base Styles */
.incVatPrice, .fixedPriceMethod {
  display: none;
}

.form-group label {
  font-size: 10px;
  margin-bottom: 0;
}

/* Page Layout */
.page-wrapper {
  background-color: #f9fafb;
  min-height: 100vh;
}

.welcome {
  background-color: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 0;
}

.title-4 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
}

/* Modern Toolbar Styles */
.table-data__tool {
  background: white;
  border-radius: 0.5rem;
  padding: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 1rem;
}

.toolbar-actions {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

.action-group {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.btn-group {
  display: flex;
  gap: 1px;
}

.btn-group .btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  border: 1px solid #e5e7eb;
}

.search-group {
  display: flex;
  gap: 0.5rem;
  flex: 1;
  max-width: 600px;
}

.search-wrapper {
  position: relative;
  flex: 1;
}

.search-wrapper:first-child {
  max-width: 150px;
}

.search-icon {
  position: absolute;
  left: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 0.875rem;
}

.search-input.compact {
  width: 100%;
  padding: 0.375rem 0.5rem 0.375rem 2rem;
  font-size: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  background: #f9fafb;
}

.search-input.compact:focus {
  outline: none;
  border-color: #3b82f6;
  background: white;
}

/* Filters */
.toolbar-filters {
  padding-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.quick-filters {
  display: flex;
  gap: 0.5rem;
}

.filter-chip {
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  user-select: none;
}

.filter-chip input[type="checkbox"] {
  display: none;
}

.chip-text {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
  border-radius: 1rem;
  background: #f3f4f6;
  color: #4b5563;
  transition: all 0.2s;
}

.filter-chip input[type="checkbox"]:checked + .chip-text {
  background: #3b82f6;
  color: white;
}

.filter-chip.small .chip-text {
  padding: 0.125rem 0.5rem;
  font-size: 0.7rem;
}

.stock-filters {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.stock-filter-group {
  background: #f9fafb;
  padding: 0.5rem;
  border-radius: 0.375rem;
  flex: 1;
  min-width: 300px;
}

.filter-header {
  font-size: 0.75rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
}

.filter-options {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.number-filter {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  background: white;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  border: 1px solid #e5e7eb;
}

.number-label {
  font-size: 0.75rem;
  color: #4b5563;
}

.number-input {
  width: 40px;
  padding: 0.125rem 0.25rem;
  border: none;
  font-size: 0.75rem;
  background: transparent;
}

.number-input:focus {
  outline: none;
}

/* Table Styles */
.table-responsive {
  overflow-x: auto;
  max-height: calc(100vh - 200px);
  margin-bottom: 1rem;
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.table {
  width: 100%;
  margin-bottom: 0;
  background-color: white;
  border-collapse: collapse;
  font-size: 11px;
}

.table th {
  position: sticky;
  top: 0;
  background-color: #f8f9fa;
  padding: 0.5rem;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 10px;
  letter-spacing: 0.05em;
  border: 1px solid #e5e7eb;
  white-space: nowrap;
  vertical-align: middle;
  color: #374151;
  z-index: 2;
}

.table td {
  padding: 0.5rem;
  border: 1px solid #e5e7eb;
  white-space: nowrap;
  vertical-align: middle;
  color: #1f2937;
}

/* Column Widths */
.table th:nth-child(1) { min-width: 60px; }  /* Enable */
.table th:nth-child(2) { min-width: 60px; }  /* WWW */
.table th:nth-child(3) { min-width: 80px; }  /* SKU */
.table th:nth-child(4) { min-width: 200px; } /* Name */
.table th:nth-child(5) { min-width: 120px; } /* Manufacturer */
.table th:nth-child(6) { min-width: 100px; } /* MPN */
.table th:nth-child(7) { min-width: 120px; } /* Category */
.table th:nth-child(8) { min-width: 100px; } /* EAN */
.table th:nth-child(9) { min-width: 80px; }  /* Retail inc */
.table th:nth-child(10){ min-width: 80px; }  /* Trade inc */
.table th:nth-child(11){ min-width: 80px; }  /* CS Stock */
.table th:nth-child(12){ min-width: 80px; }  /* AS Stock */
.table th:nth-child(13){ min-width: 100px; } /* Pricing method */
.table th:nth-child(14){ min-width: 80px; }  /* Cost */
.table th:nth-child(15){ min-width: 80px; }  /* P'Cost */
.table th:nth-child(16){ min-width: 80px; }  /* Retail % */
.table th:nth-child(17){ min-width: 80px; }  /* Trade % */
.table th:nth-child(18){ min-width: 80px; }  /* CS Value */
.table th:nth-child(19){ min-width: 80px; }  /* AS Value */
.table th:nth-child(20){ min-width: 80px; }  /* Combined */
.table th:nth-child(21){ min-width: 80px; }  /* Edit */

/* Sort Icons */
.sortWrap {
  display: inline-flex;
  flex-direction: column;
  margin-left: 4px;
  gap: 0;
}

.sortIcon {
  cursor: pointer;
  color: #9ca3af;
  font-size: 8px;
  line-height: 1;
}

.sortIcon:hover {
  color: #4b5563;
}

/* Modal Styling */
.modal-content {
  border-radius: 0.5rem;
  border: none;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 1.5rem;
}

.modal-title {
  font-weight: 600;
  color: #111827;
}

.form-control {
  border-radius: 0.375rem;
  border: 1px solid #e5e7eb;
  padding: 0.5rem 0.75rem;
  transition: all 0.2s;
  width: 100%;
}

.form-control:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  outline: none;
}

/* Responsive */
@media (max-width: 768px) {
  .toolbar-actions {
    flex-direction: column;
  }
  
  .search-group {
    flex-direction: column;
    max-width: none;
  }
  
  .search-wrapper:first-child {
    max-width: none;
  }
  
  .stock-filter-group {
    min-width: 100%;
  }
  
  .modal-dialog {
    margin: 0.5rem;
  }
}
  /* Filter Layout */
.table-data__tool {
    background: #fff;
    border-radius: 4px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
}

.primary-controls {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.export-controls {
    display: flex;
    gap: 0.25rem;
}

.search-controls {
    display: flex;
    gap: 0.5rem;
    flex: 1;
}

.sku-search {
    width: 120px;
}

.main-search {
    flex: 1;
}

.filter-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.toggle-filters {
    display: flex;
    gap: 0.5rem;
}

.filter-toggle, .stock-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    background: #f8f9fa;
    cursor: pointer;
    user-select: none;
}

.filter-toggle:hover, .stock-toggle:hover {
    background: #e9ecef;
}

.category-filter {
    width: 200px;
}

.stock-filters {
    display: flex;
    gap: 1rem;
    flex: 1;
}

.location-filter {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.filter-label {
    font-weight: 600;
    font-size: 0.875rem;
    color: #495057;
}

.number-control {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.number-control input[type="number"] {
    width: 60px;
    padding: 0.25rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .primary-controls {
        flex-direction: column;
    }
    
    .search-controls {
        flex-direction: column;
    }
    
    .sku-search {
        width: 100%;
    }
    
    .stock-filters {
        flex-direction: column;
        width: 100%;
    }
    
    .location-filter {
        width: 100%;
    }
}
.category-filter {
    position: relative;
    min-width: 200px;
}

.chosen-container {
    font-size: 0.875rem;
}

.chosen-container-single .chosen-single {
    height: 31px;
    line-height: 29px;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    box-shadow: none;
}

.chosen-container-single .chosen-drop {
    border: 1px solid #ced4da;
    border-top: none;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.chosen-container .chosen-results li.highlighted {
    background: #007bff;
    color: #fff;
}

.chosen-container-single .chosen-single div b {
    background-position: 0 4px;
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

<!-- Toolbar HTML -->
<div class="table-data__tool">
    <!-- First Row Container -->
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

<style>
.table-data__tool {
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 0.375rem;
    padding: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1rem;
}

.controls-container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.btn-group {
    display: flex;
}

.btn-group .btn {
    height: 28px;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.main-search {
    flex: 1;
    height: 28px;
    padding: 0.25rem 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.25rem;
    font-size: 0.75rem;
}

.sku-input {
    width: 120px;
    height: 28px;
    padding: 0.25rem 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    background-color: #fff3e0;
}

.category-select {
    width: 160px;
    height: 28px;
    padding: 0.25rem 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.25rem;
    font-size: 0.75rem;
}

.stock-container {
    display: flex;
    gap: 2rem;
    padding-top: 0.75rem;
}

.cs-stock, .as-stock {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cs-stock span, .as-stock span {
    font-weight: 600;
    font-size: 0.75rem;
    color: #374151;
    min-width: 70px;
}

.filter-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    height: 28px;
    background: #f8f9fa;
    border: 1px solid #e5e7eb;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    margin: 0;
}

.filter-toggle:hover {
    background: #e9ecef;
}

.number-filter {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.number-input {
    width: 50px;
    height: 28px;
    padding: 0.25rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.25rem;
    font-size: 0.75rem;
}

.edit-control {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.5rem;
    height: 28px;
    background: #fff3e0;
    border: 1px solid #ffb74d;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    margin: 0;
}

.edit-label {
    color: #e65100;
    font-weight: 500;
}

/* Hide number input spinners */
.number-input::-webkit-outer-spin-button,
.number-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.number-input[type=number] {
    -moz-appearance: textfield;
}
</style>
                <!-- Table Container -->
                <div class="table-responsive table-responsive-data2">
                  <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2">
                    <thead>
                      <tr>
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
                        <th>
                          cs value
                        </th>
                        <th>
                          as value
                        </th>
                        <th>
                          combined
                        </th>
                        <th>
                          edit
                        </th>
                      </tr>
                    </thead>
                    <tbody id="records"></tbody>
                  </table>
                  <div id="pagination"></div>
                </div>
                <!-- End Table Container -->
              </div>
            </div>
          </div>
        </section>

        <section class="p-t-60 p-b-20">
          <div class="container">
            <div class="row">
              <div class="col-md-12">
                <div class="copyright">
                  <p>Copyright © <?=date("Y")?>
                    <?=$website_name?>. All rights reserved.</p>
                </div>
              </div>
            </div>
          </div>
        </section>
    </div>
</div>

<!-- Add New Item Modal -->
<div class="modal fade" id="newItemPopup" tabindex="-1" aria-labelledby="exampleModalLabel"
     aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Add New Item</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form method="POST" id="newItemForm">
          <div class="row">
            <div class="col-sm-4" style="border-right: 3px solid #007bff;">
              <!-- Basic fields -->
              <div class="form-group">
                <input type="text" id="name" name="name" class="form-control requiredField" placeholder="Name">
              </div>
              <div class="form-group">
                <select id="manufacturer" name="manufacturer" class="form-control dropDownMenu requiredField">
                  <option value="" selected>Select Manufacturer</option>
                  <?php foreach($all_manufacturers as $m): ?>
                  <option value="<?=$m['manufacturer_name']?>"><?=$m['manufacturer_name']?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <input type="text" id="mpn" name="mpn" class="form-control" placeholder="Mpn">
              </div>
              <div class="form-group">
                <input type="text" id="ean" name="ean" class="form-control" placeholder="Barcode">
              </div>
              <div class="form-group">
                <select id="categories" name="categories" class="form-control dropDownMenu requiredField">
                  <option value="" selected>Select Category</option>
                  <?php foreach($categories as $c): ?>
                  <option value="<?=$c['id'].'|'.$c['pless_main_category'].'|'.$c['pos_category']?>">
                    <?=$c['pless_main_category']?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <select id="supplier" name="supplier" class="form-control dropDownMenu">
                  <option value="" selected>Select Supplier</option>
                </select>
              </div>
              <div class="form-check">
                <div class="checkbox">
                  <label for="enable" class="form-check-label">
                    <input type="checkbox" id="enable" name="enable" value="enabled" class="form-check-input" checked> Enabled
                  </label>
                </div>
              </div>
              <div class="form-check">
                <div class="checkbox">
                  <label for="export_to_magento" class="form-check-label">
                    <input type="checkbox" id="export_to_magento" name="export_to_magento" value="export_to_magento"
                           class="form-check-input" checked> On WWW
                  </label>
                </div>
              </div>
              <div class="form-check">
                <div class="checkbox">
                  <label for="on_pos" class="form-check-label">
                    <input type="checkbox" id="on_pos" name='on_pos' value="on_pos" class="form-check-input" disabled> On POS
                  </label>
                </div>
              </div>
            </div>
            <!-- Pricing & Calculations -->
            <div class="col-sm-8">
              <div class="row ml-0 mr-0">
                <div class="form-group col-sm-3">
                  <label for="cost">Cost</label>
                  <input type="text" id="cost" name="cost" class="form-control doCalculations cost" placeholder="Cost">
                </div>
                <div class="form-group col-sm-3">
                  <label for="pricing_cost">Pricing Cost</label>
                  <input type="text" id="pricing_cost" name="pricing_cost" class="form-control doCalculations pricing_cost" placeholder="Pricing Cost">
                </div>
                <div class="form-group col-sm-6">
                  <label for="pricing_method">Pricing Method</label>
                  <select name="pricing_method" id="pricing_method" class="form-control doCalculations pricing_method">
                    <option value="0">Markup on cost</option>
                    <option value="1" selected>Markup on P'Cost</option>
                    <option value="2">Fixed Price</option>
                  </select>
                </div>
              </div>
              <div class="row ml-0 mr-0">
                <div class="form-group col-sm-4">
                  <label for="targetRetail">Target Retail Inc Vat</label>
                  <input type="text" id="targetRetail" name="target_retail"
                         class="form-control bg-secondary text-white whitePlaceholder calculationField doCalculations targetRetail"
                         placeholder="Target Retail Inc Vat">
                  <p class="text-muted">
                    Profit = £<span id="targetRetailProfit" class="targetRetailProfit calculationResult">0.00</span>
                  </p>
                  <p class="text-muted">
                    Markup = <span id="targetRetailPercent" class="targetRetailPercent calculationResult">0.00</span>%
                  </p>
                </div>
                <div class="form-group col-sm-4">
                  <label for="targetTrade">Target Trade Inc Vat</label>
                  <input type="text" id="targetTrade" name="target_trade"
                         class="form-control bg-secondary text-white whitePlaceholder calculationField doCalculations targetTrade"
                         placeholder="Target Trade Inc Vat">
                  <p class="text-muted">
                    Profit = £<span id="targetTradeProfit" class="targetTradeProfit calculationResult">0.00</span>
                  </p>
                  <p class="text-muted">
                    Markup = <span id="targetTradePercent" class="targetTradePercent calculationResult">0.00</span>%
                  </p>
                </div>
                <div class="form-group col-sm-4">
                  <label for="vatScheme">Vat Scheme</label>
                  <select name="tax_rate_id" id="vatScheme" class="form-control dropDownMenu doCalculations vatScheme">
                    <?php foreach($tax_rates as $tax): ?>
                    <option value="<?=$tax['tax_rate_id']?>" data-tax_rate="<?=$tax['tax_rate']?>"
                            <?= ($tax['tax_rate']==0.2) ? "selected" : '' ?>>
                      <?=$tax['name']?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row ml-0 mr-0">
                <div class="form-group col-sm-6 markupPriceMehtod">
                  <label for="retailMarkup">Retail Markup %</label>
                  <input type="text" id="retailMarkup" name="retail_markup"
                         class="form-control bg-success text-white whitePlaceholder calculationField doCalculations retailMarkup"
                         placeholder="Retail Markup %">
                  <p class="text-muted">
                    Profit = <span id="retailMarkupProfit" class="retailMarkupProfit calculationResult">0.00</span>
                  </p>
                  <p class="text-muted incVatPrice">
                    Inc Vat = <span id="retailMarkupIncVatPrice" class="retailMarkupIncVatPrice calculationResult">0.00</span>
                  </p>
                </div>
                <div class="form-group col-sm-6 markupPriceMehtod">
                  <label for="tradeMarkup">Trade Markup %</label>
                  <input type="text" id="tradeMarkup" name="trade_markup"
                         class="form-control bg-primary text-white whitePlaceholder calculationField doCalculations tradeMarkup"
                         placeholder="Trade Markup %">
                  <p class="text-muted">
                    Profit = <span id="tradeMarkupProfit" class="tradeMarkupProfit calculationResult">0.00</span>
                  </p>
                  <p class="text-muted incVatPrice">
                    Inc Vat = <span id="tradeMarkupIncVatPrice" class="tradeMarkupIncVatPrice calculationResult">0.00</span>
                  </p>
                </div>
                <div class="form-group col-sm-6 fixedPriceMethod">
                  <label for="fixedRetailPricing">Fixed Retail Pricing</label>
                  <input type="text" id="fixedRetailPricing" name="fixed_retail_pricing"
                         class="form-control bg-success text-white whitePlaceholder calculationField doCalculations fixedRetailPricing"
                         placeholder="Fixed Retail Pricing">
                  <p class="text-muted">
                    Profit = <span id="fixedRetailPricingProfit" class="fixedRetailPricingProfit calculationResult">0.00</span>
                  </p>
                </div>
                <div class="form-group col-sm-6 fixedPriceMethod">
                  <label for="fixedTradePricing">Fixed Trade Pricing</label>
                  <input type="text" id="fixedTradePricing" name="fixed_trade_pricing"
                         class="form-control bg-primary text-white whitePlaceholder calculationField doCalculations fixedTradePricing"
                         placeholder="Fixed Trade Pricing">
                  <p class="text-muted">
                    Profit = <span id="fixedTradePricingProfit" class="fixedTradePricingProfit calculationResult">0.00</span>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <hr>
          <button class="btn btn-success d-block mx-auto">Add New Item</button>
          <input type="hidden" name="retail_inc_vat" class="retailIncVat">
          <input type="hidden" name="trade_inc_vat" class="tradeIncVat">
        </form>
      </div>
    </div>
  </div>
</div>
<!-- End Add New Item Modal -->

<!-- Update Record Modal -->
<div class="modal fade" id="updateRecordPopup" tabindex="-1" aria-labelledby="exampleModalLabel"
     aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Update Record Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="updateRecordContent">
        <!-- Populated via AJAX -->
      </div>
    </div>
  </div>
</div>
<!-- End Update Record Modal -->

<input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
<input type="hidden" id="offset" value="0">
<input type="hidden" id="sortCol">

<?php require 'assets/footer.php'; ?>

<script>
document.getElementById('exportToCsv')?.addEventListener('click', function () {
    const searchQuery = document.getElementById('searchQuery').value;
    const skuSearchQuery = document.getElementById('skuSearchQuery').value;
    const enabledProds = document.getElementById('enabledProducts').checked ? 1 : 0;
    const wwwChecked   = document.getElementById('wwwFilter').checked ? 1 : 0;

    const url = `export_csv.php?searchQuery=${encodeURIComponent(searchQuery)}`
              + `&skuSearchQuery=${encodeURIComponent(skuSearchQuery)}`
              + `&enabledProducts=${enabledProds}`
              + `&wwwFilter=${wwwChecked}`;
    window.location.href = url;
});

$(document).ready(function() {

    // .chosen for dropdowns
    $(".dropDownMenu").chosen({ width:'100%' });

    // Prevent Enter in modals
    $(document).on('keypress', '#newItemForm, #updateDetailsForm', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            return false;
        }
    });

    function loadRecords(limit, offset, searchType='general') {
    const searchQuery = $("#searchQuery").val();
    const skuSearch = $("#skuSearchQuery").val();
    const enabled = $("#enabledProducts").is(':checked') ? 'true' : '';
    const www = $("#wwwFilter").is(':checked') ? 'true' : '';
    const sortCol = $("#sortCol").val();
    const selectedCategory = $("#categoryFilter").val();

    // Stock filters
    const csNeg = $("#csNegativeStock").is(':checked') ? 'true' : '';
    const csZero = $("#csZeroStock").is(':checked') ? 'true' : '';
    const csAbove = $("#csAboveStock").is(':checked') ? 'true' : '';
    const csBelow = $("#csBelowStock").is(':checked') ? 'true' : '';
    const csAVal = $("#csAboveValue").val();
    const csBVal = $("#csBelowValue").val();

    const asNeg = $("#asNegativeStock").is(':checked') ? 'true' : '';
    const asZero = $("#asZeroStock").is(':checked') ? 'true' : '';
    const asAbove = $("#asAboveStock").is(':checked') ? 'true' : '';
    const asBelow = $("#asBelowStock").is(':checked') ? 'true' : '';
    const asAVal = $("#asAboveValue").val();
    const asBVal = $("#asBelowValue").val();

    $("#spinner").show();
    $.ajax({
        type: 'POST',
        url: 'php/zlist_products.php',
        data: {
            limit,
            offset,
            search_query: searchQuery,
            sku_search_query: skuSearch,
            enabled_products: enabled,
            www_filter: www,
            sort_col: sortCol,
            search_type: searchType,
            category: selectedCategory,
            // Stock filters
            cs_negative_stock: csNeg,
            cs_zero_stock: csZero,
            cs_above_stock: csAbove,
            cs_above_value: csAVal,
            cs_below_stock: csBelow,
            cs_below_value: csBVal,
            as_negative_stock: asNeg,
            as_zero_stock: asZero,
            as_above_stock: asAbove,
            as_above_value: asAVal,
            as_below_stock: asBelow,
            as_below_value: asBVal
        }
    }).done(function(response){
        $("#spinner").hide();
        if(response.length > 0){
            $("#records").html(response);
            $("#pagination").html($("#PaginationInfoResponse").html());
            $("html, body").animate({ scrollTop: 0 }, "slow");
        }
    });
}

    // Initial load
    loadRecords($("#limit").val(), $("#offset").val());

    // Pagination
    $(document).on('click', '.recordsPage', function(e){
        e.preventDefault();
        const limit  = $(this).data("limit");
        const offset = $(this).data("offset");
        loadRecords(limit, offset);
    });

    $(document).on('submit', '.jumpToPageForm', function(e){
        e.preventDefault();
        const form   = $(this);
        const pageN  = form.find(".jumpToPage").val();
        const lastPg = form.find(".jumpToPage").attr("data-last_page");
        const limit  = form.find(".jumpToPage").attr("data-limit");
        const off    = limit * (pageN -1);
        if(parseInt(pageN) <= parseInt(lastPg)){
            loadRecords(limit, off);
        } else {
            Swal.fire('Oops...', "That page doesn't exist. Last page is " + lastPg, 'warning');
        }
    });

    // Filters
    $(".filterRecords, #csAboveValue, #csBelowValue, #asAboveValue, #asBelowValue").change(function(){
        loadRecords($("#limit").val(), $("#offset").val());
    });

    // Search on Enter
    $("#searchQuery, #skuSearchQuery").keyup(function(e){
        if(e.key === 'Enter'){
            const sType = ($(this).attr("id") === 'skuSearchQuery') ? 'sku' : 'general';
            loadRecords($("#limit").val(), $("#offset").val(), sType);
        }
    });

    // Sorting
    $(".sortCol").click(function(){
        $("#sortCol").val("ORDER BY "+$(this).data("col")+" "+$(this).data("order"));
        loadRecords($("#limit").val(), $("#offset").val());
    });

    // calculations_script
    <?php require 'php/calculations_script.php'; ?>

    // Example: batch recalc
    $("#calculateSellingPrices").click(function(e){
        e.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: "You are going to calculate selling prices!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, calculate it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $("#spinner").show();
                $.ajax({
                    url:'php/calculate_selling_prices.php'
                }).done(function(resp){
                    $("#spinner").hide();
                    if(resp.includes("_done")){
                        let msg = resp.replaceAll("_done", "");
                        Swal.fire('Calculated!', msg+' calculated successfully!', 'success');
                        $(".pagination li.page-item.active .recordsPage").click();
                    } else {
                        Swal.fire('Oops...', 'Something went wrong.', 'error');
                    }
                });
            }
        });
    });

    // Show Add Item modal
    $("#newItemBtn").click(function(e){
        e.preventDefault();
        $("#newItemForm")[0].reset();
        $(".dropDownMenu").trigger("chosen:updated");
        $(".calculationResult").html("0.00");
        $("#newItemForm").find(".markupPriceMehtod").show();
        $("#newItemForm").find(".fixedPriceMethod").hide();
        $("#newItemPopup").modal('show');
    });

    // Add New Item
    $("#newItemForm").submit(function(e){
        e.preventDefault();
        const formData = $(this).serialize();
        let valid = true;

        $.each($(this).find(".requiredField"), function(_, f){
            if(!$(f).val()) valid = false;
        });
        if(!valid){
            Swal.fire('Oops...', 'Name, manufacturer & category are required.', 'error');
            return false;
        }

        $("#spinner").show();
        $.ajax({
            type:"POST",
            url:'php/add_new_product.php',
            data: formData
        }).done(function(resp){
            $("#spinner").hide();
            if(resp === 'added'){
                Swal.fire('Added!', 'Product added successfully.', 'success');
                $("#newItemPopup").modal("hide");
                $("#newItemForm")[0].reset();
                $(".pagination li.page-item.active .recordsPage").click();
                $(".dropDownMenu").trigger("chosen:updated");
                $(".calculationResult").html("0.00");
                $(".pricing_method").trigger('change');
            } else {
                Swal.fire('Oops...', 'Something went wrong.', 'error');
            }
        });
    });

    // Show Update modal
    $(document).on('click', '.updateRecord', function(e){
        e.preventDefault();
        const recordSKU = $(this).data("sku");
        $("#spinner").show();
        $.ajax({
            type:'POST',
            url:'php/get_product_details.php',
            data:{ sku: recordSKU }
        }).done(function(response){
            $("#spinner").hide();
            if(response.length > 0){
                $("#updateRecordContent").html(response);
                $("#updateRecordPopup").modal('show');
                $(".dropDownMenu").chosen({ width:'100%' });
                $('#updateRecordPopup').on('shown.bs.modal', function(){
                    $(".pricing_method").trigger("change");
                    calculations();
                });
            } else {
                Swal.fire('Oops...', 'Something went wrong.', 'error');
            }
        });
    });

    // Submit Update
    $(document).on("submit", "#updateDetailsForm", function(e){
        e.preventDefault();
        const formData = $(this).serialize();
        let valid = true;

        $.each($(this).find(".requiredField"), function(_, f){
            if(!$(f).val()) valid = false;
        });
        if(!valid){
            Swal.fire('Oops...', 'Name, manufacturer, category are required.', 'error');
            return false;
        }

        $("#spinner").show();
        $.ajax({
            type:"POST",
            url:'php/update_product_details.php',
            data: formData
        }).done(function(resp){
            $("#spinner").hide();
            if(resp === 'updated'){
                Swal.fire('Updated!', 'Product updated successfully.', 'success');
                $("#updateRecordPopup").modal("hide");
                $(".pagination li.page-item.active .recordsPage").click();
            } else {
                Swal.fire('Oops...', 'Something went wrong.', 'error');
            }
        });
    });

    // Export to Magento
    $("#exportToMagento").click(function(){
        const searchQuery = $("#searchQuery").val();
        const skuSearch   = $("#skuSearchQuery").val();
        const enabled     = $("#enabledProducts").is(':checked') ? 1 : 0;
        const www         = $("#wwwFilter").is(':checked') ? 1 : 0;

        window.location.href = `php/export_magento.php?searchQuery=${encodeURIComponent(searchQuery)}`
                             + `&skuSearchQuery=${encodeURIComponent(skuSearch)}`
                             + `&enabledProducts=${enabled}`
                             + `&wwwFilter=${www}`;
    });

    // Export to POS
    $("#exportToPos").click(function(){
        const searchQuery = $("#searchQuery").val();
        const skuSearch   = $("#skuSearchQuery").val();
        const enabled     = $("#enabledProducts").is(':checked') ? 1 : 0;
        const www         = $("#wwwFilter").is(':checked') ? 1 : 0;

        window.location.href = `php/export_pos.php?searchQuery=${encodeURIComponent(searchQuery)}`
                             + `&skuSearchQuery=${encodeURIComponent(skuSearch)}`
                             + `&enabledProducts=${enabled}`
                             + `&wwwFilter=${www}`;
    });
});
$(document).ready(function() {
    // Populate category dropdown
    function populateCategories() {
        $.ajax({
            type: 'GET',
            url: 'ajax/get_categories.php',
            success: function(response) {
                try {
                    const categories = JSON.parse(response);
                    const dropdown = $('#categoryFilter');
                    dropdown.empty();
                    dropdown.append('<option value="">All Categories</option>');
                    
                    // Create a map to group by main category
                    const categoryMap = {};
                    categories.forEach(function(category) {
                        if (!categoryMap[category.pless_main_category]) {
                            categoryMap[category.pless_main_category] = [];
                        }
                        categoryMap[category.pless_main_category].push(category.pos_category);
                    });

                    // Create optgroups for each main category
                    Object.keys(categoryMap).sort().forEach(function(mainCategory) {
                        const group = $('<optgroup>').attr('label', mainCategory);
                        categoryMap[mainCategory].forEach(function(posCategory) {
                            group.append($('<option>')
                                .val(posCategory)
                                .text(posCategory));
                        });
                        dropdown.append(group);
                    });

                    // Initialize chosen
                    dropdown.chosen({
                        width: '200px',
                        search_contains: true,
                        placeholder_text_single: 'Select Category'
                    });
                } catch (error) {
                    console.error('Error parsing categories:', error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching categories:', error);
            }
        });
    }

    // Initialize categories
    populateCategories();
  $(document).ready(function() {
    // Handle the enable editing checkbox
    $('#enableEditing').change(function() {
        if ($(this).prop('checked')) {
            Swal.fire({
                title: 'Warning',
                text: 'You are enabling editing of sensitive data. Please be careful with your changes.',
                icon: 'warning',
                confirmButtonText: 'I Understand'
            }).then(() => {
                $('.enable-toggle, .www-toggle').prop('disabled', false);
            });
        } else {
            $('.enable-toggle, .www-toggle').prop('disabled', true);
        }
    });

    // Handle enable/disable toggle
    $(document).on('change', '.enable-toggle', function() {
        if (!$('#enableEditing').prop('checked')) {
            $(this).prop('checked', !$(this).prop('checked')); // Revert the change
            return false; // Exit if editing is not enabled
        }
        
        const $checkbox = $(this);
        const sku = $checkbox.data('sku');
        const enabled = $checkbox.prop('checked') ? 'y' : 'n';
        
        $("#spinner").show();
        $.ajax({
            type: 'POST',
            url: '/update_product_status.php',
            data: {
                sku: sku,
                field: 'enable',
                value: enabled
            },
            success: function(response) {
                $("#spinner").hide();
                if (response === 'updated') {
                    Swal.fire({
                        title: 'Updated!',
                        text: 'Product status updated successfully',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else if (response === 'unauthorized') {
                    Swal.fire('Error', 'You are not authorized to make this change', 'error');
                    $checkbox.prop('checked', !$checkbox.prop('checked')); // Revert
                } else {
                    Swal.fire('Error', 'Failed to update product status', 'error');
                    $checkbox.prop('checked', !$checkbox.prop('checked')); // Revert
                }
            },
            error: function() {
                $("#spinner").hide();
                Swal.fire('Error', 'Failed to update product status', 'error');
                $checkbox.prop('checked', !$checkbox.prop('checked')); // Revert
            }
        });
    });

    // Handle WWW (export to magento) toggle
    $(document).on('change', '.www-toggle', function() {
        if (!$('#enableEditing').prop('checked')) {
            $(this).prop('checked', !$(this).prop('checked')); // Revert the change
            return false; // Exit if editing is not enabled
        }
        
        const $checkbox = $(this);
        const sku = $checkbox.data('sku');
        const exportToMagento = $checkbox.prop('checked') ? 'y' : 'n';
        
        $("#spinner").show();
        $.ajax({
            type: 'POST',
            url: 'update_product_status.php',
            data: {
                sku: sku,
                field: 'export_to_magento',
                value: exportToMagento
            },
            success: function(response) {
                $("#spinner").hide();
                if (response === 'updated') {
                    Swal.fire({
                        title: 'Updated!',
                        text: 'WWW status updated successfully',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else if (response === 'unauthorized') {
                    Swal.fire('Error', 'You are not authorized to make this change', 'error');
                    $checkbox.prop('checked', !$checkbox.prop('checked')); // Revert
                } else {
                    Swal.fire('Error', 'Failed to update WWW status', 'error');
                    $checkbox.prop('checked', !$checkbox.prop('checked')); // Revert
                }
            },
            error: function() {
                $("#spinner").hide();
                Swal.fire('Error', 'Failed to update WWW status', 'error');
                $checkbox.prop('checked', !$checkbox.prop('checked')); // Revert
            }
        });
    });
});

    // Add category filter to loadRecords function
    $('#categoryFilter').on('change', function() {
        loadRecords($("#limit").val(), $("#offset").val());
    });
});

</script>
