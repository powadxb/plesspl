// assets/js/pc_quote/ui-handlers.js

let currentEditingSection = null;
let isEditingAdditionalItem = false;
let currentAdditionalItemId = null;
let additionalItemsData = {};
let additionalItemCounter = 0;

$(document).ready(function() {
    initializeUIHandlers();
});

function initializeUIHandlers() {
    initializeModals();
    initializeComponentHandlers();
    initializeAdditionalItems();
    initializeActionButtons();
    initializeCustomerSearch();
}

function initializeModals() {
    $('#searchModal').on('hidden.bs.modal', function() {
        $('#productSearchInput').val('');
        $('#searchResultsBody').empty();
    });

    $('#manualEntryModal').on('hidden.bs.modal', function() {
        $('#manualProductName').val('');
        $('#manualProductPrice').val('');
    });

    $('#manualProductName, #manualProductPrice').on('keypress', function(e) {
        if (e.which === 13) {
            $('#saveManualEntry').click();
        }
    });
}

function initializeComponentHandlers() {
    // FIXED: Use event delegation properly for all component buttons
    $(document).on('click', '.component-section:not(.additional-item-row) .btn-search', function(e) {
        e.preventDefault();
        e.stopPropagation();
        currentEditingSection = $(this).closest('.component-section');
        isEditingAdditionalItem = false;
        currentAdditionalItemId = null;
        console.log('Opening search for component:', currentEditingSection.data('component'));
        $('#searchModal').modal('show');
    });

    $(document).on('click', '.component-section:not(.additional-item-row) .btn-manual', function(e) {
        e.preventDefault();
        e.stopPropagation();
        currentEditingSection = $(this).closest('.component-section');
        isEditingAdditionalItem = false;
        currentAdditionalItemId = null;
        console.log('Opening manual entry for component:', currentEditingSection.data('component'));
        $('#manualProductName').val('');
        $('#manualProductPrice').val('');
        $('#manualEntryModal').modal('show');
    });

    // FIXED: Improved remove button handler
    $(document).on('click', '.component-section:not(.additional-item-row) .btn-remove', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $section = $(this).closest('.component-section');
        const componentType = $section.data('component');
        console.log('Removing component:', componentType);
        
        const input = $section.find('.component-display');
        input.val('');
        input.removeData('sku');
        input.removeData('price_inc_vat');
        input.removeData('base_price');
        input.removeData('cost');
        input.removeData('isManual');
        
        $section.find('.component-details').html('');
        $(this).hide();
        
        // Reset RAM quantity to default if this is RAM
        if (componentType === 'ram') {
            $section.find('.ram-qty').val('2');
        }
        
        if (typeof window.quoteCalculations !== 'undefined') {
            window.quoteCalculations.updateTotals();
        }
    });

    $(document).on('click', '#saveManualEntry', function() {
        const name = $('#manualProductName').val().trim();
        const priceWithVAT = parseFloat($('#manualProductPrice').val()) || 0;
        
        if (!name) {
            Swal.fire('Error', 'Please enter a product name', 'error');
            return;
        }
        
        if (priceWithVAT <= 0) {
            Swal.fire('Error', 'Please enter a valid price', 'error');
            return;
        }
        
        if (isEditingAdditionalItem && currentAdditionalItemId) {
            // Save to additional item
            console.log('Saving manual entry to additional item:', currentAdditionalItemId);
            additionalItemsData[currentAdditionalItemId] = {
                name: name,
                priceWithVAT: priceWithVAT,
                basePrice: priceWithVAT / 1.2,
                cost: 0,
                sku: null,
                isManual: true
            };
            updateAdditionalItemDisplay(currentAdditionalItemId);
        } else if (currentEditingSection) {
            // Save to regular component
            console.log('Saving manual entry to component:', currentEditingSection.data('component'));
            addManualComponentToSection(currentEditingSection, name, priceWithVAT);
        }
        
        $('#manualProductName').val('');
        $('#manualProductPrice').val('');
        $('#manualEntryModal').modal('hide');
        
        // Reset flags
        currentEditingSection = null;
        isEditingAdditionalItem = false;
        currentAdditionalItemId = null;
    });

    $(document).on('click', '.select-product-btn', function() {
        const product = $(this).data('product');
        selectProductFromSearch(product);
    });
}

function addManualComponentToSection($section, name, priceWithVAT) {
    const input = $section.find('.component-display');
    const basePrice = priceWithVAT / 1.2;
    
    input.val(name);
    input.data('sku', null);
    input.data('price_inc_vat', priceWithVAT);
    input.data('base_price', basePrice);
    input.data('cost', 0);
    input.data('isManual', true);
    
    $section.find('.btn-remove').show();
    $section.find('.component-details').html(`£${priceWithVAT.toFixed(2)} (Manual)`);
    
    if (typeof window.quoteCalculations !== 'undefined') {
        window.quoteCalculations.updateTotals();
    }
}

