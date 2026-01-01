<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'repairshopdbuser');
define('DB_PASS', 'amjadchorharamda');
define('DB_NAME', 'repairshopdb');
define('SITE_URL', 'https://plesspl.dinstech.co.uk/shop');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/shop/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Email configuration
define('SMTP_HOST', 'smtp.your-domain.com');
define('SMTP_USER', 'noreply@your-domain.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_PORT', 587);

// Session configuration
session_start();

// Database connection function
function getDB() {
    try {
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch(PDOException $e) {
        error_log("Connection failed: " . $e->getMessage());
        return null;
    }
}
