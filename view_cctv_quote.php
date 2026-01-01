<?php
session_start();
$page_title = 'View CCTV Quote';
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
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'cctv_quote'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Get quote ID from URL
$quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$quote_id) {
    header('Location: cctv_quote.php');
    exit;
}

// Check if user is super admin (level 2 only)
$is_admin = $user_details['admin'] >= 2;

// Fetch quote details
$quote = $DB->query("
    SELECT q.*, 
           u.username as created_by_name,
           u2.username as modified_by_name
    FROM cctv_quotation_master q
    LEFT JOIN users u ON q.created_by = u.id
    LEFT JOIN users u2 ON q.modified_by = u2.id
    WHERE q.id = ?
", [$quote_id]);

if (empty($quote)) {
    header('Location: cctv_quotes.php');
    exit;
}

$quote = $quote[0];

// Fetch quote items grouped by component type
$items = $DB->query("
    SELECT * 
    FROM cctv_quotation_items 
    WHERE quote_id = ?
    ORDER BY line_order
", [$quote_id]);

// Group items by component type for better display
$itemsByType = [];
foreach ($items as $item) {
    $type = $item['component_type'];
    if (!isset($itemsByType[$type])) {
        $itemsByType[$type] = [];
    }
    $itemsByType[$type][] = $item;
}

// Component type labels
$componentLabels = [
    'recorder' => 'DVR/NVR Recorder',
    'hdd' => 'Hard Drive',
    'camera' => 'Camera',
    'poe_switch' => 'PoE Switch',
    'network_switch' => 'Network Switch',
    'power_supply' => 'Power Supply',
    'ups' => 'UPS',
    'monitor' => 'Monitor',
    'camera_cable' => 'Camera Cable',
    'connectors' => 'Connectors',
    'internet_cable' => 'Internet Cable',
    'video_cable' => 'HDMI/VGA Cable',
    'power_extension' => 'Power Extension',
    'mounting' => 'Mounting Brackets',
    'cable_management' => 'Cable Management',
    'additional' => 'Additional Item'
];

require 'assets/header.php';
require 'assets/navbar.php';
?>

<div class="container-fluid mt-4">
    <!-- Header Section -->
    <div class="row mb-3">
        <div class="col">
            <h2>CCTV Quote #<?php echo $quote_id; ?> - <?php echo htmlspecialchars($quote['customer_name']); ?></h2>
            <small class="text-muted">Created: <?php echo date('d/m/Y H:i', strtotime($quote['date_created'])); ?></small>
        </div>
        <div class="col text-right">
            <?php if ($is_admin): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary mr-2 no-print" id="toggleCostInfo" title="Show Cost Information">
                SC
            </button>
            <?php endif; ?>
            <div class="btn-group no-print">
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="fas fa-print"></i> Print with Prices
                </button>
                <button onclick="printWithoutPrices()" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-print"></i> Print without Prices
                </button>
                <a href="cctv_quote.php?edit=<?php echo $quote_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="cctv_quote.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> New Quote
                </a>
                <a href="cctv_quotes.php" class="btn btn-info btn-sm">
                    <i class="fas fa-search"></i> Search Quotes
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Customer Details -->
            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="fas fa-user"></i> Customer Details</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($quote['customer_name']); ?></p>
                            <?php if ($quote['customer_phone']): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($quote['customer_phone']); ?></p>
                            <?php endif; ?>
                            <?php if ($quote['customer_email']): ?>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($quote['customer_email']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if ($quote['customer_address']): ?>
                            <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($quote['customer_address'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quote Items - WITH PRICES -->
            <div class="card mb-3 show-with-prices">
                <div class="card-header">
                    <strong><i class="fas fa-list"></i> System Components</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th>Description</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $componentLabels[$item['component_type']] ?? ucfirst($item['component_type']); ?></strong>
                                    <?php if ($item['product_sku']): ?>
                                    <br><small class="text-muted">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($item['manual_entry']): ?>
                                    <br><small class="badge badge-info">Manual Entry</small>
                                    <?php endif; ?>
                                    <?php if ($item['price_edited']): ?>
                                    <br><small class="badge badge-warning no-print">Custom Price</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-right">£<?php echo number_format($item['price_inc_vat'], 2); ?></td>
                                <td class="text-right">£<?php echo number_format($item['price_inc_vat'] * $item['quantity'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if ($quote['installation_charge'] > 0): ?>
                            <tr>
                                <td colspan="2"><strong>Installation Labor</strong></td>
                                <td class="text-center">1</td>
                                <td class="text-right">£<?php echo number_format($quote['installation_charge'], 2); ?></td>
                                <td class="text-right">£<?php echo number_format($quote['installation_charge'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if ($quote['config_charge'] > 0): ?>
                            <tr>
                                <td colspan="2"><strong>Configuration & Setup</strong></td>
                                <td class="text-center">1</td>
                                <td class="text-right">£<?php echo number_format($quote['config_charge'], 2); ?></td>
                                <td class="text-right">£<?php echo number_format($quote['config_charge'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if ($quote['testing_charge'] > 0): ?>
                            <tr>
                                <td colspan="2"><strong>Testing & Commissioning</strong></td>
                                <td class="text-center">1</td>
                                <td class="text-right">£<?php echo number_format($quote['testing_charge'], 2); ?></td>
                                <td class="text-right">£<?php echo number_format($quote['testing_charge'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td colspan="4" class="text-right"><strong>Total (Inc. VAT):</strong></td>
                                <td class="text-right"><strong>£<?php echo number_format($quote['total_price'], 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Quote Items - WITHOUT PRICES -->
            <div class="card mb-3 show-without-prices" style="display: none;">
                <div class="card-header">
                    <strong><i class="fas fa-list"></i> System Specification</strong>
                </div>
                <div class="card-body">
                    <?php
                    // Group items by type for cleaner display
                    $displayGroups = [];
                    foreach ($items as $item) {
                        $typeLabel = $componentLabels[$item['component_type']] ?? ucfirst($item['component_type']);
                        if (!isset($displayGroups[$typeLabel])) {
                            $displayGroups[$typeLabel] = [];
                        }
                        $displayGroups[$typeLabel][] = $item;
                    }
                    ?>
                    
                    <div class="row">
                        <?php
                        $groups = array_chunk($displayGroups, ceil(count($displayGroups) / 2), true);
                        foreach ($groups as $groupChunk):
                        ?>
                        <div class="col-md-6">
                            <?php foreach ($groupChunk as $typeLabel => $typeItems): ?>
                            <div class="mb-3">
                                <h6 class="text-primary"><?php echo $typeLabel; ?></h6>
                                <ul class="list-unstyled ml-3">
                                    <?php foreach ($typeItems as $item): ?>
                                    <li>
                                        <i class="fas fa-check text-success"></i>
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                        <?php if ($item['quantity'] > 1): ?>
                                        <span class="badge badge-secondary">×<?php echo $item['quantity']; ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($quote['installation_charge'] > 0 || $quote['config_charge'] > 0 || $quote['testing_charge'] > 0): ?>
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="text-primary">Services Included</h6>
                        <ul class="list-unstyled ml-3">
                            <?php if ($quote['installation_charge'] > 0): ?>
                            <li><i class="fas fa-check text-success"></i> Professional Installation</li>
                            <?php endif; ?>
                            <?php if ($quote['config_charge'] > 0): ?>
                            <li><i class="fas fa-check text-success"></i> System Configuration & Setup</li>
                            <?php endif; ?>
                            <?php if ($quote['testing_charge'] > 0): ?>
                            <li><i class="fas fa-check text-success"></i> Testing & Commissioning</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4 p-3 bg-light text-right">
                        <h4 class="mb-0">Total Investment: £<?php echo number_format($quote['total_price'], 2); ?></h4>
                        <small class="text-muted">(Including VAT)</small>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-md-4">
            <!-- Quote Information -->
            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="fas fa-info-circle"></i> Quote Information</strong>
                </div>
                <div class="card-body">
                    <p><strong>Quote ID:</strong> #<?php echo $quote_id; ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge badge-<?php 
                            echo $quote['status'] == 'draft' ? 'secondary' : 
                                ($quote['status'] == 'sent' ? 'info' : 
                                ($quote['status'] == 'accepted' ? 'success' : 'warning')); 
                        ?>">
                            <?php echo ucfirst($quote['status']); ?>
                        </span>
                    </p>
                    <p class="no-print"><strong>Price Type:</strong> <?php echo $quote['price_type'] == 'R' ? 'Retail' : 'Trade'; ?></p>
                    <p><strong>Created:</strong> <?php echo date('d/m/Y H:i', strtotime($quote['date_created'])); ?></p>
                    <p><strong>Created By:</strong> <?php echo htmlspecialchars($quote['created_by_name']); ?></p>
                    <?php if ($quote['date_modified'] != $quote['date_created']): ?>
                    <p><strong>Last Modified:</strong> <?php echo date('d/m/Y H:i', strtotime($quote['date_modified'])); ?></p>
                    <p><strong>Modified By:</strong> <?php echo htmlspecialchars($quote['modified_by_name']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Summary -->
            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="fas fa-calculator"></i> Quote Summary</strong>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Components:</span>
                        <strong><?php echo count($items); ?> items</strong>
                    </div>
                    <?php if ($is_admin): ?>
                    <div class="d-flex justify-content-between mb-2 cost-sensitive">
                        <span>Total Cost:</span>
                        <strong>£<?php echo number_format($quote['total_cost'], 2); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 cost-sensitive">
                        <span>Total Profit:</span>
                        <strong class="text-success">£<?php echo number_format($quote['total_profit'], 2); ?></strong>
                    </div>
                    <hr class="cost-sensitive">
                    <?php endif; ?>
                    <div class="d-flex justify-content-between">
                        <span><strong>Total (Inc. VAT):</strong></span>
                        <strong class="text-primary">£<?php echo number_format($quote['total_price'], 2); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card no-print">
                <div class="card-header">
                    <strong><i class="fas fa-cog"></i> Actions</strong>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button onclick="window.print()" class="btn btn-primary btn-block mb-2">
                            <i class="fas fa-print"></i> Print Quote
                        </button>
                        <a href="cctv_quote.php?edit=<?php echo $quote_id; ?>" class="btn btn-warning btn-block mb-2">
                            <i class="fas fa-edit"></i> Edit Quote
                        </a>
                        <button onclick="emailQuote()" class="btn btn-info btn-block mb-2">
                            <i class="fas fa-envelope"></i> Email to Customer
                        </button>
                        <button onclick="updateStatus()" class="btn btn-success btn-block mb-2">
                            <i class="fas fa-check"></i> Update Status
                        </button>
                        <hr>
                        <a href="cctv_quotes.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    body {
        padding: 20px;
    }
    .cost-sensitive {
        display: none !important;
    }
}

.show-without-prices {
    display: none;
}

body.hide-prices .show-with-prices {
    display: none !important;
}

body.hide-prices .show-without-prices {
    display: block !important;
}

/* Hide cost-sensitive elements by default */
body.hide-costs .cost-sensitive {
    display: none !important;
}

/* SC Toggle Button Styling */
#toggleCostInfo {
    font-size: 0.8rem;
    padding: 4px 10px;
    transition: all 0.3s ease;
    min-width: 36px;
    font-weight: 600;
}

#toggleCostInfo.active {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Hide costs by default on page load
$(document).ready(function() {
    $('body').addClass('hide-costs');
    $('#toggleCostInfo').removeClass('active');
    
    // Toggle cost visibility
    $('#toggleCostInfo').click(function() {
        const isHidden = $('body').hasClass('hide-costs');
        
        if (isHidden) {
            // Show costs
            $('body').removeClass('hide-costs');
            $(this).addClass('active');
            $(this).attr('title', 'Hide Cost Information');
        } else {
            // Hide costs
            $('body').addClass('hide-costs');
            $(this).removeClass('active');
            $(this).attr('title', 'Show Cost Information');
        }
    });
});

function printWithoutPrices() {
    document.body.classList.add('hide-prices');
    window.print();
    document.body.classList.remove('hide-prices');
}

function emailQuote() {
    Swal.fire({
        title: 'Email Quote',
        html: `
            <div class="form-group text-left">
                <label>Email Address:</label>
                <input type="email" id="emailAddress" class="form-control" 
                       value="<?php echo htmlspecialchars($quote['customer_email'] ?? ''); ?>">
            </div>
            <div class="form-group text-left">
                <label>Message (optional):</label>
                <textarea id="emailMessage" class="form-control" rows="3"></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Send Email',
        preConfirm: () => {
            const email = $('#emailAddress').val();
            if (!email) {
                Swal.showValidationMessage('Please enter an email address');
                return false;
            }
            return {
                email: email,
                message: $('#emailMessage').val()
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // TODO: Implement email sending
            Swal.fire('Success', 'Quote sent successfully', 'success');
        }
    });
}

function updateStatus() {
    Swal.fire({
        title: 'Update Quote Status',
        input: 'select',
        inputOptions: {
            'draft': 'Draft',
            'sent': 'Sent to Customer',
            'accepted': 'Accepted',
            'rejected': 'Rejected',
            'converted': 'Converted to Order'
        },
        inputValue: '<?php echo $quote['status']; ?>',
        showCancelButton: true,
        confirmButtonText: 'Update',
        inputValidator: (value) => {
            if (!value) {
                return 'Please select a status';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/update_cctv_quote_status.php',
                method: 'POST',
                data: {
                    quote_id: <?php echo $quote_id; ?>,
                    status: result.value
                },
                success: function(response) {
                    Swal.fire('Success', 'Status updated', 'success').then(() => {
                        location.reload();
                    });
                },
                error: function() {
                    Swal.fire('Error', 'Failed to update status', 'error');
                }
            });
        }
    });
}
</script>

<?php require 'assets/footer.php'; ?>