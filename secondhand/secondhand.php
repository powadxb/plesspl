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

// Check permissions directly from database
$secondhand_view_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View'",
    [$user_id]
);
$has_secondhand_access = !empty($secondhand_view_check) && $secondhand_view_check[0]['has_access'];

// DEBUG - Remove this after testing
echo "<!-- DEBUG INFO:\n";
echo "User ID: " . $user_id . "\n";
echo "Query result: " . print_r($secondhand_view_check, true) . "\n";
echo "Has access: " . ($has_secondhand_access ? 'YES' : 'NO') . "\n";
echo "User details: " . print_r($user_details, true) . "\n";
echo "-->\n";

if (!$has_secondhand_access) {
    // Show more helpful error message
    die("Access Denied<br><br>
    User ID: {$user_id}<br>
    Permission Check Result: " . print_r($secondhand_view_check, true) . "<br><br>
    Please ensure you have 'SecondHand-View' permission assigned in the Control Panel.<br><br>
    <a href='../control_panel.php'>Go to Control Panel</a>");
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

// Check specific permissions from database
$financial_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Financial'",
    [$user_id]
);
$can_view_financial = !empty($financial_check) && $financial_check[0]['has_access'];

$customer_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Customer Data'",
    [$user_id]
);
$can_view_customer = !empty($customer_check) && $customer_check[0]['has_access'];

$documents_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Documents'",
    [$user_id]
);
$can_view_documents = !empty($documents_check) && $documents_check[0]['has_access'];

$manage_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'",
    [$user_id]
);
$can_manage = !empty($manage_check) && $manage_check[0]['has_access'];

$all_locations_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View All Locations'",
    [$user_id]
);
$can_view_all_locations = !empty($all_locations_check) && $all_locations_check[0]['has_access'];

