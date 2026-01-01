// Wait for jQuery to be available
(function() {
    function initializeWhenReady() {
        if (typeof jQuery !== 'undefined') {
            $(document).ready(function() {
                console.log('Initializing Essential Categories Management');
                
                // Initialize the page
                loadCategories();
                loadMasterCategories();
                
                // Event handlers
                $('#addCategoryBtn').click(function() {
                    openCategoryModal();
                });
                
                $('#categoryForm').submit(function(e) {
                    e.preventDefault();
                    saveCategoryForm();
                });
                
                $('#reorderCategoriesBtn').click(function() {
                    openReorderModal();
                });
                
                $('#saveOrderBtn').click(function() {
                    saveOrder();
                });
            });
        } else {
            setTimeout(initializeWhenReady, 100);
        }
    }
    
    initializeWhenReady();
})();

function loadCategories() {
    showSpinner();
    
    $.get('api/essential_categories.php?action=list')
        .done(function(response) {
            renderCategoriesTable(response.categories);
            initializeDragDrop();
        })
        .fail(function(xhr) {
            showError('Failed to load categories: ' + getErrorMessage(xhr));
        })
        .always(function() {
            hideSpinner();
        });
}

function loadMasterCategories() {
    console.log('=== LOADING MASTER CATEGORIES FROM API ===');
    return $.get('api/essential_categories.php?action=available_categories')
        .done(function(response) {
            console.log('API Response received:', response);
            console.log('Categories in response:', response.categories ? response.categories.length : 'NONE');
            if (response.categories && response.categories.length > 0) {
                console.log('First category structure:', response.categories[0]);
                console.log('Second category structure:', response.categories[1]);
            }
            populateMasterCategorySelect(response.categories);
        })
        .fail(function(xhr) {
            console.error('Failed to load available categories:', xhr);
            console.error('Status:', xhr.status, 'Response:', xhr.responseText);
        });
}

function populateMasterCategorySelect(categories) {
    const select = $('#masterCategorySelect');
    const currentValue = select.val();
    
    console.log('=== POPULATING DROPDOWN ===');
    console.log('Categories received:', categories.length);
    console.log('Raw categories data:', categories);
    
    // Store all categories for filtering
    window.allCategories = categories;
    
    // Clear all options and re-add placeholder
    select.empty();
    select.append('<option value="">-- Select a category --</option>');
    
    categories.forEach(function(category) {
        console.log('Processing category:', category); // DEBUG: See the full category object
        console.log('  - ID type:', typeof category.id, 'Value:', category.id); // DEBUG: Check ID type and value
        
        const option = $('<option></option>')
            .attr('value', category.id)
            .text(`${escapeHtml(category.pless_main_category)} - ${escapeHtml(category.pos_category)}`)
            .data('category', category);
        select.append(option);
        
        // Verify the option was created correctly
        const createdOption = select.find('option:last');
        console.log('  - Created option value:', createdOption.val(), 'text:', createdOption.text().substring(0, 40)); // DEBUG
        
        // Debug first few categories
        if (categories.indexOf(category) < 5) {
            console.log(`Added option: ID=${category.id}, Text="${category.pless_main_category} - ${category.pos_category}"`);
        }
    });
    
    console.log('Total options in dropdown:', select.find('option').length);
    console.log('Checking all option values:');
    select.find('option').each(function(i) {
        if (i < 10) {
            console.log(`  Option ${i}: value="${$(this).val()}" text="${$(this).text().substring(0, 40)}"`);
        }
    });
    
    // Restore selection
    if (currentValue && currentValue !== '') {
        select.val(currentValue);
        console.log('Restored selection to:', currentValue);
    }
    
    // Set up filtering
    setupCategoryFilter();
    
    // Auto-fill display name when category is selected
    $('#masterCategorySelect').off('change.autofill').on('change.autofill', function() {
        const selectedOption = $(this).find('option:selected');
        const selectedValue = selectedOption.val();
        console.log('Category selected, value:', selectedValue);
        
        if (selectedValue && selectedValue !== '') {
            const categoryText = selectedOption.text().split(' - ')[0]; // Take first part
            $('#displayName').val(categoryText);
            // Remove error highlighting when valid selection is made
            $(this).removeClass('is-invalid');
        }
    });
    
    console.log('=== DROPDOWN POPULATED ===');
}

