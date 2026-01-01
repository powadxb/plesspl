// assets/js/pc_quote/search.js

let currentComponentType = null;
let priceType = 'R'; // Default to Retail

$(document).ready(function() {
    initializeSearch();
    initializePriceTypeToggle();
});

function initializeSearch() {
    // Handle search button clicks for components
    $('.btn-search').click(function() {
    const componentSection = $(this).closest('.component-section');
    currentComponentType = componentSection.find('label').text();
    $('#searchModal').modal('show');
    // This will focus the search input after modal is fully shown
    $('#searchModal').on('shown.bs.modal', function() {
        $('#productSearchInput').focus();
    });
    $('#productSearchInput').val('').focus();
    $('#searchResultsBody').empty();
});

// Add click handler for the input fields
$('.component-input input[type="text"]').click(function() {
    $(this).closest('.component-section').find('.btn-search').click();
});

    // Handle product search input
    $('#productSearchInput').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            performSearch();
        }
    });

    // Handle search button click
    $('#searchProductsBtn').click(function() {
        performSearch();
    });

    // Handle manual entry buttons
    $('.btn-manual').click(function() {
        const componentSection = $(this).closest('.component-section');
        currentComponentType = componentSection.find('label').text();
        $('#manualProductName').val('');
        $('#manualProductPrice').val('');
        $('#manualEntryModal').modal('show');
    });

    // Handle manual entry save
    $('#saveManualEntry').click(function() {
        saveManualEntry();
    });
}

function initializePriceTypeToggle() {
    // Price type toggle buttons
    $('.price-type-toggle .btn').click(function() {
        if ($(this).hasClass('active')) return;
        
        $('.price-type-toggle .btn').removeClass('active btn-primary').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('active btn-primary');
        
        priceType = $(this).data('type');
        
        // Update all selected components with new price type
        updateAllPrices();
    });
}

function performSearch() {
    const searchTerm = $('#productSearchInput').val();
    if (searchTerm.length < 2) {
        alert('Please enter at least 2 characters to search');
        return;
    }

    $.ajax({
        url: 'ajax/search_products.php',
        method: 'POST',
        data: {
            search: searchTerm,
            priceType: priceType
        },
        success: function(response) {
            try {
                const results = JSON.parse(response);
                displaySearchResults(results);
            } catch (e) {
                console.error('Error parsing search results:', e);
                $('#searchResultsBody').html('<tr><td colspan="5" class="text-danger">Error processing results</td></tr>');
            }
        },
        error: function() {
            $('#searchResultsBody').html('<tr><td colspan="5" class="text-danger">Search failed</td></tr>');
        }
    });
}

function displaySearchResults(results) {
    const tbody = $('#searchResultsBody');
    tbody.empty();

    if (results.length === 0) {
        tbody.html('<tr><td colspan="5">No products found</td></tr>');
        return;
    }

    results.forEach(product => {
        const row = `
            <tr>
                <td>${product.sku}</td>
                <td>${product.name}</td>
                <td>${product.qty}</td>
                <td>£${parseFloat(product.price_inc_vat).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-success select-product" 
                            data-sku="${product.sku}"
                            data-name="${product.name}"
                            data-base_price="${product.base_price}"
                            data-price_inc_vat="${product.price_inc_vat}"
                            data-cost="${product.cost}">
                        Select
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function updateAllPrices() {
    // Collect all selected products that aren't manual entries
    $('.component-section').each(function() {
        const input = $(this).find('input[type="text"]');
        if (!input.val() || input.data('isManual')) return;

        const sku = input.data('sku');
        if (!sku) return;

        // Re-fetch prices for this SKU
        $.ajax({
            url: 'ajax/search_products.php',
            method: 'POST',
            data: {
                search: sku,
                priceType: priceType,
                exactSku: true
            },
            success: function(response) {
                try {
                    const results = JSON.parse(response);
                    if (results.length === 1) {
                        const product = results[0];
                        input.data('base_price', product.base_price);
                        input.data('price_inc_vat', product.price_inc_vat);
                        
                        // Update displayed price
                        const details = input.closest('.component-section').find('.component-details');
                        details.html(`SKU: ${product.sku} - £${parseFloat(product.price_inc_vat).toFixed(2)} inc VAT`);
                    }
                } catch (e) {
                    console.error('Error updating prices:', e);
                }
            }
        });
    });

    // Update totals after all prices are updated
    setTimeout(() => {
        if (typeof window.quoteCalculations !== 'undefined') {
            window.quoteCalculations.updateTotals();
        }
    }, 500);
}

// Handle product selection
$(document).on('click', '.select-product', function() {
    const productData = {
        sku: $(this).data('sku'),
        name: $(this).data('name'),
        base_price: parseFloat($(this).data('base_price')),
        price_inc_vat: parseFloat($(this).data('price_inc_vat')),
        cost: parseFloat($(this).data('cost')),
        isManual: false
    };

    selectProduct(currentComponentType, productData);
    $('#searchModal').modal('hide');
});

function selectProduct(componentType, productData) {
    const componentSection = $(`.component-section:contains('${componentType}')`);
    const input = componentSection.find('input[type="text"]');
    const details = componentSection.find('.component-details');

    input.val(productData.name);
    input.data('sku', productData.sku);
    input.data('base_price', productData.base_price);
    input.data('price_inc_vat', productData.price_inc_vat);
    input.data('cost', productData.cost);
    input.data('isManual', productData.isManual);

    details.html(`SKU: ${productData.sku} - £${productData.price_inc_vat.toFixed(2)} inc VAT`);
    
    if (typeof window.quoteCalculations !== 'undefined') {
        window.quoteCalculations.updateTotals();
    }
}

function saveManualEntry() {
    const name = $('#manualProductName').val();
    const priceIncVat = parseFloat($('#manualProductPrice').val());

    if (!name || isNaN(priceIncVat)) {
        alert('Please enter both name and price');
        return;
    }

    const productData = {
        name: name,
        base_price: priceIncVat / 1.2, // Remove VAT for base price
        price_inc_vat: priceIncVat,
        cost: 0,
        isManual: true
    };

    selectProduct(currentComponentType, productData);
    $('#manualEntryModal').modal('hide');
}