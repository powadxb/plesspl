<?php
session_start();
$page_title = 'Control Panel';
require 'php/bootstrap.php';

// Ensure session is active
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Ensure only super admins can access this page
if ($user_details['admin'] < 2) {
    header('Location: no_access.php');
    exit;
}

require 'assets/header.php';
require 'assets/navbar.php';

// Fetch all users and their permissions
$users = $DB->query("SELECT id, username FROM users");

// Grouped permissions for better organization
$permission_groups = [
    'Stock Management' => [
        'zindex' => 'Stock View',
        'count_stock' => 'Stock Count',
        'stock_suppliers' => 'Stock Suppliers',
        'supplier_availability' => 'Supplier Availability',
        'view_supplier_prices' => 'View Supplier Prices',
        'edit_stock_status' => 'Edit Stock Status'
    ],
    'Essentials Management' => [
        'essential_dashboard' => 'Essentials Dashboard',
        'essential_categories' => 'Manage Categories',
        'essential_product_types' => 'Manage Product Types',
        'essential_mapping' => 'Map Products',
        'generate_pricelist' => 'Generate Pricelist'
    ],
    'Orders & Sales' => [
        'order_list' => 'Orders',
        'cctv_quote' => 'CCTV Quote',
        'pc_quote' => 'PC Quote',
        'pc_orders' => 'PC Orders'
    ],
    'Labels & Printing' => [
        'labels' => 'Labels',
        'hanging_labels' => 'Hanging Labels',
        'placeholder_labels' => 'Placeholder Labels'
    ],
    'Magento & E-commerce' => [
        'magento_merchandiser' => 'Magento Merchandiser'
    ],
    'RMA Management' => [
        'RMA-View' => 'View Items',
        'RMA-View All Locations' => 'All Locations',
        'RMA-Manage' => 'Manage RMAs',
        'RMA-View Supplier' => 'View Suppliers',
        'RMA-View Financial' => 'View Costs',
        'RMA-Batch Management' => 'Batches',
        'RMA-Batch Admin' => 'Edit Completed'
    ],
    'System & Reports' => [
        'courier_log' => 'Courier',
        'other_page' => 'Other'
    ],
    'Second-Hand Inventory' => [
        'SecondHand-View' => 'View Items',
        'SecondHand-View All Locations' => 'All Locations',
        'SecondHand-Manage' => 'Manage Items',
        'SecondHand-View Financial' => 'View Financial Data',
        'SecondHand-View Customer Data' => 'View Customer Data',
        'SecondHand-View Documents' => 'View Documents',
        'SecondHand-Import Trade Ins' => 'Import Trade-Ins',
        'SecondHand-Manage Compliance' => 'Manage Compliance'
    ],
    'Master Data' => [
        'categories' => 'Categories',
        'manufacturers' => 'Manufacturers'
    ]
];

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_to_update = $_POST['user_id'] ?? null;
    $permissions = $_POST['permissions'] ?? [];

    if (!$user_id_to_update) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }

    // Get all known permissions from the permission_groups array
    $all_known_permissions = [];
    foreach ($permission_groups as $group => $group_perms) {
        foreach ($group_perms as $perm_key => $perm_label) {
            $all_known_permissions[] = $perm_key;
        }
    }

    // Clear only the known permissions for the user, preserve others
    if (!empty($all_known_permissions)) {
        $placeholders = str_repeat('?,', count($all_known_permissions) - 1) . '?';
        $DB->query("DELETE FROM user_permissions WHERE user_id = ? AND page IN ($placeholders)", array_merge([$user_id_to_update], $all_known_permissions));
    } else {
        // If no known permissions exist, clear all for this user
        $DB->query("DELETE FROM user_permissions WHERE user_id = ?", [$user_id_to_update]);
    }

    // Insert the selected permissions
    foreach ($permissions as $page) {
        $DB->query("INSERT INTO user_permissions (user_id, page, has_access) VALUES (?, ?, 1)", [$user_id_to_update, $page]);
    }

    echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);
    exit;
}

