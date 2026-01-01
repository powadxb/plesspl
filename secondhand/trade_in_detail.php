<?php
session_start();
require_once '../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id'])){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check permissions from database
$manage_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'",
    [$user_id]
);
$can_manage = !empty($manage_check) && $manage_check[0]['has_access'];

if (!$can_manage) {
    die("Access denied");
}

$trade_in_id = $_GET['id'] ?? 0;

// Get trade-in details
$trade_in = $DB->query("
    SELECT ti.*, u.username as created_by_name
    FROM trade_in_items ti
    LEFT JOIN users u ON ti.created_by = u.id
    WHERE ti.id = ?
", [$trade_in_id])[0] ?? null;

if(!$trade_in) {
    die("Trade-in not found");
}

// Get items
$items = $DB->query("
    SELECT * FROM trade_in_items_details
    WHERE trade_in_id = ?
    ORDER BY id
", [$trade_in_id]);

// Get ID photos
$id_photos = $DB->query("
    SELECT * FROM trade_in_id_photos
    WHERE trade_in_id = ?
", [$trade_in_id]);

// Get item photos
$item_photos = $DB->query("
    SELECT * FROM trade_in_item_photos
    WHERE trade_in_item_id IN (SELECT id FROM trade_in_items_details WHERE trade_in_id = ?)
", [$trade_in_id]);

// Group photos by item
$photos_by_item = [];
foreach($item_photos as $photo) {
    $photos_by_item[$photo['trade_in_item_id']][] = $photo;
}

// Get signed document
$signed_doc = $DB->query("
    SELECT * FROM trade_in_signatures
    WHERE trade_in_id = ?
    ORDER BY created_at DESC
    LIMIT 1
", [$trade_in_id])[0] ?? null;

$status_labels = [
    'pending' => 'Pending Testing',
    'testing' => 'Testing In Progress',
    'accepted' => 'Accepted - Awaiting Customer',
    'rejected' => 'Rejected',
    'customer_withdrew' => 'Customer Withdrew',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];

$status_colors = [
    'pending' => 'warning',
    'testing' => 'info',
    'accepted' => 'success',
    'rejected' => 'danger',
    'customer_withdrew' => 'secondary',
    'completed' => 'primary',
    'cancelled' => 'secondary'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade-In #<?=$trade_in['id']?> - Priceless Computing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .status-timeline {
            position: relative;
            padding-left: 30px;
        }
        .status-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-dot {
            position: absolute;
            left: -24px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid;
        }
        .timeline-dot.active {
            background: white;
        }
        .timeline-dot.inactive {
            background: #dee2e6;
            border-color: #dee2e6 !important;
        }
        .item-photo-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 4px;
        }
        .id-photo-thumb {
            width: 150px;
            height: auto;
            cursor: pointer;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="fas fa-exchange-alt"></i> Trade-In #<?=$trade_in['id']?>
                    <span class="badge bg-<?=$status_colors[$trade_in['status']]?>">
                        <?=$status_labels[$trade_in['status']]?>
                    </span>
                </h2>
                <p class="text-muted">
                    Reference: <?=htmlspecialchars($trade_in['trade_in_reference'])?> | 
                    Created: <?=date('d/m/Y H:i', strtotime($trade_in['created_at']))?> by <?=htmlspecialchars($trade_in['created_by_name'])?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="trade_in_management.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <?php if($trade_in['status'] === 'completed'): ?>
        <div class="alert alert-warning mb-3">
            <i class="fas fa-lock"></i> <strong>This trade-in is completed and locked.</strong> 
            No changes can be made to completed trade-ins. Items have been transferred to secondhand inventory.
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content -->
            <div class="col-md-8">
                <!-- Customer Information -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-user"></i> Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?=htmlspecialchars($trade_in['customer_name'])?></p>
                                <p><strong>Phone:</strong> <?=htmlspecialchars($trade_in['customer_phone'] ?: 'N/A')?></p>
                                <p><strong>Email:</strong> <?=htmlspecialchars($trade_in['customer_email'] ?: 'N/A')?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Address:</strong> <?=htmlspecialchars($trade_in['customer_address'] ?: 'N/A')?></p>
                                <p><strong>Location:</strong> <?=$trade_in['location'] === 'cs' ? 'Commerce Street' : 'Argyle Street'?></p>
                                <p><strong>ID Verified:</strong> <span class="badge bg-success">Yes (<?=count($id_photos)?> documents)</span></p>
                            </div>
                        </div>
                        <?php if($trade_in['compliance_notes']): ?>
                        <div class="alert alert-info mt-2 mb-0">
                            <strong>Compliance Notes:</strong> <?=nl2br(htmlspecialchars($trade_in['compliance_notes']))?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Items -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-box"></i> Items (<?=count($items)?>)</h5>
                        <?php if($trade_in['status'] === 'pending' || $trade_in['status'] === 'testing'): ?>
                        <button class="btn btn-sm btn-primary" onclick="startTesting()">
                            <i class="fas fa-vial"></i> Start Testing All
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php 
                        $accepted_total = 0;
                        $rejected_count = 0;
                        foreach($items as $index => $item): 
                            $item_status = $item['item_status'] ?? 'pending_test';
                            $is_accepted = in_array($item_status, ['accepted', 'price_revised']);
                            $is_rejected = in_array($item_status, ['rejected', 'customer_kept']);
                            
                            if($is_accepted) {
                                $accepted_total += $item['price_paid'];
                            }
                            if($is_rejected) {
                                $rejected_count++;
                            }
                            
                            $status_badges = [
                                'pending_test' => '<span class="badge bg-warning text-dark">Pending Test</span>',
                                'testing' => '<span class="badge bg-info">Testing</span>',
                                'accepted' => '<span class="badge bg-success">Accepted</span>',
                                'price_revised' => '<span class="badge bg-primary">Price Revised</span>',
                                'rejected' => '<span class="badge bg-danger">Rejected</span>',
                                'customer_kept' => '<span class="badge bg-secondary">Customer Kept</span>'
                            ];
                        ?>
                        <div class="card mb-2 item-card" id="item<?=$item['id']?>" data-item-id="<?=$item['id']?>">
                            <div class="card-header" style="cursor: pointer;" onclick="toggleItemDetails(<?=$item['id']?>)">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <strong><?=htmlspecialchars($item['item_name'])?></strong>
                                        <?php if($item['preprinted_code']): ?>
                                        <span class="badge bg-warning text-dark ms-2" style="font-size: 1em; padding: 0.4em 0.6em;">
                                            <i class="fas fa-tag"></i> <?=htmlspecialchars($item['preprinted_code'])?>
                                        </span>
                                        <?php elseif($item['tracking_code']): ?>
                                        <span class="badge bg-secondary ms-2"><?=htmlspecialchars($item['tracking_code'])?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <?=$status_badges[$item_status]?>
                                        <?php if($item['condition']): ?>
                                        <span class="badge bg-info ms-1"><?=ucfirst($item['condition'])?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <?php if($item['original_price'] && abs($item['original_price'] - $item['price_paid']) > 0.01): ?>
                                        <small class="text-muted"><del>£<?=number_format($item['original_price'], 2)?></del></small><br>
                                        <?php endif; ?>
                                        <strong class="<?=$is_rejected ? 'text-danger' : ''?>">
                                            £<?=number_format($item['price_paid'], 2)?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body item-details" id="itemDetails<?=$item['id']?>" style="display: none;">
                                <div class="row">
                                    <div class="col-md-8">
                                        <!-- Item Codes -->
                                        <div class="mb-2">
                                            <?php if($item['preprinted_code']): ?>
                                            <span class="badge bg-warning text-dark" style="font-size: 0.95em;">
                                                <i class="fas fa-tag"></i> Pre-printed: <?=htmlspecialchars($item['preprinted_code'])?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if($item['tracking_code']): ?>
                                            <span class="badge bg-secondary" style="font-size: 0.95em;">
                                                <i class="fas fa-barcode"></i> Tracking: <?=htmlspecialchars($item['tracking_code'])?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if($item['category']): ?>
                                        <p class="mb-1"><strong>Category:</strong> <?=htmlspecialchars($item['category'])?></p>
                                        <?php endif; ?>
                                        <?php if($item['serial_number']): ?>
                                        <p class="mb-1"><strong>Serial:</strong> <?=htmlspecialchars($item['serial_number'])?></p>
                                        <?php endif; ?>
                                        <?php if($item['notes']): ?>
                                        <p class="mb-1"><strong>Initial Notes:</strong> <?=htmlspecialchars($item['notes'])?></p>
                                        <?php endif; ?>
                                        
                                        <?php if(isset($photos_by_item[$item['id']])): ?>
                                        <div class="mt-2 mb-3">
                                            <strong>Photos:</strong><br>
                                            <?php foreach($photos_by_item[$item['id']] as $photo): ?>
                                            <img src="<?=htmlspecialchars($photo['file_path'])?>" 
                                                 class="item-photo-thumb me-1 mb-1" 
                                                 onclick="viewImage('<?=htmlspecialchars($photo['file_path'])?>')" 
                                                 alt="Item Photo">
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if($trade_in['status'] === 'testing' || $trade_in['status'] === 'pending'): ?>
                                        <hr>
                                        <h6>Testing</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">Condition</label>
                                                <select class="form-select form-select-sm item-condition" data-item-id="<?=$item['id']?>">
                                                    <option value="">Not Set</option>
                                                    <option value="excellent" <?=$item['condition'] === 'excellent' ? 'selected' : ''?>>Excellent</option>
                                                    <option value="good" <?=$item['condition'] === 'good' ? 'selected' : ''?>>Good</option>
                                                    <option value="fair" <?=$item['condition'] === 'fair' ? 'selected' : ''?>>Fair</option>
                                                    <option value="poor" <?=$item['condition'] === 'poor' ? 'selected' : ''?>>Poor</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Revised Price (£)</label>
                                                <input type="number" class="form-control form-control-sm item-revised-price" 
                                                       data-item-id="<?=$item['id']?>" 
                                                       data-original="<?=$item['original_price'] ?? $item['price_paid']?>"
                                                       step="0.01" 
                                                       value="<?=$item['price_paid']?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Status</label>
                                                <select class="form-select form-select-sm item-status-select" data-item-id="<?=$item['id']?>">
                                                    <option value="testing" <?=$item_status === 'testing' ? 'selected' : ''?>>Testing</option>
                                                    <option value="accepted" <?=$item_status === 'accepted' ? 'selected' : ''?>>Accept</option>
                                                    <option value="rejected" <?=$item_status === 'rejected' ? 'selected' : ''?>>Reject</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <label class="form-label">Test Notes</label>
                                                <textarea class="form-control form-control-sm item-test-notes" 
                                                          data-item-id="<?=$item['id']?>" 
                                                          rows="2"><?=htmlspecialchars($item['test_notes'] ?? '')?></textarea>
                                            </div>
                                        </div>
                                        <div class="row mt-2" id="rejectionDiv<?=$item['id']?>" style="display: <?=$is_rejected ? 'block' : 'none'?>;">
                                            <div class="col-12">
                                                <label class="form-label">Rejection Reason</label>
                                                <textarea class="form-control form-control-sm item-rejection-reason" 
                                                          data-item-id="<?=$item['id']?>" 
                                                          rows="2"><?=htmlspecialchars($item['rejection_reason'] ?? '')?></textarea>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-success" onclick="saveItemTest(<?=$item['id']?>)">
                                                <i class="fas fa-save"></i> Save Item Test
                                            </button>
                                        </div>
                                        <?php else: ?>
                                        <?php if($item['test_notes']): ?>
                                        <div class="alert alert-info mt-2">
                                            <strong>Test Notes:</strong> <?=nl2br(htmlspecialchars($item['test_notes']))?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if($item['rejection_reason']): ?>
                                        <div class="alert alert-danger mt-2">
                                            <strong>Rejection Reason:</strong> <?=nl2br(htmlspecialchars($item['rejection_reason']))?>
                                        </div>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="alert alert-secondary mt-3 mb-0">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Accepted Items:</strong> <?=(count($items) - $rejected_count)?> of <?=count($items)?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <strong>Total Value:</strong> <h4 class="d-inline mb-0" id="currentTotal">£<?=number_format($trade_in['total_value'], 2)?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Actions & Status -->
            <div class="col-md-4">
                <!-- Status Management -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-tasks"></i> Status Management</h5>
                    </div>
                    <div class="card-body">
                        <form id="statusForm">
                            <div class="mb-3">
                                <label class="form-label">Current Status</label>
                                <select class="form-select" id="tradeInStatus" name="status" <?=$trade_in['status'] === 'completed' ? 'disabled' : ''?>>
                                    <option value="pending" <?=$trade_in['status'] === 'pending' ? 'selected' : ''?>>Pending Testing</option>
                                    <option value="testing" <?=$trade_in['status'] === 'testing' ? 'selected' : ''?>>Testing In Progress</option>
                                    <option value="accepted" <?=$trade_in['status'] === 'accepted' ? 'selected' : ''?>>Accepted</option>
                                    <option value="rejected" <?=$trade_in['status'] === 'rejected' ? 'selected' : ''?>>Rejected</option>
                                    <option value="customer_withdrew" <?=$trade_in['status'] === 'customer_withdrew' ? 'selected' : ''?>>Customer Withdrew</option>
                                    <option value="completed" <?=$trade_in['status'] === 'completed' ? 'selected' : ''?>>Completed</option>
                                    <option value="cancelled" <?=$trade_in['status'] === 'cancelled' ? 'selected' : ''?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="testNotesDiv" style="display: none;">
                                <label class="form-label">Test Notes</label>
                                <textarea class="form-control" id="testNotes" rows="3" <?=$trade_in['status'] === 'completed' ? 'readonly' : ''?>><?=htmlspecialchars($trade_in['test_notes'] ?? '')?></textarea>
                            </div>
                            
                            <div class="mb-3" id="rejectionReasonDiv" style="display: none;">
                                <label class="form-label">Rejection Reason</label>
                                <textarea class="form-control" id="rejectionReason" rows="3" <?=$trade_in['status'] === 'completed' ? 'readonly' : ''?>><?=htmlspecialchars($trade_in['rejection_reason'] ?? '')?></textarea>
                            </div>
                            
                            <?php if($trade_in['status'] !== 'completed'): ?>
                            <button type="button" class="btn btn-primary w-100" onclick="updateStatus()">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary w-100" disabled>
                                <i class="fas fa-lock"></i> Status Locked
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Payment Details (Only show for accepted status) -->
                <?php if($trade_in['status'] === 'accepted' || $trade_in['status'] === 'completed'): ?>
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-money-bill"></i> Payment Details</h5>
                    </div>
                    <div class="card-body">
                        <form id="paymentForm">
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-select" id="paymentMethod" name="payment_method" <?=$trade_in['status'] === 'completed' ? 'disabled' : ''?>>
                                    <option value="cash" <?=$trade_in['payment_method'] === 'cash' ? 'selected' : ''?>>Cash</option>
                                    <option value="bank_transfer" <?=$trade_in['payment_method'] === 'bank_transfer' ? 'selected' : ''?>>Bank Transfer</option>
                                    <option value="cash_bank" <?=$trade_in['payment_method'] === 'cash_bank' ? 'selected' : ''?>>Cash & Bank Transfer</option>
                                </select>
                            </div>
                            
                            <div id="cashAmountDiv" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Cash Amount (£)</label>
                                    <input type="number" class="form-control" id="cashAmount" step="0.01" value="<?=$trade_in['cash_amount']?>" <?=$trade_in['status'] === 'completed' ? 'readonly' : ''?>>
                                </div>
                            </div>
                            
                            <div id="bankAmountDiv" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Bank Amount (£)</label>
                                    <input type="number" class="form-control" id="bankAmount" step="0.01" value="<?=$trade_in['bank_amount']?>" <?=$trade_in['status'] === 'completed' ? 'readonly' : ''?>>
                                </div>
                            </div>
                            
                            <div id="bankDetailsDiv" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Account Name</label>
                                    <input type="text" class="form-control" id="bankAccountName" value="<?=htmlspecialchars($trade_in['bank_account_name'] ?? '')?>" <?=$trade_in['status'] === 'completed' ? 'readonly' : ''?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" class="form-control" id="bankAccountNumber" value="<?=htmlspecialchars($trade_in['bank_account_number'] ?? '')?>" <?=$trade_in['status'] === 'completed' ? 'readonly' : ''?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sort Code</label>
                                    <input type="text" class="form-control" id="bankSortCode" value="<?=htmlspecialchars($trade_in['bank_sort_code'] ?? '')?>" <?=$trade_in['status'] === 'completed' ? 'readonly' : ''?>>
                                </div>
                            </div>
                            
                            <?php if($trade_in['status'] !== 'completed'): ?>
                            <button type="button" class="btn btn-success w-100" onclick="updatePayment()">
                                <i class="fas fa-save"></i> Save Payment
                            </button>
                            <?php else: ?>
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle"></i> Payment Completed
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-bolt"></i> Actions</h5>
                    </div>
                    <div class="card-body">
                        <?php if($trade_in['status'] === 'accepted'): ?>
                            <button class="btn btn-primary w-100 mb-2" onclick="generatePDF()">
                                <i class="fas fa-file-pdf"></i> Generate Agreement PDF
                            </button>
                            
                            <hr>
                            
                            <h6>Upload Signed Document</h6>
                            <input type="file" class="form-control mb-2" id="signedDocument" accept="image/*">
                            <button class="btn btn-outline-primary w-100 mb-2" onclick="uploadSignedDoc()">
                                <i class="fas fa-upload"></i> Upload Document
                            </button>
                            
                            <?php if($signed_doc): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Document uploaded
                                <a href="<?=htmlspecialchars($signed_doc['file_path'])?>" target="_blank">View</a>
                            </div>
                            
                            <button class="btn btn-success w-100" onclick="completeTrade In()">
                                <i class="fas fa-check"></i> Complete Trade-In
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if($trade_in['status'] === 'completed'): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Trade-in completed
                                <br>Items moved to inventory
                            </div>
                            <a href="<?=htmlspecialchars($signed_doc['file_path'] ?? '#')?>" class="btn btn-outline-primary w-100" target="_blank">
                                <i class="fas fa-file-alt"></i> View Signed Document
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" style="max-width: 100%; height: auto;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const tradeInId = <?=$trade_in_id?>;
        const totalValue = <?=$trade_in['total_value']?>;
        
        // Toggle item details
        function toggleItemDetails(itemId) {
            $('#itemDetails' + itemId).slideToggle();
        }
        
        // Start testing all items
        function startTesting() {
            if(confirm('Set all items to "Testing" status?')) {
                $('.item-status-select').val('testing');
                updateStatus();
            }
        }
        
        // Save individual item test results
        function saveItemTest(itemId) {
            const data = {
                item_id: itemId,
                item_status: $('.item-status-select[data-item-id="' + itemId + '"]').val(),
                condition: $('.item-condition[data-item-id="' + itemId + '"]').val() || null,
                revised_price: $('.item-revised-price[data-item-id="' + itemId + '"]').val(),
                test_notes: $('.item-test-notes[data-item-id="' + itemId + '"]').val(),
                rejection_reason: $('.item-rejection-reason[data-item-id="' + itemId + '"]').val()
            };
            
            $.post('ajax/update_trade_in_item.php', data, function(response) {
                if(response.success) {
                    // Update the UI without reloading
                    const itemCard = $('#item' + itemId);
                    const itemData = response.item_data;
                    
                    // Update status badge
                    const statusBadges = {
                        'pending': '<span class="badge bg-warning text-dark">Pending Test</span>',
                        'accepted': '<span class="badge bg-success">Accepted</span>',
                        'rejected': '<span class="badge bg-danger">Rejected</span>',
                        'price_revised': '<span class="badge bg-primary">Price Revised</span>'
                    };
                    
                    // Update the badge in the header
                    itemCard.find('.col-md-3.text-center').first().html(
                        statusBadges[itemData.item_status] || itemData.item_status
                    );
                    
                    // Update price display
                    const priceClass = (itemData.item_status === 'rejected') ? 'text-danger' : '';
                    itemCard.find('.col-md-3.text-end strong').attr('class', priceClass).text('£' + itemData.price_paid.toFixed(2));
                    
                    // Update condition badge if exists
                    if(itemData.condition) {
                        const conditionBadge = '<span class="badge bg-info ms-1">' + 
                            itemData.condition.charAt(0).toUpperCase() + itemData.condition.slice(1) + 
                            '</span>';
                        itemCard.find('.col-md-3.text-center').first().append(conditionBadge);
                    }
                    
                    // Update total display
                    $('#currentTotal').text('£' + response.new_total.toFixed(2));
                    totalValue = response.new_total;
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Item Updated',
                        text: 'New total: £' + response.new_total.toFixed(2),
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', response.message || 'Failed to update item', 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('Error', 'Failed to update item', 'error');
            });
        }
        
        // Show/hide rejection reason based on status
        $(document).on('change', '.item-status-select', function() {
            const itemId = $(this).data('item-id');
            const status = $(this).val();
            const rejectionDiv = $('#rejectionDiv' + itemId);
            
            if(status === 'rejected') {
                rejectionDiv.show();
            } else {
                rejectionDiv.hide();
            }
        });
        
        // Highlight price changes
        $(document).on('change', '.item-revised-price', function() {
            const original = parseFloat($(this).data('original'));
            const revised = parseFloat($(this).val());
            
            if(Math.abs(original - revised) > 0.01) {
                $(this).addClass('border-warning');
            } else {
                $(this).removeClass('border-warning');
            }
        });
        
        // Show/hide sections based on status
        $('#tradeInStatus').on('change', function() {
            const status = $(this).val();
            
            $('#testNotesDiv').toggle(status === 'testing' || status === 'accepted' || status === 'rejected');
            $('#rejectionReasonDiv').toggle(status === 'rejected');
        }).trigger('change');
        
        // Payment method handling
        $('#paymentMethod').on('change', function() {
            const method = $(this).val();
            
            $('#cashAmountDiv').hide();
            $('#bankAmountDiv').hide();
            $('#bankDetailsDiv').hide();
            
            if(method === 'cash') {
                $('#cashAmount').val(totalValue.toFixed(2));
                $('#cashAmountDiv').show();
            } else if(method === 'bank_transfer') {
                $('#bankAmount').val(totalValue.toFixed(2));
                $('#bankAmountDiv').show();
                $('#bankDetailsDiv').show();
            } else if(method === 'cash_bank') {
                $('#cashAmount').val((totalValue / 2).toFixed(2));
                $('#bankAmount').val((totalValue / 2).toFixed(2));
                $('#cashAmountDiv').show();
                $('#bankAmountDiv').show();
                $('#bankDetailsDiv').show();
            }
        }).trigger('change');
        
        // Update status
        function updateStatus() {
            const data = {
                trade_in_id: tradeInId,
                status: $('#tradeInStatus').val(),
                test_notes: $('#testNotes').val(),
                rejection_reason: $('#rejectionReason').val()
            };
            
            $.post('ajax/update_trade_in_status.php', data, function(response) {
                if(response.success) {
                    Swal.fire('Success', 'Status updated successfully', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message || 'Failed to update status', 'error');
                }
            }, 'json');
        }
        
        // Update payment
        function updatePayment() {
            const data = {
                trade_in_id: tradeInId,
                payment_method: $('#paymentMethod').val(),
                cash_amount: $('#cashAmount').val() || 0,
                bank_amount: $('#bankAmount').val() || 0,
                bank_account_name: $('#bankAccountName').val(),
                bank_account_number: $('#bankAccountNumber').val(),
                bank_sort_code: $('#bankSortCode').val()
            };
            
            $.post('ajax/update_trade_in_payment.php', data, function(response) {
                if(response.success) {
                    Swal.fire('Success', 'Payment details saved', 'success');
                } else {
                    Swal.fire('Error', response.message || 'Failed to save payment', 'error');
                }
            }, 'json');
        }
        
        // Generate PDF
        function generatePDF() {
            window.open('ajax/generate_trade_in_pdf_alt.php?trade_in_id=' + tradeInId, '_blank');
        }
        
        // Upload signed document
        function uploadSignedDoc() {
            const file = $('#signedDocument')[0].files[0];
            if(!file) {
                Swal.fire('Error', 'Please select a file', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('trade_in_id', tradeInId);
            formData.append('signed_document', file);
            
            $.ajax({
                url: 'ajax/upload_signed_document.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if(response.success) {
                        const message = response.items_moved 
                            ? response.message + '<br><br><strong>' + response.items_moved + ' items transferred to SecondHand Inventory</strong>'
                            : response.message;
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Trade-In Completed!',
                            html: message,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Failed to upload', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Upload failed', 'error');
                }
            });
        }
        
        // Complete trade-in
        function completeTradeIn() {
            Swal.fire({
                title: 'Complete Trade-In?',
                text: 'This will move accepted items to inventory. Rejected items will stay in records only.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, complete it'
            }).then((result) => {
                if(result.isConfirmed) {
                    $.post('ajax/complete_trade_in.php', {trade_in_id: tradeInId}, function(response) {
                        if(response.success) {
                            Swal.fire('Completed!', 'Trade-in completed. ' + response.items_moved + ' items moved to inventory', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to complete', 'error');
                        }
                    }, 'json');
                }
            });
        }
        
        // View image in modal
        function viewImage(src) {
            $('#modalImage').attr('src', src);
            new bootstrap.Modal($('#imageModal')).show();
        }
    </script>
</body>
</html>
