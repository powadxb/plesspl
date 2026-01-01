// Wait for jQuery to be available
(function() {
    function initializeWhenReady() {
        if (typeof jQuery !== 'undefined') {
            $(document).ready(function() {
                console.log('Initializing Product Mapping');
                
                // Initialize the page
                loadStats();
                loadEssentialTypes();
                loadManufacturers();
                
                // Fix modal aria-hidden issues
                setupModalAccessibility();
                
                // Event handlers
                $('#searchBtn').click(function() {
                    searchProducts();
                });
                
                $('#productSearch').keypress(function(e) {
                    if (e.which == 13) {
                        searchProducts();
                    }
                });
                
                $('#excludeSearch').keypress(function(e) {
                    if (e.which == 13) {
                        searchProducts();
                    }
                });
                
                $('#manufacturerFilter, #stockFilter, #showMappedProducts').change(function() {
                    if ($('#productSearch').val().trim()) {
                        searchProducts();
                    }
                });
                
                $('#essentialCategoryFilter').change(function() {
                    loadEssentialTypes();
                });
                
                $('#viewMappingsBtn').click(function() {
                    viewAllMappings();
                });
                
                $('#confirmMappingBtn').click(function() {
                    confirmMapping();
                });
                
                $('#confirmBulkMappingBtn').click(function() {
                    confirmBulkMapping();
                });
                
                $('#mapFromDetailsBtn').click(function() {
                    const sku = $(this).data('sku');
                    if (sku) {
                        $('#productDetailsModal').modal('hide');
                        openMappingModal(sku);
                    }
                });
                
                $('#exportMappingsBtn').click(function() {
                    exportMappings();
                });
                
                // Bulk selection handlers
                $('#selectAllProducts').change(function() {
                    const isChecked = $(this).is(':checked');
                    $('.product-checkbox:visible').prop('checked', isChecked);
                    updateBulkActions();
                });
                
                $('#bulkMapBtn').click(function() {
                    openBulkMappingModal();
                });
                
                $('#clearSelectionBtn').click(function() {
                    $('.product-checkbox').prop('checked', false);
                    $('#selectAllProducts').prop('checked', false);
                    updateBulkActions();
                });
            });
        } else {
            setTimeout(initializeWhenReady, 100);
        }
    }
    
    initializeWhenReady();
})();

let currentSelectedProduct = null;
let selectedProducts = [];

function setupModalAccessibility() {
    // Fix aria-hidden for all modals
    const modals = ['#mapProductModal', '#bulkMapProductModal', '#viewMappingsModal', '#productDetailsModal'];
    
    modals.forEach(function(modalId) {
        $(modalId).on('show.bs.modal', function() {
            $(this).removeAttr('aria-hidden');
        });
        
        $(modalId).on('shown.bs.modal', function() {
            $(this).attr('aria-hidden', 'false');
        });
        
        $(modalId).on('hide.bs.modal', function() {
            $(this).removeAttr('aria-hidden');
        });
        
        $(modalId).on('hidden.bs.modal', function() {
            $(this).attr('aria-hidden', 'true');
        });
    });
}

function loadStats() {
    $.get('api/product_mapping_simple.php?action=stats')
        .done(function(response) {
            $('#totalProductTypes').text(response.total_product_types);
            $('#mappedProducts').text(response.mapped_products);
            $('#unmappedTypes').text(response.unmapped_types);
            $('#outOfStockTypes').text(response.out_of_stock_types);
        })
        .fail(function(xhr) {
            console.error('Failed to load stats:', xhr);
        });
}

function loadEssentialTypes() {
    const categoryFilter = $('#essentialCategoryFilter').val();
    
    let url = 'api/product_mapping_simple.php?action=essential_types';
    if (categoryFilter) {
        url += '&category_id=' + categoryFilter;
    }
    
    $.get(url)
        .done(function(response) {
            renderEssentialTypes(response.essential_types);
            populateEssentialTypeSelects(response.essential_types);
        })
        .fail(function(xhr) {
            showError('Failed to load essential types: ' + getErrorMessage(xhr));
        });
}

