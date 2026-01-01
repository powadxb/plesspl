<?php
session_start();
require '../php/bootstrap.php';

// Simple authentication check
if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Second Hand Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
        }
        .btn-lg {
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-lg:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgba(0,0,0,0.2);">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-arrow-left"></i> Back to Main Menu
            </a>
            <span class="navbar-text text-white">
                <i class="fas fa-user-circle"></i> <?=htmlspecialchars($user_details['username'])?>
            </span>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h2 class="mb-0">
                            <i class="fas fa-recycle"></i> Second Hand Inventory
                        </h2>
                        <small>Management System</small>
                    </div>
                    <div class="card-body p-5">
                        <p class="lead text-center mb-4">Choose a module to get started</p>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <a href="secondhand.php" class="btn btn-lg btn-primary w-100 py-4 text-white text-decoration-none">
                                    <i class="fas fa-box fa-3x d-block mb-3"></i>
                                    <h5 class="mb-2">Second Hand Items</h5>
                                    <small class="d-block opacity-75">Browse & manage inventory</small>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="trade_in_management.php" class="btn btn-lg btn-success w-100 py-4 text-white text-decoration-none">
                                    <i class="fas fa-exchange-alt fa-3x d-block mb-3"></i>
                                    <h5 class="mb-2">Trade-Ins</h5>
                                    <small class="d-block opacity-75">Manage customer trade-ins</small>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-center bg-light">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Last login: <?=date('d/m/Y H:i')?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
