<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'cashtrackeruser');
define('DB_PASS', 'Lq1XdrkW3GGgBrdm5wte');

try {
    // First connect without a database selected
    $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create the database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS cashtracker";
    $conn->exec($sql);
    echo "Database created successfully<br>";
    
    // Select the database
    $conn->exec("USE cashtracker");
    
    // Create tables
    $sql = "
    CREATE TABLE IF NOT EXISTS branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    );

    CREATE TABLE IF NOT EXISTS daily_takings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT,
        entry_date DATE NOT NULL,
        cash_taken DECIMAL(10,2) NOT NULL,
        card_payments DECIMAL(10,2) NOT NULL,
        paypal_amount DECIMAL(10,2) DEFAULT 0,
        bank_transfers DECIMAL(10,2) DEFAULT 0,
        float_amount DECIMAL(10,2) NOT NULL,
        notes TEXT,
        entry_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id)
    );

    CREATE TABLE IF NOT EXISTS cash_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT,
        movement_date DATETIME NOT NULL,
        type ENUM('banking', 'expense', 'wages', 'float_adjustment') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        notes TEXT,
        FOREIGN KEY (branch_id) REFERENCES branches(id)
    );";
    
    // Execute the multi-query SQL
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        if (trim($query) != '') {
            $conn->exec($query);
        }
    }
    echo "Tables created successfully<br>";
    
    // Insert a default branch if needed
    $sql = "INSERT INTO branches (name) SELECT 'Main Branch' 
            WHERE NOT EXISTS (SELECT 1 FROM branches WHERE name = 'Main Branch')";
    $conn->exec($sql);
    echo "Default branch created (if it didn't exist)<br>";
    
    echo "Setup completed successfully!";
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>