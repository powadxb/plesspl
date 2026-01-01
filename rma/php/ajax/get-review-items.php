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

try {
    // Get items needing review
    $items = $DB->query("
        SELECT 
            r.id,
            r.barcode,
            r.tracking_number,
            r.serial_number,
            r.sku,
            r.product_name,
            r.ean,
            r.location,
            r.date_discovered,
            ft.fault_name
        FROM rma_items r
        INNER JOIN rma_fault_types ft ON r.fault_type_id = ft.id
        WHERE r.needs_review = 1
        AND r.status = 'unprocessed'
        ORDER BY r.created_at ASC
    ");

    echo json_encode([
        'success' => true,
        'data' => $items
    ]);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
