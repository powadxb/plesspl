// assets/js/cctv_quote/ui-handlers.js

let currentEditingSection = null;
let currentMultiItemTarget = null;
let isEditingAdditionalItem = false;
let currentAdditionalItemId = null;
let additionalItemsData = {};
let additionalItemCounter = 0;
let multiItemCounters = {};

$(document).ready(function() {
    initializeUIHandlers();
});

function initializeUIHandlers() {
    initializeModals();
    initializeSingleComponentHandlers();
    initializeMultiComponentHandlers();
    initializeAdditionalItems();
    initializePriceEditing();
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
        $('#manualProductQty').val('1');
    });

    $('#manualProductName, #manualProductPrice').on('keypress', function(e) {
        if (e.which === 13) {
            $('#saveManualEntry').click();
        }
    });
}

// ==================== SINGLE COMPONENT HANDLERS ====================

function initializeSingleComponentHandlers() {
    // Search button for single components
    $(document).on('click', '.component-section:not([data-allow-multiple]) .btn-search', function(e) {
        e.preventDefault();
        e.stopPropagation();
        currentEditingSection = $(this).closest('.component-section');
        currentMultiItemTarget = null;
        isEditingAdditionalItem = false;
        currentAdditionalItemId = null;
        $('#searchModal').modal('show');
    });

    // Manual entry button for single components
    $(document).on('click', '.component-section:not([data-allow-multiple]) .btn-manual', function(e) {
        e.preventDefault();
        e.stopPropagation();
        currentEditingSection = $(this).closest('.component-section');
        currentMultiItemTarget = null;
        isEditingAdditionalItem = false;
        currentAdditionalItemId = null;
        $('#manualEntryModal').modal('show');
    });

    // Remove button for single components
    $(document).on('click', '.component-section:not([data-allow-multiple]) .btn-remove', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $section = $(this).closest('.component-section');
        clearSingleComponent($section);
    });

    // Product selection from search
    $(document).on('click', '.select-product-btn', function() {
        const product = $(this).data('product');
        selectProduct(product);
    });

    // Save manual entry
    $('#saveManualEntry').click(function() {
        saveManualEntry();
    });
}

function clearSingleComponent($section) {
    const input = $section.find('.component-display');
    const detailsDiv = $section.find('.component-details');
    
    input.val('');
    input.removeData();
    detailsDiv.html('');
    $section.find('.btn-remove').hide();
    
    // Reset quantity selector if exists
    const qtySelect = $section.find('.item-qty');
    if (qtySelect.length > 0) {
        qtySelect.val('1');
    }
    
    if (window.cctvQuoteCalculations) {
        window.cctvQuoteCalculations.updateTotals();
    }
}

function selectProduct(product) {
    const priceType = $('.price-type-toggle .active').data('type') || 'R';
    
    let basePrice = parseFloat(product.price || 0);
    if (product.retail_price && product.trade_price) {
        basePrice = priceType === 'R' ? parseFloat(product.retail_price) : parseFloat(product.trade_price);
    }
    
    const priceWithVAT = basePrice * 1.2;
    const cost = parseFloat(product.cost || 0);
    
    if (isEditingAdditionalItem && currentAdditionalItemId) {
        // Add to additional items
        additionalItemsData[currentAdditionalItemId] = {
            name: product.name,
            priceWithVAT: priceWithVAT,
            basePrice: basePrice,
            cost: cost,
            sku: product.sku,
            isManual: false
        };
        updateAdditionalItemDisplay(currentAdditionalItemId);
        resetEditingContext();
    } else if (currentMultiItemTarget) {
        // Add to multi-item list
        addMultiItem(currentMultiItemTarget, {
            name: product.name,
            sku: product.sku,
            priceIncVat: priceWithVAT,
            basePrice: basePrice,
            cost: cost,
            isManual: false
        });
        resetEditingContext();
    } else if (currentEditingSection) {
        // Add to single component
        addProductToSingleComponent(currentEditingSection, product, basePrice, priceWithVAT, cost);
        resetEditingContext();
    }
    
    $('#searchModal').modal('hide');
}

