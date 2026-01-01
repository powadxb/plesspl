<?php
// UserManager Class (user_manager.php)
class UserManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createUser($userData) {
        try {
            $this->db->beginTransaction();

            // Check if username or email already exists
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM users
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$userData['username'], $userData['email']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Username or email already exists');
            }

            // Create new user
            $stmt = $this->db->prepare("
                INSERT INTO users
                (username, password, email, role, location_id)
                VALUES (?, ?, ?, ?, ?)
            ");

            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

            $stmt->execute([
                $userData['username'],
                $hashedPassword,
                $userData['email'],
                $userData['role'],
                $userData['location_id']
            ]);

            $this->db->commit();
            return true;
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("Create user error: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateUser($userId, $userData) {
        try {
            $this->db->beginTransaction();

            $updates = [];
            $params = [];

            // Build dynamic update query
            if (isset($userData['email'])) {
                $updates[] = "email = ?";
                $params[] = $userData['email'];
            }
            if (isset($userData['role'])) {
                $updates[] = "role = ?";
                $params[] = $userData['role'];
            }
            if (isset($userData['location_id'])) {
                $updates[] = "location_id = ?";
                $params[] = $userData['location_id'];
            }
            if (!empty($userData['password'])) {
                $updates[] = "password = ?";
                $params[] = password_hash($userData['password'], PASSWORD_DEFAULT);
            }

            if (!empty($updates)) {
                $params[] = $userId;
                $stmt = $this->db->prepare("
                    UPDATE users
                    SET " . implode(", ", $updates) . "
                    WHERE id = ?
                ");
                $stmt->execute($params);
            }

            $this->db->commit();
            return true;
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("Update user error: " . $e->getMessage());
            throw $e;
        }
    }

    public function deactivateUser($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users
                SET active = 0
                WHERE id = ?
            ");
            return $stmt->execute([$userId]);
        } catch(PDOException $e) {
            error_log("Deactivate user error: " . $e->getMessage());
            return false;
        }
    }

    public function getUsersByLocation($locationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*,
                       COUNT(DISTINCT t.id) as total_tickets,
                       COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tickets,
                       MAX(u.last_login) as last_login
                FROM users u
                LEFT JOIN repair_tickets t ON u.id = t.assigned_to
                WHERE u.location_id = ?
                GROUP BY u.id
                ORDER BY u.username
            ");
            $stmt->execute([$locationId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get users error: " . $e->getMessage());
            return [];
        }
    }

    public function getUserDetails($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, l.name as location_name
                FROM users u
                JOIN locations l ON u.location_id = l.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get user details error: " . $e->getMessage());
            return null;
        }
    }

    public function searchUsers($term) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, l.name as location_name
                FROM users u
                JOIN locations l ON u.location_id = l.id
                WHERE u.username LIKE ?
                OR u.email LIKE ?
                ORDER BY u.username
            ");
            $searchTerm = "%$term%";
            $stmt->execute([$searchTerm, $searchTerm]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Search users error: " . $e->getMessage());
            return [];
        }
    }
}
