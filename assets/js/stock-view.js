/**
 * Stock View JavaScript
 * All functionality for the stock view page
 */

// Updated CSV Export with Password Protection
document.getElementById('exportToCsv')?.addEventListener('click', function () {
    // Show password prompt using SweetAlert
    Swal.fire({
        title: 'Admin Authorization Required',
        text: 'Enter the export password to download CSV:',
        input: 'password',
        inputPlaceholder: 'Export Password',
        showCancelButton: true,
        confirmButtonText: 'Export CSV',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        inputValidator: (value) => {
            if (!value) {
                return 'Password is required!'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const password = result.value;
            
            // Get all current filter values
            const searchQuery = document.getElementById('searchQuery').value;
            const skuSearchQuery = document.getElementById('skuSearchQuery').value;
            const enabledProds = document.getElementById('enabledProducts').checked ? 1 : 0;
            const wwwChecked = document.getElementById('wwwFilter').checked ? 1 : 0;
            const categoryFilter = document.getElementById('categoryFilter').value;
            
            // Get stock filter values
            const csNegativeStock = document.getElementById('csNegativeStock').checked ? 1 : 0;
            const csZeroStock = document.getElementById('csZeroStock').checked ? 1 : 0;
            const csAboveStock = document.getElementById('csAboveStock').checked ? 1 : 0;
            const csAboveValue = document.getElementById('csAboveValue').value;
            const csBelowStock = document.getElementById('csBelowStock').checked ? 1 : 0;
            const csBelowValue = document.getElementById('csBelowValue').value;
            
            const asNegativeStock = document.getElementById('asNegativeStock').checked ? 1 : 0;
            const asZeroStock = document.getElementById('asZeroStock').checked ? 1 : 0;
            const asAboveStock = document.getElementById('asAboveStock').checked ? 1 : 0;
            const asAboveValue = document.getElementById('asAboveValue').value;
            const asBelowStock = document.getElementById('asBelowStock').checked ? 1 : 0;
            const asBelowValue = document.getElementById('asBelowValue').value;
            
            const sortCol = document.getElementById('sortCol').value;
            
            // Create form data to send via POST
            const formData = new FormData();
            formData.append('export_password', password);
            formData.append('searchQuery', searchQuery);
            formData.append('skuSearchQuery', skuSearchQuery);
            formData.append('enabledProducts', enabledProds);
            formData.append('wwwFilter', wwwChecked);
            formData.append('category', categoryFilter);
            formData.append('csNegativeStock', csNegativeStock);
            formData.append('csZeroStock', csZeroStock);
            formData.append('csAboveStock', csAboveStock);
            formData.append('csAboveValue', csAboveValue);
            formData.append('csBelowStock', csBelowStock);
            formData.append('csBelowValue', csBelowValue);
            formData.append('asNegativeStock', asNegativeStock);
            formData.append('asZeroStock', asZeroStock);
            formData.append('asAboveStock', asAboveStock);
            formData.append('asAboveValue', asAboveValue);
            formData.append('asBelowStock', asBelowStock);
            formData.append('asBelowValue', asBelowValue);
            formData.append('sortCol', sortCol);
            
            // Submit form to export_csv.php
            fetch('export_csv.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                } else {
                    return response.text().then(text => {
                        throw new Error(text);
                    });
                }
            })
            .then(blob => {
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'stock_export_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Export Complete',
                    text: 'Your CSV file has been downloaded successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Export Failed',
                    text: error.message || 'An error occurred during export.'
                });
            });
        }
    });
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

    // CALCULATION FUNCTIONS - This was missing!
    // ==================================================
    
    // Function to perform calculations
    function calculations() {
        // Get the active form (could be new item or update form)
        let $form;
        if ($('#newItemForm').is(':visible')) {
            $form = $('#newItemForm');
        } else if ($('#updateDetailsForm').length) {
            $form = $('#updateDetailsForm');
        } else {
            return; // No form found
        }
        
        // Get values
        const cost = parseFloat($form.find('.cost').val()) || 0;
        const pricingCost = parseFloat($form.find('.pricing_cost').val()) || 0;
        const pricingMethod = parseInt($form.find('.pricing_method').val()) || 1;
        
        // Get VAT rate - try multiple methods to ensure we get it
        let taxRate = 0.2; // Default to 20%
        const vatSchemeElement = $form.find('.vatScheme');
        if (vatSchemeElement.length) {
            const selectedOption = vatSchemeElement.find('option:selected');
            if (selectedOption.length && selectedOption.data('tax_rate')) {
                taxRate = parseFloat(selectedOption.data('tax_rate'));
            } else if (selectedOption.length && selectedOption.attr('data-tax_rate')) {
                taxRate = parseFloat(selectedOption.attr('data-tax_rate'));
            }
        }
        
        console.log('Tax Rate being used:', taxRate); // Debug line - you can remove this later
        
        const retailMarkup = parseFloat($form.find('.retailMarkup').val()) || 0;
        const tradeMarkup = parseFloat($form.find('.tradeMarkup').val()) || 0;
        
        const targetRetail = parseFloat($form.find('.targetRetail').val()) || 0;
        const targetTrade = parseFloat($form.find('.targetTrade').val()) || 0;
        
        const fixedRetailPricing = parseFloat($form.find('.fixedRetailPricing').val()) || 0;
        const fixedTradePricing = parseFloat($form.find('.fixedTradePricing').val()) || 0;
        
        // Base cost for calculations (depends on pricing method)
        const baseCost = (pricingMethod === 0) ? cost : pricingCost;
        
        // TARGET PRICING CALCULATIONS (always calculate these)
        if (targetRetail > 0 && baseCost > 0) {
            const targetRetailExVat = targetRetail / (1 + taxRate);
            const targetRetailProfit = targetRetailExVat - baseCost;
            const targetRetailPercent = ((targetRetailExVat - baseCost) / baseCost) * 100;
            
            $form.find('.targetRetailProfit').text(targetRetailProfit.toFixed(2));
            $form.find('.targetRetailPercent').text(targetRetailPercent.toFixed(2));
        } else {
            $form.find('.targetRetailProfit').text('0.00');
            $form.find('.targetRetailPercent').text('0.00');
        }
        
        if (targetTrade > 0 && baseCost > 0) {
            const targetTradeExVat = targetTrade / (1 + taxRate);
            const targetTradeProfit = targetTradeExVat - baseCost;
            const targetTradePercent = ((targetTradeExVat - baseCost) / baseCost) * 100;
            
            $form.find('.targetTradeProfit').text(targetTradeProfit.toFixed(2));
            $form.find('.targetTradePercent').text(targetTradePercent.toFixed(2));
        } else {
            $form.find('.targetTradeProfit').text('0.00');
            $form.find('.targetTradePercent').text('0.00');
        }
        
        // PRICING METHOD CALCULATIONS
        if (pricingMethod === 2) { // Fixed Price Method
            if (fixedRetailPricing > 0 && baseCost > 0) {
                const retailProfit = fixedRetailPricing - baseCost;
                $form.find('.fixedRetailPricingProfit').text(retailProfit.toFixed(2));
                // Set hidden value for final retail inc VAT
                $form.find('.retailIncVat').val((fixedRetailPricing * (1 + taxRate)).toFixed(2));
            }
            
            if (fixedTradePricing > 0 && baseCost > 0) {
                const tradeProfit = fixedTradePricing - baseCost;
                $form.find('.fixedTradePricingProfit').text(tradeProfit.toFixed(2));
                // Set hidden value for final trade inc VAT
                $form.find('.tradeIncVat').val((fixedTradePricing * (1 + taxRate)).toFixed(2));
            }
            
        } else { // Markup Method (0 = markup on cost, 1 = markup on pricing cost)
            
            // RETAIL MARKUP CALCULATIONS
            if (retailMarkup > 0 && baseCost > 0) {
                const retailPrice = baseCost * (1 + retailMarkup / 100);
                const retailProfit = retailPrice - baseCost;
                const retailIncVat = retailPrice * (1 + taxRate);
                
                $form.find('.retailMarkupProfit').text(retailProfit.toFixed(2));
                $form.find('.retailMarkupIncVatPrice').text(retailIncVat.toFixed(2));
                
                // Set hidden value for final retail inc VAT
                $form.find('.retailIncVat').val(retailIncVat.toFixed(2));
            } else {
                $form.find('.retailMarkupProfit').text('0.00');
                $form.find('.retailMarkupIncVatPrice').text('0.00');
                $form.find('.retailIncVat').val('0.00');
            }
            
            // TRADE MARKUP CALCULATIONS
            if (tradeMarkup > 0 && baseCost > 0) {
                const tradePrice = baseCost * (1 + tradeMarkup / 100);
                const tradeProfit = tradePrice - baseCost;
                const tradeIncVat = tradePrice * (1 + taxRate);
                
                $form.find('.tradeMarkupProfit').text(tradeProfit.toFixed(2));
                $form.find('.tradeMarkupIncVatPrice').text(tradeIncVat.toFixed(2));
                
                // Set hidden value for final trade inc VAT
                $form.find('.tradeIncVat').val(tradeIncVat.toFixed(2));
            } else {
                $form.find('.tradeMarkupProfit').text('0.00');
                $form.find('.tradeMarkupIncVatPrice').text('0.00');
                $form.find('.tradeIncVat').val('0.00');
            }
        }
    }
    
    // Event handlers for calculations
    $(document).on('input change', '.doCalculations', function() {
        calculations();
    });
    
    // Pricing method change handler
    $(document).on('change', '.pricing_method', function() {
        const $form = $(this).closest('form');
        const method = parseInt($(this).val());
        
        if (method === 2) { // Fixed Price
            $form.find('.markupPriceMehtod').hide();
            $form.find('.fixedPriceMethod').show();
            $form.find('.incVatPrice').hide();
        } else { // Markup
            $form.find('.markupPriceMehtod').show();
            $form.find('.fixedPriceMethod').hide();
            $form.find('.incVatPrice').show();
        }
        calculations();
    });
    
    // Target pricing handlers - DON'T auto-populate markup fields, just show calculations
    // The user should manually enter their desired markup percentages
    $(document).on('input', '.targetRetail', function() {
        calculations(); // Just recalculate to show profit and markup %
    });
    
    $(document).on('input', '.targetTrade', function() {
        calculations(); // Just recalculate to show profit and markup %
    });

    // End Calculation Functions
    // ==================================================

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

    // Updated Add Selected to Count (Admin only) - with session support
    $('#addSelectedToCount').click(function() {
        const selectedSkus = [];
        $('.count-checkbox:checked').each(function() {
            selectedSkus.push($(this).val());
        });
        
        if (selectedSkus.length === 0) {
            Swal.fire('No Selection', 'Please select items to add to the counting queue.', 'warning');
            return;
        }
        
        // Get active sessions first
        getActiveSessionsForSelection(selectedSkus);
    });
    
    // Updated Add All Filtered to Count (Admin only) - with session support
    $('#addAllToCount').click(function() {
        // Get current filter parameters
        const searchQuery = $("#searchQuery").val();
        const skuSearch = $("#skuSearchQuery").val();
        const enabled = $("#enabledProducts").is(':checked') ? 'true' : '';
        const www = $("#wwwFilter").is(':checked') ? 'true' : '';
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

        // Show session selection dialog
        getActiveSessionsForFiltered({
            search_query: searchQuery,
            sku_search_query: skuSearch,
            enabled_products: enabled,
            www_filter: www,
            category: selectedCategory,
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
        });
    });
    
    // Function to get active sessions and show selection dialog
    function getActiveSessionsForSelection(selectedSkus) {
        $.ajax({
            url: 'php/manage_count_queue.php',
            type: 'POST',
            data: { action: 'get_active_sessions' },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showSessionSelectionDialog(result.sessions, 'selected', selectedSkus);
                    } else {
                        Swal.fire('Error', 'Failed to load active sessions', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Invalid response from server', 'error');
                }
            }
        });
    }
    
    function getActiveSessionsForFiltered(filterData) {
        $.ajax({
            url: 'php/manage_count_queue.php',
            type: 'POST',
            data: { action: 'get_active_sessions' },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showSessionSelectionDialog(result.sessions, 'filtered', filterData);
                    } else {
                        Swal.fire('Error', 'Failed to load active sessions', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Invalid response from server', 'error');
                }
            }
        });
    }
    
    // Function to show session selection dialog - FIXED VERSION with Location Selection
    function showSessionSelectionDialog(sessions, type, data) {
        let sessionOptions = '<option value="">Create New Session</option>';
        sessions.forEach(session => {
            const locationText = session.location ? ` (${session.location.toUpperCase()})` : '';
            sessionOptions += `<option value="${session.id}">${session.name}${locationText} (${session.pending_items} pending)</option>`;
        });
        
        const itemCount = type === 'selected' ? data.length : 'all filtered';
        
        Swal.fire({
            title: 'Select Count Session',
            html: `
                <div style="text-align: left; margin-bottom: 1rem;">
                    <p>Adding <strong>${itemCount}</strong> items to counting queue.</p>
                    <p>Choose an existing session or create a new one:</p>
                </div>
                <select id="sessionSelect" class="swal2-input" style="margin-bottom: 1rem; display: block;">
                    ${sessionOptions}
                </select>
                <div id="newSessionFields">
                    <input id="newSessionName" class="swal2-input" placeholder="New session name (required)" style="margin-bottom: 0.5rem;">
                    
                    <div style="margin-bottom: 0.5rem;">
                        <select id="newSessionLocation" class="swal2-input" style="margin-bottom: 0.25rem;">
                            <option value="">Select Target Location</option>
                            <option value="cs">CS (Commerce St)</option>
                            <option value="as">AS (Argyle St)</option>
                        </select>
                        <small style="color: #6b7280; font-size: 0.75rem; display: block;">
                            Choose which location this count session will target
                        </small>
                    </div>
                    
                    <textarea id="newSessionDesc" class="swal2-textarea" placeholder="Session description (optional)" rows="3"></textarea>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Add to Queue',
            width: '500px',
            preConfirm: () => {
                const sessionId = document.getElementById('sessionSelect').value;
                const newSessionName = document.getElementById('newSessionName').value;
                const newSessionLocation = document.getElementById('newSessionLocation').value;
                const newSessionDesc = document.getElementById('newSessionDesc').value;
                
                if (!sessionId) {
                    // Creating new session
                    if (!newSessionName.trim()) {
                        Swal.showValidationMessage('Please enter a session name');
                        return false;
                    }
                    if (!newSessionLocation) {
                        Swal.showValidationMessage('Please select a target location for the new session');
                        return false;
                    }
                }
                
                return {
                    sessionId: sessionId,
                    newSessionName: newSessionName.trim(),
                    newSessionLocation: newSessionLocation,
                    newSessionDesc: newSessionDesc.trim()
                };
            },
            didOpen: () => {
                const sessionSelect = document.getElementById('sessionSelect');
                const newSessionFields = document.getElementById('newSessionFields');
                const newSessionName = document.getElementById('newSessionName');
                
                // Function to toggle fields visibility
                function toggleFields() {
                    if (sessionSelect.value === '') {
                        // Creating new session - show all fields
                        newSessionFields.style.display = 'block';
                        newSessionName.focus();
                    } else {
                        // Using existing session - hide new session fields
                        newSessionFields.style.display = 'none';
                    }
                }
                
                // Initial state - since default is "Create New Session", show the fields
                newSessionFields.style.display = 'block';
                
                // Add event listener for changes
                sessionSelect.addEventListener('change', toggleFields);
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (type === 'selected') {
                    addItemsToCountQueue(data, result.value);
                } else {
                    addFilteredToCountQueue(data, result.value);
                }
            }
        });
    }
    
    // Function to add selected items to count queue
    function addItemsToCountQueue(skus, sessionData) {
        let requestData = {
            action: 'add_items',
            skus: skus
        };
        
        // Handle session creation or selection
        if (sessionData.sessionId) {
            console.log('Using existing session:', sessionData.sessionId); // ADD THIS
            requestData.session_id = sessionData.sessionId;
            executeAddToQueue(requestData, skus.length);
        } else {
            console.log('Creating new session with data:', sessionData); // ADD THIS
            // Create new session first
            $.ajax({
                url: 'php/manage_count_queue.php',
                type: 'POST',
                data: {
                    action: 'create_session',
                    name: sessionData.newSessionName,
                    description: sessionData.newSessionDesc,
                    location: sessionData.newSessionLocation
                },
                success: function(response) {
                    console.log('Session creation response:', response); // ADD THIS
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            console.log('Session created with ID:', result.session_id); // ADD THIS
                            requestData.session_id = result.session_id;
                            executeAddToQueue(requestData, skus.length);
                        } else {
                            Swal.fire('Error', 'Failed to create session: ' + result.error, 'error');
                        }
                    } catch (e) {
                        console.log('Parse error:', e, 'Raw response:', response); // ADD THIS
                        Swal.fire('Error', 'Invalid response when creating session', 'error');
                    }
                }
            });
        }
    }
    
    // Function to add filtered items to count queue
    function addFilteredToCountQueue(filterData, sessionData) {
        let requestData = {
            action: 'add_all_filtered',
            ...filterData
        };
        
        // Handle session creation or selection
        if (sessionData.sessionId) {
            requestData.session_id = sessionData.sessionId;
            executeAddFilteredToQueue(requestData);
        } else {
            // Create new session first
            $.ajax({
                url: 'php/manage_count_queue.php',
                type: 'POST',
                data: {
                    action: 'create_session',
                    name: sessionData.newSessionName,
                    description: sessionData.newSessionDesc,
                    location: sessionData.newSessionLocation  // Now properly passes location
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            requestData.session_id = result.session_id;
                            executeAddFilteredToQueue(requestData);
                        } else {
                            Swal.fire('Error', 'Failed to create session: ' + result.error, 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error', 'Invalid response when creating session', 'error');
                    }
                }
            });
        }
    }
    
    // Execute add to queue for selected items
    function executeAddToQueue(requestData, itemCount) {
        $("#spinner").show();
        $.ajax({
            url: 'php/manage_count_queue.php',
            type: 'POST',
            data: requestData,
            success: function(response) {
                $("#spinner").hide();
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            title: 'Added Successfully',
                            text: `${result.added_count} items added to counting queue.`,
                            icon: 'success',
                            confirmButtonText: 'Go to Count Page'
                        }).then(() => {
                            // Uncheck all checkboxes
                            $('.count-checkbox, #selectAll').prop('checked', false);
                        });
                    } else {
                        Swal.fire('Error', 'Failed to add items to queue: ' + (result.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Invalid response from server: ' + response, 'error');
                }
            },
            error: function() {
                $("#spinner").hide();
                Swal.fire('Error', 'Failed to communicate with server', 'error');
            }
        });
    }
    
    // Execute add to queue for filtered items
    function executeAddFilteredToQueue(requestData) {
        $("#spinner").show();
        $.ajax({
            url: 'php/manage_count_queue.php',
            type: 'POST',
            data: requestData,
            success: function(response) {
                $("#spinner").hide();
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            title: 'Added Successfully',
                            text: `${result.added_count} items added to counting queue from ${result.total_found} filtered results.`,
                            icon: 'success'
                        });
                    } else {
                        Swal.fire('Error', 'Failed to add items to queue: ' + (result.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Invalid response from server: ' + response, 'error');
                }
            },
            error: function() {
                $("#spinner").hide();
                Swal.fire('Error', 'Failed to communicate with server', 'error');
            }
        });
    }

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

    // Handle enable/disable toggle - MODAL CONFIRMATION REMOVED
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
                    // SUCCESS - NO MODAL POPUP
                    // Silent update - you could add a subtle visual feedback here if desired
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

    // Handle WWW (export to magento) toggle - MODAL CONFIRMATION REMOVED
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
                    // SUCCESS - NO MODAL POPUP
                    // Silent update - you could add a subtle visual feedback here if desired
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
    
    // Select All functionality
    $('#selectAll').change(function() {
        $('.count-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // Update Select All when individual checkboxes change
    $(document).on('change', '.count-checkbox', function() {
        const totalCheckboxes = $('.count-checkbox').length;
        const checkedCheckboxes = $('.count-checkbox:checked').length;
        
        $('#selectAll').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        $('#selectAll').prop('checked', checkedCheckboxes === totalCheckboxes);
    });

});