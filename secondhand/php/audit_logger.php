<?php
/**
 * Audit Logging for Second-Hand Inventory System
 * Logs all changes to items for compliance and tracking purposes
 */

class SecondHandAuditLogger {
    private $DB;
    
    public function __construct($database) {
        $this->DB = $database;
    }
    
    /**
     * Log an action performed on a second-hand item
     */
    public function logAction($user_id, $item_id, $action, $old_values = null, $new_values = null) {
        $action_details = json_encode([
            'old_values' => $old_values,
            'new_values' => $new_values
        ]);
        
        $this->DB->query(
            "INSERT INTO second_hand_audit_log 
            (user_id, item_id, action, action_details, created_at) 
            VALUES (?, ?, ?, ?, NOW())",
            [$user_id, $item_id, $action, $action_details]
        );
    }
    
    /**
     * Get audit log for a specific item
     */
    public function getItemLog($item_id, $limit = 50, $offset = 0) {
        return $this->DB->query(
            "SELECT sal.*, u.username as user_name
             FROM second_hand_audit_log sal
             LEFT JOIN users u ON sal.user_id = u.id
             WHERE sal.item_id = ?
             ORDER BY sal.created_at DESC
             LIMIT ? OFFSET ?",
            [$item_id, $limit, $offset]
        );
    }
    
    /**
     * Get audit log for a specific user
     */
    public function getUserLog($user_id, $limit = 50, $offset = 0) {
        return $this->DB->query(
            "SELECT sal.*, u.username as user_name, shi.item_name
             FROM second_hand_audit_log sal
             LEFT JOIN users u ON sal.user_id = u.id
             LEFT JOIN second_hand_items shi ON sal.item_id = shi.id
             WHERE sal.user_id = ?
             ORDER BY sal.created_at DESC
             LIMIT ? OFFSET ?",
            [$user_id, $limit, $offset]
        );
    }
}

/**
 * Create the audit log table if it doesn't exist
 */
function createAuditLogTable($DB) {
    $tableExists = $DB->query("
        SELECT COUNT(*) as count 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'second_hand_audit_log'
    ")[0]['count'];
    
    if (!$tableExists) {
        $DB->query("
            CREATE TABLE `second_hand_audit_log` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `item_id` int NOT NULL,
                `action` varchar(50) NOT NULL COMMENT 'Type of action (create, update, delete, import, etc.)',
                `action_details` json COMMENT 'Details about the action including old/new values',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_item_id` (`item_id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Audit log for second-hand inventory changes';
        ");
    }
}
?>