<?php
require 'bootstrap.php';

$response = $DB->query(" UPDATE users SET enabled=? WHERE id=?", [$_POST['status'] , $_POST['id']]);
if($response>0){
  echo "updated";
}