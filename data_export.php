<?php 
ob_start();
session_start();
$page_title = 'Data Export';
require 'assets/header.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}
require __DIR__.'/php/bootstrap.php';
$user_details = $DB->query(" SELECT * FROM users WHERE id=?" , [$user_id])[0];

// all manufacturers
$all_manufacturers = $DB->query(" SELECT * FROM master_pless_manufacturers ORDER BY manufacturer_name ASC");

// tax_rates
$tax_rates = $DB->query(" SELECT * FROM tax_rates ORDER BY name ASC");

// master_categories
$categories = $DB->query(" SELECT * FROM master_categories ORDER BY pos_category ASC");

// settings
require 'php/settings.php';
?>
<link rel="stylesheet" href="assets/css/tables.css?<?=time()?>">
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
                                  <?php if ($user_details['admin'] != 0): ?>
                                    <button class="btn btn-success btn-sm" id="newItemBtn"><i class="zmdi zmdi-plus"></i> Add New Item</button>
                                    <button class="btn btn-success btn-sm" id="exportallonmagento" onclick="exportToMagento()"><i class="zmdi zmdi-plus"></i> Export for Magento</button>
                                    <!--<button class="btn btn-primary btn-sm" id="calculateSellingPrices">Calculate Selling Prices</button>-->
                                    <?php endif; ?>


                                </div>
                                <div class="table-data__tool-right">
                                  
                                  <div class="rs-select2--light rs-select2--lg mr-4">
                                      <div class="form-check">
                                        <div class="checkbox">
                                          <label for="enabledProducts" class="form-check-label ">
                                            <input type="checkbox" id="enabledProducts" value="enabled" class="form-check-input filterRecords"> Active products
                                          </label>
                                        </div>
                                      </div>
                                  </div>
                                  <div class="rs-select2--light rs-select2--lg">
                                      <div class="form-check">
                                        <div class="checkbox">
                                          <label for="exportToMagento" class="form-check-label ">
                                            <input type="checkbox" id="exportToMagento" value="enabled" class="form-check-input filterRecords"> On pless.co.uk
                                          </label>
                                        </div>
                                      </div>
                                  </div>
                                </div>
                                <div class="rs-select2--light rs-select2--lg ml-4" style="min-width:600px;">
                                  <div class="row">
                                    <div class="col-sm-6">
                                      <input type="text" class="au-input au-input--full" id="skuSearchQuery" placeholder="Search By SKU" style="background-color: #fed8b1;">
                                    </div>
                                    <div class="col-sm-6">
                                      <input type="text" class="au-input au-input--full" id="searchQuery" placeholder="Search Products">
                                    </div>
                                  </div>
                                </div>
                            </div>
                            <div class="table-responsive table-responsive-data2">
                                <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2">
                                    <thead>
                                        <tr>
                                            <!-- Normal users data start -->
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
                                              manufacturer</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="mpn" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="mpn" data-order="DESC"></i>
                                              </div>
                                              mpn</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="categories" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="categories" data-order="DESC"></i>
                                              </div>
                                              Category</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="ean" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="ean" data-order="DESC"></i>
                                              </div>
                                              ean</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="price" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="price" data-order="DESC"></i>
                                              </div>
                                              Retail inc</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="trade" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="trade" data-order="DESC"></i>
                                              </div>
                                              Trade inc</th>
                                            <!-- Normal users data end -->
                                          
                                            <!-- Admin users data start -->
                                            <?php if ($user_details['admin'] != 0): ?>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="pricing_method" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="pricing_method" data-order="DESC"></i>
                                              </div>
                                              pricing method</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="cost" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="cost" data-order="DESC"></i>
                                              </div>
                                              cost</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="pricing_cost" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="pricing_cost" data-order="DESC"></i>
                                              </div>
                                              P'Cost</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="retail_markup" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="retail_markup" data-order="DESC"></i>
                                              </div>
                                              retail %</th>
                                            <th>
                                              <div class="sortWrap">
                                                <i class="fas fa-sort-up sortIcon sortCol sortUp" data-col="trade_markup" data-order="ASC"></i>
                                                <i class="fas fa-sort-down sortIcon sortCol sortDown" data-col="trade_markup" data-order="DESC"></i>
                                              </div>
                                              trade %</th>
                                            <th>Edit</th>
                                            <!-- Admin users data end -->
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
<div class="modal fade" id="newItemPopup" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
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
              <div class="form-group">
                <input type="text" id="name" name="name" class="form-control requiredField" placeholder="Name">
              </div>
              <div class="form-group">
                <select id="manufacturer" name="manufacturer" class="form-control dropDownMenu requiredField">
                  <option value="" selected>Select Manufacturer</option>
                  <?php foreach($all_manufacturers as $manufacturer): ?>
                  <option value="<?=$manufacturer['manufacturer_name']?>"><?=$manufacturer['manufacturer_name']?></option>
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
                  <?php foreach($categories as $category): ?>
                  <option value="<?=$category['id'].'|'.$category['pless_main_category'].'|'.$category['pos_category']?>"><?=$category['pless_main_category']?></option>
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
                  <label for="enable" class="form-check-label ">
                    <input type="checkbox" id="enable" name="enable" value="enabled" class="form-check-input" checked> Enabled
                  </label>
                </div>
              </div>
              <div class="form-check">
                <div class="checkbox">
                  <label for="export_to_magento" class="form-check-label ">
                    <input type="checkbox" id="export_to_magento" name="export_to_magento" value="export_to_magento" class="form-check-input" checked> On WWW
                  </label>
                </div>
              </div>
              <div class="form-check">
                <div class="checkbox">
                  <label for="on_pos" class="form-check-label ">
                    <input type="checkbox" id="on_pos" name='on_pos' value="on_pos" class="form-check-input" disabled> On POS
                  </label>
                </div>
              </div>
            </div>
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
                  <input type="text" id="targetRetail" name="target_retail" class="form-control bg-secondary text-white whitePlaceholder calculationField doCalculations targetRetail" placeholder="Target Retail Inc Vat"  >
                  <p class="text-muted"> Profit = £<span id="targetRetailProfit" class="targetRetailProfit calculationResult">0.00</span></p>
                  <p class="text-muted"> Markup = <span id="targetRetailPercent" class="targetRetailPercent calculationResult">0.00</span>%</p>
                </div>
                <div class="form-group col-sm-4">
                  <label for="targetTrade">Target Trade Inc Vat</label>
                  <input type="text" id="targetTrade" name="target_trade" class="form-control bg-secondary text-white whitePlaceholder calculationField doCalculations targetTrade" placeholder="Target Trade Inc Vat"  >
                  <p class="text-muted"> Profit = £<span id="targetTradeProfit" class="targetTradeProfit calculationResult">0.00</span></p>
                  <p class="text-muted"> Markup = <span id="targetTradePercent" class="targetTradePercent calculationResult">0.00</span>%</p>
                </div>
                <div class="form-group col-sm-4">
                  <label for="vatScheme">Vat Scheme</label>
                  <select name="tax_rate_id" id="vatScheme" class="form-control dropDownMenu doCalculations vatScheme"  >
                    <?php foreach($tax_rates as $tax_rate): ?>
                      <option value="<?=$tax_rate['tax_rate_id']?>" data-tax_rate="<?=$tax_rate['tax_rate']?>" <?=($tax_rate['tax_rate']==0.2)?"selected":''?> ><?=$tax_rate['name']?></option>
                      <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row ml-0 mr-0">
                <div class="form-group col-sm-6 markupPriceMehtod">
                  <label for="retailMarkup">Retail Markup %</label>
                  <input type="text" id="retailMarkup" name="retail_markup" class="form-control bg-success text-white whitePlaceholder calculationField doCalculations retailMarkup" placeholder="Retail Markup %"  >
                  <p class="text-muted"> Profit = <span id="retailMarkupProfit" class="retailMarkupProfit calculationResult">0.00</span></p>
                  <p class="text-muted incVatPrice"> Inc Vat = <span id="retailMarkupIncVatPrice" class="retailMarkupIncVatPrice calculationResult">0.00</span></p>
                </div>
                <div class="form-group col-sm-6 markupPriceMehtod">
                  <label for="tradeMarkup">Trade Markup %</label>
                  <input type="text" id="tradeMarkup" name="trade_markup" class="form-control bg-primary text-white whitePlaceholder calculationField doCalculations tradeMarkup" placeholder="Trade Markup %"  >
                  <p class="text-muted"> Profit = <span id="tradeMarkupProfit" class="tradeMarkupProfit calculationResult">0.00</span></p>
                  <p class="text-muted incVatPrice"> Inc Vat = <span id="tradeMarkupIncVatPrice" class="tradeMarkupIncVatPrice calculationResult">0.00</span></p>
                </div>
                <div class="form-group col-sm-6 fixedPriceMethod">
                  <label for="fixedRetailPricing">Fixed Retail Pricing</label>
                  <input type="text" id="fixedRetailPricing" name="fixed_retail_pricing" class="form-control bg-success text-white whitePlaceholder calculationField doCalculations fixedRetailPricing" placeholder="Fixed Retail Pricing"  >
                  <p class="text-muted"> Profit = <span id="fixedRetailPricingProfit" class="fixedRetailPricingProfit calculationResult">0.00</span></p>
                </div>
                <div class="form-group col-sm-6 fixedPriceMethod">
                  <label for="fixedTradePricing">Fixed Trade Pricing</label>
                  <input type="text" id="fixedTradePricing" name="fixed_trade_pricing" class="form-control bg-primary text-white whitePlaceholder calculationField doCalculations fixedTradePricing" placeholder="Fixed Trade Pricing"  >
                  <p class="text-muted"> Profit = <span id="fixedTradePricingProfit" class="fixedTradePricingProfit calculationResult">0.00</span></p>
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

