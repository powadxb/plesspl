/**
 * RMA Phase 2: Batch Management JavaScript
 * Handles batch list, creation, and editing
 */

let batchesTable;
let allBatches = [];
let selectedItems = [];

$(document).ready(function() {
    // Initialize DataTable
    initializeBatchesTable();
    
    // Load initial data
    loadBatches();
    
    // Event listeners
    $('#btnCreateBatch').click(openCreateBatchModal);
    $('#filterStatus, #filterSupplier').change(applyFilters);
    $('#filterSearch').on('keyup', applyFilters);
    $('#btnResetFilters').click(resetFilters);
    
    // Create batch modal events
    initializeCreateBatchModal();
    
    // View batch modal events
    initializeViewBatchModal();
    
    // View RMA modal events (for viewing items from batch)
    initializeViewRMAModal();
});

/**
 * Initialize DataTables
 */
function initializeBatchesTable() {
    batchesTable = $('#batchesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [
            { targets: [10], orderable: false } // Actions column
        ]
    });
}

/**
 * Load all batches
 */
function loadBatches() {
    $.ajax({
        url: 'php/ajax/get-batch-list.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                allBatches = response.batches;
                populateBatchesTable(response.batches);
                updateStatistics(response.stats);
                populateSupplierFilter(response.suppliers);
            } else {
                showAlert('error', response.message);
            }
        },
        error: function() {
            showAlert('error', 'Failed to load batches');
        }
    });
}

/**
 * Populate batches table
 */
function populateBatchesTable(batches) {
    batchesTable.clear();
    
    batches.forEach(function(batch) {
        const statusBadge = getStatusBadge(batch.batch_status);
        const value = parseFloat(batch.total_value || 0).toFixed(2);
        
        batchesTable.row.add([
            batch.id,
            statusBadge,
            batch.supplier_name,
            batch.supplier_rma_number || '<em class="text-muted">Not set</em>',
            batch.item_count || 0,
            '£' + value,
            batch.date_created,
            batch.date_submitted || '<em class="text-muted">-</em>',
            batch.date_shipped || '<em class="text-muted">-</em>',
            batch.age_days + ' days',
            `<button class="btn btn-sm btn-primary" onclick="viewBatch(${batch.id})">
                <i class="fas fa-eye"></i> View
            </button>`
        ]);
    });
    
    batchesTable.draw();
}

/**
 * Update statistics cards
 */
function updateStatistics(stats) {
    $('#stat-draft').text(stats.draft || 0);
    $('#stat-submitted').text(stats.submitted || 0);
    $('#stat-shipped').text(stats.shipped || 0);
    $('#stat-completed').text(stats.completed || 0);
}

/**
 * Populate supplier filter dropdown
 */
function populateSupplierFilter(suppliers) {
    const $select = $('#filterSupplier');
    $select.find('option:not(:first)').remove();
    
    suppliers.forEach(function(supplier) {
        $select.append(`<option value="${supplier}">${supplier}</option>`);
    });
}

/**
 * Apply filters
 */
function applyFilters() {
    const status = $('#filterStatus').val().toLowerCase();
    const supplier = $('#filterSupplier').val().toLowerCase();
    const search = $('#filterSearch').val().toLowerCase();
    
    const filtered = allBatches.filter(function(batch) {
        const matchStatus = !status || batch.batch_status === status;
        const matchSupplier = !supplier || batch.supplier_name.toLowerCase() === supplier.toLowerCase();
        const matchSearch = !search || 
            (batch.supplier_rma_number && batch.supplier_rma_number.toLowerCase().includes(search)) ||
            (batch.shipping_tracking && batch.shipping_tracking.toLowerCase().includes(search)) ||
            (batch.notes && batch.notes.toLowerCase().includes(search));
        
        return matchStatus && matchSupplier && matchSearch;
    });
    
    populateBatchesTable(filtered);
}

/**
 * Reset filters
 */
function resetFilters() {
    $('#filterStatus').val('');
    $('#filterSupplier').val('');
    $('#filterSearch').val('');
    populateBatchesTable(allBatches);
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge bg-secondary">Draft</span>',
        'submitted': '<span class="badge bg-primary">Submitted</span>',
        'shipped': '<span class="badge bg-warning text-dark">Shipped</span>',
        'completed': '<span class="badge bg-success">Completed</span>',
        'cancelled': '<span class="badge bg-danger">Cancelled</span>'
    };
    return badges[status] || status;
}

