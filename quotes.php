<?php
session_start();
$page_title = 'PC Build Quotes';
require 'php/bootstrap.php';

// Ensure session is active
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check if user has permission
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'pc_quote'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

require 'assets/header.php';
require 'assets/navbar.php';
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2>PC Build Quotes</h2>
        </div>
        <div class="col text-right">
            <a href="pc_quote.php" class="btn btn-primary">New Quote</a>
        </div>
    </div>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="searchForm" class="form-inline">
                <div class="form-group mr-2">
                    <input type="text" class="form-control" id="searchCustomer" placeholder="Customer Name" style="width: 200px;">
                </div>
                <div class="form-group mr-2">
                    <select class="form-control" id="searchStatus">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                        <option value="converted">Converted to Order</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>
    </div>

    <!-- Results Table -->
    <div class="table-responsive">
        <table class="table table-hover" id="quotesTable">
            <thead>
                <tr>
                    <th>Quote #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be loaded here -->
            </tbody>
        </table>
    </div>
</div>

<!-- Add jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    loadQuotes();

    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        loadQuotes();
    });
});

function loadQuotes() {
    const searchData = {
        customer: $('#searchCustomer').val(),
        status: $('#searchStatus').val()
    };

    $.ajax({
        url: 'ajax/get_quotes.php',
        method: 'POST',
        data: searchData,
        success: function(response) {
            try {
                const quotes = JSON.parse(response);
                displayQuotes(quotes);
            } catch (e) {
                console.error('Error parsing quotes:', e);
            }
        },
        error: function() {
            console.error('Failed to load quotes');
        }
    });
}

function displayQuotes(quotes) {
    const tbody = $('#quotesTable tbody');
    tbody.empty();

    if (quotes.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center">No quotes found</td></tr>');
        return;
    }

    quotes.forEach(quote => {
        const createdDate = new Date(quote.date_created).toLocaleString('en-GB');
        const row = `
            <tr>
                <td>${quote.id}</td>
                <td>${quote.customer_name}</td>
                <td>${createdDate}</td>
                <td class="text-right">£${parseFloat(quote.total_price).toFixed(2)}</td>
                <td>${quote.status}</td>
                <td>${quote.created_by_name}</td>
                <td>
                    <a href="view_quote.php?id=${quote.id}" class="btn btn-sm btn-info">View</a>
                    <button onclick="printQuote(${quote.id})" class="btn btn-sm btn-primary">Print</button>
                    ${quote.status !== 'converted' ? 
                        `<button onclick="createOrder(${quote.id})" class="btn btn-sm btn-success">Create Order</button>` 
                        : ''}
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function printQuote(quoteId) {
    window.open(`view_quote.php?id=${quoteId}&print=true`, '_blank');
}

function createOrder(quoteId) {
    Swal.fire({
        title: 'Create Order',
        text: 'Are you sure you want to create an order from this quote?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, create order',
        cancelButtonText: 'No, cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/create_order.php',
                method: 'POST',
                data: { quoteId: quoteId },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            Swal.fire({
                                title: 'Success',
                                text: 'Order created successfully',
                                icon: 'success'
                            }).then(() => {
                                if (result.orderId) {
                                    window.location.href = `view_order.php?id=${result.orderId}`;
                                }
                            });
                        } else {
                            throw new Error(result.message || 'Failed to create order');
                        }
                    } catch (e) {
                        Swal.fire('Error', e.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to create order', 'error');
                }
            });
        }
    });
}
</script>

<?php require 'assets/footer.php'; ?>