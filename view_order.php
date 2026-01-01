<?php
session_start();
$page_title = 'View Order';
require 'php/bootstrap.php';

// Ensure session is active
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) {
    header('Location: orders.php');
    exit;
}

// Fetch order details
$order = $DB->query("
    SELECT o.*, 
           u.username as created_by_name,
           u2.username as modified_by_name
    FROM system_orders o
    LEFT JOIN users u ON o.created_by = u.id
    LEFT JOIN users u2 ON o.modified_by = u2.id
    WHERE o.id = ?
", [$order_id]);

if (empty($order)) {
    header('Location: orders.php');
    exit;
}
$order = $order[0];

// Fetch quote details
$quote = $DB->query("
    SELECT q.*, 
           u.username as quote_created_by
    FROM quotation_master q
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ?
", [$order['quote_id']])[0];

// Fetch quote items
$items = $DB->query("
    SELECT * 
    FROM quotation_items 
    WHERE quote_id = ?
    ORDER BY line_order
", [$order['quote_id']]);

// Fetch order comments
$comments = $DB->query("
    SELECT c.*, u.username, u.first_name, u.last_name
    FROM system_order_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.order_id = ?
    ORDER BY c.date_created DESC
", [$order_id]);

require 'assets/header.php';
require 'assets/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Order Header -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-box"></i> Order #<?php echo $order_id; ?>
                        <span class="badge badge-<?php 
                            echo $order['status'] == 'completed' ? 'success' : 
                                ($order['status'] == 'in_progress' ? 'warning' : 
                                ($order['status'] == 'cancelled' ? 'danger' : 'secondary')); 
                        ?> ml-2">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </h4>
                    <div class="btn-group no-print">
                        <a href="view_quote.php?id=<?php echo $order['quote_id']; ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-file-invoice"></i> View Quote
                        </a>
                        <button onclick="window.print()" class="btn btn-light btn-sm">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <a href="orders.php" class="btn btn-light btn-sm">
                            <i class="fas fa-list"></i> All Orders
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p><strong>Quote Reference:</strong><br>#<?php echo $order['quote_id']; ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Due Date:</strong><br>
                                <?php echo $order['due_date'] ? date('d/m/Y', strtotime($order['due_date'])) : 'Not set'; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Priority Level:</strong><br>
                                <span class="badge badge-<?php 
                                    echo $order['order_level'] >= 2 ? 'danger' : 
                                        ($order['order_level'] == 1 ? 'warning' : 'info'); 
                                ?>">
                                    <?php echo $order['order_level'] == 0 ? 'Normal' : 
                                        ($order['order_level'] == 1 ? 'High' : 'Urgent'); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p><strong>Created:</strong> <?php echo date('d/m/Y H:i', strtotime($order['date_created'])); ?></p>
                            <p><strong>Created By:</strong> <?php echo htmlspecialchars($order['created_by_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Last Modified:</strong> <?php echo date('d/m/Y H:i', strtotime($order['date_modified'])); ?></p>
                            <p><strong>Modified By:</strong> <?php echo htmlspecialchars($order['modified_by_name']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-user"></i> Customer Details</strong>
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

            <!-- Build Components -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-microchip"></i> Build Components</strong>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Component</th>
                                    <th>Description</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['product_sku']): ?>
                                                <span class="badge badge-secondary">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Manual Entry</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-right">£<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="text-right">£<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-active">
                                    <td colspan="4" class="text-right"><strong>Build Charge:</strong></td>
                                    <td class="text-right"><strong>£<?php echo number_format($quote['build_charge'], 2); ?></strong></td>
                                </tr>
                                <tr class="table-success">
                                    <td colspan="4" class="text-right"><strong>Total (Inc. VAT):</strong></td>
                                    <td class="text-right"><strong>£<?php echo number_format($quote['total_price'], 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Status Management -->
            <div class="card mb-4 no-print">
                <div class="card-header bg-info text-white">
                    <strong><i class="fas fa-tasks"></i> Order Management</strong>
                </div>
                <div class="card-body">
                    <?php if ($order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                        <div class="form-group">
                            <label for="orderStatus"><strong>Update Status:</strong></label>
                            <select class="form-control" id="orderStatus">
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $order['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dueDate"><strong>Due Date:</strong></label>
                            <input type="date" class="form-control" id="dueDate" value="<?php echo $order['due_date']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="priorityLevel"><strong>Priority Level:</strong></label>
                            <select class="form-control" id="priorityLevel">
                                <option value="0" <?php echo $order['order_level'] == 0 ? 'selected' : ''; ?>>Normal</option>
                                <option value="1" <?php echo $order['order_level'] == 1 ? 'selected' : ''; ?>>High</option>
                                <option value="2" <?php echo $order['order_level'] == 2 ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <button onclick="updateOrder()" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Update Order
                        </button>
                    <?php else: ?>
                        <div class="alert alert-info">
                            This order is <?php echo $order['status']; ?> and cannot be modified.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Comment -->
            <div class="card mb-4 no-print">
                <div class="card-header bg-secondary text-white">
                    <strong><i class="fas fa-comment"></i> Add Comment</strong>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <textarea class="form-control" id="newComment" rows="3" placeholder="Add a comment or update..."></textarea>
                    </div>
                    <button onclick="addComment()" class="btn btn-secondary btn-block">
                        <i class="fas fa-plus"></i> Add Comment
                    </button>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-history"></i> Order Timeline</strong>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($comments)): ?>
                        <p class="text-muted">No comments yet.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($comments as $comment): ?>
                                <div class="timeline-item mb-3">
                                    <div class="timeline-marker">
                                        <i class="fas fa-circle text-primary"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between">
                                            <strong>
                                                <?php 
                                                    $name = trim($comment['first_name'] . ' ' . $comment['last_name']);
                                                    echo htmlspecialchars($name ?: $comment['username']); 
                                                ?>
                                            </strong>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($comment['date_created'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateOrder() {
    const status = document.getElementById('orderStatus').value;
    const dueDate = document.getElementById('dueDate').value;
    const priorityLevel = document.getElementById('priorityLevel').value;

    $.ajax({
        url: 'ajax/update_order.php',
        method: 'POST',
        data: {
            orderId: <?php echo $order_id; ?>,
            status: status,
            dueDate: dueDate,
            priorityLevel: priorityLevel
        },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    Swal.fire({
                        title: 'Success',
                        text: 'Order updated successfully',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(result.message || 'Failed to update order');
                }
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to update order', 'error');
        }
    });
}

function addComment() {
    const comment = document.getElementById('newComment').value.trim();
    
    if (!comment) {
        Swal.fire('Error', 'Please enter a comment', 'error');
        return;
    }

    $.ajax({
        url: 'ajax/add_order_comment.php',
        method: 'POST',
        data: {
            orderId: <?php echo $order_id; ?>,
            comment: comment
        },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    Swal.fire({
                        title: 'Success',
                        text: 'Comment added successfully',
                        icon: 'success',
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(result.message || 'Failed to add comment');
                }
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to add comment', 'error');
        }
    });
}
</script>

<style>
/* Timeline styles */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
    border-left: 2px solid #e9ecef;
}

.timeline-item:last-child {
    border-left: 2px solid transparent;
}

.timeline-marker {
    position: absolute;
    left: -31px;
    top: 5px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
}

.timeline-marker i {
    font-size: 10px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    .col-lg-8 {
        width: 100% !important;
        max-width: 100% !important;
    }
    .card {
        border: none !important;
        page-break-inside: avoid;
    }
    .card-header {
        background: none !important;
        border-bottom: 2px solid #000 !important;
        color: #000 !important;
    }
}

/* Badge styles */
.badge {
    font-size: 0.85em;
    padding: 0.35em 0.65em;
}

/* Card enhancements */
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}

.card-header {
    font-weight: 600;
}

/* Table enhancements */
.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

/* Alert enhancements */
.alert {
    border-radius: 0.5rem;
}
</style>

<?php require 'assets/footer.php'; ?>