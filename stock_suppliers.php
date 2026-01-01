<?php 
ob_start();
session_start();
$page_title = 'Stock Suppliers Control Panel';
require 'assets/header.php';

// Ensure user is logged in
if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
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

// Check if user has permission for this page using the permission system
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'stock_suppliers'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// settings
require 'php/settings.php';
?>
<style>
  @media (min-width: 992px){
    .modal-lg {
        max-width: 1000px;
    }
  }
  
  .incVatPrice, .fixedPriceMethod {
    display:none;
  }
  .form-group label {
    font-size: 10px;
    margin-bottom: 0;
  }
  
  /* Excel-like styling with tight padding and proper column widths */
  .table-compact {
    font-size: 11px !important;
    line-height: 1.2 !important;
    border-collapse: collapse !important;
    background: #ffffff !important;
    min-width: 1600px !important; /* Ensure minimum width for all columns */
    width: auto !important; /* Allow table to expand */
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
  }
  
  .table-compact th {
    font-size: 10px !important;
    padding: 4px 6px !important;
    vertical-align: middle !important;
    text-align: center !important;
    border: 1px solid #d1d5db !important;
    background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
    color: white !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.3px !important;
    white-space: nowrap !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    height: 32px !important;
  }
  
  .table-compact td {
    font-size: 10px !important;
    padding: 2px 4px !important;
    vertical-align: top !important;
    border: 1px solid #d1d5db !important;
    white-space: nowrap !important;
    background-color: #ffffff !important;
    transition: background-color 0.2s ease !important;
    height: auto !important;
    line-height: 1.2 !important;
  }
  
  /* Specific column widths for Excel-like layout */
  .table-compact th:nth-child(1), .table-compact td:nth-child(1) { width: 90px !important; } /* SKU */
  .table-compact th:nth-child(2), .table-compact td:nth-child(2) { width: 240px !important; } /* Name - Wide for 30+ chars */
  .table-compact th:nth-child(3), .table-compact td:nth-child(3) { width: 100px !important; } /* Category */
  .table-compact th:nth-child(4), .table-compact td:nth-child(4) { width: 100px !important; } /* Manufacturer */
  .table-compact th:nth-child(5), .table-compact td:nth-child(5) { width: 90px !important; } /* MPN */
  .table-compact th:nth-child(6), .table-compact td:nth-child(6) { width: 90px !important; } /* EAN */
  .table-compact th:nth-child(7), .table-compact td:nth-child(7) { width: 50px !important; } /* Qty */
  .table-compact th:nth-child(8), .table-compact td:nth-child(8) { width: 70px !important; } /* Cost */
  .table-compact th:nth-child(9), .table-compact td:nth-child(9) { width: 75px !important; } /* Cost Inc VAT */
  .table-compact th:nth-child(10), .table-compact td:nth-child(10) { width: 70px !important; } /* +25% */
  .table-compact th:nth-child(11), .table-compact td:nth-child(11) { width: 70px !important; } /* +20% */
  .table-compact th:nth-child(12), .table-compact td:nth-child(12) { width: 70px !important; } /* +15% */
  .table-compact th:nth-child(13), .table-compact td:nth-child(13) { width: 70px !important; } /* +12% */
  .table-compact th:nth-child(14), .table-compact td:nth-child(14) { width: 100px !important; } /* Supplier */
  .table-compact th:nth-child(15), .table-compact td:nth-child(15) { width: 90px !important; } /* Supplier SKU */
  .table-compact th:nth-child(16), .table-compact td:nth-child(16) { width: 110px !important; } /* Time Recorded */
  .table-compact th:nth-child(17), .table-compact td:nth-child(17) { width: 60px !important; } /* Product */
  
  /* Special handling for name column - allow text wrapping for long names */
  .table-compact td:nth-child(2) {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    hyphens: auto !important;
    max-width: 240px !important;
    min-width: 240px !important;
    vertical-align: top !important;
    line-height: 1.3 !important;
  }
  
  
  .table-compact tbody tr:hover {
    background-color: #f8fafc !important;
  }
  
  .table-compact tbody tr:hover td {
    background-color: #f8fafc !important;
  }
  
  .table-compact tbody tr:nth-child(even) {
    background-color: #f9fafb !important;
  }
  
  .table-compact tbody tr:nth-child(even) td {
    background-color: #f9fafb !important;
  }
  
  .table-compact .sortWrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 2px;
  }
  
  .table-compact .sortIcon {
    font-size: 8px !important;
    margin: 0 !important;
    line-height: 1 !important;
    cursor: pointer !important;
    opacity: 0.8 !important;
    color: white !important;
  }
  
  .table-compact .sortIcon:hover {
    opacity: 1 !important;
  }
  
  .table-compact .cost-column {
    text-align: right !important;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    font-weight: 500 !important;
    font-size: 10px !important;
  }
  
  /* Corporate color-coded calculated columns with tight spacing */
  .table-compact .calculated-column {
    text-align: right !important;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    font-weight: 500 !important;
    border-left: 2px solid transparent !important;
    font-size: 10px !important;
    padding: 2px 3px !important;
  }
  
  .table-compact .cost-inc-vat {
    background-color: #eff6ff !important;
    border-left-color: #2563eb !important;
  }
  
  .table-compact .margin-25 {
    background-color: #fffbeb !important;
    border-left-color: #d97706 !important;
  }
  
  .table-compact .margin-20 {
    background-color: #f5f3ff !important;
    border-left-color: #7c3aed !important;
  }
  
  .table-compact .margin-15 {
    background-color: #f0fdf4 !important;
    border-left-color: #16a34a !important;
  }
  
  .table-compact .margin-12 {
    background-color: #fefce8 !important;
    border-left-color: #ca8a04 !important;
  }
  
  /* Corporate header styling for calculated columns */
  .table-compact th.calc-header {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
  }
  
  .table-compact th.calc-header-vat {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
  }
  
  /* Cheapest supplier highlighting */
  .table-compact tbody tr.cheapest-supplier {
    background-color: #dcfce7 !important;
    border-left: 3px solid #16a34a !important;
  }
  
  .table-compact tbody tr.cheapest-supplier td {
    background-color: #dcfce7 !important;
  }
  
  .table-compact tbody tr.cheapest-supplier:hover {
    background-color: #bbf7d0 !important;
  }
  
  .table-compact tbody tr.cheapest-supplier:hover td {
    background-color: #bbf7d0 !important;
  }
  
  /* Add a badge/indicator for cheapest items */
  .table-compact tbody tr.cheapest-supplier td:first-child::before {
    content: '🏆';
    margin-right: 4px;
    font-size: 11px;
  }
  
  /* CRITICAL: Container and layout fixes for horizontal scrolling */
  .container-fluid {
    padding: 8px !important;
    max-width: none !important; /* Remove width restriction */
    width: 100% !important;
    overflow-x: visible !important; /* Allow horizontal overflow */
  }
  
  .page-content--bgf7 {
    padding: 8px !important;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
    overflow-x: visible !important; /* Allow horizontal overflow */
  }
  
  .welcome {
    padding: 8px 0 !important;
  }
  
  .title-4 {
    font-size: 20px !important;
    margin-bottom: 8px !important;
    color: #2c3e50 !important;
    font-weight: 600 !important;
  }
  
  .table-data__tool {
    padding: 12px !important;
    margin-bottom: 10px !important;
    background: #f8fafc !important;
    border-radius: 6px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
    border: 1px solid #e2e8f0 !important;
    width: 100% !important;
    overflow: visible !important; /* Ensure tool area doesn't clip */
  }
  
  /* CRITICAL: Table responsive container fixes for Excel-like layout */
  .table-responsive {
    background: white !important;
    border-radius: 6px !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08) !important;
    padding: 8px !important;
    overflow-x: auto !important; /* Enable horizontal scrolling */
    overflow-y: visible !important;
    max-width: 100% !important;
    width: 100% !important;
    /* Remove any height restrictions that might clip content */
    max-height: none !important;
    position: relative !important;
    border: 1px solid #d1d5db !important;
  }
  
  .table-responsive-data2 {
    overflow-x: auto !important;
    overflow-y: visible !important;
    max-width: 100% !important;
    width: 100% !important;
    position: relative !important;
  }
  
  /* Ensure the main row doesn't restrict width */
  .row {
    overflow-x: visible !important;
    width: 100% !important;
  }
  
  .col-md-12 {
    overflow-x: visible !important;
    width: 100% !important;
    max-width: none !important;
  }
  
  /* Page wrapper fixes */
  .page-wrapper {
    overflow-x: visible !important;
    width: 100% !important;
  }
  
  /* Section fixes for tight layout */
  section.p-t-20 {
    padding-top: 8px !important;
    overflow-x: visible !important;
    width: 100% !important;
  }
  
  /* Centered Search Container with tighter spacing */
  .search-container {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    width: 100% !important;
  }
  
  .search-wrapper {
    position: relative !important;
    width: 100% !important;
    max-width: 500px !important;
  }
  
  .search-icon {
    position: absolute !important;
    left: 12px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    color: #64748b !important;
    font-size: 14px !important;
    z-index: 2 !important;
    transition: color 0.2s ease !important;
  }
  
  .modern-search-input {
    width: 100% !important;
    height: 36px !important;
    padding: 0 16px 0 40px !important;
    font-size: 13px !important;
    font-weight: 400 !important;
    border: 2px solid #cbd5e1 !important;
    border-radius: 4px !important;
    background: #ffffff !important;
    transition: all 0.2s ease !important;
    outline: none !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
    color: #1e293b !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
  }
  
  .modern-search-input::placeholder {
    color: #64748b !important;
    font-style: normal !important;
  }
  
  .modern-search-input:focus {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1), 0 2px 4px rgba(0,0,0,0.05) !important;
    background: #ffffff !important;
  }
  
  .modern-search-input:focus ~ .search-icon {
    color: #2563eb !important;
  }
  
  .search-underline {
    display: none !important;
  }
  
  /* Filter checkbox styling */
  .filter-label {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    color: #334155 !important;
    cursor: pointer !important;
    user-select: none !important;
    transition: color 0.2s ease !important;
  }
  
  .filter-label:hover {
    color: #1e293b !important;
  }
  
  .filter-label input[type="checkbox"] {
    width: 16px !important;
    height: 16px !important;
    cursor: pointer !important;
    accent-color: #2563eb !important;
  }
  
  /* Optional: Subtle scroll indicator for Excel-like experience */
  .table-responsive::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 15px;
    height: 100%;
    background: linear-gradient(to left, rgba(0,0,0,0.08), transparent);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
  }
  
  .table-responsive:hover::after {
    opacity: 1;
  }
  
  /* Responsive adjustments for Excel-like tight layout */
  @media (max-width: 768px) {
    .search-wrapper {
      max-width: 100% !important;
    }
    
    .modern-search-input {
      height: 32px !important;
      font-size: 12px !important;
      padding: 0 12px 0 36px !important;
    }
    
    .search-icon {
      left: 10px !important;
      font-size: 12px !important;
    }
    
    /* Mobile: Allow horizontal scrolling with tighter spacing */
    .table-responsive {
      padding: 4px !important;
    }
    
    .container-fluid {
      padding: 4px !important;
    }
    
    .table-data__tool {
      padding: 8px !important;
      margin-bottom: 6px !important;
    }
    
    .table-compact th {
      padding: 3px 4px !important;
      font-size: 9px !important;
      height: 28px !important;
    }
    
    .table-compact td {
      padding: 1px 3px !important;
      font-size: 9px !important;
    }
  }
  
  /* Additional mobile fixes for very small screens */
  @media (max-width: 576px) {
    .table-compact {
      min-width: 1400px !important; /* Ensure table is wide enough on mobile */
    }
    
    /* Maintain column widths on mobile for consistency */
    .table-compact th:nth-child(2), .table-compact td:nth-child(2) { 
      width: 200px !important; /* Slightly smaller name column on mobile */
    }
  }
