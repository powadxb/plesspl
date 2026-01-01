<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
session_start();
$page_title = 'Stock Count';
require 'assets/header.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

// Bootstrap & DB
require __DIR__ . '/php/bootstrap.php';
require __DIR__ . '/php/odoo_connection.php';

// Retrieve user info
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? null;

// Check if user exists
if (empty($user_details)) {
    header("Location: login.php");
    exit();
}

// Check if user has permission for this page
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'count_stock'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Check if user is admin
$is_admin = $user_details['admin'] >= 1;

// Get user's effective location
function getUserEffectiveLocation($user_id, $DB) {
    $user = $DB->query("
        SELECT user_location, temp_location, temp_location_expires 
        FROM users 
        WHERE id = ?
    ", [$user_id]);
    
    if (empty($user)) {
        return null;
    }
    
    $user_data = $user[0];
    
    // Check if temporary location is active
    if (!empty($user_data['temp_location']) && 
        !empty($user_data['temp_location_expires']) && 
        strtotime($user_data['temp_location_expires']) > time()) {
        return $user_data['temp_location'];
    }
    
    return $user_data['user_location'];
}

$user_location = getUserEffectiveLocation($user_id, $DB);

// Settings
require 'php/settings.php';
?>

<style>
.page-wrapper {
  background-color: #f9fafb;
  min-height: 100vh;
}

.welcome {
  background-color: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 0;
}

.title-4 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
}

.count-container {
  background: white;
  border-radius: 0.25rem;
  padding: 0.5rem;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  margin-bottom: 0.5rem;
}

.admin-section {
  border-left: 3px solid #3b82f6;
  background: #f8fafc;
  padding: 0.5rem;
  margin-bottom: 0.75rem;
  border-radius: 0.25rem;
}

.staff-section {
  border-left: 3px solid #10b981;
  background: #f0fdf4;
  padding: 0.5rem;
  border-radius: 0.25rem;
}

.user-section {
  border-left: 3px solid #8b5cf6;
  background: #faf5ff;
  padding: 0.5rem;
  margin-bottom: 0.75rem;
  border-radius: 0.25rem;
}

.location-info {
  border-left: 3px solid #f59e0b;
  background: #fffbeb;
  padding: 0.5rem;
  margin-bottom: 0.75rem;
  border-radius: 0.25rem;
}

.filter-container {
  background: white;
  border-radius: 0.25rem;
  padding: 0.75rem;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  margin-bottom: 0.5rem;
  border-left: 3px solid #06b6d4;
}

.filter-row {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
}

.filter-input {
  flex: 1;
  min-width: 200px;
  padding: 0.375rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  font-family: 'Segoe UI', 'Arial', sans-serif;
}

.filter-input:focus {
  outline: none;
  border-color: #06b6d4;
  box-shadow: 0 0 0 1px #06b6d4;
}

.filter-stats {
  font-size: 0.75rem;
  color: #6b7280;
  margin-left: 0.5rem;
  white-space: nowrap;
}

.clear-filter {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
  cursor: pointer;
  color: #6b7280;
}

.clear-filter:hover {
  background: #e5e7eb;
}

.filter-highlight {
  background-color: #fef3c7 !important;
  border: 1px solid #f59e0b !important;
}

.no-results-message {
  text-align: center;
  padding: 2rem;
  color: #6b7280;
  font-style: italic;
  background: #f9fafb;
  border-radius: 0.25rem;
  border: 1px dashed #d1d5db;
}

