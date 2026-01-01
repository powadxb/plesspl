<?php
/**
 * SUPER SIMPLE TEST - Step by step to find the error
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: PHP is working<br>";

// Test bootstrap path
$bootstrap_path = __DIR__.'/../../../../php/bootstrap.php';
echo "Step 2: Bootstrap path: " . $bootstrap_path . "<br>";
echo "Step 3: Bootstrap exists? " . (file_exists($bootstrap_path) ? 'YES' : 'NO') . "<br>";

if (!file_exists($bootstrap_path)) {
    echo "<strong>ERROR: Bootstrap file not found!</strong><br>";
    echo "Current directory: " . __DIR__ . "<br>";
    echo "Looking for: " . $bootstrap_path . "<br>";
    
    // Try to find it
    $try1 = __DIR__.'/../../../php/bootstrap.php';
    $try2 = __DIR__.'/../../php/bootstrap.php';
    $try3 = '/home/dinstech-plesspl/htdocs/plesspl.dinstech.co.uk/public/php/bootstrap.php';
    
    echo "<br>Trying different paths:<br>";
    echo "3 levels up: " . ($try1) . " = " . (file_exists($try1) ? 'FOUND' : 'not found') . "<br>";
    echo "2 levels up: " . ($try2) . " = " . (file_exists($try2) ? 'FOUND' : 'not found') . "<br>";
    echo "Absolute: " . ($try3) . " = " . (file_exists($try3) ? 'FOUND' : 'not found') . "<br>";
    exit;
}

require $bootstrap_path;

echo "Step 4: Bootstrap loaded successfully<br>";

session_start();
echo "Step 5: Session started<br>";

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo "Step 6: NOT AUTHENTICATED (this is okay for testing)<br>";
} else {
    echo "Step 6: User is authenticated<br>";
    echo "User ID from bootstrap: " . (isset($user_id) ? $user_id : 'NOT SET') . "<br>";
}

echo "Step 7: Checking database connection<br>";
if (isset($DB)) {
    echo "Database object exists: YES<br>";
    
    try {
        $test = $DB->query("SELECT 1 as test");
        echo "Database query works: YES<br>";
        
        // Check if table exists
        $tableCheck = $DB->query("SHOW TABLES LIKE 'rma_supplier_batches'");
        if (empty($tableCheck)) {
            echo "<strong>ERROR: Table 'rma_supplier_batches' does NOT exist!</strong><br>";
            echo "You need to run the SQL file: rma_phase2_table.sql<br>";
        } else {
            echo "Table 'rma_supplier_batches' exists: YES<br>";
            
            // Count batches
            $count = $DB->query("SELECT COUNT(*) as cnt FROM rma_supplier_batches");
            echo "Current batch count: " . $count[0]['cnt'] . "<br>";
        }
        
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Database object exists: NO<br>";
}

echo "<br><strong>TEST COMPLETE</strong>";
