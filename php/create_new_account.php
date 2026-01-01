<?php
require 'bootstrap.php';

// check if username or email address already exist
$response = $DB->query(" SELECT username , email FROM users WHERE username=? OR email=?", [$_POST['username'], $_POST['email']]);
if(!empty($response)){
  $details = $response[0];
  $error = '';
  if($details['username']==$_POST['username']) $error .= ' Username ';
  
  if($details['email']==$_POST['email']) {
    if(!empty($error)) $error .= ' and ';
    $error .= ' email address ';
  } 
  if(!empty($error)) $error .= '_exist';
  echo $error;
  die();
}

$password = password_encrypt($_POST['password']) ;
$response = $DB->query(" INSERT INTO users ( username , password , email )  VALUES (?, ?, ?) ", [$_POST['username'], $password, $_POST['email']]);
if($response>0) echo 'added';