/**
 * =============================================================================
 * CREATE BATCH MODAL
 * =============================================================================
 */

function initializeCreateBatchModal() {
    $('#batchSupplierSelect').change(loadAvailableItems);
    $('#selectAllItems').change(toggleSelectAll);
    $('#itemQuickFilter').on('keyup', filterAvailableItems);
    $('#btnNextToStep2').click(goToStep2);
    $('#btnBackToStep1').click(goToStep1);
    
    // Use event delegation for modal button to ensure it works even if modal loads dynamically
    $(document).on('click', '#btnSubmitBatch', createBatch);
}

/**
 * Open create batch modal
 */
function openCreateBatchModal() {
    // Reset modal
    selectedItems = [];
    $('#createStep1').show();
    $('#createStep2').hide();
    $('#batchSupplierSelect').val('');
    $('#availableItemsTable tbody').empty();
    $('#selectedCount').text('0');
    $('#selectedValue').text('£0.00');
    $('#btnNextToStep2').prop('disabled', true);
    
    // Load suppliers
    loadSuppliersForBatch();
    
    // Show modal
    const modal = new bootstrap.Modal($('#createBatchModal')[0]);
    modal.show();
}

/**
 * Load suppliers with available items
 */
function loadSuppliersForBatch() {
    $.ajax({
        url: 'php/ajax/get-available-items.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const $select = $('#batchSupplierSelect');
                $select.find('option:not(:first)').remove();
                
                response.suppliers.forEach(function(supplier) {
                    $select.append(`
                        <option value="${supplier.supplier_name}">
                            ${supplier.supplier_name} (${supplier.item_count} items, £${parseFloat(supplier.total_value).toFixed(2)})
                        </option>
                    `);
                });
            }
        }
    });
}

/**
 * Load available items for selected supplier
 */
function loadAvailableItems() {
    const supplier = $('#batchSupplierSelect').val();
    if (!supplier) {
        $('#availableItemsTable tbody').empty();
        return;
    }
    
    $.ajax({
        url: 'php/ajax/get-available-items.php',
        method: 'GET',
        data: { supplier: supplier },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateAvailableItems(response.items);
            }
        }
    });
}

/**
 * Populate available items table
 */
function populateAvailableItems(items) {
    const $tbody = $('#availableItemsTable tbody');
    $tbody.empty();
    
    if (items.length === 0) {
        $tbody.append('<tr><td colspan="8" class="text-center text-muted">No items available</td></tr>');
        return;
    }
    
    items.forEach(function(item) {
        const cost = parseFloat(item.cost_at_creation || 0).toFixed(2);
        $tbody.append(`
            <tr data-item-id="${item.id}" data-cost="${cost}">
                <td><input type="checkbox" class="item-checkbox" value="${item.id}"></td>
                <td>${item.tracking_number || item.barcode || '-'}</td>
                <td>${item.sku || '-'}</td>
                <td><small>${item.product_name}</small></td>
                <td><small>${item.serial_number || '-'}</small></td>
                <td><small>${item.fault_name}</small></td>
                <td>£${cost}</td>
                <td>${item.date_discovered}</td>
            </tr>
        `);
    });
    
    // Re-attach checkbox listeners
    $('.item-checkbox').change(updateSelectedItems);
}

/**
 * Filter available items
 */
function filterAvailableItems() {
    const filter = $('#itemQuickFilter').val().toLowerCase();
    $('#availableItemsTable tbody tr').each(function() {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(filter));
    });
}

/**
 * Toggle select all
 */
function toggleSelectAll() {
    const checked = $('#selectAllItems').is(':checked');
    $('.item-checkbox:visible').prop('checked', checked).trigger('change');
}

/**
 * Update selected items
 */
function updateSelectedItems() {
    selectedItems = [];
    let totalValue = 0;
    
    $('.item-checkbox:checked').each(function() {
        const $row = $(this).closest('tr');
        const itemId = parseInt($(this).val());
        const cost = parseFloat($row.data('cost')) || 0;
        
        selectedItems.push(itemId);
        totalValue += cost;
    });
    
    $('#selectedCount').text(selectedItems.length);
    $('#selectedValue').text('£' + totalValue.toFixed(2));
    $('#btnNextToStep2').prop('disabled', selectedItems.length === 0);
}

