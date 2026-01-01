<?php 
ob_start();
session_start();
$page_title = 'Stock Suppliers Control Panel';
require 'assets/header.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}
require __DIR__.'/php/bootstrap.php';
$user_details = $DB->query(" SELECT * FROM users WHERE id=?" , [$user_id])[0];
if($user_details['admin'] == 0){
  header("Location: index.php");
  exit();
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
</style>
<link rel="stylesheet" href="assets/css/tables.css">

    <div class="page-wrapper">
       <?php require 'assets/navbar.php' ?>
        <!-- PAGE CONTENT-->
        <div class="page-content--bgf7">
            <!-- BREADCRUMB-->
            <section class="au-breadcrumb2 p-0 pt-4"></section>
            <!-- END BREADCRUMB-->

            <!-- WELCOME-->
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
            <!-- END WELCOME-->
            
            <!-- DATA TABLE-->
            <section class="p-t-20">
                <div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="table-data__tool">
                                <div class="table-data__tool-left">
                                  <!--<button class="btn btn-primary btn-sm" id="checkTable">Check Table</button>-->
                                </div>
                                <div class="table-data__tool-right">
                                  <div class="rs-select2--light rs-select2--lg" style="min-width:400px;">
                                      <input type="text" class="au-input au-input--full" id="searchQuery" placeholder="Search Stock Suppliers">
                                  </div>
                                </div>
                                
                            </div>
                            <div class="table-responsive table-responsive-data2">
                                <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2">
                                    <thead>
                                        <tr>
                                            <!--<th>Select</th>-->
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="sku" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="sku" data-order="DESC"></i>
                                              </div>
                                              sku</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="name" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="name" data-order="DESC"></i>
                                              </div>
                                              name</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="category" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="category" data-order="DESC"></i>
                                              </div>
                                              category</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="manufacturer" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="manufacturer" data-order="DESC"></i>
                                              </div>
                                              manufacturer</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="mpn" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="mpn" data-order="DESC"></i>
                                              </div>
                                              mpn</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="ean" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="ean" data-order="DESC"></i>
                                              </div>
                                              ean</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="qty" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="qty" data-order="DESC"></i>
                                              </div>
                                              qty</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="cost" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="cost" data-order="DESC"></i>
                                              </div>
                                              Cost</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="supplier" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="supplier" data-order="DESC"></i>
                                              </div>
                                              supplier</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="supplier_sku" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="supplier_sku" data-order="DESC"></i>
                                              </div>
                                              supplier sku</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="time_recorded" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="time_recorded" data-order="DESC"></i>
                                              </div>
                                              time recorded</th>
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
      function loadRecords(limit, offset){
        var searchQuery = $("#searchQuery").val(),
            sortCol = $("#sortCol").val();
        $("#spinner").show();
        $.ajax({
          type:'POST',
          url:'php/list_stock_suppliers.php',
          data:{limit:limit, offset:offset, search_query:searchQuery, sort_col:sortCol}
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
