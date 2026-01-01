<?php 
ob_start();
session_start();
$page_title = 'Second Hand Items';
require 'assets/header.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}
require __DIR__.'/php/bootstrap.php';
$user_details = $DB->query(" SELECT * FROM users WHERE id=?" , [$user_id])[0];
?>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
  .modal-lg {
    max-width: 1000px;
  }
  
  .page-wrapper {
    background-color: #f9fafb;
    min-height: 100vh;
  }
  
  .welcome {
    background-color: white;
    border-bottom: 1px solid #e5e7eb;
    padding: 1.5rem 0;
  }
  
  .title-4 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #111827;
  }
  
  .table-data__tool {
    background-color: white;
    padding: 1.25rem;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
  }
</style>

<div class="page-wrapper">
    <?php require 'assets/navbar.php' ?>
    <div class="page-content--bgf7">
        <section class="au-breadcrumb2 p-0 pt-4"></section>
        
        <section class="welcome p-t-10">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="title-4"><?=$page_title?></h1>
                        <hr class="line-seprate">
                    </div>
                </div>
            </div>
        </section>

        <section class="p-t-20">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-data__tool">
                            <div class="table-data__tool-left">
                                <?php if ($user_details['admin'] != 0): ?>
                                <button class="btn btn-success btn-sm" id="newItemBtn">
                                    <i class="zmdi zmdi-plus"></i> Add New Item
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="table-data__tool-right">
                                <div class="rs-select2--light rs-select2--lg ml-4 w-100">
                                    <div class="row">
                                        <div class="col-12 col-sm-6">
                                            <input type="text" class="au-input au-input--full" id="searchQuery" 
                                                   placeholder="Search items by name or serial number">
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <select id="statusFilter" class="form-control">
                                                <option value="">All Status</option>
                                                <option value="in_stock">In Stock</option>
                                                <option value="sold">Sold</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                      <div class="table-responsive table-responsive-data2">
                            <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="id" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="id" data-order="DESC"></i>
                                            </div>
                                            ID
                                        </th>
                                        <th>
                                            <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="item_name" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="item_name" data-order="DESC"></i>
                                            </div>
                                            Item Name
                                        </th>
                                        <th>Condition</th>
                                        <th>Serial Number</th>
                                        <th>Status</th>
                                        <?php if ($user_details['admin'] != 0): ?>
                                        <th>Purchase Price</th>
                                        <th>Customer ID</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                        <?php endif; ?>
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
    </div>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" id="item_id" name="id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="item_name">Item Name</label>
                                <input type="text" id="item_name" name="item_name" class="form-control requiredField">
                            </div>
                            <div class="form-group">
                                <label for="condition">Condition</label>
                                <select id="condition" name="condition" class="form-control">
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="serial_number">Serial Number</label>
                                <input type="text" id="serial_number" name="serial_number" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="in_stock">In Stock</option>
                                    <option value="sold">Sold</option>
                                </select>
                            </div>
                        </div>
                      <div class="col-md-6">
                            <div class="form-group">
                                <label for="purchase_price">Purchase Price</label>
                                <input type="number" step="0.01" id="purchase_price" name="purchase_price" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="customer_id">Customer ID</label>
                                <input type="text" id="customer_id" name="customer_id" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-success d-block mx-auto">Save Item</button>
                </form>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
<input type="hidden" id="offset" value="0">
<input type="hidden" id="sortCol">

<?php require 'assets/footer.php'; ?>

<script>
$(document).ready(function(){
    function loadRecords(limit, offset){
        var searchQuery = $("#searchQuery").val(),
            statusFilter = $("#statusFilter").val(),
            sortCol = $("#sortCol").val();
        
        $("#spinner").show();
        $.ajax({
            type: 'POST',
            url: 'php/list_second_hand_items.php',
            data: {
                limit: limit,
                offset: offset,
                search_query: searchQuery,
                status_filter: statusFilter,
                sort_col: sortCol
            }
        }).done(function(response){
            $("#spinner").hide();
            if(response.length > 0){
                $("#records").html(response);
                $('[data-toggle="tooltip"]').tooltip();
                $("#pagination").html($("#PaginationInfoResponse").html());
                $("html, body").animate({ scrollTop: 0 }, "slow");
            }
        });
    }

    loadRecords($("#limit").val(), $("#offset").val());

    $("#searchQuery, #statusFilter").on('input change', function(){
        loadRecords($("#limit").val(), $("#offset").val());
    });

    $(".sortCol").click(function(e){
        $("#sortCol").val("ORDER BY " + $(this).data("col") + " " + $(this).data("order"));
        loadRecords($("#limit").val(), $("#offset").val());
    });
  $("#newItemBtn").click(function(){
        $("#itemForm")[0].reset();
        $("#item_id").val('');
        $("#modalTitle").text("Add New Item");
        $("#itemModal").modal('show');
    });

    $(document).on('click', '.editItem', function(){
        var id = $(this).data('id');
        $("#spinner").show();
        $.ajax({
            type: 'POST',
            url: 'php/get_second_hand_item.php',
            data: { id: id }
        }).done(function(response){
            $("#spinner").hide();
            if(response.success){
                var item = response.item;
                $("#item_id").val(item.id);
                $("#item_name").val(item.item_name);
                $("#condition").val(item.condition);
                $("#serial_number").val(item.serial_number);
                $("#status").val(item.status);
                $("#purchase_price").val(item.purchase_price);
                $("#customer_id").val(item.customer_id);
                $("#notes").val(item.notes);
                $("#modalTitle").text("Edit Item");
                $("#itemModal").modal('show');
            } else {
                Swal.fire('Error', response.message || 'Failed to load item details.', 'error');
            }
        });
    });

    $("#itemForm").submit(function(e){
        e.preventDefault();
        var form = $(this),
            formData = form.serialize();

        if(!form.find("#item_name").val()){
            Swal.fire('Error', 'Item name is required.', 'error');
            return false;
        }

        $("#spinner").show();
        $.ajax({
            type: "POST",
            url: 'php/save_second_hand_item.php',
            data: formData
        }).done(function(response){
            $("#spinner").hide();
            if(response.success){
                Swal.fire('Success', 'Item saved successfully.', 'success');
                $("#itemModal").modal("hide");
                loadRecords($("#limit").val(), $("#offset").val());
            } else {
                Swal.fire('Error', response.message || 'Something went wrong.', 'error');
            }
        });
    });

    $(document).on('click', '.recordsPage', function(e){
        e.preventDefault();
        var limit = $(this).attr("data-limit"),
            offset = $(this).attr("data-offset");
        loadRecords(limit, offset);
    });
});
</script>