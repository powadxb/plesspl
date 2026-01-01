<?php 
ob_start();
session_start();

// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Users Management';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}

require __DIR__.'/php/bootstrap.php';
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? null;

if(empty($user_details) || $user_details['admin'] != 2){
  header("Location: no_access.php");
  exit();
}

// Only level 2 admins can access this page
$is_super_admin = true; // Since we already verified admin level 2

// Security verification - require username AND password re-entry
if(!isset($_SESSION['users_verified'])) {
    
    // Handle form submission
    if(isset($_POST['verify_credentials'])) {
        $entered_username = trim($_POST['admin_username']);
        $entered_password = $_POST['admin_password'];
        
        // Verify both username and password
        $verification_user = $DB->query("SELECT * FROM users WHERE username=? AND admin=2", [$entered_username])[0] ?? null;
        
        if($verification_user && 
           $verification_user['id'] == $user_details['id'] && 
           password_verify($entered_password, $verification_user['password'])) {
            $_SESSION['users_verified'] = time();
            header("Location: users.php");
            exit();
        } else {
            $credentials_error = "Invalid username or password. Only level 2 administrators can access this page.";
        }
    }
    
    // Show verification form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Level 2 Admin Verification Required</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 20px;
            }
            .verification-container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                padding: 2rem;
                max-width: 450px;
                width: 100%;
                text-align: center;
                border: 3px solid #dc2626;
            }
            .verification-icon {
                font-size: 3.5rem;
                color: #dc2626;
                margin-bottom: 1rem;
            }
            .form-control {
                border-radius: 10px;
                border: 2px solid #e5e7eb;
                padding: 0.75rem 1rem;
                margin-bottom: 1rem;
                width: 100%;
                box-sizing: border-box;
                font-size: 0.9rem;
            }
            .form-control:focus {
                border-color: #dc2626;
                box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
                outline: none;
            }
            .btn-verify {
                background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%);
                border: none;
                border-radius: 10px;
                padding: 0.75rem 2rem;
                font-weight: 600;
                width: 100%;
                color: white;
                cursor: pointer;
                font-size: 1rem;
                transition: all 0.3s ease;
            }
            .btn-verify:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(220, 38, 38, 0.3);
            }
            .alert-danger {
                background: #fecaca;
                color: #991b1b;
                padding: 0.75rem;
                border-radius: 10px;
                margin-bottom: 1rem;
                border: 1px solid #fca5a5;
            }
            .security-info {
                background: #fef2f2;
                border-radius: 10px;
                padding: 1rem;
                margin-top: 1rem;
                font-size: 0.8rem;
                color: #7f1d1d;
                border: 1px solid #fecaca;
            }
            .back-link {
                color: #dc2626;
                text-decoration: none;
                font-size: 0.9rem;
                margin-top: 1rem;
                display: inline-block;
                font-weight: 500;
            }
            .back-link:hover {
                color: #7f1d1d;
                text-decoration: none;
            }
            .input-group {
                position: relative;
                margin-bottom: 1rem;
            }
            .input-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #6b7280;
                z-index: 2;
            }
            .form-control.with-icon {
                padding-left: 2.5rem;
            }
        </style>
    </head>
    <body>
        <div class="verification-container">
            <div class="verification-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            
            <h2 style="color: #7f1d1d; margin-bottom: 0.5rem; font-weight: 700;">Level 2 Admin Verification</h2>
            <p style="color: #991b1b; margin-bottom: 2rem; font-size: 0.9rem; font-weight: 500;">
                This page requires Level 2 Administrator credentials
            </p>
            
            <?php if(isset($credentials_error)): ?>
            <div class="alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?=$credentials_error?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" 
                           name="admin_username" 
                           class="form-control with-icon"
                           placeholder="Username"
                           required 
                           autofocus
                           autocomplete="username">
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" 
                           name="admin_password" 
                           class="form-control with-icon"
                           placeholder="Password"
                           required
                           autocomplete="current-password">
                </div>
                
                <button type="submit" name="verify_credentials" class="btn-verify">
                    <i class="fas fa-shield-check"></i> Verify Level 2 Access
                </button>
            </form>
            
            <div class="security-info">
                <i class="fas fa-info-circle"></i>
                <strong>Security Notice:</strong> Only Level 2 Administrators can access user management. Your credentials will be verified against the system and remain valid for 30 minutes.
            </div>
            
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <script>
            // Auto-focus username field
            document.querySelector('input[name="admin_username"]').focus();
            
            // Handle form submission with Enter key
            document.querySelectorAll('input').forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.closest('form').submit();
                    }
                });
            });
            
            // Move focus to password after username is entered
            document.querySelector('input[name="admin_username"]').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.querySelector('input[name="admin_password"]').focus();
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Check if verification has expired (30 minutes)
if(isset($_SESSION['users_verified'])) {
    if($_SESSION['users_verified'] < (time() - 1800)) { // 30 minutes = 1800 seconds
        unset($_SESSION['users_verified']);
        header("Location: users.php"); // This will trigger verification again
        exit();
    }
}