function addProductToSingleComponent($section, product, basePrice, priceWithVAT, cost) {
    const input = $section.find('.component-display');
    const detailsDiv = $section.find('.component-details');
    
    input.val(product.name);
    input.data('sku', product.sku);
    input.data('price_inc_vat', priceWithVAT);
    input.data('base_price', basePrice);
    input.data('cost', cost);
    input.data('isManual', false);
    
    // Show price with edit capability
    if (window.cctvSearch && window.cctvSearch.updatePriceDisplay) {
        window.cctvSearch.updatePriceDisplay(detailsDiv, priceWithVAT, cost, false);
    }
    
    $section.find('.btn-remove').show();
    
    if (window.cctvQuoteCalculations) {
        window.cctvQuoteCalculations.updateTotals();
    }
}

function saveManualEntry() {
    const name = $('#manualProductName').val().trim();
    const priceWithVAT = parseFloat($('#manualProductPrice').val()) || 0;
    const qty = parseInt($('#manualProductQty').val()) || 1;
    
    if (!name) {
        Swal.fire('Error', 'Please enter a product name', 'error');
        return;
    }
    
    if (priceWithVAT <= 0) {
        Swal.fire('Error', 'Please enter a valid price', 'error');
        return;
    }
    
    const basePrice = priceWithVAT / 1.2;
    
    if (isEditingAdditionalItem && currentAdditionalItemId) {
        additionalItemsData[currentAdditionalItemId] = {
            name: name,
            priceWithVAT: priceWithVAT,
            basePrice: basePrice,
            cost: 0,
            sku: null,
            isManual: true
        };
        updateAdditionalItemDisplay(currentAdditionalItemId);
        resetEditingContext();
    } else if (currentMultiItemTarget) {
        addMultiItem(currentMultiItemTarget, {
            name: name,
            sku: null,
            priceIncVat: priceWithVAT,
            basePrice: basePrice,
            cost: 0,
            isManual: true,
            quantity: qty
        });
        resetEditingContext();
    } else if (currentEditingSection) {
        const input = currentEditingSection.find('.component-display');
        const detailsDiv = currentEditingSection.find('.component-details');
        
        input.val(name);
        input.data('sku', null);
        input.data('price_inc_vat', priceWithVAT);
        input.data('base_price', basePrice);
        input.data('cost', 0);
        input.data('isManual', true);
        
        detailsDiv.html(`<div class="price-display"><span>£${priceWithVAT.toFixed(2)} (Manual)</span></div>`);
        currentEditingSection.find('.btn-remove').show();
        resetEditingContext();
        
        if (window.cctvQuoteCalculations) {
            window.cctvQuoteCalculations.updateTotals();
        }
    }
    
    $('#manualEntryModal').modal('hide');
}

function resetEditingContext() {
    currentEditingSection = null;
    currentMultiItemTarget = null;
    isEditingAdditionalItem = false;
    currentAdditionalItemId = null;
}

// ==================== MULTI-COMPONENT HANDLERS ====================

function initializeMultiComponentHandlers() {
    // Add multi-item button
    $(document).on('click', '.btn-add-multi', function(e) {
        e.preventDefault();
        const target = $(this).data('target');
        currentMultiItemTarget = target;
        currentEditingSection = null;
        isEditingAdditionalItem = false;
        currentAdditionalItemId = null;
        $('#searchModal').modal('show');
    });

    // Remove multi-item
    $(document).on('click', '.btn-remove-multi', function(e) {
        e.preventDefault();
        $(this).closest('.multi-item').remove();
        if (window.cctvQuoteCalculations) {
            window.cctvQuoteCalculations.updateTotals();
        }
    });
}

function addMultiItem(targetType, itemData) {
    if (!multiItemCounters[targetType]) {
        multiItemCounters[targetType] = 0;
    }
    multiItemCounters[targetType]++;
    
    const itemId = `${targetType}_${Date.now()}_${multiItemCounters[targetType]}`;
    const listId = getMultiListId(targetType);
    const qty = itemData.quantity || 1;
    
    const itemHtml = `
        <div class="multi-item" data-item-id="${itemId}">
            <div class="multi-item-header">
                <span class="multi-item-name">${itemData.name}</span>
                <div class="multi-item-controls">
                    <select class="form-control form-control-sm multi-item-qty">
                        ${generateQtyOptions(qty)}
                    </select>
                    <button class="btn btn-sm btn-danger btn-remove-multi">×</button>
                </div>
            </div>
            <div class="multi-item-details"></div>
        </div>
    `;
    
    $(`#${listId}`).append(itemHtml);
    
    // Store item data
    const $item = $(`[data-item-id="${itemId}"]`);
    const fullItemData = {
        ...itemData,
        type: targetType,
        id: itemId
    };
    $item.data('item', fullItemData);
    
    // Update price display
    const $details = $item.find('.multi-item-details');
    if (window.cctvSearch && window.cctvSearch.updatePriceDisplay) {
        window.cctvSearch.updatePriceDisplay($details, itemData.priceIncVat, itemData.cost, itemData.isManual);
    }
    
    if (window.cctvQuoteCalculations) {
        window.cctvQuoteCalculations.updateTotals();
    }
}

