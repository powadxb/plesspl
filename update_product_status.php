<?php
require 'php/bootstrap.php';
if (!isset($_SESSION['dins_user_id'])) {
    echo 'unauthorized';
    exit;
}
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT admin FROM users WHERE id=?", [$user_id])[0];
if ($user_details['admin'] == 0) {
    echo 'unauthorized';
    exit;
}

// Debug output
error_log("Received request: " . print_r($_POST, true));

if (isset($_POST['sku']) && isset($_POST['field']) && isset($_POST['value'])) {
    $sku = $_POST['sku'];
    $field = $_POST['field'];
    $value = $_POST['value'];
    
    // Debug output
    error_log("Processing update: SKU=$sku, Field=$field, Value=$value");
    
    // Validate field name to prevent SQL injection
    if (!in_array($field, ['enable', 'export_to_magento'])) {
        error_log("Invalid field: $field");
        echo 'invalid_field';
        exit;
    }
    
    // Validate value
    if (!in_array($value, ['y', 'n'])) {
        error_log("Invalid value: $value");
        echo 'invalid_value';
        exit;
    }
    
    try {
        $query = "UPDATE master_products SET $field = ? WHERE sku = ?";
        error_log("Executing query: $query with params: [$value, $sku]");
        
        $result = $DB->query($query, [$value, $sku]);
        
        if ($result !== false) {
            error_log("Update successful");
            echo 'updated';
        } else {
            error_log("Update failed");
            echo 'error';
        }
    } catch (Exception $e) {
        error_log("Exception occurred: " . $e->getMessage());
        echo 'error';
    }
} else {
    error_log("Missing parameters");
    echo 'missing_parameters';
}