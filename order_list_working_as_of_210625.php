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

<div class="page-container">
    <!-- Compact Header Section -->
    <div class="header-section">
        <div class="header-controls">
            <h1 class="page-title">Order List</h1>
            <?php if ($is_admin): ?>
                <a href="export_order_list.php" class="btn-excel">Export CSV</a>
            <?php endif; ?>
        </div>
        <!-- Compact Add Product Form -->
        <form id="addToOrderListForm" class="compact-form">
            <div class="form-row">
                <input type="text" name="sku" id="sku" class="form-input" placeholder="SKU">
                <div class="search-container">
                    <input type="text" name="name" id="name" class="form-input" placeholder="Product Name">
                    <div id="productSuggestions" class="suggestions-dropdown"></div>
                </div>
                <input type="number" name="quantity" id="quantity" class="form-input-small" value="1" min="1">
                <button type="submit" class="btn-add">Add</button>
            </div>
        </form>
    </div>

    <!-- Excel-like Table -->
    <div class="table-container">
        <table id="orderListTable" class="excel-table">
            <thead>
                <tr>
                    <th class="col-category">
                        <span id="categoryToggleBtn" class="toggle-btn">→</span>
                        <a href="#" class="sort-link" data-sort="category_name">Cat.</a>
                    </th>
                    <th class="col-sku">SKU</th>
                    <th class="col-name">Name</th>
                    <?php if ($is_admin): ?>
                        <th class="col-cost">Cost</th>
                    <?php endif; ?>
                    <th class="col-qty">Qty</th>
                    <th class="col-status">Status</th>
                    <th class="col-date">
                        <a href="#" class="sort-link" data-sort="last_ordered">Last Ord.</a>
                    </th>
                    <th class="col-date">
                        <a href="#" class="sort-link" data-sort="added_on">Added</a>
                    </th>
                    <th class="col-user">User</th>
                    <?php if ($is_admin): ?>
                        <th class="col-actions">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<style>
/* Modern Excel-like styling */
.page-container {
    padding: 0.5rem;
    max-width: 100%;
    margin: 0 auto;
    background: #f8f9fa;
}

.header-section {
    background: white;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.header-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.page-title {
    font-size: 1.2rem;
    margin: 0;
    color: #333;
}

/* Compact Form Styling */
.compact-form {
    display: flex;
    gap: 0.5rem;
}

.form-row {
    display: flex;
    gap: 0.5rem;
    width: 100%;
}

.form-input {
    height: 28px;
    padding: 0 0.5rem;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 0.8rem;
}

.form-input-small {
    width: 60px;
    height: 28px;
    padding: 0 0.5rem;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 0.8rem;
}

/* Buttons */
.btn-excel {
    background: #217346;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 3px;
    font-size: 0.8rem;
    text-decoration: none;
    border: none;
}

.btn-add {
    background: #0077cc;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 3px;
    font-size: 0.8rem;
    border: none;
}

/* Table Styling */
.table-container {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: auto;
    height: calc(100vh - 180px);
}

.excel-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
    font-family: "Segoe UI", Arial, sans-serif;
}

.excel-table th {
    background: #f1f1f1;
    border: 1px solid #ddd;
    padding: 0.25rem 0.5rem;
    font-weight: 600;
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
}

.excel-table td {
    border: 1px solid #ddd;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
}

.excel-table tr:nth-child(even) {
    background: #f9f9f9;
}

.excel-table tr:hover {
    background: #f0f7ff;
}

/* Column Widths */
.col-category { width: 30px; max-width: 30px; }
.col-sku { width: 100px; }
.col-name { width: 250px; }
.col-cost { width: 70px; }
.col-qty { width: 50px; }
.col-status { width: 80px; }
.col-date { width: 80px; }
.col-user { width: 100px; }
.col-actions { width: 120px; }

/* Action Buttons */
.action-btn {
    padding: 0.15rem 0.4rem;
    font-size: 0.7rem;
    border-radius: 2px;
    border: none;
    margin-right: 0.25rem;
}

.btn-status-pending {
    background: #dc3545;
    color: white;
}

.btn-status-ordered {
    background: #28a745;
    color: white;
}

.btn-delete {
    background: #6c757d;
    color: white;
}

/* Status Colors */
.status-pending {
    color: #dc3545;
    font-weight: 600;
}

.status-ordered {
    color: #28a745;
    font-weight: 600;
}

/* Suggestions Dropdown */
.search-container {
    position: relative;
    flex-grow: 1;
}

.suggestions-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 3px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.toggle-btn {
    cursor: pointer;
    padding: 0 0.25rem;
    font-size: 0.7rem;
    color: #666;
}

/* Make the table more compact */
.excel-table td, .excel-table th {
    padding: 2px 4px;
    line-height: 1.2;
}
</style>

