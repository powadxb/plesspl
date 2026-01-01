<?php
session_start();
require 'bootstrap.php';

if(!isset($_SESSION['dins_user_id'])){
    echo 'unauthorized';
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Only admins can save instructions
if ($user_details['admin'] < 1) {
    echo 'unauthorized';
    exit;
}

if ($_POST && isset($_POST['instruction']) && isset($_POST['product_sku'])) {
    $instruction = trim($_POST['instruction']);
    $product_sku = trim($_POST['product_sku']);
    
    try {
        // Check if instruction already exists for this SKU
        $existing = $DB->query("SELECT id FROM product_instructions WHERE sku = ?", [$product_sku]);
        
        if (!empty($existing)) {
            // Update existing instruction
            $DB->query("UPDATE product_instructions SET instruction = ?, updated_at = NOW(), updated_by = ? WHERE sku = ?", 
                       [$instruction, $user_id, $product_sku]);
        } else {
            // Insert new instruction
            $DB->query("INSERT INTO product_instructions (sku, instruction, created_at, updated_at, created_by, updated_by) VALUES (?, ?, NOW(), NOW(), ?, ?)", 
                       [$product_sku, $instruction, $user_id, $user_id]);
        }
        
        echo 'success';
    } catch (Exception $e) {
        error_log("Instruction save error: " . $e->getMessage());
        echo 'error';
    }
} else {
    echo 'invalid_request';
}
?>