function getMultiListId(targetType) {
    const listIds = {
        'hdd': 'hddList',
        'camera': 'cameraList',
        'power_supply': 'powerSupplyList',
        'camera_cable': 'cameraCableList',
        'connectors': 'connectorsList',
        'mounting': 'mountingList',
        'cable_management': 'cableManagementList'
    };
    return listIds[targetType] || targetType + 'List';
}

function generateQtyOptions(selectedQty) {
    let options = '';
    for (let i = 1; i <= 20; i++) {
        options += `<option value="${i}" ${i === selectedQty ? 'selected' : ''}>${i}x</option>`;
    }
    return options;
}

// ==================== PRICE EDITING ====================

function initializePriceEditing() {
    // Click on price to edit
    $(document).on('click', '.price-editable', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $priceSpan = $(this);
        const $container = $priceSpan.closest('.component-details, .multi-item-details');
        const currentPrice = parseFloat($container.data('current-price')) || 0;
        
        // Create input field
        const $input = $('<input type="number" class="form-control form-control-sm price-input" step="0.01">');
        $input.val(currentPrice.toFixed(2));
        
        // Replace span with input
        $priceSpan.replaceWith($input);
        $input.focus().select();
        
        // Handle blur (save)
        $input.on('blur', function() {
            const newPrice = parseFloat($(this).val()) || currentPrice;
            const $newSpan = $('<span class="price-editable" title="Click to edit price">£' + newPrice.toFixed(2) + '</span>');
            $(this).replaceWith($newSpan);
            
            // Validate and save
            $container.data('current-price', newPrice);
            if (window.cctvQuoteCalculations) {
                window.cctvQuoteCalculations.validateAndSavePrice($input);
            }
        });
        
        // Handle Enter key
        $input.on('keypress', function(e) {
            if (e.which === 13) {
                $(this).blur();
            }
        });
        
        // Handle Escape key
        $input.on('keydown', function(e) {
            if (e.which === 27) {
                const $newSpan = $('<span class="price-editable" title="Click to edit price">£' + currentPrice.toFixed(2) + '</span>');
                $(this).replaceWith($newSpan);
            }
        });
    });
}

// ==================== ADDITIONAL ITEMS ====================

function initializeAdditionalItems() {
    $('#addItemBtn').click(function() {
        addNewAdditionalItem();
    });

    $(document).on('click', '.additional-search-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const itemId = $(this).data('item-id');
        currentAdditionalItemId = itemId;
        isEditingAdditionalItem = true;
        currentEditingSection = null;
        currentMultiItemTarget = null;
        $('#searchModal').modal('show');
    });

    $(document).on('click', '.additional-manual-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const itemId = $(this).data('item-id');
        currentAdditionalItemId = itemId;
        isEditingAdditionalItem = true;
        currentEditingSection = null;
        currentMultiItemTarget = null;
        $('#manualEntryModal').modal('show');
    });

    $(document).on('click', '.additional-remove-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const itemId = $(this).data('item-id');
        delete additionalItemsData[itemId];
        $(this).closest('.additional-item-row').remove();
        
        if (window.cctvQuoteCalculations) {
            window.cctvQuoteCalculations.updateTotals();
        }
    });
}

function addNewAdditionalItem() {
    additionalItemCounter++;
    const itemId = 'additional_' + Date.now() + '_' + additionalItemCounter;
    
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
    
    additionalItemsData[itemId] = {
        name: null,
        priceWithVAT: 0,
        basePrice: 0,
        cost: 0,
        sku: null,
        isManual: false
    };
    
    setTimeout(function() {
        currentAdditionalItemId = itemId;
        isEditingAdditionalItem = true;
        currentEditingSection = null;
        currentMultiItemTarget = null;
        $('#searchModal').modal('show');
    }, 100);
}