// Fetch user permissions
$all_permissions = $DB->query("SELECT * FROM user_permissions");
$user_permissions = [];
foreach ($all_permissions as $permission) {
    $user_permissions[$permission['user_id']][] = $permission['page'];
}

// Get all unique permission pages to add to the permission groups so they appear in the form
$all_unique_pages = $DB->query("SELECT DISTINCT page FROM user_permissions");
foreach ($all_unique_pages as $page_row) {
    $page = $page_row['page'];
    $found = false;

    // Check if this page is already in one of the permission groups
    foreach ($permission_groups as $group_name => $group_perms) {
        if (array_key_exists($page, $group_perms)) {
            $found = true;
            break;
        }
    }

    // If not found in any group and it's a SecondHand permission, add it to the SecondHand group
    if (!$found && strpos($page, 'SecondHand-') === 0) {
        if (!isset($permission_groups['Second-Hand Inventory'])) {
            $permission_groups['Second-Hand Inventory'] = [];
        }
        // Create a label by replacing hyphens with spaces and capitalizing
        $label = str_replace(['SecondHand-', '-', '_'], [' ', ' ', ' '], $page);
        $label = ucwords(trim($label));
        $permission_groups['Second-Hand Inventory'][$page] = $label;
    }
    // Add other non-standard permissions to a general group if needed
    elseif (!$found) {
        if (!isset($permission_groups['Other Permissions'])) {
            $permission_groups['Other Permissions'] = [];
        }
        $label = str_replace(['-', '_'], [' ', ' '], $page);
        $label = ucwords(trim($label));
        $permission_groups['Other Permissions'][$page] = $label;
    }
}
?>

<div class="control-panel-container">
    <div class="panel-header">
        <h1 class="panel-title">
            <i class="fas fa-users-cog"></i>
            User Permissions Management
        </h1>
        <div class="panel-actions">
            <button id="expand-all" class="action-btn">
                <i class="fas fa-chevron-down"></i> Expand All
            </button>
            <button id="collapse-all" class="action-btn">
                <i class="fas fa-chevron-up"></i> Collapse All
            </button>
        </div>
    </div>

    <div class="users-accordion">
        <?php foreach ($users as $user): ?>
            <div class="user-card" data-user-id="<?php echo $user['id']; ?>">
                <div class="user-card-header">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                            <span class="permission-count">
                                <?php 
                                $count = isset($user_permissions[$user['id']]) ? count($user_permissions[$user['id']]) : 0;
                                echo $count . ' permission' . ($count != 1 ? 's' : '');
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="save-user-btn" data-user-id="<?php echo $user['id']; ?>" title="Save permissions">
                            <i class="fas fa-save"></i>
                            <span>Save</span>
                        </button>
                        <button class="toggle-card-btn">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
                
                <div class="user-card-body">
                    <div class="permission-groups">
                        <?php foreach ($permission_groups as $group_name => $permissions): ?>
                            <div class="permission-group">
                                <div class="group-header">
                                    <h3 class="group-title">
                                        <i class="fas fa-folder"></i>
                                        <?php echo $group_name; ?>
                                    </h3>
                                    <div class="group-actions">
                                        <button class="group-action-btn select-all" data-user-id="<?php echo $user['id']; ?>" data-group="<?php echo $group_name; ?>">
                                            <i class="fas fa-check-double"></i> All
                                        </button>
                                        <button class="group-action-btn select-none" data-user-id="<?php echo $user['id']; ?>" data-group="<?php echo $group_name; ?>">
                                            <i class="fas fa-times"></i> None
                                        </button>
                                    </div>
                                </div>
                                <div class="permission-list">
                                    <?php foreach ($permissions as $page => $name): ?>
                                        <label class="permission-item">
                                            <input type="checkbox" 
                                                   class="permission-checkbox" 
                                                   data-user-id="<?php echo $user['id']; ?>" 
                                                   data-page="<?php echo $page; ?>"
                                                   data-group="<?php echo $group_name; ?>"
                                                   <?php echo isset($user_permissions[$user['id']]) && in_array($page, $user_permissions[$user['id']]) ? 'checked' : ''; ?>>
                                            <span class="permission-checkmark"></span>
                                            <span class="permission-label"><?php echo $name; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Success Toast -->
