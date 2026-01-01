// Wait for jQuery to be available
(function() {
    function initializeWhenReady() {
        if (typeof jQuery !== 'undefined') {
            $(document).ready(function() {
                console.log('Initializing Product Types Management');
                
                // Initialize the page
                loadProductTypes();
                loadCategories();
                
                // Event handlers
                $('#addProductTypeBtn').click(function() {
                    openProductTypeModal();
                });
                
                $('#productTypeForm').submit(function(e) {
                    e.preventDefault();
                    saveProductTypeForm();
                });
                
                $('#saveOrderBtn').click(function() {
                    saveOrder();
                });
                
                $('#editFromDetailsBtn').click(function() {
                    const productTypeId = $(this).data('product-type-id');
                    if (productTypeId) {
                        $('#detailsModal').modal('hide');
                        editProductType(productTypeId);
                    }
                });
                
                // Filter handlers
                $('#categoryFilter, #statusFilter').change(function() {
                    loadProductTypes();
                });
                
                // Auto-calculate display order when category is selected
                $('#essentialCategorySelect').change(function() {
                    const categoryId = $(this).val();
                    if (categoryId) {
                        calculateNextDisplayOrder(categoryId);
                    }
                });
            });
        } else {
            setTimeout(initializeWhenReady, 100);
        }
    }
    
    initializeWhenReady();
})();

function loadProductTypes() {
    showSpinner();
    
    const categoryFilter = $('#categoryFilter').val();
    const statusFilter = $('#statusFilter').val();
    
    let url = 'api/essential_product_types.php?action=list';
    if (categoryFilter) url += '&category_id=' + categoryFilter;
    if (statusFilter !== '') url += '&status=' + statusFilter;
    
    $.get(url)
        .done(function(response) {
            renderProductTypesAccordion(response.product_types);
            initializeDragDrop();
        })
        .fail(function(xhr) {
            showError('Failed to load product types: ' + getErrorMessage(xhr));
        })
        .always(function() {
            hideSpinner();
        });
}

function loadCategories() {
    $.get('api/essential_product_types.php?action=categories')
        .done(function(response) {
            populateCategorySelects(response.categories);
        })
        .fail(function(xhr) {
            console.error('Failed to load categories:', xhr);
        });
}

function populateCategorySelects(categories) {
    const selects = ['#categoryFilter', '#essentialCategorySelect'];
    
    selects.forEach(function(selectId) {
        const select = $(selectId);
        const currentValue = select.val();
        
        // Keep first option for filter, clear for form select
        if (selectId === '#categoryFilter') {
            select.find('option:not(:first)').remove();
        } else {
            select.find('option:not(:first)').remove();
        }
        
        categories.forEach(function(category) {
            const option = $(`
                <option value="${category.id}">
                    ${escapeHtml(category.display_name)}
                </option>
            `);
            select.append(option);
        });
        
        // Restore selection
        if (currentValue) {
            select.val(currentValue);
        }
    });
}