function selectProductFromSearch(product) {
    const priceType = $('.price-type-toggle .btn.active').data('type') || 'R';
    
    let basePrice = parseFloat(product.price || 0);
    if (product.retail_price && product.trade_price) {
        basePrice = priceType === 'R' ? parseFloat(product.retail_price) : parseFloat(product.trade_price);
    }
    
    const priceWithVAT = basePrice * 1.2;
    const cost = parseFloat(product.cost || 0);
    
    // FIXED: Better logging to debug
    console.log('Selecting product:', {
        name: product.name,
        isEditingAdditionalItem: isEditingAdditionalItem,
        currentAdditionalItemId: currentAdditionalItemId,
        currentEditingSection: currentEditingSection ? currentEditingSection.data('component') : null
    });
    
    // CHECK ADDITIONAL ITEM FIRST
    if (isEditingAdditionalItem && currentAdditionalItemId) {
        console.log('Adding to additional item:', currentAdditionalItemId);
        additionalItemsData[currentAdditionalItemId] = {
            name: product.name || product.product_name,
            priceWithVAT: priceWithVAT,
            basePrice: basePrice,
            cost: cost,
            sku: product.sku || product.product_sku,
            isManual: false
        };
        updateAdditionalItemDisplay(currentAdditionalItemId);
        
        // Reset flags
        isEditingAdditionalItem = false;
        currentAdditionalItemId = null;
        currentEditingSection = null;
    } 
    // THEN CHECK REGULAR COMPONENT
    else if (currentEditingSection && currentEditingSection.length > 0) {
        console.log('Adding to component:', currentEditingSection.data('component'));
        const input = currentEditingSection.find('.component-display');
        input.val(product.name || product.product_name);
        input.data('sku', product.sku || product.product_sku);
        input.data('price_inc_vat', priceWithVAT);
        input.data('base_price', basePrice);
        input.data('cost', cost);
        input.data('isManual', false);
        
        currentEditingSection.find('.btn-remove').show();
        currentEditingSection.find('.component-details').html(`£${priceWithVAT.toFixed(2)}`);
        
        // Reset
        currentEditingSection = null;
    } else {
        console.warn('No target set for product selection!');
        Swal.fire('Error', 'Could not determine where to add the product. Please try again.', 'error');
        return;
    }
    
    $('#searchModal').modal('hide');
    
    if (typeof window.quoteCalculations !== 'undefined') {
        window.quoteCalculations.updateTotals();
    }
}

function initializeAdditionalItems() {
    $('#addItemBtn').click(function() {
        addNewAdditionalItem();
    });

    $(document).on('click', '.additional-search-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const itemId = $(this).data('item-id');
        console.log('Opening search for additional item:', itemId);
        currentAdditionalItemId = itemId;
        isEditingAdditionalItem = true;
        currentEditingSection = null;
        $('#searchModal').modal('show');
    });

    $(document).on('click', '.additional-manual-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const itemId = $(this).data('item-id');
        console.log('Opening manual entry for additional item:', itemId);
        currentAdditionalItemId = itemId;
        isEditingAdditionalItem = true;
        currentEditingSection = null;
        $('#manualProductName').val('');
        $('#manualProductPrice').val('');
        $('#manualEntryModal').modal('show');
    });

    $(document).on('click', '.additional-remove-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const itemId = $(this).data('item-id');
        console.log('Removing additional item:', itemId);
        delete additionalItemsData[itemId];
        $(this).closest('.additional-item-row').remove();
        
        if (typeof window.quoteCalculations !== 'undefined') {
            window.quoteCalculations.updateTotals();
        }
    });
}

