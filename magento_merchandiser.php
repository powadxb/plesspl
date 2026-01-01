<?php 
ob_start();
session_start();
$page_title = 'Magento Merchandiser';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['dins_user_id'];

require __DIR__.'/php/bootstrap.php';
require 'assets/header.php';

// Add the permission checking function
if (!function_exists('hasPermission')) {
    function hasPermission($user_id, $permission_name, $DB) {
        $result = $DB->query(
            "SELECT COUNT(*) as count FROM user_permissions WHERE user_id = ? AND page = ? AND has_access = 1", 
            [$user_id, $permission_name]
        );
        return $result[0]['count'] > 0;
    }
}

$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? null;

// Check if user exists
if (empty($user_details)) {
    header("Location: login.php");
    exit();
}

// Check access permissions
$has_access = false;
if ($user_details['admin'] >= 1) {
    $has_access = true;
} else {
    $has_access = hasPermission($user_details['id'], 'magento_merchandiser', $DB);
}

if (!$has_access) {
    header('Location: no_access.php');
    exit;
}

// Handle merchandiser message update (for admin level >= 2)
if ($_POST && isset($_POST['update_merchandiser_message']) && $user_details['admin'] >= 2) {
    $message_content = trim($_POST['merchandiser_message']);
    // Limit to 500 characters
    $message_content = substr($message_content, 0, 500);
    
    // Update or insert the setting
    $existing = $DB->query("SELECT * FROM settings WHERE setting_key = 'merchandiser_message'");
    if (count($existing) > 0) {
        $DB->query("UPDATE settings SET setting_value = ? WHERE setting_key = 'merchandiser_message'", [$message_content]);
    } else {
        $DB->query("INSERT INTO settings (setting_key, setting_value) VALUES ('merchandiser_message', ?)", [$message_content]);
    }
    
    $message = 'Merchandiser message updated successfully!';
}

// Handle merchandiser question submission
$message = '';
if ($_POST && isset($_POST['submit_question'])) {
    $question = trim($_POST['merchandiser_question']);
    $product_sku = trim($_POST['product_sku']);
    
    if (!empty($question)) {
        $DB->query("INSERT INTO merchandiser_questions (user_id, product_sku, question, created_at, status) VALUES (?, ?, ?, NOW(), 'pending')", 
                   [$user_id, $product_sku, $question]);
        $message = 'Question submitted successfully!';
    }
}

// Get current merchandiser message
$merchandiser_message_result = $DB->query("SELECT setting_value FROM settings WHERE setting_key = 'merchandiser_message'");
$merchandiser_message = $merchandiser_message_result ? ($merchandiser_message_result[0]['setting_value'] ?? '') : '';

// Get search parameters from URL or use defaults
$search_query = '';
$sku_search_query = '';
$enabled_products = 1;
$magento_enabled = 1;

// tax_rates for display
$tax_rates = $DB->query(" SELECT * FROM tax_rates ORDER BY name ASC");
$tax_lookup = [];
foreach($tax_rates as $rate) {
    $tax_lookup[$rate['tax_rate_id']] = $rate['name'];
}

// master_categories
$categories = $DB->query(" SELECT * FROM master_categories ORDER BY pos_category ASC");

// settings
require 'php/settings.php';
?>

<!-- External Stylesheets -->
<link rel="stylesheet" href="assets/css/tables.css?<?=time()?>">
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/products.css?<?=time()?>">

