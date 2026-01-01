<?php
session_start();
require '../php/bootstrap.php';

// Simple authentication check
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

// Check if user has access to Trade-In system
// Admin and useradmin bypass permission check
$is_admin = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);
$has_tradein_access = $is_admin || (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-View', $DB));

if (!$has_tradein_access) {
    header("Location: ../no_access.php");
    exit();
}

// Check if user has location assigned
if(empty($user_details['user_location'])){
    die("Error: Your user account does not have a location assigned. Please contact administrator.");
}

// Determine effective location (considering temp location)
$effective_location = $user_details['user_location'];
if(!empty($user_details['temp_location']) &&
   !empty($user_details['temp_location_expires']) &&
   strtotime($user_details['temp_location_expires']) > time()) {
    $effective_location = $user_details['temp_location'];
}

$location_name = ($effective_location == 'cs') ? 'Commerce Street' : 'Argyle Street';

// Set authorization flag (for legacy code compatibility)
$is_authorized = $is_admin;

// Check specific permissions
$can_view_financial = $is_admin || (function_exists('canViewFinancialData') && canViewFinancialData($user_id, $DB));
$can_view_customer = $is_admin || (function_exists('canViewCustomerData') && canViewCustomerData($user_id, $DB));
$can_view_documents = $is_admin || (function_exists('canViewDocuments') && canViewDocuments($user_id, $DB));
$can_manage = $is_admin || (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB));
$can_import_tradeins = $is_admin || (function_exists('canImportTradeIns') && canImportTradeIns($user_id, $DB));
$can_manage_compliance = $is_admin || (function_exists('canManageCompliance') && canManageCompliance($user_id, $DB));
$can_view_all_locations = $is_admin || (function_exists('canViewAllLocations') && canViewAllLocations($user_id, $DB));

