// assets/js/cctv_quote/calculations.js

let quoteTotals = {
    total: 0,    // Total including VAT
    profit: 0    // Profit excluding VAT
};

$(document).ready(function() {
    initializeCalculations();
    updateTotals(); // Initial calculation
});

function initializeCalculations() {
    // Listen for service charge changes
    $('#installationCharge, #configCharge, #testingCharge').on('change keyup', function() {
        updateTotals();
    });

    // Listen for quantity changes
    $(document).on('change', '.item-qty, .multi-item-qty', function() {
        updateTotals();
    });

    // Listen for price edits
    $(document).on('blur', '.price-input', function() {
        validateAndSavePrice($(this));
    });

    $(document).on('keypress', '.price-input', function(e) {
        if (e.which === 13) {
            $(this).blur();
        }
    });
}

function updateTotals() {
    console.log('updateTotals called');
    resetTotals();
    calculateComponentTotals();
    displayTotals();
}

function resetTotals() {
    quoteTotals = {
        total: 0,
        profit: 0
    };
}

function calculateComponentTotals() {
    let totalExVat = 0;
    let totalCost = 0;

    console.log('Calculating components...');

    // Calculate single-item components
    $('.component-section:not([data-allow-multiple])').each(function() {
        if ($(this).closest('#additionalItemsList').length > 0) {
            return;
        }

        const input = $(this).find('.component-display');
        const sectionName = $(this).find('label').first().text();
        
        if (!input.val()) {
            return; // Skip empty components
        }

        let priceIncVat = parseFloat(input.data('price_inc_vat')) || 0;
        let basePrice = parseFloat(input.data('base_price')) || 0;
        let cost = parseFloat(input.data('cost')) || 0;
        let isManual = input.data('isManual') || false;
        let qty = 1;

        // Handle quantity for items with qty selector
        const qtySelect = $(this).find('.item-qty');
        if (qtySelect.length > 0) {
            qty = parseInt(qtySelect.val()) || 1;
        }

        console.log(sectionName + ':', {
            name: input.val(),
            qty: qty,
            priceIncVat: priceIncVat,
            basePrice: basePrice,
            cost: cost
        });

        // Add to totals
        quoteTotals.total += priceIncVat * qty;

        // Only add to profit if not a manual entry
        if (!isManual) {
            totalExVat += basePrice * qty;
            totalCost += cost * qty;
        }
    });

    // Calculate multi-item components
    $('.multi-item').each(function() {
        const itemData = $(this).data('item');
        if (!itemData || !itemData.name) return;

        const qty = parseInt($(this).find('.multi-item-qty').val()) || 1;
        const priceIncVat = parseFloat(itemData.priceIncVat) || 0;
        const basePrice = parseFloat(itemData.basePrice) || 0;
        const cost = parseFloat(itemData.cost) || 0;

        console.log('Multi-item:', {
            name: itemData.name,
            qty: qty,
            priceIncVat: priceIncVat,
            basePrice: basePrice,
            cost: cost
        });

        quoteTotals.total += priceIncVat * qty;

        if (!itemData.isManual) {
            totalExVat += basePrice * qty;
            totalCost += cost * qty;
        }
    });

    // Handle additional items
    if (typeof window.getAdditionalItemsData === 'function') {
        const additionalItemsData = window.getAdditionalItemsData();
        console.log('Additional items data:', additionalItemsData);
        
        Object.keys(additionalItemsData).forEach(itemId => {
            const item = additionalItemsData[itemId];
            if (item && item.name) {
                const priceIncVat = parseFloat(item.priceWithVAT) || 0;
                const basePrice = parseFloat(item.basePrice) || 0;
                const cost = parseFloat(item.cost) || 0;
                
                console.log('Additional item:', item.name, 'price:', priceIncVat);
                
                quoteTotals.total += priceIncVat;
                
                if (!item.isManual) {
                    totalExVat += basePrice;
                    totalCost += cost;
                }
            }
        });
    }

    // Add service charges (already include VAT)
    const installationCharge = parseFloat($('#installationCharge').val()) || 0;
    const configCharge = parseFloat($('#configCharge').val()) || 0;
    const testingCharge = parseFloat($('#testingCharge').val()) || 0;

    const totalServices = installationCharge + configCharge + testingCharge;
    
    if (totalServices > 0) {
        quoteTotals.total += totalServices;
        // Add services to profit calculation (removing VAT)
        totalExVat += (totalServices / 1.2);
    }

    // Calculate profit (using ex-VAT values)
    quoteTotals.profit = totalExVat - totalCost;

    console.log('Final totals:', quoteTotals);
}

function displayTotals() {
    // Format and display total
    $('#totalAmount').text(quoteTotals.total.toFixed(2));
    
    // Display profit if element exists
    if ($('#totalProfit').length) {
        $('#totalProfit').text(quoteTotals.profit.toFixed(2));
    }

    // Enable/disable action buttons based on total
    const hasItems = quoteTotals.total > 0;
    $('#saveQuoteBtn, #printQuoteBtn').prop('disabled', !hasItems);
}

