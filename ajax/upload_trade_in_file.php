<?php
// ================ INITIALIZATION START ================
session_start();
require '../php/bootstrap.php';
if (!isset($_SESSION['dins_user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}
// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/trade_ins/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
// ================ INITIALIZATION END ================

// ================ CONFIGURATION START ================
$allowed_types = [
    'item_photo' => ['image/jpeg', 'image/png', 'image/gif'],
    'id_document' => ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']
];
$max_file_size = 10 * 1024 * 1024; // 10MB
// ================ CONFIGURATION END ================

// ================ MAIN LOGIC START ================
try {
    // Validate inputs
    if (!isset($_POST['trade_in_id']) || !isset($_POST['file_type'])) {
        throw new Exception('Missing required parameters');
    }
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }

    $trade_in_id = intval($_POST['trade_in_id']);
    $file_type = $_POST['file_type'];
    $file = $_FILES['file'];

    // Validate trade-in exists
    $trade_in = $DB->query(
        "SELECT id FROM trade_in_items WHERE id = ?", 
        [$trade_in_id]
    )[0] ?? null;

    if (!$trade_in) { // Fixed: Changed !trade_in to !$trade_in
        throw new Exception('Trade-in item not found');
    }

    // Validate file size
    if ($file['size'] > $max_file_size) {
        throw new Exception('File size exceeds maximum limit of 10MB');
    }

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!isset($allowed_types[$file_type]) || !in_array($mime_type, $allowed_types[$file_type])) {
        throw new Exception('Invalid file type');
    }

    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '_' . time() . '.' . $extension;

    // Move file to upload directory
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
        throw new Exception('Failed to save file');
    }

    // Save file record in database
    $DB->query(
        "INSERT INTO trade_in_files 
            (trade_in_id, file_type, original_filename, stored_filename, 
             file_extension, file_size, uploaded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $trade_in_id,
            $file_type,
            $file['name'],
            $new_filename,
            $extension,
            $file['size'],
            $_SESSION['dins_user_id']
        ]
    );

    $file_id = $DB->lastInsertId();

    echo json_encode([
        'success' => true,
        'file' => [
            'id' => $file_id,
            'filename' => $new_filename,
            'original_name' => $file['name'],
            'url' => 'uploads/trade_ins/' . $new_filename
        ]
    ]);

} catch (Exception $e) {
    error_log("File upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
// ================ MAIN LOGIC END ================