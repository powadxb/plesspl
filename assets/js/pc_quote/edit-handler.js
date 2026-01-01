// assets/js/pc_quote/edit-handler.js
// Simplified: Just populate the form, let existing system handle everything

(function() {
    'use strict';
    
    console.log('✓ Edit handler loaded');
    
    // Store original prices for revert functionality
    let originalPrices = {};
    let currentPrices = {};

    $(document).ready(function() {
        const quoteId = $('#quote_id').val();
        
        if (quoteId && quoteId !== '' && quoteId !== '0') {
            console.log('✓ EDIT MODE - Loading quote', quoteId);
            loadQuoteForEdit(quoteId);
        } else {
            console.log('✓ NEW QUOTE MODE');
        }
    });

    function loadQuoteForEdit(quoteId) {
        Swal.fire({
            title: 'Loading Quote...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'ajax/get_quote.php',
            method: 'GET',
            data: { id: quoteId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.quote) {
                    populateForm(response.quote);
                    
                    Swal.fire({
                        title: 'Quote Loaded',
                        text: 'Quote #' + quoteId + ' loaded for editing',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    throw new Error(response.message || 'Failed to load quote');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Failed to load quote';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                } catch (e) {}
                
                Swal.fire({
                    title: 'Error',
                    text: errorMsg,
                    icon: 'error'
                }).then(() => {
                    window.location.href = 'quotes.php';
                });
            }
        });
    }

    function populateForm(quoteData) {
        console.log('Populating form with:', quoteData);
        
        // Populate customer
        if (quoteData.customer && quoteData.customer.name) {
            $('#customerSearch').val(quoteData.customer.name);
            
            const detailsHtml = `
                <strong>${quoteData.customer.name}</strong><br>
                ${quoteData.customer.phone ? `Phone: ${quoteData.customer.phone}<br>` : ''}
                ${quoteData.customer.email ? `Email: ${quoteData.customer.email}<br>` : ''}
                ${quoteData.customer.address ? `${quoteData.customer.address}` : ''}
            `;
            
            $('#customerDetails').html(detailsHtml).show();
            $('#customerDetails').data({
                customerId: quoteData.customer.id,
                customerName: quoteData.customer.name,
                customerEmail: quoteData.customer.email,
                customerPhone: quoteData.customer.phone,
                customerAddress: quoteData.customer.address
            });
        }
        
        // Set price type
        if (quoteData.priceType) {
            $('.price-type-toggle .btn').removeClass('active btn-primary').addClass('btn-outline-primary');
            $(`.price-type-toggle .btn[data-type="${quoteData.priceType}"]`)
                .addClass('active btn-primary')
                .removeClass('btn-outline-primary');
        }
        
        // Set build charge
        if (quoteData.buildCharge !== undefined) {
            $('#buildCharge').val(parseFloat(quoteData.buildCharge).toFixed(2));
        }
        
        // Load components into sections
        if (quoteData.components && quoteData.components.length > 0) {
            const $sections = $('.component-section[data-component]');
            
            quoteData.components.forEach((component, index) => {
                if (index < $sections.length && component.name) {
                    const $section = $sections.eq(index);
                    const $input = $section.find('.component-display');
                    
                    // Calculate prices
                    const basePrice = parseFloat(component.basePrice) || 0;
                    const priceWithVat = basePrice * 1.2;
                    
                    // Set input value
                    $input.val(component.name);
                    
                    // Store data on input (the way the system expects)
                    $input.data('sku', component.sku);
                    $input.data('price_inc_vat', priceWithVat);
                    $input.data('base_price', basePrice);
                    $input.data('cost', parseFloat(component.cost) || 0);
                    $input.data('isManual', component.isManual);
                    
                    // Show remove button
                    $section.find('.btn-remove').show();
                    
                    // Set quantity for RAM
                    if ($section.attr('data-component') === 'ram' && component.quantity > 1) {
                        $section.find('.ram-qty').val(component.quantity);
                    }
                    
                    // Update display
                    let displayPrice = priceWithVat;
                    if (component.quantity > 1) {
                        displayPrice = priceWithVat * component.quantity;
                        $section.find('.component-details').html(`£${priceWithVat.toFixed(2)} × ${component.quantity} = £${displayPrice.toFixed(2)}`);
                    } else {
                        $section.find('.component-details').html(`£${displayPrice.toFixed(2)}`);
                    }
                }
            });
        }
        
        // Trigger calculation after a brief delay
        setTimeout(function() {
            if (window.quoteCalculations && window.quoteCalculations.updateTotals) {
                window.quoteCalculations.updateTotals();
            }
        }, 300);
    }

})();