// Get all categories for the form
$categories = $DB->query("SELECT DISTINCT pos_category FROM master_categories WHERE pos_category IS NOT NULL AND pos_category != '' ORDER BY pos_category ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade-In Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
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
        .compliance-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-arrow-left"></i> Back to Main Menu
            </a>
            <span class="navbar-text">Trade-In Management (<?=$location_name?>)</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Trade-In Management</h1>
            <?php if ($can_manage): ?>
            <button class="btn btn-success" id="addTradeInBtn" onclick="window.location.href='trade_in_workflow.php'">
                <i class="fas fa-plus"></i> Start New Trade-In
            </button>
            <?php endif; ?>
        </div>

        <!-- Trade-Ins List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Trade-In Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="tradeInsTable">
                        <thead>
                            <tr>
                                <th>Trade-In ID</th>
                                <th>Preprinted Code</th>
                                <th>Tracking Code</th>
                                <th>Customer Name</th>
                                <th>Item Name</th>
                                <th>Condition</th>
                                <th>Offered Price</th>
                                <th>Status</th>
                                <?php if ($can_manage_compliance): ?>
                                <th>Compliance</th>
                                <?php endif; ?>
                                <th>Location</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tradeInsList">
                            <!-- Trade-ins will be loaded here via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Trade-In Modal -->
    <div class="modal fade" id="tradeInModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tradeInModalLabel">Add Trade-In</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="tradeInForm">
                        <input type="hidden" id="tradeInId" name="id">

                        <!-- Compliance Section -->
                        <?php if ($can_manage_compliance): ?>
                        <div class="compliance-section">
                            <h6>Scottish Compliance Requirements</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="idDocumentType" class="form-label">ID Document Type</label>
                                        <select class="form-select" id="idDocumentType" name="id_document_type">
                                            <option value="">Select ID Type</option>
                                            <option value="passport">Passport</option>
                                            <option value="driving_license">Driving License</option>
                                            <option value="national_id">National ID Card</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="idDocumentNumber" class="form-label">ID Document Number</label>
                                        <input type="text" class="form-control" id="idDocumentNumber" name="id_document_number">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="complianceNotes" class="form-label">Compliance Notes</label>
                                <textarea class="form-control" id="complianceNotes" name="compliance_notes" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="complianceStatus" class="form-label">Compliance Status</label>
                                <select class="form-select" id="complianceStatus" name="compliance_status">
                                    <option value="pending">Pending Verification</option>
                                    <option value="verified">Verified</option>
                                    <option value="completed">Completed</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Customer Information</h6>

                                    <div class="mb-3">
                                        <label for="customerName" class="form-label">Customer Name *</label>
                                        <input type="text" class="form-control" id="customerName" name="customer_name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="customerPhone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="customerPhone" name="customer_phone">
                                    </div>

                                    <div class="mb-3">
                                        <label for="customerEmail" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="customerEmail" name="customer_email">
                                    </div>

                                    <div class="mb-3">
                                        <label for="customerAddress" class="form-label">Address</label>
                                        <textarea class="form-control" id="customerAddress" name="customer_address" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Trade-In Item Information</h6>

                                    <div class="mb-3">
                                        <label for="tradeInItemName" class="form-label">Item Name *</label>
                                        <input type="text" class="form-control" id="tradeInItemName" name="item_name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tradeInCategory" class="form-label">Category</label>
                                        <select class="form-select" id="tradeInCategory" name="category">
                                            <option value="">Select Category</option>
                                            <?php foreach($categories as $category): ?>
                                            <option value="<?=htmlspecialchars($category['pos_category'])?>"><?=htmlspecialchars($category['pos_category'])?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tradeInBrand" class="form-label">Brand</label>
                                        <input type="text" class="form-control" id="tradeInBrand" name="brand">
                                    </div>

                                    <div class="mb-3">
                                        <label for="tradeInModel" class="form-label">Model Number</label>
                                        <input type="text" class="form-control" id="tradeInModel" name="model_number">
                                    </div>

                                    <div class="mb-3">
                                        <label for="tradeInSerial" class="form-label">Serial Number</label>
                                        <input type="text" class="form-control" id="tradeInSerial" name="serial_number">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Condition & Valuation</h6>

                                    <div class="mb-3">
                                        <label for="tradeInCondition" class="form-label">Condition *</label>
                                        <select class="form-select" id="tradeInCondition" name="condition" required>
                                            <option value="excellent">Excellent</option>
                                            <option value="good" selected>Good</option>
                                            <option value="fair">Fair</option>
                                            <option value="poor">Poor</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="detailedCondition" class="form-label">Detailed Condition Notes</label>
                                        <textarea class="form-control" id="detailedCondition" name="detailed_condition" rows="3"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="offeredPrice" class="form-label">Offered Price (£)</label>
                                        <input type="number" class="form-control" id="offeredPrice" name="offered_price" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Location & Status</h6>

                                    <div class="mb-3">
                                        <label for="tradeInLocation" class="form-label">Location</label>
                                        <select class="form-select" id="tradeInLocation" name="location" required>
                                            <option value="cs" <?=($effective_location == 'cs') ? 'selected' : ''?>>Commerce Street</option>
                                            <option value="as" <?=($effective_location == 'as') ? 'selected' : ''?>>Argyle Street</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tradeInStatus" class="form-label">Status</label>
                                        <select class="form-select" id="tradeInStatus" name="status">
                                            <option value="pending" selected>Pending</option>
                                            <option value="accepted">Accepted</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="processed">Processed</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="collectionDate" class="form-label">Collection Date</label>
                                        <input type="date" class="form-control" id="collectionDate" name="collection_date">
                                    </div>

                                    <div class="mb-3">
                                        <label for="tradeInNotes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="tradeInNotes" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Tracking Codes</h6>

                                    <div class="mb-3">
                                        <label for="tradeInPreprintedCode" class="form-label">Preprinted Code (DSH)</label>
                                        <input type="text" class="form-control" id="tradeInPreprintedCode" name="preprinted_code" placeholder="e.g., DSH1, DSH2">
                                        <div class="form-text">For items with pre-applied DSH labels</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tradeInTrackingCode" class="form-label">Tracking Code (SH)</label>
                                        <input type="text" class="form-control" id="tradeInTrackingCode" name="tracking_code" placeholder="e.g., SH1, SH2">
                                        <div class="form-text">Automatically generated if left empty</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Photos</h6>
                                    <div class="photo-upload-area">
                                        <p>Upload photos of the trade-in item and customer ID (if applicable)</p>
                                        <input type="file" class="form-control" id="tradeInPhotos" name="photos[]" multiple accept="image/*">
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="openTradeInCameraBtn">
                                                <i class="fas fa-camera"></i> Use Camera
                                            </button>
                                        </div>
                                        <div class="photo-preview" id="tradeInPhotoPreview"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Camera Modal -->
                        <div class="modal fade" id="tradeInCameraModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Take Photo</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <video id="tradeInVideoElement" autoplay playsinline style="width: 100%; max-height: 400px;"></video>
                                        <canvas id="tradeInCanvasElement" style="display: none;"></canvas>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-primary" id="captureTradeInPhotoBtn">
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

                        <?php if ($can_import_tradeins): ?>
                        <div class="form-section">
                            <h6>Import to Second-Hand Inventory</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="importToSecondHand" name="import_to_second_hand">
                                <label class="form-check-label" for="importToSecondHand">
                                    Import this trade-in to second-hand inventory after processing
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveTradeInBtn">Save Trade-In</button>
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
        // Initialize Select2 for category dropdown
        $('#tradeInCategory').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select a category',
            allowClear: true
        });

        // Load trade-ins list
        loadTradeIns();

        // Show add trade-in modal
        $('#addTradeInBtn').click(function(){
            $('#tradeInModalLabel').text('Add Trade-In');
            $('#tradeInForm')[0].reset();
            $('#tradeInId').val('');
            $('#tradeInModal').modal('show');
        });

        // Save trade-in
        $('#saveTradeInBtn').click(function(){
            saveTradeIn();
        });

        // Photo preview
        $('#tradeInPhotos').change(function() {
            const files = this.files;
            const preview = $('#tradeInPhotoPreview');
            preview.empty();

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.append(`<img src="${e.target.result}" class="item-photo" alt="Preview">`);
                    }
                    reader.readAsDataURL(file);
                }
            }
        });

        // Open camera modal
        $('#openTradeInCameraBtn').click(function() {
            $('#tradeInCameraModal').modal('show');
            startTradeInCamera();
        });

        // Capture photo from camera
        $('#captureTradeInPhotoBtn').click(function() {
            captureTradeInPhoto();
        });

        // Close camera when modal is closed
        $('#tradeInCameraModal').on('hidden.bs.modal', function () {
            stopTradeInCamera();
        });
    });

    // Camera functionality for trade-ins
    let tradeInVideoStream = null;

    function startTradeInCamera() {
        const video = document.getElementById('tradeInVideoElement');

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(stream) {
                    tradeInVideoStream = stream;
                    video.srcObject = stream;
                })
                .catch(function(error) {
                    console.error("Error accessing camera: ", error);
                    Swal.fire('Error', 'Could not access camera: ' + error.message, 'error');
                });
        } else {
            Swal.fire('Error', 'Camera access not supported in this browser', 'error');
        }
    }

    function captureTradeInPhoto() {
        const video = document.getElementById('tradeInVideoElement');
        const canvas = document.getElementById('tradeInCanvasElement');
        const context = canvas.getContext('2d');

        // Set canvas dimensions to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        // Draw video frame to canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Convert to blob and add to preview
        canvas.toBlob(function(blob) {
            const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
            const preview = $('#tradeInPhotoPreview');

            // Create object URL for preview
            const url = URL.createObjectURL(file);
            preview.append(`<img src="${url}" class="item-photo" alt="Captured">`);

            // Add to file input (this is more complex in practice)
            // For now, we'll just show the preview
        }, 'image/jpeg');

        // Stop camera
        stopTradeInCamera();
    }

    function stopTradeInCamera() {
        if (tradeInVideoStream) {
            const tracks = tradeInVideoStream.getTracks();
            tracks.forEach(track => track.stop());
            tradeInVideoStream = null;
        }
    }

    function loadTradeIns() {
        $.ajax({
            url: 'ajax/list_trade_ins.php', // This file would need to be created
            method: 'GET',
            data: {
                location: '<?=$effective_location?>',
                view_all_locations: <?=$can_view_all_locations ? 'true' : 'false'?>
            },
            success: function(response) {
                const tradeIns = response.trade_ins || [];
                let html = '';

                tradeIns.forEach(function(tradeIn) {
                    html += `
                        <tr>
                            <td>${tradeIn.id}</td>
                            <td>${tradeIn.preprinted_code || 'N/A'}</td>
                            <td>${tradeIn.tracking_code || 'N/A'}</td>
                            <td>${tradeIn.customer_name || 'N/A'}</td>
                            <td>${tradeIn.item_name}</td>
                            <td>${tradeIn.condition}</td>
                            <td>£${parseFloat(tradeIn.offered_price || 0).toFixed(2)}</td>
                            <td>${tradeIn.status}</td>
                            <?php if ($can_manage_compliance): ?>
                            <td>${tradeIn.compliance_status || 'N/A'}</td>
                            <?php endif; ?>
                            <td>${tradeIn.location === 'cs' ? 'Commerce Street' : 'Argyle Street'}</td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-tradein" data-id="${tradeIn.id}">Edit</button>
                                <button class="btn btn-sm btn-success import-tradein" data-id="${tradeIn.id}" style="display: <?=$can_import_tradeins ? 'inline-block' : 'none'?>;">Import</button>
                                <button class="btn btn-sm btn-danger delete-tradein" data-id="${tradeIn.id}">Delete</button>
                            </td>
                        </tr>
                    `;
                });

                $('#tradeInsList').html(html);

                // Add event listeners for edit and delete buttons
                $('.edit-tradein').click(function() {
                    const id = $(this).data('id');
                    editTradeIn(id);
                });

                $('.delete-tradein').click(function() {
                    const id = $(this).data('id');
                    deleteTradeIn(id);
                });

                $('.import-tradein').click(function() {
                    const id = $(this).data('id');
                    importTradeIn(id);
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading trade-ins:', error);
                Swal.fire('Error', 'Failed to load trade-ins', 'error');
            }
        });
    }

    function editTradeIn(id) {
        $.ajax({
            url: 'ajax/get_trade_in.php', // This file would need to be created
            method: 'GET',
            data: { id: id },
            success: function(response) {
                if (response.success) {
                    const tradeIn = response.trade_in;

                    $('#tradeInModalLabel').text('Edit Trade-In');
                    $('#tradeInId').val(tradeIn.id);
                    $('#customerName').val(tradeIn.customer_name);
                    $('#customerPhone').val(tradeIn.customer_phone);
                    $('#customerEmail').val(tradeIn.customer_email);
                    $('#customerAddress').val(tradeIn.customer_address);
                    $('#tradeInItemName').val(tradeIn.item_name);
                    $('#tradeInCategory').val(tradeIn.category).trigger('change');
                    $('#tradeInBrand').val(tradeIn.brand);
                    $('#tradeInModel').val(tradeIn.model_number);
                    $('#tradeInSerial').val(tradeIn.serial_number);
                    $('#tradeInCondition').val(tradeIn.condition);
                    $('#detailedCondition').val(tradeIn.detailed_condition);
                    $('#offeredPrice').val(tradeIn.offered_price);
                    $('#tradeInLocation').val(tradeIn.location);
                    $('#tradeInStatus').val(tradeIn.status);
                    $('#collectionDate').val(tradeIn.collection_date);
                    $('#tradeInNotes').val(tradeIn.notes);
                    $('#tradeInPreprintedCode').val(tradeIn.preprinted_code);
                    $('#tradeInTrackingCode').val(tradeIn.tracking_code);

                    <?php if ($can_manage_compliance): ?>
                    $('#idDocumentType').val(tradeIn.id_document_type);
                    $('#idDocumentNumber').val(tradeIn.id_document_number);
                    $('#complianceNotes').val(tradeIn.compliance_notes);
                    $('#complianceStatus').val(tradeIn.compliance_status);
                    <?php endif; ?>

                    $('#tradeInModal').modal('show');
                } else {
                    Swal.fire('Error', response.message || 'Failed to load trade-in', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading trade-in:', error);
                Swal.fire('Error', 'Failed to load trade-in', 'error');
            }
        });
    }

    function saveTradeIn() {
        const formData = new FormData($('#tradeInForm')[0]);

        $.ajax({
            url: 'ajax/save_trade_in.php', // This file would need to be created
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#tradeInModal').modal('hide');
                    Swal.fire('Success', response.message || 'Trade-in saved successfully', 'success');
                    loadTradeIns(); // Reload the trade-ins list
                } else {
                    Swal.fire('Error', response.message || 'Failed to save trade-in', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving trade-in:', error);
                Swal.fire('Error', 'Failed to save trade-in', 'error');
            }
        });
    }

    function deleteTradeIn(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This will permanently delete the trade-in!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax/delete_trade_in.php', // This file would need to be created
                    method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message || 'Trade-in has been deleted.', 'success');
                            loadTradeIns(); // Reload the trade-ins list
                        } else {
                            Swal.fire('Error', response.message || 'Failed to delete trade-in', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error deleting trade-in:', error);
                        Swal.fire('Error', 'Failed to delete trade-in', 'error');
                    }
                });
            }
        });
    }

    function importTradeIn(id) {
        Swal.fire({
            title: 'Import Trade-In',
            text: "This will import this trade-in to the second-hand inventory system.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, import it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'php/import_trade_in.php', // This file already exists
                    method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Imported!', response.message || 'Trade-in has been imported to second-hand inventory.', 'success');
                            loadTradeIns(); // Reload the trade-ins list
                        } else {
                            Swal.fire('Error', response.message || 'Failed to import trade-in', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error importing trade-in:', error);
                        Swal.fire('Error', 'Failed to import trade-in', 'error');
                    }
                });
            }
        });
    }
    </script>
</body>
</html>