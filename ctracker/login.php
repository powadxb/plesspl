<?php
// login.php
session_start();
require_once 'config.php';

// If already logged in, redirect to index
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=cashtracker", DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("SELECT id, username, password, role, branch_id, active 
                               FROM users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($_POST['password'], $user['password'])) {
            if (!$user['active']) {
                $error = "Account is deactivated. Please contact admin.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                
                header('Location: index.php');
                exit();
            }
        } else {
            $error = "Invalid username or password";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cash Management System</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            font-size: 14px;
        }
        .container { 
            max-width: 300px; 
            margin: 50px auto; 
        }
        .form-group { 
            margin-bottom: 15px;
        }
        label { 
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], input[type="password"] { 
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        button { 
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            width: 100%;
        }
        .error { 
            color: red; 
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" name="username" id="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required>
            </div>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>