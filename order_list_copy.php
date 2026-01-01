<?php
session_start();
$page_title = 'Order List';
require 'php/bootstrap.php';
require 'assets/header.php';

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;
?>

<?php require 'assets/navbar.php'; ?>

<div class="page-container3">
    <section class="welcome p-t-20">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h1 class="title-4">Add Product to Order List</h1>
                    <hr class="line-seprate">
                </div>
            </div>
        </div>
    </section>

    <section class="p-t-20">
        <div class="container">
            <!-- Add Product Form -->
            <div class="mb-4">
                <form id="addToOrderListForm">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="sku">SKU (exact match):</label>
                            <input type="text" name="sku" id="sku" class="form-control" placeholder="Enter SKU">
                        </div>
                        <div class="col-md-4 position-relative">
                            <label for="name">Product Name (fuzzy search):</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Enter product name">
                            <div id="productSuggestions" class="dropdown-menu" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-md-2">
                            <label for="quantity">Quantity:</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">Add to Order List</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Order List Table -->
            <div class="table-responsive table-bordered m-b-30">
                <table class="table table-striped table-bordered" id="orderListTable">
                    <thead>
                        <tr>
                            <th>
                                <a href="#" class="sort-link" data-sort="category_name" data-direction="ASC">
                                    Category <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>SKU</th>
                            <th>Name</th>
                            <?php if ($is_admin): ?>
                            <th>Last Cost</th>
                            <?php endif; ?>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>
                                <a href="#" class="sort-link" data-sort="last_ordered" data-direction="ASC">
                                    Last Ordered <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>
                                <a href="#" class="sort-link" data-sort="added_on" data-direction="ASC">
                                    Added On <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Requested By</th>
                            <?php if ($is_admin): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php require 'assets/footer.php'; ?>

<script>
$(document).ready(function () {
    let currentSort = {
        sort_by: 'added_on',
        sort_direction: 'ASC'
    };

    // Fetch the order list
    function fetchOrderList() {
        $.getJSON('fetch_order_list.php', currentSort, function (response) {
            if (response.success) {
                var tbody = $('#orderListTable tbody');
                tbody.empty();

                if (response.data.length > 0) {
                    response.data.forEach(function (item) {
                        var row = $('<tr>');
                        row.append($('<td>').text(item.category_name || 'N/A'));
                        row.append($('<td>').text(item.sku || 'N/A'));
                        row.append($('<td>').text(item.name || 'N/A'));

                        if (response.is_admin) {
                            row.append($('<td>').text(`£${item.cost_price || 'N/A'}`).addClass('text-right'));
                        }

                        row.append($('<td>').text(item.quantity || 'N/A'));
                        row.append($('<td>').text(item.status).css({
                            'color': item.status === 'pending' ? 'red' : 'green',
                            'font-weight': 'bold'
                        }));
                        row.append($('<td>').text(item.last_ordered || 'N/A'));
                        row.append($('<td>').text(item.added_on || 'N/A'));
                        row.append($('<td>').text(item.requested_by || 'Unknown'));

                        if (response.is_admin) {
                            var actions = $('<td>');

                            // Mark as Ordered button
                            var statusBtn = $('<button>')
                                .addClass('btn btn-sm ' + (item.status === 'pending' ? 'btn-success' : 'btn-secondary'))
                                .text(item.status === 'pending' ? 'Mark as Ordered' : 'Mark as Pending')
                                .data('id', item.id)
                                .data('status', item.status === 'pending' ? 'ordered' : 'pending')
                                .click(updateStatus);

                            // Delete button
                            var deleteBtn = $('<button>')
                                .addClass('btn btn-sm btn-danger ml-2')
                                .text('Delete')
                                .data('id', item.id)
                                .click(deleteItem);

                            actions.append(statusBtn).append(deleteBtn);
                            row.append(actions);
                        }

                        tbody.append(row);
                    });
                } else {
                    tbody.append('<tr><td colspan="10" class="text-center">No data found</td></tr>');
                }
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        });
    }

    // Handle Product Name search (fuzzy tokenised)
    $('#name').on('input', function () {
        var nameQuery = $(this).val();

        // Only perform the search if at least 3 characters are entered
        if (nameQuery.length < 3) {
            $('#productSuggestions').hide();
            return;
        }

        // Perform AJAX request to the backend
        $.getJSON('search_products.php', { q: nameQuery }, function (results) {
            var suggestions = $('#productSuggestions');
            suggestions.empty(); // Clear previous suggestions

            if (results.length > 0) {
                results.forEach(function (product) {
                    suggestions.append(`
                        <a href="#" class="dropdown-item" data-sku="${product.sku}" data-name="${product.name}">
                            ${product.name} (${product.manufacturer || ''}, ${product.mpn || ''}, ${product.ean || ''})
                        </a>
                    `);
                });
                suggestions.show(); // Display the dropdown with new suggestions
            } else {
                suggestions.hide(); // Hide the dropdown if no matches
            }
        });
    });

    // Handle suggestion click
    $(document).on('click', '#productSuggestions .dropdown-item', function (e) {
        e.preventDefault();
        var name = $(this).data('name');
        var sku = $(this).data('sku');

        // Populate the name and SKU fields with the selected suggestion
        $('#name').val(name);
        $('#sku').val(sku);
        $('#productSuggestions').hide(); // Hide the dropdown
    });

    // Hide suggestions when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#name, #productSuggestions').length) {
            $('#productSuggestions').hide();
        }
    });

    // Add Product to Order List
    $('#addToOrderListForm').submit(function (e) {
        e.preventDefault();

        var sku = $('#sku').val().trim();
        var name = $('#name').val().trim();
        var quantity = $('#quantity').val();

        if (!sku && !name) {
            Swal.fire('Error', 'You must enter either a SKU or a Product Name.', 'error');
            return;
        }

        $.post('add_to_order_list.php', { sku: sku, name: name, quantity: quantity }, function (response) {
            if (response.success) {
                Swal.fire('Success', 'Product added to the order list.', 'success');
                $('#addToOrderListForm')[0].reset();
                fetchOrderList();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }, 'json');
    });

    // Update the status of an item
    function updateStatus() {
        var id = $(this).data('id');
        var status = $(this).data('status');

        $.post('update_order_status.php', { id: id, status: status }, function (response) {
            if (response.success) {
                fetchOrderList(); // Refresh the table
                Swal.fire('Success', 'Status updated successfully.', 'success');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }, 'json');
    }

    // Delete an item from the list
    function deleteItem() {
        var id = $(this).data('id');

        Swal.fire({
            title: 'Are you sure?',
            text: 'This will delete the item from the order list.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('delete_order_item.php', { id: id }, function (response) {
                    if (response.success) {
                        fetchOrderList(); // Refresh the table
                        Swal.fire('Success', 'Item deleted successfully.', 'success');
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    // Handle sorting
    $(document).on('click', '.sort-link', function (e) {
        e.preventDefault();

        const sort_by = $(this).data('sort');
        const currentDirection = $(this).data('direction');
        const newDirection = currentDirection === 'ASC' ? 'DESC' : 'ASC';

        currentSort.sort_by = sort_by;
        currentSort.sort_direction = newDirection;

        // Update the UI direction icon
        $('.sort-link').each(function () {
            $(this).data('direction', 'ASC'); // Reset all to default ASC
            $(this).find('i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
        });

        $(this)
            .data('direction', newDirection) // Update clicked column's direction
            .find('i')
            .removeClass('fa-sort')
            .addClass(newDirection === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');

        fetchOrderList();
    });

    fetchOrderList();
});
</script>