<div class="page-wrapper">
    <?php require 'assets/navbar.php' ?>
    
    <div class="page-content--bgf7">
        <section class="au-breadcrumb2 p-0 pt-4"></section>

        <section class="welcome p-t-10">
            <div class="container-fluid" style="padding: 0 15px;">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="title-4"><?=$page_title?></h1>
                        <hr class="line-seprate">
                    </div>
                </div>
            </div>
        </section>

        <!-- Question Submission Success Message -->
        <?php if (!empty($message)): ?>
        <section class="p-t-10">
            <div class="container-fluid" style="padding: 0 15px;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-success"><?= $message ?></div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Merchandiser Message Box -->
        <?php if (!empty($merchandiser_message) || $user_details['admin'] >= 2): ?>
        <section class="p-t-10">
            <div class="container-fluid" style="padding: 0 15px;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="merchandiser-message-box">
                            <?php if ($user_details['admin'] >= 2): ?>
                                <form method="POST" style="margin: 0;">
                                    <textarea name="merchandiser_message" 
                                              class="merchandiser-message-textarea" 
                                              placeholder="Enter a message for merchandisers (500 characters max)..."
                                              maxlength="500"><?= htmlspecialchars($merchandiser_message) ?></textarea>
                                    <input type="hidden" name="update_merchandiser_message" value="1">
                                    <div class="message-controls">
                                        <small class="char-counter">
                                            <span id="char-count"><?= strlen($merchandiser_message) ?></span>/500 characters
                                        </small>
                                        <button type="submit" class="btn btn-sm btn-primary">Update Message</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="merchandiser-message-display">
                                    <?= nl2br(htmlspecialchars($merchandiser_message)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <section class="p-t-20">
            <div class="container-fluid" style="padding: 0 15px;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-data__tool" style="max-width: 100%; overflow: hidden; display: flex; justify-content: space-between; align-items: center; padding: 10px 0;">
                            <!-- Left side - Filter options -->
                            <div class="table-data__tool-left" style="display: flex; gap: 20px;">
                                <div class="form-check">
                                    <label for="enabledProducts" class="form-check-label">
                                        <input type="checkbox" id="enabledProducts" value="enabled" 
                                               class="form-check-input filterRecords" <?= $enabled_products ? 'checked' : '' ?>> Active Only
                                    </label>
                                </div>
                                <div class="form-check">
                                    <label for="magentoEnabled" class="form-check-label">
                                        <input type="checkbox" id="magentoEnabled" value="enabled" 
                                               class="form-check-input filterRecords" <?= $magento_enabled ? 'checked' : '' ?>> Magento Enabled
                                    </label>
                                </div>
                            </div>

                            <!-- Search boxes - on same row -->
                            <div class="table-data__tool-search" style="display: flex; gap: 30px; align-items: center;">
                                <div class="search-sku">
                                    <input type="text" class="au-input au-input--xs" id="skuSearchQuery" 
                                           placeholder="Search SKU" style="background-color: #fed8b1; width: 200px;">
                                </div>
                                <div class="search-main">
                                    <input type="text" class="au-input au-input--xs" id="searchQuery" 
                                           placeholder="Search Products" style="width: 400px;">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive table-responsive-data2" style="overflow-x: auto; white-space: nowrap;">
                            <!-- Add spinner like your existing system -->
                            <div id="spinner" style="display: none; text-align: center; padding: 20px;">
                                <i class="fas fa-spinner fa-spin fa-2x"></i>
                                <div>Loading...</div>
                            </div>
                            
                            <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2 show-admin-columns" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                                   data-col="sku" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                                   data-col="sku" data-order="DESC"></i>
                                            </div>
                                            SKU
                                        </th>
                                        <th>EAN</th>
                                        <th>Manufacturer</th>
                                        <th>MPN</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Retail Inc VAT</th>
                                        <th>Retail Ex VAT</th>
                                        <th>Tax Code</th>
                                        <th>Instructions (Enter=Save) & Questions (Enter=Submit)</th>
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
            <div class="container-fluid" style="padding: 0 15px;">
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

<!-- Hidden form values -->
<input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
<input type="hidden" id="offset" value="0">
<input type="hidden" id="sortCol">
<input type="hidden" id="taxLookup" value='<?= json_encode($tax_lookup) ?>'>

<?php require 'assets/footer.php'; ?>

<script>
/* Merchandiser Page JavaScript - Based on products.js */

$(document).ready(function(){
    
    // Character counter for merchandiser message
    $('textarea[name="merchandiser_message"]').on('input', function() {
        const currentLength = $(this).val().length;
        $('#char-count').text(currentLength);
        
        // Change color if approaching limit
        if (currentLength > 450) {
            $('#char-count').css('color', '#dc3545');
        } else if (currentLength > 400) {
            $('#char-count').css('color', '#ffc107');
        } else {
            $('#char-count').css('color', '#6c757d');
        }
    });
    
    // Set default sort to SKU descending
    $("#sortCol").val("ORDER BY sku DESC");
    
    // Load records function - exactly match your existing pattern
    function loadRecords(limit, offset, searchType='general'){
        var searchQuery = $("#searchQuery").val(),
            skuSearchQuery = $("#skuSearchQuery").val(),
            enabledProducts = $("#enabledProducts")[0].checked,
            magentoEnabled = $("#magentoEnabled")[0].checked,
            sortCol = $("#sortCol").val();
        $("#spinner").show();
        $.ajax({
          type:'POST',
          url:'php/merchandiser_list_products.php',
          data:{limit:limit, offset:offset, search_query:searchQuery, sku_search_query:skuSearchQuery, enabled_products:enabledProducts, magento_enabled:magentoEnabled, sort_col:sortCol, search_type:searchType}
        }).done(function(response){
          $("#spinner").hide();
          if(response.length>0){
            $("#records").html(response);
            $('[data-toggle="tooltip"]').tooltip();
            $(".table-data__tool").width($(".table").width())
            $("#pagination").html($("#PaginationInfoResponse").html());
            $("html, body").animate({ scrollTop: 0 }, "slow");
          }
        });
    }
    
    // Initial load
    loadRecords($("#limit").val() , $("#offset").val());

    // Pagination click handlers
    $(document).on('click' , '.recordsPage' , function(e){
      e.preventDefault();
      var limit = $(this).attr("data-limit"),
        offset = $(this).attr("data-offset");
      loadRecords(limit, offset);
    });

    // Jump to page form submission
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
    });

    // Filter records change
    $(".filterRecords").change(function(e){
      e.preventDefault();
      loadRecords($("#limit").val() , $("#offset").val());
    });
    
    // Search functionality - Enter key required
    $("#searchQuery").keypress(function(e){
      if(e.which == 13) { // Enter key pressed
        $("#offset").val(0); // Reset to first page when searching
        loadRecords($("#limit").val() , $("#offset").val(), 'general');
      }
    });
    
    $("#skuSearchQuery").keypress(function(e){
      if(e.which == 13) { // Enter key pressed
        $("#offset").val(0); // Reset to first page when searching
        loadRecords($("#limit").val() , $("#offset").val(), 'sku');
      }
    });
    
    // Sort column functionality
    $(".sortCol").click(function(e){
      $("#sortCol").val("ORDER BY "+$(this).data("col")+" "+$(this).data("order"));
      loadRecords($("#limit").val() , $("#offset").val());
    });

    // Handle Enter key for question submission
    $(document).on('keypress', '.question-textarea', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            $(this).closest('form').submit();
        }
    });

    // Handle Enter key for instruction saving
    $(document).on('keypress', '.instruction-field', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            const textarea = $(this);
            const sku = textarea.data('sku');
            const instruction = textarea.val().trim();
            
            // Show saving feedback
            textarea.css('background-color', '#fff3cd');
            
            $.ajax({
                url: 'php/save_instruction.php',
                type: 'POST',
                data: {
                    product_sku: sku,
                    instruction: instruction
                },
                success: function(response) {
                    if(response == 'success') {
                        textarea.css('background-color', '#d4edda');
                        setTimeout(() => {
                            textarea.css('background-color', '');
                        }, 1000);
                    } else {
                        textarea.css('background-color', '#f8d7da');
                        setTimeout(() => {
                            textarea.css('background-color', '');
                        }, 2000);
                    }
                },
                error: function() {
                    textarea.css('background-color', '#f8d7da');
                    setTimeout(() => {
                        textarea.css('background-color', '');
                    }, 2000);
                }
            });
        }
    });

    // Handle question form submissions
    $(document).on('submit', '.product-question-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const sku = form.data('sku');
        const question = form.find('.question-textarea').val().trim();
        const textarea = form.find('.question-textarea');
        
        if (!question) {
            alert('Please enter a question before submitting.');
            return;
        }
        
        // Show loading state
        textarea.css('background-color', '#fff3cd');
        
        $.ajax({
            url: 'php/submit_merchandiser_question.php',
            type: 'POST',
            data: {
                product_sku: sku,
                merchandiser_question: question
            },
            success: function(response) {
                if(response == 'success') {
    textarea.css('background-color', '#d4edda');
    
    setTimeout(() => {
        textarea.css('background-color', '');
    }, 1000);
    
    Swal.fire(
        'Success!',
        'Question submitted successfully!',
        'success'
    );
    
    // Reload the table data to show the new question
    loadRecords($("#limit").val(), $("#offset").val());
} else {
                    textarea.css('background-color', '#f8d7da');
                    setTimeout(() => {
                        textarea.css('background-color', '');
                    }, 2000);
                    Swal.fire(
                        'Error',
                        'Error submitting question. Please try again.',
                        'error'
                    );
                }
            },
            error: function() {
                textarea.css('background-color', '#f8d7da');
                setTimeout(() => {
                    textarea.css('background-color', '');
                }, 2000);
                Swal.fire(
                    'Error',
                    'Error submitting question. Please try again.',
                    'error'
                );
            }
        });
    });
    
});
</script>

