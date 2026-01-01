<!-- View RMA Modal -->
<div class="modal fade" id="viewRmaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> RMA Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                <div id="rmaDetailsContent">
                    <!-- Content loaded via AJAX -->
                </div>

                <!-- Status Update Section (Only if user has RMA-Manage permission) -->
                <?php 
                // Check for manage permission - this is what controls status updates!
                $show_status_update = false;
                if (isset($can_manage_rma)) {
                    $show_status_update = $can_manage_rma; // Check RMA-Manage permission
                } elseif (isset($is_authorized)) {
                    // Fallback for old system (admin/useradmin)
                    $show_status_update = $is_authorized;
                }
                
                if($show_status_update): 
                ?>
                <div id="statusUpdateSection" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #ddd;">
                    <h6 class="font-weight-bold">Update Status</h6>
                    <form id="updateStatusForm" onsubmit="return false;">
                        <input type="hidden" id="updateRmaId">
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="newStatus">New Status</label>
                                <select class="form-control" id="newStatus" onchange="handleStatusChange()">
                                    <option value="">-- Select Status --</option>
                                    <option value="unprocessed">Unprocessed</option>
                                    <option value="rma_number_issued">RMA Number Issued</option>
                                    <option value="applied_for">Applied For</option>
                                    <option value="sent">Sent</option>
                                    <option value="credited">Credited</option>
                                    <option value="exchanged">Exchanged</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            
                            <div class="form-group col-md-6" id="creditedAmountGroup" style="display:none;">
                                <label for="creditedAmount">Credited Amount (£)</label>
                                <input type="number" class="form-control" id="creditedAmount" step="0.01" placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-group" id="trackingNumberGroup" style="display:none;">
                            <label for="trackingNumber">Tracking Number</label>
                            <input type="text" class="form-control" id="trackingNumber" placeholder="Enter tracking number">
                        </div>

                        <div class="form-group" id="dateResolvedGroup" style="display:none;">
                            <label for="dateResolved">Date Resolved</label>
                            <input type="date" class="form-control" id="dateResolved">
                        </div>

                        <button type="button" class="btn btn-primary" onclick="updateRMAStatus()">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <!-- No status update section shown - user doesn't have RMA-Manage permission -->
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Handle status change
function handleStatusChange() {
    const status = $('#newStatus').val();
    
    // Show credited amount field for credited status
    $('#creditedAmountGroup').toggle(status === 'credited');
    
    // Show tracking for sent/credited/exchanged/rejected
    $('#trackingNumberGroup').toggle(['sent', 'credited', 'exchanged', 'rejected'].includes(status));
    
    // Show date resolved for credited/exchanged/rejected
    $('#dateResolvedGroup').toggle(['credited', 'exchanged', 'rejected'].includes(status));
}

// Update RMA status
function updateRMAStatus() {
    console.log('updateRMAStatus called'); // Debug
    
    const rmaId = $('#updateRmaId').val();
    const newStatus = $('#newStatus').val();
    
    console.log('RMA ID:', rmaId, 'Status:', newStatus); // Debug
    
    if (!newStatus) {
        alert('Please select a status');
        return;
    }
    
    const data = {
        rma_id: rmaId,
        status: newStatus,
        credited_amount: $('#creditedAmount').val() || null,
        tracking_number: $('#trackingNumber').val() || null,
        date_resolved: $('#dateResolved').val() || null
    };
    
    console.log('Sending data:', data); // Debug
    
    // Show loading state
    const $btn = $('button:contains("Update Status")');
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');
    
    $.ajax({
        url: 'php/ajax/update-status.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        dataType: 'json',
        success: function(response) {
            console.log('Response:', response); // Debug
            
            // Reset button
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Status');
            
            if (response.success) {
                // Show success message
                if (typeof showAlert === 'function') {
                    showAlert('success', response.message);
                } else {
                    alert(response.message);
                }
                
                // Close the RMA modal
                $('#viewRmaModal').modal('hide');
                
                // Check if we're in batch management (viewBatch function exists)
                if (typeof viewBatch === 'function') {
                    // If batch was completed, reload batch list
                    if (response.batch_completed && typeof loadBatches === 'function') {
                        loadBatches();
                    }
                    
                    // Reload batch details after short delay
                    setTimeout(function() {
                        const batchId = $('#viewBatchModal').data('batch-id');
                        if (batchId) {
                            viewBatch(batchId);
                        }
                    }, 500);
                } else if (typeof loadRMAs === 'function') {
                    // We're in index.php, reload the main RMA list
                    loadRMAs();
                }
            } else {
                if (typeof showAlert === 'function') {
                    showAlert('error', response.message);
                } else {
                    alert('Error: ' + response.message);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText); // Debug
            
            // Reset button
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Status');
            
            if (typeof showAlert === 'function') {
                showAlert('error', 'Failed to update status: ' + error);
            } else {
                alert('Failed to update status: ' + error);
            }
        }
    });
}

// Fix modal scrolling issue when RMA modal closes (for batch management)
$(document).ready(function() {
    $('#viewRmaModal').on('hidden.bs.modal', function() {
        // Re-enable scrolling on batch modal if it exists
        if ($('#viewBatchModal').length && $('#viewBatchModal').hasClass('show')) {
            $('body').addClass('modal-open');
            // Remove extra backdrop that might be left behind
            if ($('.modal-backdrop').length > 1) {
                $('.modal-backdrop').first().remove();
            }
        }
    });
    
    // Fix modal stacking when RMA modal opens (for batch management)
    $('#viewRmaModal').on('shown.bs.modal', function() {
        // Ensure proper z-index for stacked modals
        const backdropZIndex = parseInt($('.modal-backdrop').last().css('z-index'));
        if (backdropZIndex) {
            $(this).css('z-index', backdropZIndex + 10);
        }
    });
});
</script>