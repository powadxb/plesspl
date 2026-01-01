/* Products Page JavaScript with Category Sidebar and Supplier Price Comparison */

// Global function for mobile sidebar toggle (needed for onclick in HTML)
function toggleMobileSidebar() {
    const sidebar = document.getElementById('categorySidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('show');
}

// Export to CSV functionality
document.getElementById('exportToCsv')?.addEventListener('click', function () {
    const searchQuery = document.getElementById('searchQuery').value;
    const skuSearchQuery = document.getElementById('skuSearchQuery').value;
    const enabledProducts = document.getElementById('enabledProducts').checked ? 1 : 0;
    const exportToMagento = document.getElementById('exportToMagentoFilter').checked ? 1 : 0;

    const url = `export_csv.php?searchQuery=${encodeURIComponent(searchQuery)}&skuSearchQuery=${encodeURIComponent(skuSearchQuery)}&enabledProducts=${enabledProducts}&exportToMagento=${exportToMagento}`;
    window.location.href = url;
});

$(document).ready(function(){
    
    // Category filtering variables
    let selectedCategories = new Set();
    
    // Initialize chosen dropdowns
    $(".dropDownMenu").chosen({
      width:'100%'
    });

    // Prevent form submission on enter key for modal forms
    $(document).on('keypress', '#newItemForm, #updateDetailsForm', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            return false;
        }
    });
    
    // Category sidebar event handlers using delegation
    $(document).on('click', '.main-category', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const categoryId = $(this).data('category');
        const icon = $(this).find('.category-icon');
        const subcategories = $('#' + categoryId);
        
        icon.toggleClass('expanded');
        subcategories.toggleClass('expanded');
        $(this).toggleClass('active');
    });

    // Subcategory checkbox handling
    $(document).on('change', '.subcategory-checkbox', function(e) {
        e.stopPropagation();
        
        const checkbox = $(this);
        const categoryName = checkbox.val();
        const subcategoryDiv = checkbox.closest('.subcategory');
        
        if (checkbox.prop('checked')) {
            selectedCategories.add(categoryName);
            subcategoryDiv.addClass('selected');
        } else {
            selectedCategories.delete(categoryName);
            subcategoryDiv.removeClass('selected');
        }
        
        updateSelectedCategories();
        loadRecords($("#limit").val(), $("#offset").val());
    });

    // Subcategory click handler
    $(document).on('click', '.subcategory', function(e) {
        e.stopPropagation();
        
        const checkbox = $(this).find('.subcategory-checkbox');
        
        if (!$(e.target).is('input[type="checkbox"]') && !$(e.target).is('label')) {
            checkbox.prop('checked', !checkbox.prop('checked'));
            checkbox.trigger('change');
        }
    });

    $(document).on('click', '.clear-filters', function() {
        selectedCategories.clear();
        $('.subcategory-checkbox').prop('checked', false);
        $('.subcategory').removeClass('selected');
        $('.main-category').removeClass('active');
        $('.subcategories').removeClass('expanded');
        $('.category-icon').removeClass('expanded');
        updateSelectedCategories();
        loadRecords($("#limit").val(), $("#offset").val());
    });

    function updateSelectedCategories() {
        const container = $('#selectedCategories');
        if (selectedCategories.size > 0) {
            container.text(`Filtering by: ${Array.from(selectedCategories).join(', ')}`);
        } else {
            container.text('');
        }
    }

    $(document).on('click', '#mobileOverlay', function() {
        $('#categorySidebar').removeClass('mobile-open');
        $('#mobileOverlay').removeClass('show');
    });
    
    // Load records function - WITH SUPPLIER PRICE SUPPORT
    function loadRecords(limit, offset, searchType='general'){
        var searchQuery = $("#searchQuery").val() || '',
            skuSearchQuery = $("#skuSearchQuery").val() || '',
            enabledProducts = $("#enabledProducts")[0] ? $("#enabledProducts")[0].checked : true,
            exportToMagento = $("#exportToMagentoFilter")[0] ? $("#exportToMagentoFilter")[0].checked : false,
            inStockFilter = $("#inStockFilter")[0] ? $("#inStockFilter")[0].checked : true,
            outOfStockFilter = $("#outOfStockFilter")[0] ? $("#outOfStockFilter")[0].checked : true,
            showSupplierPrices = $("#showSupplierPrices")[0] ? $("#showSupplierPrices")[0].checked : false,
            sortCol = $("#sortCol").val() || '',
            categoryFilters = Array.from(selectedCategories);
        
        $("#spinner").show();
        $.ajax({
          type:'POST',
          url:'php/list_products.php',
          data:{
            limit:limit, 
            offset:offset, 
            search_query:searchQuery, 
            sku_search_query:skuSearchQuery, 
            enabled_products:enabledProducts, 
            export_to_magento:exportToMagento,
            in_stock_filter:inStockFilter,
            out_of_stock_filter:outOfStockFilter,
            show_supplier_prices:showSupplierPrices,
            category_filters:categoryFilters,
            sort_col:sortCol, 
            search_type:searchType
          },
          success: function(response){
            $("#spinner").hide();
            if(response.length>0){
              $("#records").html(response);
              $('[data-toggle="tooltip"]').tooltip();
              $(".table-data__tool").width($(".table").width())
              $("#pagination").html($("#PaginationInfoResponse").html());
              $("html, body").animate({ scrollTop: 0 }, "slow");
              
              // Show/hide supplier price column header based on toggle
              if(showSupplierPrices) {
                  $('.supplier-price-header').show();
              } else {
                  $('.supplier-price-header').hide();
              }
            } else {
              $("#records").html('<tr><td colspan="15"><p class="text-center"><b class="text-danger">No records found.</b></p></td></tr>');
            }
          },
          error: function(xhr, status, error) {
            console.log('AJAX Error:', error);
            console.log('Response Text:', xhr.responseText);
            $("#spinner").hide();
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
    
    // Search functionality
    $("#searchQuery, #skuSearchQuery").keyup(function(e){
      e.preventDefault();
      if(e.key === 'Enter' || e.keyCode === 13) {
        var searchType = 'general';
        if($(this).attr("id")=='skuSearchQuery') searchType = 'sku';
        loadRecords($("#limit").val() , $("#offset").val(), searchType);
      }
    });
    
    // Sort column functionality
    $(".sortCol").click(function(e){
      $("#sortCol").val("ORDER BY "+$(this).data("col")+" "+$(this).data("order"));
      loadRecords($("#limit").val() , $("#offset").val());
    });
    
    // Calculate selling prices
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
    });
    
    // New item button click
    $("#newItemBtn").click(function(e){
      e.preventDefault();
      $("#newItemForm")[0].reset()
      $(".dropDownMenu").trigger("chosen:updated");
      $(".calculationResult").html("0.00")
      $("#newItemForm").find(".markupPriceMehtod").show();
      $("#newItemForm").find(".fixedPriceMethod").hide();
      $("#newItemPopup").modal('show')
    });
    
    // New item form submission
    $("#newItemForm").submit(function(e){
      e.preventDefault();
      var form = $(this),
        formData = form.serialize(),
        validToSubmit = true;

      $.each(form.find(".requiredField") , function(index , value){
        if($(value).val()==null || $(value).val().length==0){
          validToSubmit = false;
        }
      });

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
    });
    
    // Update record click handler
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
    });

    // Export to Magento
    $("#exportToMagentoBtn").click(function() {
      var searchQuery = $("#searchQuery").val(),
          skuSearchQuery = $("#skuSearchQuery").val(),
          enabledProducts = $("#enabledProducts")[0].checked ? 1 : 0,
          exportToMagento = $("#exportToMagentoFilter")[0].checked ? 1 : 0;

      window.location.href = `php/export_magento.php?searchQuery=${encodeURIComponent(searchQuery)}&skuSearchQuery=${encodeURIComponent(skuSearchQuery)}&enabledProducts=${enabledProducts}&exportToMagento=${exportToMagento}`;
    });
    
    // Update details form submission
    $(document).on("submit" , '#updateDetailsForm' , function(e){
      e.preventDefault();
      var form = $(this),
        formData = form.serialize(),
        validToSubmit = true;

      $.each(form.find(".requiredField") , function(index , value){
        if($(value).val()==null || $(value).val().length==0){
          validToSubmit = false;
        }
      });

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
    });

    // Export to POS
    $("#exportToPos").click(function() {
      var searchQuery = $("#searchQuery").val(),
          skuSearchQuery = $("#skuSearchQuery").val(),
          enabledProducts = $("#enabledProducts")[0].checked ? 1 : 0,
          exportToMagento = $("#exportToMagentoFilter")[0].checked ? 1 : 0;

      window.location.href = `./php/export_pos.php?searchQuery=${encodeURIComponent(searchQuery)}&skuSearchQuery=${encodeURIComponent(skuSearchQuery)}&enabledProducts=${enabledProducts}&exportToMagento=${exportToMagento}`;
    });

    // Admin columns toggle
    $('#showAdminColumns').change(function() {
        if($(this).is(':checked')) {
            $('.table').addClass('show-admin-columns');
        } else {
            $('.table').removeClass('show-admin-columns');
        }
    });

    // Stock status instant update
    $(document).on('change', '.stock-checkbox', function(e) {
        var checkbox = $(this);
        var sku = checkbox.data('sku');
        var stockStatus = checkbox.is(':checked') ? 1 : 0;
        
        checkbox.prop('disabled', true);
        
        $.ajax({
            type: 'POST',
            url: 'php/update_stock_status.php',
            data: {
                sku: sku,
                stock_status: stockStatus
            },
            success: function(response) {
                if (response === 'updated') {
                    checkbox.closest('tr').addClass('table-success');
                    setTimeout(function() {
                        checkbox.closest('tr').removeClass('table-success');
                    }, 1000);
                } else if (response.includes('Insufficient permissions')) {
                    checkbox.prop('checked', !checkbox.is(':checked'));
                    Swal.fire(
                        'Access Denied',
                        'You do not have permission to modify stock status.',
                        'error'
                    );
                } else {
                    checkbox.prop('checked', !checkbox.is(':checked'));
                    Swal.fire(
                        'Error',
                        'Failed to update stock status: ' + response,
                        'error'
                    );
                }
            },
            error: function(xhr, status, error) {
                checkbox.prop('checked', !checkbox.is(':checked'));
                Swal.fire(
                    'Error',
                    'Failed to update stock status. Please try again.',
                    'error'
                );
            },
            complete: function() {
                checkbox.prop('disabled', false);
            }
        });
    });

    // Calculations functionality
    function calculations(){
        var activePopup = $(".modal.show");
        var cost = parseFloat(activePopup.find(".cost").val());
        var pricingCost = parseFloat(activePopup.find(".pricing_cost").val());
        var vatScheme = parseFloat(activePopup.find(".vatScheme option:selected").attr("data-tax_rate"));
        var pricingMethod = activePopup.find(".pricing_method").val();
        var fieldValue = 0;
        var fieldID = '';

        if(!$.isNumeric(cost)) cost = 0;
        if(!$.isNumeric(pricingCost)) pricingCost = 0;
        if(!$.isNumeric(vatScheme)) vatScheme = 0;
              
        $.each(activePopup.find(".calculationField") , function(index , value){
          fieldValue = parseFloat($(value).val());
          if(activePopup.attr("id")=='updateRecordPopup' || activePopup.attr("id")=='productPopup'){
            fieldID = $(value).attr("id");
            fieldID = fieldID.replace("u-", "");
          }else{
            fieldID = $(value).attr("id");
          }
          
          if(!$.isNumeric(fieldValue)) fieldValue = 0;
          
          var profit = 0, percent = 0, incVatPrice = 0;
          
          if(fieldID=='fixedRetailPricing' || fieldID=='fixedTradePricing') pricingMethod = 0;
          
          if(fieldID=='retailMarkup' || fieldID=='tradeMarkup') {
            fieldValue = fieldValue/100;
            if(pricingMethod==0){
              profit = (fieldValue*cost).toFixed(2) ;
              incVatPrice = ((cost+(cost*fieldValue))*(1+vatScheme)).toFixed(2) ;
              activePopup.find(".incVatPrice").show();
            }else if(pricingMethod==1){
              profit = (fieldValue*pricingCost).toFixed(2) ;
              incVatPrice = ((pricingCost+(pricingCost*fieldValue))*(1+vatScheme)).toFixed(2) ;
              activePopup.find(".incVatPrice").show();
            }else if(pricingMethod==2){
              profit = (fieldValue*cost).toFixed(2) ;
              activePopup.find(".incVatPrice").hide();
            }
          }else{
            if(pricingMethod==0){
              profit = ((fieldValue/(1+vatScheme))-cost).toFixed(2) ;
              percent = ((profit/cost) * 100).toFixed(2) ;
            }else if(pricingMethod==1){
              profit = ((fieldValue/(1+vatScheme))-pricingCost).toFixed(2) ;
              percent = ((profit / pricingCost) * 100).toFixed(2) ;
            }else if(pricingMethod==2){
              profit = ((fieldValue/(1+vatScheme)-cost)).toFixed(2) ;
              percent = ((profit/cost) * 100).toFixed(2) ;
            }
          }
          
          if($.isNumeric(fieldValue) && fieldValue!=0){
            if(fieldID=='targetRetail'){
              activePopup.find(".targetRetailPercent").html(percent);
              activePopup.find(".targetRetailProfit").html(profit); 
            }else if(fieldID=='targetTrade'){
              activePopup.find(".targetTradePercent").html(percent);
              activePopup.find(".targetTradeProfit").html(profit);
            }else if(fieldID=='retailMarkup'){
              activePopup.find(".retailMarkupProfit").html(profit);
              activePopup.find(".retailMarkupIncVatPrice").html(incVatPrice);
              activePopup.find(".retailIncVat").val(incVatPrice);
            }else if(fieldID=='tradeMarkup'){
              activePopup.find(".tradeMarkupProfit").html(profit);
              activePopup.find(".tradeMarkupIncVatPrice").html(incVatPrice);
              activePopup.find(".tradeIncVat").val(incVatPrice);
            }else if(fieldID=='fixedRetailPricing'){
              activePopup.find(".fixedRetailPricingProfit").html(profit);
            }else if(fieldID=='fixedTradePricing'){
              activePopup.find(".fixedTradePricingProfit").html(profit);
            }
          }
        });
    }

    $(document).on('change' , '.doCalculations' , function(e){
        e.preventDefault();
        calculations();
    });
    
    $(document).on('change' , '.pricing_method' , function(e){
        var activePopup = $(".modal.show");
        if($(this).val()==2){
          activePopup.find(".fixedPriceMethod").show();
          activePopup.find(".markupPriceMehtod").hide();
        }else{
          activePopup.find(".markupPriceMehtod").show();
          activePopup.find(".fixedPriceMethod").hide();
        }
    });
    
});