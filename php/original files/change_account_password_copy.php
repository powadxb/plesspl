<?php
require 'bootstrap.php';

// check if the entered current password matching the account password
$user_details = $DB->query(" SELECT * FROM users WHERE id=?", [$user_id])[0];
$hash= crypt($_POST['current_password'] , $user_details['password']) ;
if($hash === $user_details['password']) {
  $password = password_encrypt($_POST['new_password']) ;
  $response = $DB->query(" UPDATE users SET password=? WHERE id=?", [$password, $user_id]);
  if($response>0) echo 'updated';
}else {
  echo 'wrong_password';
}