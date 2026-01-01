<?php
require 'bootstrap.php';

$response = $DB->query(" UPDATE master_categories SET id=?, pless_main_category=?, pos_category=? WHERE id=?" , [$_POST['cat_id'], $_POST['pless_main_category'], $_POST['pos_category'], $_POST['id']]);
if($response>0) echo 'updated';