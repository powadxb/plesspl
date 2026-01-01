<?php
session_start();
header('Content-Type: application/json');

try {
    // Step 1: Test basic PHP
    $step = $_GET['step'] ?? '1';
    
    if ($step == '1') {
        echo json_encode(['step' => 1, 'status' => 'PHP working']);
        exit;
    }
    
    // Step 2: Test bootstrap
    if ($step == '2') {
        require '../php/bootstrap.php';
        echo json_encode(['step' => 2, 'status' => 'Bootstrap loaded', 'db_exists' => isset($DB)]);
        exit;
    }
    
    // Step 3: Test database
    if ($step == '3') {
        require '../php/bootstrap.php';
        $test = $DB->query("SELECT 1 as test");
        echo json_encode(['step' => 3, 'status' => 'Database working', 'result' => $test]);
        exit;
    }
    
    // Step 4: Test session
    if ($step == '4') {
        require '../php/bootstrap.php';
        echo json_encode([
            'step' => 4, 
            'status' => 'Session test',
            'user_id' => $_SESSION['dins_user_id'] ?? 'not set',
            'session_active' => session_status() === PHP_SESSION_ACTIVE
        ]);
        exit;
    }
    
    // Step 5: Test table queries
    if ($step == '5') {
        require '../php/bootstrap.php';
        
        $tables = [];
        $tables['categories'] = $DB->query("SELECT COUNT(*) as count FROM master_essential_categories")[0]['count'];
        $tables['product_types'] = $DB->query("SELECT COUNT(*) as count FROM master_essential_product_types")[0]['count'];
        $tables['mappings'] = $DB->query("SELECT COUNT(*) as count FROM master_essential_product_mappings")[0]['count'];
        
        echo json_encode(['step' => 5, 'status' => 'All tables working', 'counts' => $tables]);
        exit;
    }
    
    echo json_encode(['error' => 'Invalid step. Use ?step=1,2,3,4,or 5']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile()),
        'step' => $step ?? 'unknown'
    ]);
}
?>