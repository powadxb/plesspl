<?php
// ================ PHP INITIALIZATION SECTION START ================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'php/bootstrap.php';
require_once 'functions.php';

if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0] ?? null;

if (!$user_details) {
    die("User not found. Please log in again.");
}

$page_title = 'Trade-In Management';
require_once 'assets/header.php';
require_once 'assets/navbar.php';
// ================ PHP INITIALIZATION SECTION END ================
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ================ META SECTION START ================ -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade-In Management</title>
    <!-- ================ META SECTION END ================ -->

    <!-- ================ CSS INCLUDES SECTION START ================ -->
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- ================ CSS INCLUDES SECTION END ================ -->

    <!-- ================ CUSTOM STYLES SECTION START ================ -->
    <style>
        /* Layout styles */
        .page-container3 { 
            padding: 20px; 
        }
        .title-4 { 
            margin-bottom: 1rem; 
        }
        .line-seprate { 
            margin: 1.5rem 0; 
        }

        /* Customer search styles */
        .customer-search-wrapper {
            position: relative;
        }
        #customerSearchResults {
            position: absolute;
            z-index: 1050;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background-color: white;
        }

        /* Condition badge styles */
        .condition-New { background-color: #28a745; color: white; }
        .condition-Good { background-color: #17a2b8; color: white; }
        .condition-Fair { background-color: #ffc107; color: black; }
        .condition-Poor { background-color: #dc3545; color: white; }

        /* Progress steps styles */
        .step-progress {
            display: flex;
            margin: 2rem 0;
        }
        .step-item {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step-item:not(:last-child):after {
            content: '';
            position: absolute;
            right: -50%;
            top: 1rem;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        .step-circle {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: #fff;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            position: relative;
            z-index: 2;
        }
        .step-active .step-circle {
            border-color: #0d6efd;
            background-color: #0d6efd;
            color: white;
        }
        .step-complete .step-circle {
            background-color: #198754;
            border-color: #198754;
            color: white;
        }
        .step-complete:after {
            background-color: #198754;
        }

        /* Preview image styles */
        .preview-image-container {
            position: relative;
            display: inline-block;
            margin: 5px;
        }
        .preview-image {
            max-width: 150px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        .remove-file {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            line-height: 20px;
            text-align: center;
            cursor: pointer;
            font-size: 12px;
        }

        /* Camera preview styles */
        #camera {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }
        #photoPreview img {
            max-width: 100%;
            height: auto;
        }
        
        /* Modal adjustments */
        .modal-xl {
            max-width: 95%;
        }
        .form-section {
            display: none;
        }
        .form-section.active {
            display: block;
        }
    </style>
    <!-- ================ CUSTOM STYLES SECTION END ================ -->
</head>

<body>
    <!-- ================ MAIN CONTENT SECTION START ================ -->
  <div class="page-container3">
        <!-- ================ HEADER SECTION START ================ -->
        <section class="welcome p-t-20">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="title-4">Trade-In Management</h1>
                        <hr class="line-seprate">
                    </div>
                </div>
            </div>
        </section>
        <!-- ================ HEADER SECTION END ================ -->

        <!-- ================ TRADE-IN LIST SECTION START ================ -->
        <section class="p-t-20">
            <div class="container">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tradeInModal">
                            <i class="fas fa-plus"></i> New Trade-In
                        </button>
                    </div>
                    <div class="col-md-9">
                        <form id="searchForm" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="searchSku" 
                                       placeholder="SKU/Serial Number">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="searchCustomer" 
                                       placeholder="Customer Name">
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="searchCondition">
                                    <option value="">All Conditions</option>
                                    <option value="New">New</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Trade-in Items Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Custom SKU</th>
                                        <th>Serial Number</th>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Condition</th>
                                        <th>Customer</th>
                                        <th>Trade-In Date</th>
                                        <th class="text-end">Purchase Price</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="tradeInList">
                                    <!-- Populated via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <div id="pagination" class="mt-4 d-flex justify-content-center">
                            <!-- Pagination controls added via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- ================ TRADE-IN LIST SECTION END ================ -->
    </div>

    <!-- ================ MODALS SECTION START ================ -->
    <!-- Main Trade-In Modal -->
    <div class="modal fade" id="tradeInModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">New Trade-In</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" 
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Progress Steps -->
                    <div class="step-progress mb-4">
                        <div class="step-item step-active">
                            <div class="step-circle">1</div>
                            <div class="step-text">Seller Details</div>
                        </div>
                        <div class="step-item">
                            <div class="step-circle">2</div>
                            <div class="step-text">Documents</div>
                        </div>
                        <div class="step-item">
                            <div class="step-circle">3</div>
                            <div class="step-text">Items</div>
                        </div>
                        <div class="step-item">
                            <div class="step-circle">4</div>
                            <div class="step-text">Review</div>
                        </div>
                    </div>

                    <!-- Form Sections -->
                    <div id="tradeInForms">
                        <!-- Seller Details Section -->
                        <div id="sellerSection" class="form-section active">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Search Existing Customer</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group mb-3">
                                                <div class="customer-search-wrapper">
                                                    <input type="text" class="form-control" id="customerSearch" 
                                                           placeholder="Search by name, phone, or email...">
                                                    <div id="customerSearchResults" class="list-group mt-2" 
                                                         style="display:none;"></div>
                                                </div>
                                            </div>
                                            <div id="selectedCustomerDetails"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Or Add New Customer</h5>
                                        </div>
                                        <div class="card-body">
                                            <form id="sellerForm" class="needs-validation" novalidate>
                                                <input type="hidden" id="customerId" name="customer_id">
                                                <div class="form-group mb-3">
                                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="name" required>
                                                </div>
                                                <div class="form-group mb-3">
                                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                                    <input type="tel" class="form-control" name="phone" required>
                                                </div>
                                                <div class="form-group mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="email">
                                                </div>
                                                <div class="form-group mb-3">
                                                    <label class="form-label">Address</label>
                                                    <textarea class="form-control" name="address" rows="2"></textarea>
                                                </div>
                                                <div class="form-group mb-3">
                                                    <label class="form-label">Post Code <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="post_code" required>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Section -->
                        <div id="documentsSection" class="form-section" style="display:none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">ID Document</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label d-block">Capture ID</label>
                                                <button type="button" class="btn btn-primary me-2" onclick="startCamera('id')">
                                                    <i class="fas fa-camera"></i> Take Photo
                                                </button>
                                                <input type="file" class="form-control mt-2" name="id_document" 
                                                       accept="image/*">
                                            </div>
                                            <div id="idPreview" class="mt-3"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Additional Documents</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label d-block">Add Documents</label>
                                                <button type="button" class="btn btn-primary me-2" onclick="startCamera('doc')">
                                                    <i class="fas fa-camera"></i> Take Photo
                                                </button>
                                                <input type="file" class="form-control mt-2" name="additional_docs[]" 
                                                       multiple accept="image/*,.pdf">
                                            </div>
                                            <div id="docsPreview" class="mt-3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Section -->
                        <div id="itemsSection" class="form-section" style="display:none;">
                            <div class="d-flex justify-content-between mb-3">
                                <h5>Trade-In Items</h5>
                                <button type="button" class="btn btn-primary" onclick="showAddItemModal()">
                                    <i class="fas fa-plus"></i> Add Item
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th>Serial Number</th>
                                            <th>Condition</th>
                                            <th>Price</th>
                                            <th>Photos</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsList"></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                            <td id="totalPrice" class="text-end">£0.00</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Review Section -->
                        <div id="reviewSection" class="form-section" style="display:none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Seller Details</h5>
                                        </div>
                                        <div class="card-body" id="reviewSellerDetails"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Documents</h5>
                                        </div>
                                        <div class="card-body" id="reviewDocuments"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Items Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div id="reviewItems"></div>
                                    <div class="text-end mt-3">
                                        <h5>Total Value: <span id="reviewTotal">£0.00</span></h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="previousStep()">Previous</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                    <button type="button" class="btn btn-success" onclick="submitTradeIn()" style="display:none;">
                        Complete Trade-In
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
   <!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <div class="form-group mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Custom SKU</label>
                        <input type="text" class="form-control" name="custom_sku" placeholder="Optional custom SKU">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-control" name="category" id="categorySelect" required>
                            <option value="">Select Category...</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" class="form-control" name="serial_number">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Condition <span class="text-danger">*</span></label>
                        <select class="form-control" name="condition_rating" required>
                            <option value="New">New</option>
                            <option value="Good" selected>Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Purchase Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">£</span>
                            <input type="number" class="form-control" name="purchase_price" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Item Photos</label>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" onclick="startCamera('item')">
                                <i class="fas fa-camera"></i> Take Photo
                            </button>
                            <input type="file" class="form-control" name="item_photos[]" multiple accept="image/*">
                        </div>
                        <div id="itemPhotosPreview" class="mt-2"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addItem()">Add Item</button>
            </div>
        </div>
    </div>
</div>
    <!-- Camera Modal -->
    <div class="modal fade" id="cameraModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Take Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <video id="camera" style="width: 100%; display: none;"></video>
                    <canvas id="photoCanvas" style="display: none;"></canvas>
                    <div id="photoPreview"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="takePhoto">
                        <i class="fas fa-camera"></i> Capture
                    </button>
                    <button type="button" class="btn btn-success" id="savePhoto" style="display:none;">
                        Use Photo
                    </button>
                    <button type="button" class="btn btn-danger" id="retakePhoto" style="display:none;">
                        Retake
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- ================ MODALS SECTION END ================ -->

    <!-- ================ JAVASCRIPT INCLUDES SECTION START ================ -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- ================ JAVASCRIPT INCLUDES SECTION END ================ -->
  <!-- ================ CUSTOM JAVASCRIPT SECTION START ================ -->
    <script>
// ================ GLOBAL VARIABLES SECTION START ================
let currentStep = 1;
let tradeInItems = [];
let currentCamera = null;
let currentCameraMode = null;
// ================ GLOBAL VARIABLES SECTION END ================

// ================ INITIALIZATION SECTION START ================
$(document).ready(function() {
    // Initialize Select2 for category selection
    $('#categorySelect').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search category...',
        dropdownParent: $('#addItemModal')
    });

    // Load categories from master_categories
    loadCategories();

    // Initialize trade-ins table
    loadTradeIns();

    // Initialize customer search functionality
    setupCustomerSearch();

    // Add search form handler
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        loadTradeIns(1); // Reset to first page when searching
    });

    // Add handlers for individual search inputs
    $('#searchSku, #searchCustomer, #searchCondition').on('change', function() {
        loadTradeIns(1);
    });

    // Clear form when trade-in modal is hidden
    $('#tradeInModal').on('hidden.bs.modal', function() {
        resetTradeInForm();
    });

    // Clear item form when item modal is hidden
    $('#addItemModal').on('hidden.bs.modal', function() {
        $('#itemForm')[0].reset();
        $('#itemPhotosPreview').empty();
        $('#categorySelect').val('').trigger('change');
    });

    // Camera modal events
    $('#takePhoto').on('click', capturePhoto);
    $('#retakePhoto').on('click', retakePhoto);
    $('#savePhoto').on('click', savePhoto);

    // Stop camera when camera modal is closed
    $('#cameraModal').on('hidden.bs.modal', stopCamera);

    // Initialize current date for forms
    $('[name="trade_in_date"]').val(new Date().toISOString().split('T')[0]);
});
// ================ INITIALIZATION SECTION END ================

// ================ STEP MANAGEMENT SECTION START ================
function nextStep() {
    if (validateCurrentStep()) {
        if (currentStep < 4) {
            currentStep++;
            updateStepDisplay();
        }
    }
}

function previousStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepDisplay();
    }
}

