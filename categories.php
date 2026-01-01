<?php 
ob_start();
session_start();
$page_title = 'Categories Control Panel';
require 'assets/header.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}
require __DIR__.'/php/bootstrap.php';
$user_details = $DB->query(" SELECT * FROM users WHERE id=?" , [$user_id])[0];

if($user_details['admin']!=2){
  header("Location: index.php");
  exit();
}
?>
<link rel="stylesheet" href="assets/css/tables.css?<?=time()?>">

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
                                  <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#newCatPopup"><i class="zmdi zmdi-plus"></i> Add New Category</button>
                                </div>
                                <div class="table-data__tool-right"></div>
                                <div class="rs-select2--light rs-select2--lg ml-4" style="min-width:600px;"></div>
                            </div>
                            <div class="table-responsive table-responsive-data2">
                                <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2">
                                    <thead>
                                        <tr>
                                          <th>ID</th>
                                          <th>pless_main_category</th>
                                          <th>pos_category</th>
                                          <th>Update</th>
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

<!-- Add New Item Popup -->
<div class="modal fade" id="newCatPopup" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Add New Category</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form method="POST" id="newCatForm">
          <div class="form-group">
            <label for="catID">ID <span class="text-danger">*</span></label>
            <input type="text" name="cat_id" id="catID" class="form-control requiredField">
          </div>
          <div class="form-group">
            <label for="pless_main_category">pless_main_category <span class="text-danger">*</span></label>
            <input type="text" name="pless_main_category" id="pless_main_category" class="form-control requiredField">
          </div>
          <div class="form-group">
            <label for="pos_category">pos_category <span class="text-danger">*</span></label>
            <input type="text" name="pos_category" id="pos_category" class="form-control requiredField">
          </div>
          <hr>
          <button class="btn btn-success d-block mx-auto">Add New Category</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Add New Item Popup -->
<div class="modal fade" id="updateRecordPopup" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Update Record Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="updateRecordContent"></div>
    </div>
  </div>
</div>

<input type="hidden" id="limit" value="50">
<input type="hidden" id="offset" value="0">
<?php require 'assets/footer.php'; ?>
<script>
  $(document).ready(function(){

    
      function loadRecords(limit, offset, searchType='general'){
        var searchQuery = $("#searchQuery").val();
        $("#spinner").show();
        $.ajax({
          type:'POST',
          url:'php/list_categories.php',
          data:{limit:limit, offset:offset, search_query:searchQuery}
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
    
    $("#searchQuery, #skuSearchQuery").keyup(function(e){
      e.preventDefault();
      if(e.key === 'Enter' || e.keyCode === 13) {
        var searchType = 'general';
        if($(this).attr("id")=='skuSearchQuery') searchType = 'sku';
        loadRecords($("#limit").val() , $("#offset").val(), searchType);
      }
    })

   
    
    $("#newCatForm").submit(function(e){
      e.preventDefault();
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
					'All fields with * are required.',
					'error'
					)
				return false;
			}
      
      $("#spinner").show();
      
      $.ajax({
        type:"POST",
        url:'php/add_new_category.php',
        data:formData
      }).done(function(response){
        console.log(response);
        $("#spinner").hide();
        if(response=='added'){
          Swal.fire(
              'Added!',
              'Category added successfully.',
              'success'
            )
          $("#newCatPopup").modal("hide");
          form[0].reset();
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
    
    $(document).on('click' , '.updateRecord' , function(e){
      e.preventDefault();
      var recordID = $(this).attr("data-id");
      $("#spinner").show();
      $.ajax({
        type:'POST',
        url:'php/get_category_details.php',
        data:{id:recordID}
      }).done(function(response){
        $("#spinner").hide();
        if(response.length>0){
          $("#updateRecordContent").html(response);
          $("#updateRecordPopup").modal('show');
        }else{
          Swal.fire(
              'Oops...',
              'Something went wrong, please try again.',
              'error'
            )
        }
      })
    })
    
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
					'All fields with * are required.',
					'error'
					)
				return false;
			}
      
      $("#spinner").show();
      
      $.ajax({
        type:"POST",
        url:'php/update_category_details.php',
        data:formData
      }).done(function(response){
        console.log(response);
        $("#spinner").hide();
        if(response=='updated'){
          Swal.fire(
              'Updated!',
              'Category updated successfully.',
              'success'
            )
          $("#updateRecordPopup").modal("hide");
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
