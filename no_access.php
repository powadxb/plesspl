<?php
session_start();
$page_title = 'Access Denied';
require 'php/bootstrap.php';

// Ensure session is active, redirect to login if not
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

require 'assets/header.php';
require 'assets/navbar.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-lock fa-4x text-muted"></i>
                    </div>
                    <h2 class="card-title mb-4">Access Restricted</h2>
                    <p class="card-text lead mb-4">
                        Sorry, you don't currently have access to the requested page. 
                        If you believe this is an error, please contact your system administrator.
                    </p>
                    <div class="mt-4">
                        <button onclick="history.back()" class="btn btn-primary me-2">
                            <i class="fas fa-arrow-left"></i> Go Back
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Go to Homepage
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($_SERVER['HTTP_REFERER'])): ?>
            <div class="text-center mt-3">
                <small class="text-muted">
                    Attempted to access: <?php echo htmlspecialchars($_SERVER['HTTP_REFERER']); ?>
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'assets/footer.php'; ?>