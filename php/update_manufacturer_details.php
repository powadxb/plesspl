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
  $manufacturer_id = isset($_POST['manufacturer_id']) ? (int)$_POST['manufacturer_id'] : 0;
  $manufacturer_name = isset($_POST['manufacturer_name']) ? trim($_POST['manufacturer_name']) : '';
  
  if($manufacturer_id <= 0 || empty($manufacturer_name)){
    exit('error');
  }
  
  // Check if another manufacturer with the same name exists (excluding current one)
  $exists = $DB->query(
    "SELECT COUNT(*) as count FROM master_pless_manufacturers WHERE manufacturer_name = ? AND manufacturer_id != ?",
    [$manufacturer_name, $manufacturer_id]
  );
  
  if($exists[0]['count'] > 0){
    exit('exists');
  }
  
  // Update manufacturer
  $result = $DB->query(
    "UPDATE master_pless_manufacturers SET manufacturer_name = ? WHERE manufacturer_id = ?",
    [$manufacturer_name, $manufacturer_id]
  );
  
  if($result !== false){
    echo 'updated';
  }else{
    echo 'error';
  }
}
?>
