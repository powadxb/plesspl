<?php
// test_permissions.php - Upload this and run it to see the actual error

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'php/bootstrap.php';

echo "<h2>Testing Permissions Save</h2>";

$test_user_id = 1; // Change this to an actual user ID from your users table
$test_permissions = ['zindex', 'count_stock'];

echo "<p>Testing with user_id: $test_user_id</p>";

try {
    echo "<p>Step 1: Deleting existing permissions...</p>";
    $result = $DB->query("DELETE FROM user_permissions WHERE user_id = ?", [$test_user_id]);
    echo "<p style='color:green'>✓ DELETE worked</p>";
    
    echo "<p>Step 2: Inserting new permissions...</p>";
    foreach ($test_permissions as $page) {
        $DB->query("INSERT INTO user_permissions (user_id, page, has_access) VALUES (?, ?, 1)", [$test_user_id, $page]);
        echo "<p style='color:green'>✓ Inserted: $page</p>";
    }
    
    echo "<p style='color:green'><strong>SUCCESS! Everything works!</strong></p>";
    echo "<p>The problem is somewhere else in control_panel.php</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'><strong>ERROR: " . $e->getMessage() . "</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