function setupCategoryFilter() {
    $('#masterCategoryFilter').off('input.filter').on('input.filter', function() {
        const filterText = $(this).val().toLowerCase();
        const select = $('#masterCategorySelect');
        
        console.log('=== FILTER TRIGGERED ===');
        console.log('Filter text:', filterText);
        
        // Clear and re-add placeholder
        select.empty();
        select.append('<option value="">-- Select a category --</option>');
        
        if (!window.allCategories) {
            console.warn('No categories available for filtering!');
            return;
        }
        
        let addedCount = 0;
        window.allCategories.forEach(function(category) {
            const categoryText = `${category.pless_main_category} - ${category.pos_category}`.toLowerCase();
            
            if (!filterText || categoryText.includes(filterText)) {
                console.log('  Adding filtered option: ID=', category.id, 'Text=', category.pless_main_category); // DEBUG
                
                const option = $('<option></option>')
                    .attr('value', category.id)
                    .text(`${escapeHtml(category.pless_main_category)} - ${escapeHtml(category.pos_category)}`)
                    .data('category', category);
                select.append(option);
                addedCount++;
                
                // Check first created option
                if (addedCount === 1) {
                    const firstOption = select.find('option:last');
                    console.log('  First filtered option value:', firstOption.val());
                }
            }
        });
        
        console.log('Filter added', addedCount, 'options');
        console.log('=== FILTER COMPLETE ===');
    });
}

function renderCategoriesTable(categories) {
    const tbody = $('#categoriesTableBody');
    tbody.empty();
    
    if (categories.length === 0) {
        const colSpan = window.ESSENTIALS_CONFIG.canReorder ? 6 : 5;
        tbody.append(`
            <tr>
                <td colspan="${colSpan}" class="text-center text-muted py-4">
                    <i class="fas fa-sitemap fa-2x mb-2"></i><br>
                    <h5>No Categories Found</h5>
                    <p>No essential categories have been created yet.</p>
                    <button class="btn btn-primary btn-sm" onclick="$('#addCategoryBtn').click()">
                        <i class="fas fa-plus"></i> Add First Category
                    </button>
                </td>
            </tr>
        `);
        return;
    }
    
    categories.forEach(function(category) {
        const row = $(`
            <tr data-category-id="${category.id}" data-display-order="${category.display_order}" 
                class="sortable-row ${category.is_active ? '' : 'table-secondary'}">
                ${window.ESSENTIALS_CONFIG.canReorder ? `
                    <td class="drag-handle" title="Drag to reorder">
                        <i class="fas fa-grip-vertical text-muted"></i>
                    </td>
                ` : ''}
                <td>
                    <span class="badge badge-secondary order-badge">${category.display_order}</span>
                </td>
                <td>
                    <div>
                        <strong class="${category.is_active ? '' : 'text-muted'}">${escapeHtml(category.display_name)}</strong>
                        ${category.notes ? `<br><small class="text-muted">${escapeHtml(category.notes)}</small>` : ''}
                        ${!category.is_active ? '<br><small class="text-danger">Inactive</small>' : ''}
                    </div>
                </td>
                <td>
                    <small class="text-muted">${escapeHtml(category.pless_main_category || 'Unknown')}</small><br>
                    <small>${escapeHtml(category.pos_category || '')}</small>
                </td>
                <td>
                    <span class="badge badge-${category.is_active ? 'success' : 'secondary'}">
                        ${category.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary btn-sm edit-category" 
                                data-category-id="${category.id}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${window.ESSENTIALS_CONFIG.isSuperAdmin ? `
                            <button class="btn btn-outline-danger btn-sm delete-category" 
                                    data-category-id="${category.id}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `);
        
        tbody.append(row);
    });
    
    // Attach event handlers
    attachEventHandlers();
}

function initializeDragDrop() {
    if (!window.ESSENTIALS_CONFIG.canReorder) return;
    
    const $tbody = $('.sortable-tbody');
    
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
            const colSpan = window.ESSENTIALS_CONFIG.canReorder ? 6 : 5;
            ui.placeholder.html(`<td colspan="${colSpan}" class="text-center text-muted py-2"><i class="fas fa-arrows-alt-v"></i> Drop here to reorder</td>`);
            ui.item.addClass('dragging');
        },
        stop: function(e, ui) {
            ui.item.removeClass('dragging');
            updateRowOrders();
            saveInlineOrder();
        }
    }).disableSelection();
}

