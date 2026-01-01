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
    $('#buildCharge').on('change', function() {
        updateTotals();
    });

    // Listen for RAM quantity changes
    $('.ram-qty').on('change', function() {
        updateTotals();
    });
}

function updateTotals() {
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

    // Calculate each component section
    $('.component-section').each(function() {
        const input = $(this).find('input[type="text"]');
        if (!input.val()) return; // Skip empty components

        let priceIncVat = parseFloat(input.data('price_inc_vat')) || 0;
        let basePrice = parseFloat(input.data('base_price')) || 0;
        let cost = parseFloat(input.data('cost')) || 0;
        let isManual = input.data('isManual') || false;
        let qty = 1;

        // Handle RAM quantity
        if ($(this).find('label').text() === 'RAM') {
            qty = parseInt($(this).find('.ram-qty').val()) || 1;
            priceIncVat *= qty;
            basePrice *= qty;
            cost *= qty;
        }

        // Add to totals (VAT inclusive total for display)
        componentTotals.total += priceIncVat;

        // Only add to profit if not a manual entry
        if (!isManual) {
            totalExVat += basePrice;
            totalCost += cost;
        }
    });

    // Handle additional items
    $('#additionalItemsList .additional-item').each(function() {
        const priceIncVat = parseFloat($(this).data('price_inc_vat')) || 0;
        componentTotals.total += priceIncVat;
        // Note: Additional items don't contribute to profit
    });

    // Add build charge (already includes VAT)
    const buildCharge = parseFloat($('#buildCharge').val()) || 0;
    if (buildCharge > 0) {
        componentTotals.total += buildCharge;
        // Add build charge to profit calculation (removing VAT)
        totalExVat += (buildCharge / 1.2); // Remove VAT for profit calculation
    }

    // Calculate profit (using ex-VAT values)
    componentTotals.profit = totalExVat - totalCost;
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
        additionalItems: [],
        totals: componentTotals
    };

    // Get all component data
    $('.component-section').each(function() {
        const input = $(this).find('input[type="text"]');
        if (!input.val()) return;

        quoteData.components.push({
            type: $(this).find('label').text(),
            sku: input.data('sku'),
            name: input.val(),
            basePrice: input.data('base_price'),
            priceIncVat: input.data('price_inc_vat'),
            cost: input.data('cost'),
            quantity: $(this).find('.ram-qty').val() || 1,
            isManual: input.data('isManual') || false
        });
    });

    // Get additional items
    $('#additionalItemsList .additional-item').each(function() {
        quoteData.additionalItems.push({
            name: $(this).data('name'),
            basePrice: $(this).data('base_price'),
            priceIncVat: $(this).data('price_inc_vat'),
            cost: $(this).data('cost'),
            isManual: true
        });
    });

    return quoteData;
}

function validateQuote() {
    const errors = [];

    // Check if customer is selected
    if (!getCustomerData()) {
        errors.push("Please select a customer");
    }

    // Check if at least one component is selected
    if ($('.component-section input[type="text"]').filter(function() { 
        return $(this).val(); 
    }).length === 0) {
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