function loadManufacturers() {
    $.get('api/product_mapping_simple.php?action=manufacturers')
        .done(function(response) {
            populateManufacturerSelect(response.manufacturers);
        })
        .fail(function(xhr) {
            console.error('Failed to load manufacturers:', xhr);
        });
    
    // Also load categories for filter
    $.get('api/essential_product_types.php?action=categories')
        .done(function(response) {
            populateCategoryFilter(response.categories);
        })
        .fail(function(xhr) {
            console.error('Failed to load categories:', xhr);
        });
}

function populateManufacturerSelect(manufacturers) {
    const select = $('#manufacturerFilter');
    select.find('option:not(:first)').remove();
    
    manufacturers.forEach(function(manufacturer) {
        const option = $('<option></option>')
            .attr('value', manufacturer.manufacturer)
            .text(manufacturer.manufacturer);
        select.append(option);
    });
}

function populateCategoryFilter(categories) {
    const select = $('#essentialCategoryFilter');
    select.find('option:not(:first)').remove();
    
    categories.forEach(function(category) {
        const option = $('<option></option>')
            .attr('value', category.id)
            .text(category.display_name);
        select.append(option);
    });
}

function populateEssentialTypeSelects(essentialTypes) {
    const selects = ['#essentialTypeSelect', '#bulkEssentialTypeSelect'];
    
    selects.forEach(function(selectId) {
        const select = $(selectId);
        const currentValue = select.val(); // Remember current selection
        
        // Clear ALL options and rebuild from scratch
        select.empty();
        select.append('<option value="">Choose a product type...</option>');
        
        // Group by category
        const groupedTypes = {};
        essentialTypes.forEach(function(type) {
            if (!groupedTypes[type.category_name]) {
                groupedTypes[type.category_name] = [];
            }
            groupedTypes[type.category_name].push(type);
        });
        
        // Add optgroups
        Object.keys(groupedTypes).sort().forEach(function(categoryName) {
            const optgroup = $('<optgroup></optgroup>').attr('label', categoryName);
            
            groupedTypes[categoryName].forEach(function(type) {
                const option = $('<option></option>')
                    .attr('value', type.id)
                    .text(type.product_type_name + ' (' + type.current_stock + ' in stock)');
                optgroup.append(option);
            });
            
            select.append(optgroup);
        });
        
        // Restore previous selection if it still exists
        if (currentValue && select.find('option[value="' + currentValue + '"]').length > 0) {
            select.val(currentValue);
        }
    });
}

// New function to refresh essential types for modals only
function refreshEssentialTypesForModal() {
    // Prevent multiple simultaneous calls
    if (refreshEssentialTypesForModal.isRunning) {
        return refreshEssentialTypesForModal.currentPromise;
    }
    
    refreshEssentialTypesForModal.isRunning = true;
    
    const promise = new Promise(function(resolve, reject) {
        const categoryFilter = $('#essentialCategoryFilter').val();
        
        let url = 'api/product_mapping_simple.php?action=essential_types';
        if (categoryFilter) {
            url += '&category_id=' + categoryFilter;
        }
        
        $.get(url)
            .done(function(response) {
                populateEssentialTypeSelects(response.essential_types);
                resolve();
            })
            .fail(function(xhr) {
                console.error('Failed to refresh essential types:', xhr);
                reject();
            })
            .always(function() {
                refreshEssentialTypesForModal.isRunning = false;
                refreshEssentialTypesForModal.currentPromise = null;
            });
    });
    
    refreshEssentialTypesForModal.currentPromise = promise;
    return promise;
}

