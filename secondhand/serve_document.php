<?php
/**
 * Secure Document Server
 * Serves ID verification documents with permission checks
 * 
 * Usage: serve_document.php?id=123
 * 
 * Security features:
 * - Requires authentication
 * - Checks "SecondHand-View Documents" permission
 * - Validates document belongs to accessible trade-in
 * - No directory traversal
 * - Logs all access
 */

session_start();
require_once '../php/bootstrap.php';

// Check authentication
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    die('Authentication required');
}

$user_id = $_SESSION['dins_user_id'];

// Get user details
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
if (!$user_details) {
    http_response_code(403);
    die('Invalid user');
}

// Check permission to view documents
$can_view = $DB->query(
    "SELECT has_access FROM user_permissions 
     WHERE user_id = ? AND page = 'SecondHand-View Documents'",
    [$user_id]
);

if (empty($can_view) || !$can_view[0]['has_access']) {
    // Log unauthorized access attempt
    error_log("SECURITY: User {$user_id} ({$user_details['username']}) attempted to access document without permission from IP {$_SERVER['REMOTE_ADDR']}");
    
    http_response_code(403);
    die('Access denied: You do not have permission to view documents');
}

// Get document ID
$doc_id = intval($_GET['id'] ?? 0);
if ($doc_id <= 0) {
    http_response_code(400);
    die('Invalid document ID');
}

// Get document details from database
$doc = $DB->query("SELECT * FROM trade_in_id_photos WHERE id = ?", [$doc_id]);
if (empty($doc)) {
    http_response_code(404);
    die('Document not found');
}
$doc = $doc[0];

// Verify user has access to the trade-in this document belongs to
$trade_in = $DB->query("SELECT * FROM trade_in_items WHERE id = ?", [$doc['trade_in_id']]);
if (empty($trade_in)) {
    http_response_code(404);
    die('Associated trade-in not found');
}
$trade_in = $trade_in[0];

// Check if user can access this location (if location restrictions exist)
$can_access_location = $DB->query(
    "SELECT has_access FROM user_permissions 
     WHERE user_id = ? AND page = 'SecondHand-View All Locations'",
    [$user_id]
);
$can_view_all_locations = !empty($can_access_location) && $can_access_location[0]['has_access'];

if (!$can_view_all_locations && $trade_in['location'] !== $user_details['user_location']) {
    error_log("SECURITY: User {$user_id} attempted to access document from different location");
    http_response_code(403);
    die('Access denied: Document is from a different location');
}

// Build file path
// Currently files are in: /secondhand/uploads/trade_in_ids/
// Path in DB is stored as: uploads/trade_in_ids/filename.jpg
$base_path = __DIR__ . '/';
$filepath = $base_path . $doc['file_path'];

// Security: Prevent directory traversal
$real_path = realpath($filepath);
$allowed_base = realpath($base_path . 'uploads/trade_in_ids/');

if ($real_path === false || strpos($real_path, $allowed_base) !== 0) {
    error_log("SECURITY: Directory traversal attempt by user {$user_id}: {$filepath}");
    http_response_code(403);
    die('Access denied: Invalid file path');
}

// Check if file exists
if (!file_exists($filepath)) {
    error_log("ERROR: Document file not found: {$filepath}");
    http_response_code(404);
    die('Document file not found on server');
}

// Log successful access
error_log("AUDIT: User {$user_id} ({$user_details['username']}) viewed document {$doc_id} (trade-in {$doc['trade_in_id']}) from IP {$_SERVER['REMOTE_ADDR']}");

// Decrypt the file contents
try {
    $encrypted_contents = file_get_contents($filepath);
    $decrypted_contents = Encryption::decrypt($encrypted_contents);
} catch (Exception $e) {
    error_log("ERROR: Failed to decrypt document {$doc_id}: " . $e->getMessage());
    http_response_code(500);
    die('Failed to decrypt document');
}

// Determine content type based on file extension (remove .enc from path)
$original_filename = str_replace('.enc', '', basename($filepath));
$ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
$content_types = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'pdf'  => 'application/pdf'
];
$content_type = $content_types[$ext] ?? 'application/octet-stream';

// Serve the file
header('Content-Type: ' . $content_type);
header('Content-Length: ' . strlen($decrypted_contents));
header('Content-Disposition: inline; filename="document_' . $doc_id . '.' . $ext . '"');

// Prevent caching of sensitive documents
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Send decrypted file
echo $decrypted_contents;
exit;
