<?php
// check_data.php
require_once 'config.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=cashtracker", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Daily Takings</h2>";
    $takings = $conn->query("SELECT * FROM daily_takings ORDER BY entry_date DESC LIMIT 5");
    echo "<pre>";
    while($row = $takings->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
    
    echo "<h2>Cash Movements</h2>";
    $movements = $conn->query("SELECT * FROM cash_movements ORDER BY movement_date DESC LIMIT 5");
    echo "<pre>";
    while($row = $movements->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
    
    echo "<h2>Daily Balances</h2>";
    $balances = $conn->query("SELECT * FROM daily_balances ORDER BY date DESC LIMIT 5");
    echo "<pre>";
    while($row = $balances->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>