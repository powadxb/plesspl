<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session only if it hasn't already been started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in via session or cookies
if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

// Include the database connection setup
require __DIR__ . '/php/bootstrap.php';

// Check if the "action" parameter is set to "exportMagento"
if (isset($_GET['action']) && $_GET['action'] === 'exportMagento') {
    try {
        // Fetch SKU and price data from the "master_products" table where "export_to_magento" is set to "y"
        $sql = "SELECT sku, price FROM master_products WHERE export_to_magento = 'y'";
        $stmt = $DB->query($sql);

        // Check if query execution was successful
        if ($stmt === false) {
            throw new PDOException('Query failed');
        }

        // Fetch all the data
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare the data for CSV export
        $csvData = "SKU,Price\n"; // Add a header row
        foreach ($data as $row) {
            // Enclose values in double quotes to handle commas or special characters in data
            $csvData .= '"' . $row['sku'] . '","' . $row['price'] . '"' . "\n";
        }

        // Set headers for the CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="magento_export.csv"');

        // Output the CSV data
        echo $csvData;
        exit();
    } catch (PDOException $e) {
        // Handle database errors
        echo 'Error: ' . $e->getMessage();
        exit();
    }
}
