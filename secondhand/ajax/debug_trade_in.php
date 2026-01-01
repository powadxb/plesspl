<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

if(!isset($_SESSION['dins_user_id'])){
    die('Not authenticated. Please log in first.');
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

echo "<h1>Trade-In System Debug</h1>";
echo "<h2>User Info</h2>";
echo "<pre>";
print_r([
    'id' => $user_details['id'],
    'username' => $user_details['username'],
    'admin' => $user_details['admin'],
    'location' => $user_details['user_location']
]);
echo "</pre>";

// Check required tables
echo "<h2>Database Tables</h2>";
$required_tables = [
    'trade_in_items',
    'trade_in_items_details', 
    'trade_in_item_photos',
    'trade_in_id_photos',
    'trade_in_signatures'
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";

foreach($required_tables as $table) {
    $exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = ?
    ", [$table])[0]['count'] ?? 0;
    
    $row_count = 0;
    if($exists) {
        try {
            $row_count = $DB->query("SELECT COUNT(*) as cnt FROM `$table`")[0]['cnt'] ?? 0;
        } catch (Exception $e) {
            $row_count = 'Error: ' . $e->getMessage();
        }
    }
    
    $status = $exists ? '✅ Exists' : '❌ Missing';
    echo "<tr>";
    echo "<td>$table</td>";
    echo "<td>$status</td>";
    echo "<td>$row_count</td>";
    echo "</tr>";
}
echo "</table>";

// Check if repairs database is accessible
echo "<h2>Repairs Database Connection</h2>";
try {
    $repairsDB = new PDO(
        "mysql:host=localhost;dbname=sitegroundrepairs;charset=utf8mb4",
        "root",
        ""
    );
    $repairsDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check customers table
    $result = $repairsDB->query("SELECT COUNT(*) as cnt FROM customers")->fetch();
    echo "<p>✅ Repairs database accessible</p>";
    echo "<p>Customers table has " . $result['cnt'] . " records</p>";
    
    // Show customers table structure
    echo "<h3>Customers Table Structure</h3>";
    $columns = $repairsDB->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")" . ($col['Null'] == 'NO' ? ' NOT NULL' : '') . "\n";
    }
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p>❌ Repairs database error: " . $e->getMessage() . "</p>";
}

// Check upload directories
echo "<h2>Upload Directories</h2>";
$upload_dirs = [
    '../uploads/trade_in_items/',
    '../uploads/trade_in_ids/',
    '../uploads/trade_in_signatures/'
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Directory</th><th>Status</th><th>Writable</th></tr>";

foreach($upload_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    $exists = is_dir($full_path);
    $writable = $exists && is_writable($full_path);
    
    echo "<tr>";
    echo "<td>" . basename($dir) . "</td>";
    echo "<td>" . ($exists ? '✅ Exists' : '❌ Missing') . "</td>";
    echo "<td>" . ($writable ? '✅ Writable' : '❌ Not Writable') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show trade_in_items table structure if it exists
$table_exists = $DB->query("
    SELECT COUNT(*) as count
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
    AND table_name = 'trade_in_items'
")[0]['count'] ?? 0;

if($table_exists) {
    echo "<h2>trade_in_items Table Structure</h2>";
    $columns = $DB->query("SHOW COLUMNS FROM trade_in_items");
    echo "<pre>";
    foreach($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")" . ($col['Null'] == 'NO' ? ' NOT NULL' : '') . "\n";
    }
    echo "</pre>";
}

echo "<hr>";
echo "<h2>Action Required</h2>";
$missing_tables = [];
foreach($required_tables as $table) {
    $exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = ?
    ", [$table])[0]['count'] ?? 0;
    
    if(!$exists) {
        $missing_tables[] = $table;
    }
}

if(!empty($missing_tables)) {
    echo "<p style='color: red; font-weight: bold;'>❌ Missing tables: " . implode(', ', $missing_tables) . "</p>";
    echo "<p>Please run the migration script: <a href='../php/migrate_trade_in_system.php' target='_blank'>migrate_trade_in_system.php</a></p>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✅ All tables exist!</p>";
}

// Test a simple insert
if(empty($missing_tables)) {
    echo "<h2>Test Insert</h2>";
    try {
        // Try to insert a test record
        $sql = "INSERT INTO trade_in_items (
                    customer_name, customer_phone, customer_email,
                    location, status, total_value, payment_method,
                    cash_amount, bank_amount,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, 'pending', ?, 'cash', ?, ?, ?, NOW())";
        
        $DB->query($sql, [
            'Test Customer',
            '0123456789',
            'test@example.com',
            $user_details['user_location'] ?? 'cs',
            50.00,
            50.00,
            0.00,
            $user_id
        ]);
        
        $test_id = $DB->lastInsertId();
        
        // Delete the test record
        $DB->query("DELETE FROM trade_in_items WHERE id = ?", [$test_id]);
        
        echo "<p style='color: green;'>✅ Test insert successful!</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Test insert failed: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='../trade_in_workflow.php'>Back to Trade-In Workflow</a></p>";
?>