function validateAndSavePrice($input) {
    const $parent = $input.closest('.component-details, .multi-item-details');
    const cost = parseFloat($parent.data('cost')) || 0;
    const costWithVat = cost * 1.2; // Cost including VAT
    const newPrice = parseFloat($input.val()) || 0;
    const originalPrice = parseFloat($parent.data('original-price')) || 0;

    // Validate against cost (using ex-VAT cost for validation)
    if (newPrice < costWithVat) {
        Swal.fire({
            title: 'Price Below Cost',
            html: `The price you entered (£${newPrice.toFixed(2)}) is below cost including VAT (£${costWithVat.toFixed(2)}).<br>Please enter a higher price.`,
            icon: 'error'
        });
        $input.val(originalPrice.toFixed(2));
        return false;
    }

    // Save the new price
    const basePrice = newPrice / 1.2;  // Remove VAT
    
    // Update the display
    $parent.find('.price-editable').text('£' + newPrice.toFixed(2));
    $parent.data('current-price', newPrice);

    // Update the component/multi-item data
    const $section = $parent.closest('.component-section, .multi-item');
    
    if ($section.hasClass('multi-item')) {
        // Update multi-item data
        const itemData = $section.data('item');
        itemData.priceIncVat = newPrice;
        itemData.basePrice = basePrice;
        itemData.priceEdited = true;
        $section.data('item', itemData);
    } else {
        // Update single component data
        const $display = $section.find('.component-display');
        $display.data('price_inc_vat', newPrice);
        $display.data('base_price', basePrice);
        $display.data('priceEdited', true);
    }

    // Recalculate totals
    updateTotals();
    
    return true;
}

function getQuoteData() {
    const quoteData = {
        customer: getCustomerData(),
        priceType: $('.price-type-toggle .active').data('type'),
        services: {
            installation: parseFloat($('#installationCharge').val()) || 0,
            configuration: parseFloat($('#configCharge').val()) || 0,
            testing: parseFloat($('#testingCharge').val()) || 0
        },
        components: [],
        totals: quoteTotals
    };

    // Get single-item components
    $('.component-section:not([data-allow-multiple])').each(function() {
        if ($(this).closest('#additionalItemsList').length > 0) {
            return;
        }

        const input = $(this).find('.component-display');
        if (!input.val()) return;

        const componentType = $(this).data('component');
        let quantity = 1;
        
        // Get quantity if selector exists
        const qtySelect = $(this).find('.item-qty');
        if (qtySelect.length > 0) {
            quantity = parseInt(qtySelect.val()) || 1;
        }

        quoteData.components.push({
            type: componentType,
            sku: input.data('sku'),
            name: input.val(),
            basePrice: input.data('base_price'),
            priceIncVat: input.data('price_inc_vat'),
            cost: input.data('cost'),
            quantity: quantity,
            isManual: input.data('isManual') || false,
            priceEdited: input.data('priceEdited') || false
        });
    });

    // Get multi-item components
    $('.multi-item').each(function() {
        const itemData = $(this).data('item');
        if (!itemData || !itemData.name) return;

        const qty = parseInt($(this).find('.multi-item-qty').val()) || 1;

        quoteData.components.push({
            type: itemData.type,
            sku: itemData.sku,
            name: itemData.name,
            basePrice: itemData.basePrice,
            priceIncVat: itemData.priceIncVat,
            cost: itemData.cost,
            quantity: qty,
            isManual: itemData.isManual || false,
            priceEdited: itemData.priceEdited || false
        });
    });

    // Get additional items
    if (typeof window.getAdditionalItemsData === 'function') {
        const additionalItemsData = window.getAdditionalItemsData();
        
        Object.keys(additionalItemsData).forEach(itemId => {
            const item = additionalItemsData[itemId];
            if (item && item.name) {
                quoteData.components.push({
                    type: 'additional',
                    sku: item.sku,
                    name: item.name,
                    basePrice: item.basePrice,
                    priceIncVat: item.priceWithVAT,
                    cost: item.cost,
                    quantity: 1,
                    isManual: item.isManual,
                    priceEdited: item.priceEdited || false
                });
            }
        });
    }

    return quoteData;
}

function validateQuote() {
    const errors = [];

    // Check if customer is selected
    const customerData = getCustomerData();
    if (!customerData || !customerData.name) {
        errors.push("Please select a customer");
    }

    // Check if at least one component is selected
    let hasComponents = false;
    
    // Check single components
    $('.component-section:not([data-allow-multiple])').each(function() {
        if ($(this).closest('#additionalItemsList').length > 0) {
            return;
        }
        const input = $(this).find('.component-display');
        if (input.val()) {
            hasComponents = true;
            return false;
        }
    });
    
    // Check multi-items
    if (!hasComponents && $('.multi-item').length > 0) {
        hasComponents = true;
    }
    
    // Check additional items
    if (!hasComponents && typeof window.getAdditionalItemsData === 'function') {
        const additionalItemsData = window.getAdditionalItemsData();
        if (Object.keys(additionalItemsData).length > 0) {
            hasComponents = true;
        }
    }
    
    if (!hasComponents) {
        errors.push("Please select at least one component or service");
    }

    // Check if total is above 0
    if (quoteTotals.total <= 0) {
        errors.push("Quote total must be greater than 0");
    }

    return {
        isValid: errors.length === 0,
        errors: errors
    };
}

function getCustomerData() {
    const customerDetails = $('#customerDetails');
    if (customerDetails.is(':hidden')) return null;

    return {
        id: customerDetails.data('customerId'),
        name: customerDetails.data('customerName'),
        email: customerDetails.data('customerEmail'),
        phone: customerDetails.data('customerPhone'),
        address: customerDetails.data('customerAddress')
    };
}

// Export functions that other modules might need
window.cctvQuoteCalculations = {
    updateTotals,
    validateQuote,
    getQuoteData,
    validateAndSavePrice
};

console.log('✓ CCTV Calculations loaded');