function renderEssentialTypes(essentialTypes) {
    const container = $('#essentialTypesList');
    container.empty();
    
    if (essentialTypes.length === 0) {
        container.html('<div class="empty-state"><i class="fas fa-tags"></i><h5>No Essential Types Found</h5><p>No essential types match your current filter.</p></div>');
        return;
    }
    
    essentialTypes.forEach(function(type) {
        const stockClass = {
            'OUT_OF_STOCK': 'no-stock',
            'LOW_STOCK': 'low-stock',
            'OK': 'has-stock'
        }[type.stock_status] || '';
        
        const stockBadgeClass = {
            'OUT_OF_STOCK': 'badge-danger',
            'LOW_STOCK': 'badge-warning',
            'OK': 'badge-success'
        }[type.stock_status] || 'badge-secondary';
        
        const typeItemHtml = '<div class="essential-type-item ' + stockClass + '">' +
            '<div class="essential-type-header">' +
                '<h6 class="essential-type-name">' + escapeHtml(type.product_type_name) + '</h6>' +
                '<span class="essential-type-category">' + escapeHtml(type.category_name) + '</span>' +
            '</div>' +
            '<div class="essential-type-stats">' +
                '<div class="stock-info">' +
                    '<span class="stock-badge ' + stockBadgeClass + '">' + type.current_stock + '</span>' +
                    '<small class="text-muted">/ ' + type.minimum_stock_qty + ' min</small>' +
                '</div>' +
                '<div class="mapped-count" data-essential-type-id="' + type.id + '">' +
                    '<i class="fas fa-link"></i> ' + type.mapped_count + ' mapped' +
                '</div>' +
            '</div>' +
        '</div>';
        
        container.append(typeItemHtml);
    });
    
    // Attach click handlers for mapped count
    $('.mapped-count').click(function() {
        const essentialTypeId = $(this).data('essential-type-id');
        viewMappingsForType(essentialTypeId);
    });
}

function searchProducts() {
    const search = $('#productSearch').val().trim();
    const exclude = $('#excludeSearch').val().trim();
    const manufacturer = $('#manufacturerFilter').val();
    const stockFilter = $('#stockFilter').val();
    const showMapped = $('#showMappedProducts').is(':checked');
    
    if (!search && !manufacturer && !stockFilter) {
        $('#searchResults').html('<div class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2"></i><br>Enter search terms to find products</div>');
        return;
    }
    
    showSpinner();
    
    let url = 'api/product_mapping_simple.php?action=search';
    const params = [];
    
    if (search) params.push('search=' + encodeURIComponent(search));
    if (exclude) params.push('exclude=' + encodeURIComponent(exclude));
    if (manufacturer) params.push('manufacturer=' + encodeURIComponent(manufacturer));
    if (stockFilter) params.push('stock_filter=' + stockFilter);
    if (showMapped) params.push('show_mapped=true');
    
    if (params.length > 0) {
        url += '&' + params.join('&');
    }
    
    $.get(url)
        .done(function(response) {
            renderSearchResults(response.products);
        })
        .fail(function(xhr) {
            showError('Failed to search products: ' + getErrorMessage(xhr));
        })
        .always(function() {
            hideSpinner();
        });
}

