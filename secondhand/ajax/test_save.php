<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

if(!isset($_SESSION['dins_user_id'])){
    die('Not authenticated');
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Test insert
    try {
        $sql = "INSERT INTO trade_in_items (
                    customer_name, customer_phone, customer_email,
                    location, status, total_value, payment_method,
                    cash_amount, bank_amount, compliance_notes,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, 'pending', ?, 'cash', ?, ?, ?, ?, NOW())";
        
        $DB->query($sql, [
            'Test Customer',
            '0123456789',
            'test@example.com',
            $user_details['user_location'] ?? 'cs',
            100.00,
            100.00,
            0.00,
            'Test trade-in',
            $user_id
        ]);
        
        $trade_in_id = $DB->lastInsertId();
        
        echo "<h2 style='color: green;'>✅ Success!</h2>";
        echo "<p>Trade-in ID created: $trade_in_id</p>";
        
        // Add a test item
        $sql = "INSERT INTO trade_in_items_details (
                    trade_in_id, item_name, category, serial_number,
                    `condition`, price_paid, notes, tracking_code,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $DB->query($sql, [
            $trade_in_id,
            'Test Laptop',
            'Laptops',
            'SN12345',
            'good',
            100.00,
            'Test notes',
            'SH1'
        ]);
        
        $item_id = $DB->lastInsertId();
        echo "<p>Item ID created: $item_id</p>";
        
        echo "<p><a href='?view=$trade_in_id'>View This Trade-In</a></p>";
        echo "<p><a href='?delete=$trade_in_id'>Delete This Test</a></p>";
        
    } catch (Exception $e) {
        echo "<h2 style='color: red;'>❌ Error</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
} elseif(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $DB->query("DELETE FROM trade_in_items WHERE id = ?", [$id]);
        echo "<p style='color: green;'>✅ Test trade-in deleted</p>";
        echo "<p><a href='test_save.php'>Run Another Test</a></p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
    
} elseif(isset($_GET['view'])) {
    $id = intval($_GET['view']);
    
    $trade_in = $DB->query("SELECT * FROM trade_in_items WHERE id = ?", [$id])[0] ?? null;
    if($trade_in) {
        echo "<h2>Trade-In #$id</h2>";
        echo "<pre>";
        print_r($trade_in);
        echo "</pre>";
        
        $items = $DB->query("SELECT * FROM trade_in_items_details WHERE trade_in_id = ?", [$id]);
        echo "<h3>Items (" . count($items) . ")</h3>";
        echo "<pre>";
        print_r($items);
        echo "</pre>";
        
        echo "<p><a href='?delete=$id'>Delete This Test</a></p>";
    } else {
        echo "<p>Trade-in not found</p>";
    }
    echo "<p><a href='test_save.php'>Run Another Test</a></p>";
    
} else {
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Trade-In Save</title>
</head>
<body>
    <h1>Test Trade-In Save</h1>
    <p>This will test if the database schema is correct and can accept trade-in data.</p>
    
    <form method="POST">
        <button type="submit" style="padding: 10px 20px; font-size: 16px;">
            Run Test Insert
        </button>
    </form>
    
    <hr>
    
    <h2>Recent Trade-Ins</h2>
    <?php
    $recent = $DB->query("SELECT * FROM trade_in_items ORDER BY id DESC LIMIT 5");
    if(empty($recent)) {
        echo "<p>No trade-ins found</p>";
    } else {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Customer</th><th>Total</th><th>Status</th><th>Created</th><th>Action</th></tr>";
        foreach($recent as $r) {
            echo "<tr>";
            echo "<td>" . $r['id'] . "</td>";
            echo "<td>" . htmlspecialchars($r['customer_name']) . "</td>";
            echo "<td>£" . number_format($r['total_value'] ?? $r['offered_price'] ?? 0, 2) . "</td>";
            echo "<td>" . $r['status'] . "</td>";
            echo "<td>" . $r['created_at'] . "</td>";
            echo "<td><a href='?view=" . $r['id'] . "'>View</a> | <a href='?delete=" . $r['id'] . "'>Delete</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    ?>
    
    <hr>
    <p><a href='debug_trade_in.php'>Back to Debug Page</a></p>
</body>
</html>
<?php
}
?>
