<?php
// ajax/get_cctv_templates.php
session_start();
require '../php/bootstrap.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

try {
    // Fetch all templates
    $templates = $DB->query("
        SELECT 
            q.id,
            q.template_name,
            q.date_created,
            q.price_type,
            q.total_price,
            u.username as created_by_name,
            (SELECT COUNT(*) FROM cctv_quotation_items WHERE quote_id = q.id) as item_count
        FROM cctv_quotation_master q
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.is_template = 1
        ORDER BY q.date_created DESC
    ");
    
    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);

} catch (Exception $e) {
    error_log('Get CCTV Templates Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}