function updateStepDisplay() {
    // Update progress bar and step indicators
    $('.step-item').removeClass('step-active step-complete');
    for (let i = 1; i <= currentStep; i++) {
        $(`.step-item:nth-child(${i})`).addClass(i === currentStep ? 'step-active' : 'step-complete');
    }

    // Hide all sections and show current
    $('.form-section').hide();
    let currentSection;
    switch(currentStep) {
        case 1:
            currentSection = '#sellerSection';
            $('.modal-footer .btn-success').hide();
            $('.modal-footer .btn-primary').show();
            $('.modal-footer .btn-secondary').hide();
            break;
        case 2:
            currentSection = '#documentsSection';
            $('.modal-footer .btn-success').hide();
            $('.modal-footer .btn-primary').show();
            $('.modal-footer .btn-secondary').show();
            break;
        case 3:
            currentSection = '#itemsSection';
            $('.modal-footer .btn-success').hide();
            $('.modal-footer .btn-primary').show();
            $('.modal-footer .btn-secondary').show();
            break;
        case 4:
            currentSection = '#reviewSection';
            updateReviewSection();
            $('.modal-footer .btn-primary').hide();
            $('.modal-footer .btn-success').show();
            $('.modal-footer .btn-secondary').show();
            break;
    }
    $(currentSection).show();
}

