// RMA System JavaScript - WITH SMART SERIAL LOOKUP
$(document).ready(function() {
    const userLocation = $('#userLocation').val();
    const isAuthorized = $('#isAuthorized').val() === '1';
    const canViewSupplier = $('#canViewSupplier').val() === '1';
    const canViewFinancial = $('#canViewFinancial').val() === '1';
    
    // Load RMAs on page load
    loadRMAs();

    // Quick Entry Modal
    $('#quickEntryBtn').click(function() {
        resetQuickEntryForm();
        $('#quickEntryModal').modal('show');
        setTimeout(() => $('#serialNumber').focus(), 500);
    });

    // ============================================================================
    // SMART SERIAL NUMBER LOOKUP SECTION
    // ============================================================================

    // Serial number lookup - Enter key triggers lookup
    $('#serialNumber').keypress(function(e) {
        if(e.which === 13) {
            e.preventDefault();
            $('#lookupSerialBtn').click();
        }
    });

    // Lookup button click - perform normal lookup
    $('#lookupSerialBtn').click(function() {
        performSerialLookup(false); // false = normal lookup (exact first)
    });

    // Main serial lookup function
    function performSerialLookup(showAll) {
        const serialNumber = $('#serialNumber').val().trim();
        
        if(!serialNumber) {
            showAlert('Please enter a serial number', 'warning');
            return;
        }
        
        if(serialNumber.length < 3) {
            showAlert('Serial number must be at least 3 characters', 'warning');
            return;
        }

        $('#lookupSerialBtn').html('<i class="fas fa-spinner fa-spin"></i> Looking up...').prop('disabled', true);

        $.ajax({
            url: 'php/ajax/lookup-serial.php',
            method: 'POST',
            data: { 
                serial_number: serialNumber,
                show_all: showAll ? '1' : '0'
            },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    if(response.multiple) {
                        // Multiple matches - show selection
                        showMultipleMatches(response.matches, response.message);
                    } else {
                        // Single match - populate fields
                        populateProductFields(response.data);
                        
                        // If there are other matches, show option to see them
                        if(response.other_matches && response.other_matches > 0) {
                            showSeeMoreOption(response.other_matches);
                        }
                    }
                } else {
                    // Serial not found
                    $('#serialNotFoundSection').show();
                    $('#productInfoSection').hide();
                    $('#multipleMatchesSection').hide();
                }
            },
            error: function() {
                showAlert('Error looking up serial number', 'danger');
            },
            complete: function() {
                $('#lookupSerialBtn').html('<i class="fas fa-search"></i> Lookup').prop('disabled', false);
            }
        });
    }

    // Show "See X more" button when exact match found but others exist
    function showSeeMoreOption(count) {
        const html = `
            <div class="alert alert-info mt-2" id="seeMoreAlert">
                <i class="fas fa-info-circle"></i> 
                ${count} other serial(s) contain this number.
                <button type="button" class="btn btn-sm btn-primary ml-2" id="seeMoreBtn">
                    <i class="fas fa-list"></i> Show All ${count + 1} Matches
                </button>
            </div>
        `;
        
        $('#productInfoSection').append(html);
        
        // Handle see more button click
        $('#seeMoreBtn').click(function() {
            $('#seeMoreAlert').remove();
            performSerialLookup(true); // true = show all matches
        });
    }

    // Show multiple matches (with optional message)
    function showMultipleMatches(matches, message) {
        let html = '<div class="alert alert-info">';
        html += '<i class="fas fa-info-circle"></i> ';
        html += message || ('Found ' + matches.length + ' matching serial numbers. Please select one:');
        html += '</div>';
        
        html += '<div class="list-group" style="max-height: 500px; overflow-y: auto;">';
        
        matches.forEach(function(match, index) {
            html += '<a href="#" class="list-group-item list-group-item-action select-serial-match" data-index="' + index + '">';
            html += '<div class="d-flex w-100 justify-content-between">';
            html += '<h6 class="mb-1"><strong>Serial:</strong> ' + match.serial_num + '</h6>';
            html += '<small>SKU: ' + match.sku + '</small>';
            html += '</div>';
            html += '<p class="mb-1">' + match.product_name + '</p>';
            if(match.supplier_name) {
                html += '<small><strong>Supplier:</strong> ' + match.supplier_name;
                if(match.document_number && match.document_number !== '-') {
                    html += ' | <strong>Doc:</strong> ' + match.document_number;
                }
                html += '</small>';
            } else {
                html += '<small class="text-muted">Supplier unknown</small>';
            }
            html += '</a>';
        });
        
        html += '</div>';
        
        $('#multipleMatchesContent').html(html);
        $('#multipleMatchesSection').show();
        $('#serialNotFoundSection').hide();
        $('#productInfoSection').hide();
        
        // Store matches in a global variable for selection
        window.serialMatches = matches;
    }

    // Handle serial match selection
    $(document).on('click', '.select-serial-match', function(e) {
        e.preventDefault();
        const index = $(this).data('index');
        const selectedMatch = window.serialMatches[index];
        
        // Update the serial number field with the exact match
        $('#serialNumber').val(selectedMatch.serial_num);
        
        // Populate fields with selected match
        populateProductFields(selectedMatch);
        
        // Hide multiple matches section
        $('#multipleMatchesSection').hide();
    });

    // Function to populate product fields (extracted for reuse)
    function populateProductFields(data) {
        $('#hiddenSku').val(data.sku);
        $('#hiddenProductName').val(data.product_name);
        $('#hiddenEan').val(data.ean || '');
        $('#hiddenSupplier').val(data.supplier_name || '');
        $('#hiddenDocumentId').val(data.document_id || '');
        $('#hiddenDocumentNumber').val(data.document_number || '');
        $('#hiddenDocumentDate').val(data.document_date || '');
        $('#hiddenCost').val(data.cost || '');
        $('#hiddenNeedsReview').val(data.needs_review ? '1' : '0');

        // Display product info
        $('#displaySku').text(data.sku);
        $('#displayProductName').text(data.product_name);
        $('#displayEan').text(data.ean || 'N/A');
        $('#displaySupplier').text(data.supplier_name || 'Unknown (will need review)');

        $('#serialNotFoundSection').hide();
        $('#multipleMatchesSection').hide();
        $('#productInfoSection').show();
    }

    // ============================================================================
    // END SMART SERIAL NUMBER LOOKUP SECTION
    // ============================================================================

    // Manual SKU validation
    $('#validateSkuBtn').click(function() {
        const sku = $('#manualSku').val().trim();
        const ean = $('#manualEan').val().trim();
        const serialNumber = $('#serialNumber').val().trim();

        if(!sku) {
            showAlert('Please enter a SKU', 'warning');
            return;
        }

        $(this).html('<i class="fas fa-spinner fa-spin"></i> Validating...').prop('disabled', true);

        $.ajax({
            url: 'php/ajax/validate-sku.php',
            method: 'POST',
            data: { 
                sku: sku,
                serial_number: serialNumber 
            },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    // SKU valid - populate fields
                    $('#hiddenSku').val(response.data.sku);
                    $('#hiddenProductName').val(response.data.product_name);
                    $('#hiddenEan').val(ean || response.data.ean || '');
                    $('#hiddenSupplier').val('');
                    $('#hiddenDocumentId').val('');
                    $('#hiddenDocumentNumber').val('');
                    $('#hiddenDocumentDate').val('');
                    $('#hiddenCost').val(response.data.cost || '');
                    $('#hiddenNeedsReview').val('1'); // Manual entry needs review

                    // Display product info
                    $('#displaySku').text(response.data.sku);
                    $('#displayProductName').text(response.data.product_name);
                    $('#displayEan').text(ean || response.data.ean || 'N/A');
                    $('#displaySupplier').text('Unknown (will need review)');

                    $('#serialNotFoundSection').hide();
                    $('#productInfoSection').show();
                } else {
                    showAlert(response.message || 'SKU not found in database', 'danger');
                }
            },
            error: function() {
                showAlert('Error validating SKU', 'danger');
            },
            complete: function() {
                $('#validateSkuBtn').html('<i class="fas fa-check"></i> Validate SKU').prop('disabled', false);
            }
        });
    });

    // Continue to fault selection
    $('#continueToFaultBtn').click(function() {
        $('#step1').hide();
        $('#step2').show();
        $('#faultType').focus();
    });

    $('#backToStep1Btn').click(function() {
        $('#step2').hide();
        $('#step1').show();
    });

    // Continue to barcode/tracking
    $('#continueToIdBtn').click(function() {
        if(!$('#faultType').val()) {
            showAlert('Please select a fault type', 'warning');
            return;
        }

        $('#step2').hide();
        $('#step3').show();

        // If tracking number method selected, generate it
        if($('input[name="idMethod"]:checked').val() === 'tracking') {
            generateTrackingNumber();
        }

        // Focus on barcode input if barcode method selected
        if($('input[name="idMethod"]:checked').val() === 'barcode') {
            $('#barcodeInput').focus();
        }
    });

    $('#backToStep2Btn').click(function() {
        $('#step3').hide();
        $('#step2').show();
    });

    // ID Method toggle
    $('input[name="idMethod"]').change(function() {
        if($(this).val() === 'barcode') {
            $('#barcodeSection').show();
            $('#trackingSection').hide();
            $('#barcodeInput').focus();
        } else {
            $('#barcodeSection').hide();
            $('#trackingSection').show();
            generateTrackingNumber();
        }
    });

    // Generate tracking number
    function generateTrackingNumber() {
        $.ajax({
            url: 'php/ajax/generate-tracking.php',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $('#generatedTracking').val(response.tracking_number);
                } else {
                    showAlert('Error generating tracking number', 'danger');
                }
            }
        });
    }

    // Save RMA
    $('#quickEntryForm').submit(function(e) {
        e.preventDefault();

        const idMethod = $('input[name="idMethod"]:checked').val();
        const barcode = (idMethod === 'barcode') ? $('#barcodeInput').val().trim() : '';
        const trackingNumber = (idMethod === 'tracking') ? $('#generatedTracking').val() : '';

        if(idMethod === 'barcode' && !barcode) {
            showAlert('Please scan a barcode', 'warning');
            return;
        }

        const formData = {
            serial_number: $('#serialNumber').val().trim(),
            sku: $('#hiddenSku').val(),
            product_name: $('#hiddenProductName').val(),
            ean: $('#hiddenEan').val(),
            supplier_name: $('#hiddenSupplier').val(),
            document_id: $('#hiddenDocumentId').val(),
            document_number: $('#hiddenDocumentNumber').val(),
            document_date: $('#hiddenDocumentDate').val(),
            cost: $('#hiddenCost').val(),
            needs_review: $('#hiddenNeedsReview').val(),
            fault_type_id: $('#faultType').val(),
            fault_description: $('#faultDescription').val().trim(),
            barcode: barcode,
            tracking_number: trackingNumber
        };

        $('#saveRmaBtn').html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);

        $.ajax({
            url: 'php/ajax/create-rma.php',
            method: 'POST',
            data: $.param(formData),
            contentType: 'application/x-www-form-urlencoded',
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $('#quickEntryModal').modal('hide');
                    loadRMAs(); // Reload table
                } else {
                    showAlert(response.message || 'Error creating RMA', 'danger');
                }
            },
            error: function() {
                showAlert('Error creating RMA', 'danger');
            },
            complete: function() {
                $('#saveRmaBtn').html('<i class="fas fa-save"></i> Save RMA').prop('disabled', false);
            }
        });
    });

    // Reset quick entry form
    function resetQuickEntryForm() {
        $('#quickEntryForm')[0].reset();
        $('#step1').show();
        $('#step2, #step3').hide();
        $('#serialNotFoundSection, #productInfoSection, #multipleMatchesSection').hide();
        $('#seeMoreAlert').remove(); // Clear "see more" alert if present
        $('input[name="idMethod"][value="barcode"]').prop('checked', true).trigger('change');
        $('#hiddenSku, #hiddenProductName, #hiddenEan, #hiddenSupplier, #hiddenDocumentId, #hiddenDocumentNumber, #hiddenDocumentDate, #hiddenCost, #hiddenNeedsReview').val('');
    }

    // Load RMAs table
    function loadRMAs() {
        const searchQuery = $('#searchQuery').val().trim();
        const filterLocation = $('#filterLocation').val();
        const filterStatus = $('#filterStatus').val();
        const limit = $('#limit').val();
        const offset = $('#offset').val();
        
        // Calculate column count for error messages
        let colCount = 11;
        if(canViewSupplier) colCount++;
        if(canViewFinancial) colCount++;

        $.ajax({
            url: 'php/ajax/get-rma-list.php',
            method: 'POST',
            data: {
                search: searchQuery,
                location: filterLocation,
                status: filterStatus,
                limit: limit,
                offset: offset
            },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    renderRMATable(response.data);
                } else {
                    $('#rmaRecords').html('<tr><td colspan="' + colCount + '" class="text-center text-danger">Error loading RMAs</td></tr>');
                }
            },
            error: function() {
                $('#rmaRecords').html('<tr><td colspan="' + colCount + '" class="text-center text-danger">Error loading RMAs</td></tr>');
            }
        });
    }

    // Render RMA table
    function renderRMATable(rmas) {
        // Calculate column count
        let colCount = 11; // Base columns
        if(canViewSupplier) colCount++;
        if(canViewFinancial) colCount++;
        
        if(rmas.length === 0) {
            $('#rmaRecords').html('<tr><td colspan="' + colCount + '" class="text-center">No RMAs found</td></tr>');
            return;
        }

        let html = '';
        rmas.forEach(function(rma) {
            const statusBadge = getStatusBadge(rma.status);
            const locationBadge = (rma.location === 'cs') ? '<span class="badge badge-primary">CS</span>' : '<span class="badge badge-info">AS</span>';
            
            html += '<tr>';
            html += '<td><button class="btn btn-sm btn-info viewRmaBtn" data-id="' + rma.id + '"><i class="fas fa-eye"></i></button></td>';
            html += '<td>' + (rma.barcode || '<em>N/A</em>') + '</td>';
            html += '<td>' + (rma.tracking_number || '<em>N/A</em>') + '</td>';
            html += '<td>' + (rma.serial_number || '<em>N/A</em>') + '</td>';
            html += '<td>' + rma.sku + '</td>';
            html += '<td>' + rma.product_name + '</td>';
            html += '<td>' + rma.fault_name + '</td>';
            
            if(canViewSupplier) {
                html += '<td>' + (rma.supplier_name || '<em class="text-muted">Unknown</em>') + 
                        (rma.needs_review == 1 ? ' <i class="fas fa-exclamation-triangle text-warning" title="Needs Review"></i>' : '') + '</td>';
            }
            
            if(canViewFinancial) {
                html += '<td>£' + parseFloat(rma.cost_at_creation || 0).toFixed(2) + '</td>';
            }
            
            html += '<td>' + statusBadge + '</td>';
            html += '<td>' + locationBadge + '</td>';
            html += '<td>' + rma.date_discovered + '</td>';
            html += '<td>' + rma.days_open + ' days</td>';
            html += '</tr>';
        });

        $('#rmaRecords').html(html);
    }

    // Get status badge HTML
    function getStatusBadge(status) {
        const badges = {
            'unprocessed': '<span class="badge badge-danger">Unprocessed</span>',
            'rma_number_issued': '<span class="badge badge-warning">RMA Issued</span>',
            'applied_for': '<span class="badge badge-primary">Applied For</span>',
            'sent': '<span class="badge badge-info">Sent</span>',
            'credited': '<span class="badge badge-success">Credited</span>',
            'exchanged': '<span class="badge badge-success">Exchanged</span>',
            'rejected': '<span class="badge badge-secondary">Rejected</span>'
        };
        return badges[status] || '<span class="badge badge-secondary">' + status + '</span>';
    }

    // View RMA details
    $(document).on('click', '.viewRmaBtn', function() {
        const rmaId = $(this).data('id');
        loadRMADetails(rmaId);
    });

    function loadRMADetails(rmaId) {
        $.ajax({
            url: 'php/ajax/get-rma-details.php',
            method: 'POST',
            data: { rma_id: rmaId },
            success: function(response) {
                $('#rmaDetailsContent').html(response);
                if(isAuthorized) {
                    $('#updateRmaId').val(rmaId);
                }
                $('#viewRmaModal').modal('show');
            },
            error: function() {
                showAlert('Error loading RMA details', 'danger');
            }
        });
    }

    // Status change triggers
    $('#newStatus').change(function() {
        const status = $(this).val();
        
        // Show/hide relevant fields based on status
        if(status === 'credited') {
            $('#creditedAmountGroup').show();
        } else {
            $('#creditedAmountGroup').hide();
        }

        if(status === 'sent') {
            $('#shippingTrackingGroup').show();
            $('#sendDateGroup').show();
            $('#dateSent').val(new Date().toISOString().split('T')[0]); // Set to today
        } else {
            $('#shippingTrackingGroup').hide();
            $('#sendDateGroup').hide();
        }
    });

    // Update status form submit
    $('#updateStatusForm').submit(function(e) {
        e.preventDefault();

        const formData = {
            rma_id: $('#updateRmaId').val(),
            status: $('#newStatus').val(),
            credited_amount: $('#creditedAmount').val(),
            shipping_tracking: $('#shippingTracking').val(),
            date_sent: $('#dateSent').val()
        };

        $.ajax({
            url: 'php/ajax/update-status.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    showAlert('Status updated successfully', 'success');
                    $('#viewRmaModal').modal('hide');
                    loadRMAs();
                } else {
                    showAlert(response.message || 'Error updating status', 'danger');
                }
            },
            error: function() {
                showAlert('Error updating status', 'danger');
            }
        });
    });

    // Search functionality
    $('#searchQuery').on('input', debounce(function() {
        $('#offset').val(0);
        loadRMAs();
    }, 500));

    // Filter changes
    $('.filterRecords').change(function() {
        $('#offset').val(0);
        loadRMAs();
    });

    // Review items button
    $('#reviewItemsBtn').click(function() {
        loadReviewItems();
    });

    function loadReviewItems() {
        $.ajax({
            url: 'php/ajax/get-review-items.php',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    renderReviewItems(response.data);
                    $('#reviewItemsModal').modal('show');
                } else {
                    showAlert('Error loading review items', 'danger');
                }
            }
        });
    }

    function renderReviewItems(items) {
        if(items.length === 0) {
            $('#reviewItemsList').html('<tr><td colspan="7" class="text-center">No items need review</td></tr>');
            return;
        }

        let html = '';
        items.forEach(function(item) {
            const locationBadge = (item.location === 'cs') ? 'CS' : 'AS';
            html += '<tr>';
            html += '<td><button class="btn btn-sm btn-primary assignSupplierBtn" data-id="' + item.id + '" data-sku="' + item.sku + '" data-product="' + item.product_name + '">Assign</button></td>';
            html += '<td>' + (item.barcode || item.tracking_number) + '</td>';
            html += '<td>' + (item.serial_number || '<em>N/A</em>') + '</td>';
            html += '<td>' + item.sku + '</td>';
            html += '<td>' + item.product_name + '</td>';
            html += '<td>' + locationBadge + '</td>';
            html += '<td>' + item.date_discovered + '</td>';
            html += '</tr>';
        });

        $('#reviewItemsList').html(html);
    }

    // Assign supplier button
    $(document).on('click', '.assignSupplierBtn', function() {
        const rmaId = $(this).data('id');
        const sku = $(this).data('sku');
        const productName = $(this).data('product');

        $('#assignRmaId').val(rmaId);
        $('#assignProductInfo').html('<strong>SKU:</strong> ' + sku + '<br><strong>Product:</strong> ' + productName);
        $('#assignSupplierModal').modal('show');
    });

    // Assign supplier form submit
    $('#assignSupplierForm').submit(function(e) {
        e.preventDefault();

        const formData = {
            rma_id: $('#assignRmaId').val(),
            supplier_name: $('#assignSupplierName').val().trim(),
            document_number: $('#assignDocumentNumber').val().trim(),
            document_date: $('#assignDocumentDate').val(),
            mark_resolved: $('#markAsResolved').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: 'php/ajax/assign-supplier.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    showAlert('Supplier assigned successfully', 'success');
                    $('#assignSupplierModal').modal('hide');
                    loadReviewItems(); // Reload review list
                    loadRMAs(); // Reload main table
                    location.reload(); // Reload page to update counts
                } else {
                    showAlert(response.message || 'Error assigning supplier', 'danger');
                }
            },
            error: function() {
                showAlert('Error assigning supplier', 'danger');
            }
        });
    });

    // Helper functions
    function showAlert(message, type) {
        // You can implement your preferred alert system here
        alert(message);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Filter by status (from dashboard cards)
    window.filterByStatus = function(status) {
        $('#filterStatus').val(status);
        $('#offset').val(0);
        loadRMAs();
    };

    // Filter by needs review (from dashboard card)
    window.filterByNeedsReview = function() {
        $('#reviewItemsBtn').click();
    };
});