function addNewAdditionalItem() {
    additionalItemCounter++;
    const itemId = 'item_' + Date.now() + '_' + additionalItemCounter;
    
    console.log('Creating new additional item:', itemId);
    
    const itemHtml = `
        <div class="additional-item-row" data-item-id="${itemId}">
            <div class="additional-item-content">
                <div class="additional-item-info">
                    <span class="item-name"><strong>Not selected</strong></span>
                    <span class="item-price text-muted">Click search to select</span>
                </div>
                <div class="additional-item-buttons">
                    <button type="button" class="btn btn-sm btn-primary additional-search-btn" data-item-id="${itemId}">🔍</button>
                    <button type="button" class="btn btn-sm btn-success additional-manual-btn" data-item-id="${itemId}">+</button>
                    <button type="button" class="btn btn-sm btn-danger additional-remove-btn" data-item-id="${itemId}">×</button>
                </div>
            </div>
        </div>
    `;
    
    $('#additionalItemsList').append(itemHtml);
    
    // FIXED: Don't initialize as null - initialize with empty object that will be populated
    additionalItemsData[itemId] = {
        name: null,
        priceWithVAT: 0,
        basePrice: 0,
        cost: 0,
        sku: null,
        isManual: false
    };
    
    // Auto-open search after a delay
    setTimeout(function() {
        currentAdditionalItemId = itemId;
        isEditingAdditionalItem = true;
        currentEditingSection = null;
        console.log('Auto-opening search for new item:', itemId);
        $('#searchModal').modal('show');
    }, 100);
}

function updateAdditionalItemDisplay(itemId) {
    const data = additionalItemsData[itemId];
    if (!data || !data.name) {
        console.warn('No data to display for item:', itemId);
        return;
    }
    
    console.log('Updating display for item:', itemId, data);
    
    const $row = $(`#additionalItemsList .additional-item-row[data-item-id="${itemId}"]`);
    
    if ($row.length === 0) {
        console.error('Could not find row for item:', itemId);
        return;
    }
    
    // FIXED: Update the HTML structure correctly
    $row.find('.item-name').html(`<strong>${data.name}</strong>`);
    $row.find('.item-price').removeClass('text-muted').text(`£${data.priceWithVAT.toFixed(2)}${data.isManual ? ' (Manual)' : ''}`);
    
    console.log('Display updated successfully for item:', itemId);
    
    if (typeof window.quoteCalculations !== 'undefined') {
        window.quoteCalculations.updateTotals();
    }
}

// FIXED: Filter out items that have no name (not yet selected)
window.getAdditionalItemsData = function() {
    const filtered = {};
    Object.keys(additionalItemsData).forEach(key => {
        const item = additionalItemsData[key];
        if (item && item.name !== null && item.name !== '') {
            filtered[key] = item;
        }
    });
    console.log('Getting additional items data:', filtered);
    return filtered;
};

function initializeActionButtons() {
    $('#saveQuoteBtn').click(function() {
        if ($(this).prop('disabled')) return;

        const validation = window.quoteCalculations.validateQuote();
        if (!validation.isValid) {
            showError('Cannot Save Quote', validation.errors);
            return;
        }

        saveQuote();
    });

    $('#printQuoteBtn').click(function() {
        if ($(this).prop('disabled')) return;

        const validation = window.quoteCalculations.validateQuote();
        if (!validation.isValid) {
            showError('Cannot Print Quote', validation.errors);
            return;
        }

        printQuote();
    });

    $('#createOrderBtn').click(function() {
        if ($(this).prop('disabled')) return;

        const validation = window.quoteCalculations.validateQuote();
        if (!validation.isValid) {
            showError('Cannot Create Order', validation.errors);
            return;
        }

        createOrder();
    });

    $('#newCustomerBtn').click(function() {
        showNewCustomerForm();
    });
}

function initializeCustomerSearch() {
    $('#customerSearch').on('keypress', function(e) {
        if (e.which === 13) {
            searchCustomers();
        }
    });

    $('#searchCustomerBtn').click(function() {
        searchCustomers();
    });
}

