<?php
// Authentication class (auth.php)
class Auth {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['location_id'] = $user['location_id'];

                // Update last login
                $stmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);

                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    public function hasPermission($requiredRole) {
        $allowedRoles = [
            'admin' => ['admin'],
            'manager' => ['admin', 'manager'],
            'technician' => ['admin', 'manager', 'technician'],
            'front_desk' => ['admin', 'manager', 'front_desk']
        ];

        return in_array($_SESSION['role'], $allowedRoles[$requiredRole]);
    }

    public function logout() {
        session_destroy();
    }
}
