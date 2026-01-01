<?php
/**
 * Database Migration: Updated Trade-In System
 * Adds support for new ID types and ensures proper schema
 */

require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

if($user_details['admin'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

try {
    $migrations = [];
    
    // Check if trade_in_items table exists, create if not
    $table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_items'
    ")[0]['count'];

    if (!$table_exists) {
        $DB->query("
            CREATE TABLE `trade_in_items` (
              `id` int NOT NULL AUTO_INCREMENT,
              `customer_id` int DEFAULT NULL,
              `customer_name` varchar(255) NOT NULL,
              `customer_phone` varchar(50) DEFAULT NULL,
              `customer_email` varchar(255) DEFAULT NULL,
              `location` varchar(50) NOT NULL,
              `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
              `total_value` decimal(10,2) DEFAULT NULL,
              `payment_method` enum('cash','bank_transfer','cash_bank') DEFAULT 'cash',
              `cash_amount` decimal(10,2) DEFAULT 0.00,
              `bank_amount` decimal(10,2) DEFAULT 0.00,
              `bank_account_name` varchar(255) DEFAULT NULL,
              `bank_account_number` varchar(50) DEFAULT NULL,
              `bank_sort_code` varchar(20) DEFAULT NULL,
              `bank_reference` varchar(255) DEFAULT NULL,
              `compliance_notes` text,
              `created_by` int NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `completed_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_customer_id` (`customer_id`),
              KEY `idx_location` (`location`),
              KEY `idx_status` (`status`),
              KEY `idx_created_by` (`created_by`),
              KEY `idx_created_at` (`created_at`),
              CONSTRAINT `fk_trade_in_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $migrations[] = 'Created trade_in_items table';
    }

    // Check trade_in_items_details
    $details_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_items_details'
    ")[0]['count'];

    if (!$details_exists) {
        $DB->query("
            CREATE TABLE `trade_in_items_details` (
              `id` int NOT NULL AUTO_INCREMENT,
              `trade_in_id` int NOT NULL,
              `item_name` varchar(255) NOT NULL,
              `category` varchar(255) DEFAULT NULL,
              `serial_number` varchar(100) DEFAULT NULL,
              `condition` enum('excellent','good','fair','poor') NOT NULL DEFAULT 'good',
              `price_paid` decimal(10,2) NOT NULL,
              `notes` text,
              `preprinted_code` varchar(20) NULL COMMENT 'Preprinted barcode code (DSH format)',
              `tracking_code` varchar(20) NULL COMMENT 'Generated tracking code (SH format)',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_preprinted_code` (`preprinted_code`),
              UNIQUE KEY `unique_tracking_code` (`tracking_code`),
              KEY `idx_trade_in_id` (`trade_in_id`),
              KEY `idx_preprinted_code` (`preprinted_code`),
              KEY `idx_tracking_code` (`tracking_code`),
              CONSTRAINT `fk_trade_in_item_trade_in` FOREIGN KEY (`trade_in_id`) REFERENCES `trade_in_items` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $migrations[] = 'Created trade_in_items_details table';
    }

    // Check trade_in_item_photos
    $photos_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_item_photos'
    ")[0]['count'];

    if (!$photos_exists) {
        $DB->query("
            CREATE TABLE `trade_in_item_photos` (
              `id` int NOT NULL AUTO_INCREMENT,
              `trade_in_item_id` int NOT NULL,
              `file_path` varchar(255) NOT NULL,
              `uploaded_by` int NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_trade_in_item_id` (`trade_in_item_id`),
              KEY `idx_uploaded_by` (`uploaded_by`),
              CONSTRAINT `fk_trade_in_photo_item` FOREIGN KEY (`trade_in_item_id`) REFERENCES `trade_in_items_details` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_trade_in_photo_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $migrations[] = 'Created trade_in_item_photos table';
    }

    // Check trade_in_id_photos
    $id_photos_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_id_photos'
    ")[0]['count'];

    if (!$id_photos_exists) {
        $DB->query("
            CREATE TABLE `trade_in_id_photos` (
              `id` int NOT NULL AUTO_INCREMENT,
              `trade_in_id` int NOT NULL,
              `file_path` varchar(255) NOT NULL,
              `document_type` varchar(50) NOT NULL,
              `document_number` varchar(100) DEFAULT NULL,
              `uploaded_by` int NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_trade_in_id` (`trade_in_id`),
              KEY `idx_uploaded_by` (`uploaded_by`),
              CONSTRAINT `fk_trade_in_id_photo_trade_in` FOREIGN KEY (`trade_in_id`) REFERENCES `trade_in_items` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_trade_in_id_photo_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $migrations[] = 'Created trade_in_id_photos table';
    }

    // Check trade_in_signatures
    $signatures_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_signatures'
    ")[0]['count'];

    if (!$signatures_exists) {
        $DB->query("
            CREATE TABLE `trade_in_signatures` (
              `id` int NOT NULL AUTO_INCREMENT,
              `trade_in_id` int NOT NULL,
              `file_path` varchar(255) NOT NULL,
              `uploaded_by` int NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_trade_in_id` (`trade_in_id`),
              KEY `idx_uploaded_by` (`uploaded_by`),
              CONSTRAINT `fk_trade_in_signature_trade_in` FOREIGN KEY (`trade_in_id`) REFERENCES `trade_in_items` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_trade_in_signature_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $migrations[] = 'Created trade_in_signatures table';
    }

    // Create upload directories
    $upload_dirs = [
        __DIR__ . '/../uploads/trade_in_items/',
        __DIR__ . '/../uploads/trade_in_ids/',
        __DIR__ . '/../uploads/trade_in_signatures/'
    ];

    foreach ($upload_dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $migrations[] = 'Created directory: ' . basename($dir);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database migration completed successfully',
        'migrations' => $migrations
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration error: ' . $e->getMessage()
    ]);
}
