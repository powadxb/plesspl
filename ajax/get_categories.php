<?php
// ================ INITIALIZATION START ================
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}
// ================ INITIALIZATION END ================

// ================ MAIN LOGIC START ================
try {
    // Get all categories ordered by main category
    $categories = $DB->query(
        "SELECT pless_main_category, pos_category 
         FROM master_categories 
         ORDER BY pless_main_category"
    );
    
    echo json_encode($categories);

} catch (Exception $e) {
    error_log("Get categories error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
// ================ MAIN LOGIC END ================