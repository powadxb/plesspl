<?php
session_start();
$page_title = 'View Quote';
require 'php/bootstrap.php';

// Ensure session is active
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check if user has permission for this page
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'pc_quote'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Get quote ID from URL
$quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$quote_id) {
    header('Location: pc_quote.php');
    exit;
}

// Fetch quote details
$quote = $DB->query("
    SELECT q.*, 
           u.username as created_by_name,
           u2.username as modified_by_name
    FROM quotation_master q
    LEFT JOIN users u ON q.created_by = u.id
    LEFT JOIN users u2 ON q.modified_by = u2.id
    WHERE q.id = ?
", [$quote_id])[0];

// Fetch quote items
$items = $DB->query("
    SELECT * 
    FROM quotation_items 
    WHERE quote_id = ?
    ORDER BY line_order
", [$quote_id]);

require 'assets/header.php';
require 'assets/navbar.php';
?>

<div class="container mt-4">
    <!-- Header Section -->
    <div class="row mb-3">
        <div class="col">
            <h2><?php echo htmlspecialchars($quote['customer_name']) . '.' . date('dmY', strtotime($quote['date_created'])); ?></h2>
        </div>
        <div class="col text-right">
            <div class="btn-group no-print">
                <button onclick="window.print()" class="btn btn-primary btn-sm">Print with Prices</button>
                <button onclick="printWithoutPrices()" class="btn btn-outline-primary btn-sm">Print without Prices</button>
                <?php if ($quote['status'] != 'converted'): ?>
                    <button onclick="createOrder(<?php echo $quote_id; ?>)" class="btn btn-success btn-sm">Create Order</button>
                <?php endif; ?>
                <a href="pc_quote.php" class="btn btn-secondary btn-sm">New Quote</a>
                <a href="quotes.php" class="btn btn-info btn-sm">Find Quote</a>
            </div>
        </div>
    </div>

    <!-- Customer Details Section -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Customer Details</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($quote['customer_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($quote['customer_email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($quote['customer_phone']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($quote['customer_address'])); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quote Details Section - With Prices -->
    <div class="card mb-4 show-with-prices">
        <div class="card-header">
            <strong>Quote Details</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Description</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_sku'] ? 'SKU: ' . $item['product_sku'] : 'Manual Entry'); ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-right"><?php echo $item['quantity']; ?></td>
                                <td class="text-right">£<?php echo number_format($item['unit_price'] * (1 + 0.2), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Build Charge:</strong></td>
                            <td class="text-right">£<?php echo number_format($quote['build_charge'], 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Total (Inc. VAT):</strong></td>
                            <td class="text-right"><strong>£<?php echo number_format($quote['total_price'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quote Details Section - Without Prices -->
    <div class="card mb-4 show-without-prices">
        <div class="card-header">
            <strong>System Specification</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <?php 
                $itemCount = count($items);
                $halfCount = ceil($itemCount / 2);
                $column1Items = array_slice($items, 0, $halfCount);
                $column2Items = array_slice($items, $halfCount);
                ?>
                
                <div class="col-md-6">
                    <ul class="list-unstyled component-list">
                        <?php foreach ($column1Items as $item): ?>
                            <li class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                        <?php if ($item['product_sku']): ?>
                                            <br><small class="text-muted">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="quantity-badge">
                                        <?php echo $item['quantity']; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="col-md-6">
                    <ul class="list-unstyled component-list">
                        <?php foreach ($column2Items as $item): ?>
                            <li class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                        <?php if ($item['product_sku']): ?>
                                            <br><small class="text-muted">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="quantity-badge">
                                        <?php echo $item['quantity']; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12 text-right">
                    <h4 class="total-price mb-0">Total Price (Inc. VAT): £<?php echo number_format($quote['total_price'], 2); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Quote Information Section -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Quote Information</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Status:</strong> <?php echo ucfirst($quote['status']); ?></p>
                    <!--<p><strong>Type:</strong> <?php echo $quote['price_type'] == 'R' ? 'R' : 'T'; ?></p>-->
                    <p><strong>Created By:</strong> <?php echo htmlspecialchars($quote['created_by_name']); ?></p>
                    <p><strong>Created Date:</strong> <?php echo date('d/m/Y H:i', strtotime($quote['date_created'])); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Last Modified By:</strong> <?php echo htmlspecialchars($quote['modified_by_name']); ?></p>
                    <p><strong>Last Modified Date:</strong> <?php echo date('d/m/Y H:i', strtotime($quote['date_modified'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printWithoutPrices() {
    document.body.classList.add('hide-prices');
    window.print();
    document.body.classList.remove('hide-prices');
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

<style>
/* General print styles */
@media print {
    .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
    }
    .card-header {
        background: none !important;
        border-bottom: 1px solid #ddd !important;
    }
}

/* Styles for price display control */
.show-without-prices {
    display: none;
}

body.hide-prices .show-with-prices {
    display: none !important;
}

body.hide-prices .show-without-prices {
    display: block !important;
}

/* Component list styling */
.component-list li {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.component-list li:last-child {
    border-bottom: none;
}

.quantity-badge {
    background-color: #f8f9fa;
    padding: 4px 12px;
    border-radius: 15px;
    font-weight: bold;
    color: #495057;
}

/* Total price styling */
.total-price {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
    display: inline-block;
}

@media print {
    .quantity-badge {
        background-color: transparent !important;
        border: 1px solid #ddd;
    }
    
    .total-price {
        background-color: transparent !important;
        border: 2px solid #000;
        padding: 10px 20px;
    }
}
</style>

<?php require 'assets/footer.php'; ?>