// Handle clear verification (for testing/logout)
if(isset($_POST['clear_verification'])) {
    unset($_SESSION['users_verified']);
    header("Location: users.php");
    exit();
}

// Include header after verification
require 'assets/header.php';

// settings
require 'php/settings.php';
?>

<link rel="stylesheet" href="assets/css/tables.css?<?=time()?>">

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

.temp-assignment {
    background-color: #fef3c7;
}

.expired-assignment {
    background-color: #fecaca;
}

.btn-group {
    display: flex;
    gap: 0.25rem;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.location-info {
    font-size: 0.75rem;
    line-height: 1.2;
}

/* Responsive table improvements */
@media (max-width: 1200px) {
    .table th:nth-child(4), 
    .table td:nth-child(4),
    .table th:nth-child(5), 
    .table td:nth-child(5) {
        display: none;
    }
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.75rem;
    }
    
    .btn-group {
        flex-direction: column;
        gap: 0.125rem;
    }
    
    .btn-group .btn {
        font-size: 0.625rem;
        padding: 0.125rem 0.25rem;
    }
}
</style>

<div class="page-wrapper">
    <?php require 'assets/navbar.php' ?>
    <div class="page-content--bgf7">
        <section class="au-breadcrumb2 p-0 pt-4"></section>

        <section class="welcome p-t-10">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="title-4"><?=$page_title?></h1>
                        <hr class="line-seprate">
                    </div>
                </div>
            </div>
        </section>
        
        <section class="p-t-20">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-data__tool">
                            <div class="table-data__tool-left">
                                <a class="btn btn-success btn-sm" href="new_account.php">
                                    <i class="zmdi zmdi-plus"></i> Add New User
                                </a>
                                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#settingsPopup">
                                    <i class="zmdi zmdi-settings"></i> Settings
                                </button>
                                <button class="btn btn-info btn-sm" onclick="refreshUserList()">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                                
                                <!-- Clear verification for testing -->
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="clear_verification" class="btn btn-warning btn-sm" 
                                            onclick="return confirm('Clear security verification? You will need to re-enter your credentials.')">
                                        <i class="fas fa-unlock"></i> Clear Verification
                                    </button>
                                </form>
                            </div>
                            <div class="table-data__tool-right">
                                <input type="text" id="searchQuery" placeholder="Search users..." class="form-control form-control-sm" style="width: 200px;">
                            </div>
                        </div>
                        
                        <div class="table-responsive table-responsive-data2">
                            <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">ID</th>
                                        <th style="width: 100px;">Username</th>
                                        <th style="width: 150px;">Email</th>
                                        <th style="width: 100px;">First Name</th>
                                        <th style="width: 100px;">Last Name</th>
                                        <th style="width: 80px;">Role</th>
                                        <th style="width: 120px;">Location</th>
                                        <th style="width: 80px;">Status</th>
                                        <th style="width: 140px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="records"></tbody>
                            </table>
                            <div id="pagination"></div>
                        </div>

                        <!-- Location Assignment History -->
                        <div class="mt-4">
                            <div class="table-data__tool">
                                <div class="table-data__tool-left">
                                    <h5 style="margin: 0;">Recent Location Assignment History</h5>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm">
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

<!-- Update User Modal -->
<div class="modal fade" id="updateRecordPopup" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Update User Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="updateRecordContent"></div>
        </div>
    </div>
</div>

<!-- Location Assignment Modal -->
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

<!-- Settings Modal -->
<div class="modal fade" id="settingsPopup" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Settings</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="settingsForm">
                    <div class="form-group">
                        <label for="tableLines">Table Lines</label>
                        <input type="text" name="table_lines" id="tableLines" class="form-control requiredField" value="<?=$settings['table_lines']?>">
                    </div>
                    <hr>
                    <button class="btn btn-primary mx-auto d-block">Update Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
<input type="hidden" id="offset" value="0">

<?php require 'assets/footer.php'; ?>

