<?php
require 'bootstrap.php';

$response = $DB->query(" INSERT INTO master_categories (id, pless_main_category, pos_category) VALUES (?, ?, ?) ", [$_POST['cat_id'], $_POST['pless_main_category'], $_POST['pos_category']]);
if($response>0) echo 'added';