function renderSearchResults(products) {
    const container = $('#searchResults');
    container.empty();
    
    if (products.length === 0) {
        container.html('<div class="empty-state"><i class="fas fa-search"></i><h5>No Products Found</h5><p>No products match your search criteria.</p></div>');
        return;
    }
    
    // Add bulk selection header
    let headerHtml = '<div class="bulk-selection-header">' +
        '<div class="bulk-controls">' +
            '<label class="bulk-select-all">' +
                '<input type="checkbox" id="selectAllProducts"> Select All (' + products.length + ')' +
            '</label>' +
            '<div class="bulk-actions" id="bulkActions" style="display: none;">' +
                '<span class="selected-count" id="selectedCount">0 selected</span>' +
                '<button class="btn btn-sm btn-primary" id="bulkMapBtn"><i class="fas fa-link"></i> Bulk Map</button>' +
                '<button class="btn btn-sm btn-secondary" id="clearSelectionBtn"><i class="fas fa-times"></i> Clear</button>' +
            '</div>' +
        '</div>' +
    '</div>';
    
    container.append(headerHtml);
    
    products.forEach(function(product) {
        const isMapped = product.essential_product_type_id !== null;
        const stockClass = product.qty > 0 ? 'stock-in' : 'stock-out';
        
        let productItemHtml = '<div class="product-item ' + (isMapped ? 'already-mapped' : '') + '" data-sku="' + product.sku + '">';
        
        // Add checkbox for unmapped products
        if (!isMapped) {
            productItemHtml += '<div class="product-checkbox-wrapper">' +
                '<input type="checkbox" class="product-checkbox" data-sku="' + product.sku + '">' +
            '</div>';
        }
        
        productItemHtml += '<div class="product-content">';
        
        productItemHtml += '<div class="product-header">' +
            '<h6 class="product-title">' + escapeHtml(product.name) + '</h6>' +
            '<span class="product-sku">' + product.sku + '</span>' +
        '</div>';
        
        productItemHtml += '<div class="product-meta">' +
            '<span class="product-manufacturer">' + escapeHtml(product.manufacturer || 'Unknown') + '</span>' +
            '<span class="product-stock ' + stockClass + '">Stock: ' + product.qty + '</span>';
        
        if (product.ean) {
            productItemHtml += '<span class="text-muted">EAN: ' + product.ean + '</span>';
        }
        
        productItemHtml += '</div>';
        
        if (isMapped) {
            productItemHtml += '<div class="mapped-info">' +
                '<i class="fas fa-link"></i> Mapped to: ' + escapeHtml(product.product_type_name) + ' (' + escapeHtml(product.category_name) + ')' +
            '</div>';
        }
        
        productItemHtml += '<div class="product-actions">' +
            '<button class="btn btn-sm btn-outline-info view-product-details" data-sku="' + product.sku + '">' +
                '<i class="fas fa-eye"></i> Details' +
            '</button>';
        
        if (!isMapped) {
            productItemHtml += '<button class="btn btn-sm btn-map map-product" data-sku="' + product.sku + '">' +
                '<i class="fas fa-link"></i> Map' +
            '</button>';
        } else {
            productItemHtml += '<button class="btn btn-sm btn-unmap unmap-product" data-sku="' + product.sku + '">' +
                '<i class="fas fa-unlink"></i> Unmap' +
            '</button>';
        }
        
        productItemHtml += '</div></div></div>';
        
        container.append(productItemHtml);
    });
    
    // Attach event handlers
    $('.view-product-details').click(function() {
        const sku = $(this).data('sku');
        viewProductDetails(sku);
    });
    
    $('.map-product').click(function() {
        const sku = $(this).data('sku');
        openMappingModal(sku);
    });
    
    $('.unmap-product').click(function() {
        const sku = $(this).data('sku');
        unmapProduct(sku);
    });
    
    // Bulk selection handlers
    $('.product-checkbox').change(function() {
        updateBulkActions();
    });
    
    $('#selectAllProducts').change(function() {
        const isChecked = $(this).is(':checked');
        $('.product-checkbox:visible').prop('checked', isChecked);
        updateBulkActions();
    });
    
    $('#bulkMapBtn').click(function() {
        openBulkMappingModal();
    });
    
    $('#clearSelectionBtn').click(function() {
        $('.product-checkbox').prop('checked', false);
        $('#selectAllProducts').prop('checked', false);
        updateBulkActions();
    });
}