function searchCustomers() {
    const searchTerm = $('#customerSearch').val();
    if (searchTerm.length < 2) {
        Swal.fire('Error', 'Please enter at least 2 characters to search', 'error');
        return;
    }

    $.ajax({
        url: 'ajax/search_customers.php',
        method: 'POST',
        data: { search: searchTerm },
        success: function(response) {
            try {
                const results = JSON.parse(response);
                if (results.length === 0) {
                    Swal.fire('No Results', 'No customers found matching your search', 'info');
                    return;
                }
                showCustomerSearchResults(results);
            } catch (e) {
                Swal.fire('Error', 'Failed to process customer search results', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to search for customers', 'error');
        }
    });
}

function showCustomerSearchResults(customers) {
    let html = `<div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Postcode</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>`;
    
    customers.forEach(customer => {
        const customerData = encodeURIComponent(JSON.stringify(customer));
        html += `
            <tr>
                <td>${customer.name || ''}</td>
                <td>${customer.phone || customer.mobile || ''}</td>
                <td>${customer.email || ''}</td>
                <td>${customer.post_code || ''}</td>
                <td>
                    <button class="btn btn-sm btn-success select-customer" 
                            data-customer-info="${customerData}">
                        Select
                    </button>
                </td>
            </tr>`;
    });

    html += `</tbody></table></div>`;

    Swal.fire({
        title: 'Select Customer',
        html: html,
        width: '800px',
        showConfirmButton: false,
        showCloseButton: true
    });

    $('.select-customer').click(function() {
        const customerData = $(this).data('customer-info');
        const customer = JSON.parse(decodeURIComponent(customerData));
        selectCustomer(customer);
        Swal.close();
    });
}

function selectCustomer(customer) {
    $('#customerSearch').val(customer.name);

    const detailsHtml = `
        <strong>${customer.name}</strong><br>
        ${customer.phone ? `Phone: ${customer.phone}<br>` : ''}
        ${customer.mobile ? `Mobile: ${customer.mobile}<br>` : ''}
        ${customer.email ? `Email: ${customer.email}<br>` : ''}
        ${customer.address ? `Address: ${customer.address}<br>` : ''}
        ${customer.post_code ? `Postcode: ${customer.post_code}` : ''}
    `;

    const customerDetails = $('#customerDetails');
    customerDetails.html(detailsHtml);
    customerDetails.show();

    customerDetails.data({
        customerId: customer.id,
        customerName: customer.name,
        customerEmail: customer.email,
        customerPhone: customer.phone || customer.mobile,
        customerAddress: customer.address
    });
}

function showNewCustomerForm() {
    Swal.fire({
        title: 'New Customer',
        html: `
            <form id="newCustomerForm">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="newCustomerName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="newCustomerPhone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="newCustomerEmail" class="form-control">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea id="newCustomerAddress" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Postcode</label>
                    <input type="text" id="newCustomerPostcode" class="form-control">
                </div>
            </form>`,
        showCancelButton: true,
        confirmButtonText: 'Save Customer',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const customerData = {
                name: $('#newCustomerName').val(),
                phone: $('#newCustomerPhone').val(),
                email: $('#newCustomerEmail').val(),
                address: $('#newCustomerAddress').val(),
                post_code: $('#newCustomerPostcode').val()
            };

            return $.ajax({
                url: 'ajax/save_customer.php',
                method: 'POST',
                data: customerData,
                dataType: 'json'
            }).then(response => {
                if (response.success) {
                    return response;
                }
                throw new Error(response.message || 'Failed to save customer');
            }).catch(error => {
                Swal.showValidationMessage(`Request failed: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value.customer) {
            selectCustomer(result.value.customer);
        }
    });
}

function saveQuote() {
    const quoteData = window.quoteCalculations.getQuoteData();
    
    $.ajax({
        url: 'ajax/save_quote.php',
        method: 'POST',
        data: { quote: JSON.stringify(quoteData) },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'Success',
                    text: 'Quote saved successfully',
                    icon: 'success'
                }).then(() => {
                    if (response.quoteId) {
                        window.location.href = `view_quote.php?id=${response.quoteId}`;
                    }
                });
            } else {
                showError('Error', response.message || 'Failed to save quote');
            }
        },
        error: function(xhr, status, error) {
            showError('Error', 'Failed to save quote: ' + error);
        }
    });
}

function printQuote() {
    const quoteData = window.quoteCalculations.getQuoteData();
    
    $.ajax({
        url: 'ajax/get_quote_print.php',
        method: 'POST',
        data: { quote: JSON.stringify(quoteData) },
        success: function(response) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(response);
            printWindow.document.close();
            printWindow.focus();
            printWindow.onload = function() {
                printWindow.print();
            };
        },
        error: function(xhr, status, error) {
            showError('Error', 'Failed to generate printable quote: ' + error);
        }
    });
}

function createOrder() {
    const quoteData = window.quoteCalculations.getQuoteData();
    
    Swal.fire({
        title: 'Create Order',
        text: 'Are you sure you want to create an order from this quote?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, create order',
        cancelButtonText: 'No, cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/create_order.php',
                method: 'POST',
                data: { quote: JSON.stringify(quoteData) },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            Swal.fire({
                                title: 'Success',
                                text: 'Order created successfully',
                                icon: 'success'
                            }).then(() => {
                                if (result.orderId) {
                                    window.location.href = `view_order.php?id=${result.orderId}`;
                                }
                            });
                        } else {
                            throw new Error(result.message || 'Failed to create order');
                        }
                    } catch (e) {
                        showError('Error', e.message);
                    }
                },
                error: function(xhr, status, error) {
                    showError('Error', 'Failed to create order: ' + error);
                }
            });
        }
    });
}

function showError(title, errors) {
    const errorHtml = Array.isArray(errors) ? errors.join('<br>') : errors;
    Swal.fire({
        title: title,
        html: errorHtml,
        icon: 'error'
    });
}

console.log('✓ UI Handlers loaded');