function validateCurrentStep() {
    switch(currentStep) {
        case 1: // Seller Details
            if (!$('#customerId').val() && !$('#sellerForm input[name="name"]').val()) {
                Swal.fire('Error', 'Please select an existing customer or enter new customer details', 'error');
                return false;
            }
            return true;

        case 2: // Documents
            const hasIdDoc = $('#idPreview').children().length > 0 || $('[name="id_document"]')[0].files.length > 0;
            if (!hasIdDoc) {
                Swal.fire('Error', 'Please provide at least one form of ID', 'error');
                return false;
            }
            return true;

        case 3: // Items
            if (tradeInItems.length === 0) {
                Swal.fire('Error', 'Please add at least one item', 'error');
                return false;
            }
            return true;

        default:
            return true;
    }
}

// ================ STEP MANAGEMENT SECTION END ================

// ================ CUSTOMER SEARCH SECTION START ================
function setupCustomerSearch() {
    let searchTimeout;

    // If no customer is selected on page load, ensure form is enabled
    if (!$('#customerId').val()) {
        $('#sellerForm input, #sellerForm textarea').prop('disabled', false);
    }

    $('#customerSearch').on('input', function() {
        clearTimeout(searchTimeout);
        const search = $(this).val();
        
        if (search.length < 2) {
            $('#customerSearchResults').hide();
            return;
        }

        searchTimeout = setTimeout(() => {
            $.post('ajax/search_customers.php', { search: search })
                .done(function(response) {
                    try {
                        const results = typeof response === 'string' ? JSON.parse(response) : response;
                        displayCustomerResults(results);
                    } catch (e) {
                        console.error('Error parsing results:', e);
                    }
                })
                .fail(function(xhr) {
                    console.error('Search request failed:', xhr.responseText);
                });
        }, 300);
    });
}

function displayCustomerResults(results) {
    const container = $('#customerSearchResults');
    container.empty();

    if (results.length === 0) {
        container.html('<div class="list-group-item">No customers found</div>');
        container.show();
        return;
    }

    results.forEach(customer => {
        const phone = customer.phone || customer.mobile || 'No phone';
        const element = $(`
            <a href="#" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${escapeHtml(customer.name)}</h6>
                </div>
                <div class="small">
                    <i class="fas fa-phone me-1"></i>${escapeHtml(phone)}
                    ${customer.email ? `<span class="mx-2">|</span><i class="fas fa-envelope me-1"></i>${escapeHtml(customer.email)}` : ''}
                    <br>
                    <i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(customer.post_code)}
                    ${customer.address ? `- ${escapeHtml(customer.address)}` : ''}
                </div>
            </a>
        `);
        
        element.click(function(e) {
            e.preventDefault();
            selectCustomer(customer);
        });
        
        container.append(element);
    });
    container.show();
}

function selectCustomer(customer) {
    $('#customerId').val(customer.id);
    $('#customerSearchResults').hide();
    $('#customerSearch').val(''); // Clear search input
    
    // Clear and disable new customer form
    $('#sellerForm')[0].reset();
    $('#sellerForm input, #sellerForm textarea').prop('disabled', true);
    
    // Display selected customer with deselect button
    $('#selectedCustomerDetails').html(`
        <div class="alert alert-info">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="alert-heading mb-0">Selected Customer</h6>
                <button type="button" class="btn-close" onclick="deselectCustomer()"></button>
            </div>
            <p class="mb-0">
                <strong>${escapeHtml(customer.name)}</strong><br>
                ${customer.phone ? `<i class="fas fa-phone me-1"></i>${escapeHtml(customer.phone)}<br>` : ''}
                ${customer.email ? `<i class="fas fa-envelope me-1"></i>${escapeHtml(customer.email)}<br>` : ''}
                <i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(customer.post_code)}
                ${customer.address ? `<br>${escapeHtml(customer.address)}` : ''}
            </p>
        </div>
    `);
}

function deselectCustomer() {
    // Clear customer ID
    $('#customerId').val('');
    
    // Clear selected customer display
    $('#selectedCustomerDetails').empty();
    
    // Enable and clear new customer form
    $('#sellerForm input, #sellerForm textarea').prop('disabled', false);
    $('#sellerForm')[0].reset();
    
    // Clear search
    $('#customerSearch').val('');
    $('#customerSearchResults').hide();
}
// ================ CUSTOMER SEARCH SECTION END ================

// ================ ITEM MANAGEMENT SECTION START ================
function loadCategories() {
    $.get('ajax/get_categories.php')
        .done(function(response) {
            const categories = typeof response === 'string' ? JSON.parse(response) : response;
            const select = $('#categorySelect');
            select.empty().append('<option value="">Select Category...</option>');
            
            categories.forEach(category => {
                select.append(`<option value="${escapeHtml(category.pless_main_category)}">
                    ${escapeHtml(category.pless_main_category)}
                </option>`);
            });
        })
        .fail(function(error) {
            console.error('Failed to load categories:', error);
        });
}

function showAddItemModal() {
    $('#itemForm')[0].reset();
    $('#itemPhotosPreview').empty();
    $('#categorySelect').val('').trigger('change');
    $('#addItemModal').modal('show');
}

function addItem() {
    const form = $('#itemForm')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const photoUrls = [];
    $('#itemPhotosPreview img').each(function() {
        photoUrls.push($(this).attr('src'));
    });

    const item = {
        id: Date.now(), // Temporary ID for frontend
        item_name: $('[name="item_name"]').val(),
        custom_sku: $('[name="custom_sku"]').val().trim(),
        category: $('#categorySelect').val(),
        serial_number: $('[name="serial_number"]').val().trim(),
        condition_rating: $('[name="condition_rating"]').val(),
        purchase_price: parseFloat($('[name="purchase_price"]').val()),
        photos: photoUrls
    };

    tradeInItems.push(item);
    updateItemsList();
    $('#addItemModal').modal('hide');
}

