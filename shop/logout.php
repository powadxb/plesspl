<?php
// logout.php
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth(getDB());

// Destroy the session
session_start();
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();