function updateAdditionalItemDisplay(itemId) {
    const data = additionalItemsData[itemId];
    if (!data || !data.name) return;
    
    const $row = $(`#additionalItemsList .additional-item-row[data-item-id="${itemId}"]`);
    if ($row.length === 0) return;
    
    $row.find('.item-name').html(`<strong>${data.name}</strong>`);
    $row.find('.item-price').removeClass('text-muted').text(`£${data.priceWithVAT.toFixed(2)}${data.isManual ? ' (Manual)' : ''}`);
    
    if (window.cctvQuoteCalculations) {
        window.cctvQuoteCalculations.updateTotals();
    }
}

window.getAdditionalItemsData = function() {
    const filtered = {};
    Object.keys(additionalItemsData).forEach(key => {
        const item = additionalItemsData[key];
        if (item && item.name !== null && item.name !== '') {
            filtered[key] = item;
        }
    });
    return filtered;
};

// ==================== CUSTOMER SEARCH ====================

function initializeCustomerSearch() {
    $('#customerSearch').on('keypress', function(e) {
        if (e.which === 13) {
            searchCustomers();
        }
    });

    $('#searchCustomerBtn').click(function() {
        searchCustomers();
    });

    $('#newCustomerBtn').click(function() {
        showNewCustomerForm();
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
                    Swal.fire('No Results', 'No customers found', 'info');
                    return;
                }
                showCustomerSearchResults(results);
            } catch (e) {
                Swal.fire('Error', 'Failed to process search results', 'error');
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
        width: '700px',
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
        ${customer.email ? `Email: ${customer.email}<br>` : ''}
        ${customer.address ? `Address: ${customer.address}` : ''}
    `;

    const customerDetails = $('#customerDetails');
    customerDetails.html(detailsHtml);
    customerDetails.show();

    customerDetails.data({
        customerId: customer.id,
        customerName: customer.name,
        customerEmail: customer.email,
        customerPhone: customer.phone,
        customerAddress: customer.address
    });
}

function showNewCustomerForm() {
    Swal.fire({
        title: 'New Customer',
        html: `
            <form id="newCustomerForm">
                <div class="form-group text-left">
                    <label>Name</label>
                    <input type="text" id="newCustomerName" class="form-control" required>
                </div>
                <div class="form-group text-left">
                    <label>Phone</label>
                    <input type="text" id="newCustomerPhone" class="form-control">
                </div>
                <div class="form-group text-left">
                    <label>Email</label>
                    <input type="email" id="newCustomerEmail" class="form-control">
                </div>
                <div class="form-group text-left">
                    <label>Address</label>
                    <textarea id="newCustomerAddress" class="form-control" rows="2"></textarea>
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
                address: $('#newCustomerAddress').val()
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
                Swal.showValidationMessage(`Error: ${error.message}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed && result.value.customer) {
            selectCustomer(result.value.customer);
        }
    });
}

// ==================== ACTION BUTTONS ====================

function initializeActionButtons() {
    // Load Template Button
    $('#loadTemplateBtn').click(function() {
        loadTemplatesList();
    });

    // Save Quote Button - show modal instead of saving directly
    $('#saveQuoteBtn').click(function() {
        if ($(this).prop('disabled')) return;

        const validation = window.cctvQuoteCalculations.validateQuote();
        if (!validation.isValid) {
            showError('Cannot Save Quote', validation.errors);
            return;
        }

        // Show template save modal
        $('#templateName').val('');
        $('#saveAsTemplate').prop('checked', false);
        $('#templateSaveModal').modal('show');
    });

    // Confirm Save from Modal
    $('#confirmSaveQuote').click(function() {
        const templateName = $('#templateName').val().trim();
        const isTemplate = $('#saveAsTemplate').is(':checked');
        
        $('#templateSaveModal').modal('hide');
        saveQuote(templateName, isTemplate);
    });

    $('#printQuoteBtn').click(function() {
        if ($(this).prop('disabled')) return;

        const validation = window.cctvQuoteCalculations.validateQuote();
        if (!validation.isValid) {
            showError('Cannot Print Quote', validation.errors);
            return;
        }

        printQuote();
    });
}

function saveQuote(templateName, isTemplate) {
    const quoteData = window.cctvQuoteCalculations.getQuoteData();
    
    // Add template information
    quoteData.templateName = templateName || null;
    quoteData.isTemplate = isTemplate ? 1 : 0;
    
    Swal.fire({
        title: 'Saving Quote...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'ajax/save_cctv_quote.php',
        method: 'POST',
        data: { quote: JSON.stringify(quoteData) },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'Success',
                    text: isTemplate ? 'Template saved successfully' : 'Quote saved successfully',
                    icon: 'success'
                }).then(() => {
                    if (!isTemplate && response.quoteId) {
                        window.location.href = `view_cctv_quote.php?id=${response.quoteId}`;
                    } else if (isTemplate) {
                        // Stay on page for templates
                        Swal.fire({
                            title: 'Template Saved',
                            text: 'You can now load this template anytime using the "Load Template" button',
                            icon: 'info',
                            timer: 3000
                        });
                    }
                });
            } else {
                showError('Error', response.message || 'Failed to save quote');
            }
        },
        error: function() {
            showError('Error', 'Failed to save quote');
        }
    });
}

function loadTemplatesList() {
    Swal.fire({
        title: 'Loading Templates...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'ajax/get_cctv_templates.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success && response.templates) {
                displayTemplates(response.templates);
            } else {
                showError('No Templates', 'No templates found. Save a quote as a template first.');
            }
        },
        error: function() {
            Swal.close();
            showError('Error', 'Failed to load templates');
        }
    });
}

function displayTemplates(templates) {
    if (templates.length === 0) {
        $('#templateList').html('<div class="alert alert-info">No templates available. Save a quote with a template name to create one.</div>');
        $('#templateLoadModal').modal('show');
        return;
    }
    
    let html = '<div class="list-group">';
    templates.forEach(template => {
        const itemCount = template.item_count || 0;
        const totalPrice = parseFloat(template.total_price || 0);
        
        html += `
            <a href="#" class="list-group-item list-group-item-action template-item" data-template-id="${template.id}">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1">${template.template_name}</h5>
                    <small>${new Date(template.date_created).toLocaleDateString()}</small>
                </div>
                <p class="mb-1">
                    <span class="badge badge-primary">${itemCount} items</span>
                    <span class="badge badge-success">£${totalPrice.toFixed(2)}</span>
                    <span class="badge badge-info">${template.price_type === 'R' ? 'Retail' : 'Trade'}</span>
                </p>
                <small class="text-muted">Created by ${template.created_by_name}</small>
            </a>
        `;
    });
    html += '</div>';
    
    $('#templateList').html(html);
    $('#templateLoadModal').modal('show');
    
    // Handle template selection
    $('.template-item').click(function(e) {
        e.preventDefault();
        const templateId = $(this).data('template-id');
        loadTemplate(templateId);
    });
}

function loadTemplate(templateId) {
    $('#templateLoadModal').modal('hide');
    
    Swal.fire({
        title: 'Loading Template...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'ajax/get_cctv_quote.php',
        method: 'GET',
        data: { id: templateId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.quote) {
                // Clear current quote
                clearCurrentQuote();
                
                // Load template data (without customer)
                populateFromTemplate(response.quote);
                
                Swal.fire({
                    title: 'Template Loaded',
                    text: 'Template loaded successfully. Add a customer and modify as needed.',
                    icon: 'success',
                    timer: 2000
                });
            } else {
                showError('Error', 'Failed to load template');
            }
        },
        error: function() {
            showError('Error', 'Failed to load template');
        }
    });
}

function clearCurrentQuote() {
    // Clear customer
    $('#customerSearch').val('');
    $('#customerDetails').hide().removeData();
    
    // Clear all single components
    $('.component-section:not([data-allow-multiple]) .component-display').each(function() {
        $(this).val('').removeData();
        $(this).closest('.component-section').find('.component-details').html('');
        $(this).closest('.component-section').find('.btn-remove').hide();
    });
    
    // Clear all multi-items
    $('.multi-component-list').empty();
    
    // Clear additional items
    $('#additionalItemsList').empty();
    additionalItemsData = {};
    
    // Reset services
    $('#installationCharge').val('0.00');
    $('#configCharge').val('0.00');
    $('#testingCharge').val('0.00');
    
    // Update totals
    if (window.cctvQuoteCalculations) {
        window.cctvQuoteCalculations.updateTotals();
    }
}

function populateFromTemplate(templateData) {
    console.log('Loading template:', templateData);
    
    // Set price type
    if (templateData.priceType) {
        $('.price-type-toggle .btn').removeClass('active btn-primary').addClass('btn-outline-primary');
        $(`.price-type-toggle .btn[data-type="${templateData.priceType}"]`)
            .addClass('active btn-primary')
            .removeClass('btn-outline-primary');
    }
    
    // Set services
    if (templateData.services) {
        $('#installationCharge').val(parseFloat(templateData.services.installation).toFixed(2));
        $('#configCharge').val(parseFloat(templateData.services.configuration).toFixed(2));
        $('#testingCharge').val(parseFloat(templateData.services.testing).toFixed(2));
    }
    
    // Load components
    if (templateData.components && templateData.components.length > 0) {
        templateData.components.forEach((component) => {
            loadComponentFromTemplate(component);
        });
    }
    
    // Update totals after loading
    setTimeout(function() {
        if (window.cctvQuoteCalculations && window.cctvQuoteCalculations.updateTotals) {
            window.cctvQuoteCalculations.updateTotals();
        }
    }, 500);
}

function loadComponentFromTemplate(component) {
    const componentType = component.type;
    
    // Check if this is a multi-item type
    const multiTypes = ['hdd', 'camera', 'power_supply', 'camera_cable', 'connectors', 'mounting', 'cable_management'];
    
    if (multiTypes.includes(componentType)) {
        // Add as multi-item
        addMultiItem(componentType, {
            name: component.name,
            sku: component.sku,
            priceIncVat: component.priceIncVat,
            basePrice: component.basePrice,
            cost: component.cost,
            isManual: component.isManual,
            quantity: component.quantity
        });
    } else if (componentType === 'additional') {
        // Add as additional item
        additionalItemCounter++;
        const itemId = 'additional_' + Date.now() + '_' + additionalItemCounter;
        
        additionalItemsData[itemId] = {
            name: component.name,
            priceWithVAT: component.priceIncVat,
            basePrice: component.basePrice,
            cost: component.cost,
            sku: component.sku,
            isManual: component.isManual
        };
        
        const itemHtml = `
            <div class="additional-item-row" data-item-id="${itemId}">
                <div class="additional-item-content">
                    <div class="additional-item-info">
                        <span class="item-name"><strong>${component.name}</strong></span>
                        <span class="item-price">£${component.priceIncVat.toFixed(2)}${component.isManual ? ' (Manual)' : ''}</span>
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
    } else {
        // Add as single component
        const $section = $(`.component-section[data-component="${componentType}"]`);
        if ($section.length > 0) {
            const $input = $section.find('.component-display');
            const $details = $section.find('.component-details');
            
            $input.val(component.name);
            $input.data('sku', component.sku);
            $input.data('price_inc_vat', component.priceIncVat);
            $input.data('base_price', component.basePrice);
            $input.data('cost', component.cost);
            $input.data('isManual', component.isManual);
            $input.data('priceEdited', component.priceEdited);
            
            // Set quantity if applicable
            const $qtySelect = $section.find('.item-qty');
            if ($qtySelect.length > 0 && component.quantity > 1) {
                $qtySelect.val(component.quantity);
            }
            
            // Update display
            if (window.cctvSearch && window.cctvSearch.updatePriceDisplay) {
                window.cctvSearch.updatePriceDisplay($details, component.priceIncVat, component.cost, component.isManual);
            }
            
            $section.find('.btn-remove').show();
        }
    }
}

function printQuote() {
    const quoteData = window.cctvQuoteCalculations.getQuoteData();
    
    $.ajax({
        url: 'ajax/get_cctv_quote_print.php',
        method: 'POST',
        data: { quote: JSON.stringify(quoteData) },
        success: function(response) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(response);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 250);
        },
        error: function() {
            showError('Error', 'Failed to generate printable quote');
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

console.log('✓ CCTV UI Handlers loaded');