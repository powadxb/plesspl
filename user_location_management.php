<?php
ob_start();
session_start();
$page_title = 'User Location Management';
require 'assets/header.php';

// Ensure user is logged in and is admin
if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

// Bootstrap & DB
require __DIR__ . '/php/bootstrap.php';

// Retrieve user info
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? null;

// Check if user exists and is admin
if (empty($user_details) || $user_details['admin'] < 1) {
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

.management-container {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1rem;
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

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
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

.badge-cs {
    background: #dbeafe;
    color: #1e40af;
}

.badge-as {
    background: #d1fae5;
    color: #065f46;
}

.badge-unassigned {
    background: #fef3c7;
    color: #92400e;
}

.badge-temp {
    background: #fce7f3;
    color: #be185d;
}

.form-control {
    padding: 0.375rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    width: 100%;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.25rem;
    font-weight: 500;
    color: #374151;
}

.modal-content {
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem 1.5rem;
}

.modal-title {
    font-weight: 600;
    color: #111827;
}

.text-center {
    text-align: center;
}

.temp-assignment {
    background-color: #fef3c7;
}

.expired-assignment {
    background-color: #fecaca;
}

@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
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

                        <!-- User Location Assignment -->
                        <div class="management-container">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h4 style="margin: 0;">User Location Assignments</h4>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button class="btn btn-primary btn-sm" onclick="refreshUserList()">
                                        <i class="fas fa-sync"></i> Refresh
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Current Location</th>
                                            <th>Temporary Assignment</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="userLocationTable">
                                        <!-- Users will be loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Location Assignment History -->
                        <div class="management-container">
                            <h4 style="margin: 0 0 1rem 0;">Recent Location Assignment History</h4>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>Location</th>
                                            <th>Type</th>
                                            <th>Assigned By</th>
                                            <th>Expires</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTable">
                                        <!-- History will be loaded via AJAX -->
                                    </tbody>
                                </table>
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

<!-- Assignment Modal -->
<div class="modal fade" id="assignmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign User Location</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="assignmentForm">
                    <input type="hidden" id="assignUserId" name="user_id">
                    
                    <div class="form-group">
                        <label>User:</label>
                        <div id="assignUserName" style="font-weight: bold; color: #1f2937;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignLocation">Location:</label>
                        <select id="assignLocation" name="location" class="form-control" required>
                            <option value="">Select Location</option>
                            <option value="cs">CS (Commerce St)</option>
                            <option value="as">AS (Argyle St)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="isTemporary" name="is_temporary" style="margin-right: 0.5rem;">
                            Temporary Assignment
                        </label>
                    </div>
                    
                    <div class="form-group" id="expiryGroup" style="display: none;">
                        <label for="expiryHours">Expires in (hours):</label>
                        <select id="expiryHours" name="expires_hours" class="form-control">
                            <option value="4">4 hours</option>
                            <option value="8">8 hours (1 shift)</option>
                            <option value="24" selected>24 hours (1 day)</option>
                            <option value="48">48 hours (2 days)</option>
                            <option value="168">168 hours (1 week)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignmentNotes">Notes (optional):</label>
                        <textarea id="assignmentNotes" name="notes" class="form-control" rows="3" placeholder="Reason for assignment, special instructions, etc."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAssignment()">Assign Location</button>
            </div>
        </div>
    </div>
</div>

<?php require 'assets/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // Load initial data
    loadUserLocations();
    loadAssignmentHistory();
    
    // Handle temporary assignment checkbox
    $('#isTemporary').change(function() {
        if ($(this).is(':checked')) {
            $('#expiryGroup').show();
        } else {
            $('#expiryGroup').hide();
        }
    });
    
    function loadUserLocations() {
        $.ajax({
            url: 'php/user_location_ajax.php',
            type: 'POST',
            data: { action: 'get_user_locations' },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        displayUserLocations(result.users);
                    } else {
                        console.error('Error loading users:', result.error);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                }
            }
        });
    }
    
    function displayUserLocations(users) {
        let html = '';
        
        users.forEach(user => {
            const effectiveLocation = getEffectiveLocation(user);
            const isTemporary = user.temp_location && user.temp_location_expires && new Date(user.temp_location_expires) > new Date();
            const isExpired = user.temp_location && user.temp_location_expires && new Date(user.temp_location_expires) <= new Date();
            
            let rowClass = '';
            if (isTemporary) rowClass = 'temp-assignment';
            else if (isExpired) rowClass = 'expired-assignment';
            
            html += `
                <tr class="${rowClass}">
                    <td><strong>${user.first_name || ''} ${user.last_name || ''}</strong></td>
                    <td>${user.username}</td>
                    <td>${user.email}</td>
                    <td>
                        ${getLocationBadge(user.user_location)}
                    </td>
                    <td>
                        ${isTemporary ? getLocationBadge(user.temp_location, true) + '<br><small>Expires: ' + formatDate(user.temp_location_expires) + '</small>' : 
                          isExpired ? '<span class="badge badge-danger">Expired</span>' : 
                          '<span class="text-muted">None</span>'}
                    </td>
                    <td>
                        ${user.enabled ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Disabled</span>'}
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="showAssignmentModal(${user.id}, '${user.username}', '${user.first_name || ''} ${user.last_name || ''}')">
                            <i class="fas fa-map-marker-alt"></i> Assign
                        </button>
                        ${isTemporary ? `
                            <button class="btn btn-warning btn-sm" onclick="clearTemporaryAssignment(${user.id})">
                                <i class="fas fa-times"></i> Clear Temp
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
        
        if (html === '') {
            html = '<tr><td colspan="7" class="text-center">No users found</td></tr>';
        }
        
        $('#userLocationTable').html(html);
    }
    
    function getEffectiveLocation(user) {
        if (user.temp_location && user.temp_location_expires && new Date(user.temp_location_expires) > new Date()) {
            return user.temp_location;
        }
        return user.user_location;
    }
    
    function getLocationBadge(location, isTemp = false) {
        if (!location) {
            return '<span class="badge badge-unassigned">Not Assigned</span>';
        }
        
        const tempClass = isTemp ? ' badge-temp' : '';
        const tempText = isTemp ? ' (Temp)' : '';
        
        switch (location) {
            case 'cs':
                return `<span class="badge badge-cs${tempClass}">CS${tempText}</span>`;
            case 'as':
                return `<span class="badge badge-as${tempClass}">AS${tempText}</span>`;
            default:
                return `<span class="badge badge-unassigned">${location}${tempText}</span>`;
        }
    }
    
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleString();
    }
    
    function loadAssignmentHistory() {
        $.ajax({
            url: 'php/user_location_ajax.php',
            type: 'POST',
            data: { action: 'get_assignment_history' },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        displayAssignmentHistory(result.history);
                    }
                } catch (e) {
                    console.error('Error parsing history response:', e);
                }
            }
        });
    }
    
    function displayAssignmentHistory(history) {
        let html = '';
        
        history.forEach(item => {
            html += `
                <tr>
                    <td>${formatDate(item.assigned_date)}</td>
                    <td>${item.username}</td>
                    <td>${getLocationBadge(item.location)}</td>
                    <td>
                        <span class="badge ${item.assignment_type === 'temporary' ? 'badge-warning' : 'badge-primary'}">
                            ${item.assignment_type}
                        </span>
                    </td>
                    <td>${item.assigned_by_username}</td>
                    <td>${item.expires_date ? formatDate(item.expires_date) : 'N/A'}</td>
                    <td>${item.notes || ''}</td>
                </tr>
            `;
        });
        
        if (html === '') {
            html = '<tr><td colspan="7" class="text-center">No assignment history found</td></tr>';
        }
        
        $('#historyTable').html(html);
    }
    
    // Global functions
    window.refreshUserList = function() {
        loadUserLocations();
        loadAssignmentHistory();
    };
    
    window.showAssignmentModal = function(userId, username, fullName) {
        $('#assignUserId').val(userId);
        $('#assignUserName').text(fullName || username);
        $('#assignmentForm')[0].reset();
        $('#assignUserId').val(userId); // Reset clears this, so set it again
        $('#expiryGroup').hide();
        $('#assignmentModal').modal('show');
    };
    
    window.submitAssignment = function() {
        const formData = {
            action: 'assign_location',
            user_id: $('#assignUserId').val(),
            location: $('#assignLocation').val(),
            is_temporary: $('#isTemporary').is(':checked'),
            expires_hours: $('#expiryHours').val(),
            notes: $('#assignmentNotes').val()
        };
        
        if (!formData.user_id || !formData.location) {
            alert('Please select a location');
            return;
        }
        
        $.ajax({
            url: 'php/user_location_ajax.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        alert('Location assigned successfully!');
                        $('#assignmentModal').modal('hide');
                        loadUserLocations();
                        loadAssignmentHistory();
                    } else {
                        alert('Error: ' + result.error);
                    }
                } catch (e) {
                    alert('Error processing assignment');
                }
            }
        });
    };
    
    window.clearTemporaryAssignment = function(userId) {
        if (confirm('Clear the temporary location assignment for this user?')) {
            $.ajax({
                url: 'php/user_location_ajax.php',
                type: 'POST',
                data: {
                    action: 'clear_temporary',
                    user_id: userId
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Temporary assignment cleared');
                            loadUserLocations();
                            loadAssignmentHistory();
                        } else {
                            alert('Error: ' + result.error);
                        }
                    } catch (e) {
                        alert('Error clearing assignment');
                    }
                }
            });
        }
    };
    
});
</script>