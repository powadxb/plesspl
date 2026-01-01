<?php
require __DIR__.'/../../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get user details - check authorization
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];
$is_authorized = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);

if(!$is_authorized) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$rma_id = $_POST['rma_id'] ?? '';
$supplier_name = $_POST['supplier_name'] ?? '';
$document_number = $_POST['document_number'] ?? null;
$document_date = $_POST['document_date'] ?? null;
$mark_resolved = intval($_POST['mark_resolved'] ?? 0);

if(empty($rma_id) || empty($supplier_name)) {
    echo json_encode(['success' => false, 'message' => 'RMA ID and supplier name required']);
    exit();
}

try {
    // Update RMA with supplier details
    $DB->query("
        UPDATE rma_items
        SET 
            supplier_name = ?,
            document_number = ?,
            document_date = ?,
            needs_review = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [
        $supplier_name,
        $document_number,
        $document_date,
        $mark_resolved ? 0 : 1,
        $rma_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Supplier details assigned successfully'
    ]);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
