<?php
// unauthorized.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            text-align: center;
        }
        .container { 
            max-width: 400px; 
            margin: 50px auto; 
        }
        .back-link {
            margin-top: 20px;
            display: inline-block;
            text-decoration: none;
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Unauthorized Access</h1>
        <p>You don't have permission to access this page.</p>
        <a href="index.php" class="back-link">Return to Home</a>
    </div>
</body>
</html>