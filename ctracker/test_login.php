<?php
// test_login.php
$host = 'localhost';
$dbname = 'cashtracker';
$user = 'cashtrackeruser';    // Replace with your actual database username
$pass = 'Lq1XdrkW3GGgBrdm5wte';    // Replace with your actual database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get the admin user
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Admin user details:<br>";
    echo "Username: " . $user['username'] . "<br>";
    echo "Stored password hash: " . $user['password'] . "<br>";
    
    // Test password verification
    $test_password = 'admin123';
    $matches = password_verify($test_password, $user['password']);
    echo "<br>Password test:<br>";
    echo "Testing password 'admin123'<br>";
    echo "Matches: " . ($matches ? 'Yes' : 'No');
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>