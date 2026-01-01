// assets/js/pc_quote/ui-handlers.js

$(document).ready(function() {
    initializeUIHandlers();
});

function initializeUIHandlers() {
    initializeModals();
    initializeAdditionalItems();
    initializeActionButtons();
    initializeCustomerSearch();
}

function initializeModals() {
    // Reset search modal when closed
    $('#searchModal').on('hidden.bs.modal', function() {
        $('#productSearchInput').val('');
        $('#searchResultsBody').empty();
    });

    // Reset manual entry modal when closed
    $('#manualEntryModal').on('hidden.bs.modal', function() {
        $('#manualProductName').val('');
        $('#manualProductPrice').val('');
    });

    // Handle Enter key in manual entry modal
    $('#manualProductName, #manualProductPrice').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $('#saveManualEntry').click();
        }
    });
}

function initializeAdditionalItems() {
    // Handle adding additional items
    $('#addItemBtn').click(function() {
        $('#manualProductName').val('');
        $('#manualProductPrice').val('');
        $('#manualEntryModal').modal('show');
    });

    // Handle removing additional items
    $(document).on('click', '.remove-additional-item', function() {
        $(this).closest('.additional-item').remove();
        window.quoteCalculations.updateTotals();
    });
}

function initializeActionButtons() {
    // Save Quote Button
    $('#saveQuoteBtn').click(function() {
        if ($(this).prop('disabled')) return;

        const validation = window.quoteCalculations.validateQuote();
        if (!validation.isValid) {
            showError('Cannot Save Quote', validation.errors);
            return;
        }

        saveQuote();
    });

    // Print Quote Button
    $('#printQuoteBtn').click(function() {
        if ($(this).prop('disabled')) return;

        const validation = window.quoteCalculations.validateQuote();
        if (!validation.isValid) {
            showError('Cannot Print Quote', validation.errors);
            return;
        }

        printQuote();
    });

    // Create Order Button
    $('#createOrderBtn').click(function() {
        if ($(this).prop('disabled')) return;

        const validation = window.quoteCalculations.validateQuote();
        if (!validation.isValid) {
            showError('Cannot Create Order', validation.errors);
            return;
        }

        createOrder();
    });

    // New Customer Button
    $('#newCustomerBtn').click(function() {
        showNewCustomerForm();
    });
}

function initializeCustomerSearch() {
    // Customer search input handler
    $('#customerSearch').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            searchCustomers();
        }
    });

    // Customer search button handler
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
                console.error('Error parsing customer results:', e);
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
        // Escape the data properly
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

    // Handle customer selection
    $('.select-customer').click(function() {
        const customerData = $(this).data('customer-info');
        const customer = JSON.parse(decodeURIComponent(customerData));
        selectCustomer(customer);
        Swal.close();
    });
}

function selectCustomer(customer) {
    // Update the customer search input
    $('#customerSearch').val(customer.name);

    // Display customer details
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

    // Store customer data for quote
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
    console.log('Quote Data:', quoteData); // Debug log
    
    $.ajax({
        url: 'ajax/save_quote.php',
        method: 'POST',
        data: { quote: JSON.stringify(quoteData) }, // Make sure we're stringifying the data
        dataType: 'json', // Expect JSON response
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
            console.error('Save Error:', xhr.responseText); // Debug log
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
            // Print after images are loaded
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