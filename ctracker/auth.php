<?php
// auth.php
session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($allowed_roles) {
    requireLogin();
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: unauthorized.php');
        exit();
    }
}

function isAdmin() {
    return $_SESSION['role'] === 'admin';
}

function isManager() {
    return $_SESSION['role'] === 'manager' || $_SESSION['role'] === 'admin';
}
?>