.queue-stats {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.stat-card {
  background: white;
  padding: 0.5rem;
  border-radius: 0.25rem;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  flex: 1;
  text-align: center;
}

.stat-number {
  font-size: 1.25rem;
  font-weight: bold;
  color: #1f2937;
  line-height: 1;
}

.stat-label {
  font-size: 0.7rem;
  color: #6b7280;
  margin-top: 0.125rem;
}

.btn {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.7rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
  text-decoration: none;
  display: inline-block;
  line-height: 1.2;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-success {
  background: #10b981;
  color: white;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-info {
  background: #06b6d4;
  color: white;
}

.btn:hover {
  opacity: 0.9;
}

.btn-sm {
  padding: 0.125rem 0.375rem;
  font-size: 0.65rem;
  height: 24px;
  line-height: 1.2;
}

.btn-xs {
  padding: 0.125rem 0.25rem;
  font-size: 0.6rem;
  height: 20px;
  line-height: 1;
}

.table {
  width: 100%;
  margin-top: 0.25rem;
  border-collapse: collapse;
  font-size: 0.75rem;
  font-family: 'Segoe UI', 'Arial', sans-serif;
}

.table th,
.table td {
  padding: 0.25rem 0.375rem;
  text-align: left;
  border: 1px solid #d1d5db;
  vertical-align: middle;
  line-height: 1.2;
}

.table th {
  background: #f9fafb;
  font-weight: 600;
  color: #374151;
  font-size: 0.7rem;
  height: 28px;
  white-space: nowrap;
}

.table td {
  height: 32px;
  background: white;
}

.table tbody tr:nth-child(even) td {
  background: #f9fafb;
}

.table tbody tr:hover td {
  background: #f3f4f6;
}

.count-input {
  width: 60px;
  height: 24px;
  padding: 0.125rem 0.25rem;
  border: 1px solid #d1d5db;
  border-radius: 0.125rem;
  text-align: center;
  font-size: 0.75rem;
  font-family: 'Segoe UI', 'Arial', sans-serif;
}

.count-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 1px #3b82f6;
}

.count-form {
  display: inline-block;
  margin: 0;
}

.form-control {
  padding: 0.25rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  font-size: 0.75rem;
}

.alert {
  padding: 0.75rem 1rem;
  border-radius: 0.375rem;
  margin-bottom: 1rem;
}

.alert-success {
  background-color: #d1fae5;
  border: 1px solid #a7f3d0;
  color: #065f46;
}

.location-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.location-cs {
  background: #dbeafe;
  color: #1e40af;
}

.location-as {
  background: #d1fae5;
  color: #065f46;
}

.location-unassigned {
  background: #fef3c7;
  color: #92400e;
}

.location-temp {
  background: #fce7f3;
  color: #be185d;
}
</style>

<div class="page-wrapper">
    <?php require 'assets/navbar.php'; ?>
    <div class="page-content--bgf7">
        <section class="au-breadcrumb2 p-0 pt-4"></section>

        <section class="welcome p-t-10">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="title-4"><?= $page_title ?></h1>
                        <hr class="line-seprate">
                    </div>
                </div>
            </div>
        </section>

        <section class="p-t-20">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">

                        <!-- User Location Information -->
                        <div class="count-container location-info">
                            <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">Your Location Assignment</h4>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 0.7rem; font-weight: 600;">Current Location:</span>
                                <?php if ($user_location): ?>
                                    <?php 
                                    $temp_location = $DB->query("
                                        SELECT temp_location, temp_location_expires 
                                        FROM users 
                                        WHERE id = ? AND temp_location IS NOT NULL AND temp_location_expires > NOW()
                                    ", [$user_id]);
                                    $is_temp = !empty($temp_location);
                                    ?>
                                    <span class="location-badge location-<?= $user_location ?> <?= $is_temp ? 'location-temp' : '' ?>">
                                        <?= strtoupper($user_location) ?><?= $is_temp ? ' (Temporary)' : '' ?>
                                    </span>
                                    <?php if ($is_temp): ?>
                                        <span style="font-size: 0.7rem; color: #6b7280;">
                                            Expires: <?= date('M j, H:i', strtotime($temp_location[0]['temp_location_expires'])) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="location-badge location-unassigned">Not Assigned</span>
                                    <span style="font-size: 0.7rem; color: #dc2626;">Contact admin to assign your location</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($is_admin): ?>
                        <!-- Admin Section - Statistics and Management -->
                        <div class="count-container admin-section">
                            <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">Admin Dashboard</h4>
                            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                                <span style="font-size: 0.7rem;">Add items:</span>
                                <a href="zindex.php" class="btn btn-primary">Stock View</a>
                                <a href="user_location_management.php" class="btn btn-warning">Manage User Locations</a>
                                <button class="btn btn-danger" id="clearQueue">Clear All</button>
                            </div>
                            
                            <!-- Queue Statistics - FIXED to only count ACTIVE sessions -->
                            <?php
                            // Only count items from ACTIVE sessions
                            $queue_stats = $DB->query("
                                SELECT 
                                    COUNT(q.id) as total,
                                    SUM(CASE WHEN q.status = 'pending' THEN 1 ELSE 0 END) as pending,
                                    SUM(CASE WHEN q.status = 'counted' THEN 1 ELSE 0 END) as counted,
                                    SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed
                                FROM stock_count_queue q
                                LEFT JOIN stock_count_sessions s ON q.session_id = s.id
                                WHERE s.status = 'active'
                            ")[0] ?? [];
                            
                            // Handle case where no active sessions exist
                            if (empty($queue_stats) || $queue_stats['total'] === null) {
                                $queue_stats = [
                                    'total' => 0,
                                    'pending' => 0,
                                    'counted' => 0,
                                    'completed' => 0
                                ];
                            }
                            ?>
                            
                            <div class="queue-stats">
                                <div class="stat-card">
                                    <div class="stat-number"><?= $queue_stats['total'] ?></div>
                                    <div class="stat-label">Total Items</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $queue_stats['pending'] ?></div>
                                    <div class="stat-label">Pending</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $queue_stats['counted'] ?></div>
                                    <div class="stat-label">Counted</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $queue_stats['completed'] ?></div>
                                    <div class="stat-label">Completed</div>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Session Management Section -->
                        <div class="count-container admin-section" style="margin-bottom: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <h4 style="margin: 0; font-size: 0.9rem;">Admin Session Management</h4>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button class="btn btn-success btn-sm" id="newSessionBtn">
                                        <i class="fas fa-plus"></i> New Session
                                    </button>
                                    <a href="count_results.php" class="btn btn-info btn-sm">
                                        <i class="fas fa-chart-bar"></i> View Results
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Admin Session Controls -->
                            <div style="margin-bottom: 0.5rem;">
                                <span style="font-size: 0.7rem; font-weight: 600;">Manage Sessions:</span>
                                <select id="adminSessionSelect" class="form-control" style="display: inline-block; width: 200px; margin-left: 0.5rem; height: 24px; font-size: 0.7rem;">
                                    <option value="">Loading...</option>
                                </select>
                                <button class="btn btn-warning btn-xs" id="completeSessionBtn" style="margin-left: 0.5rem;">
                                    Complete Session
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Session Selection for ALL Users (Admin and Staff) -->
                        <div class="count-container <?= $is_admin ? 'admin-section' : 'user-section' ?>">
                            <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">
                                <?= $is_admin ? 'Select Session to Count' : 'Active Count Sessions' ?>
                            </h4>
                            
                            <!-- Session Selector for all users -->
                            <div style="margin-bottom: 0.5rem;">
                                <span style="font-size: 0.7rem; font-weight: 600;">Choose session:</span>
                                <select id="activeSessionSelect" class="form-control" style="display: inline-block; width: 250px; margin-left: 0.5rem; height: 24px; font-size: 0.7rem;">
                                    <option value="">Loading sessions...</option>
                                </select>
                            </div>
                            
                            <!-- Current Session Info -->
                            <div id="currentSessionInfo" style="display: none; padding: 0.25rem; background: #f0f9ff; border-radius: 0.25rem; font-size: 0.7rem;">
                                <strong>Current Session:</strong> <span id="currentSessionName"></span> |
                                <strong>Location:</strong> <span id="currentSessionLocation"></span> |
                                <strong>Your Pending Items:</strong> <span id="currentSessionPending">0</span> |
                                <strong>Session Completed Items:</strong> <span id="currentSessionCompleted">0</span>
                            </div>
                            
                            <!-- Instructions for users -->
                            <?php if (!$is_admin): ?>
                            <div style="margin-top: 0.5rem; padding: 0.25rem; background: #fffbeb; border-radius: 0.25rem; font-size: 0.7rem; color: #92400e;">
                                <strong>Instructions:</strong> Select a session above to see items assigned for counting. 
                                <?php if (empty($user_location)): ?>
                                    <strong style="color: #dc2626;">Your location is not assigned - contact an admin to assign your location before counting.</strong>
                                <?php else: ?>
                                    You will only see sessions for your assigned location (<?= strtoupper($user_location) ?>).
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Item Filter Section -->
                        <div class="count-container filter-container">
                            <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">
                                <i class="fas fa-search" style="margin-right: 0.5rem; color: #06b6d4;"></i>
                                Find Items
                            </h4>
                            <div class="filter-row">
                                <input 
                                    type="text" 
                                    id="itemFilter" 
                                    class="filter-input" 
                                    placeholder="Search by item code, description, or barcode..."
                                    autocomplete="off"
                                />
                                <button type="button" id="clearFilter" class="clear-filter">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                                <div class="filter-stats">
                                    <span id="filterStats">Ready to search</span>
                                </div>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.7rem; color: #6b7280;">
                                <i class="fas fa-info-circle"></i>
                                Type to instantly filter items below. Search works across SKU, EAN/barcode, product names, manufacturers, and categories.
                            </div>
                        </div>

                        <!-- Stock Counting Section (visible to all users with access) -->
                        <div class="count-container staff-section">
                            <h4 style="margin: 0 0 0.25rem 0; font-size: 0.9rem;">Stock Counting</h4>
                            <p style="margin: 0 0 0.5rem 0; font-size: 0.7rem; color: #6b7280;">Enter physical count only - do not include system stock figures.</p>
                            
                            <div id="countingItems">
                                <!-- Counting items will be loaded here via AJAX -->
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <section class="p-t-60 p-b-20">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="copyright">
                            <p>Copyright © <?=date("Y")?> <?=$website_name?>. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require 'assets/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // Session Management Variables
    let currentSessionId = '';
    let filterTimeout;
    
    // Load initial data
    loadCountingItems();
    
    function loadCountingItems() {
        // Include session ID if one is selected
        const sessionParam = currentSessionId ? `?session_id=${currentSessionId}` : '';
        
        $.ajax({
            url: 'php/load_counting_items.php' + sessionParam,
            type: 'GET',
            success: function(response) {
                $('#countingItems').html(response);
                // Reset filter after items are loaded
                setTimeout(function() {
                    $('#itemFilter').val('');
                    $('#filterStats').text('Ready to search');
                    $('#countingItems .no-results-message').remove();
                }, 100);
            },
            error: function() {
                $('#countingItems').html('<p class="text-danger">Error loading counting items. Please refresh the page.</p>');
            }
        });
    }
    
    // Filter functionality
    function filterItems() {
        const filterValue = $('#itemFilter').val().toLowerCase().trim();
        const $rows = $('#countingItems tbody tr');
        
        if (filterValue === '') {
            // Show all rows
            $rows.show().removeClass('filter-highlight');
            $('#filterStats').text('Ready to search');
            return;
        }
        
        // Split the filter value into individual words for multi-word search
        const filterWords = filterValue.split(/\s+/).filter(word => word.length > 0);
        let visibleCount = 0;
        
        $rows.each(function() {
            const $row = $(this);
            const sku = $row.find('td:nth-child(1)').text().toLowerCase(); // SKU
            const ean = $row.find('td:nth-child(2)').text().toLowerCase(); // EAN/Barcode
            const productName = $row.find('td:nth-child(3)').text().toLowerCase(); // Product Name
            const manufacturer = $row.find('td:nth-child(4)').text().toLowerCase(); // Manufacturer
            const category = $row.find('td:nth-child(5)').text().toLowerCase(); // Category
            
            let isMatch = false;
            
            // Check if filter matches SKU, EAN, manufacturer, or category (exact substring match)
            if (sku.includes(filterValue) || 
                ean.includes(filterValue) || 
                manufacturer.includes(filterValue) || 
                category.includes(filterValue)) {
                isMatch = true;
            }
            
            // For product name, check if ALL filter words are found somewhere in the product name
            if (!isMatch && filterWords.length > 0) {
                const allWordsFound = filterWords.every(word => productName.includes(word));
                if (allWordsFound) {
                    isMatch = true;
                }
            }
            
            if (isMatch) {
                $row.show().addClass('filter-highlight');
                visibleCount++;
            } else {
                $row.hide().removeClass('filter-highlight');
            }
        });
        
        // Update stats
        const totalCount = $rows.length;
        $('#filterStats').text(`Showing ${visibleCount} of ${totalCount} items`);
        
        // Show "no results" message if no items match
        if (visibleCount === 0 && totalCount > 0) {
            if ($('#countingItems .no-results-message').length === 0) {
                $('#countingItems').append(`
                    <div class="no-results-message">
                        <i class="fas fa-search" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block;"></i>
                        No items found matching "${filterValue}"
                        <br><small>Try a different search term or clear the filter</small>
                    </div>
                `);
            }
        } else {
            $('#countingItems .no-results-message').remove();
        }
    }
    
    // Real-time filtering with debounce
    $('#itemFilter').on('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(filterItems, 300); // 300ms delay
    });
    
    // Clear filter
    $('#clearFilter').click(function() {
        $('#itemFilter').val('').focus();
        filterItems();
    });
    
    // Keyboard shortcuts
    $(document).keydown(function(e) {
        // Ctrl+F or Cmd+F to focus filter (prevent default browser search)
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
            e.preventDefault();
            $('#itemFilter').focus();
        }
        // Escape to clear filter
        if (e.keyCode === 27) {
            $('#itemFilter').val('').blur();
            filterItems();
        }
    });
    
    // Focus filter input when page loads
    setTimeout(function() {
        $('#itemFilter').focus();
    }, 500);
    
    // Clear queue (admin only)
    $('#clearQueue').click(function() {
        if (confirm('Are you sure you want to clear the entire counting queue from active sessions?')) {
            $.ajax({
                url: 'php/manage_count_queue.php',
                type: 'POST',
                data: { action: 'clear_all' },
                success: function(response) {
                    loadCountingItems();
                    alert('Queue cleared successfully');
                    // Reload page to update admin dashboard stats
                    location.reload();
                }
            });
        }
    });
    
    // Submit count - SINGLE EVENT HANDLER (this is the main one)
    $(document).on('submit', '.count-form', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const sku = form.find('input[name="sku"]').val();
        const count = form.find('input[name="counted_stock"]').val();
        const sessionId = form.find('input[name="session_id"]').val();
        
        if (!count || count < 0) {
            alert('Please enter a valid count');
            return;
        }
        
        // Disable the submit button to prevent double submission
        const submitBtn = form.closest('tr').find('.submit-count');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');
        
        $.ajax({
            url: 'php/submit_count.php',
            type: 'POST',
            data: {
                sku: sku,
                counted_stock: count,
                session_id: sessionId
            },
            success: function(response) {
                if (response.trim() === 'success') {
                    // Show brief success notification without blocking
                    const successMsg = $('<div class="alert alert-success" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 200px;">Count submitted successfully!</div>');
                    $('body').append(successMsg);
                    setTimeout(() => successMsg.fadeOut(500, () => successMsg.remove()), 2000);
                    
                    loadCountingItems();
                } else {
                    // Re-enable button on error
                    submitBtn.prop('disabled', false).html(originalText);
                    alert('Error submitting count: ' + response);
                }
            },
            error: function() {
                // Re-enable button on error
                submitBtn.prop('disabled', false).html(originalText);
                alert('Error submitting count. Please try again.');
            }
        });
    });
    
    // Alternative click handler for submit buttons (backup)
    $(document).on('click', '.submit-count', function(e) {
        e.preventDefault();
        // Find the form in the same row and submit it
        const form = $(this).closest('tr').find('.count-form');
        form.submit();
    });
    
    // Remove item from queue (admin only)
    $(document).on('click', '.remove-from-queue', function() {
        const sku = $(this).data('sku');
        const sessionId = $(this).data('session-id');
        
        if (confirm('Remove this item from the counting queue?')) {
            $.ajax({
                url: 'php/manage_count_queue.php',
                type: 'POST',
                data: { 
                    action: 'remove_item',
                    sku: sku,
                    session_id: sessionId
                },
                success: function(response) {
                    loadCountingItems();
                    alert('Item removed from queue');
                }
            });
        }
    });

    // Delete and recount functionality
    $(document).on('click', '.delete-and-recount', function() {
        const $btn = $(this);
        const sku = $btn.data('sku');
        const sessionId = $btn.data('session-id');
        const countedBy = $btn.data('counted-by');
        const originalCount = $btn.data('count');
        
        // Different confirmation messages for admin vs staff
        <?php if ($is_admin): ?>
        const confirmMessage = `Delete count and add item back to queue for recounting?\n\n` +
                              `SKU: ${sku}\n` +
                              `Original count: ${originalCount}\n` +
                              `Counted by: ${countedBy}\n\n` +
                              `This will remove the count entry and put the item back in the pending queue.`;
        <?php else: ?>
        const confirmMessage = `Delete your count and recount this item?\n\n` +
                              `SKU: ${sku}\n` +
                              `Your count: ${originalCount}\n\n` +
                              `This will remove your count and put the item back in the queue for you to count again.`;
        <?php endif; ?>
        
        if (confirm(confirmMessage)) {
            // Disable button and show loading state
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            $.ajax({
                url: 'php/delete_and_recount.php',
                type: 'POST',
                data: {
                    sku: sku,
                    session_id: sessionId
                },
                success: function(response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (result.success) {
                            // Show success message
                            const successMsg = $('<div class="alert alert-success" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">' +
                                                'Count deleted! Item added back to queue for recounting.' +
                                                '</div>');
                            $('body').append(successMsg);
                            setTimeout(() => successMsg.fadeOut(500, () => successMsg.remove()), 3000);
                            
                            // Reload the counting items to show updated queue and remove from completed
                            loadCountingItems();
                        } else {
                            alert('Error: ' + result.error);
                            // Re-enable button on error
                            $btn.prop('disabled', false).html(originalText);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        alert('Error processing response. Please try again.');
                        $btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    alert('Network error. Please check your connection and try again.');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    });

    // Session Management Functions
    function loadActiveSessions() {
        $.ajax({
            url: 'php/manage_count_queue.php',
            type: 'POST',
            data: { action: 'get_active_sessions' },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        populateSessionSelectors(result.sessions);
                    } else {
                        console.error('Session loading error:', result.error);
                        if (result.error.includes('location not assigned')) {
                            $('#activeSessionSelect').html('<option value="">Location not assigned - contact admin</option>');
                        }
                    }
                } catch (e) {
                    console.error('Error loading sessions:', e);
                }
            }
        });
    }
    
    function populateSessionSelectors(sessions) {
        // Populate the main session selector (for all users)
        const select = $('#activeSessionSelect');
        select.empty();
        
        if (sessions.length === 0) {
            <?php if ($is_admin): ?>
            select.append('<option value="">No active sessions available</option>');
            <?php else: ?>
            select.append('<option value="">No sessions available for your location</option>');
            <?php endif; ?>
        } else {
            select.append('<option value="">Select a session to count</option>');
            sessions.forEach(session => {
                const locationText = session.location ? ` (${session.location.toUpperCase()})` : '';
                select.append(`<option value="${session.id}">${session.name}${locationText} - ${session.pending_items} items to count</option>`);
            });
        }
        
        // Populate admin session selector if admin
        <?php if ($is_admin): ?>
        const adminSelect = $('#adminSessionSelect');
        adminSelect.empty();
        adminSelect.append('<option value="">Select session to manage</option>');
        sessions.forEach(session => {
            const locationText = session.location ? ` (${session.location.toUpperCase()})` : '';
            adminSelect.append(`<option value="${session.id}">${session.name}${locationText} - ${session.pending_items} pending</option>`);
        });
        <?php endif; ?>
        
        // Auto-select first session if only one exists
        if (sessions.length === 1) {
            select.val(sessions[0].id);
            selectSession(sessions[0]);
        }
    }
    
    function selectSession(session) {
        currentSessionId = session.id;
        $('#currentSessionName').text(session.name);
        $('#currentSessionLocation').text(session.location ? session.location.toUpperCase() : 'Unknown');
        $('#currentSessionPending').text(session.pending_items);
        $('#currentSessionCompleted').text(session.completed_items);
        $('#currentSessionInfo').show();
        
        // Reload counting items for this session
        loadCountingItems();
    }
    
    // Handle session selection (for all users)
    $('#activeSessionSelect').change(function() {
        const sessionId = $(this).val();
        if (sessionId) {
            const sessionText = $(this).find('option:selected').text();
            const sessionParts = sessionText.match(/^(.+?)\s*\(([^)]+)\)\s*-\s*(\d+)\s*items/);
            
            const session = {
                id: sessionId,
                name: sessionParts ? sessionParts[1].trim() : sessionText.split(' - ')[0],
                location: sessionParts ? sessionParts[2].toLowerCase() : 'unknown',
                pending_items: sessionParts ? sessionParts[3] : 0,
                completed_items: 0 // Will be updated when we load full session data
            };
            selectSession(session);
        } else {
            $('#currentSessionInfo').hide();
            currentSessionId = '';
            loadCountingItems(); // Reload to show message about no session selected
        }
    });
    
    // Admin-only functions
    <?php if ($is_admin): ?>
    // Updated New Session Button with Location Selection
    $('#newSessionBtn').click(function() {
        Swal.fire({
            title: 'Create New Count Session',
            html: `
                <div style="text-align: left; margin-bottom: 1rem;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Session Name *</label>
                        <input id="sessionName" class="swal2-input" placeholder="e.g., Monthly Check - February 2025" style="margin: 0;">
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Target Location *</label>
                        <select id="sessionLocation" class="swal2-input" style="margin: 0;">
                            <option value="">Select Location</option>
                            <option value="cs">CS (Commerce St)</option>
                            <option value="as">AS (Argyle St)</option>
                        </select>
                        <small style="color: #6b7280; font-size: 0.75rem;">
                            Sessions must target a specific location for accurate variance calculation
                        </small>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Description</label>
                        <textarea id="sessionDescription" class="swal2-textarea" placeholder="Optional description or notes about this count session" rows="3"></textarea>
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Create Session',
            cancelButtonText: 'Cancel',
            width: '500px',
            preConfirm: () => {
                const sessionName = document.getElementById('sessionName').value;
                const sessionLocation = document.getElementById('sessionLocation').value;
                const sessionDescription = document.getElementById('sessionDescription').value;
                
                if (!sessionName.trim()) {
                    Swal.showValidationMessage('Session name is required');
                    return false;
                }
                
                if (!sessionLocation) {
                    Swal.showValidationMessage('Please select a target location');
                    return false;
                }
                
                return {
                    name: sessionName.trim(),
                    location: sessionLocation,
                    description: sessionDescription.trim()
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const sessionData = result.value;
                
                $.ajax({
                    url: 'php/manage_count_queue.php',
                    type: 'POST',
                    data: {
                        action: 'create_session',
                        name: sessionData.name,
                        description: sessionData.description,
                        location: sessionData.location
                    },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire({
                                    title: 'Session Created!',
                                    text: `"${sessionData.name}" has been created for ${sessionData.location.toUpperCase()} location.`,
                                    icon: 'success',
                                    confirmButtonText: 'Continue'
                                }).then(() => {
                                    loadActiveSessions();
                                });
                            } else {
                                Swal.fire('Error', result.error || 'Failed to create session', 'error');
                            }
                        } catch (e) {
                            Swal.fire('Error', 'Invalid response from server', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to communicate with server', 'error');
                    }
                });
            }
        });
    });
    
    // Complete Session Button (Admin-controlled with double confirmation)
    $('#completeSessionBtn').click(function() {
        const adminSessionId = $('#adminSessionSelect').val();
        if (!adminSessionId) {
            alert('Please select a session to complete first');
            return;
        }
        
        // First check if session can be completed
        checkSessionForCompletion(adminSessionId);
    });

    function checkSessionForCompletion(sessionId) {
        const btn = $('#completeSessionBtn');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Checking...');
        
        $.ajax({
            url: 'php/manage_count_queue.php',
            type: 'POST',
            data: {
                action: 'complete_session',
                session_id: sessionId
            },
            success: function(response) {
                btn.prop('disabled', false).html(originalText);
                
                try {
                    const result = JSON.parse(response);
                    
                    if (result.success) {
                        // Session completed successfully
                        Swal.fire({
                            title: 'Session Completed!',
                            text: result.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            loadActiveSessions();
                            $('#currentSessionInfo').hide();
                            currentSessionId = '';
                            loadCountingItems();
                            location.reload();
                        });
                    } else if (result.requires_decision) {
                        // Session has uncounted items - ask admin what to do
                        showUncountedItemsDialog(sessionId, result.pending_items);
                    } else {
                        // Other error
                        Swal.fire('Error', result.error, 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    Swal.fire('Error', 'Invalid response from server', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(originalText);
                Swal.fire('Error', 'Failed to communicate with server', 'error');
            }
        });
    }

    function showUncountedItemsDialog(sessionId, pendingCount) {
        Swal.fire({
            title: 'Uncounted Items Found',
            html: `
                <div style="text-align: left; margin-bottom: 1.5rem;">
                    <p style="margin-bottom: 1rem;"><strong>This session has ${pendingCount} uncounted items.</strong></p>
                    <p style="margin-bottom: 1rem;">You have two options:</p>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                        <strong>Option 1:</strong> Complete session without the uncounted items<br>
                        <small style="color: #6c757d;">Uncounted items will remain in the queue and won't appear in results</small>
                    </div>
                    <div style="background: #fff3cd; padding: 1rem; border-radius: 0.5rem; border: 1px solid #ffeaa7;">
                        <strong>Option 2:</strong> Auto-zero the uncounted items<br>
                        <small style="color: #856404;">All uncounted items will be recorded as zero counts in the results</small>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Complete Without Uncounted Items',
            denyButtonText: 'Auto-Zero Uncounted Items',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3085d6',
            denyButtonColor: '#f39c12',
            cancelButtonColor: '#6c757d',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
                // Complete without uncounted items - just ignore them
                completeSessionIgnoreUncounted(sessionId);
            } else if (result.isDenied) {
                // Auto-zero uncounted items - show confirmation dialog
                showAutoZeroConfirmation(sessionId, pendingCount);
            }
            // If cancelled, do nothing
        });
    }

    function completeSessionIgnoreUncounted(sessionId) {
        // For now, let's prevent this and force admin to make a decision about uncounted items
        Swal.fire({
            title: 'Not Recommended',
            text: 'Completing a session with uncounted items is not recommended as it will leave orphaned data. Please choose to auto-zero the uncounted items instead.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
    }

    function showAutoZeroConfirmation(sessionId, pendingCount) {
        Swal.fire({
            title: 'Confirm Auto-Zero Action',
            html: `
                <div style="text-align: left; margin-bottom: 1.5rem;">
                    <div style="background: #fee; border: 1px solid #fcc; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                        <strong style="color: #c33;">⚠️ Warning: This action cannot be undone!</strong>
                    </div>
                    <p style="margin-bottom: 1rem;">You are about to:</p>
                    <ul style="margin-bottom: 1.5rem;">
                        <li>Record <strong>${pendingCount} uncounted items</strong> as zero counts</li>
                        <li>Calculate negative variances for these items</li>
                        <li>Include them in the session results</li>
                        <li>Complete the session</li>
                    </ul>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                            Type "CONFIRM" to proceed:
                        </label>
                        <input id="autoZeroConfirmation" class="swal2-input" placeholder="Type CONFIRM here" style="margin: 0; text-transform: uppercase;">
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Auto-Zero Uncounted Items',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
            focusConfirm: false,
            preConfirm: () => {
                const confirmation = document.getElementById('autoZeroConfirmation').value.toUpperCase();
                if (confirmation !== 'CONFIRM') {
                    Swal.showValidationMessage('You must type "CONFIRM" to proceed');
                    return false;
                }
                return confirmation;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                executeAutoZeroCompletion(sessionId);
            }
        });
    }

    function executeAutoZeroCompletion(sessionId) {
        const btn = $('#completeSessionBtn');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Auto-zeroing...');
        
        $.ajax({
            url: 'php/manage_count_queue.php',
            type: 'POST',
            data: {
                action: 'complete_session',
                session_id: sessionId,
                auto_zero_uncounted: true,
                confirmation_text: 'CONFIRM'
            },
            success: function(response) {
                btn.prop('disabled', false).html(originalText);
                
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            title: 'Session Completed!',
                            text: result.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            loadActiveSessions();
                            $('#currentSessionInfo').hide();
                            currentSessionId = '';
                            loadCountingItems();
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', result.error, 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    Swal.fire('Error', 'Invalid response from server', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(originalText);
                Swal.fire('Error', 'Failed to communicate with server', 'error');
            }
        });
    }
    <?php endif; ?>
    
    // Load sessions on page load
    loadActiveSessions();
    
});
</script>