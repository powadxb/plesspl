<?php
// tickets.php
require_once 'config.php';
require_once 'auth.php';
require_once 'ticket.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit();
}

$ticketManager = new TicketManager(getDB());
$status = isset($_GET['status']) ? $_GET['status'] : null;
$tickets = $ticketManager->getTicketsByLocation($_SESSION['location_id'], $status);
?>

<?php include 'header.php'; ?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Repair Tickets</h2>
        <div>
            <a href="kanban.php" class="btn btn-outline-primary me-2">Kanban View</a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                Create New Ticket
            </button>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Ticket created successfully</div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <!-- Status Filter -->
    <div class="mb-4">
        <div class="btn-group">
            <a href="tickets.php" class="btn btn-outline-primary <?= !$status ? 'active' : '' ?>">All</a>
            <a href="tickets.php?status=pending" class="btn btn-outline-primary <?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="tickets.php?status=in_progress" class="btn btn-outline-primary <?= $status === 'in_progress' ? 'active' : '' ?>">In Progress</a>
            <a href="tickets.php?status=completed" class="btn btn-outline-primary <?= $status === 'completed' ? 'active' : '' ?>">Completed</a>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Customer</th>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Cost</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No tickets found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ticket['ticket_number']) ?></td>
                                    <td><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></td>
                                    <td><?= htmlspecialchars($ticket['device_type']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $ticket['status'] === 'completed' ? 'success' : 
                                            ($ticket['status'] === 'in_progress' ? 'primary' : 'warning') 
                                        ?>">
                                            <?= ucfirst($ticket['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $ticket['repair_cost'] ? '£' . number_format($ticket['repair_cost'], 2) : '-' ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($ticket['created_at'])) ?></td>
                                    <td>
                                        <a href="ticket_details.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-info">View Details</a>
                                        <button class="btn btn-sm btn-primary" onclick="updateStatus(<?= $ticket['id'] ?>)">Update Status</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div class="modal fade" id="createTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="create_ticket.php" enctype="multipart/form-data" id="createTicketForm">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Customer Information -->
                    <h6 class="mb-3">Customer Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>

                    <!-- Device Information -->
                    <h6 class="mb-3">Device Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Device Type</label>
                            <select class="form-select" name="device_type" required>
                                <option value="">Select Device Type</option>
                                <option value="laptop">Laptop</option>
                                <option value="desktop">Desktop</option>
                                <option value="phone">Mobile Phone</option>
                                <option value="tablet">Tablet</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issue Description</label>
                        <textarea class="form-control" name="issue_description" rows="3" required></textarea>
                    </div>

                    <!-- Initial Cost -->
                    <h6 class="mb-3">Cost Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Estimated Repair Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">£</span>
                            <input type="number" class="form-control" name="repair_cost" step="0.01" min="0">
                        </div>
                    </div>

                    <!-- Photo Section -->
                    <h6 class="mb-3">Device Photos</h6>
                    <div class="mb-3">
                        <input type="file" id="mainPhotoInput" class="form-control d-none" name="photos[]" multiple 
                               accept="image/*" onchange="previewImages(this)">
                        <div class="btn-group w-100 mb-2">
                            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-folder-open"></i> Choose Files
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="openCamera()">
                                <i class="fas fa-camera"></i> Take Photo
                            </button>
                        </div>
                        <input type="file" id="fileInput" class="d-none" accept="image/*" multiple onchange="handleFileSelect(this)">
                        <!-- Video element for camera -->
                        <div id="cameraContainer" class="d-none">
                            <video id="videoElement" class="w-100 mb-2" autoplay playsinline></video>
                            <canvas id="canvas" class="d-none"></canvas>
                            <div class="text-center mb-2">
                                <button type="button" class="btn btn-primary" onclick="takePhoto()">Capture</button>
                                <button type="button" class="btn btn-secondary" onclick="closeCamera()">Cancel</button>
                            </div>
                        </div>
                        <!-- Preview section -->
                        <div id="imagePreview" class="row g-2 mb-3"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="updateStatusForm" method="POST" action="update_ticket_status.php">
                <input type="hidden" name="ticket_id" id="updateTicketId">
                <div class="modal-header">
                    <h5 class="modal-title">Update Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <select class="form-select" name="status" required>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let videoStream = null;

async function openCamera() {
    const cameraContainer = document.getElementById('cameraContainer');
    const video = document.getElementById('videoElement');
    
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: 'environment' }, 
            audio: false 
        });
        videoStream = stream;
        video.srcObject = stream;
        await video.play();
        
        cameraContainer.classList.remove('d-none');
    } catch (err) {
        console.error('Error accessing camera:', err);
        alert('Could not access camera. Please make sure you have granted permission to use it.');
    }
}

function closeCamera() {
    const cameraContainer = document.getElementById('cameraContainer');
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    cameraContainer.classList.add('d-none');
}

function takePhoto() {
    const video = document.getElementById('videoElement');
    const canvas = document.getElementById('canvas');
    const mainInput = document.getElementById('mainPhotoInput');

    // Set canvas size to match video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    // Draw video frame to canvas
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Convert canvas to file
    canvas.toBlob((blob) => {
        const dataTransfer = new DataTransfer();
        
        // Add existing files
        if (mainInput.files.length > 0) {
            Array.from(mainInput.files).forEach(file => {
                dataTransfer.items.add(file);
            });
        }

        // Add new photo
        const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
        dataTransfer.items.add(file);
        
        mainInput.files = dataTransfer.files;
        previewImages(mainInput);
        
        closeCamera();
    }, 'image/jpeg');
}

function handleFileSelect(input) {
    const mainInput = document.getElementById('mainPhotoInput');
    const dataTransfer = new DataTransfer();
    
    // Add existing files
    if (mainInput.files.length > 0) {
        Array.from(mainInput.files).forEach(file => {
            dataTransfer.items.add(file);
        });
    }
    
    // Add new files
    Array.from(input.files).forEach(file => {
        dataTransfer.items.add(file);
    });
    
    mainInput.files = dataTransfer.files;
    previewImages(mainInput);
}

function previewImages(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';

    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            const div = document.createElement('div');
            div.className = 'col-6 col-md-4 position-relative';
            
            reader.onload = function(e) {
                div.innerHTML = `
                    <div class="position-relative">
                        <img src="${e.target.result}" class="img-thumbnail w-100" style="height: 150px; object-fit: cover;">
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" 
                                onclick="removeImage(this)">×</button>
                    </div>
                `;
            }
            
            reader.readAsDataURL(file);
            preview.appendChild(div);
        });
    }
}

function removeImage(button) {
    const mainInput = document.getElementById('mainPhotoInput');
    const dataTransfer = new DataTransfer();
    const imageDiv = button.closest('.col-6');
    const imageIndex = Array.from(imageDiv.parentNode.children).indexOf(imageDiv);
    
    // Copy all files except the removed one
    Array.from(mainInput.files).forEach((file, index) => {
        if (index !== imageIndex) {
            dataTransfer.items.add(file);
        }
    });
    
    mainInput.files = dataTransfer.files;
    imageDiv.remove();
}

function updateStatus(ticketId) {
    document.getElementById('updateTicketId').value = ticketId;
    var modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
}
</script>

<?php include 'footer.php'; ?>