function updateBulkActions() {
    const selectedCheckboxes = $('.product-checkbox:checked');
    const count = selectedCheckboxes.length;
    
    selectedProducts = [];
    selectedCheckboxes.each(function() {
        selectedProducts.push($(this).data('sku'));
    });
    
    $('#selectedCount').text(count + ' selected');
    
    if (count > 0) {
        $('#bulkActions').show();
    } else {
        $('#bulkActions').hide();
    }
    
    // Update select all checkbox state
    const totalCheckboxes = $('.product-checkbox').length;
    const checkedCheckboxes = $('.product-checkbox:checked').length;
    
    if (checkedCheckboxes === 0) {
        $('#selectAllProducts').prop('indeterminate', false).prop('checked', false);
    } else if (checkedCheckboxes === totalCheckboxes) {
        $('#selectAllProducts').prop('indeterminate', false).prop('checked', true);
    } else {
        $('#selectAllProducts').prop('indeterminate', true);
    }
}

function openBulkMappingModal() {
    if (selectedProducts.length === 0) {
        showError('Please select products to map');
        return;
    }
    
    $('#bulkSelectedCount').text(selectedProducts.length);
    $('#bulkEssentialTypeSelect').val('');
    $('#bulkMappingNotes').val('');
    
    // Refresh essential types before showing modal
    refreshEssentialTypesForModal().then(function() {
        $('#bulkMapProductModal').modal('show');
    });
}

function confirmBulkMapping() {
    const essentialTypeId = $('#bulkEssentialTypeSelect').val();
    const notes = $('#bulkMappingNotes').val().trim();
    
    if (!essentialTypeId) {
        showError('Please select an essential type');
        return;
    }
    
    if (selectedProducts.length === 0) {
        showError('No products selected');
        return;
    }
    
    showSpinner();
    
    const promises = [];
    let successCount = 0;
    let errorCount = 0;
    
    selectedProducts.forEach(function(sku) {
        const promise = $.ajax({
            url: 'api/product_mapping_simple.php',
            method: 'POST',
            data: JSON.stringify({
                action: 'create_mapping',
                sku: sku,
                essential_type_id: essentialTypeId,
                notes: notes
            }),
            contentType: 'application/json',
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                successCount++;
            } else {
                errorCount++;
                console.error('Failed to map SKU ' + sku + ':', response.error);
            }
        })
        .fail(function(xhr) {
            errorCount++;
            console.error('Failed to map SKU ' + sku + ':', getErrorMessage(xhr));
        });
        
        promises.push(promise);
    });
    
    Promise.all(promises.map(p => p.catch(e => e)))
        .then(function() {
            hideSpinner();
            $('#bulkMapProductModal').modal('hide');
            
            if (successCount > 0) {
                showSuccess(`Successfully mapped ${successCount} products` + 
                           (errorCount > 0 ? `. ${errorCount} failed.` : ''));
                
                // Clear selections and refresh
                $('.product-checkbox').prop('checked', false);
                $('#selectAllProducts').prop('checked', false);
                updateBulkActions();
                
                loadStats();
                loadEssentialTypes();
                
                if ($('#productSearch').val().trim()) {
                    searchProducts();
                }
            } else {
                showError(`Failed to map ${errorCount} products`);
            }
        });
}

function openMappingModal(sku) {
    const productItem = $('.product-item[data-sku="' + sku + '"]');
    const productName = productItem.find('.product-title').text();
    const manufacturer = productItem.find('.product-manufacturer').text();
    
    currentSelectedProduct = sku;
    
    const detailsHtml = '<table class="table table-sm">' +
        '<tr><td><strong>SKU:</strong></td><td>' + sku + '</td></tr>' +
        '<tr><td><strong>Name:</strong></td><td>' + escapeHtml(productName) + '</td></tr>' +
        '<tr><td><strong>Manufacturer:</strong></td><td>' + escapeHtml(manufacturer) + '</td></tr>' +
    '</table>';
    
    $('#selectedProductDetails').html(detailsHtml);
    $('#essentialTypeSelect').val('');
    $('#mappingNotes').val('');
    
    // Refresh essential types before showing modal
    refreshEssentialTypesForModal().then(function() {
        $('#mapProductModal').modal('show');
    });
}