function renderProductTypesAccordion(productTypes) {
    const container = $('#categoriesAccordion');
    container.empty();
    
    if (productTypes.length === 0) {
        container.append(`
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle fa-2x mb-3"></i><br>
                <h5>No Product Types Found</h5>
                <p>No product types match your current filters, or none have been created yet.</p>
                <button class="btn btn-primary" onclick="$('#addProductTypeBtn').click()">
                    <i class="fas fa-plus"></i> Add First Product Type
                </button>
            </div>
        `);
        return;
    }
    
    // Group by category
    const groupedTypes = {};
    productTypes.forEach(function(type) {
        if (!groupedTypes[type.essential_category_id]) {
            groupedTypes[type.essential_category_id] = {
                category_name: type.category_name,
                category_order: type.category_order,
                types: []
            };
        }
        groupedTypes[type.essential_category_id].types.push(type);
    });
    
    // Sort categories by order
    const sortedCategories = Object.keys(groupedTypes).sort((a, b) => {
        return groupedTypes[a].category_order - groupedTypes[b].category_order;
    });
    
    sortedCategories.forEach(function(categoryId, index) {
        const categoryData = groupedTypes[categoryId];
        const isFirst = index === 0;
        
        const categoryCard = $(`
            <div class="card mb-3">
                <div class="card-header" id="heading${categoryId}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-left" type="button" data-toggle="collapse" 
                                    data-target="#collapse${categoryId}" aria-expanded="${isFirst}" 
                                    aria-controls="collapse${categoryId}">
                                <i class="fas fa-chevron-${isFirst ? 'down' : 'right'} me-2"></i>
                                ${escapeHtml(categoryData.category_name)}
                                <span class="badge badge-secondary ml-2">${categoryData.types.length} types</span>
                            </button>
                        </h5>
                        <div class="category-actions">
                            <button class="btn btn-sm btn-outline-primary add-to-category" 
                                    data-category-id="${categoryId}" title="Add Product Type">
                                <i class="fas fa-plus"></i>
                            </button>
                            ${window.PRODUCT_TYPES_CONFIG.canReorder ? `
                                <button class="btn btn-sm btn-outline-warning reorder-category" 
                                        data-category-id="${categoryId}" title="Reorder Types">
                                    <i class="fas fa-sort"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
                <div id="collapse${categoryId}" class="collapse ${isFirst ? 'show' : ''}" 
                     aria-labelledby="heading${categoryId}" data-parent="#categoriesAccordion">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        ${window.PRODUCT_TYPES_CONFIG.canReorder ? '<th width="30px">Drag</th>' : ''}
                                        <th>Order</th>
                                        <th>Product Type</th>
                                        <th>Min Stock</th>
                                        <th>Current Stock</th>
                                        <th>Status</th>
                                        <th>Mapped Items</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="sortable-tbody" data-category-id="${categoryId}">
                                    ${renderProductTypeRows(categoryData.types)}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        container.append(categoryCard);
    });
    
    // Attach event handlers
    attachEventHandlers();
}

function renderProductTypeRows(types) {
    return types.map(function(type) {
        const stockStatusClass = {
            'OUT_OF_STOCK': 'danger',
            'LOW_STOCK': 'warning',
            'OK': 'success'
        }[type.stock_status] || 'secondary';
        
        const stockStatusText = {
            'OUT_OF_STOCK': 'Out of Stock',
            'LOW_STOCK': 'Low Stock',
            'OK': 'In Stock'
        }[type.stock_status] || 'Unknown';
        
        return `
            <tr data-product-type-id="${type.id}" data-display-order="${type.display_order}" 
                class="${type.is_active ? '' : 'table-secondary'} sortable-row">
                ${window.PRODUCT_TYPES_CONFIG.canReorder ? `
                    <td class="drag-handle" title="Drag to reorder">
                        <i class="fas fa-grip-vertical text-muted"></i>
                    </td>
                ` : ''}
                <td>
                    <span class="badge badge-secondary order-badge">${type.display_order}</span>
                </td>
                <td>
                    <div>
                        <strong class="${type.is_active ? '' : 'text-muted'}">${escapeHtml(type.product_type_name)}</strong>
                        ${type.notes ? `<br><small class="text-muted">${escapeHtml(type.notes)}</small>` : ''}
                        ${!type.is_active ? '<br><small class="text-danger">Inactive</small>' : ''}
                    </div>
                </td>
                <td>
                    <span class="badge badge-info">${type.minimum_stock_qty}</span>
                </td>
                <td>
                    <span class="badge badge-${stockStatusClass}">${type.current_stock}</span>
                </td>
                <td>
                    <span class="badge badge-${stockStatusClass}">${stockStatusText}</span>
                </td>
                <td>
                    <a href="#" class="badge badge-primary mapped-products-link" 
                       data-product-type-id="${type.id}">
                        ${type.mapped_products} items
                    </a>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info btn-sm view-details" 
                                data-product-type-id="${type.id}" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary btn-sm edit-product-type" 
                                data-product-type-id="${type.id}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${window.PRODUCT_TYPES_CONFIG.isSuperAdmin ? `
                            <button class="btn btn-outline-danger btn-sm delete-product-type" 
                                    data-product-type-id="${type.id}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function initializeDragDrop() {
    if (!window.PRODUCT_TYPES_CONFIG.canReorder) return;
    
    $('.sortable-tbody').each(function() {
        const $tbody = $(this);
        const categoryId = $tbody.data('category-id');
        
        $tbody.sortable({
            handle: '.drag-handle',
            placeholder: 'sortable-placeholder',
            tolerance: 'pointer',
            axis: 'y',
            containment: 'parent',
            cursor: 'move',
            opacity: 0.8,
            helper: function(e, ui) {
                // Fix width of cells in the helper
                ui.children().each(function() {
                    $(this).width($(this).width());
                });
                return ui;
            },
            start: function(e, ui) {
                ui.placeholder.html('<td colspan="8" class="text-center text-muted py-2"><i class="fas fa-arrows-alt-v"></i> Drop here to reorder</td>');
                ui.item.addClass('dragging');
            },
            stop: function(e, ui) {
                ui.item.removeClass('dragging');
                updateRowOrders(categoryId);
                saveInlineOrder(categoryId);
            }
        }).disableSelection();
    });
}

function updateRowOrders(categoryId) {
    const $tbody = $(`.sortable-tbody[data-category-id="${categoryId}"]`);
    let order = 1;
    
    $tbody.find('tr.sortable-row').each(function() {
        const $row = $(this);
        $row.attr('data-display-order', order);
        $row.find('.order-badge').text(order);
        order++;
    });
}

function saveInlineOrder(categoryId) {
    const order = [];
    const $tbody = $(`.sortable-tbody[data-category-id="${categoryId}"]`);
    
    $tbody.find('tr.sortable-row').each(function() {
        order.push($(this).data('product-type-id'));
    });
    
    if (order.length === 0) return;
    
    // Show subtle loading indicator
    const $category = $tbody.closest('.card');
    $category.addClass('reordering');
    
    $.ajax({
        url: 'api/essential_product_types.php',
        method: 'POST',
        data: JSON.stringify({ action: 'reorder', order: order }),
        contentType: 'application/json',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.success) {
            // Show subtle success feedback
            $category.addClass('reorder-success');
            setTimeout(() => {
                $category.removeClass('reorder-success');
            }, 1000);
        } else {
            showError(response.error || 'Failed to save order');
            // Reload to restore original order
            loadProductTypes();
        }
    })
    .fail(function(xhr) {
        showError('Failed to save order: ' + getErrorMessage(xhr));
        // Reload to restore original order
        loadProductTypes();
    })
    .always(function() {
        $category.removeClass('reordering');
    });
}

function attachEventHandlers() {
    // Add to category buttons
    $('.add-to-category').click(function() {
        const categoryId = $(this).data('category-id');
        openProductTypeModal(null, categoryId);
    });
    
    // Reorder buttons (modal-based reordering)
    $('.reorder-category').click(function() {
        const categoryId = $(this).data('category-id');
        openReorderModal(categoryId);
    });
    
    // View details
    $('.view-details').click(function() {
        const productTypeId = $(this).data('product-type-id');
        viewProductTypeDetails(productTypeId);
    });
    
    // Edit product type
    $('.edit-product-type').click(function() {
        const productTypeId = $(this).data('product-type-id');
        editProductType(productTypeId);
    });
    
    // Delete product type
    $('.delete-product-type').click(function() {
        const productTypeId = $(this).data('product-type-id');
        deleteProductType(productTypeId);
    });
    
    // Mapped products link
    $('.mapped-products-link').click(function(e) {
        e.preventDefault();
        const productTypeId = $(this).data('product-type-id');
        viewProductTypeDetails(productTypeId);
    });
    
    // Collapse chevron rotation
    $('[data-toggle="collapse"]').on('click', function() {
        const icon = $(this).find('i.fa-chevron-right, i.fa-chevron-down');
        setTimeout(() => {
            const target = $($(this).data('target'));
            if (target.hasClass('show')) {
                icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
            } else {
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
            }
        }, 100);
    });
}

function openProductTypeModal(productTypeData = null, categoryId = null) {
    const modal = $('#productTypeModal');
    const form = $('#productTypeForm')[0];
    
    // Reset form
    form.reset();
    $('#productTypeId').val('');
    
    if (productTypeData) {
        // Edit mode
        $('#productTypeModalLabel').text('Edit Product Type');
        $('#productTypeId').val(productTypeData.id);
        $('#essentialCategorySelect').val(productTypeData.essential_category_id);
        $('#productTypeName').val(productTypeData.product_type_name);
        $('#minimumStockQty').val(productTypeData.minimum_stock_qty);
        $('#displayOrder').val(productTypeData.display_order);
        $('#notes').val(productTypeData.notes || '');
        $('#isActive').prop('checked', productTypeData.is_active == 1);
        
        // Disable category selection in edit mode
        $('#essentialCategorySelect').prop('disabled', true);
    } else {
        // Add mode
        $('#productTypeModalLabel').text('Add Product Type');
        $('#essentialCategorySelect').prop('disabled', false);
        $('#isActive').prop('checked', true);
        
        // Pre-select category if provided
        if (categoryId) {
            $('#essentialCategorySelect').val(categoryId);
            calculateNextDisplayOrder(categoryId);
        }
    }
    
    modal.modal('show');
}

function calculateNextDisplayOrder(categoryId) {
    // Find the highest display order in this category
    let maxOrder = 0;
    $(`#collapse${categoryId} tbody tr`).each(function() {
        const order = parseInt($(this).find('td:eq(' + (window.PRODUCT_TYPES_CONFIG.canReorder ? '1' : '0') + ') .badge').text());
        if (order > maxOrder) {
            maxOrder = order;
        }
    });
    $('#displayOrder').val(maxOrder + 1);
}

function saveProductTypeForm() {
    const form = $('#productTypeForm')[0];
    const formData = new FormData(form);
    const productTypeId = $('#productTypeId').val();
    
    const data = {
        action: productTypeId ? 'update' : 'create',
        essential_category_id: formData.get('essential_category_id'),
        product_type_name: formData.get('product_type_name'),
        minimum_stock_qty: parseInt(formData.get('minimum_stock_qty')),
        display_order: parseInt(formData.get('display_order')),
        notes: formData.get('notes'),
        is_active: formData.get('is_active') ? 1 : 0
    };
    
    if (productTypeId) {
        data.id = productTypeId;
    }
    
    const method = productTypeId ? 'PUT' : 'POST';
    const url = 'api/essential_product_types.php';
    
    showSpinner();
    
    $.ajax({
        url: url,
        method: method,
        data: JSON.stringify(data),
        contentType: 'application/json',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.success) {
            $('#productTypeModal').modal('hide');
            loadProductTypes();
            showSuccess(productTypeId ? 'Product type updated successfully' : 'Product type added successfully');
        } else {
            showError(response.error || 'Failed to save product type');
        }
    })
    .fail(function(xhr) {
        showError('Failed to save product type: ' + getErrorMessage(xhr));
    })
    .always(function() {
        hideSpinner();
    });
}

function editProductType(productTypeId) {
    // Find product type data from current display
    const productTypeData = getCurrentProductTypeData(productTypeId);
    
    if (productTypeData) {
        openProductTypeModal(productTypeData);
    } else {
        showError('Product type not found');
    }
}

function getCurrentProductTypeData(productTypeId) {
    const row = $(`tr[data-product-type-id="${productTypeId}"]`);
    if (!row.length) return null;
    
    // Adjust column indices based on whether drag column exists
    const dragOffset = window.PRODUCT_TYPES_CONFIG.canReorder ? 1 : 0;
    
    // Extract data from the table row
    const displayOrder = row.find(`td:eq(${dragOffset}) .badge`).text();
    const productTypeName = row.find(`td:eq(${dragOffset + 1}) strong`).text();
    const notes = row.find(`td:eq(${dragOffset + 1}) small`).text();
    const minimumStockQty = row.find(`td:eq(${dragOffset + 2}) .badge`).text();
    const isActive = !row.hasClass('table-secondary');
    
    // Find category ID from the collapse parent
    const categoryId = row.closest('[id^="collapse"]').attr('id').replace('collapse', '');
    
    return {
        id: productTypeId,
        essential_category_id: categoryId,
        product_type_name: productTypeName,
        minimum_stock_qty: parseInt(minimumStockQty),
        display_order: parseInt(displayOrder),
        notes: notes,
        is_active: isActive ? 1 : 0
    };
}

function deleteProductType(productTypeId) {
    confirmDelete('This product type will be permanently deleted. Any product mappings will also be removed.')
        .then((result) => {
            if (result.isConfirmed) {
                showSpinner();
                
                $.ajax({
                    url: 'api/essential_product_types.php',
                    method: 'DELETE',
                    data: JSON.stringify({ id: productTypeId }),
                    contentType: 'application/json',
                    dataType: 'json'
                })
                .done(function(response) {
                    if (response.success) {
                        loadProductTypes();
                        showSuccess('Product type deleted successfully');
                    } else {
                        showError(response.error || 'Failed to delete product type');
                    }
                })
                .fail(function(xhr) {
                    showError('Failed to delete product type: ' + getErrorMessage(xhr));
                })
                .always(function() {
                    hideSpinner();
                });
            }
        });
}

function viewProductTypeDetails(productTypeId) {
    showSpinner();
    
    $.get(`api/essential_product_types.php?action=details&id=${productTypeId}`)
        .done(function(response) {
            renderDetailsModal(response.details, response.mapped_products);
        })
        .fail(function(xhr) {
            showError('Failed to load details: ' + getErrorMessage(xhr));
        })
        .always(function() {
            hideSpinner();
        });
}

function renderDetailsModal(details, mappedProducts) {
    const stockStatus = details.current_stock >= details.minimum_stock_qty ? 'success' : 
                       details.current_stock > 0 ? 'warning' : 'danger';
    
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6>Basic Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Product Type:</strong></td><td>${escapeHtml(details.product_type_name)}</td></tr>
                    <tr><td><strong>Category:</strong></td><td>${escapeHtml(details.category_name)}</td></tr>
                    <tr><td><strong>Minimum Stock:</strong></td><td>${details.minimum_stock_qty}</td></tr>
                    <tr><td><strong>Current Stock:</strong></td><td><span class="badge badge-${stockStatus}">${details.current_stock}</span></td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="badge badge-${details.is_active ? 'success' : 'secondary'}">${details.is_active ? 'Active' : 'Inactive'}</span></td></tr>
                    <tr><td><strong>Display Order:</strong></td><td>${details.display_order}</td></tr>
                </table>
                ${details.notes ? `<p><strong>Notes:</strong><br>${escapeHtml(details.notes)}</p>` : ''}
            </div>
            <div class="col-md-6">
                <h6>Mapped Products (${mappedProducts.length})</h6>
                <div style="max-height: 300px; overflow-y: auto;">
                    ${mappedProducts.length > 0 ? `
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${mappedProducts.map(product => `
                                    <tr>
                                        <td>${product.sku}</td>
                                        <td>
                                            <small>${escapeHtml(product.name)}</small><br>
                                            <small class="text-muted">${escapeHtml(product.manufacturer || '')}</small>
                                        </td>
                                        <td><span class="badge badge-${product.qty > 0 ? 'success' : 'danger'}">${product.qty}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p class="text-muted">No products mapped to this type yet.</p>'}
                </div>
            </div>
        </div>
    `;
    
    $('#detailsModalBody').html(detailsHtml);
    $('#editFromDetailsBtn').data('product-type-id', details.id);
    $('#detailsModal').modal('show');
}

function openReorderModal(categoryId) {
    const categoryName = $(`#heading${categoryId} h5 button`).text().trim().split('\n')[0];
    const productTypes = getCurrentCategoryProductTypes(categoryId);
    
    $('#reorderCategoryName').text(categoryName);
    const sortableList = $('#sortableProductTypes');
    
    sortableList.empty();
    
    productTypes.forEach(function(type) {
        const item = $(`
            <li class="list-group-item d-flex justify-content-between align-items-center" 
                data-product-type-id="${type.id}">
                <div>
                    <i class="fas fa-grip-vertical text-muted me-2"></i>
                    <strong>${escapeHtml(type.product_type_name)}</strong>
                    ${type.notes ? `<br><small class="text-muted">${escapeHtml(type.notes)}</small>` : ''}
                </div>
                <span class="badge badge-secondary">${type.display_order}</span>
            </li>
        `);
        sortableList.append(item);
    });
    
    // Initialize sortable
    sortableList.sortable({
        handle: '.fas.fa-grip-vertical',
        placeholder: 'sortable-placeholder',
        tolerance: 'pointer'
    });
    
    $('#reorderModal').modal('show');
}

function getCurrentCategoryProductTypes(categoryId) {
    const types = [];
    const dragOffset = window.PRODUCT_TYPES_CONFIG.canReorder ? 1 : 0;
    
    $(`#collapse${categoryId} tbody tr[data-product-type-id]`).each(function() {
        const row = $(this);
        const productTypeId = row.data('product-type-id');
        const displayOrder = parseInt(row.find(`td:eq(${dragOffset}) .badge`).text());
        const productTypeName = row.find(`td:eq(${dragOffset + 1}) strong`).text();
        const notes = row.find(`td:eq(${dragOffset + 1}) small`).text();
        
        types.push({
            id: productTypeId,
            display_order: displayOrder,
            product_type_name: productTypeName,
            notes: notes
        });
    });
    
    return types.sort((a, b) => a.display_order - b.display_order);
}

function saveOrder() {
    const order = [];
    $('#sortableProductTypes li').each(function() {
        order.push($(this).data('product-type-id'));
    });
    
    showSpinner();
    
    $.ajax({
        url: 'api/essential_product_types.php',
        method: 'POST',
        data: JSON.stringify({ action: 'reorder', order: order }),
        contentType: 'application/json',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.success) {
            $('#reorderModal').modal('hide');
            loadProductTypes();
            showSuccess('Product type order updated successfully');
        } else {
            showError(response.error || 'Failed to save order');
        }
    })
    .fail(function(xhr) {
        showError('Failed to save order: ' + getErrorMessage(xhr));
    })
    .always(function() {
        hideSpinner();
    });
}

// Utility functions
function showSpinner() {
    $('#spinner').show();
}

function hideSpinner() {
    $('#spinner').hide();
}

function showSuccess(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Success!',
            text: message,
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    } else {
        alert('Success: ' + message);
    }
}

function showError(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Error!',
            text: message,
            icon: 'error'
        });
    } else {
        alert('Error: ' + message);
    }
}

function confirmDelete(message) {
    if (typeof Swal !== 'undefined') {
        return Swal.fire({
            title: 'Are you sure?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        });
    } else {
        return Promise.resolve({ isConfirmed: confirm(message) });
    }
}

function getErrorMessage(xhr) {
    try {
        const response = JSON.parse(xhr.responseText);
        return response.error || 'Unknown error';
    } catch (e) {
        return xhr.statusText || 'Unknown error';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}