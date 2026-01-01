<?php
// index.php
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth(getDB());
$error = '';

// Check if user is already logged in
if ($auth->isAuthenticated()) {
    header('Location: dashboard.php');
    exit();
}

// Handle login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        if ($auth->login($username, $password)) {
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    } catch (Exception $e) {
        $error = 'Login failed. Please try again.';
        error_log("Login error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Shop Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-card {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h1>Repair Shop</h1>
                <p class="text-muted">Management System</p>
            </div>

            <div class="login-card">
                <?php if ($error): ?>
                    <div class="error-message"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text"
                               class="form-control"
                               id="username"
                               name="username"
                               required
                               autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">
                        Contact your administrator if you need access.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