<script>
// Load jQuery if not present
if (typeof jQuery === 'undefined') {
    document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
}

// Load SweetAlert2 if not present
if (typeof Swal === 'undefined') {
    document.write('<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"><\/script>');
}

// Load Font Awesome if not present
if (!document.querySelector('link[href*="font-awesome"]')) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
    document.head.appendChild(link);
}

// Initialize when dependencies are ready
function initializeWhenReady() {
    if (window.jQuery && window.Swal) {
        $(document).ready(function () {
            let currentSort = {
                sort_by: 'added_at',
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
                                row.append($('<td>').text(item.category_name || 'N/A')
                                    .addClass('category-column')
                                    .attr('title', item.category_name || 'N/A'));
                                row.append($('<td>').text(item.sku || 'N/A'));

                                var truncatedName = item.name.length > 50 ? item.name.substring(0, 50) + '...' : item.name;
                                var nameCell = $('<td>')
                                    .text(truncatedName)
                                    .attr('title', item.name);
                                row.append(nameCell);

                                if (response.is_admin) {
                                    row.append($('<td>').text(`£${item.cost_price || 'N/A'}`));
                                }

                                row.append($('<td>').text(item.quantity || 'N/A'));
                                row.append($('<td>').text(item.status)
                                    .addClass(item.status === 'pending' ? 'status-pending' : 'status-ordered'));
                                row.append($('<td>').text(item.last_ordered || 'N/A'));
                                row.append($('<td>').text(item.added_on || 'N/A'));
                                row.append($('<td>').text(item.requested_by || 'Unknown'));

                                if (response.is_admin) {
                                    var actions = $('<td>');
                                    var statusBtn = $('<button>')
                                        .addClass('action-btn ' + 
                                            (item.status === 'pending' ? 'btn-status-ordered' : 'btn-status-pending'))
                                        .text(item.status === 'pending' ? '✓' : '↺')
                                        .attr('title', item.status === 'pending' ? 'Mark as Ordered' : 'Mark as Pending')
                                        .data('id', item.id)
                                        .data('status', item.status === 'pending' ? 'ordered' : 'pending')
                                        .click(updateStatus);

                                    var deleteBtn = $('<button>')
                                        .addClass('action-btn btn-delete')
                                        .text('×')
                                        .attr('title', 'Delete')
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
                    initializeCategoryToggle();
                });
            }

            // Handle Product Name search
            $('#name').on('input', function () {
                var nameQuery = $(this).val();

                if (nameQuery.length < 3) {
                    $('#productSuggestions').hide();
                    return;
                }

                $.getJSON('search_products.php', { q: nameQuery }, function (results) {
                    var suggestions = $('#productSuggestions');
                    suggestions.empty();

                    if (results.length > 0) {
                        results.forEach(function (product) {
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

            // Handle suggestion click
            $(document).on('click', '#productSuggestions .dropdown-item', function (e) {
                e.preventDefault();
                var name = $(this).data('name');
                var sku = $(this).data('sku');
                $('#name').val(name);
                $('#sku').val(sku);
                $('#productSuggestions').hide();
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

                $.post('add_to_order_list.php', { 
                    sku: sku, 
                    name: name, 
                    quantity: quantity 
                }, function (response) {
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

                $.post('update_order_status.php', { 
                    id: id, 
                    status: status 
                }, function (response) {
                    if (response.success) {
                        fetchOrderList();
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
                                fetchOrderList();
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

                $('.sort-link').each(function () {
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

            // Category toggle functionality
            let isCategoryVisible = false;

            function toggleCategoryColumn() {
                isCategoryVisible = !isCategoryVisible;
                if (isCategoryVisible) {
                    $('.category-column').css({
                        'width': '150px',
                        'max-width': '150px',
                        'min-width': '150px'
                    }).removeClass('collapsed');
                    $('#categoryToggleBtn').text('←');
                } else {
                    $('.category-column').css({
                        'width': '10px',
                        'max-width': '10px',
                        'min-width': '10px'
                    }).addClass('collapsed');
                    $('#categoryToggleBtn').text('→');
                }
            }

            function initializeCategoryToggle() {
                if (!$('#categoryToggleBtn').length) {
                    $('#orderListTable thead tr').first().find('th').first()
                        .addClass('category-column')
                        .prepend(
                            $('<span>')
                                .addClass('category-toggle')
                                .attr('id', 'categoryToggleBtn')
                                .text('→')
                                .click(toggleCategoryColumn)
                        );
                }
            }

            // Initial load
            fetchOrderList();
        });
    } else {
        setTimeout(initializeWhenReady, 50);
    }
}

initializeWhenReady();
</script>
<?php require 'assets/footer.php'; ?>