<script>
$(document).ready(function(){
    
    function loadRecords(limit, offset, searchType='general'){
        var searchQuery = $("#searchQuery").val();
        $("#spinner").show();
        $.ajax({
            type:'POST',
            url:'php/list_users.php', // Using the modified existing file
            data:{limit:limit, offset:offset, search_query:searchQuery}
        }).done(function(response){
            $("#spinner").hide();
            if(response.length>0){
                $("#records").html(response);
                $('[data-toggle="tooltip"]').tooltip();
                $(".table-data__tool").width($(".table").width())
                $("#pagination").html($("#PaginationInfoResponse").html());
                $("html, body").animate({ scrollTop: 0 }, "slow");
            }
        });
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

    // Initial load
    loadRecords($("#limit").val(), $("#offset").val());
    loadAssignmentHistory();

    // Pagination
    $(document).on('click', '.recordsPage', function(e){
        e.preventDefault();
        var limit = $(this).attr("data-limit"),
            offset = $(this).attr("data-offset");
        loadRecords(limit, offset);
    });

    $(document).on('submit', '.jumpToPageForm', function(e){
        e.preventDefault();
        var form = $(this),
            pageNum = form.find(".jumpToPage").val(),
            lastPage = form.find(".jumpToPage").attr("data-last_page"),
            limit = form.find(".jumpToPage").attr("data-limit"),
            offset = limit * (pageNum -1);
        if(parseInt(pageNum)<=parseInt(lastPage)){
            loadRecords(limit, offset);
        }else{
            Swal.fire('Oops...', "The page number you have entered does not exist, the last page number is "+lastPage, 'warning');
        }
    });
    
    // Search functionality
    $("#searchQuery").keyup(function(e){
        if(e.key === 'Enter' || e.keyCode === 13) {
            loadRecords($("#limit").val(), $("#offset").val(), 'general');
        }
    });

    // Update user details
    $(document).on('click', '.updateRecord', function(e){
        e.preventDefault();
        var recordID = $(this).attr("data-id");
        $("#spinner").show();
        $.ajax({
            type:'POST',
            url:'php/get_user_details.php',
            data:{id:recordID}
        }).done(function(response){
            $("#spinner").hide();
            if(response.length>0){
                $("#updateRecordContent").html(response);
                $("#updateRecordPopup").modal('show');
            }else{
                Swal.fire('Oops...', 'Something went wrong, please try again.', 'error');
            }
        });
    });
    
    $(document).on("submit", '#updateDetailsForm', function(e){
        e.preventDefault();
        var form = $(this),
            formData = form.serialize(),
            validToSubmit = true;

        $.each(form.find(".requiredField"), function(index, value){
            if($(value).val()==null || $(value).val().length==0){
                validToSubmit = false;
            }
        });

        if(!validToSubmit){
            Swal.fire('Oops...', 'All fields with * are required.', 'error');
            return false;
        }
      
        if($("#password").val().length>0 && $("#password").val()!==$("#rePassword").val()){
            Swal.fire('Oops...', 'Password does not match.', 'error');
            return false;
        }
      
        $("#spinner").show();
      
        $.ajax({
            type:"POST",
            url:'php/update_user_details.php',
            data:formData
        }).done(function(response){
            $("#spinner").hide();
            if(response=='updated'){
                Swal.fire('Updated!', 'User updated successfully.', 'success');
                $("#updateRecordPopup").modal("hide");
                $(".pagination li.page-item.active .recordsPage").click();
            }else{
                Swal.fire('Oops...', 'Something went wrong, please try again.', 'error');
            }
        });
    });
    
    // Enable/disable user
    $(document).on('change', '.controlUser', function(e){
        e.preventDefault();
        var userID = $(this).attr("data-id");
        var status = $(this)[0].checked ? 1 : 0;
        $("#spinner").show();
      
        $.ajax({
            type:"POST",
            url:'php/enable_disable_user.php',
            data:{id:userID, status:status}
        }).done(function(response){
            $("#spinner").hide();
            if(response=='updated'){
                Swal.fire('Updated!', 'User status updated successfully.', 'success');
            }else{
                Swal.fire('Oops...', 'Something went wrong, please try again.', 'error');
            }
        });
    });
    
    // Settings form
    $(document).on("submit", '#settingsForm', function(e){
        e.preventDefault();
        var form = $(this),
            formData = form.serialize(),
            validToSubmit = true;

        $.each(form.find(".requiredField"), function(index, value){
            if($(value).val()==null || $(value).val().length==0){
                validToSubmit = false;
            }
        });

        if(!validToSubmit){
            Swal.fire('Oops...', 'All fields are required.', 'error');
            return false;
        }
      
        $("#spinner").show();
      
        $.ajax({
            type:"POST",
            url:'php/update_settings.php',
            data:formData
        }).done(function(response){
            $("#spinner").hide();
            if(response=='updated'){
                Swal.fire('Updated!', 'Settings updated successfully.', 'success');
                $("#settingsPopup").modal("hide");
            }else{
                Swal.fire('Oops...', 'Something went wrong, please try again.', 'error');
            }
        });
    });

    // Location assignment functionality
    $('#isTemporary').change(function() {
        if ($(this).is(':checked')) {
            $('#expiryGroup').show();
        } else {
            $('#expiryGroup').hide();
        }
    });
    
    // Global functions for location management
    window.refreshUserList = function() {
        loadRecords($("#limit").val(), $("#offset").val());
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
                        refreshUserList();
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
                            refreshUserList();
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