</style>
<link rel="stylesheet" href="assets/css/tables.css">

    <div class="page-wrapper">
       <?php require 'assets/navbar.php' ?>
        <!-- PAGE CONTENT-->
        <div class="page-content--bgf7">
            <!-- BREADCRUMB-->
            <section class="au-breadcrumb2 p-0 pt-1"></section>
            <!-- END BREADCRUMB-->

            <!-- WELCOME-->
            <section class="welcome p-t-5">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12">
                            <h1 class="title-4"><?=$page_title?></h1>
                            <hr class="line-seprate" style="margin: 5px 0;">
                        </div>
                    </div>
                </div>
            </section>
            <!-- END WELCOME-->
            
            <!-- DATA TABLE-->
            <section class="p-t-20">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="table-data__tool">
                                <div class="search-container">
                                  <div class="search-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="modern-search-input" id="searchQuery" placeholder="Search by SKU, Name, Category, Manufacturer, EAN, or Supplier...">
                                    <div class="search-underline"></div>
                                  </div>
                                </div>
                                <div class="search-container" style="margin-top: 8px;">
                                  <label class="filter-label">
                                    <input type="checkbox" id="qtyFilter">
                                    <span>Show only items with Qty &gt; 0</span>
                                  </label>
                                </div>
                                <div class="search-container" style="margin-top: 6px;">
                                  <small style="font-size: 11px; color: #16a34a; display: flex; align-items: center; gap: 4px;">
                                    <span style="display: inline-block; width: 20px; height: 14px; background: #dcfce7; border: 1px solid #16a34a; border-radius: 2px;"></span>
                                    Green highlight = Cheapest supplier for products with matching EAN
                                  </small>
                                </div>
                            </div>
                            <div class="table-responsive table-responsive-data2">
                                <table class="table table-condensed table-striped table-bordered table-hover table-sm table-compact">
                                    <thead>
                                        <tr>
                                            <!--<th>Select</th>-->
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="sku" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="sku" data-order="DESC"></i>
                                              </div>
                                              SKU</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="name" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="name" data-order="DESC"></i>
                                              </div>
                                              Name</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="category" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="category" data-order="DESC"></i>
                                              </div>
                                              Category</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="manufacturer" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="manufacturer" data-order="DESC"></i>
                                              </div>
                                              Manufacturer</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="mpn" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="mpn" data-order="DESC"></i>
                                              </div>
                                              MPN</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="ean" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="ean" data-order="DESC"></i>
                                              </div>
                                              EAN</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="qty" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="qty" data-order="DESC"></i>
                                              </div>
                                              Qty</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="cost" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="cost" data-order="DESC"></i>
                                              </div>
                                              Cost</th>
                                            <th class="calc-header-vat">Cost Inc VAT</th>
                                            <th class="calc-header">+25%</th>
                                            <th class="calc-header">+20%</th>
                                            <th class="calc-header">+15%</th>
                                            <th class="calc-header">+12%</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="supplier" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="supplier" data-order="DESC"></i>
                                              </div>
                                              Supplier</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="supplier_sku" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="supplier_sku" data-order="DESC"></i>
                                              </div>
                                              Supplier SKU</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="time_recorded" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="time_recorded" data-order="DESC"></i>
                                              </div>
                                              Time Recorded</th>
                                            <th>Product</th>
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
            <!-- END DATA TABLE-->

            <!-- COPYRIGHT-->
            <section class="p-t-20 p-b-10">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="copyright">
                                <p style="font-size: 10px;">Copyright © <?=date("Y")?> <?=$website_name?>. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- END COPYRIGHT-->
        </div>

    </div>