function updateRowOrders() {
    let order = 1;
    $('.sortable-tbody tr.sortable-row').each(function() {
        const $row = $(this);
        $row.attr('data-display-order', order);
        $row.find('.order-badge').text(order);
        order++;
    });
}

function saveInlineOrder() {
    const order = [];
    $('.sortable-tbody tr.sortable-row').each(function() {
        order.push($(this).data('category-id'));
    });
    
    if (order.length === 0) return;
    
    // Show subtle loading indicator
    const $table = $('.table-container');
    $table.addClass('reordering');
    
    $.ajax({
        url: 'api/essential_categories.php',
        method: 'POST',
        data: JSON.stringify({ action: 'reorder', order: order }),
        contentType: 'application/json',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.success) {
            // Show subtle success feedback
            $table.addClass('reorder-success');
            setTimeout(() => {
                $table.removeClass('reorder-success');
            }, 1000);
        } else {
            showError(response.error || 'Failed to save order');
            // Reload to restore original order
            loadCategories();
        }
    })
    .fail(function(xhr) {
        showError('Failed to save order: ' + getErrorMessage(xhr));
        // Reload to restore original order
        loadCategories();
    })
    .always(function() {
        $table.removeClass('reordering');
    });
}

function attachEventHandlers() {
    // Edit category
    $('.edit-category').click(function() {
        const categoryId = $(this).data('category-id');
        editCategory(categoryId);
    });
    
    // Delete category
    $('.delete-category').click(function() {
        const categoryId = $(this).data('category-id');
        deleteCategory(categoryId);
    });
}

function openCategoryModal(categoryData = null) {
    const modal = $('#categoryModal');
    const form = $('#categoryForm')[0];
    
    console.log('=== OPENING MODAL ===');
    console.log('Category data received:', categoryData);
    
    // Reset form
    form.reset();
    $('#categoryId').val('');
    $('#masterCategoryFilter').val(''); // Clear filter
    $('#masterCategorySelect').val('').removeClass('is-invalid'); // Reset to placeholder and remove error
    $('#displayName').val('').removeClass('is-invalid'); // Clear and remove error
    
    if (categoryData) {
        // Edit mode
        $('#categoryModalLabel').text('Edit Essential Category');
        $('#categoryId').val(categoryData.id);
        $('#displayName').val(categoryData.display_name);
        $('#displayOrder').val(categoryData.display_order);
        $('#notes').val(categoryData.notes || '');
        $('#isActive').prop('checked', categoryData.is_active == 1);
        
        console.log('Setting form fields for edit mode');
        console.log('Master category ID to select:', categoryData.master_category_id);
        
        // Set the master category selection
        if (categoryData.master_category_id) {
            $('#originalMasterCategoryId').val(categoryData.master_category_id);
            
            // Try to set immediately if options are already loaded
            const selectElement = $('#masterCategorySelect');
            const allOptions = selectElement.find('option');
            console.log('Available options in dropdown:', allOptions.length);
            
            allOptions.each(function(index) {
                const optionVal = $(this).val();
                const optionText = $(this).text();
                if (index < 10) { // Show first 10 options
                    console.log(`Option ${index}: value="${optionVal}", text="${optionText}"`);
                }
            });
            
            const targetOption = selectElement.find(`option[value="${categoryData.master_category_id}"]`);
            console.log('Target option found:', targetOption.length > 0);
            
            if (targetOption.length > 0) {
                selectElement.val(categoryData.master_category_id);
                console.log('✓ Immediately set master category selection to:', categoryData.master_category_id);
                console.log('✓ Dropdown value is now:', selectElement.val());
            } else {
                console.log('⚠ Target option not found, will try after modal is shown');
                
                // Wait for modal to be shown, then set the selection
                modal.one('shown.bs.modal', function() {
                    console.log('Modal shown, trying to set selection again...');
                    
                    // Ensure all categories are visible (clear any filter)
                    $('#masterCategoryFilter').val('').trigger('input');
                    
                    // Small delay to ensure DOM is ready
                    setTimeout(function() {
                        const selectElement = $('#masterCategorySelect');
                        const targetOption = selectElement.find(`option[value="${categoryData.master_category_id}"]`);
                        
                        console.log('After modal shown - target option found:', targetOption.length > 0);
                        
                        if (targetOption.length > 0) {
                            selectElement.val(categoryData.master_category_id);
                            console.log('✓ Delayed set master category selection to:', categoryData.master_category_id);
                            console.log('✓ Dropdown value is now:', selectElement.val());
                        } else {
                            console.error('✗ Could not find option with value:', categoryData.master_category_id);
                            console.log('Available options after modal shown:');
                            selectElement.find('option').each(function(index) {
                                if (index < 20) {
                                    console.log(`  ${index}: value="${$(this).val()}", text="${$(this).text()}"`);
                                }
                            });
                        }
                    }, 150);
                });
            }
        } else {
            console.log('⚠ No master_category_id provided');
        }
    } else {
        // Add mode
        $('#categoryModalLabel').text('Add Essential Category');
        $('#isActive').prop('checked', true);
        
        // Calculate next display order
        calculateNextDisplayOrder();
        console.log('Add mode - no category data to populate');
        console.log('Dropdown reset to placeholder, value:', $('#masterCategorySelect').val());
    }
    
    console.log('=== SHOWING MODAL ===');
    modal.modal('show');
}

