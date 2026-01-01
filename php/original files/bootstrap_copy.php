<?php
ob_start();
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../libs/vendor/autoload.php';
require __DIR__.'/fn.php';

define('DBHost', 'localhost');
define('DBPort', 3306);
define('DBName', 'plesspl');
define('DBUser', 'plesspluser');
define('DBPassword', '66golA28mmvelAXBeQlH');

$DB = new Db(DBHost, DBPort, DBName, DBUser, DBPassword);


if(isset($_COOKIE['dins_user_id'])) $user_id = $_COOKIE['dins_user_id'];
elseif(isset($_SESSION['dins_user_id'])) $user_id = $_SESSION['dins_user_id'];