/**
 * Go to step 2
 */
function goToStep2() {
    if (selectedItems.length === 0) return;
    
    const supplier = $('#batchSupplierSelect').val();
    const itemCount = selectedItems.length;
    const totalValue = $('#selectedValue').text();
    
    // Populate step 2
    $('#batchSupplierDisplay').val(supplier);
    $('#batchItemCount').val(itemCount);
    $('#summaryItems').text(itemCount);
    $('#summaryValue').text(totalValue);
    
    // Switch steps
    $('#createStep1').hide();
    $('#createStep2').show();
    
    updateSummaryStatus();
}

/**
 * Go back to step 1
 */
function goToStep1() {
    $('#createStep2').hide();
    $('#createStep1').show();
}

/**
 * Update summary status display
 */
function updateSummaryStatus() {
    const status = $('#batchInitialStatus').val();
    const statusText = status === 'draft' ? 'Draft' : 'Submitted';
    $('#summaryStatus').text(statusText);
}

// Update summary when status changes
$('#batchInitialStatus').change(updateSummaryStatus);

/**
 * Create the batch
 */
function createBatch() {
    const data = {
        supplier: $('#batchSupplierDisplay').val(),
        rma_number: $('#batchRMANumber').val() || null,
        status: $('#batchInitialStatus').val(),
        notes: $('#batchNotes').val() || null,
        item_ids: selectedItems
    };
    
    $.ajax({
        url: 'php/ajax/create-batch.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                bootstrap.Modal.getInstance($('#createBatchModal')[0]).hide();
                loadBatches();
            } else {
                showAlert('error', response.message);
            }
        },
        error: function() {
            showAlert('error', 'Failed to create batch');
        }
    });
}

/**
 * =============================================================================
 * VIEW BATCH MODAL
 * =============================================================================
 */

function initializeViewBatchModal() {
    $('#btnUpdateRMANumber').click(updateBatchRMANumber);
    $('#btnUpdateStatus').click(updateBatchStatus);
    $('#btnUpdateShipping').click(updateBatchShipping);
    $('#btnUpdateNotes').click(updateBatchNotes);
    $('#btnDeleteBatch').click(deleteBatch);
    $('#btnPrintBatch').click(printBatch);
}

/**
 * View batch details
 */
function viewBatch(batchId) {
    $.ajax({
        url: 'php/ajax/get-batch-details.php',
        method: 'GET',
        data: { batch_id: batchId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateBatchModal(response.batch, response.items, response.statistics);
                const modal = new bootstrap.Modal($('#viewBatchModal')[0]);
                modal.show();
            } else {
                showAlert('error', response.message);
            }
        }
    });
}

/**
 * Populate batch modal with data
 */