function calculateNextDisplayOrder() {
    let maxOrder = 0;
    $('.sortable-tbody tr.sortable-row').each(function() {
        const order = parseInt($(this).find('.order-badge').text());
        if (order > maxOrder) {
            maxOrder = order;
        }
    });
    $('#displayOrder').val(maxOrder + 1);
}

function saveCategoryForm() {
    const form = $('#categoryForm')[0];
    const formData = new FormData(form);
    const categoryId = $('#categoryId').val();
    
    // Debug: Log form elements and their values
    console.log('=== SAVING CATEGORY ===');
    console.log('Category ID:', categoryId);
    console.log('Master category select element:', $('#masterCategorySelect').length);
    console.log('Master category selected value:', $('#masterCategorySelect').val());
    console.log('Display name element:', $('#displayName').length);
    console.log('Display name value:', $('#displayName').val());
    console.log('Form data entries:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Get values directly from form elements
    const masterCategoryId = $('#masterCategorySelect').val();
    const displayName = $('#displayName').val()?.trim();
    
    // Convert master_category_id to number and validate
    const masterCategoryIdNum = parseInt(masterCategoryId);
    
    console.log('Parsed master_category_id:', masterCategoryIdNum);
    console.log('Is valid number:', !isNaN(masterCategoryIdNum));
    console.log('Is greater than 0:', masterCategoryIdNum > 0);
    
    // Validation FIRST - before building data object
    if (!masterCategoryId || masterCategoryId === '' || masterCategoryId === '0' || isNaN(masterCategoryIdNum) || masterCategoryIdNum <= 0) {
        showError('⚠️ Please select a master category from the dropdown');
        $('#masterCategorySelect').addClass('is-invalid').focus();
        return;
    }
    
    if (!displayName || displayName === '') {
        showError('⚠️ Please enter a display name');
        $('#displayName').addClass('is-invalid').focus();
        return;
    }
    
    // Remove error highlighting if validation passes
    $('#masterCategorySelect').removeClass('is-invalid');
    $('#displayName').removeClass('is-invalid');
    
    // Build data object AFTER validation
    const data = {
        action: categoryId ? 'update' : 'create',
        master_category_id: masterCategoryIdNum, // Use the parsed number
        display_name: displayName,
        display_order: parseInt($('#displayOrder').val()) || 1,
        notes: $('#notes').val() || '',
        is_active: $('#isActive').is(':checked') ? 1 : 0
    };
    
    if (categoryId) {
        data.id = categoryId;
    }
    
    console.log('Data being sent:', data);
    
    const method = categoryId ? 'PUT' : 'POST';
    
    showSpinner();
    
    $.ajax({
        url: 'api/essential_categories.php',
        method: method,
        data: JSON.stringify(data),
        contentType: 'application/json',
        dataType: 'json'
    })
    .done(function(response) {
        console.log('Success response:', response);
        if (response.success) {
            $('#categoryModal').modal('hide');
            loadCategories();
            loadMasterCategories();
            showSuccess(categoryId ? 'Category updated successfully' : 'Category added successfully');
        } else {
            showError(response.error || 'Failed to save category');
        }
    })
    .fail(function(xhr) {
        console.error('Error response:', xhr);
        console.error('Response text:', xhr.responseText);
        try {
            const errorResponse = JSON.parse(xhr.responseText);
            console.error('Parsed error:', errorResponse);
            showError('Failed to save category: ' + (errorResponse.error || getErrorMessage(xhr)));
        } catch (e) {
            showError('Failed to save category: ' + getErrorMessage(xhr));
        }
    })
    .always(function() {
        hideSpinner();
    });
}

function editCategory(categoryId) {
    // Get detailed category data from API
    $.get(`api/essential_categories.php?action=details&id=${categoryId}`)
        .done(function(response) {
            const categoryData = response.category;
            console.log('Edit category data from API:', categoryData);
            
            // Ensure master categories are loaded before opening modal
            if (!window.allCategories || window.allCategories.length === 0) {
                loadMasterCategories().then(() => {
                    openCategoryModal(categoryData);
                });
            } else {
                openCategoryModal(categoryData);
            }
        })
        .fail(function(xhr) {
            console.error('Failed to get category details:', xhr);
            showError('Failed to load category details: ' + getErrorMessage(xhr));
        });
}

function findAndSetMasterCategory(categoryData, displayedMainCategory) {
    console.log('=== DEBUGGING CATEGORY MATCHING ===');
    console.log('Looking for category:', displayedMainCategory);
    console.log('POS category:', categoryData.pos_category);
    console.log('Available categories count:', window.allCategories ? window.allCategories.length : 0);
    
    if (window.allCategories && displayedMainCategory) {
        // Get both the main category and pos category from the table
        const displayedPosCategory = categoryData.pos_category;
        
        console.log('Searching for exact match...');
        // Try to find exact match first (both main and pos category)
        let matchedCategory = window.allCategories.find(cat => {
            const mainMatch = cat.pless_main_category === displayedMainCategory;
            const posMatch = cat.pos_category === displayedPosCategory;
            return mainMatch && posMatch;
        });
        
        // If no exact match, try matching just the main category
        if (!matchedCategory) {
            console.log('No exact match found, trying main category only...');
            matchedCategory = window.allCategories.find(cat => {
                return cat.pless_main_category === displayedMainCategory;
            });
        }
        
        // If still no match, try partial matching for similar categories
        if (!matchedCategory) {
            console.log('No main category match, trying partial matches...');
            
            // Try to find categories that contain similar keywords
            const searchTerms = displayedMainCategory.toLowerCase().split('/');
            
            matchedCategory = window.allCategories.find(cat => {
                const catMain = cat.pless_main_category.toLowerCase();
                // Check if the category contains the same path segments
                return searchTerms.every(term => catMain.includes(term));
            });
            
            if (matchedCategory) {
                console.log('Found partial match:', matchedCategory);
            }
        }
        
        // If still no match, try to find any category in the same top-level group
        if (!matchedCategory) {
            console.log('No partial match, trying same category group...');
            const topLevel = displayedMainCategory.split('/')[0]; // e.g., "components"
            
            matchedCategory = window.allCategories.find(cat => {
                return cat.pless_main_category.toLowerCase().startsWith(topLevel.toLowerCase());
            });
            
            if (matchedCategory) {
                console.log('Found category in same group:', matchedCategory);
            }
        }
        
        if (matchedCategory) {
            categoryData.master_category_id = matchedCategory.id;
            console.log('✓ Found matching category:', matchedCategory);
            console.log('✓ Setting master_category_id to:', matchedCategory.id);
        } else {
            console.log('✗ No matching category found at all!');
            console.log('This essential category may be mapped to a deleted/missing master category');
            console.log('Available categories in same group:');
            window.allCategories.forEach((cat, index) => {
                if (cat.pless_main_category.toLowerCase().includes('memory') || cat.pless_main_category.toLowerCase().includes('components')) {
                    console.log(`  ${index + 1}. ID: ${cat.id}, Main: "${cat.pless_main_category}", POS: "${cat.pos_category}"`);
                }
            });
        }
    } else {
        console.log('✗ Missing data - allCategories:', !!window.allCategories, 'displayedMainCategory:', !!displayedMainCategory);
    }
    
    console.log('Final category data:', categoryData);
    console.log('=== END DEBUGGING ===');
    openCategoryModal(categoryData);
}

function getCurrentCategoryData(categoryId) {
    const row = $(`tr[data-category-id="${categoryId}"]`);
    if (!row.length) return null;
    
    // Adjust column indices based on whether drag column exists
    const dragOffset = window.ESSENTIALS_CONFIG.canReorder ? 1 : 0;
    
    // Extract data from the table row
    const displayOrder = row.find(`td:eq(${dragOffset}) .badge`).text();
    const displayNameCell = row.find(`td:eq(${dragOffset + 1})`);
    const displayName = displayNameCell.find('strong').text();
    const notesElement = displayNameCell.find('small').first();
    const notes = notesElement.length > 0 ? notesElement.text() : '';
    
    const originalCategoryCell = row.find(`td:eq(${dragOffset + 2})`);
    const smallElements = originalCategoryCell.find('small');
    const plessMainCategory = smallElements.length > 0 ? smallElements.first().text() : '';
    const posCategory = smallElements.length > 1 ? smallElements.last().text() : '';
    
    const isActive = !row.hasClass('table-secondary');
    
    const categoryData = {
        id: categoryId,
        display_name: displayName,
        display_order: parseInt(displayOrder),
        notes: notes,
        is_active: isActive ? 1 : 0,
        pless_main_category: plessMainCategory,
        pos_category: posCategory
    };
    
    console.log('Extracted category data from table:', categoryData);
    console.log('Row HTML:', row.html());
    
    return categoryData;
}

function deleteCategory(categoryId) {
    confirmDelete('This category will be permanently deleted. Any associated product types will need to be reassigned.')
        .then((result) => {
            if (result.isConfirmed) {
                showSpinner();
                
                $.ajax({
                    url: 'api/essential_categories.php',
                    method: 'DELETE',
                    data: JSON.stringify({ id: categoryId }),
                    contentType: 'application/json',
                    dataType: 'json'
                })
                .done(function(response) {
                    if (response.success) {
                        loadCategories();
                        loadMasterCategories(); // Reload available categories
                        showSuccess('Category deleted successfully');
                    } else {
                        showError(response.error || 'Failed to delete category');
                    }
                })
                .fail(function(xhr) {
                    showError('Failed to delete category: ' + getErrorMessage(xhr));
                })
                .always(function() {
                    hideSpinner();
                });
            }
        });
}

function openReorderModal() {
    const categories = getCurrentCategories();
    
    const sortableList = $('#sortableCategories');
    sortableList.empty();
    
    categories.forEach(function(category) {
        const item = $(`
            <li class="list-group-item d-flex justify-content-between align-items-center" 
                data-category-id="${category.id}">
                <div>
                    <i class="fas fa-grip-vertical text-muted me-2"></i>
                    <strong>${escapeHtml(category.display_name)}</strong>
                    ${category.notes ? `<br><small class="text-muted">${escapeHtml(category.notes)}</small>` : ''}
                    <br><small class="text-muted">${escapeHtml(category.pless_main_category || '')}</small>
                </div>
                <span class="badge badge-secondary">${category.display_order}</span>
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

function getCurrentCategories() {
    const categories = [];
    const dragOffset = window.ESSENTIALS_CONFIG.canReorder ? 1 : 0;
    
    $('.sortable-tbody tr[data-category-id]').each(function() {
        const row = $(this);
        const categoryId = row.data('category-id');
        const displayOrder = parseInt(row.find(`td:eq(${dragOffset}) .badge`).text());
        const displayName = row.find(`td:eq(${dragOffset + 1}) strong`).text();
        const notes = row.find(`td:eq(${dragOffset + 1}) small`).first().text();
        
        categories.push({
            id: categoryId,
            display_order: displayOrder,
            display_name: displayName,
            notes: notes
        });
    });
    
    return categories.sort((a, b) => a.display_order - b.display_order);
}

function saveOrder() {
    const order = [];
    $('#sortableCategories li').each(function() {
        order.push($(this).data('category-id'));
    });
    
    showSpinner();
    
    $.ajax({
        url: 'api/essential_categories.php',
        method: 'POST',
        data: JSON.stringify({ action: 'reorder', order: order }),
        contentType: 'application/json',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.success) {
            $('#reorderModal').modal('hide');
            loadCategories();
            showSuccess('Category order updated successfully');
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
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}