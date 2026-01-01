<?php
// ticket_details.php
require_once 'config.php';
require_once 'auth.php';
require_once 'ticket.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit();
}

$ticketId = $_GET['id'] ?? null;
if (!$ticketId) {
    header('Location: tickets.php');
    exit();
}

$db = getDB();

// Get ticket details with customer info
$stmt = $db->prepare("
    SELECT 
        t.*,
        c.first_name,
        c.last_name,
        c.email,
        c.phone
    FROM repair_tickets t
    JOIN customers c ON t.customer_id = c.id
    WHERE t.id = ?
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: tickets.php');
    exit();
}


// Get comments/updates
$stmt = $db->prepare("
    SELECT 
        tu.*,
        u.username
    FROM ticket_updates tu
    JOIN users u ON tu.user_id = u.id
    WHERE tu.ticket_id = ?
    ORDER BY tu.created_at DESC
");
$stmt->execute([$ticketId]);
$updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get photos
$stmt = $db->prepare("
    SELECT *
    FROM ticket_photos
    WHERE ticket_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$ticketId]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get outsourcing details
$stmt = $db->prepare("
    SELECT *
    FROM outsourced_repairs
    WHERE ticket_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$ticketId]);
$outsourceDetails = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_comment':
            $stmt = $db->prepare("
                INSERT INTO ticket_updates 
                (ticket_id, user_id, update_type, content)
                VALUES (?, ?, 'comment', ?)
            ");
            $stmt->execute([$ticketId, $_SESSION['user_id'], $_POST['content']]);
            header("Location: ticket_details.php?id=$ticketId&success=1");
            exit();
            break;

        case 'add_photos':
            if (isset($_FILES['photos'])) {
                $uploadDir = UPLOAD_DIR;
                foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = uniqid() . '_' . $_FILES['photos']['name'][$key];
                        if (move_uploaded_file($tmp_name, $uploadDir . $filename)) {
                            $stmt = $db->prepare("
                                INSERT INTO ticket_photos 
                                (ticket_id, photo_path, uploaded_by)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$ticketId, $filename, $_SESSION['user_id']]);
                        }
                    }
                }
                header("Location: ticket_details.php?id=$ticketId&success=1");
                exit();
            }
            break;

        case 'update_status':
            if (isset($_POST['status'])) {
                $stmt = $db->prepare("
                    UPDATE repair_tickets 
                    SET status = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['status'], $ticketId]);
                header("Location: ticket_details.php?id=$ticketId&success=1");
                exit();
            }
            break;
case 'update_cost':
    if (isset($_POST['repair_cost'])) {
        $stmt = $db->prepare("
            UPDATE repair_tickets 
            SET repair_cost = ? 
            WHERE id = ?
        ");
        $stmt->execute([$_POST['repair_cost'], $ticketId]);

        // Add a comment about the cost update
        $content = "Repair cost updated to £" . number_format($_POST['repair_cost'], 2);
        $stmt = $db->prepare("
            INSERT INTO ticket_updates 
            (ticket_id, user_id, update_type, content)
            VALUES (?, ?, 'comment', ?)
        ");
        $stmt->execute([$ticketId, $_SESSION['user_id'], $content]);

        header("Location: ticket_details.php?id=$ticketId&success=1");
        exit();
    }
    break;
        case 'outsource':
            $stmt = $db->prepare("
                INSERT INTO outsourced_repairs 
                (ticket_id, vendor_name, vendor_contact, sent_date, expected_return_date, cost, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'sent')
            ");
            $stmt->execute([
                $ticketId,
                $_POST['vendor_name'],
                $_POST['vendor_contact'],
                $_POST['sent_date'],
                $_POST['expected_return_date'] ?: null,
                $_POST['cost'] ?: null,
                $_POST['notes']
            ]);
            header("Location: ticket_details.php?id=$ticketId&success=1");
            exit();
            break;

        case 'update_outsource':
            $stmt = $db->prepare("
                UPDATE outsourced_repairs 
                SET status = ?,
                    returned_date = CASE WHEN ? = 'returned' THEN CURRENT_DATE ELSE NULL END
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['status'],
                $_POST['status'],
                $_POST['outsource_id']
            ]);
            header("Location: ticket_details.php?id=$ticketId&success=1");
            exit();
            break;
    }
}
?>

