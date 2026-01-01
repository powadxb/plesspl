<?php
require __DIR__.'/../../../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo "Not authenticated";
    exit();
}

// Check if rma-permissions.php exists and load it
$permissions_file = __DIR__.'/../rma-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
} else {
    // Fallback: define functions inline if file doesn't exist
    if (!function_exists('canViewSupplierData')) {
        function canViewSupplierData($user_id, $DB) {
            try {
                $result = $DB->query("SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Supplier'", [$user_id]);
                return !empty($result) && $result[0]['has_access'] == 1;
            } catch (Exception $e) {
                return false;
            }
        }
    }
    if (!function_exists('canViewFinancialData')) {
        function canViewFinancialData($user_id, $DB) {
            try {
                $result = $DB->query("SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Financial'", [$user_id]);
                return !empty($result) && $result[0]['has_access'] == 1;
            } catch (Exception $e) {
                return false;
            }
        }
    }
}

// Get user details
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];
$is_authorized = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);

// Check specific permissions
$can_view_supplier = false;
$can_view_financial = false;

try {
    $can_view_supplier = canViewSupplierData($user_id, $DB);
    $can_view_financial = canViewFinancialData($user_id, $DB);
} catch (Exception $e) {
    // Continue with no permissions
}

$rma_id = $_POST['rma_id'] ?? '';

if(empty($rma_id)) {
    echo "RMA ID required";
    exit();
}

