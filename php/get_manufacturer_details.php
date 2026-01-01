<?php
session_start();
require __DIR__.'/bootstrap.php';

if(!isset($_SESSION['dins_user_id'])){
  exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

// Check if user has permission
$manufacturer_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'manufacturers'", 
    [$user_id]
);

if(empty($manufacturer_access) || !$manufacturer_access[0]['has_access']){
  exit('unauthorized');
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  
  if($id <= 0){
    exit();
  }
  
  $record = $DB->query("SELECT * FROM master_pless_manufacturers WHERE manufacturer_id = ?", [$id]);
  
  if(empty($record)){
    exit();
  }
  
  $record = $record[0];
  
  $output = '<form method="POST" id="updateDetailsForm">';
  $output .= '<input type="hidden" name="manufacturer_id" value="'.htmlspecialchars($record['manufacturer_id']).'">';
  
  $output .= '<div class="form-group">';
  $output .= '<label for="manufacturerName">Manufacturer Name <span class="text-danger">*</span></label>';
  $output .= '<input type="text" name="manufacturer_name" id="manufacturerName" class="form-control requiredField" value="'.htmlspecialchars($record['manufacturer_name']).'">';
  $output .= '</div>';
  
  $output .= '<hr>';
  $output .= '<button class="btn btn-success d-block mx-auto">Update Manufacturer</button>';
  $output .= '</form>';
  
  echo $output;
}
?>
