<?php
require 'bootstrap.php';


if(isset($_POST['password']) && !empty($_POST['password'])){
  $password = password_encrypt($_POST['password']) ;
  $response = $DB->query(" UPDATE users SET username=?, password=?, email=?, admin=?, first_name=?, last_name=? WHERE id=?", [$_POST['username'] , $password, $_POST['email'], $_POST['admin'], $_POST['first_name'], $_POST['last_name'] , $_POST['id']]);
}else{
  $response = $DB->query(" UPDATE users SET username=?, email=?, admin=?, first_name=?, last_name=? WHERE id=?", [$_POST['username'] , $_POST['email'], $_POST['admin'], $_POST['first_name'], $_POST['last_name'] , $_POST['id']]);
}

if($response>0) echo "updated";