<?php
// API Authentication and Response Handler (api/core/ApiAuth.php)
class ApiAuth {
    private $db;
    private $headers;

    public function __construct($db) {
        $this->db = $db;
        $this->headers = getallheaders();
    }

    public function authenticateRequest() {
        if (!isset($this->headers['Authorization'])) {
            throw new Exception('No authentication token provided', 401);
        }

        $token = str_replace('Bearer ', '', $this->headers['Authorization']);

        $stmt = $this->db->prepare("
            SELECT u.* FROM api_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token = ? AND t.expires_at > NOW() AND t.active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('Invalid or expired token', 401);
        }

        return $user;
    }

    public function generateToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $this->db->prepare("
            INSERT INTO api_tokens (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $token, $expiresAt]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt
        ];
    }
}