function removeItem(index) {
    tradeInItems.splice(index, 1);
    updateItemsList();
}

function viewItemPhotos(index) {
    const item = tradeInItems[index];
    const photoHtml = item.photos.map(photo => `
        <div class="col-md-4 mb-3">
            <img src="${photo}" class="img-fluid rounded" alt="Item photo">
        </div>
    `).join('');

    Swal.fire({
        title: `Photos of ${item.item_name}`,
        html: `<div class="row">${photoHtml}</div>`,
        width: '80%',
        confirmButtonText: 'Close'
    });
}

function updateItemsList() {
    const tbody = $('#itemsList');
    tbody.empty();

    let total = 0;
    tradeInItems.forEach((item, index) => {
        total += item.purchase_price;
        tbody.append(`
            <tr>
                <td>
                    ${escapeHtml(item.item_name)}
                    ${item.custom_sku ? `<br><small class="text-muted">SKU: ${escapeHtml(item.custom_sku)}</small>` : ''}
                </td>
                <td>${escapeHtml(item.category)}</td>
                <td>${escapeHtml(item.serial_number || '')}</td>
                <td>
                    <span class="badge bg-${getConditionClass(item.condition_rating)}">
                        ${item.condition_rating}
                    </span>
                </td>
                <td class="text-end">£${item.purchase_price.toFixed(2)}</td>
                <td>
                    ${item.photos.length > 0 ? 
                        `<button type="button" class="btn btn-sm btn-info" onclick="viewItemPhotos(${index})">
                            <i class="fas fa-images"></i> View (${item.photos.length})
                        </button>` : 
                        'No photos'
                    }
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `);
    });

    $('#totalPrice').text(`£${total.toFixed(2)}`);
    $('#reviewTotal').text(`£${total.toFixed(2)}`);
}
// ================ ITEM MANAGEMENT SECTION END ================

// ================ CAMERA FUNCTIONS SECTION START ================
async function startCamera(mode) {
    try {
        currentCameraMode = mode;
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: 'environment' }
        });
        currentCamera = stream;
        const video = document.getElementById('camera');
        video.srcObject = stream;
        video.style.display = 'block';
        video.play();
        
        $('#cameraModal').modal('show');
        $('#takePhoto').show();
        $('#savePhoto, #retakePhoto').hide();
        $('#photoPreview').empty();
        document.getElementById('photoCanvas').style.display = 'none';
    } catch (err) {
        console.error('Camera error:', err);
        Swal.fire('Error', 'Could not access camera. Please check permissions.', 'error');
    }
}

function stopCamera() {
    if (currentCamera) {
        currentCamera.getTracks().forEach(track => track.stop());
        currentCamera = null;
        document.getElementById('camera').srcObject = null;
    }
}

