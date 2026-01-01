<?php
// API endpoint for user activity (api/get_user_activity.php)
require_once '../config.php';
require_once '../auth.php';

header('Content-Type: application/json');

$auth = new Auth(getDB());
if (!$auth->isAuthenticated() || !$auth->hasPermission('admin')) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user ID']);
    exit();
}

try {
    $stmt = getDB()->prepare("
        SELECT
            timestamp,
            action,
            details
        FROM user_activity_log
        WHERE user_id = ?
        ORDER BY timestamp DESC
        LIMIT 100
    ");
    $stmt->execute([$_GET['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

// Add user activity logging table
$sql = "
CREATE TABLE user_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";
?>
