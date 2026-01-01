<?php
session_start();
require 'php/bootstrap.php';

$page_title = 'Print Labels';
require 'assets/header.php';

// Check if the user is an admin
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;

if (!$is_admin) {
   
}

?>

<?php require 'assets/navbar.php'; ?>

<div class="page-container3">
    <section class="p-t-20">
        <div class="container">
            <h2>Print Product Labels</h2>
            <form id="labelForm">
                <div class="form-group">
                    <label for="searchProduct">Search Product:</label>
                    <input type="text" id="searchProduct" class="form-control" placeholder="Enter product name or SKU">
                    <div id="productSuggestions" class="dropdown-menu" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                </div>
                <div id="selectedProducts">
                    <!-- Selected products will be listed here -->
                </div>
                <button type="submit" class="btn btn-primary">Print Labels</button>
            </form>
        </div>
    </section>
</div>

<?php require 'assets/footer.php'; ?>

<script>
$(document).ready(function () {
    // Search for products
    $('#searchProduct').on('input', function () {
        const query = $(this).val();
        if (query.length < 3) {
            $('#productSuggestions').hide();
            return;
        }
        $.getJSON('search_products.php', { q: query }, function (results) {
            const suggestions = $('#productSuggestions');
            suggestions.empty();
            if (results.length > 0) {
                results.forEach(product => {
                    suggestions.append(`
                        <a href="#" class="dropdown-item" data-sku="${product.sku}" data-name="${product.name}">
                            ${product.name} (SKU: ${product.sku})
                        </a>
                    `);
                });
                suggestions.show();
            } else {
                suggestions.hide();
            }
        });
    });

    // Add product to selection
    $(document).on('click', '#productSuggestions .dropdown-item', function (e) {
        e.preventDefault();
        const sku = $(this).data('sku');
        const name = $(this).data('name');
        $('#selectedProducts').append(`
            <div class="selected-product" data-sku="${sku}">
                <label>${name} (SKU: ${sku})</label>
                <input type="number" class="form-control label-quantity" placeholder="Quantity" min="1" value="1">
                <button type="button" class="btn btn-danger remove-product">Remove</button>
            </div>
        `);
        $('#productSuggestions').hide();
        $('#searchProduct').val('');
    });

    // Remove product from selection
    $(document).on('click', '.remove-product', function () {
        $(this).closest('.selected-product').remove();
    });

    // Submit the form to print labels
    $('#labelForm').submit(function (e) {
        e.preventDefault();
        const products = [];
        $('.selected-product').each(function () {
            products.push({
                sku: $(this).data('sku'),
                quantity: $(this).find('.label-quantity').val()
            });
        });

        if (products.length === 0) {
            alert('Please select at least one product.');
            return;
        }

        $.post('print_labels.php', { products: products }, function (response) {
            if (response.success) {
                alert('Labels sent to printer');
            } else {
                alert(response.message);
            }
        }, 'json');
    });
});
</script>