<input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
<input type="hidden" id="offset" value="0">
<input type="hidden" id="sortCol">

<!-- Product Popup -->
<div class="modal fade" id="productPopup" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Product Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="productContent"></div>
    </div>
  </div>
</div>


<?php require 'assets/footer.php'; ?>
<script>
  $(document).ready(function(){
      function loadRecords(limit, offset, searchType='general'){
        var searchQuery = $("#searchQuery").val(),
            sortCol = $("#sortCol").val(),
            qtyFilter = $("#qtyFilter").is(":checked") ? 1 : 0;
        $("#spinner").show();
        $.ajax({
          type:'POST',
          url:'php/list_stock_suppliers.php',
          data:{limit:limit, offset:offset, search_query:searchQuery, sort_col:sortCol, qty_filter:qtyFilter}
        }).done(function(response){
          $("#spinner").hide();
          if(response.length>0){
            $("#records").html(response);
            
            // Calculate new columns after loading data
            calculateColumns();
            
            $('[data-toggle="tooltip"]').tooltip();
            $(".table-data__tool").width($(".table").width())
            $("#pagination").html($("#PaginationInfoResponse").html());
            $("html, body").animate({ scrollTop: 0 }, "slow");
          }
        });
      }
      
      // Function to round up to nearest 50p
      function roundUpToNearest50p(value) {
        return Math.ceil(value * 2) / 2;
      }
      
      // Function to calculate the new columns
      function calculateColumns() {
        $("#records tr").each(function() {
          var row = $(this);
          var costCell = row.find('td').eq(7); // Cost column (8th column, 0-indexed)
          var cost = parseFloat(costCell.text().replace('£', '').replace(',', '')) || 0;
          
          if (cost > 0) {
            // Cost Inc VAT (cost * 1.2) - keep as precise calculation for reference
            var costIncVat = (cost * 1.2).toFixed(2);
            
            // +25% ((cost * 1.25) * 1.2) - rounded up to nearest 50p
            var plus25Percent = roundUpToNearest50p((cost * 1.25) * 1.2).toFixed(2);
            
            // +20% ((cost * 1.20) * 1.2) - rounded up to nearest 50p  
            var plus20Percent = roundUpToNearest50p((cost * 1.20) * 1.2).toFixed(2);
            
            // +15% ((cost * 1.15) * 1.2) - rounded up to nearest 50p
            var plus15Percent = roundUpToNearest50p((cost * 1.15) * 1.2).toFixed(2);
            
            // +12% ((cost * 1.12) * 1.2) - rounded up to nearest 50p
            var plus12Percent = roundUpToNearest50p((cost * 1.12) * 1.2).toFixed(2);
            
            // Insert calculated columns after cost column in the correct order
            costCell.after('<td class="cost-column calculated-column cost-inc-vat">£' + costIncVat + '</td>');
            costCell.next().after('<td class="cost-column calculated-column margin-25">£' + plus25Percent + '</td>');
            costCell.next().next().after('<td class="cost-column calculated-column margin-20">£' + plus20Percent + '</td>');
            costCell.next().next().next().after('<td class="cost-column calculated-column margin-15">£' + plus15Percent + '</td>');
            costCell.next().next().next().next().after('<td class="cost-column calculated-column margin-12">£' + plus12Percent + '</td>');
          } else {
            // Insert empty cells if no cost
            costCell.after('<td class="cost-column calculated-column cost-inc-vat">£0.00</td>');
            costCell.next().after('<td class="cost-column calculated-column margin-25">£0.00</td>');
            costCell.next().next().after('<td class="cost-column calculated-column margin-20">£0.00</td>');
            costCell.next().next().next().after('<td class="cost-column calculated-column margin-15">£0.00</td>');
            costCell.next().next().next().next().after('<td class="cost-column calculated-column margin-12">£0.00</td>');
          }
        });
      }
      
	    loadRecords($("#limit").val() , $("#offset").val());

	    $(document).on('click' , '.recordsPage' , function(e){
	      e.preventDefault();
	      var limit = $(this).attr("data-limit"),
	        offset = $(this).attr("data-offset");
	      loadRecords(limit, offset);
	    })

	    $(document).on('submit' , '.jumpToPageForm' , function(e){
	      e.preventDefault();
	      var form = $(this) ,
	        pageNum = form.find(".jumpToPage").val(),
	        lastPage = form.find(".jumpToPage").attr("data-last_page"),
	        limit = form.find(".jumpToPage").attr("data-limit"),
	        offset = limit * (pageNum -1);
	      if(parseInt(pageNum)<=parseInt(lastPage)){
	        loadRecords(limit, offset);
	      }else{
	        Swal.fire(
	          'Oops...' ,
	          "The page number you have entered is not exist, the last page number is "+lastPage,
	          'warning'
	          )
	      }
	    })

	    $("#searchQuery").keyup(function(e){
        e.preventDefault();
        if(e.key === 'Enter' || e.keyCode === 13) {
          loadRecords($("#limit").val() , $("#offset").val());
        }
      })
      
      // Event listener for quantity filter checkbox
      $("#qtyFilter").change(function(e){
        loadRecords($("#limit").val(), $("#offset").val());
      })
    
    $(".sortCol").click(function(e){
      $("#sortCol").val("ORDER BY "+$(this).data("col")+" "+$(this).data("order"));
      loadRecords($("#limit").val() , $("#offset").val());
    })
    
    function processRecords(itemsIDs){
      $("#spinner").show();
      $.ajax({
        type:'POST',
        url:'php/main_database_process.php',
        data:{items_ids:itemsIDs}
      }).done(function(response){
        console.log(response);
        $("#spinner").hide();
        if(response.length>0){
          Swal.fire(
              'Processed!',
              response,
              'success'
            )
          $(".pagination li.page-item.active .recordsPage").click();
        }else{
          Swal.fire(
              'Oops...',
              'Something went wrong, please try again.',
              'error'
            )
        }
      })
    }
    
    $(document).on('click' , '.updateRecord' , function(e){
      e.preventDefault();
      var itemsIDs = [$(this).attr("data-id")];
      processRecords(itemsIDs);
    })
    $("#checkTable").click(function(e){
      e.preventDefault();
      var checkedItems = [];
      $.each($(".recordCheckBox") , function (index,value) {
        if ($(value)[0].checked) {
          checkedItems.push($(value).attr("data-id"));
        }
      });
      
      if(checkedItems.length>0){
        processRecords(checkedItems);
      }
      
    })
    
    $(document).on('click' , '.updateProduct' , function(e){
      e.preventDefault();
      var id = $(this).attr("data-id");
      $("#spinner").show();
      $.ajax({
        type:'POST',
        url:'php/get_product_popup.php',
        data:{id:id}
      }).done(function(response){
        console.log(response);
        $("#spinner").hide();
        if(response.length>0){
          $("#productContent").html(response);
          $("#productPopup").modal("show");
          $(".dropDownMenu").chosen({
            width:'100%'
          })
          $('#updateRecordPopup').on('shown.bs.modal', function (event) {
            $(".pricing_method").trigger("change")
            calculations();
          })
        }else{
          Swal.fire(
              'Oops...',
              'Something went wrong, please try again.',
              'error'
            )
        }
      })
    })
    
    <?php require 'php/calculations_script.php'; ?>
    
    $(document).on("submit" , '#updateDetailsForm' , function(e){
      e.preventDefault();
      var form = $(this),
				formData = form.serialize(),
				validToSubmit = true;

			$.each(form.find(".requiredField") , function(index , value){
				if($(value).val()==null || $(value).val().length==0){
					validToSubmit = false;
				}
			})

			if(!validToSubmit){
				Swal.fire(
					'Oops...',
					'Name, manufacturer and categorys are required.',
					'error'
					)
				return false;
			}
      
      $("#spinner").show();
      
      $.ajax({
        type:"POST",
        url:'php/process_product_stock_suppliers.php',
        data:formData
      }).done(function(response){
        console.log(response);
        $("#spinner").hide();
        if(response=='updated'){
          Swal.fire(
              'Updated!',
              'Product updated successfully.',
              'success'
            )
          $("#productPopup").modal("hide");
          $(".pagination li.page-item.active .recordsPage").click();
        }else{
          Swal.fire(
              'Oops...',
              'Something went wrong, please try again.',
              'error'
            )
        }
      })
    })

  })
</script>