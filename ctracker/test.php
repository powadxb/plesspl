<?php
// test.php
$host = 'localhost';
$dbname = 'cashtracker';
$user = 'cashtrackeruser';    // Replace with your actual database username
$pass = 'Lq1XdrkW3GGgBrdm5wte';    // Replace with your actual database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if users table exists and show its content
    $stmt = $conn->query("SELECT * FROM users");
    echo "Current users in database:<br>";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Username: " . $row['username'] . "<br>";
    }
    
    echo "Database connection working!";
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>