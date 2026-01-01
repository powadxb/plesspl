<?php
session_start();
if (!isset($_SESSION['dins_user_id'])) {
    header('Location: login.php');
    exit;
}

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
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <h1 class="title-4">Order List</h1>
                    <hr class="line-seprate">
                </div>
            </div>
        </div>
    </section>

    <section class="p-t-20">
        <div class="container-fluid">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Add Product to Order List</h5>
                </div>
                <div class="card-body">
                    <form id="addToOrderListForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sku">SKU (exact match):</label>
                                    <input type="text" name="sku" id="sku" class="form-control" placeholder="Enter SKU">
                                </div>
                            </div>
                            <div class="col-md-4 position-relative">
                                <div class="form-group">
                                    <label for="name">Product Name (fuzzy search):</label>
                                    <input type="text" name="name" id="name" class="form-control" placeholder="Enter product name">
                                    <div id="productSuggestions" class="dropdown-menu" style="max-height: 200px; overflow-y: auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="quantity">Quantity:</label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="1">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">Add to Order List</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Order List</h5>
                    <?php if ($is_admin): ?>
                    <a href="export_order_list.php" class="btn btn-success">Export as CSV</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="orderListTable">
                            <thead>
                                <tr>
                                    <th><a href="#" class="sort-link" data-sort="category_name">Category <i class="fas fa-sort"></i></a></th>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <?php if ($is_admin): ?>
                                    <th class="text-right">Last Cost</th>
                                    <?php endif; ?>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th><a href="#" class="sort-link" data-sort="last_ordered">Last Ordered <i class="fas fa-sort"></i></a></th>
                                    <th><a href="#" class="sort-link" data-sort="added_on">Added On <i class="fas fa-sort"></i></a></th>
                                    <th>Requested By</th>
                                    <?php if ($is_admin): ?>
                                    <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.card {
    border: none;
    border-radius: 8px;
    margin-bottom: 1rem;
}
.shadow-sm {
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
}
.card-header {
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem;
}
.card-body {
    padding: 1rem;
}
.table th {
    border-top: none;
    background-color: #f8f9fa;
}
.table td {
    vertical-align: middle;
}
.badge {
    padding: 0.4em 0.6em;
    font-size: 85%;
}
.badge-pending {
    background-color: #fff3cd;
    color: #856404;
}
.badge-ordered {
    background-color: #d4edda;
    color: #155724;
}
.btn {
    font-weight: 500;
}
.form-group {
    margin-bottom: 1rem;
}
</style>

<script>
$(document).ready(function() {
    let currentSort = {
        sort_by: 'added_on',
        sort_direction: 'ASC'
    };

    function fetchOrderList() {
        $.getJSON('fetch_order_list.php', currentSort, function(response) {
            if (response.success) {
                var tbody = $('#orderListTable tbody');
                tbody.empty();

                if (response.data.length > 0) {
                    response.data.forEach(function(item) {
                        var row = $('<tr>');
                        row.append($('<td>').text(item.category_name || 'N/A'));
                        row.append($('<td>').text(item.sku || 'N/A'));
                        
                        var truncatedName = item.name.length > 55 ? item.name.substring(0, 55) + '...' : item.name;
                        var nameCell = $('<td>')
                            .text(truncatedName)
                            .attr('title', item.name);
                        row.append(nameCell);

                        if (response.is_admin) {
                            row.append($('<td>').text(`£${item.cost_price || 'N/A'}`).addClass('text-right'));
                        }

                        row.append($('<td>').text(item.quantity || 'N/A'));
                        
                        var statusBadge = $('<span>')
                            .addClass('badge ' + (item.status === 'pending' ? 'badge-pending' : 'badge-ordered'))
                            .text(item.status);
                        row.append($('<td>').append(statusBadge));
                        
                        row.append($('<td>').text(item.last_ordered || 'N/A'));
                        row.append($('<td>').text(item.added_on || 'N/A'));
                        row.append($('<td>').text(item.requested_by || 'Unknown'));

                        if (response.is_admin) {
                            var actions = $('<td>');
                            var statusBtn = $('<button>')
                                .addClass('btn btn-sm ' + (item.status === 'pending' ? 'btn-success' : 'btn-secondary'))
                                .text(item.status === 'pending' ? 'Mark as Ordered' : 'Mark as Pending')
                                .data('id', item.id)
                                .data('status', item.status === 'pending' ? 'ordered' : 'pending')
                                .click(updateStatus);

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

    $('#name').on('input', function() {
        var nameQuery = $(this).val();
        if (nameQuery.length < 3) {
            $('#productSuggestions').hide();
            return;
        }

        $.getJSON('search_products.php', { q: nameQuery }, function(results) {
            var suggestions = $('#productSuggestions');
            suggestions.empty();

            if (results.length > 0) {
                results.forEach(function(product) {
                    suggestions.append(`
                        <a href="#" class="dropdown-item" data-sku="${product.sku}" data-name="${product.name}">
                            ${product.name} (${product.manufacturer || ''}, ${product.mpn || ''}, ${product.ean || ''})
                        </a>
                    `);
                });
                suggestions.show();
            } else {
                suggestions.hide();
            }
        });
    });

    $(document).on('click', '#productSuggestions .dropdown-item', function(e) {
        e.preventDefault();
        var name = $(this).data('name');
        var sku = $(this).data('sku');
        $('#name').val(name);
        $('#sku').val(sku);
        $('#productSuggestions').hide();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#name, #productSuggestions').length) {
            $('#productSuggestions').hide();
        }
    });

    $('#addToOrderListForm').submit(function(e) {
        e.preventDefault();
        var data = {
            sku: $('#sku').val().trim(),
            name: $('#name').val().trim(),
            quantity: $('#quantity').val()
        };

        if (!data.sku && !data.name) {
            Swal.fire('Error', 'You must enter either a SKU or a Product Name.', 'error');
            return;
        }

        $.post('add_to_order_list.php', data, function(response) {
            if (response.success) {
                Swal.fire('Success', 'Product added to the order list.', 'success');
                $('#addToOrderListForm')[0].reset();
                fetchOrderList();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }, 'json');
    });

    function updateStatus() {
        var id = $(this).data('id');
        var status = $(this).data('status');

        $.post('update_order_status.php', { id: id, status: status }, function(response) {
            if (response.success) {
                fetchOrderList();
                Swal.fire('Success', 'Status updated successfully.', 'success');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }, 'json');
    }

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
                $.post('delete_order_item.php', { id: id }, function(response) {
                    if (response.success) {
                        fetchOrderList();
                        Swal.fire('Success', 'Item deleted successfully.', 'success');
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    $(document).on('click', '.sort-link', function(e) {
        e.preventDefault();
        const sort_by = $(this).data('sort');
        const currentDirection = $(this).data('direction');
        const newDirection = currentDirection === 'ASC' ? 'DESC' : 'ASC';

        currentSort.sort_by = sort_by;
        currentSort.sort_direction = newDirection;

        $('.sort-link').each(function() {
            $(this).data('direction', 'ASC');
            $(this).find('i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
        });

        $(this)
            .data('direction', newDirection)
            .find('i')
            .removeClass('fa-sort')
            .addClass(newDirection === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');

        fetchOrderList();
    });

    fetchOrderList();
});
</script>

<?php require 'assets/footer.php'; ?>