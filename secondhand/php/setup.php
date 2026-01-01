<?php
/**
 * Second-hand Inventory System Setup Script
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
    // Check if second_hand_items table exists
    $table_exists = $DB->query("
        SELECT COUNT(*) as count 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'second_hand_items'
    ")[0]['count'];

    if (!$table_exists) {
        // Create the second_hand_items table
        $create_table_sql = "
            CREATE TABLE `second_hand_items` (
              `id` int NOT NULL AUTO_INCREMENT,
              `preprinted_code` VARCHAR(20) NULL COMMENT 'Preprinted barcode code (DSH format)',
              `tracking_code` VARCHAR(20) NULL COMMENT 'Generated tracking code (SH format)',
              `item_name` varchar(255) NOT NULL,
              `condition` enum('excellent','good','fair','poor') NOT NULL DEFAULT 'good',
              `item_source` ENUM('trade_in', 'donation', 'abandoned', 'parts_dismantle', 'purchase', 'other') NULL DEFAULT 'other' COMMENT 'Source of the item',
              `serial_number` varchar(100) DEFAULT NULL,
              `status` enum('in_stock','sold') NOT NULL DEFAULT 'in_stock',
              `purchase_price` decimal(10,2) DEFAULT NULL,
              `estimated_sale_price` decimal(10,2) DEFAULT NULL COMMENT 'Estimated sale price for the item',
              `estimated_value` decimal(10,2) DEFAULT NULL COMMENT 'Estimated value at time of acquisition',
              `customer_id` varchar(50) DEFAULT NULL,
              `customer_name` VARCHAR(255) NULL COMMENT 'Name of customer for trade-ins/donations',
              `customer_contact` VARCHAR(255) NULL COMMENT 'Customer contact information',
              `category` VARCHAR(255) NULL COMMENT 'Category of the item',
              `detailed_condition` TEXT NULL COMMENT 'Detailed condition notes',
              `location` VARCHAR(50) NULL COMMENT 'Current location of the item',
              `acquisition_date` DATE NULL COMMENT 'Date when item was acquired',
              `warranty_info` TEXT NULL COMMENT 'Warranty or guarantee information',
              `supplier_info` VARCHAR(255) NULL COMMENT 'Supplier information if purchased',
              `model_number` VARCHAR(255) NULL COMMENT 'Model number of the item',
              `brand` VARCHAR(255) NULL COMMENT 'Brand of the item',
              `purchase_document` VARCHAR(255) NULL COMMENT 'Purchase document reference',
              `status_detail` VARCHAR(100) NULL COMMENT 'Detailed status (for parts, repair needed, etc.)',
              `notes` text,
              `trade_in_reference` VARCHAR(255) NULL COMMENT 'Reference to trade-in if applicable',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_preprinted_code` (`preprinted_code`),
              UNIQUE KEY `unique_tracking_code` (`tracking_code`),
              KEY `idx_preprinted_code` (`preprinted_code`),
              KEY `idx_tracking_code` (`tracking_code`),
              KEY `idx_item_source` (`item_source`),
              KEY `idx_status` (`status`),
              KEY `idx_location` (`location`),
              KEY `idx_trade_in_reference` (`trade_in_reference`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Second-hand inventory tracking system';
        ";
        
        $DB->query($create_table_sql);
        echo json_encode(['success' => true, 'message' => 'Second-hand items table created successfully']);
        exit();
    }

    // Check if the required columns exist in second_hand_items
    $columns = $DB->query("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'second_hand_items'
    ");
    
    $existing_columns = [];
    foreach ($columns as $col) {
        $existing_columns[] = $col['COLUMN_NAME'];
    }

    // Define required columns that might be missing
    $required_columns = [
        'preprinted_code' => "ALTER TABLE `second_hand_items` ADD COLUMN `preprinted_code` VARCHAR(20) NULL COMMENT 'Preprinted barcode code (DSH format)' AFTER `id`",
        'tracking_code' => "ALTER TABLE `second_hand_items` ADD COLUMN `tracking_code` VARCHAR(20) NULL COMMENT 'Generated tracking code (SH format)' AFTER `preprinted_code`",
        'item_source' => "ALTER TABLE `second_hand_items` ADD COLUMN `item_source` ENUM('trade_in', 'donation', 'abandoned', 'parts_dismantle', 'purchase', 'other') NULL DEFAULT 'other' COMMENT 'Source of the item' AFTER `tracking_code`",
        'estimated_sale_price' => "ALTER TABLE `second_hand_items` ADD COLUMN `estimated_sale_price` DECIMAL(10,2) NULL COMMENT 'Estimated sale price for the item' AFTER `purchase_price`",
        'estimated_value' => "ALTER TABLE `second_hand_items` ADD COLUMN `estimated_value` DECIMAL(10,2) NULL COMMENT 'Estimated value at time of acquisition' AFTER `estimated_sale_price`",
        'customer_name' => "ALTER TABLE `second_hand_items` ADD COLUMN `customer_name` VARCHAR(255) NULL COMMENT 'Name of customer for trade-ins/donations' AFTER `customer_id`",
        'customer_contact' => "ALTER TABLE `second_hand_items` ADD COLUMN `customer_contact` VARCHAR(255) NULL COMMENT 'Customer contact information' AFTER `customer_name`",
        'category' => "ALTER TABLE `second_hand_items` ADD COLUMN `category` VARCHAR(255) NULL COMMENT 'Category of the item' AFTER `customer_contact`",
        'detailed_condition' => "ALTER TABLE `second_hand_items` ADD COLUMN `detailed_condition` TEXT NULL COMMENT 'Detailed condition notes' AFTER `category`",
        'location' => "ALTER TABLE `second_hand_items` ADD COLUMN `location` VARCHAR(50) NULL COMMENT 'Current location of the item' AFTER `detailed_condition`",
        'acquisition_date' => "ALTER TABLE `second_hand_items` ADD COLUMN `acquisition_date` DATE NULL COMMENT 'Date when item was acquired' AFTER `location`",
        'warranty_info' => "ALTER TABLE `second_hand_items` ADD COLUMN `warranty_info` TEXT NULL COMMENT 'Warranty or guarantee information' AFTER `acquisition_date`",
        'supplier_info' => "ALTER TABLE `second_hand_items` ADD COLUMN `supplier_info` VARCHAR(255) NULL COMMENT 'Supplier information if purchased' AFTER `warranty_info`",
        'model_number' => "ALTER TABLE `second_hand_items` ADD COLUMN `model_number` VARCHAR(255) NULL COMMENT 'Model number of the item' AFTER `supplier_info`",
        'brand' => "ALTER TABLE `second_hand_items` ADD COLUMN `brand` VARCHAR(255) NULL COMMENT 'Brand of the item' AFTER `model_number`",
        'purchase_document' => "ALTER TABLE `second_hand_items` ADD COLUMN `purchase_document` VARCHAR(255) NULL COMMENT 'Purchase document reference' AFTER `brand`",
        'status_detail' => "ALTER TABLE `second_hand_items` ADD COLUMN `status_detail` VARCHAR(100) NULL COMMENT 'Detailed status (for parts, repair needed, etc.)' AFTER `purchase_document`",
        'trade_in_reference' => "ALTER TABLE `second_hand_items` ADD COLUMN `trade_in_reference` VARCHAR(255) NULL COMMENT 'Reference to trade-in if applicable' AFTER `notes`"
    ];

    $updated_columns = [];
    foreach ($required_columns as $col_name => $sql) {
        if (!in_array($col_name, $existing_columns)) {
            $DB->query($sql);
            $updated_columns[] = $col_name;
        }
    }

    // Check if the required indexes exist
    $indexes = $DB->query("
        SELECT INDEX_NAME 
        FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'second_hand_items'
    ");
    
    $existing_indexes = [];
    foreach ($indexes as $idx) {
        $existing_indexes[] = $idx['INDEX_NAME'];
    }

    // Define required indexes
    $required_indexes = [
        'unique_preprinted_code' => "ALTER TABLE `second_hand_items` ADD UNIQUE KEY `unique_preprinted_code` (`preprinted_code`)",
        'unique_tracking_code' => "ALTER TABLE `second_hand_items` ADD UNIQUE KEY `unique_tracking_code` (`tracking_code`)",
        'idx_preprinted_code' => "ALTER TABLE `second_hand_items` ADD KEY `idx_preprinted_code` (`preprinted_code`)",
        'idx_tracking_code' => "ALTER TABLE `second_hand_items` ADD KEY `idx_tracking_code` (`tracking_code`)",
        'idx_item_source' => "ALTER TABLE `second_hand_items` ADD KEY `idx_item_source` (`item_source`)",
        'idx_location' => "ALTER TABLE `second_hand_items` ADD KEY `idx_location` (`location`)",
        'idx_trade_in_reference' => "ALTER TABLE `second_hand_items` ADD KEY `idx_trade_in_reference` (`trade_in_reference`)"
    ];

    $updated_indexes = [];
    foreach ($required_indexes as $idx_name => $sql) {
        if (!in_array($idx_name, $existing_indexes)) {
            $DB->query($sql);
            $updated_indexes[] = $idx_name;
        }
    }

    // Check if trade_in_items table exists (for the original trade-in system)
    $trade_in_table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'trade_in_items'
    ")[0]['count'];

    if (!$trade_in_table_exists) {
        // Create the original trade_in_items table
        $create_trade_in_table_sql = "
            CREATE TABLE `trade_in_items` (
              `id` int NOT NULL AUTO_INCREMENT,
              `preprinted_code` VARCHAR(20) NULL COMMENT 'Preprinted barcode code (DSH format)',
              `tracking_code` VARCHAR(20) NULL COMMENT 'Generated tracking code (SH format)',
              `item_name` varchar(255) NOT NULL,
              `customer_name` varchar(255) NULL,
              `customer_phone` varchar(50) DEFAULT NULL,
              `customer_email` varchar(255) DEFAULT NULL,
              `customer_address` text,
              `category` varchar(255) DEFAULT NULL,
              `brand` varchar(255) DEFAULT NULL,
              `model_number` varchar(255) DEFAULT NULL,
              `serial_number` varchar(100) DEFAULT NULL,
              `condition` enum('excellent','good','fair','poor') NOT NULL DEFAULT 'good',
              `detailed_condition` text,
              `offered_price` decimal(10,2) DEFAULT NULL,
              `location` varchar(50) NOT NULL,
              `status` enum('pending','accepted','rejected','processed') NOT NULL DEFAULT 'pending',
              `collection_date` date DEFAULT NULL,
              `notes` text,
              `id_document_type` varchar(50) DEFAULT NULL,
              `id_document_number` varchar(100) DEFAULT NULL,
              `compliance_notes` text,
              `compliance_status` enum('pending','verified','completed','rejected') DEFAULT 'pending',
              `created_by` int NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_location` (`location`),
              KEY `idx_status` (`status`),
              KEY `idx_created_by` (`created_by`),
              KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $DB->query($create_trade_in_table_sql);
    }

    // Update existing trade_in_items table with any missing columns
    $trade_in_columns = $DB->query("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'trade_in_items'
    ");

    $trade_in_existing_columns = [];
    foreach ($trade_in_columns as $col) {
        $trade_in_existing_columns[] = $col['COLUMN_NAME'];
    }

    // Define required columns for trade_in_items - add them without specifying order to avoid dependency issues
    $trade_in_required_columns = [
        'preprinted_code' => "ALTER TABLE `trade_in_items` ADD COLUMN `preprinted_code` VARCHAR(20) NULL COMMENT 'Preprinted barcode code (DSH format)'",
        'tracking_code' => "ALTER TABLE `trade_in_items` ADD COLUMN `tracking_code` VARCHAR(20) NULL COMMENT 'Generated tracking code (SH format)'",
        'customer_name' => "ALTER TABLE `trade_in_items` ADD COLUMN `customer_name` VARCHAR(255) NULL", // Changed to NULL to avoid issues
        'customer_phone' => "ALTER TABLE `trade_in_items` ADD COLUMN `customer_phone` VARCHAR(50) NULL",
        'customer_email' => "ALTER TABLE `trade_in_items` ADD COLUMN `customer_email` VARCHAR(255) NULL",
        'customer_address' => "ALTER TABLE `trade_in_items` ADD COLUMN `customer_address` TEXT NULL",
        'category' => "ALTER TABLE `trade_in_items` ADD COLUMN `category` VARCHAR(255) NULL",
        'brand' => "ALTER TABLE `trade_in_items` ADD COLUMN `brand` VARCHAR(255) NULL",
        'model_number' => "ALTER TABLE `trade_in_items` ADD COLUMN `model_number` VARCHAR(255) NULL",
        'serial_number' => "ALTER TABLE `trade_in_items` ADD COLUMN `serial_number` VARCHAR(100) NULL",
        'detailed_condition' => "ALTER TABLE `trade_in_items` ADD COLUMN `detailed_condition` TEXT NULL",
        'offered_price' => "ALTER TABLE `trade_in_items` ADD COLUMN `offered_price` DECIMAL(10,2) NULL",
        'collection_date' => "ALTER TABLE `trade_in_items` ADD COLUMN `collection_date` DATE NULL",
        'id_document_type' => "ALTER TABLE `trade_in_items` ADD COLUMN `id_document_type` VARCHAR(50) NULL",
        'id_document_number' => "ALTER TABLE `trade_in_items` ADD COLUMN `id_document_number` VARCHAR(100) NULL",
        'compliance_notes' => "ALTER TABLE `trade_in_items` ADD COLUMN `compliance_notes` TEXT NULL",
        'compliance_status' => "ALTER TABLE `trade_in_items` ADD COLUMN `compliance_status` ENUM('pending', 'verified', 'completed', 'rejected') DEFAULT 'pending'"
    ];

    $trade_in_updated_columns = [];
    foreach ($trade_in_required_columns as $col_name => $sql) {
        if (!in_array($col_name, $trade_in_existing_columns)) {
            $DB->query($sql);
            $trade_in_updated_columns[] = $col_name;
        }
    }

    // Create audit log table if it doesn't exist
    $audit_table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'second_hand_audit_log'
    ")[0]['count'];

    if (!$audit_table_exists) {
        $DB->query("
            CREATE TABLE `second_hand_audit_log` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `item_id` int NOT NULL,
                `action` varchar(50) NOT NULL COMMENT 'Type of action (create, update, delete, import, etc.)',
                `action_details` text COMMENT 'Details about the action including old/new values',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_item_id` (`item_id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit log for second-hand inventory changes';
        ");
    }

    // Create photos table if it doesn't exist
    $photos_table_exists = $DB->query("
        SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'second_hand_item_photos'
    ")[0]['count'];

    if (!$photos_table_exists) {
        $DB->query("
            CREATE TABLE `second_hand_item_photos` (
                `id` int NOT NULL AUTO_INCREMENT,
                `item_id` int NOT NULL,
                `file_path` varchar(255) NOT NULL,
                `file_type` enum('item_photo', 'id_document', 'other_document') DEFAULT 'item_photo',
                `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `uploaded_by` int NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_item_id` (`item_id`),
                KEY `idx_uploaded_by` (`uploaded_by`),
                CONSTRAINT `fk_second_hand_photo_item` FOREIGN KEY (`item_id`) REFERENCES `second_hand_items` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_second_hand_photo_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/second_hand_items/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database schema updated successfully',
        'second_hand_columns_added' => $updated_columns,
        'second_hand_indexes_added' => $updated_indexes,
        'trade_in_columns_added' => $trade_in_updated_columns,
        'audit_table_created' => !$audit_table_exists,
        'photos_table_created' => !$photos_table_exists,
        'uploads_directory_created' => is_dir($upload_dir)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating database schema: ' . $e->getMessage()
    ]);
}
?>