// Get all categories for the form
$categories = $DB->query("SELECT DISTINCT pos_category FROM master_categories WHERE pos_category IS NOT NULL AND pos_category != '' ORDER BY pos_category ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Second Hand Items</title>
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-arrow-left"></i> Back to Main Menu
            </a>
            <span class="navbar-text">Second Hand Items (<?=$location_name?>)</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Second Hand Items</h1>
            <div class="btn-group">
                <a href="trade_in_management.php" class="btn btn-primary">
                    <i class="fas fa-exchange-alt"></i> Trade-Ins
                </a>
                <?php if ($can_manage): ?>
                <button class="btn btn-success" id="addItemBtn">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Inventory Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Preprinted Code</th>
                                <th>Tracking Code</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Condition</th>
                                <th>Source</th>
                                <th>Status</th>
                                <?php if ($can_view_financial): ?>
                                <th>Purchase Price</th>
                                <th>Est. Sale Price</th>
                                <?php endif; ?>
                                <th>Location</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="itemsList">
                            <!-- Items will be loaded here via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Item Modal -->
    <div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalLabel">Add Second Hand Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="itemForm">
                        <input type="hidden" id="itemId" name="id">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Item Information</h6>

                                    <div class="mb-3">
                                        <label for="itemName" class="form-label">Item Name *</label>
                                        <input type="text" class="form-control" id="itemName" name="item_name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="itemCategory" class="form-label">Category</label>
                                        <select class="form-select" id="itemCategory" name="category">
                                            <option value="">Select Category</option>
                                            <?php foreach($categories as $category): ?>
                                            <option value="<?=htmlspecialchars($category['pos_category'])?>"><?=htmlspecialchars($category['pos_category'])?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="itemBrand" class="form-label">Brand</label>
                                        <input type="text" class="form-control" id="itemBrand" name="brand">
                                    </div>

                                    <div class="mb-3">
                                        <label for="itemModel" class="form-label">Model Number</label>
                                        <input type="text" class="form-control" id="itemModel" name="model_number">
                                    </div>

                                    <div class="mb-3">
                                        <label for="itemSerial" class="form-label">Serial Number</label>
                                        <input type="text" class="form-control" id="itemSerial" name="serial_number">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Condition & Source</h6>

                                    <div class="mb-3">
                                        <label for="itemCondition" class="form-label">Condition *</label>
                                        <select class="form-select" id="itemCondition" name="condition" required>
                                            <option value="excellent">Excellent</option>
                                            <option value="good" selected>Good</option>
                                            <option value="fair">Fair</option>
                                            <option value="poor">Poor</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="itemSource" class="form-label">Source *</label>
                                        <select class="form-select" id="itemSource" name="item_source" required>
                                            <option value="trade_in">Trade-In</option>
                                            <option value="donation">Donation</option>
                                            <option value="abandoned">Abandoned</option>
                                            <option value="parts_dismantle">Parts from Dismantle</option>
                                            <option value="purchase">Purchased</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="detailedCondition" class="form-label">Detailed Condition Notes</label>
                                        <textarea class="form-control" id="detailedCondition" name="detailed_condition" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Financial Information</h6>

                                    <div class="mb-3">
                                        <label for="purchasePrice" class="form-label">Purchase Price (£)</label>
                                        <input type="number" class="form-control" id="purchasePrice" name="purchase_price" step="0.01" min="0">
                                    </div>

                                    <div class="mb-3">
                                        <label for="estimatedValue" class="form-label">Estimated Value (£)</label>
                                        <input type="number" class="form-control" id="estimatedValue" name="estimated_value" step="0.01" min="0">
                                    </div>

                                    <div class="mb-3">
                                        <label for="estimatedSalePrice" class="form-label">Estimated Sale Price (£)</label>
                                        <input type="number" class="form-control" id="estimatedSalePrice" name="estimated_sale_price" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Location & Status</h6>

                                    <div class="mb-3">
                                        <label for="itemLocation" class="form-label">Location</label>
                                        <select class="form-select" id="itemLocation" name="location" required>
                                            <option value="cs" <?=($effective_location == 'cs') ? 'selected' : ''?>>Commerce Street</option>
                                            <option value="as" <?=($effective_location == 'as') ? 'selected' : ''?>>Argyle Street</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="itemStatus" class="form-label">Status</label>
                                        <select class="form-select" id="itemStatus" name="status">
                                            <option value="in_stock" selected>In Stock</option>
                                            <option value="sold">Sold</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="statusDetail" class="form-label">Status Detail</label>
                                        <input type="text" class="form-control" id="statusDetail" name="status_detail">
                                    </div>

                                    <div class="mb-3">
                                        <label for="acquisitionDate" class="form-label">Acquisition Date</label>
                                        <input type="date" class="form-control" id="acquisitionDate" name="acquisition_date" value="<?=date('Y-m-d')?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h6>Customer Information (if applicable)</h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customerName" class="form-label">Customer Name</label>
                                        <input type="text" class="form-control" id="customerName" name="customer_name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customerContact" class="form-label">Customer Contact</label>
                                        <input type="text" class="form-control" id="customerContact" name="customer_contact">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h6>Additional Information</h6>

                            <div class="mb-3">
                                <label for="itemNotes" class="form-label">Notes</label>
                                <textarea class="form-control" id="itemNotes" name="notes" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="warrantyInfo" class="form-label">Warranty Information</label>
                                <textarea class="form-control" id="warrantyInfo" name="warranty_info" rows="2"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="supplierInfo" class="form-label">Supplier Information</label>
                                <input type="text" class="form-control" id="supplierInfo" name="supplier_info">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Tracking Codes</h6>

                                    <div class="mb-3">
                                        <label for="preprintedCode" class="form-label">Preprinted Code (DSH)</label>
                                        <input type="text" class="form-control" id="preprintedCode" name="preprinted_code" placeholder="e.g., DSH1, DSH2">
                                        <div class="form-text">For items with pre-applied DSH labels</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="trackingCode" class="form-label">Tracking Code (SH)</label>
                                        <input type="text" class="form-control" id="trackingCode" name="tracking_code" placeholder="e.g., SH1, SH2">
                                        <div class="form-text">Automatically generated if left empty</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6>Photos</h6>
                                    <div class="photo-upload-area">
                                        <p>Upload photos of the item</p>
                                        <input type="file" class="form-control" id="itemPhotos" name="photos[]" multiple accept="image/*">
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="openCameraBtn">
                                                <i class="fas fa-camera"></i> Use Camera
                                            </button>
                                        </div>
                                        <div class="photo-preview" id="photoPreview"></div>
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
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveItemBtn">Save Item</button>
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
        $('#itemCategory').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select a category',
            allowClear: true
        });

        // Load items list
        loadItems();

        // Show add item modal
        $('#addItemBtn').click(function(){
            $('#itemModalLabel').text('Add Second Hand Item');
            $('#itemForm')[0].reset();
            $('#itemId').val('');
            $('#itemModal').modal('show');
        });

        // Save item
        $('#saveItemBtn').click(function(){
            saveItem();
        });

        // Photo preview
        $('#itemPhotos').change(function() {
            const files = this.files;
            const preview = $('#photoPreview');
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
        $('#openCameraBtn').click(function() {
            $('#cameraModal').modal('show');
            startCamera();
        });

        // Capture photo from camera
        $('#capturePhotoBtn').click(function() {
            capturePhoto();
        });

        // Close camera when modal is closed
        $('#cameraModal').on('hidden.bs.modal', function () {
            stopCamera();
        });
    });

    // Camera functionality
    let videoStream = null;

    function startCamera() {
        const video = document.getElementById('videoElement');

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(stream) {
                    videoStream = stream;
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

    function capturePhoto() {
        const video = document.getElementById('videoElement');
        const canvas = document.getElementById('canvasElement');
        const context = canvas.getContext('2d');

        // Set canvas dimensions to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        // Draw video frame to canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Convert to blob and add to preview
        canvas.toBlob(function(blob) {
            const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
            const preview = $('#photoPreview');

            // Create object URL for preview
            const url = URL.createObjectURL(file);
            preview.append(`<img src="${url}" class="item-photo" alt="Captured">`);

            // Add to file input (this is more complex in practice)
            // For now, we'll just show the preview
        }, 'image/jpeg');

        // Stop camera
        stopCamera();
    }

    function stopCamera() {
        if (videoStream) {
            const tracks = videoStream.getTracks();
            tracks.forEach(track => track.stop());
            videoStream = null;
        }
    }

    function loadItems() {
        $.ajax({
            url: 'php/list_second_hand_items.php',
            method: 'GET',
            data: {
                location: '<?=$effective_location?>',
                view_all_locations: <?=$can_view_all_locations ? 'true' : 'false'?>
            },
            success: function(response) {
                const items = response.items || [];
                let html = '';

                items.forEach(function(item) {
                    html += `
                        <tr>
                            <td>${item.preprinted_code || 'N/A'}</td>
                            <td>${item.tracking_code || 'N/A'}</td>
                            <td>${item.item_name}</td>
                            <td>${item.category || 'N/A'}</td>
                            <td>${item.condition}</td>
                            <td>${item.item_source}</td>
                            <td>${item.status}</td>
                            ${<?=$can_view_financial ? 'true' : 'false'?> ? `
                                <td>£${parseFloat(item.purchase_price || 0).toFixed(2)}</td>
                                <td>£${parseFloat(item.estimated_sale_price || 0).toFixed(2)}</td>
                            ` : ''}
                            <td>${item.location === 'cs' ? 'Commerce Street' : 'Argyle Street'}</td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-item" data-id="${item.id}">Edit</button>
                                <button class="btn btn-sm btn-danger delete-item" data-id="${item.id}">Delete</button>
                            </td>
                        </tr>
                    `;
                });

                $('#itemsList').html(html);

                // Add event listeners for edit and delete buttons
                $('.edit-item').click(function() {
                    const id = $(this).data('id');
                    editItem(id);
                });

                $('.delete-item').click(function() {
                    const id = $(this).data('id');
                    deleteItem(id);
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading items:', error);
                Swal.fire('Error', 'Failed to load items', 'error');
            }
        });
    }

    function editItem(id) {
        $.ajax({
            url: 'php/get_second_hand_item.php',
            method: 'GET',
            data: { id: id },
            success: function(response) {
                if (response.success) {
                    const item = response.item;

                    $('#itemModalLabel').text('Edit Second Hand Item');
                    $('#itemId').val(item.id);
                    $('#itemName').val(item.item_name);
                    $('#itemCategory').val(item.category).trigger('change');
                    $('#itemBrand').val(item.brand);
                    $('#itemModel').val(item.model_number);
                    $('#itemSerial').val(item.serial_number);
                    $('#itemCondition').val(item.condition);
                    $('#itemSource').val(item.item_source);
                    $('#detailedCondition').val(item.detailed_condition);
                    $('#purchasePrice').val(item.purchase_price);
                    $('#estimatedValue').val(item.estimated_value);
                    $('#estimatedSalePrice').val(item.estimated_sale_price);
                    $('#itemLocation').val(item.location);
                    $('#itemStatus').val(item.status);
                    $('#statusDetail').val(item.status_detail);
                    $('#acquisitionDate').val(item.acquisition_date);
                    $('#customerName').val(item.customer_name);
                    $('#customerContact').val(item.customer_contact);
                    $('#itemNotes').val(item.notes);
                    $('#warrantyInfo').val(item.warranty_info);
                    $('#supplierInfo').val(item.supplier_info);
                    $('#preprintedCode').val(item.preprinted_code);
                    $('#trackingCode').val(item.tracking_code);

                    $('#itemModal').modal('show');
                } else {
                    Swal.fire('Error', response.message || 'Failed to load item', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading item:', error);
                Swal.fire('Error', 'Failed to load item', 'error');
            }
        });
    }

    function saveItem() {
        const formData = new FormData($('#itemForm')[0]);

        $.ajax({
            url: 'php/save_second_hand_item.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#itemModal').modal('hide');
                    Swal.fire('Success', response.message || 'Item saved successfully', 'success');
                    loadItems(); // Reload the items list
                } else {
                    Swal.fire('Error', response.message || 'Failed to save item', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving item:', error);
                Swal.fire('Error', 'Failed to save item', 'error');
            }
        });
    }

    function deleteItem(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This will permanently delete the item!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'php/delete_second_hand_item.php', // This file would need to be created
                    method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message || 'Item has been deleted.', 'success');
                            loadItems(); // Reload the items list
                        } else {
                            Swal.fire('Error', response.message || 'Failed to delete item', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error deleting item:', error);
                        Swal.fire('Error', 'Failed to delete item', 'error');
                    }
                });
            }
        });
    }
    </script>
</body>
</html>