<style>
.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid transparent;
    border-radius: 4px;
}

/* Merchandiser Message Box Styling */
.merchandiser-message-box {
    background-color: #f8f9fa;
    border: 2px solid #007bff;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,123,255,0.1);
}

.merchandiser-message-textarea {
    width: 100%;
    min-height: 80px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 10px;
    font-size: 1.1em;
    font-family: inherit;
    resize: vertical;
    line-height: 1.4;
    margin-bottom: 10px;
}

.merchandiser-message-textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.merchandiser-message-display {
    font-size: 1.1em;
    line-height: 1.4;
    color: #333;
    padding: 5px 0;
}

.message-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.char-counter {
    color: #6c757d;
    font-size: 0.9em;
}

.btn-sm {
    padding: 5px 15px;
    font-size: 0.9em;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

/* Compact table styling - Excel-like */
.table {
    font-size: 0.75rem;
    border-collapse: collapse;
    margin-bottom: 0;
    width: 100% !important;
}

.table th,
.table td {
    border: 1px solid #dee2e6;
    padding: 2px 4px;
    vertical-align: top;
    line-height: 1.2;
}

.table th {
    background-color: #f8f9fa;
    font-weight: bold;
    text-align: center;
    padding: 4px;
    font-size: 0.7rem;
}

.table tbody tr {
    height: 50px; /* Fixed compact row height */
}

.table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.table tbody tr:hover {
    background-color: #e9ecef;
}

/* Column width controls - using percentages for full width */
.table th:nth-child(1), .table td:nth-child(1) { width: 6%; } /* SKU */
.table th:nth-child(2), .table td:nth-child(2) { width: 8%; } /* EAN */
.table th:nth-child(3), .table td:nth-child(3) { width: 9%; } /* Manufacturer */
.table th:nth-child(4), .table td:nth-child(4) { width: 9%; } /* MPN */
.table th:nth-child(5), .table td:nth-child(5) { width: 25%; } /* Product Name */
.table th:nth-child(6), .table td:nth-child(6) { width: 10%; } /* Category */
.table th:nth-child(7), .table td:nth-child(7) { width: 6%; } /* Retail Inc VAT */
.table th:nth-child(8), .table td:nth-child(8) { width: 6%; } /* Retail Ex VAT */
.table th:nth-child(9), .table td:nth-child(9) { width: 6%; } /* Tax Code */
.table th:nth-child(10), .table td:nth-child(10) { width: 15%; } /* Instructions & Questions */

/* Product name - allow text wrapping and selection */
.table td:nth-child(5) {
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    line-height: 1.3;
    user-select: text; /* Allow text selection for copying */
    cursor: text; /* Show text cursor for selection */
}

/* Sort icons styling */
.sortWrap {
    display: inline-block;
    position: relative;
    margin-right: 5px;
}

.sortIcon {
    cursor: pointer;
    color: #6c757d;
    font-size: 0.6rem;
    margin-left: 2px;
}

.sortIcon:hover {
    color: #007bff;
}

.sortIcon.active {
    color: #007bff;
}

/* Compact form elements */
.table textarea {
    font-family: inherit;
    font-size: 0.7em;
    border: 1px solid #ccc;
    padding: 2px;
    margin: 0;
    resize: none;
    line-height: 1.2;
}

.table button {
    font-size: 0.6em;
    padding: 1px 4px;
    margin: 1px 0;
    border: none;
    border-radius: 1px;
    cursor: pointer;
    line-height: 1.2;
}

/* Remove section padding */
.welcome {
    padding-top: 5px !important;
}

.p-t-20 {
    padding-top: 10px !important;
}

/* Compact page layout */
.page-content--bgf7 {
    padding: 10px;
}

.line-seprate {
    margin: 5px 0 !important;
}

.title-4 {
    margin-bottom: 5px !important;
    font-size: 1.5rem !important;
}

/* Fix search bar container */
.table-data__tool {
    max-width: 100% !important;
    width: 100% !important;
    overflow: hidden;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 10px 0 !important;
}

.table-data__tool-left {
    display: flex !important;
    gap: 20px !important;
}

.table-data__tool-search {
    display: flex !important;
    gap: 30px !important;
    align-items: center !important;
}
</style>