<?php
// index.php
require_once 'config.php';
require_once 'auth.php';

// Require login for all users
requireLogin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Management System</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            font-size: 14px;
        }
        .container { 
            max-width: 400px; 
            margin: 0 auto; 
        }
        h1 { 
            font-size: 20px;
            margin-bottom: 20px;
        }
        .menu-item {
            display: block;
            padding: 12px;
            margin-bottom: 10px;
            background-color: #f5f5f5;
            text-decoration: none;
            color: #333;
            border-radius: 3px;
        }
        .menu-item:hover {
            background-color: #e0e0e0;
        }
        .description {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .user-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 3px;
        }
        .logout {
            display: inline-block;
            padding: 6px 12px;
            background-color: #f44336;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 10px;
        }
        .logout:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="user-info">
            Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> 
            (<?php echo ucfirst($_SESSION['role']); ?>)
            <br>
            <a href="logout.php" class="logout">Logout</a>
        </div>

        <h1>Cash Management System</h1>
        
        <!-- Daily takings - available to all users -->
        <a href="daily.php" class="menu-item">
            Daily Takings Entry
            <div class="description">Record end of day cash, card payments, and other takings</div>
        </a>
        
        <?php if (isManager()): ?>
        <!-- Cash movements - managers and admins only -->
        <a href="movements.php" class="menu-item">
            Cash Movements
            <div class="description">Record banking, purchases, expenses, and wages</div>
        </a>
        
        <!-- Reports - managers and admins only -->
        <a href="report.php" class="menu-item">
            Cash Report
            <div class="description">View cash balance and recent transactions</div>
        </a>
        <?php endif; ?>
        
        <?php if (isAdmin()): ?>
        <!-- User management - admin only -->
        <a href="users.php" class="menu-item">
            User Management
            <div class="description">Manage user accounts and permissions</div>
        </a>
        <?php endif; ?>
    </div>
</body>
</html>