<?php
session_start();
require '../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Load permission functions
$permissions_file = __DIR__.'/php/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

// Check if user has access to Trade-In system - ONLY use control panel permissions
$has_tradein_access = (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-View', $DB));

if (!$has_tradein_access) {
    header("Location: ../no_access.php");
    exit();
}

// Check if user has location assigned
if(empty($user_details['user_location'])){
    die("Error: Your user account does not have a location assigned. Please contact administrator.");
}

// Determine effective location
$effective_location = $user_details['user_location'];
if(!empty($user_details['temp_location']) &&
   !empty($user_details['temp_location_expires']) &&
   strtotime($user_details['temp_location_expires']) > time()) {
    $effective_location = $user_details['temp_location'];
}

$location_name = ($effective_location == 'cs') ? 'Commerce Street' : 'Argyle Street';

// Check specific permissions - ONLY use control panel permissions
$can_view_financial = (function_exists('canViewFinancialData') && canViewFinancialData($user_id, $DB));
$can_view_customer = (function_exists('canViewCustomerData') && canViewCustomerData($user_id, $DB));
$can_view_documents = (function_exists('canViewDocuments') && canViewDocuments($user_id, $DB));
$can_manage = (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB));

// Get all categories
$categories = $DB->query("SELECT DISTINCT pos_category FROM master_categories WHERE pos_category IS NOT NULL AND pos_category != '' ORDER BY pos_category ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade-In Process</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .step-container {
            display: none;
        }
        .step-container.active {
            display: block;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            position: relative;
        }
        .step.active {
            background: #0d6efd;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .step-line {
            width: 50px;
            height: 2px;
            background: #e9ecef;
            position: absolute;
            top: 50%;
            left: 100%;
            transform: translateY(-50%);
        }
        .step:last-child .step-line {
            display: none;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .item-photo {
            max-width: 100px;
            max-height: 100px;
            margin: 5px;
            position: relative;
        }
        .photo-upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f9f9f9;
        }
        .photo-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .photo-preview-item {
            position: relative;
            display: inline-block;
        }
        .photo-preview-item img {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .photo-preview-item .remove-photo {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .compliance-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .trade-in-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
        }
        .total-section {
            background: #e8f4ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        .payment-section {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        .id-uploaded {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .customer-search-result {
            cursor: pointer;
            transition: background 0.2s;
        }
        .customer-search-result:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-arrow-left"></i> Back to Main Menu
            </a>
            <span class="navbar-text">Trade-In Process (<?=$location_name?>)</span>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="text-center mb-4">Trade-In Process</h1>
        
        <!-- Step Indicator -->
        <div class="step-indicator mb-4">
            <div class="step active" id="step1-indicator">1</div>
            <div class="step-line"></div>
            <div class="step" id="step2-indicator">2</div>
            <div class="step-line"></div>
            <div class="step" id="step3-indicator">3</div>
            <div class="step-line"></div>
            <div class="step" id="step4-indicator">4</div>
            <div class="step-line"></div>
            <div class="step" id="step5-indicator">5</div>
        </div>

        <!-- Step 1: Staff Authentication -->
        <div class="step-container active" id="step1">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Step 1: Staff Authentication</h4>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h5>Current Staff: <?=$user_details['username']?></h5>
                        <p>Please confirm your credentials to proceed with the trade-in</p>
                    </div>
                    
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="staffUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" id="staffUsername" value="<?=$user_details['username']?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="staffPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="staffPassword" placeholder="Enter your password to confirm">
                            </div>
                            
                            <div class="d-grid">
                                <button class="btn btn-primary" id="confirmStaffBtn">Confirm & Proceed</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Customer Selection -->
        <div class="step-container" id="step2">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Step 2: Customer Information</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="customerSearch" class="form-label">Search Customer</label>
                        <input type="text" class="form-control" id="customerSearch" placeholder="Search by name, email, or phone">
                        <div id="customerResults" class="mt-2"></div>
                    </div>
                    
                    <div class="mb-3">
                        <button class="btn btn-outline-primary" id="addNewCustomerBtn">Add New Customer</button>
                    </div>
                    
                    <div id="customerForm" style="display: none;">
                        <input type="hidden" id="customerId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customerName" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="customerName" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="customerPhone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="customerPhone">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="customerEmail" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="customerEmail">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customerAddress" class="form-label">Address</label>
                                    <textarea class="form-control" id="customerAddress" rows="3"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="customerPostcode" class="form-label">Postcode</label>
                                    <input type="text" class="form-control" id="customerPostcode">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="btn btn-secondary" id="prevStep2Btn">Previous</button>
                        <button class="btn btn-primary" id="nextStep2Btn">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: ID Verification -->
        <div class="step-container" id="step3">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Step 3: ID Verification</h4>
                </div>
                <div class="card-body">
                    <div class="compliance-section">
                        <h6><i class="fas fa-exclamation-triangle"></i> Scottish Compliance Requirements</h6>
                        <p>At least <strong>ONE</strong> form of ID is required to proceed</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-section">
                                <h6>Document 1: Photographic ID *</h6>
                                
                                <div class="mb-3">
                                    <label for="idDocumentType1" class="form-label">Photo ID Type *</label>
                                    <select class="form-select id-doc-type" id="idDocumentType1" required>
                                        <option value="">Select Photo ID Type</option>
                                        <option value="passport">Passport</option>
                                        <option value="driving_license">Driving License</option>
                                        <option value="national_id">National ID Card</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="idDocumentNumber1" class="form-label">Document Number</label>
                                    <input type="text" class="form-control" id="idDocumentNumber1" placeholder="e.g. Passport number">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Copy of Photo ID *</label>
                                    <div class="photo-upload-area">
                                        <input type="file" class="form-control id-photo-input" id="idPhoto1" accept="image/*" required>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCameraForId(1)">
                                                <i class="fas fa-camera"></i> Use Camera
                                            </button>
                                        </div>
                                        <div class="photo-preview" id="idPhotoPreview1"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-section">
                                <h6>Document 2: Proof of Address *</h6>
                                
                                <div class="mb-3">
                                    <label for="idDocumentType2" class="form-label">Proof of Address Type *</label>
                                    <select class="form-select id-doc-type" id="idDocumentType2" required>
                                        <option value="">Select Proof of Address Type</option>
                                        <option value="bank_statement">Bank Statement</option>
                                        <option value="council_tax">Council Tax Bill</option>
                                        <option value="utility_bill">Utility Bill</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="idDocumentNumber2" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="idDocumentNumber2" placeholder="e.g. Account number">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Copy of Proof of Address *</label>
                                    <div class="photo-upload-area">
                                        <input type="file" class="form-control id-photo-input" id="idPhoto2" accept="image/*" required>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCameraForId(2)">
                                                <i class="fas fa-camera"></i> Use Camera
                                            </button>
                                        </div>
                                        <div class="photo-preview" id="idPhotoPreview2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="complianceNotes" class="form-label">Compliance Notes</label>
                        <textarea class="form-control" id="complianceNotes" rows="2"></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="btn btn-secondary" id="prevStep3Btn">Previous</button>
                        <button class="btn btn-primary" id="nextStep3Btn">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Item Entry -->
        <div class="step-container" id="step4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Step 4: Item Entry</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Trade-In Items</h5>
                        <button class="btn btn-success" id="addItemBtn">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    
                    <div id="itemsContainer">
                        <!-- Items will be added dynamically -->
                    </div>
                    
                    <div class="total-section">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>Total Trade-In Value (Proposed):</h5>
                            <h5 id="totalAmount">£0.00</h5>
                        </div>
                        <small class="text-muted">Note: This is the proposed value. Final payment will be processed after testing and customer agreement.</small>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <button class="btn btn-secondary" id="prevStep4Btn">Previous</button>
                        <button class="btn btn-primary" id="nextStep4Btn">Review & Save</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 5: Save Trade-In -->
        <div class="step-container" id="step5">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Step 5: Save Trade-In</h4>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h5>Review Trade-In Details</h5>
                        <p>Please review all information before saving</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Customer Information</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Name:</strong> <span id="reviewCustomerName">-</span></p>
                                    <p><strong>Phone:</strong> <span id="reviewCustomerPhone">-</span></p>
                                    <p><strong>Email:</strong> <span id="reviewCustomerEmail">-</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Trade-In Summary</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Total Items:</strong> <span id="reviewTotalItems">0</span></p>
                                    <p><strong>Proposed Value:</strong> £<span id="reviewTotalValue">0.00</span></p>
                                    <p><strong>Status:</strong> <span class="badge bg-warning">Pending Testing</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Trade-In Items</h6>
                        <div id="reviewItemsList"></div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Next Steps:</h6>
                        <ol>
                            <li>Trade-in will be saved as <strong>PENDING</strong></li>
                            <li>Test the items to ensure they work</li>
                            <li>Update status to <strong>ACCEPTED</strong> or <strong>REJECTED</strong></li>
                            <li>If accepted, customer reviews and signs agreement</li>
                            <li>Process payment and complete trade-in</li>
                        </ol>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button class="btn btn-secondary" id="prevStep5Btn">Previous</button>
                        <button class="btn btn-danger" id="cancelTradeInBtn">Cancel</button>
                        <button class="btn btn-success" id="saveTradeInBtn">
                            <i class="fas fa-save"></i> Save Trade-In
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Camera Modal -->
    <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Take Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="videoElement" autoplay playsinline style="width: 100%; max-height: 400px;"></video>
                    <canvas id="canvasElement" style="display: none;"></canvas>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" id="capturePhotoBtn">
                            <i class="fas fa-camera"></i> Capture
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function(){
        // Global variables
        let currentStep = 1;
        let itemCounter = 0;
        let currentCameraTarget = null;
        let stream = null;
        let tradeInData = {
            customer: {},
            items: [],
            ids: [],
            payment: {}
        };
        
        // Tracking codes are now generated server-side when saving
        // This prevents race conditions and ensures correct sequential numbering
        
        // Step navigation functions
        function goToStep(step) {
            $('.step-container').removeClass('active');
            $('#step' + step).addClass('active');
            
            $('.step').removeClass('active completed');
            for(let i = 1; i <= 5; i++) {
                if(i < step) {
                    $('#step' + i + '-indicator').addClass('completed');
                } else if(i === step) {
                    $('#step' + i + '-indicator').addClass('active');
                }
            }
            
            currentStep = step;
            window.scrollTo(0, 0);
        }
        
        // Step 1: Staff Authentication
        $('#confirmStaffBtn').click(function() {
            const password = $('#staffPassword').val();
            if(!password) {
                Swal.fire('Error', 'Please enter your password', 'error');
                return;
            }
            // For now, just proceed - you can add actual validation if needed
            goToStep(2);
        });
        
        // Step 2: Customer Search
        let searchTimeout;
        $('#customerSearch').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            
            if(query.length < 2) {
                $('#customerResults').empty();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: '../ajax/search_customers.php',
                    method: 'POST',
                    data: { search: query },
                    dataType: 'json',
                    success: function(customers) {
                        let html = '';
                        if(customers.length === 0) {
                            html = '<div class="alert alert-info">No customers found</div>';
                        } else {
                            html = '<div class="list-group">';
                            customers.forEach(function(c) {
                                html += `
                                    <a href="#" class="list-group-item list-group-item-action customer-search-result"
                                       data-id="${c.id}"
                                       data-name="${c.name || ''}"
                                       data-phone="${c.phone || c.mobile || ''}"
                                       data-email="${c.email || ''}"
                                       data-address="${c.address || ''}"
                                       data-postcode="${c.post_code || ''}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">${c.name}</h6>
                                        </div>
                                        <small>${c.phone || c.mobile || 'No phone'} | ${c.email || 'No email'}</small>
                                    </a>
                                `;
                            });
                            html += '</div>';
                        }
                        $('#customerResults').html(html);
                    },
                    error: function() {
                        $('#customerResults').html('<div class="alert alert-danger">Error searching customers</div>');
                    }
                });
            }, 300);
        });
        
        // Select customer from search
        $(document).on('click', '.customer-search-result', function(e) {
            e.preventDefault();
            const $this = $(this);
            
            $('#customerId').val($this.data('id'));
            $('#customerName').val($this.data('name'));
            $('#customerPhone').val($this.data('phone'));
            $('#customerEmail').val($this.data('email'));
            $('#customerAddress').val($this.data('address'));
            $('#customerPostcode').val($this.data('postcode'));
            
            $('#customerForm').show();
            $('#customerResults').empty();
            $('#customerSearch').val('');
            
            tradeInData.customer = {
                id: $this.data('id'),
                name: $this.data('name'),
                phone: $this.data('phone'),
                email: $this.data('email'),
                address: $this.data('address'),
                postcode: $this.data('postcode')
            };
        });
        
        // Add new customer
        $('#addNewCustomerBtn').click(function() {
            $('#customerForm').show();
            $('#customerId').val('');
            $('#customerName').val('');
            $('#customerPhone').val('');
            $('#customerEmail').val('');
            $('#customerAddress').val('');
            $('#customerPostcode').val('');
        });
        
        // Step 2 navigation
        $('#prevStep2Btn').click(function() {
            goToStep(1);
        });
        
        $('#nextStep2Btn').click(function() {
            const name = $('#customerName').val().trim();
            if(!name) {
                Swal.fire('Error', 'Customer name is required', 'error');
                return;
            }
            
            tradeInData.customer = {
                id: $('#customerId').val() || null,
                name: name,
                phone: $('#customerPhone').val(),
                email: $('#customerEmail').val(),
                address: $('#customerAddress').val(),
                postcode: $('#customerPostcode').val()
            };
            
            goToStep(3);
        });
        
        // Step 3: ID Verification
        function validateIDStep() {
            let hasAtLeastOneID = false;
            
            // Check if at least one ID type and photo is provided
            for(let i = 1; i <= 2; i++) {
                const type = $('#idDocumentType' + i).val();
                const hasPhoto = $('#idPhotoPreview' + i).children().length > 0;
                
                if(type && hasPhoto) {
                    hasAtLeastOneID = true;
                    break;
                }
            }
            
            return hasAtLeastOneID;
        }
        
        // ID photo upload handlers
        $('.id-photo-input').on('change', function() {
            const idNum = this.id.replace('idPhoto', '');
            const file = this.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const html = `
                        <div class="photo-preview-item">
                            <img src="${e.target.result}" alt="ID Photo">
                            <button type="button" class="remove-photo" onclick="removeIdPhoto(${idNum})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    $('#idPhotoPreview' + idNum).html(html);
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Step 3 navigation
        $('#prevStep3Btn').click(function() {
            goToStep(2);
        });
        
        $('#nextStep3Btn').click(function() {
            if(!validateIDStep()) {
                Swal.fire('Error', 'At least ONE form of ID with photo is required', 'error');
                return;
            }
            
            // Collect ID data
            tradeInData.ids = [];
            for(let i = 1; i <= 2; i++) {
                const type = $('#idDocumentType' + i).val();
                const number = $('#idDocumentNumber' + i).val();
                const hasPhoto = $('#idPhotoPreview' + i).children().length > 0;
                
                if(type && hasPhoto) {
                    tradeInData.ids.push({
                        type: type,
                        number: number,
                        photoFile: $('#idPhoto' + i)[0].files[0]
                    });
                }
            }
            
            tradeInData.complianceNotes = $('#complianceNotes').val();
            
            // Add first item
            addItem();
            goToStep(4);
        });
        
        // Step 4: Item Entry
        function addItem() {
            itemCounter++;
            
            // Don't generate tracking codes here - they'll be generated server-side when saving
            // This prevents showing wrong codes if another trade-in is saved in the meantime
            
            const html = `
                <div class="trade-in-item" id="itemRow${itemCounter}">
                    <div class="d-flex justify-content-between mb-2">
                        <h6>Item #${itemCounter}</h6>
                        <button type="button" class="btn btn-danger btn-sm removeItemBtn" data-item-id="${itemCounter}">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                    
                    <!-- No tracking code field - codes generated server-side on save -->
                    
                    <!-- Generated code notice (only shown if no preprinted code) -->
                    <div class="alert alert-info generated-code-display" id="generatedCodeDisplay${itemCounter}" style="display: none; border: 2px solid #2196f3;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><i class="fas fa-info-circle"></i> No Barcode Entered</strong>
                                <p class="mb-0 mt-1"><small>A tracking code will be generated when you save this trade-in</small></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-7">
                                    <div class="mb-3">
                                        <label class="form-label">Item Name *</label>
                                        <input type="text" class="form-control item-name" data-item-id="${itemCounter}" required>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="mb-3">
                                        <label class="form-label">Proposed Value (£) *</label>
                                        <input type="number" class="form-control item-cost" data-item-id="${itemCounter}" step="0.01" min="0" required>
                                        <small class="text-muted">Initial offer</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select item-category" data-item-id="${itemCounter}">
                                            <option value="">Select Category</option>
                                            <?php foreach($categories as $category): ?>
                                            <option value="<?=htmlspecialchars($category['pos_category'])?>"><?=htmlspecialchars($category['pos_category'])?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Serial Number</label>
                                        <input type="text" class="form-control item-serial" data-item-id="${itemCounter}">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control item-notes" data-item-id="${itemCounter}" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="alert alert-warning mb-0 p-3" style="border: 2px solid #ff9800; background-color: #fff3cd;">
                                    <label class="form-label fw-bold mb-2" style="font-size: 1rem; color: #e65100;">
                                        <i class="fas fa-barcode"></i> PREPRINTED BARCODE
                                    </label>
                                    <input type="text" class="form-control item-preprinted fw-bold" data-item-id="${itemCounter}" placeholder="DSH1, DSH2..." style="border: 2px solid #ff9800; font-size: 1.1rem; text-align: center;">
                                    <small class="form-text d-block mt-2" style="color: #e65100;">
                                        <i class="fas fa-info-circle"></i> If item has a barcode sticker, enter it here!
                                    </small>
                                </div>
                            </div>
                            
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> <strong>Note:</strong> Item condition will be assessed during testing phase.
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Photos</label>
                                <div class="photo-upload-area">
                                    <input type="file" class="form-control item-photo-input" data-item-id="${itemCounter}" accept="image/*" multiple>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCameraForItem(${itemCounter})">
                                            <i class="fas fa-camera"></i> Use Camera
                                        </button>
                                    </div>
                                    <div class="photo-preview" id="itemPhotoPreview${itemCounter}"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#itemsContainer').append(html);
            
            // Bind events for the new item
            $(`[data-item-id="${itemCounter}"].item-cost`).on('input', calculateTotal);
            
            // Toggle generated code display based on preprinted code
            $(`[data-item-id="${itemCounter}"].item-preprinted`).on('input', function() {
                const itemId = $(this).data('item-id');
                const preprintedValue = $(this).val().trim();
                
                if (preprintedValue === '') {
                    // No preprinted code - show generated code
                    $(`#generatedCodeDisplay${itemId}`).slideDown();
                } else {
                    // Has preprinted code - hide generated code
                    $(`#generatedCodeDisplay${itemId}`).slideUp();
                }
            });
        }
        
        // Add item button
        $('#addItemBtn').click(function() {
            addItem();
        });
        
        // Remove item
        $(document).on('click', '.removeItemBtn', function() {
            const itemId = $(this).data('item-id');
            if($('.trade-in-item').length > 1) {
                $('#itemRow' + itemId).remove();
                calculateTotal();
                // Note: We don't decrement nextTrackingNumber - it's ok to have gaps (SH1, SH3, SH4)
                // This ensures codes remain unique even if items are added/removed
            } else {
                Swal.fire('Error', 'At least one item is required', 'error');
            }
        });
        
        // Item photo upload
        $(document).on('change', '.item-photo-input', function() {
            const itemId = $(this).data('item-id');
            const files = this.files;
            const previewContainer = $('#itemPhotoPreview' + itemId);
            
            // Clear existing previews
            previewContainer.empty();
            
            // Display all selected photos
            Array.from(files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const html = `
                        <div class="photo-preview-item" data-photo-index="${index}">
                            <img src="${e.target.result}" alt="Item Photo">
                            <button type="button" class="remove-photo" onclick="removeItemPhoto(${itemId}, ${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    previewContainer.append(html);
                };
                reader.readAsDataURL(file);
            });
        });
        
        // Calculate total
        function calculateTotal() {
            let total = 0;
            $('.item-cost').each(function() {
                const val = parseFloat($(this).val()) || 0;
                total += val;
            });
            $('#totalAmount').text('£' + total.toFixed(2));
        }
        
        // Step 4 navigation
        $('#prevStep4Btn').click(function() {
            goToStep(3);
        });
        
        $('#nextStep4Btn').click(function() {
            // Validate items
            let hasItems = false;
            let allItemsValid = true;
            let allCostsValid = true;
            
            $('.trade-in-item').each(function() {
                hasItems = true;
                const itemId = $(this).find('.item-name').data('item-id');
                const name = $(this).find('.item-name').val().trim();
                const cost = parseFloat($(this).find('.item-cost').val());
                
                if(!name) {
                    allItemsValid = false;
                    return false;
                }
                
                if(!cost || cost <= 0) {
                    allCostsValid = false;
                    return false;
                }
            });
            
            if(!hasItems) {
                Swal.fire('Error', 'At least one item is required', 'error');
                return;
            }
            
            if(!allItemsValid) {
                Swal.fire('Error', 'Please ensure all items have a name', 'error');
                return;
            }
            
            if(!allCostsValid) {
                Swal.fire('Error', 'Please enter a proposed value for all items', 'error');
                return;
            }
            
            // Check for items missing preprinted barcodes
            const itemsWithoutBarcodes = [];
            $('.trade-in-item').each(function() {
                const name = $(this).find('.item-name').val();
                const preprintedCode = $(this).find('.item-preprinted').val().trim();
                
                if (!preprintedCode) {
                    itemsWithoutBarcodes.push({
                        name: name
                    });
                }
            });
            
            // If any items are missing preprinted barcodes, warn them
            if (itemsWithoutBarcodes.length > 0) {
                const itemNames = itemsWithoutBarcodes.map(item => item.name).join(', ');
                
                let warningHtml = '<p><strong>You haven\'t entered Pre-Printed Barcodes for:</strong></p>';
                warningHtml += `<div class="alert alert-warning"><strong>${itemNames}</strong></div>`;
                warningHtml += '<hr>';
                warningHtml += '<p><i class="fas fa-info-circle text-info"></i> <strong>Tracking codes will be automatically generated when you save.</strong></p>';
                warningHtml += '<p class="text-muted"><small>You will see the generated codes after saving so you can apply them to the physical items.</small></p>';
                
                Swal.fire({
                    icon: 'warning',
                    title: 'Pre-Printed Barcodes Missing',
                    html: warningHtml,
                    width: '600px',
                    showCancelButton: true,
                    confirmButtonText: 'Proceed to Review',
                    cancelButtonText: 'Go Back and Add Barcodes',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // User chose to proceed
                        proceedToReview();
                    }
                    // If cancelled, stay on items screen
                });
            } else {
                // All items have preprinted codes, proceed directly
                proceedToReview();
            }
        });
        
        function proceedToReview() {
            // Collect items data
            tradeInData.items = [];
            $('.trade-in-item').each(function() {
                const itemId = $(this).find('.item-name').data('item-id');
                const itemData = {
                    name: $(this).find('.item-name').val(),
                    category: $(this).find('.item-category').val(),
                    serial: $(this).find('.item-serial').val(),
                    notes: $(this).find('.item-notes').val(),
                    preprintedCode: $(this).find('.item-preprinted').val(),
                    // trackingCode will be generated server-side
                    condition: null, // Set during testing phase
                    cost: parseFloat($(this).find('.item-cost').val()) || 0,
                    photos: $(this).find('.item-photo-input')[0].files
                };
                tradeInData.items.push(itemData);
            });
            
            // Populate review
            $('#reviewCustomerName').text(tradeInData.customer.name);
            $('#reviewCustomerPhone').text(tradeInData.customer.phone || 'N/A');
            $('#reviewCustomerEmail').text(tradeInData.customer.email || 'N/A');
            $('#reviewTotalItems').text(tradeInData.items.length);
            
            const totalValue = tradeInData.items.reduce((sum, item) => sum + item.cost, 0);
            $('#reviewTotalValue').text(totalValue.toFixed(2));
            
            let itemsHtml = '<div class="list-group">';
            tradeInData.items.forEach((item, index) => {
                itemsHtml += `
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${item.name}</h6>
                            <small>£${item.cost.toFixed(2)}</small>
                        </div>
                        <small class="text-muted">
                            ${item.trackingCode ? 'Code: ' + item.trackingCode + ' | ' : ''}
                            ${item.category ? 'Category: ' + item.category + ' | ' : ''}
                            ${item.serial ? 'S/N: ' + item.serial : ''}
                            <span class="badge bg-warning text-dark">Pending Testing</span>
                        </small>
                    </div>
                `;
            });
            itemsHtml += '</div>';
            $('#reviewItemsList').html(itemsHtml);
            
            goToStep(5);
        }
        
        // Cancel trade-in
        $('#cancelTradeInBtn').click(function() {
            Swal.fire({
                title: 'Cancel Trade-In?',
                text: 'Are you sure you want to cancel this trade-in? All data will be lost.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it'
            }).then((result) => {
                if(result.isConfirmed) {
                    window.location.href = 'trade_in_management.php';
                }
            });
        });
        
        $('#prevStep5Btn').click(function() {
            goToStep(4);
        });
        
        // Step 5: Save Trade-In
        $('#saveTradeInBtn').click(function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            
            saveTradeIn(function(tradeInId, trackingCodes) {
                // Check if there are any generated tracking codes to display
                const generatedCodes = [];
                if (trackingCodes && trackingCodes.length > 0) {
                    trackingCodes.forEach(code => {
                        if (!code.preprinted_code && code.tracking_code) {
                            generatedCodes.push({
                                item: code.item_name,
                                code: code.tracking_code
                            });
                        }
                    });
                }
                
                // If there are generated codes, show them prominently
                if (generatedCodes.length > 0) {
                    let codesHtml = '<p><strong>Apply these Generated Tracking Codes to the items:</strong></p>';
                    codesHtml += '<table class="table table-bordered mt-3">';
                    codesHtml += '<thead class="table-primary"><tr><th>Item</th><th>Tracking Code to Apply</th></tr></thead>';
                    codesHtml += '<tbody>';
                    
                    generatedCodes.forEach(item => {
                        codesHtml += `<tr>
                            <td>${item.item}</td>
                            <td class="text-center"><strong style="font-size: 22px; color: #0d47a1; background: #e3f2fd; padding: 8px 20px; border: 2px solid #2196f3; border-radius: 4px;">${item.code}</strong></td>
                        </tr>`;
                    });
                    
                    codesHtml += '</tbody></table>';
                    codesHtml += '<p class="text-muted mt-3"><small><i class="fas fa-info-circle"></i> Write these codes on labels and apply them to the physical items before putting them in stock.</small></p>';
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Trade-In Saved!',
                        html: codesHtml,
                        width: '700px',
                        confirmButtonText: 'Go to Management'
                    }).then(() => {
                        window.location.href = 'trade_in_management.php';
                    });
                } else {
                    // All items had preprinted codes
                    Swal.fire({
                        icon: 'success',
                        title: 'Trade-In Saved!',
                        text: 'Trade-in #' + tradeInId + ' has been saved as PENDING. You can now test the items.',
                        confirmButtonText: 'Go to Management'
                    }).then(() => {
                        window.location.href = 'trade_in_management.php';
                    });
                }
            });
        });
        
        // Save trade-in function
        function saveTradeIn(callback) {
            const formData = new FormData();
            
            console.log('=== Starting saveTradeIn ===');
            console.log('Customer data:', tradeInData.customer);
            console.log('Items:', tradeInData.items);
            
            // Customer data
            Object.keys(tradeInData.customer).forEach(key => {
                formData.append('customer_' + key, tradeInData.customer[key] || '');
            });
            
            // ID documents
            tradeInData.ids.forEach((id, index) => {
                formData.append('id_type_' + index, id.type);
                formData.append('id_number_' + index, id.number || '');
                formData.append('id_photo_' + index, id.photoFile);
            });
            formData.append('compliance_notes', tradeInData.complianceNotes || '');
            
            // Items
            tradeInData.items.forEach((item, index) => {
                formData.append('item_name_' + index, item.name);
                formData.append('item_category_' + index, item.category || '');
                formData.append('item_serial_' + index, item.serial || '');
                formData.append('item_notes_' + index, item.notes || '');
                formData.append('item_preprinted_' + index, item.preprintedCode || '');
                // Don't send tracking code - will be generated server-side
                formData.append('item_condition_' + index, item.condition);
                formData.append('item_cost_' + index, item.cost);
                
                // Photos
                if(item.photos && item.photos.length > 0) {
                    for(let i = 0; i < item.photos.length; i++) {
                        formData.append('item_photos_' + index + '[]', item.photos[i]);
                    }
                }
            });
            
            formData.append('location', '<?=$effective_location?>');
            
            $.ajax({
                url: 'ajax/save_trade_in_complete.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        callback(response.trade_in_id, response.tracking_codes);
                    } else {
                        console.error('Save failed:', response);
                        Swal.fire('Error', response.message || 'Failed to save trade-in', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    console.error('Status:', status);
                    console.error('Error:', error);
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        Swal.fire('Error', response.message || 'Failed to save trade-in', 'error');
                    } catch(e) {
                        Swal.fire('Error', 'Server error: ' + xhr.responseText.substring(0, 200), 'error');
                    }
                }
            });
        }
        
        // Cancel trade-in
        $('#cancelTradeInBtn').click(function() {
            Swal.fire({
                title: 'Cancel Trade-In?',
                text: 'Are you sure you want to cancel this trade-in? All data will be lost.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it'
            }).then((result) => {
                if(result.isConfirmed) {
                    window.location.href = 'trade_in.php';
                }
            });
        });
        
        $('#prevStep5Btn').click(function() {
            goToStep(4);
        });
        
        // Camera functions
        function openCamera() {
            const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
            modal.show();
            
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(s) {
                    stream = s;
                    document.getElementById('videoElement').srcObject = stream;
                })
                .catch(function(err) {
                    Swal.fire('Error', 'Cannot access camera', 'error');
                });
        }
        
        window.openCameraForId = function(idNum) {
            currentCameraTarget = 'id' + idNum;
            openCamera();
        };
        
        window.openCameraForItem = function(itemId) {
            currentCameraTarget = 'item' + itemId;
            openCamera();
        };
        
        $('#capturePhotoBtn').click(function() {
            const video = document.getElementById('videoElement');
            const canvas = document.getElementById('canvasElement');
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);
            
            canvas.toBlob(function(blob) {
                const file = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                
                if(currentCameraTarget.startsWith('id')) {
                    const idNum = currentCameraTarget.replace('id', '');
                    const input = document.getElementById('idPhoto' + idNum);
                    input.files = dataTransfer.files;
                    $(input).trigger('change');
                } else if(currentCameraTarget.startsWith('item')) {
                    const itemId = currentCameraTarget.replace('item', '');
                    const input = $('[data-item-id="' + itemId + '"].item-photo-input')[0];
                    input.files = dataTransfer.files;
                    $(input).trigger('change');
                } else if(currentCameraTarget === 'signedDoc') {
                    const input = document.getElementById('signedDocumentPhoto');
                    input.files = dataTransfer.files;
                    $(input).trigger('change');
                }
                
                $('#cameraModal').modal('hide');
            });
        });
        
        $('#cameraModal').on('hidden.bs.modal', function() {
            if(stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        });
        
        // Remove photo functions
        window.removeIdPhoto = function(idNum) {
            $('#idPhotoPreview' + idNum).empty();
            $('#idPhoto' + idNum).val('');
        };
        
        window.removeItemPhoto = function(itemId, photoIndex) {
            // For simplicity, clear all photos and require re-upload
            $('#itemPhotoPreview' + itemId).empty();
            $('[data-item-id="' + itemId + '"].item-photo-input').val('');
        };
        
        window.removeSignedDoc = function() {
            $('#signedDocPreview').empty();
            $('#signedDocumentPhoto').val('');
            $('#completeTradeInBtn').prop('disabled', true);
        };
    });
    </script>
</body>
</html>