function confirmMapping() {
    const sku = currentSelectedProduct;
    const essentialTypeId = $('#essentialTypeSelect').val();
    const notes = $('#mappingNotes').val().trim();
    
    if (!sku || !essentialTypeId) {
        showError('Please select a product type');
        return;
    }
    
    showSpinner();
    
    $.ajax({
        url: 'api/product_mapping_simple.php',
        method: 'POST',
        data: JSON.stringify({
            action: 'create_mapping',
            sku: sku,
            essential_type_id: essentialTypeId,
            notes: notes
        }),
        contentType: 'application/json',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.success) {
            $('#mapProductModal').modal('hide');
            showSuccess('Product mapped successfully');
            loadStats();
            loadEssentialTypes();
            if ($('#productSearch').val().trim()) {
                searchProducts();
            }
        } else {
            showError(response.error || 'Failed to create mapping');
        }
    })
    .fail(function(xhr) {
        showError('Failed to create mapping: ' + getErrorMessage(xhr));
    })
    .always(function() {
        hideSpinner();
    });
}

function unmapProduct(sku) {
    confirmDelete('This will remove the mapping for this product. Are you sure?')
        .then(function(result) {
            if (result.isConfirmed) {
                showSpinner();
                
                $.ajax({
                    url: 'api/product_mapping_simple.php',
                    method: 'DELETE',
                    data: JSON.stringify({ 
                        action: 'delete_mapping',
                        sku: sku 
                    }),
                    contentType: 'application/json',
                    dataType: 'json'
                })
                .done(function(response) {
                    if (response.success) {
                        showSuccess('Product unmapped successfully');
                        loadStats();
                        loadEssentialTypes();
                        if ($('#productSearch').val().trim()) {
                            searchProducts();
                        }
                    } else {
                        showError(response.error || 'Failed to unmap product');
                    }
                })
                .fail(function(xhr) {
                    showError('Failed to unmap product: ' + getErrorMessage(xhr));
                })
                .always(function() {
                    hideSpinner();
                });
            }
        });
}

function viewProductDetails(sku) {
    showSpinner();
    
    $.get('api/product_mapping_simple.php?action=product_details&sku=' + sku)
        .done(function(response) {
            renderProductDetailsModal(response.product);
        })
        .fail(function(xhr) {
            showError('Failed to load product details: ' + getErrorMessage(xhr));
        })
        .always(function() {
            hideSpinner();
        });
}

