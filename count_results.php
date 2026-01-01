<?php
ob_start();
session_start();
$page_title = 'Stock Count Results';
require 'assets/header.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

// Bootstrap & DB
require __DIR__ . '/php/bootstrap.php';

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

// Only admins can view results
if (!$is_admin) {
    header('Location: no_access.php');
    exit;
}

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

.results-container {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem;
    border-radius: 0.5rem;
    text-align: center;
}

.stat-card.success {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card.info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-number {
    font-size: 1.75rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.session-card {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
}

.session-card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.session-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.session-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
}

.session-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.session-stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.mini-stat {
    background: #f3f4f6;
    padding: 0.5rem;
    border-radius: 0.25rem;
    text-align: center;
    flex: 1;
}

.mini-stat-number {
    font-weight: bold;
    font-size: 1rem;
    color: #111827;
}

.mini-stat-label {
    font-size: 0.75rem;
    color: #6b7280;
}

.session-actions {
    display: flex;
    gap: 0.5rem;
}

.btn {
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.2s;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-info {
    background: #06b6d4;
    color: white;
}

.btn-info:hover {
    background: #0891b2;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 0.375rem;
    font-weight: 500;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.table th,
.table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.variance-positive {
    background-color: #fef3c7;
}

.variance-negative {
    background-color: #fecaca;
}

.variance-zero {
    background-color: #d1fae5;
}

.filters-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    align-items: center;
}

.form-select {
    padding: 0.375rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.form-control {
    padding: 0.375rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .session-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .session-meta {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .session-stats {
        flex-direction: column;
    }
    
    .filters-row {
        flex-direction: column;
        align-items: stretch;
    }
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

                        <!-- Overall Statistics -->
                        <div class="results-container">
                            <h4 style="margin: 0 0 1rem 0;">Overview Statistics</h4>
                            <div class="stats-grid" id="overallStats">
                                <!-- Stats will be loaded via AJAX -->
                            </div>
                        </div>

                        <!-- Sessions List -->
                        <div class="results-container">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h4 style="margin: 0;">Count Sessions</h4>
                                <div style="display: flex; gap: 0.5rem;">
                                    <select id="statusFilter" class="form-select">
                                        <option value="">All Sessions</option>
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                    <a href="count_stock.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-arrow-left"></i> Back to Counting
                                    </a>
                                </div>
                            </div>
                            
                            <div id="sessionsList">
                                <!-- Sessions will be loaded via AJAX -->
                            </div>
                        </div>

                        <!-- Session Details Modal Area -->
                        <div id="sessionDetailsContainer" style="display: none;">
                            <div class="results-container">
                                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 1rem;">
                                    <h4 style="margin: 0;">Session Details: <span id="detailSessionName"></span></h4>
                                    <button class="btn btn-danger btn-sm" onclick="closeSessionDetails()">
                                        <i class="fas fa-times"></i> Close
                                    </button>
                                </div>

                                <!-- Filters for session details -->
                                <div class="filters-row">
                                    <select id="varianceFilter" class="form-select" style="width: 150px;">
                                        <option value="">All Variances</option>
                                        <option value="positive">Overages</option>
                                        <option value="negative">Shortages</option>
                                        <option value="zero">Exact</option>
                                    </select>
                                    <input type="text" id="productSearch" class="form-control" placeholder="Search products..." style="width: 200px;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="checkbox" id="showApplied">
                                        <label for="showApplied">Show Applied to Odoo</label>
                                    </div>
                                </div>

                                <!-- Results table -->
                                <div style="overflow-x: auto;">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAllResults"></th>
                                                <th>SKU</th>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <th>System CS</th>
                                                <th>System AS</th>
                                                <th>Total Sys</th>
                                                <th>Counted</th>
                                                <th>Variance</th>
                                                <th>Value Impact</th>
                                                <th>Counted By</th>
                                                <th>Date</th>
                                                <th>Applied</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sessionResultsTable">
                                            <!-- Results will be loaded via AJAX -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Bulk actions -->
                                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                                    <button class="btn btn-success btn-sm" onclick="markAsApplied()">
                                        <i class="fas fa-check"></i> Mark Selected as Applied to Odoo
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="exportSelected()">
                                        <i class="fas fa-download"></i> Export Selected
                                    </button>
                                </div>
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
    
    // Load initial data
    loadOverallStats();
    loadSessionsList();
    
    // Status filter
    $('#statusFilter').change(function() {
        loadSessionsList();
    });
    
    // Detail filters
    $('#varianceFilter, #productSearch, #showApplied').on('change keyup', function() {
        if ($('#sessionDetailsContainer').is(':visible')) {
            filterSessionResults();
        }
    });
    
    function loadOverallStats() {
        $.ajax({
            url: 'php/count_results_ajax.php',
            type: 'POST',
            data: { action: 'get_overall_stats' },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        displayOverallStats(result.stats);
                    }
                } catch (e) {
                    console.error('Error loading stats:', e);
                }
            }
        });
    }
    
    function displayOverallStats(stats) {
        const html = `
            <div class="stat-card">
                <div class="stat-number">${stats.total_sessions || 0}</div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number">${stats.total_items_counted || 0}</div>
                <div class="stat-label">Items Counted</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number">${stats.pending_items || 0}</div>
                <div class="stat-label">Pending Counts</div>
            </div>
            <div class="stat-card info">
                <div class="stat-number">£${parseFloat(stats.total_variance_value || 0).toFixed(2)}</div>
                <div class="stat-label">Total Value Impact</div>
            </div>
        `;
        $('#overallStats').html(html);
    }
    
    function loadSessionsList() {
        const status = $('#statusFilter').val();
        
        $.ajax({
            url: 'php/count_results_ajax.php',
            type: 'POST',
            data: { 
                action: 'get_sessions',
                status: status
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        displaySessionsList(result.sessions);
                    }
                } catch (e) {
                    console.error('Error loading sessions:', e);
                }
            }
        });
    }
    
    function displaySessionsList(sessions) {
        let html = '';
        
        if (sessions.length === 0) {
            html = '<p class="text-center" style="color: #6b7280; padding: 2rem;">No sessions found.</p>';
        } else {
            sessions.forEach(session => {
                const statusBadge = session.status === 'active' ? 'badge-warning' : 
                                  session.status === 'completed' ? 'badge-success' : 'badge-info';
                                  
                const varianceClass = parseFloat(session.total_variance_value) > 0 ? 'text-success' : 
                                    parseFloat(session.total_variance_value) < 0 ? 'text-danger' : '';
                
                html += `
                    <div class="session-card">
                        <div class="session-header">
                            <h5 class="session-title">${session.name}</h5>
                            <span class="badge ${statusBadge}">${session.status.toUpperCase()}</span>
                        </div>
                        <div class="session-meta">
                            <span><i class="fas fa-user"></i> ${session.created_by}</span>
                            <span><i class="fas fa-calendar"></i> ${formatDate(session.created_date)}</span>
                            <span><i class="fas fa-map-marker-alt"></i> ${session.location.toUpperCase()}</span>
                        </div>
                        <div class="session-stats">
                            <div class="mini-stat">
                                <div class="mini-stat-number">${session.total_items || 0}</div>
                                <div class="mini-stat-label">Total Items</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-number">${session.completed_items || 0}</div>
                                <div class="mini-stat-label">Completed</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-number">${session.pending_items || 0}</div>
                                <div class="mini-stat-label">Pending</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-number ${varianceClass}">£${parseFloat(session.total_variance_value || 0).toFixed(2)}</div>
                                <div class="mini-stat-label">Value Impact</div>
                            </div>
                        </div>
                        <div class="session-actions">
                            <button class="btn btn-primary btn-sm" onclick="viewSessionDetails(${session.id}, '${session.name}')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="btn btn-success btn-sm" onclick="exportSession(${session.id})">
                                <i class="fas fa-download"></i> Export Full
                            </button>
                            <button class="btn btn-info btn-sm" onclick="exportOdooAdjustment(${session.id})">
                                <i class="fas fa-upload"></i> Odoo Import
                            </button>
                            ${session.status === 'completed' ? `
                                <button class="btn btn-warning btn-sm" onclick="archiveSession(${session.id})">
                                    <i class="fas fa-archive"></i> Archive
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
        }
        
        $('#sessionsList').html(html);
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    // Global functions for buttons
    window.viewSessionDetails = function(sessionId, sessionName) {
        $('#detailSessionName').text(sessionName);
        $('#sessionDetailsContainer').show();
        loadSessionResults(sessionId);
        $('html, body').animate({
            scrollTop: $('#sessionDetailsContainer').offset().top
        }, 500);
    };
    
    window.closeSessionDetails = function() {
        $('#sessionDetailsContainer').hide();
    };
    
    window.exportSession = function(sessionId) {
        window.location.href = `php/export_session.php?session_id=${sessionId}`;
    };
    
    // NEW FUNCTION FOR ODOO EXPORT
    window.exportOdooAdjustment = function(sessionId) {
        window.location.href = `php/export_odoo_adjustment.php?session_id=${sessionId}`;
    };
    
    window.archiveSession = function(sessionId) {
        if (confirm('Archive this session? It will be moved to archived status.')) {
            $.ajax({
                url: 'php/count_results_ajax.php',
                type: 'POST',
                data: {
                    action: 'archive_session',
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.trim() === 'success') {
                        alert('Session archived successfully');
                        loadSessionsList();
                    } else {
                        alert('Error archiving session');
                    }
                }
            });
        }
    };
    
    function loadSessionResults(sessionId) {
        $.ajax({
            url: 'php/count_results_ajax.php',
            type: 'POST',
            data: { 
                action: 'get_session_results',
                session_id: sessionId
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        window.currentSessionResults = result.results;
                        filterSessionResults();
                    }
                } catch (e) {
                    console.error('Error loading session results:', e);
                }
            }
        });
    }
    
    function filterSessionResults() {
        if (!window.currentSessionResults) return;
        
        let filteredResults = window.currentSessionResults;
        
        // Apply filters
        const varianceFilter = $('#varianceFilter').val();
        const searchTerm = $('#productSearch').val().toLowerCase();
        const showApplied = $('#showApplied').is(':checked');
        
        if (varianceFilter) {
            filteredResults = filteredResults.filter(item => {
                const variance = parseFloat(item.variance);
                if (varianceFilter === 'positive') return variance > 0;
                if (varianceFilter === 'negative') return variance < 0;
                if (varianceFilter === 'zero') return variance === 0;
                return true;
            });
        }
        
        if (searchTerm) {
            filteredResults = filteredResults.filter(item => 
                item.sku.toLowerCase().includes(searchTerm) ||
                item.name.toLowerCase().includes(searchTerm)
            );
        }
        
        if (showApplied) {
            filteredResults = filteredResults.filter(item => item.applied_to_odoo === 'yes');
        }
        
        displaySessionResults(filteredResults);
    }
    
    function displaySessionResults(results) {
        let html = '';
        
        results.forEach(item => {
            const variance = parseFloat(item.variance);
            const varianceValue = parseFloat(item.variance_value);
            const totalSystem = parseFloat(item.system_cs_stock) + parseFloat(item.system_as_stock);
            
            let rowClass = '';
            if (variance > 0) rowClass = 'variance-positive';
            else if (variance < 0) rowClass = 'variance-negative';
            else rowClass = 'variance-zero';
            
            const appliedBadge = item.applied_to_odoo === 'yes' ? 
                '<span class="badge badge-success">Yes</span>' : 
                '<span class="badge badge-warning">No</span>';
            
            html += `
                <tr class="${rowClass}">
                    <td><input type="checkbox" class="result-checkbox" value="${item.sku}"></td>
                    <td><strong>${item.sku}</strong></td>
                    <td>${item.name}</td>
                    <td>${item.pos_category}</td>
                    <td class="text-right">${parseFloat(item.system_cs_stock).toFixed(0)}</td>
                    <td class="text-right">${parseFloat(item.system_as_stock).toFixed(0)}</td>
                    <td class="text-right"><strong>${totalSystem.toFixed(0)}</strong></td>
                    <td class="text-right"><strong>${parseFloat(item.counted_stock).toFixed(0)}</strong></td>
                    <td class="text-right">
                        <strong style="color: ${variance > 0 ? '#d97706' : variance < 0 ? '#dc2626' : 'inherit'}">
                            ${variance > 0 ? '+' : ''}${variance.toFixed(0)}
                        </strong>
                    </td>
                    <td class="text-right">
                        <strong style="color: ${varianceValue > 0 ? '#d97706' : varianceValue < 0 ? '#dc2626' : 'inherit'}">
                            £${varianceValue > 0 ? '+' : ''}${varianceValue.toFixed(2)}
                        </strong>
                    </td>
                    <td>${item.counted_by}</td>
                    <td>${formatDate(item.count_date)}</td>
                    <td>${appliedBadge}</td>
                </tr>
            `;
        });
        
        if (html === '') {
            html = '<tr><td colspan="13" class="text-center">No results found</td></tr>';
        }
        
        $('#sessionResultsTable').html(html);
    }
    
    // Select all functionality
    $('#selectAllResults').change(function() {
        $('.result-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    window.markAsApplied = function() {
        const selected = $('.result-checkbox:checked').map(function() {
            return this.value;
        }).get();
        
        if (selected.length === 0) {
            alert('Please select items to mark as applied');
            return;
        }
        
        if (confirm(`Mark ${selected.length} items as applied to Odoo?`)) {
            $.ajax({
                url: 'php/count_results_ajax.php',
                type: 'POST',
                data: {
                    action: 'mark_as_applied',
                    skus: selected
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Items marked as applied successfully');
                            // Reload current session results
                            const sessionId = window.currentSessionId;
                            if (sessionId) {
                                loadSessionResults(sessionId);
                            }
                        } else {
                            alert('Error: ' + result.error);
                        }
                    } catch (e) {
                        alert('Error marking items as applied');
                    }
                }
            });
        }
    };
    
    window.exportSelected = function() {
        const selected = $('.result-checkbox:checked').map(function() {
            return this.value;
        }).get();
        
        if (selected.length === 0) {
            alert('Please select items to export');
            return;
        }
        
        // Create a form and submit it for download
        const form = $('<form>', {
            method: 'POST',
            action: 'php/export_selected_results.php'
        });
        
        selected.forEach(sku => {
            form.append($('<input>', {
                type: 'hidden',
                name: 'skus[]',
                value: sku
            }));
        });
        
        $('body').append(form);
        form.submit();
        form.remove();
    };
    
});
</script>