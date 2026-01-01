/**
 * Serial Number Lookup - JavaScript
 * Place this AFTER jQuery loads
 */

// Global variable to store lookup results
let serialLookupResults = [];

// Handle serial number lookup
function lookupSerial(serialNumber) {
    if (!serialNumber) {
        return;
    }
    
    $.ajax({
        url: 'php/ajax/lookup-serial.php',
        method: 'POST',
        data: { serial_number: serialNumber },
        dataType: 'json',
        success: function(response) {
            console.log('Lookup response:', response);
            
            if (!response.success) {
                alert(response.message);
                return;
            }
            
            if (response.single_match) {
                // Only one match, auto-fill the form
                fillProductData(response.product);
            } else if (response.multiple_matches) {
                // Multiple matches, show selection modal
                showSerialMatchesModal(response.products, response.show_supplier, response.show_financial);
            }
        },
        error: function(xhr, status, error) {
            console.error('Lookup error:', xhr.responseText);
            alert('Failed to lookup serial number. Check console for details.');
        }
    });
}

// Show modal with multiple matches
function showSerialMatchesModal(products, showSupplier, showFinancial) {
    serialLookupResults = products;
    
    console.log('Showing modal with', products.length, 'products');
    console.log('Show supplier:', showSupplier, 'Show financial:', showFinancial);
    
    // Show/hide supplier columns
    if (showSupplier) {
        $('.supplier-column').show();
    } else {
        $('.supplier-column').hide();
    }
    
    // Show/hide financial columns
    if (showFinancial) {
        $('.financial-column').show();
    } else {
        $('.financial-column').hide();
    }
    
    // Populate table
    const $tbody = $('#serialMatchesBody');
    $tbody.empty();
    
    products.forEach(function(product, index) {
        let row = `
            <tr>
                <td>${product.sku}</td>
                <td>${product.product_name}</td>
                <td>${product.serial_number}</td>
        `;
        
        // Add supplier columns if authorized
        if (showSupplier) {
            row += `
                <td><small>${product.supplier_name || 'Unknown'}</small></td>
                <td><small>${product.document_type || ''} ${product.document_number || '-'}</small></td>
            `;
        }
        
        // Add financial columns if authorized
        if (showFinancial) {
            row += `
                <td>£${parseFloat(product.cost || 0).toFixed(2)}</td>
            `;
        }
        
        row += `
                <td>
                    <button class="btn btn-sm btn-primary" onclick="selectSerialMatch(${index})">
                        <i class="fas fa-check"></i> Select
                    </button>
                </td>
            </tr>
        `;
        
        $tbody.append(row);
    });
    
    // Show modal
    $('#serialLookupModal').modal('show');
}

// Select a product from multiple matches
function selectSerialMatch(index) {
    const product = serialLookupResults[index];
    console.log('Selected product:', product);
    fillProductData(product);
    $('#serialLookupModal').modal('hide');
}

// Fill form with product data
function fillProductData(product) {
    console.log('Filling form with:', product);
    
    // Try to find and fill common field IDs
    // Adjust these to match your actual form field IDs
    if ($('#productId').length) $('#productId').val(product.id);
    if ($('#product_id').length) $('#product_id').val(product.id);
    
    if ($('#sku').length) $('#sku').val(product.sku);
    if ($('#productSKU').length) $('#productSKU').val(product.sku);
    
    if ($('#productName').length) $('#productName').val(product.product_name);
    if ($('#product_name').length) $('#product_name').val(product.product_name);
    
    if ($('#serialNumber').length) $('#serialNumber').val(product.serial_number);
    if ($('#serial_number').length) $('#serial_number').val(product.serial_number);
    
    // Only fill cost if provided (permission-based)
    if (product.cost !== null && product.cost !== undefined) {
        if ($('#cost').length) $('#cost').val(product.cost);
        if ($('#costAtCreation').length) $('#costAtCreation').val(product.cost);
    }
    
    console.log('Form filled successfully');
}
