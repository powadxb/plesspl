// assets/js/pc_quote/calculations.js

let componentTotals = {
    total: 0,    // Total including VAT
    profit: 0    // Profit excluding VAT
};

$(document).ready(function() {
    initializeCalculations();
    updateTotals(); // Initial calculation
});

function initializeCalculations() {
    // Listen for build charge changes
    $('#buildCharge').on('change keyup', function() {
        updateTotals();
    });

    // Listen for RAM quantity changes
    $('.ram-qty').on('change', function() {
        updateTotals();
    });
}

function updateTotals() {
    console.log('updateTotals called');
    resetTotals();
    calculateComponentTotals();
    displayTotals();
}

function resetTotals() {
    componentTotals = {
        total: 0,
        profit: 0
    };
}

function calculateComponentTotals() {
    let totalExVat = 0;
    let totalCost = 0;

    console.log('Calculating components...');

    // Calculate each REGULAR component section (not additional items)
    $('.component-section').each(function() {
        // Skip additional items in the list
        if ($(this).closest('#additionalItemsList').length > 0) {
            return;
        }

        const input = $(this).find('.component-display');
        const sectionName = $(this).find('label').text();
        
        if (!input.val()) {
            console.log(sectionName + ': empty');
            return; // Skip empty components
        }

        let priceIncVat = parseFloat(input.data('price_inc_vat')) || 0;
        let basePrice = parseFloat(input.data('base_price')) || 0;
        let cost = parseFloat(input.data('cost')) || 0;
        let isManual = input.data('isManual') || false;
        let qty = 1;

        console.log(sectionName + ':', input.val());
        console.log('  price_inc_vat:', priceIncVat);
        console.log('  base_price:', basePrice);
        console.log('  cost:', cost);

        // Handle RAM quantity
        if (sectionName === 'RAM') {
            qty = parseInt($(this).find('.ram-qty').val()) || 1;
            priceIncVat *= qty;
            basePrice *= qty;
            cost *= qty;
            console.log('  quantity:', qty);
        }

        // Add to totals (VAT inclusive total for display)
        componentTotals.total += priceIncVat;

        // Only add to profit if not a manual entry
        if (!isManual) {
            totalExVat += basePrice;
            totalCost += cost;
        }
    });

    // Handle additional items using the new data structure
    if (typeof window.getAdditionalItemsData === 'function') {
        const additionalItemsData = window.getAdditionalItemsData();
        console.log('Additional items data:', additionalItemsData);
        
        Object.keys(additionalItemsData).forEach(itemId => {
            const item = additionalItemsData[itemId];
            if (item.name) {
                const priceIncVat = parseFloat(item.priceWithVAT) || 0;
                const basePrice = parseFloat(item.basePrice) || 0;
                const cost = parseFloat(item.cost) || 0;
                
                console.log('Additional item:', item.name, 'price:', priceIncVat);
                
                componentTotals.total += priceIncVat;
                
                // Add to profit calculation if not manual
                if (!item.isManual) {
                    totalExVat += basePrice;
                    totalCost += cost;
                }
            }
        });
    }

    // Add build charge (already includes VAT)
    const buildCharge = parseFloat($('#buildCharge').val()) || 0;
    if (buildCharge > 0) {
        componentTotals.total += buildCharge;
        // Add build charge to profit calculation (removing VAT)
        totalExVat += (buildCharge / 1.2); // Remove VAT for profit calculation
    }

    // Calculate profit (using ex-VAT values)
    componentTotals.profit = totalExVat - totalCost;

    console.log('Final totals:', componentTotals);
}

function displayTotals() {
    // Format and display total
    $('#totalAmount').text(componentTotals.total.toFixed(2));
    
    // Display profit if admin section exists
    if ($('#totalProfit').length) {
        $('#totalProfit').text(componentTotals.profit.toFixed(2));
    }

    // Enable/disable action buttons based on total
    const hasItems = componentTotals.total > 0;
    $('#saveQuoteBtn, #printQuoteBtn, #createOrderBtn').prop('disabled', !hasItems);
}

function getQuoteData() {
    const quoteData = {
        customer: getCustomerData(),
        priceType: $('.price-type-toggle .active').data('type'),
        buildCharge: parseFloat($('#buildCharge').val()) || 0,
        components: [],
        totals: componentTotals
    };

    // Get all REGULAR component data (not additional items)
    $('.component-section').each(function() {
        // Skip additional items in the list
        if ($(this).closest('#additionalItemsList').length > 0) {
            return;
        }

        const input = $(this).find('.component-display');
        if (!input.val()) return;

        const componentType = $(this).data('component');
        let quantity = 1;
        
        // Get quantity for RAM
        if (componentType === 'ram') {
            quantity = parseInt($(this).find('.ram-qty').val()) || 1;
        }

        quoteData.components.push({
            type: componentType,
            sku: input.data('sku'),
            name: input.val(),
            basePrice: input.data('base_price'),
            cost: input.data('cost'),
            quantity: quantity,
            isManual: input.data('isManual') || false
        });
    });

    // Get additional items using the new data structure
    if (typeof window.getAdditionalItemsData === 'function') {
        const additionalItemsData = window.getAdditionalItemsData();
        
        Object.keys(additionalItemsData).forEach(itemId => {
            const item = additionalItemsData[itemId];
            if (item.name) {
                quoteData.components.push({
                    type: 'additional',
                    sku: item.sku,
                    name: item.name,
                    basePrice: item.basePrice,
                    cost: item.cost,
                    quantity: 1,
                    isManual: item.isManual
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

    // Check if at least one component is selected (including additional items)
    let hasComponents = false;
    
    // Check regular components
    $('.component-section').each(function() {
        if ($(this).closest('#additionalItemsList').length > 0) {
            return;
        }
        const input = $(this).find('.component-display');
        if (input.val()) {
            hasComponents = true;
            return false; // break
        }
    });
    
    // Check additional items
    if (!hasComponents && typeof window.getAdditionalItemsData === 'function') {
        const additionalItemsData = window.getAdditionalItemsData();
        if (Object.keys(additionalItemsData).length > 0) {
            hasComponents = true;
        }
    }
    
    if (!hasComponents) {
        errors.push("Please select at least one component");
    }

    // Check if total is above 0
    if (componentTotals.total <= 0) {
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
window.quoteCalculations = {
    updateTotals,
    validateQuote,
    getQuoteData
};

console.log('✓ Calculations loaded');