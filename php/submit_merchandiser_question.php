<?php
session_start();
require 'bootstrap.php';

if(!isset($_SESSION['dins_user_id'])){
    echo 'unauthorized';
    exit;
}

$user_id = $_SESSION['dins_user_id'];

if ($_POST && isset($_POST['merchandiser_question'])) {
    $question = trim($_POST['merchandiser_question']);
    $product_sku = trim($_POST['product_sku']);
    
    if (!empty($question)) {
        try {
            $DB->query("INSERT INTO merchandiser_questions (user_id, product_sku, question, created_at, status) VALUES (?, ?, ?, NOW(), 'pending')", 
                       [$user_id, $product_sku, $question]);
            
            echo 'success';
        } catch (Exception $e) {
            error_log("Question submission error: " . $e->getMessage());
            echo 'error';
        }
    } else {
        echo 'empty_question';
    }
} else {
    echo 'invalid_request';
}
?>