function renderProductDetailsModal(product) {
    const isMapped = product.essential_product_type_id !== null;
    
    let detailsHtml = '<div class="product-detail-grid">';
    
    detailsHtml += '<div class="product-detail-section">' +
        '<h6>Product Information</h6>' +
        '<table class="product-detail-table">' +
            '<tr><td>SKU:</td><td>' + product.sku + '</td></tr>' +
            '<tr><td>Name:</td><td>' + escapeHtml(product.name) + '</td></tr>' +
            '<tr><td>Manufacturer:</td><td>' + escapeHtml(product.manufacturer || 'Unknown') + '</td></tr>' +
            '<tr><td>EAN:</td><td>' + (product.ean || 'N/A') + '</td></tr>' +
            '<tr><td>MPN:</td><td>' + (product.mpn || 'N/A') + '</td></tr>' +
            '<tr><td>Category:</td><td>' + escapeHtml(product.pless_main_category || 'N/A') + '</td></tr>' +
        '</table>' +
    '</div>';
    
    detailsHtml += '<div class="product-detail-section">' +
        '<h6>Stock & Pricing</h6>' +
        '<table class="product-detail-table">' +
            '<tr><td>Stock Qty:</td><td><span class="badge badge-' + (product.qty > 0 ? 'success' : 'danger') + '">' + product.qty + '</span></td></tr>' +
            '<tr><td>Cost:</td><td>£' + parseFloat(product.cost || 0).toFixed(2) + '</td></tr>' +
            '<tr><td>Retail Price:</td><td>£' + parseFloat(product.price || 0).toFixed(2) + '</td></tr>' +
            '<tr><td>Trade Price:</td><td>£' + parseFloat(product.trade || 0).toFixed(2) + '</td></tr>' +
            '<tr><td>Weight:</td><td>' + (product.weight || 0) + 'kg</td></tr>' +
            '<tr><td>Status:</td><td><span class="badge badge-' + (product.enable === 'y' ? 'success' : 'secondary') + '">' + (product.enable === 'y' ? 'Enabled' : 'Disabled') + '</span></td></tr>' +
        '</table>' +
    '</div>';
    
    detailsHtml += '</div>';
    
    if (product.description) {
        detailsHtml += '<div class="product-detail-section">' +
            '<h6>Description</h6>' +
            '<p>' + escapeHtml(product.description) + '</p>' +
        '</div>';
    }
    
    if (isMapped) {
        detailsHtml += '<div class="product-detail-section">' +
            '<h6>Mapping Information</h6>' +
            '<table class="product-detail-table">' +
                '<tr><td>Mapped to:</td><td>' + escapeHtml(product.product_type_name) + '</td></tr>' +
                '<tr><td>Category:</td><td>' + escapeHtml(product.mapped_category) + '</td></tr>' +
                '<tr><td>Mapped Date:</td><td>' + formatDate(product.mapped_at) + '</td></tr>' +
                '<tr><td>Mapped By:</td><td>' + escapeHtml(product.mapped_by || 'Unknown') + '</td></tr>';
        
        if (product.mapping_notes) {
            detailsHtml += '<tr><td>Notes:</td><td>' + escapeHtml(product.mapping_notes) + '</td></tr>';
        }
        
        detailsHtml += '</table></div>';
    }
    
    $('#productDetailsBody').html(detailsHtml);
    $('#mapFromDetailsBtn').toggle(!isMapped).data('sku', product.sku);
    $('#productDetailsModal').modal('show');
}

function viewAllMappings() {
    showSpinner();
    
    $.get('api/product_mapping_simple.php?action=mappings')
        .done(function(response) {
            renderMappingsTable(response.mappings);
            $('#viewMappingsModal').modal('show');
        })
        .fail(function(xhr) {
            showError('Failed to load mappings: ' + getErrorMessage(xhr));
        })
        .always(function() {
            hideSpinner();
        });
}

function viewMappingsForType(essentialTypeId) {
    showSpinner();
    
    $.get('api/product_mapping_simple.php?action=mappings&essential_type_id=' + essentialTypeId)
        .done(function(response) {
            renderMappingsTable(response.mappings);
            $('#viewMappingsModal').modal('show');
        })
        .fail(function(xhr) {
            showError('Failed to load mappings: ' + getErrorMessage(xhr));
        })
        .always(function() {
            hideSpinner();
        });
}

