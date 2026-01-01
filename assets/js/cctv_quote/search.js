// assets/js/cctv_quote/search.js

let priceType = 'R'; // Default to Retail

$(document).ready(function() {
    initializeSearch();
    initializePriceTypeToggle();
});

function initializeSearch() {
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
        Swal.fire('Error', 'Please enter at least 2 characters to search', 'error');
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
        const productJson = JSON.stringify({
            sku: product.sku,
            name: product.name,
            base_price: product.base_price,
            price_inc_vat: product.price_inc_vat,
            price: product.base_price,
            retail_price: product.retail_price || product.base_price,
            trade_price: product.trade_price || product.base_price,
            cost: product.cost
        });
        
        const row = `
            <tr>
                <td>${product.sku}</td>
                <td>${product.name}</td>
                <td>${product.qty || 0}</td>
                <td>£${parseFloat(product.price_inc_vat).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-success select-product-btn" 
                            data-product='${productJson.replace(/'/g, "&apos;")}'>
                        Select
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function updateAllPrices() {
    // Update single-item components
    $('.component-section:not([data-allow-multiple]) .component-display').each(function() {
        const input = $(this);
        if (!input.val() || input.data('isManual') || input.data('priceEdited')) return;

        const sku = input.data('sku');
        if (!sku) return;

        updateProductPrice(sku, input);
    });

    // Update multi-item components
    $('.multi-item').each(function() {
        const itemData = $(this).data('item');
        if (!itemData || itemData.isManual || itemData.priceEdited) return;

        const sku = itemData.sku;
        if (!sku) return;

        updateMultiItemPrice(sku, $(this));
    });

    // Update totals after all prices are updated
    setTimeout(() => {
        if (typeof window.cctvQuoteCalculations !== 'undefined') {
            window.cctvQuoteCalculations.updateTotals();
        }
    }, 500);
}

function updateProductPrice(sku, $input) {
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
                    $input.data('base_price', product.base_price);
                    $input.data('price_inc_vat', product.price_inc_vat);
                    
                    // Update displayed price
                    const details = $input.closest('.component-section').find('.component-details');
                    updatePriceDisplay(details, product.price_inc_vat, product.cost, false);
                }
            } catch (e) {
                console.error('Error updating price:', e);
            }
        }
    });
}

function updateMultiItemPrice(sku, $multiItem) {
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
                    const itemData = $multiItem.data('item');
                    itemData.basePrice = parseFloat(product.base_price);
                    itemData.priceIncVat = parseFloat(product.price_inc_vat);
                    $multiItem.data('item', itemData);
                    
                    // Update displayed price
                    const details = $multiItem.find('.multi-item-details');
                    updatePriceDisplay(details, product.price_inc_vat, product.cost, false);
                }
            } catch (e) {
                console.error('Error updating multi-item price:', e);
            }
        }
    });
}

function updatePriceDisplay($container, priceIncVat, cost, isManual) {
    const priceDisplay = `
        <div class="price-display">
            <span class="price-editable" title="Click to edit price">£${parseFloat(priceIncVat).toFixed(2)}</span>
            ${!isManual ? `<span class="cost-info cost-sensitive">(Cost: £${parseFloat(cost).toFixed(2)})</span>` : ''}
        </div>
    `;
    
    $container.html(priceDisplay);
    $container.data('cost', cost);
    $container.data('current-price', priceIncVat);
    $container.data('original-price', priceIncVat);
}

// Export for use in ui-handlers
window.cctvSearch = {
    updatePriceDisplay
};

console.log('✓ CCTV Search loaded');