function populateBatchModal(batch, items, stats) {
    // Store batch ID
    $('#viewBatchModal').data('batch-id', batch.id);
    
    // Check if user can edit completed batches
    const canEditCompleted = $('#canEditCompleted').val() === '1';
    const isCompleted = batch.batch_status === 'completed';
    
    // Show completed batch banner if applicable
    if (isCompleted) {
        const banner = `
            <div class="alert alert-success mb-3" id="completedBatchBanner">
                <i class="fas fa-check-circle"></i> 
                <strong>Batch Completed</strong> - All items in this batch have been resolved.
                ${!canEditCompleted ? ' Only administrators can edit completed batches.' : ' You can still edit this batch.'}
            </div>
        `;
        $('#viewBatchModal .modal-body').prepend(banner);
    } else {
        $('#completedBatchBanner').remove();
    }
    
    // Disable edit controls for completed batches (unless user has permission)
    const canEdit = !isCompleted || canEditCompleted;
    
    // Basic info
    $('#viewBatchId').text(batch.id);
    $('#viewBatchStatusBadge').html(getStatusBadge(batch.batch_status));
    $('#viewSupplier').text(batch.supplier_name);
    $('#viewRMANumber').val(batch.supplier_rma_number || '').prop('disabled', !canEdit);
    $('#viewBatchStatus').val(batch.batch_status).prop('disabled', !canEdit);
    $('#viewCreated').text(batch.date_created);
    $('#viewCreatedBy').text(batch.creator_name);
    
    // Show/hide update buttons based on permissions
    $('#btnUpdateRMANumber').toggle(canEdit);
    $('#btnUpdateStatus').toggle(canEdit);
    
    // Dates
    $('#viewDateSubmitted').val(batch.date_submitted || '').prop('disabled', !canEdit);
    $('#viewDateShipped').val(batch.date_shipped || '').prop('disabled', !canEdit);
    $('#viewShippingTracking').val(batch.shipping_tracking || '').prop('disabled', !canEdit);
    $('#viewShippingCost').val(batch.shipping_cost || '').prop('disabled', !canEdit);
    $('#btnUpdateShipping').toggle(canEdit);
    
    // Notes
    $('#viewNotes').val(batch.notes || '').prop('disabled', !canEdit);
    $('#btnUpdateNotes').toggle(canEdit);
    
    // Statistics
    $('#viewTotalItems').text(stats.total_items);
    $('#viewTotalValue').text('£' + parseFloat(stats.total_value).toFixed(2));
    $('#viewCredited').text('£' + parseFloat(stats.total_credited).toFixed(2));
    $('#viewPending').text('£' + parseFloat(stats.pending_value).toFixed(2));
    
    // Items
    populateBatchItems(items);
    
    // Show/hide danger zone (only for draft batches)
    $('#dangerZone').toggle(batch.batch_status === 'draft' && canEdit);
}

/**
 * Populate items in batch
 */
function populateBatchItems(items) {
    const $tbody = $('#batchItemsList');
    $tbody.empty();
    
    items.forEach(function(item) {
        const cost = parseFloat(item.cost_at_creation || 0).toFixed(2);
        const credited = item.credited_amount ? '£' + parseFloat(item.credited_amount).toFixed(2) : '-';
        
        $tbody.append(`
            <tr>
                <td>${item.tracking_number || item.barcode}</td>
                <td>${item.sku || '-'}</td>
                <td><small>${item.product_name}</small></td>
                <td><small>${item.serial_number || '-'}</small></td>
                <td><small>${item.fault_name}</small></td>
                <td><span class="badge bg-info">${item.status.replace(/_/g, ' ')}</span></td>
                <td>£${cost}</td>
                <td>${credited}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewRMAItem(${item.id})" title="View/Edit Item">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>
        `);
    });
}

/**
 * Update batch RMA number
 */
function updateBatchRMANumber() {
    const batchId = $('#viewBatchModal').data('batch-id');
    const rmaNumber = $('#viewRMANumber').val();
    
    updateBatchField(batchId, 'rma_number', { rma_number: rmaNumber });
}

/**
 * Update batch status
 */
function updateBatchStatus() {
    const batchId = $('#viewBatchModal').data('batch-id');
    const status = $('#viewBatchStatus').val();
    
    updateBatchField(batchId, 'status', { status: status }, function() {
        $('#viewBatchStatusBadge').html(getStatusBadge(status));
        $('#dangerZone').toggle(status === 'draft');
    });
}

/**
 * Update batch shipping info
 */
function updateBatchShipping() {
    const batchId = $('#viewBatchModal').data('batch-id');
    const data = {
        date_submitted: $('#viewDateSubmitted').val() || null,
        date_shipped: $('#viewDateShipped').val() || null,
        shipping_tracking: $('#viewShippingTracking').val() || null,
        shipping_cost: $('#viewShippingCost').val() || null
    };
    
    updateBatchField(batchId, 'shipping', data);
}

/**
 * Update batch notes
 */
function updateBatchNotes() {
    const batchId = $('#viewBatchModal').data('batch-id');
    const notes = $('#viewNotes').val();
    
    updateBatchField(batchId, 'notes', { notes: notes });
}

/**
 * Generic update function
 */
function updateBatchField(batchId, updateType, data, callback) {
    data.batch_id = batchId;
    data.update_type = updateType;
    
    $.ajax({
        url: 'php/ajax/update-batch.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadBatches(); // Refresh list
                if (callback) callback();
            } else {
                showAlert('error', response.message);
            }
        }
    });
}