function renderMappingsTable(mappings) {
    const tbody = $('#mappingsTable tbody');
    tbody.empty();
    
    if (mappings.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-link fa-2x mb-2"></i><br>No mappings found</td></tr>');
        return;
    }
    
    mappings.forEach(function(mapping) {
        let rowHtml = '<tr>';
        rowHtml += '<td>' + escapeHtml(mapping.product_type_name) + '</td>';
        rowHtml += '<td><small>' + escapeHtml(mapping.category_name) + '</small></td>';
        rowHtml += '<td>' + mapping.sku + '</td>';
        rowHtml += '<td><strong>' + escapeHtml(mapping.product_name) + '</strong><br><small class="text-muted">' + escapeHtml(mapping.manufacturer || '') + '</small></td>';
        rowHtml += '<td><span class="badge badge-' + (mapping.qty > 0 ? 'success' : 'danger') + '">' + mapping.qty + '</span></td>';
        rowHtml += '<td><small>' + formatDate(mapping.mapped_at) + '</small></td>';
        rowHtml += '<td><div class="btn-group btn-group-sm">';
        rowHtml += '<button class="btn btn-outline-info btn-sm view-mapping-details" data-sku="' + mapping.sku + '" title="View Details"><i class="fas fa-eye"></i></button>';
        
        if (window.MAPPING_CONFIG && window.MAPPING_CONFIG.canEdit) {
            rowHtml += '<button class="btn btn-outline-danger btn-sm unmap-from-table" data-mapping-id="' + mapping.mapping_id + '" title="Unmap"><i class="fas fa-unlink"></i></button>';
        }
        
        rowHtml += '</div></td>';
        rowHtml += '</tr>';
        
        tbody.append(rowHtml);
    });
    
    // Attach event handlers
    $('.view-mapping-details').click(function() {
        const sku = $(this).data('sku');
        $('#viewMappingsModal').modal('hide');
        viewProductDetails(sku);
    });
    
    $('.unmap-from-table').click(function() {
        const mappingId = $(this).data('mapping-id');
        unmapByMappingId(mappingId);
    });
}

function unmapByMappingId(mappingId) {
    confirmDelete('This will remove this product mapping. Are you sure?')
        .then(function(result) {
            if (result.isConfirmed) {
                showSpinner();
                
                $.ajax({
                    url: 'api/product_mapping_simple.php',
                    method: 'DELETE',
                    data: JSON.stringify({ 
                        action: 'delete_mapping',
                        mapping_id: mappingId 
                    }),
                    contentType: 'application/json',
                    dataType: 'json'
                })
                .done(function(response) {
                    if (response.success) {
                        showSuccess('Mapping removed successfully');
                        viewAllMappings();
                        loadStats();
                        loadEssentialTypes();
                        if ($('#productSearch').val().trim()) {
                            searchProducts();
                        }
                    } else {
                        showError(response.error || 'Failed to remove mapping');
                    }
                })
                .fail(function(xhr) {
                    showError('Failed to remove mapping: ' + getErrorMessage(xhr));
                })
                .always(function() {
                    hideSpinner();
                });
            }
        });
}

function exportMappings() {
    showSpinner();
    
    $.get('api/product_mapping_simple.php?action=mappings')
        .done(function(response) {
            const csvContent = generateMappingsCSV(response.mappings);
            downloadCSV(csvContent, 'product_mappings.csv');
        })
        .fail(function(xhr) {
            showError('Failed to export mappings: ' + getErrorMessage(xhr));
        })
        .always(function() {
            hideSpinner();
        });
}

function generateMappingsCSV(mappings) {
    const headers = ['Product Type', 'Category', 'SKU', 'Product Name', 'Manufacturer', 'Stock Qty', 'Cost', 'Retail Price', 'Mapped Date', 'Mapped By', 'Notes'];
    
    let csvContent = headers.join(',') + '\n';
    
    mappings.forEach(function(mapping) {
        const row = [
            '"' + (mapping.product_type_name || '').replace(/"/g, '""') + '"',
            '"' + (mapping.category_name || '').replace(/"/g, '""') + '"',
            mapping.sku,
            '"' + (mapping.product_name || '').replace(/"/g, '""') + '"',
            '"' + (mapping.manufacturer || '').replace(/"/g, '""') + '"',
            mapping.qty,
            mapping.cost || 0,
            mapping.price || 0,
            formatDate(mapping.mapped_at),
            '"' + (mapping.mapped_by || '').replace(/"/g, '""') + '"',
            '"' + (mapping.mapping_notes || '').replace(/"/g, '""') + '"'
        ];
        csvContent += row.join(',') + '\n';
    });
    
    return csvContent;
}

function downloadCSV(csvContent, filename) {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

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
            confirmButtonText: 'Yes, remove it!'
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
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}