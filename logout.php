<?php
ob_start();
session_start();
setcookie('dins_user_id', null, -1, "/");
$_SESSION['dins_user_id'] = null;

header("Location: login.php");
exit();