/**
 * Delete batch
 */
function deleteBatch() {
    if (!confirm('Are you sure you want to delete this batch? All items will be reset to unprocessed status.')) {
        return;
    }
    
    const batchId = $('#viewBatchModal').data('batch-id');
    
    $.ajax({
        url: 'php/ajax/delete-batch.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ batch_id: batchId }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                bootstrap.Modal.getInstance($('#viewBatchModal')[0]).hide();
                loadBatches();
            } else {
                showAlert('error', response.message);
            }
        }
    });
}

/**
 * Print batch
 */
function printBatch() {
    const batchId = $('#viewBatchModal').data('batch-id');
    window.open('/rma/print-batch.php?batch_id=' + batchId, '_blank');
}

/**
 * View RMA Item (opens view RMA modal from batch screen)
 */
function viewRMAItem(itemId) {
    $.ajax({
        url: 'php/ajax/get-rma-details.php',
        method: 'POST',
        data: { rma_id: itemId },
        success: function(response) {
            // This endpoint returns HTML, not JSON
            $('#rmaDetailsContent').html(response);
            $('#viewRmaModal').modal('show');
            
            // Store the RMA ID for updates
            $('#updateRmaId').val(itemId);
        },
        error: function() {
            showAlert('error', 'Failed to load RMA details');
        }
    });
}

/**
 * Initialize View RMA Modal event handlers
 */
function initializeViewRMAModal() {
    // Fix modal scrolling issue when RMA modal closes
    $('#viewRmaModal').on('hidden.bs.modal', function() {
        // Re-enable scrolling on batch modal
        if ($('#viewBatchModal').hasClass('show')) {
            $('body').addClass('modal-open');
            // Remove extra backdrop that might be left behind
            if ($('.modal-backdrop').length > 1) {
                $('.modal-backdrop').first().remove();
            }
        }
    });
    
    // Fix modal stacking when RMA modal opens
    $('#viewRmaModal').on('shown.bs.modal', function() {
        // Ensure proper z-index for stacked modals
        const backdropZIndex = parseInt($('.modal-backdrop').last().css('z-index'));
        if (backdropZIndex) {
            $(this).css('z-index', backdropZIndex + 10);
        }
    });
    
    // Show/hide conditional fields based on status selection
    $(document).on('change', '#newStatus', function() {
        const status = $(this).val();
        
        // Show credited amount for credited status
        $('#creditedAmountGroup').toggle(status === 'credited');
        
        // Show shipping tracking and date sent for "sent" status
        $('#shippingTrackingGroup, #sendDateGroup').toggle(status === 'sent');
    });
    
    // Status update form submission
    $(document).on('submit', '#updateStatusForm', function(e) {
        e.preventDefault();
        
        const rmaId = $('#updateRmaId').val();
        const status = $('#newStatus').val();
        const creditedAmount = $('#creditedAmount').val();
        const shippingTracking = $('#shippingTracking').val();
        const dateSent = $('#dateSent').val();
        
        if (!status) {
            showAlert('error', 'Please select a status');
            return;
        }
        
        $.ajax({
            url: 'php/ajax/update-status.php',
            method: 'POST',
            data: {
                rma_id: rmaId,
                status: status,
                credited_amount: creditedAmount || null,
                shipping_tracking: shippingTracking || null,
                date_sent: dateSent || null
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Check if batch was auto-completed
                    if (response.batch_completed) {
                        showAlert('success', response.message);
                        // Reload batch list to show completed status
                        loadBatches();
                    } else {
                        showAlert('success', response.message);
                    }
                    
                    // Reload the RMA details
                    viewRMAItem(rmaId);
                    
                    // Reload batch if we're viewing from a batch
                    const batchId = $('#viewBatchModal').data('batch-id');
                    if (batchId) {
                        setTimeout(function() {
                            viewBatch(batchId);
                        }, 500);
                    }
                } else {
                    showAlert('error', response.message || 'Failed to update status');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Failed to update status';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {}
                showAlert('error', errorMsg);
            }
        });
    });
}

/**
 * Show alert
 */
function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const $alert = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
             style="z-index: 9999; min-width: 300px;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append($alert);
    setTimeout(() => $alert.alert('close'), 5000);
}