try {
    // Get RMA details
    $rma = $DB->query("
        SELECT 
            r.*,
            ft.fault_name,
            CONCAT(u.first_name, ' ', u.last_name) AS created_by_name,
            DATEDIFF(CURDATE(), r.date_discovered) AS days_open
        FROM rma_items r
        INNER JOIN rma_fault_types ft ON r.fault_type_id = ft.id
        INNER JOIN users u ON r.created_by = u.id
        WHERE r.id = ?
        LIMIT 1
    ", [$rma_id]);

    if(empty($rma)) {
        echo "RMA not found";
        exit();
    }

    $rma = $rma[0];

    // Check if user has permission to view this RMA (location check)
    if(!$is_authorized) {
        $effective_location = $user_details['user_location'];
        if(!empty($user_details['temp_location']) && 
           !empty($user_details['temp_location_expires']) && 
           strtotime($user_details['temp_location_expires']) > time()) {
            $effective_location = $user_details['temp_location'];
        }

        if($rma['location'] !== $effective_location) {
            echo "Access denied - RMA is at different location";
            exit();
        }
    }

    // Render details
    $location_name = ($rma['location'] === 'cs') ? 'Commerce Street' : 'Argyle Street';
    $status_labels = [
        'unprocessed' => 'Unprocessed',
        'rma_number_issued' => 'RMA Number Issued',
        'applied_for' => 'Applied For',
        'sent' => 'Sent to Supplier',
        'credited' => 'Credited',
        'exchanged' => 'Exchanged',
        'rejected' => 'Rejected'
    ];
    $status_label = $status_labels[$rma['status']] ?? ucfirst($rma['status']);
    
    // Status badge colors
    $status_colors = [
        'unprocessed' => 'danger',
        'rma_number_issued' => 'warning',
        'applied_for' => 'primary',
        'sent' => 'info',
        'credited' => 'success',
        'exchanged' => 'success',
        'rejected' => 'secondary'
    ];
    $status_color = $status_colors[$rma['status']] ?? 'secondary';

    ?>
    
    <style>
    .rma-detail-card {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 15px;
        border-left: 4px solid #007bff;
    }
    
    .rma-detail-card.financial {
        border-left-color: #28a745;
    }
    
    .rma-detail-card.supplier {
        border-left-color: #17a2b8;
    }
    
    .rma-detail-card.timeline {
        border-left-color: #6c757d;
    }
    
    .rma-detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .rma-detail-row:last-child {
        border-bottom: none;
    }
    
    .rma-detail-label {
        font-weight: 600;
        color: #495057;
        min-width: 150px;
        flex-shrink: 0;
    }
    
    .rma-detail-value {
        color: #212529;
        word-break: break-word;
        flex-grow: 1;
    }
    
    .section-title {
        font-size: 1.1em;
        font-weight: 600;
        color: #343a40;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
    }
    
    .section-title i {
        margin-right: 8px;
    }
    </style>
    
    <!-- Product Information -->
    <div class="rma-detail-card">
        <div class="section-title">
            <i class="fas fa-box"></i> Product Information
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">RMA ID:</div>
            <div class="rma-detail-value"><strong>#<?=htmlspecialchars($rma['id'])?></strong></div>
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">Barcode:</div>
            <div class="rma-detail-value"><?=htmlspecialchars($rma['barcode'] ?: 'N/A')?></div>
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">Tracking Number:</div>
            <div class="rma-detail-value"><?=htmlspecialchars($rma['tracking_number'] ?: 'N/A')?></div>
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">Serial Number:</div>
            <div class="rma-detail-value"><?=htmlspecialchars($rma['serial_number'] ?: 'N/A')?></div>
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">SKU:</div>
            <div class="rma-detail-value"><strong><?=htmlspecialchars($rma['sku'])?></strong></div>
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">Product Name:</div>
            <div class="rma-detail-value"><?=htmlspecialchars($rma['product_name'])?></div>
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">EAN:</div>
            <div class="rma-detail-value"><?=htmlspecialchars($rma['ean'] ?: 'N/A')?></div>
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">Location:</div>
            <div class="rma-detail-value">
                <span class="badge badge-<?=($rma['location'] === 'cs') ? 'primary' : 'info'?>"><?=$location_name?></span>
            </div>
        </div>
    </div>
    
    <!-- Supplier Information (if authorized) -->
    <?php if($can_view_supplier): ?>
    <div class="rma-detail-card supplier">
        <div class="section-title">
            <i class="fas fa-truck"></i> Supplier Information
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">Supplier:</div>
            <div class="rma-detail-value">
                <?=htmlspecialchars($rma['supplier_name'] ?: 'Unknown')?>
                <?php if($rma['needs_review'] == 1): ?>
                <span class="badge badge-warning ml-2">Needs Review</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if(!empty($rma['document_number'])): ?>
        <div class="rma-detail-row">
            <div class="rma-detail-label">Document Number:</div>
            <div class="rma-detail-value"><?=htmlspecialchars($rma['document_number'])?></div>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($rma['document_date'])): ?>
        <div class="rma-detail-row">
            <div class="rma-detail-label">Document Date:</div>
            <div class="rma-detail-value"><?=htmlspecialchars($rma['document_date'])?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Financial Information (if authorized) -->
    <?php if($can_view_financial): ?>
    <div class="rma-detail-card financial">
        <div class="section-title">
            <i class="fas fa-pound-sign"></i> Financial Information
        </div>
        
        <div class="rma-detail-row">
            <div class="rma-detail-label">Cost at Creation:</div>
            <div class="rma-detail-value"><strong>£<?=number_format($rma['cost_at_creation'] ?: 0, 2)?></strong></div>
        </div>
        
        <?php if(!empty($rma['credited_amount'])): ?>
        <div class="rma-detail-row">
            <div class="rma-detail-label">Credited Amount:</div>
            <div class="rma-detail-value"><strong class="text-success">£<?=number_format($rma['credited_amount'], 2)?></strong></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <!-- Fault Information -->
            <div class="rma-detail-card">
                <div class="section-title">
                    <i class="fas fa-exclamation-circle"></i> Fault Details
                </div>
                
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Fault Type:</div>
                    <div class="rma-detail-value"><strong><?=htmlspecialchars($rma['fault_name'])?></strong></div>
                </div>
                
                <?php if(!empty($rma['fault_description'])): ?>
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Description:</div>
                    <div class="rma-detail-value"><?=nl2br(htmlspecialchars($rma['fault_description']))?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Status Information -->
            <div class="rma-detail-card">
                <div class="section-title">
                    <i class="fas fa-tasks"></i> Status
                </div>
                
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Current Status:</div>
                    <div class="rma-detail-value">
                        <span class="badge badge-<?=$status_color?> badge-lg" style="font-size: 1em; padding: 6px 12px;">
                            <?=$status_label?>
                        </span>
                    </div>
                </div>
                
                <?php if(!empty($rma['shipping_tracking'])): ?>
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Shipping Tracking:</div>
                    <div class="rma-detail-value"><?=htmlspecialchars($rma['shipping_tracking'])?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-6">
            <!-- Timeline -->
            <div class="rma-detail-card timeline">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i> Timeline
                </div>
                
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Date Discovered:</div>
                    <div class="rma-detail-value">
                        <?=date('d M Y', strtotime($rma['date_discovered']))?>
                        <span class="badge badge-secondary ml-2"><?=$rma['days_open']?> days ago</span>
                    </div>
                </div>
                
                <?php if(!empty($rma['date_applied'])): ?>
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Date Applied:</div>
                    <div class="rma-detail-value"><?=date('d M Y', strtotime($rma['date_applied']))?></div>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($rma['date_sent'])): ?>
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Date Sent:</div>
                    <div class="rma-detail-value"><?=date('d M Y', strtotime($rma['date_sent']))?></div>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($rma['date_resolved'])): ?>
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Date Resolved:</div>
                    <div class="rma-detail-value"><?=date('d M Y', strtotime($rma['date_resolved']))?></div>
                </div>
                <?php endif; ?>
                
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Created By:</div>
                    <div class="rma-detail-value"><?=htmlspecialchars($rma['created_by_name'])?></div>
                </div>
                
                <div class="rma-detail-row">
                    <div class="rma-detail-label">Created At:</div>
                    <div class="rma-detail-value"><?=date('d M Y H:i', strtotime($rma['created_at']))?></div>
                </div>
            </div>
        </div>
    </div>
    
    <?php

} catch(Exception $e) {
    echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>