<div id="success-toast" class="toast">
    <i class="fas fa-check-circle"></i>
    <span class="toast-message">Permissions saved successfully</span>
</div>

<!-- Error Toast -->
<div id="error-toast" class="toast error">
    <i class="fas fa-exclamation-circle"></i>
    <span class="toast-message">Error saving permissions</span>
</div>

<?php require 'assets/footer.php'; ?>

<script>
$(document).ready(function () {
    // Toggle card expansion
    $('.toggle-card-btn, .user-info').click(function () {
        const card = $(this).closest('.user-card');
        const body = card.find('.user-card-body');
        const icon = card.find('.toggle-card-btn i');
        
        body.slideToggle(300);
        card.toggleClass('expanded');
        icon.toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Expand all cards
    $('#expand-all').click(function () {
        $('.user-card').each(function () {
            if (!$(this).hasClass('expanded')) {
                $(this).find('.user-card-body').slideDown(300);
                $(this).addClass('expanded');
                $(this).find('.toggle-card-btn i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
            }
        });
    });

    // Collapse all cards
    $('#collapse-all').click(function () {
        $('.user-card.expanded').each(function () {
            $(this).find('.user-card-body').slideUp(300);
            $(this).removeClass('expanded');
            $(this).find('.toggle-card-btn i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });
    });

    // Select all in group
    $('.select-all').click(function (e) {
        e.stopPropagation();
        const userId = $(this).data('user-id');
        const group = $(this).data('group');
        $(`.permission-checkbox[data-user-id="${userId}"][data-group="${group}"]`).prop('checked', true);
        updatePermissionCount(userId);
    });

    // Select none in group
    $('.select-none').click(function (e) {
        e.stopPropagation();
        const userId = $(this).data('user-id');
        const group = $(this).data('group');
        $(`.permission-checkbox[data-user-id="${userId}"][data-group="${group}"]`).prop('checked', false);
        updatePermissionCount(userId);
    });

    // Update permission count when checkboxes change
    $('.permission-checkbox').change(function () {
        const userId = $(this).data('user-id');
        updatePermissionCount(userId);
    });

    function updatePermissionCount(userId) {
        const count = $(`.permission-checkbox[data-user-id="${userId}"]:checked`).length;
        $(`.user-card[data-user-id="${userId}"] .permission-count`).text(
            count + ' permission' + (count !== 1 ? 's' : '')
        );
    }

    // Save user permissions
    $('.save-user-btn').click(function () {
        const userId = $(this).data('user-id');
        const btn = $(this);
        const permissions = [];
        
        $(`.permission-checkbox[data-user-id="${userId}"]:checked`).each(function () {
            permissions.push($(this).data('page'));
        });

        // Show loading state
        btn.addClass('loading').html('<i class="fas fa-spinner fa-spin"></i><span>Saving...</span>');
        btn.prop('disabled', true);

        $.post('control_panel.php', { 
            user_id: userId, 
            permissions: permissions 
        }, function (response) {
            // Show success state
            btn.removeClass('loading').addClass('saved').html('<i class="fas fa-check"></i><span>Saved!</span>');
            showToast('success-toast');
            
            // Reset button after 2 seconds
            setTimeout(() => {
                btn.removeClass('saved').html('<i class="fas fa-save"></i><span>Save</span>');
                btn.prop('disabled', false);
            }, 2000);
        }).fail(function() {
            // Show error state
            btn.removeClass('loading').addClass('error').html('<i class="fas fa-times"></i><span>Error</span>');
            showToast('error-toast');
            
            setTimeout(() => {
                btn.removeClass('error').html('<i class="fas fa-save"></i><span>Save</span>');
                btn.prop('disabled', false);
            }, 2000);
        });
    });

    function showToast(toastId) {
        const toast = $('#' + toastId);
        toast.addClass('show');
        setTimeout(() => {
            toast.removeClass('show');
        }, 3000);
    }
});
</script>

<style>
/* Hide default page elements */
.welcome, .line-seprate {
    display: none;
}

.page-container3 {
    padding: 0 !important;
    margin: 0 !important;
}

/* Main Container */
.control-panel-container {
    max-width: 1400px;
    margin: 20px auto;
    padding: 20px;
}

/* Panel Header */
.panel-header {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(220, 38, 38, 0.2);
}

.panel-title {
    font-size: 1.75rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.panel-title i {
    font-size: 1.5rem;
}

.panel-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
}

/* Users Accordion */
.users-accordion {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

/* User Card */
.user-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.user-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    border-color: #cbd5e1;
}

.user-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    cursor: pointer;
    user-select: none;
    border-bottom: 1px solid #e2e8f0;
}

.user-card.expanded .user-card-header {
    background: #f8fafc;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.user-avatar {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.username {
    font-weight: 600;
    color: #1e293b;
    font-size: 1.1rem;
}

.permission-count {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

.card-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.save-user-btn {
    background: #10b981;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.save-user-btn:hover:not(:disabled) {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.save-user-btn.loading {
    background: #6b7280;
    cursor: not-allowed;
}

.save-user-btn.saved {
    background: #10b981;
}

.save-user-btn.error {
    background: #ef4444;
}

.save-user-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.toggle-card-btn {
    background: #f1f5f9;
    color: #475569;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toggle-card-btn:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.toggle-card-btn i {
    transition: transform 0.3s ease;
}

/* User Card Body */
.user-card-body {
    display: none;
    padding: 24px;
    background: #fafafa;
}

/* Permission Groups */
.permission-groups {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.permission-group {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.group-header {
    background: #f8fafc;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.group-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.group-title i {
    color: #64748b;
    font-size: 0.85rem;
}

.group-actions {
    display: flex;
    gap: 6px;
}

.group-action-btn {
    background: white;
    color: #64748b;
    border: 1px solid #e2e8f0;
    padding: 4px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 4px;
}

.group-action-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #475569;
}

.group-action-btn i {
    font-size: 0.7rem;
}

/* Permission List */
.permission-list {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.permission-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.permission-item:hover {
    background: #f8fafc;
}

.permission-checkbox {
    display: none;
}

.permission-checkmark {
    width: 20px;
    height: 20px;
    background: white;
    border: 2px solid #cbd5e1;
    border-radius: 5px;
    position: relative;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.permission-checkmark::after {
    content: '';
    position: absolute;
    display: none;
    left: 6px;
    top: 2px;
    width: 4px;
    height: 9px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.permission-checkbox:checked + .permission-checkmark {
    background: #10b981;
    border-color: #10b981;
}

.permission-checkbox:checked + .permission-checkmark::after {
    display: block;
}

.permission-item:hover .permission-checkmark {
    border-color: #94a3b8;
    transform: scale(1.05);
}

.permission-checkbox:checked + .permission-checkmark:hover {
    background: #059669;
    border-color: #059669;
}

.permission-label {
    font-size: 0.9rem;
    color: #475569;
    font-weight: 500;
    user-select: none;
}

.permission-checkbox:checked ~ .permission-label {
    color: #1e293b;
    font-weight: 600;
}

/* Toast Notifications */
.toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: #10b981;
    color: white;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 1000;
}

.toast.show {
    transform: translateY(0);
    opacity: 1;
}

.toast.error {
    background: #ef4444;
}

.toast i {
    font-size: 1.2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .control-panel-container {
        margin: 10px;
        padding: 10px;
    }

    .panel-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }

    .panel-title {
        font-size: 1.4rem;
    }

    .panel-actions {
        width: 100%;
        justify-content: center;
    }

    .user-card-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .card-actions {
        width: 100%;
        justify-content: space-between;
    }

    .permission-groups {
        grid-template-columns: 1fr;
    }

    .save-user-btn span {
        display: none;
    }

    .toast {
        bottom: 15px;
        right: 15px;
        left: 15px;
    }
}

@media (max-width: 480px) {
    .action-btn span {
        display: none;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        font-size: 0.95rem;
    }

    .username {
        font-size: 1rem;
    }
}
</style>