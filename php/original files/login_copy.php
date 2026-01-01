<?php
require 'bootstrap.php';
$response = $DB->query("SELECT id, username , password FROM users WHERE username=?", [$_POST['username']]);
if(!empty($response)){
  $user_details = $response[0];
  $hash= crypt($_POST['password'] , $user_details['password']) ;
  if($hash === $user_details['password']) {
    if(isset($_POST['remember'])){
      setcookie('dins_user_id', $user_details['id'], time() + (86400 * 30), "/"); // 86400 = 1 day
    }
    $_SESSION['dins_user_id'] = $user_details['id'];
    echo 'valid_login';
  }else {
    echo 'invalid_login';
  }
}else{
  echo 'invalid_login';
}