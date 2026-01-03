<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['dins_user_id'];

try {
    $item_id = $_POST['item_id'] ?? 0;
    
    if(!$item_id) {
        throw new Exception('Item ID is required');
    }
    
    // Verify item exists
    $item = $DB->query("SELECT * FROM second_hand_items WHERE id = ?", [$item_id])[0] ?? null;
    if(!$item) {
        throw new Exception('Item not found');
    }
    
    // Check if files were uploaded
    if(!isset($_FILES['photos']) || !is_array($_FILES['photos']['name'])) {
        throw new Exception('No photos uploaded');
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/second_hand_photos/';
    if(!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_count = 0;
    $errors = [];
    
    // Process each uploaded file
    $files = $_FILES['photos'];
    $file_count = count($files['name']);
    
    for($i = 0; $i < $file_count; $i++) {
        // Check if file was uploaded successfully
        if($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "File " . ($i + 1) . " upload failed";
            continue;
        }
        
        $file_name = $files['name'][$i];
        $file_tmp = $files['tmp_name'][$i];
        $file_size = $files['size'][$i];
        
        // Validate file type
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if(!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $errors[] = "$file_name: Invalid file type. Allowed: JPG, PNG, GIF";
            continue;
        }
        
        // Validate file size (max 5MB)
        if($file_size > 5 * 1024 * 1024) {
            $errors[] = "$file_name: File too large (max 5MB)";
            continue;
        }
        
        // Generate unique filename
        $new_filename = 'item_' . $item_id . '_' . time() . '_' . uniqid() . '.' . $ext;
        $filepath = $upload_dir . $new_filename;
        
        // Move uploaded file
        if(move_uploaded_file($file_tmp, $filepath)) {
            // Save to database
            $DB->query("
                INSERT INTO second_hand_item_photos (
                    item_id, file_path, file_type, uploaded_by
                ) VALUES (?, ?, 'item_photo', ?)
            ", [
                $item_id,
                'uploads/second_hand_photos/' . $new_filename,
                $user_id
            ]);
            
            $uploaded_count++;
        } else {
            $errors[] = "$file_name: Failed to save file";
        }
    }
    
    $message = "$uploaded_count photo(s) uploaded successfully";
    if(!empty($errors)) {
        $message .= ". Errors: " . implode(", ", $errors);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'uploaded_count' => $uploaded_count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