<input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
<input type="hidden" id="offset" value="0">
<input type="hidden" id="sortCol">
<?php require 'assets/footer.php'; ?>
<script>
  $(document).ready(function(){
    
    $(".dropDownMenu").chosen({
      width:'100%'
    })
    
      function loadRecords(limit, offset, searchType='general'){
        var searchQuery = $("#searchQuery").val(),
            skuSearchQuery = $("#skuSearchQuery").val(),
            enabledProducts = $("#enabledProducts")[0].checked,
            exportToMagento = $("#exportToMagento")[0].checked,
            sortCol = $("#sortCol").val();
        $("#spinner").show();
        $.ajax({
          type:'POST',
          url:'php/list_products.php',
          data:{limit:limit, offset:offset, search_query:searchQuery, sku_search_query:skuSearchQuery, enabled_products:enabledProducts, export_to_magento:exportToMagento,sort_col:sortCol, search_type:searchType}
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

	    $(".filterRecords").change(function(e){
	    	e.preventDefault();
	    	loadRecords($("#limit").val() , $("#offset").val());
	    })
    
    $("#searchQuery, #skuSearchQuery").keyup(function(e){
      e.preventDefault();
      if(e.key === 'Enter' || e.keyCode === 13) {
        var searchType = 'general';
        if($(this).attr("id")=='skuSearchQuery') searchType = 'sku';
        loadRecords($("#limit").val() , $("#offset").val(), searchType);
      }
    })
    
    $(".sortCol").click(function(e){
      $("#sortCol").val("ORDER BY "+$(this).data("col")+" "+$(this).data("order"));
      loadRecords($("#limit").val() , $("#offset").val());
    })
    

    <?php require 'php/calculations_script.php'; ?>
    
    
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
            url:'php/calculate_selling_prices.php',
          }).done(function(response){
            console.log(response);
            $("#spinner").hide();
            if(response.includes("_done")){
              var message = response.replaceAll("_done", "");
              Swal.fire(
                  'Calculated!',
                  message+' calculated successfully!',
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
      })
    })
    
    $("#newItemBtn").click(function(e){
      e.preventDefault();
      $("#newItemForm")[0].reset()
      $(".dropDownMenu").trigger("chosen:updated");
      $(".calculationResult").html("0.00")
      $("#newItemForm").find(".markupPriceMehtod").show();
      $("#newItemForm").find(".fixedPriceMethod").hide();
      $("#newItemPopup").modal('show')
    })
    
    $("#newItemForm").submit(function(e){
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
					'Name, manufacturer and categorys are required.',
					'error'
					)
				return false;
			}
      
      $("#spinner").show();
      
      $.ajax({
        type:"POST",
        url:'php/add_new_product.php',
        data:formData
      }).done(function(response){
        console.log(response);
        $("#spinner").hide();
        if(response=='added'){
          Swal.fire(
              'Added!',
              'Product added successfully.',
              'success'
            )
          $("#newItemPopup").modal("hide");
          form[0].reset();
          $(".pagination li.page-item.active .recordsPage").click();
          $(".dropDownMenu").trigger("chosen:updated");
          $(".calculationResult").html("0.00")
          form.find(".markupPriceMehtod").show();
          form.find(".fixedPriceMethod").hide();
          $(".pricing_method").trigger('change');
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
      var recordSKU = $(this).attr("data-sku");
      $("#spinner").show();
      $.ajax({
        type:'POST',
        url:'php/get_product_details.php',
        data:{sku:recordSKU}
      }).done(function(response){
        $("#spinner").hide();
        if(response.length>0){
          $("#updateRecordContent").html(response);
          $("#updateRecordPopup").modal('show');
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
        url:'php/update_product_details.php',
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
<script>
function exportToMagento() {
  // Redirect the user to the export.php script with a query parameter
  window.location.href = 'export.php?action=exportMagento';
}
</script>