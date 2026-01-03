<?php
session_start();
require_once '../../php/bootstrap.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if(!isset($_SESSION['dins_user_id'])){
    header('Content-Type: application/json');
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check permissions - ONLY use control panel permissions
$permissions_file = __DIR__.'/../php/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

$can_manage = (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB));

if (!$can_manage) {
    header('Content-Type: application/json');
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Access denied']));
}

try {
    // Debug: Log all POST data
    error_log("Trade-in save POST data: " . print_r($_POST, true));
    error_log("Trade-in save FILES data: " . print_r(array_keys($_FILES), true));
    
    // Check if required tables exist
    $required_tables = ['trade_in_items', 'trade_in_items_details', 'trade_in_id_photos'];
    foreach($required_tables as $table) {
        $exists = $DB->query("
            SELECT COUNT(*) as count
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = ?
        ", [$table])[0]['count'] ?? 0;
        
        if(!$exists) {
            throw new Exception("Required table '$table' does not exist. Please run the migration script first.");
        }
    }
    
    // Parse customer data
    $customer_id = $_POST['customer_id'] ?? null;
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $customer_phone = empty($customer_phone) ? null : $customer_phone;
    $customer_email = $_POST['customer_email'] ?? '';
    $customer_email = empty($customer_email) ? null : $customer_email;
    $customer_address = $_POST['customer_address'] ?? '';
    $customer_address = empty($customer_address) ? null : $customer_address;
    $customer_postcode = $_POST['customer_postcode'] ?? '';
    $customer_postcode = empty($customer_postcode) ? null : $customer_postcode;
    $location = $_POST['location'] ?? $user_details['user_location'];
    
    // Validate
    if(empty($customer_name)) {
        throw new Exception('Customer name is required');
    }
    
    // If customer doesn't exist in repairs DB, create them
    if(empty($customer_id)) {
        try {
            $repairsDB = new PDO(
                "mysql:host=localhost;dbname=sitegroundrepairs;charset=utf8mb4",
                "root",
                ""
            );
            $repairsDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if customers table has the columns we need
            $columns = $repairsDB->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
            
            // Build insert based on available columns
            $insert_cols = ['name'];
            $insert_vals = [$customer_name];
            
            if(in_array('phone', $columns)) {
                $insert_cols[] = 'phone';
                $insert_vals[] = $customer_phone;
            }
            if(in_array('email', $columns)) {
                $insert_cols[] = 'email';
                $insert_vals[] = $customer_email;
            }
            if(in_array('address', $columns)) {
                $insert_cols[] = 'address';
                $insert_vals[] = $customer_address;
            }
            if(in_array('post_code', $columns)) {
                $insert_cols[] = 'post_code';
                $insert_vals[] = $customer_postcode;
            }
            if(in_array('customer_type', $columns)) {
                $insert_cols[] = 'customer_type';
                $insert_vals[] = 'customer';
            }
            
            $placeholders = implode(',', array_fill(0, count($insert_vals), '?'));
            $sql = "INSERT INTO customers (" . implode(',', $insert_cols) . ") VALUES (" . $placeholders . ")";
            
            $stmt = $repairsDB->prepare($sql);
            $stmt->execute($insert_vals);
            $customer_id = $repairsDB->lastInsertId();
            
            error_log("Created new customer with ID: " . $customer_id);
        } catch (Exception $e) {
            // If repairs DB not available, continue without customer_id
            error_log("Failed to create customer in repairs DB: " . $e->getMessage());
            $customer_id = null;
        }
    }
    
    // Calculate total value
    $total_value = 0;
    $item_count = 0;
    foreach($_POST as $key => $value) {
        if(strpos($key, 'item_name_') === 0) {
            $item_count++;
            $index = str_replace('item_name_', '', $key);
            $cost = floatval($_POST['item_cost_' . $index] ?? 0);
            $total_value += $cost;
        }
    }
    
    // Payment details - not collected at initial entry
    $payment_method = 'cash'; // Default
    $cash_amount = 0;
    $bank_amount = 0;
    $bank_account_name = null;
    $bank_account_number = null;
    $bank_sort_code = null;
    $bank_reference = null;
    $compliance_notes = $_POST['compliance_notes'] ?? null;
    
    // Generate unique trade-in reference (this is the main identifier)
    $trade_in_reference = 'TI-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if SKU column is nullable
    $sku_column = $DB->query("SHOW COLUMNS FROM trade_in_items WHERE Field = 'sku'")[0] ?? null;
    $sku_is_nullable = ($sku_column && $sku_column['Null'] == 'YES');
    
    // SKU is redundant with trade_in_reference
    // After running make_fields_nullable.php, we can set this to NULL
    // Until then, generate a temp value to satisfy the constraint
    $sku = $sku_is_nullable ? null : ('TI' . substr(time(), -7));
    
    // Ensure customer_id is properly set or null
    $customer_id = !empty($customer_id) ? $customer_id : null;
    
    // Insert trade-in record
    $sql = "INSERT INTO trade_in_items (
                sku, trade_in_reference, trade_in_date,
                customer_id, customer_name, customer_phone, customer_email,
                customer_address, customer_postcode,
                location, status, total_value, payment_method,
                cash_amount, bank_amount, bank_account_name, bank_account_number,
                bank_sort_code, bank_reference, compliance_notes,
                created_by, created_at
            ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $DB->query($sql, [
        $sku, $trade_in_reference,
        $customer_id, $customer_name, $customer_phone, $customer_email,
        $customer_address, $customer_postcode,
        $location, $total_value, $payment_method,
        $cash_amount, $bank_amount, $bank_account_name, $bank_account_number,
        $bank_sort_code, $bank_reference, $compliance_notes,
        $user_id
    ]);
    
    $trade_in_id = $DB->lastInsertId();
    
    // Upload ID documents
    for($i = 0; $i < 2; $i++) {
        $id_type = $_POST['id_type_' . $i] ?? '';
        $id_number = $_POST['id_number_' . $i] ?? '';
        
        if(!empty($id_type) && isset($_FILES['id_photo_' . $i]) && $_FILES['id_photo_' . $i]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/trade_in_ids/';
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['id_photo_' . $i];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'trade_in_' . $trade_in_id . '_id' . ($i+1) . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if(move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Insert ID photo record
                    $sql = "INSERT INTO trade_in_id_photos (
                                trade_in_id, file_path, document_type, document_number,
                                uploaded_by, created_at
                            ) VALUES (?, ?, ?, ?, ?, NOW())";
                    
                    $DB->query($sql, [
                        $trade_in_id,
                        'uploads/trade_in_ids/' . $filename,
                        $id_type,
                        $id_number,
                        $user_id
                    ]);
                }
            }
        }
    }
    
    // Get starting tracking code number ONCE before loop
    // Sort numerically by extracting the number part
    $last_item = $DB->query("
        SELECT tracking_code FROM trade_in_items_details 
        WHERE tracking_code LIKE 'SH%' 
        AND tracking_code REGEXP '^SH[0-9]+$'
        ORDER BY CAST(SUBSTRING(tracking_code, 3) AS UNSIGNED) DESC 
        LIMIT 1
    ");
    
    $next_tracking_num = 1;
    if(!empty($last_item) && !empty($last_item[0]['tracking_code'])) {
        $last_code = $last_item[0]['tracking_code'];
        if(preg_match('/SH(\d+)/', $last_code, $matches)) {
            $next_tracking_num = intval($matches[1]) + 1;
        }
    }
    
    // Track which items need generated codes (for response)
    $assigned_tracking_codes = [];
    
    // Insert items
    for($i = 0; $i < $item_count; $i++) {
        $item_name = $_POST['item_name_' . $i] ?? '';
        if(empty($item_name)) continue;
        
        $item_category = $_POST['item_category_' . $i] ?? '';
        $item_category = empty($item_category) ? null : $item_category;
        
        $item_serial = $_POST['item_serial_' . $i] ?? '';
        $item_serial = empty($item_serial) ? null : $item_serial;
        
        $item_notes = $_POST['item_notes_' . $i] ?? '';
        $item_notes = empty($item_notes) ? null : $item_notes;

        $item_brand = $_POST['item_brand_' . $i] ?? '';
$item_brand = empty($item_brand) ? null : $item_brand;

$item_model = $_POST['item_model_' . $i] ?? '';
$item_model = empty($item_model) ? null : $item_model;
        
        // IMPORTANT: Convert empty strings to NULL for UNIQUE constraint fields
        $item_preprinted = $_POST['item_preprinted_' . $i] ?? '';
        $item_preprinted = empty($item_preprinted) ? null : $item_preprinted;
        
        // Condition is NULL initially - will be set during testing phase
        $item_condition = $_POST['item_condition_' . $i] ?? null;
        $item_cost = floatval($_POST['item_cost_' . $i] ?? 0);
        
        // Generate tracking code ONLY if no preprinted code
        $item_tracking = null;
        if(empty($item_preprinted)) {
            $item_tracking = 'SH' . $next_tracking_num;
            $next_tracking_num++; // Increment for next item
            
            // Store for response
            $assigned_tracking_codes[] = [
                'item_name' => $item_name,
                'tracking_code' => $item_tracking,
                'preprinted_code' => null
            ];
        } else {
            // Has preprinted code - store for response
            $assigned_tracking_codes[] = [
                'item_name' => $item_name,
                'tracking_code' => null,
                'preprinted_code' => $item_preprinted
            ];
        }
        
        // Debug logging
        error_log("Inserting item $i: name=$item_name, preprinted=" . ($item_preprinted ?? 'NULL') . ", tracking=" . ($item_tracking ?? 'NULL'));
        
        // Insert item
        // Insert item
$sql = "INSERT INTO trade_in_items_details (
            trade_in_id, item_name, category, brand, model_number, serial_number,
            `condition`, price_paid, notes, preprinted_code,
            tracking_code, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$DB->query($sql, [
    $trade_in_id, $item_name, $item_category, $item_brand, $item_model, $item_serial,
    $item_condition, $item_cost, $item_notes, $item_preprinted,
    $item_tracking
]);
        
        $item_detail_id = $DB->lastInsertId();
        
        $item_detail_id = $DB->lastInsertId();
        
        // Upload item photos
        if(isset($_FILES['item_photos_' . $i])) {
            $files = $_FILES['item_photos_' . $i];
            $upload_dir = __DIR__ . '/../uploads/trade_in_items/';
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if(is_array($files['name'])) {
                // Multiple photos
                for($j = 0; $j < count($files['name']); $j++) {
                    if($files['error'][$j] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($files['name'][$j], PATHINFO_EXTENSION));
                        if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $filename = 'item_' . $item_detail_id . '_' . ($j+1) . '_' . time() . '.' . $ext;
                            $filepath = $upload_dir . $filename;
                            
                            if(move_uploaded_file($files['tmp_name'][$j], $filepath)) {
                                $sql = "INSERT INTO trade_in_item_photos (
                                            trade_in_item_id, file_path, uploaded_by, created_at
                                        ) VALUES (?, ?, ?, NOW())";
                                
                                $DB->query($sql, [
                                    $item_detail_id,
                                    'uploads/trade_in_items/' . $filename,
                                    $user_id
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Trade-in saved successfully',
        'trade_in_id' => $trade_in_id,
        'trade_in_reference' => $trade_in_reference,
        'tracking_codes' => $assigned_tracking_codes
    ]);
    
} catch (Exception $e) {
    error_log("Trade-in save error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