<?php include 'header.php'; ?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Ticket #<?= htmlspecialchars($ticket['ticket_number']) ?></h2>
            <div class="mt-2">
                <span class="badge bg-<?= 
                    $ticket['status'] === 'completed' ? 'success' : 
                    ($ticket['status'] === 'in_progress' ? 'primary' : 'warning') 
                ?>">
                    <?= ucfirst($ticket['status']) ?>
                </span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="update_status">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $ticket['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </form>
            <a href="tickets.php" class="btn btn-outline-primary">Back to Tickets</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Update saved successfully</div>
    <?php endif; ?>

    <div class="row">
        <!-- Ticket Information -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Repair Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Customer Information</h6>
                            <p>
                                <strong>Name:</strong> <?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?><br>
                                <strong>Phone:</strong> <?= htmlspecialchars($ticket['phone']) ?><br>
                                <strong>Email:</strong> <?= htmlspecialchars($ticket['email']) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>Device Information</h6>
                            <p>
                                <strong>Type:</strong> <?= htmlspecialchars($ticket['device_type']) ?><br>
                                <strong>Serial Number:</strong> <?= htmlspecialchars($ticket['serial_number']) ?><br>
                                <strong>Created:</strong> <?= date('M d, Y g:i A', strtotime($ticket['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                    <h6>Issue Description</h6>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($ticket['issue_description'])) ?></p>
                </div>
            </div>
<div class="row mb-3">
    <div class="col-12">
        <h6>Repair Cost</h6>
        <div class="d-flex align-items-center gap-3">
            <?php if ($ticket['repair_cost']): ?>
                <h3 class="mb-0">£<?= number_format($ticket['repair_cost'], 2) ?></h3>
            <?php endif; ?>
            
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateCostModal">
                <?= $ticket['repair_cost'] ? 'Update Cost' : 'Set Cost' ?>
            </button>
        </div>
    </div>
</div>
            <!-- Outsource Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Outsourced Repair</h5>
                    <?php if (!$outsourceDetails): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#outsourceModal">
                        Send to Vendor
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($outsourceDetails): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Vendor:</strong> <?= htmlspecialchars($outsourceDetails['vendor_name']) ?></p>
                                <p><strong>Contact:</strong> <?= htmlspecialchars($outsourceDetails['vendor_contact']) ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?= 
                                        $outsourceDetails['status'] === 'returned' ? 'success' : 
                                        ($outsourceDetails['status'] === 'in_progress' ? 'primary' : 'warning') 
                                    ?>">
                                        <?= ucfirst($outsourceDetails['status']) ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Sent Date:</strong> <?= date('M d, Y', strtotime($outsourceDetails['sent_date'])) ?></p>
                                <p><strong>Expected Return:</strong> 
                                    <?= $outsourceDetails['expected_return_date'] ? date('M d, Y', strtotime($outsourceDetails['expected_return_date'])) : 'Not specified' ?>
                                </p>
                                <?php if ($outsourceDetails['returned_date']): ?>
                                    <p><strong>Returned Date:</strong> <?= date('M d, Y', strtotime($outsourceDetails['returned_date'])) ?></p>
                                <?php endif; ?>
                                <p><strong>Cost:</strong> £<?= number_format($outsourceDetails['cost'], 2) ?></p>
                            </div>
                            <?php if ($outsourceDetails['notes']): ?>
                                <div class="col-12">
                                    <p><strong>Notes:</strong></p>
                                    <p><?= nl2br(htmlspecialchars($outsourceDetails['notes'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($outsourceDetails['status'] !== 'returned'): ?>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="update_outsource">
                                <input type="hidden" name="outsource_id" value="<?= $outsourceDetails['id'] ?>">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="sent" <?= $outsourceDetails['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                                            <option value="in_progress" <?= $outsourceDetails['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="returned" <?= $outsourceDetails['status'] === 'returned' ? 'selected' : '' ?>>Returned</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-primary">Update Status</button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">This repair has not been outsourced.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Comments & Updates</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_comment">
                        <div class="mb-3">
                            <label class="form-label">Add Comment</label>
                            <textarea class="form-control" name="content" rows="3" required 
                                    placeholder="Add a comment, diagnosis, or repair details..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Comment</button>
                    </form>

                    <div class="updates-timeline">
                        <?php foreach ($updates as $update): ?>
                            <div class="update-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($update['username']) ?></strong>
                                    <small class="text-muted">
                                        <?= date('M d, Y g:i A', strtotime($update['created_at'])) ?>
                                    </small>
                                </div>
                                <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($update['content'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

<!-- Photos Section -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Photos</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" name="action" value="add_photos">
                        <div class="mb-3">
                            <label class="form-label">Add Photos</label>
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
                            <video id="videoElement" class="d-none w-100 mb-2" autoplay playsinline></video>
                            <canvas id="canvas" class="d-none"></canvas>
                            <div id="cameraControls" class="d-none text-center mb-2">
                                <button type="button" class="btn btn-primary" onclick="takePhoto()">Capture</button>
                                <button type="button" class="btn btn-secondary" onclick="closeCamera()">Cancel</button>
                            </div>
                            <small class="form-text text-muted">
                                Take photos with your camera or select existing photos
                            </small>
                        </div>
                        <!-- Preview Container -->
                        <div id="imagePreview" class="row g-2 mb-3"></div>
                        <button type="submit" class="btn btn-primary">Upload Photos</button>
                    </form>

                    <!-- Existing Photos -->
                    <?php if (!empty($photos)): ?>
                        <!-- Intake Photos -->
                        <h6 class="mt-4">Check-in Photos</h6>
                        <div class="row g-2">
                            <?php foreach ($photos as $photo): ?>
                                <?php if ($photo['photo_type'] === 'intake'): ?>
                                    <div class="col-6">
                                        <a href="uploads/<?= htmlspecialchars($photo['photo_path']) ?>" 
                                           data-lightbox="intake-photos"
                                           class="d-block photo-thumbnail">
                                            <img src="uploads/<?= htmlspecialchars($photo['photo_path']) ?>" 
                                                 class="img-thumbnail w-100" 
                                                 alt="Intake photo">
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <!-- Repair Photos -->
                        <h6 class="mt-4">Repair Progress Photos</h6>
                        <div class="row g-2">
                            <?php foreach ($photos as $photo): ?>
                                <?php if ($photo['photo_type'] === 'repair'): ?>
                                    <div class="col-6">
                                        <a href="uploads/<?= htmlspecialchars($photo['photo_path']) ?>" 
                                           data-lightbox="repair-photos"
                                           class="d-block photo-thumbnail">
                                            <img src="uploads/<?= htmlspecialchars($photo['photo_path']) ?>" 
                                                 class="img-thumbnail w-100" 
                                                 alt="Repair photo">
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Outsource Modal -->
<div class="modal fade" id="outsourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="outsource">
                <div class="modal-header">
                    <h5 class="modal-title">Send to Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vendor Name</label>
                        <input type="text" class="form-control" name="vendor_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vendor Contact</label>
                        <input type="text" class="form-control" name="vendor_contact">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sent Date</label>
                                <input type="date" class="form-control" name="sent_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Expected Return Date</label>
                                <input type="date" class="form-control" name="expected_return_date">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estimated Cost</label>
                        <input type="number" class="form-control" name="cost" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send to Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Lightbox -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>

<style>
.photo-thumbnail {
    aspect-ratio: 1;
    overflow: hidden;
}

.photo-thumbnail img {
    object-fit: cover;
    height: 100%;
    width: 100%;
}

.updates-timeline .update-item:last-child {
    border-bottom: none !important;
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

#videoElement {
    max-width: 100%;
    border-radius: 4px;
}
</style>

<script>
let videoStream = null;

async function openCamera() {
    const video = document.getElementById('videoElement');
    const controls = document.getElementById('cameraControls');
    
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: true, 
            audio: false 
        });
        videoStream = stream;
        video.srcObject = stream;
        video.play();
        
        video.classList.remove('d-none');
        controls.classList.remove('d-none');
    } catch (err) {
        console.error('Error accessing camera:', err);
        alert('Could not access camera. Please make sure you have a camera connected and have granted permission to use it.');
    }
}

function closeCamera() {
    const video = document.getElementById('videoElement');
    const controls = document.getElementById('cameraControls');
    
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    
    video.classList.add('d-none');
    controls.classList.add('d-none');
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

function previewImages(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';

    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            const div = document.createElement('div');
            div.className = 'col-6';
            
            reader.onload = function(e) {
                div.innerHTML = `
                    <div class="photo-thumbnail">
                        <img src="${e.target.result}" class="img-thumbnail w-100" alt="Preview">
                    </div>
                `;
            }
            
            reader.readAsDataURL(file);
            preview.appendChild(div);
        });
    }
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
</script>
<!-- Update Cost Modal -->
<div class="modal fade" id="updateCostModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_cost">
                <div class="modal-header">
                    <h5 class="modal-title">Update Repair Cost</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">£</span>
                            <input type="number" class="form-control" name="repair_cost" 
                                   value="<?= $ticket['repair_cost'] ?? '' ?>" 
                                   step="0.01" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Cost</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>