<?php
/**
 * Enhanced Second-hand Inventory System Setup Script
 * This script applies all necessary database changes for the enhanced system
 */

require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check admin permissions
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
if($user_details['admin'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

try {
    // Check if trade_in_items table exists
    $table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_items'
    ")[0]['count'];

    if (!$table_exists) {
        // Create the trade_in_items table
        $create_table_sql = "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $DB->query($create_table_sql);
    }

    // Check if trade_in_items_details table exists
    $details_table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_items_details'
    ")[0]['count'];

    if (!$details_table_exists) {
        // Create the trade_in_items_details table
        $create_details_table_sql = "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $DB->query($create_details_table_sql);
    }

    // Check if trade_in_item_photos table exists
    $photos_table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_item_photos'
    ")[0]['count'];

    if (!$photos_table_exists) {
        // Create the trade_in_item_photos table
        $create_photos_table_sql = "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $DB->query($create_photos_table_sql);
    }

    // Check if trade_in_id_photos table exists
    $id_photos_table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_id_photos'
    ")[0]['count'];

    if (!$id_photos_table_exists) {
        // Create the trade_in_id_photos table
        $create_id_photos_table_sql = "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $DB->query($create_id_photos_table_sql);
    }

    // Check if trade_in_signatures table exists
    $signatures_table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_signatures'
    ")[0]['count'];

    if (!$signatures_table_exists) {
        // Create the trade_in_signatures table
        $create_signatures_table_sql = "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $DB->query($create_signatures_table_sql);
    }

    // Create uploads directories if they don't exist
    $upload_dirs = [
        __DIR__ . '/../uploads/trade_in_items/',
        __DIR__ . '/../uploads/trade_in_ids/',
        __DIR__ . '/../uploads/trade_in_signatures/',
        __DIR__ . '/../uploads/second_hand_items/'
    ];

    foreach ($upload_dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Trade-in database schema updated successfully',
        'uploads_directories_created' => true
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating database schema: ' . $e->getMessage()
    ]);
}
?>