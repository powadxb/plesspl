<?php
require 'bootstrap.php';

$response = $DB->query(" UPDATE settings SET setting_value=? WHERE setting_key='table_lines'" , [$_POST['table_lines']]);
if($response>0) echo 'updated';