function capturePhoto() {
    const video = document.getElementById('camera');
    const canvas = document.getElementById('photoCanvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    const preview = document.getElementById('photoPreview');
    preview.innerHTML = `<img src="${canvas.toDataURL('image/jpeg')}" style="max-width: 100%;">`;

    $('#takePhoto').hide();
    $('#savePhoto, #retakePhoto').show();
    video.style.display = 'none';
}

function retakePhoto() {
    const video = document.getElementById('camera');
    video.style.display = 'block';
    $('#photoPreview').empty();
    $('#takePhoto').show();
    $('#savePhoto, #retakePhoto').hide();
}

function savePhoto() {
    const canvas = document.getElementById('photoCanvas');
    canvas.toBlob(function(blob) {
        const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
        
        switch(currentCameraMode) {
            case 'id':
                handleIdPhoto(file);
                break;
            case 'doc':
                handleDocPhoto(file);
                break;
            case 'item':
                handleItemPhoto(file);
                break;
        }$('#cameraModal').modal('hide');
    }, 'image/jpeg', 0.9);
}
// ================ CAMERA FUNCTIONS SECTION END ================

// ================ FILE HANDLING FUNCTIONS START ================
function handleIdPhoto(file) {
    const preview = $('#idPreview');
    preview.empty().append(`
        <div class="preview-image-container">
            <img src="${URL.createObjectURL(file)}" class="preview-image">
            <span class="remove-file" onclick="$(this).closest('.preview-image-container').remove()">×</span>
        </div>
    `);
}

function handleDocPhoto(file) {
    const preview = $('#docsPreview');
    preview.append(`
        <div class="preview-image-container">
            <img src="${URL.createObjectURL(file)}" class="preview-image">
            <span class="remove-file" onclick="$(this).closest('.preview-image-container').remove()">×</span>
        </div>
    `);
}

function handleItemPhoto(file) {
    const preview = $('#itemPhotosPreview');
    preview.append(`
        <div class="preview-image-container">
            <img src="${URL.createObjectURL(file)}" class="preview-image">
            <span class="remove-file" onclick="$(this).closest('.preview-image-container').remove()">×</span>
        </div>
    `);
}
// ================ FILE HANDLING FUNCTIONS END ================

// ================ TRADE-IN SUBMISSION UPDATE START ================
async function submitTradeIn() {
    try {
        // Show loading indicator
        Swal.fire({
            title: 'Processing Trade-In',
            html: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        // Validate required data
        if (tradeInItems.length === 0) {
            throw new Error('No items added to trade-in');
        }

        // Get customer ID or create new customer
        const sellerId = $('#customerId').val();
        let customerId;

        if (!sellerId) {
            // [Previous customer creation code remains the same...]
        } else {
            customerId = sellerId;
        }

        // Save first trade-in item and use its ID for documents
        const firstItem = tradeInItems[0];
        const itemFormData = new FormData();
        itemFormData.append('customer_id', customerId);
        itemFormData.append('item_name', firstItem.item_name);
        itemFormData.append('custom_sku', firstItem.custom_sku || '');
        itemFormData.append('category', firstItem.category);
        itemFormData.append('serial_number', firstItem.serial_number || '');
        itemFormData.append('condition_rating', firstItem.condition_rating);
        itemFormData.append('purchase_price', firstItem.purchase_price);
        itemFormData.append('trade_in_date', new Date().toISOString().split('T')[0]);

        const firstItemResponse = await $.ajax({
            url: 'ajax/save_trade_in.php',
            type: 'POST',
            data: itemFormData,
            processData: false,
            contentType: false
        });

        const firstItemResult = typeof firstItemResponse === 'string' ? 
            JSON.parse(firstItemResponse) : firstItemResponse;

        if (!firstItemResult.success) {
            throw new Error(firstItemResult.message || 'Failed to save first trade-in item');
        }

        // Upload documents using the first trade-in item's ID
        await uploadTradeInDocuments(firstItemResult.id);

        // Save remaining trade-in items
        for (let i = 1; i < tradeInItems.length; i++) {
            const item = tradeInItems[i];
            const itemFormData = new FormData();
            itemFormData.append('customer_id', customerId);
            itemFormData.append('item_name', item.item_name);
            itemFormData.append('custom_sku', item.custom_sku || '');
            itemFormData.append('category', item.category);
            itemFormData.append('serial_number', item.serial_number || '');
            itemFormData.append('condition_rating', item.condition_rating);
            itemFormData.append('purchase_price', item.purchase_price);
            itemFormData.append('trade_in_date', new Date().toISOString().split('T')[0]);

            try {
                const itemResponse = await $.ajax({
                    url: 'ajax/save_trade_in.php',
                    type: 'POST',
                    data: itemFormData,
                    processData: false,
                    contentType: false
                });

                const itemResult = typeof itemResponse === 'string' ? 
                    JSON.parse(itemResponse) : itemResponse;

                if (!itemResult.success) {
                    throw new Error(itemResult.message || 'Failed to save trade-in item');
                }

                // Upload item photos if any
                if (item.photos && item.photos.length > 0) {
                    for (const [index, photoUrl] of item.photos.entries()) {
                        const response = await fetch(photoUrl);
                        const blob = await response.blob();
                        const photoData = new FormData();
                        photoData.append('file', blob, `item_${index + 1}.jpg`);
                        photoData.append('trade_in_id', itemResult.id);
                        photoData.append('file_type', 'item_photo');

                        await $.ajax({
                            url: 'ajax/upload_trade_in_file.php',
                            type: 'POST',
                            data: photoData,
                            processData: false,
                            contentType: false
                        });
                    }
                }
            } catch (error) {
                console.error('Item save error:', error);
                throw new Error(`Failed to save item "${item.item_name}": ${error.message}`);
            }
        }

        // Success
        await Swal.fire({
            title: 'Success!',
            text: 'Trade-in process completed successfully',
            icon: 'success'
        });

        // Reset and refresh
        $('#tradeInModal').modal('hide');
        loadTradeIns();

    } catch (error) {
        console.error('Trade-in submission error:', error);
        await Swal.fire({
            title: 'Error',
            text: error.message || 'Failed to complete trade-in process',
            icon: 'error'
        });
    }
}
// ================ TRADE-IN SUBMISSION UPDATE END ================

// ================ REVIEW SECTION FUNCTIONS START ================
function updateReviewSection() {
    // Update seller details
    const sellerId = $('#customerId').val();
    let sellerHtml = '';
    if (sellerId) {
        // Use selected customer details
        sellerHtml = $('#selectedCustomerDetails').html();
    } else {
        // Use form data
        sellerHtml = `
            <div class="alert alert-info">
                <p class="mb-0">
                    <strong>${$('#sellerForm [name="name"]').val()}</strong><br>
                    ${$('#sellerForm [name="phone"]').val() ? `<i class="fas fa-phone me-1"></i>${$('#sellerForm [name="phone"]').val()}<br>` : ''}
                    ${$('#sellerForm [name="email"]').val() ? `<i class="fas fa-envelope me-1"></i>${$('#sellerForm [name="email"]').val()}<br>` : ''}
                    <i class="fas fa-map-marker-alt me-1"></i>${$('#sellerForm [name="post_code"]').val()}
                    ${$('#sellerForm [name="address"]').val() ? `<br>${$('#sellerForm [name="address"]').val()}` : ''}
                </p>
            </div>
        `;
    }
    $('#reviewSellerDetails').html(sellerHtml);

    // Update documents section
    const hasIdDoc = $('#idPreview').children().length > 0;
    const hasAdditionalDocs = $('#docsPreview').children().length > 0;

    // Clone the preview containers to keep all styling and layout
    const idPreviewContent = hasIdDoc ? $('#idPreview').clone().html() : '<p class="text-muted">No ID document uploaded</p>';
    const docsPreviewContent = hasAdditionalDocs ? $('#docsPreview').clone().html() : '<p class="text-muted">No additional documents uploaded</p>';

    $('#reviewDocuments').html(`
        <div class="row">
            <div class="col-md-6">
                <h6>ID Document</h6>
                <div class="preview-container">
                    ${idPreviewContent}
                </div>
            </div>
            <div class="col-md-6">
                <h6>Additional Documents</h6>
                <div class="preview-container">
                    ${docsPreviewContent}
                </div>
            </div>
        </div>
    `);

    // Remove any click handlers from the remove buttons in review section
    $('#reviewDocuments .remove-file').remove();

    // Update items summary
    let itemsHtml = '<div class="table-responsive"><table class="table table-sm">';
    itemsHtml += `
        <thead>
            <tr>
                <th>Item</th>
                <th>Category</th>
                <th>Condition</th>
                <th class="text-end">Price</th>
            </tr>
        </thead>
        <tbody>
    `;

    let total = 0;
    tradeInItems.forEach(item => {
        total += item.purchase_price;
        itemsHtml += `
            <tr>
                <td>${escapeHtml(item.item_name)}${item.custom_sku ? `<br><small class="text-muted">SKU: ${escapeHtml(item.custom_sku)}</small>` : ''}</td>
                <td>${escapeHtml(item.category)}</td>
                <td><span class="badge bg-${getConditionClass(item.condition_rating)}">${item.condition_rating}</span></td>
                <td class="text-end">£${item.purchase_price.toFixed(2)}</td>
            </tr>
        `;
    });

    itemsHtml += '</tbody></table></div>';
    $('#reviewItems').html(itemsHtml);
    $('#reviewTotal').text(`£${total.toFixed(2)}`);
}
// ================ REVIEW SECTION FUNCTIONS END ================

// ================ SEARCH AND LIST FUNCTIONS START ================
function loadTradeIns(page = 1) {
    const searchData = {
        page: page,
        sku: $('#searchSku').val().trim(),
        customer: $('#searchCustomer').val().trim(),
        condition: $('#searchCondition').val()
    };

    // Show loading overlay
    const loadingOverlay = $('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
    $('#tradeInList').html(loadingOverlay);

    $.get('ajax/get_trade_ins.php', searchData)
        .done(function(response) {
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                if (data.success) {
                    displayTradeIns(data.items);
                    if (data.pagination) {
                        displayPagination(data.pagination);
                    }
                } else {
                    console.error('Error:', data.message);
                    Swal.fire('Error', data.message || 'Failed to load trade-in items', 'error');
                }
            } catch (e) {
                console.error('Error parsing trade-ins:', e);
                Swal.fire('Error', 'Failed to parse server response', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            Swal.fire('Error', 'Failed to load trade-in items', 'error');
        });
}

function displayTradeIns(items) {
    const tbody = $('#tradeInList');
    tbody.empty();

    if (items.length === 0) {
        tbody.html('<tr><td colspan="10" class="text-center">No trade-in items found</td></tr>');
        return;
    }

    items.forEach(item => {
        const conditionClass = `condition-${item.condition_rating}`;
        const row = `
            <tr>
                <td>${escapeHtml(item.sku)}</td>
                <td>${escapeHtml(item.custom_sku || '')}</td>
                <td>${escapeHtml(item.serial_number || '')}</td>
                <td>${escapeHtml(item.item_name)}</td>
                <td>${escapeHtml(item.category)}</td>
                <td>
                    <span class="badge ${conditionClass}">
                        ${escapeHtml(item.condition_rating)}
                    </span>
                </td>
                <td>${escapeHtml(item.customer_name)}</td>
                <td>${new Date(item.trade_in_date).toLocaleDateString()}</td>
                <td class="text-end">£${parseFloat(item.purchase_price).toFixed(2)}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-info" onclick="viewTradeIn(${item.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="editTradeIn(${item.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteTradeIn(${item.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function displayPagination(pagination) {
    const container = $('#pagination');
    container.empty();

    if (pagination.total_pages <= 1) {
        return;
    }

    const paginationHtml = `
        <nav>
            <ul class="pagination">
                <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadTradeIns(1); return false;">First</a>
                </li>
                <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadTradeIns(${pagination.current_page - 1}); return false;">Previous</a>
                </li>
                ${generatePageNumbers(pagination)}
                <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadTradeIns(${pagination.current_page + 1}); return false;">Next</a>
                </li>
                <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
				<a class="page-link" href="#" onclick="loadTradeIns(${pagination.total_pages}); return false;">Last</a>
                </li>
            </ul>
        </nav>
    `;
    container.html(paginationHtml);
}

function generatePageNumbers(pagination) {
    let pages = '';
    const current = pagination.current_page;
    const total = pagination.total_pages;
    const range = 2;

    for (let i = Math.max(1, current - range); i <= Math.min(total, current + range); i++) {
        pages += `
            <li class="page-item ${i === current ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadTradeIns(${i}); return false;">${i}</a>
            </li>
        `;
    }

    return pages;
}
// ================ SEARCH AND LIST FUNCTIONS END ================

// ================ VIEW AND EDIT FUNCTIONS START ================
function viewTradeIn(id) {
    console.log('Viewing trade-in:', id);

    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.get('ajax/get_trade_in.php', { id: id })
        .done(function(response) {
            console.log('Raw response:', response);
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                console.log('Parsed data:', data);
                
                // Check if we have the item data (regardless of success property)
                if (data && data.id) {
                    let html = `
                        <div class="table-responsive">
                            <table class="table">
                                <tr>
                                    <th>SKU</th>
                                    <td>${escapeHtml(data.sku)}</td>
                                    <th>Custom SKU</th>
                                    <td>${escapeHtml(data.custom_sku || '')}</td>
                                </tr>
                                <tr>
                                    <th>Item Name</th>
                                    <td>${escapeHtml(data.item_name)}</td>
                                    <th>Category</th>
                                    <td>${escapeHtml(data.category)}</td>
                                </tr>
                                <tr>
                                    <th>Serial Number</th>
                                    <td>${escapeHtml(data.serial_number || '')}</td>
                                    <th>Condition</th>
                                    <td><span class="badge bg-${getConditionClass(data.condition_rating)}">${data.condition_rating}</span></td>
                                </tr>
                                <tr>
                                    <th>Purchase Price</th>
                                    <td>£${parseFloat(data.purchase_price).toFixed(2)}</td>
                                    <th>Trade-In Date</th>
                                    <td>${new Date(data.trade_in_date).toLocaleDateString()}</td>
                                </tr>
                                <tr>
                                    <th>Customer</th>
                                    <td colspan="3">${escapeHtml(data.customer_name)}</td>
                                </tr>
                            </table>
                        </div>
                    `;

                    // Only show photos section if photos exist
                    if (data.photos && data.photos.length > 0) {
                        html += '<h6 class="mt-3">Photos</h6><div class="row">';
                        data.photos.forEach(photo => {
                            html += `
                                <div class="col-md-4 mb-3">
                                    <img src="${photo.url}" class="img-fluid rounded" alt="Item photo">
                                </div>
                            `;
                        });
                        html += '</div>';
                    }

                    Swal.fire({
                        title: 'Trade-In Details',
                        html: html,
                        width: '800px'
                    });
                } else {
                    Swal.fire('Error', 'Failed to load trade-in details', 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                Swal.fire('Error', 'Failed to parse trade-in details', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('Response text:', xhr.responseText);
            Swal.fire('Error', 'Failed to load trade-in details', 'error');
        });
}

function deleteTradeIn(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('ajax/delete_trade_in.php', { id: id })
                .done(function(response) {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.success) {
                        Swal.fire('Deleted!', 'Trade-in has been deleted.', 'success');
                        loadTradeIns(); // Refresh the list
                    } else {
                        Swal.fire('Error', data.message || 'Failed to delete trade-in', 'error');
                    }
                })
                .fail(function() {
                    Swal.fire('Error', 'Failed to delete trade-in', 'error');
                });
        }
    });
}
// ================ VIEW AND EDIT FUNCTIONS END ================
function editTradeIn(id) {
    console.log('Editing trade-in:', id); // Debug log

    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.get('ajax/get_trade_in.php', { id: id })
        .done(function(response) {
            console.log('Raw response:', response); // Debug log
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                console.log('Parsed data:', data); // Debug log
                
                // Check if we have the item data
                if (data && data.id) {
                    // Reset the form first
                    resetTradeInForm();
                    
                    // Populate form fields
                    $('[name="id"]').val(data.id);
                    $('[name="item_name"]').val(data.item_name);
                    $('[name="custom_sku"]').val(data.custom_sku);
                    $('#categorySelect').val(data.category).trigger('change');
                    $('[name="serial_number"]').val(data.serial_number);
                    $('[name="condition_rating"]').val(data.condition_rating);
                    $('[name="purchase_price"]').val(data.purchase_price);
                    $('[name="trade_in_date"]').val(data.trade_in_date);

                    // If there are photos, populate them
                    if (data.photos && data.photos.length > 0) {
                        data.photos.forEach(photo => {
                            $('#itemPhotosPreview').append(`
                                <div class="preview-image-container">
                                    <img src="${photo.url}" class="preview-image">
                                    <span class="remove-file" onclick="$(this).closest('.preview-image-container').remove()">×</span>
                                </div>
                            `);
                        });
                    }

                    // Show the modal
                    $('#tradeInModal').modal('show');
                    Swal.close();
                } else {
                    console.error('No item data in response'); // Debug log
                    Swal.fire('Error', 'Failed to load trade-in details', 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                Swal.fire('Error', 'Failed to parse trade-in details', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('Response text:', xhr.responseText);
            Swal.fire('Error', 'Failed to load trade-in details', 'error');
        });
}
// ================ UTILITY FUNCTIONS START ================
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function getConditionClass(condition) {
    switch (condition) {
        case 'New': return 'success';
        case 'Good': return 'info';
        case 'Fair': return 'warning';
        case 'Poor': return 'danger';
        default: return 'secondary';
    }
}

function resetTradeInForm() {
    currentStep = 1;
    tradeInItems = [];
    $('#customerId').val('');
    $('#sellerForm')[0].reset();
    $('#selectedCustomerDetails').empty();
    $('#idPreview').empty();
    $('#docsPreview').empty();
    $('#itemsList').empty();
    $('#totalPrice').text('£0.00');
    updateStepDisplay();
}
// ================ UTILITY FUNCTIONS END ================
// ================ EDIT ITEM INITIALIZATION SECTION START ================
$(document).ready(function() {
    // Initialize Select2 for edit category
    $('#editCategorySelect').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search category...',
        dropdownParent: $('#editItemModal')
    });

    // Clear form when edit modal is hidden
    $('#editItemModal').on('hidden.bs.modal', function() {
        $('#editItemForm')[0].reset();
        $('#editItemPhotosPreview').empty();
        $('#editCategorySelect').val('').trigger('change');
    });
});
// ================ EDIT ITEM INITIALIZATION SECTION END ================

// ================ EDIT ITEM FUNCTIONS SECTION START ================
function editTradeIn(id) {
    console.log('Editing item:', id);

    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.get('ajax/get_trade_in.php', { id: id })
        .done(function(response) {
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                console.log('Parsed data:', data);
                
                if (data && data.id) {
                    // Populate edit form
                    const form = $('#editItemForm');
                    form.find('[name="id"]').val(data.id);
                    form.find('[name="item_name"]').val(data.item_name);
                    form.find('[name="custom_sku"]').val(data.custom_sku);
                    $('#editCategorySelect').val(data.category).trigger('change');
                    form.find('[name="serial_number"]').val(data.serial_number);
                    form.find('[name="condition_rating"]').val(data.condition_rating);
                    form.find('[name="purchase_price"]').val(data.purchase_price);

                    // Display existing photos
                    const photoPreview = $('#editItemPhotosPreview');
                    photoPreview.empty();
                    if (data.photos && data.photos.length > 0) {
                        data.photos.forEach(photo => {
                            photoPreview.append(`
                                <div class="preview-image-container">
                                    <img src="${photo.url}" class="preview-image">
                                    <input type="hidden" name="existing_photos[]" value="${photo.id}">
                                    <span class="remove-file" onclick="$(this).closest('.preview-image-container').remove()">×</span>
                                </div>
                            `);
                        });
                    }

                    // Show the modal
                    $('#editItemModal').modal('show');
                    Swal.close();
                } else {
                    Swal.fire('Error', 'Failed to load item details', 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                Swal.fire('Error', 'Failed to parse item details', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            Swal.fire('Error', 'Failed to load item details', 'error');
        });
}

function updateItem() {
    const form = $('#editItemForm')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const formData = new FormData(form);

    // Add any new photos from camera
    $('#editItemPhotosPreview img').each(function() {
        if (this.src.startsWith('data:')) {
            fetch(this.src)
                .then(res => res.blob())
                .then(blob => {
                    formData.append('new_photos[]', blob, 'photo.jpg');
                });
        }
    });

    $.ajax({
        url: 'ajax/update_trade_in.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success) {
                    Swal.fire('Success', 'Item updated successfully', 'success');
                    $('#editItemModal').modal('hide');
                    loadTradeIns(); // Refresh the list
                } else {
                    throw new Error(result.message || 'Failed to update item');
                }
            } catch (e) {
                console.error('Update error:', e);
                Swal.fire('Error', e.message || 'Failed to update item', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            Swal.fire('Error', 'Failed to update item', 'error');
        }
    });
}

function handleEditItemPhoto(file) {
    const preview = $('#editItemPhotosPreview');
    preview.append(`
        <div class="preview-image-container">
            <img src="${URL.createObjectURL(file)}" class="preview-image">
            <span class="remove-file" onclick="$(this).closest('.preview-image-container').remove()">×</span>
        </div>
    `);
}
// ================ EDIT ITEM FUNCTIONS SECTION END ================
// ================ DOCUMENT UPLOAD FUNCTIONS START ================
async function uploadTradeInDocuments(tradeInId) {
    // Upload ID document
    const idPhoto = $('#idPreview img').attr('src');
    if (idPhoto) {
        try {
            const response = await fetch(idPhoto);
            const blob = await response.blob();
            const idFormData = new FormData();
            idFormData.append('file', blob, 'id_document.jpg');
            idFormData.append('trade_in_id', tradeInId);
            idFormData.append('file_type', 'id_document');

            await $.ajax({
                url: 'ajax/upload_trade_in_file.php',
                type: 'POST',
                data: idFormData,
                processData: false,
                contentType: false
            });
        } catch (error) {
            console.error('ID document upload error:', error);
            throw new Error('Failed to upload ID document');
        }
    }

    // Upload additional documents (they will also be saved as id_document type)
    const additionalDocs = $('#docsPreview img');
    if (additionalDocs.length > 0) {
        for (let i = 0; i < additionalDocs.length; i++) {
            try {
                const docSrc = $(additionalDocs[i]).attr('src');
                const response = await fetch(docSrc);
                const blob = await response.blob();
                const docFormData = new FormData();
                docFormData.append('file', blob, `additional_doc_${i + 1}.jpg`);
                docFormData.append('trade_in_id', tradeInId);
                docFormData.append('file_type', 'id_document');

                await $.ajax({
                    url: 'ajax/upload_trade_in_file.php',
                    type: 'POST',
                    data: docFormData,
                    processData: false,
                    contentType: false
                });
            } catch (error) {
                console.error('Additional document upload error:', error);
                throw new Error('Failed to upload additional document');
            }
        }
    }
}
// ================ DOCUMENT UPLOAD FUNCTIONS END ================
// ================ DOCUMENT UPLOAD HANDLERS START ================
// Add these event handlers in your document.ready function
$(document).ready(function() {
    // Handle ID document upload via file input
    $('[name="id_document"]').on('change', function(e) {
        const files = e.target.files;
        if (files.length > 0) {
            handleIdPhoto(files[0]);
        }
    });

    // Handle additional documents upload via file input
    $('[name="additional_docs[]"]').on('change', function(e) {
        const files = e.target.files;
        Array.from(files).forEach(file => {
            handleDocPhoto(file);
        });
    });
});

// Update the photo handling functions
function handleIdPhoto(file) {
    const preview = $('#idPreview');
    preview.empty().append(`
        <div class="preview-image-container">
            <img src="${URL.createObjectURL(file)}" class="preview-image">
            <span class="remove-file" onclick="$(this).closest('.preview-image-container').remove()">×</span>
            <input type="hidden" name="id_doc_data" value="">
        </div>
    `);
    
    // Convert image to base64 for storage
    const reader = new FileReader();
    reader.onloadend = function() {
        preview.find('[name="id_doc_data"]').val(reader.result);
    }
    reader.readAsDataURL(file);
}

function handleDocPhoto(file) {
    const preview = $('#docsPreview');
    const container = $(`
        <div class="preview-image-container">
            <img src="${URL.createObjectURL(file)}" class="preview-image">
            <span class="remove-file" onclick="$(this).closest('.preview-image-container').remove()">×</span>
            <input type="hidden" name="additional_doc_data[]" value="">
        </div>
    `);
    preview.append(container);
    
    // Convert image to base64 for storage
    const reader = new FileReader();
    reader.onloadend = function() {
        container.find('[name="additional_doc_data[]"]').val(reader.result);
    }
    reader.readAsDataURL(file);
}

// This function now saves documents to the database
async function uploadTradeInDocuments(tradeInId) {
    console.log('Uploading documents for trade-in:', tradeInId);

    // Upload ID document
    const idDocData = $('[name="id_doc_data"]').val();
    if (idDocData) {
        try {
            const blob = await fetch(idDocData).then(r => r.blob());
            const formData = new FormData();
            formData.append('file', blob, 'id_document.jpg');
            formData.append('trade_in_id', tradeInId);
            formData.append('file_type', 'id_document');

            const response = await $.ajax({
                url: 'ajax/upload_trade_in_file.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            });
            console.log('ID document upload response:', response);
        } catch (error) {
            console.error('ID document upload error:', error);
            throw new Error('Failed to upload ID document');
        }
    }

    // Upload additional documents
    const additionalDocs = $('[name="additional_doc_data[]"]');
    if (additionalDocs.length > 0) {
        for (let i = 0; i < additionalDocs.length; i++) {
            try {
                const docData = $(additionalDocs[i]).val();
                const blob = await fetch(docData).then(r => r.blob());
                const formData = new FormData();
                formData.append('file', blob, `additional_doc_${i + 1}.jpg`);
                formData.append('trade_in_id', tradeInId);
                formData.append('file_type', 'id_document');

                const response = await $.ajax({
                    url: 'ajax/upload_trade_in_file.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false
                });
                console.log('Additional document upload response:', response);
            } catch (error) {
                console.error('Additional document upload error:', error);
                throw new Error('Failed to upload additional document');
            }
        }
    }
}
// ================ DOCUMENT UPLOAD HANDLERS END ================
    </script>
    <!-- ================ CUSTOM JAVASCRIPT SECTION END ================ -->
	<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Trade-In Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editItemForm">
                    <input type="hidden" name="id">
                    <div class="form-group mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Custom SKU</label>
                        <input type="text" class="form-control" name="custom_sku" placeholder="Optional custom SKU">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-control" name="category" id="editCategorySelect" required>
                            <option value="">Select Category...</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" class="form-control" name="serial_number">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Condition <span class="text-danger">*</span></label>
                        <select class="form-control" name="condition_rating" required>
                            <option value="New">New</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Purchase Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">£</span>
                            <input type="number" class="form-control" name="purchase_price" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Item Photos</label>
                        <div id="editItemPhotosPreview" class="mt-2"></div>
                        <div class="d-grid gap-2 mt-2">
                            <button type="button" class="btn btn-primary" onclick="startCamera('edit-item')">
                                <i class="fas fa-camera"></i> Take Photo
                            </button>
                            <input type="file" class="form-control" name="new_photos[]" multiple accept="image/*">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateItem()">Save Changes</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<?php require 'assets/footer.php'; ?>