<?php
session_start();
require_once '../../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Load permission functions
$permissions_file = __DIR__.'/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

// Check permissions
$is_admin = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);
$can_manage = $is_admin || (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB));

if (!$can_manage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get form data
$action = $_POST['action'] ?? '';

switch($action) {
    case 'save_trade_in':
        saveTradeIn();
        break;
    case 'search_customers':
        searchCustomers();
        break;
    case 'verify_password':
        verifyPassword();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function saveTradeIn() {
    global $DB, $user_id;
    
    try {
        // Get form data
        $customer_data = json_decode($_POST['customer_data'] ?? '[]', true);
        $id_data = json_decode($_POST['id_data'] ?? '[]', true);
        $items_data = json_decode($_POST['items_data'] ?? '[]', true);
        $payment_data = json_decode($_POST['payment_data'] ?? '[]', true);
        $compliance_notes = $_POST['compliance_notes'] ?? '';
        $signature_data = $_POST['signature_data'] ?? '';
        
        // Validate required data
        if (empty($customer_data) || empty($items_data)) {
            echo json_encode(['success' => false, 'message' => 'Customer and items data are required']);
            return;
        }
        
        // Start transaction
        $DB->beginTransaction();
        
        // Insert customer if new
        $customer_id = $customer_data['id'] ?? null;
        if (!$customer_id) {
            // Insert new customer
            $sql = "INSERT INTO customers (name, phone, email, address, postcode, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $customer_data['name'],
                $customer_data['phone'] ?? null,
                $customer_data['email'] ?? null,
                $customer_data['address'] ?? null,
                $customer_data['postcode'] ?? null,
                $user_id,
                date('Y-m-d H:i:s')
            ];
            
            $DB->query($sql, $params);
            $customer_id = $DB->lastInsertId();
        }
        
        // Insert trade-in record
        $sql = "INSERT INTO trade_in_items (
                    customer_id, customer_name, customer_phone, customer_email, 
                    location, status, total_value, payment_method, cash_amount, 
                    bank_amount, bank_account_name, bank_account_number, 
                    bank_sort_code, bank_reference, compliance_notes, 
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $customer_id,
            $customer_data['name'],
            $customer_data['phone'] ?? null,
            $customer_data['email'] ?? null,
            $user_details['user_location'],
            'completed', // Status set to completed
            $payment_data['total_value'] ?? 0,
            $payment_data['method'] ?? 'cash',
            $payment_data['cash_amount'] ?? 0,
            $payment_data['bank_amount'] ?? 0,
            $payment_data['bank_account_name'] ?? null,
            $payment_data['bank_account_number'] ?? null,
            $payment_data['bank_sort_code'] ?? null,
            $payment_data['bank_reference'] ?? null,
            $compliance_notes,
            $user_id,
            date('Y-m-d H:i:s')
        ];
        
        $DB->query($sql, $params);
        $trade_in_id = $DB->lastInsertId();
        
        // Insert items
        foreach ($items_data as $item) {
            // Generate tracking codes if not provided
            $preprinted_code = $item['preprinted_code'] ?? null;
            $tracking_code = $item['tracking_code'] ?? null;
            
            if (empty($tracking_code) && empty($preprinted_code)) {
                // Generate SH tracking code
                $last_sh_item = $DB->query("SELECT tracking_code FROM second_hand_items WHERE tracking_code LIKE 'SH%' ORDER BY tracking_code DESC LIMIT 1");
                $next_sh_number = 1;
                if (!empty($last_sh_item)) {
                    $last_code = $last_sh_item[0]['tracking_code'];
                    if (preg_match('/SH(\d+)/', $last_code, $matches)) {
                        $next_sh_number = (int)$matches[1] + 1;
                    }
                }
                $tracking_code = "SH{$next_sh_number}";
            } elseif (empty($tracking_code) && !empty($preprinted_code)) {
                // If only preprinted code is provided, use it as the tracking code too
                $tracking_code = $preprinted_code;
            } elseif (empty($preprinted_code) && !empty($tracking_code) && strpos($tracking_code, 'DSH') === 0) {
                // If tracking code starts with DSH, it's also the preprinted code
                $preprinted_code = $tracking_code;
            }
            
            // Insert trade-in item
            $sql = "INSERT INTO trade_in_items_details (
                        trade_in_id, item_name, category, serial_number, 
                        condition, price_paid, notes, preprinted_code, 
                        tracking_code, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $trade_in_id,
                $item['name'],
                $item['category'] ?? null,
                $item['serial_number'] ?? null,
                $item['condition'] ?? 'good',
                $item['price_paid'] ?? 0,
                $item['notes'] ?? null,
                $preprinted_code,
                $tracking_code,
                date('Y-m-d H:i:s')
            ];
            
            $DB->query($sql, $params);
            $item_id = $DB->lastInsertId();
            
            // Handle photo uploads for this item
            if (isset($_FILES['item_photos']) && isset($_FILES['item_photos']['name'][$item_id])) {
                $upload_dir = __DIR__ . '/../uploads/trade_in_items/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_tmp = $_FILES['item_photos']['tmp_name'][$item_id];
                $file_name = $_FILES['item_photos']['name'][$item_id];
                
                if ($file_tmp && is_uploaded_file($file_tmp)) {
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $new_filename = 'item_' . $item_id . '_' . time() . '.' . $file_ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            // Insert photo record
                            $DB->query(
                                "INSERT INTO trade_in_item_photos (trade_in_item_id, file_path, uploaded_by, created_at)
                                 VALUES (?, ?, ?, ?)",
                                [$item_id, $new_filename, $user_id, date('Y-m-d H:i:s')]
                            );
                        }
                    }
                }
            }
        }
        
        // Handle ID photos
        if (isset($_FILES['id_photos'])) {
            $upload_dir = __DIR__ . '/../uploads/trade_in_ids/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            for ($i = 0; $i < count($_FILES['id_photos']['name']); $i++) {
                $file_tmp = $_FILES['id_photos']['tmp_name'][$i];
                $file_name = $_FILES['id_photos']['name'][$i];
                
                if ($file_tmp && is_uploaded_file($file_tmp)) {
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $new_filename = 'id_' . $trade_in_id . '_' . $i . '_' . time() . '.' . $file_ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            // Insert ID photo record
                            $DB->query(
                                "INSERT INTO trade_in_id_photos (trade_in_id, file_path, document_type, document_number, uploaded_by, created_at)
                                 VALUES (?, ?, ?, ?, ?, ?)",
                                [
                                    $trade_in_id,
                                    $new_filename,
                                    $id_data[$i]['type'] ?? 'other',
                                    $id_data[$i]['number'] ?? null,
                                    $user_id,
                                    date('Y-m-d H:i:s')
                                ]
                            );
                        }
                    }
                }
            }
        }
        
        // Insert signature if provided
        if ($signature_data) {
            $signature_dir = __DIR__ . '/../uploads/trade_in_signatures/';
            if (!is_dir($signature_dir)) {
                mkdir($signature_dir, 0755, true);
            }
            
            $signature_filename = 'signature_' . $trade_in_id . '_' . time() . '.png';
            $signature_path = $signature_dir . $signature_filename;
            
            // Remove data:image/png;base64, prefix and decode
            $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
            $signature_data = str_replace(' ', '+', $signature_data);
            $signature_binary = base64_decode($signature_data);
            
            if ($signature_binary !== false) {
                file_put_contents($signature_path, $signature_binary);
                
                // Insert signature record
                $DB->query(
                    "INSERT INTO trade_in_signatures (trade_in_id, file_path, created_by, created_at)
                     VALUES (?, ?, ?, ?)",
                    [$trade_in_id, $signature_filename, $user_id, date('Y-m-d H:i:s')]
                );
            }
        }
        
        // Commit transaction
        $DB->commit();
        
        // Now import items to second-hand inventory
        importToSecondHand($trade_in_id);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Trade-in completed successfully',
            'trade_in_id' => $trade_in_id
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $DB->rollback();
        error_log("Error saving trade-in: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error saving trade-in: ' . $e->getMessage()]);
    }
}

function importToSecondHand($trade_in_id) {
    global $DB, $user_id, $user_details;
    
    try {
        // Get trade-in items
        $items = $DB->query(
            "SELECT * FROM trade_in_items_details WHERE trade_in_id = ?",
            [$trade_in_id]
        );
        
        foreach ($items as $item) {
            // Insert into second-hand inventory
            $sql = "INSERT INTO second_hand_items (
                        preprinted_code, tracking_code, item_name, condition, item_source, 
                        serial_number, status, purchase_price, estimated_value, estimated_sale_price,
                        customer_name, customer_contact, category, detailed_condition, location,
                        acquisition_date, warranty_info, supplier_info, model_number, brand, 
                        notes, trade_in_reference, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $item['preprinted_code'],
                $item['tracking_code'],
                $item['item_name'],
                $item['condition'],
                'trade_in',
                $item['serial_number'],
                'in_stock',
                $item['price_paid'],
                $item['price_paid'],
                $item['price_paid'] * 1.2, // Estimated sale price at 20% markup
                null, // customer_name will be from trade-in
                null, // customer_contact will be from trade-in
                $item['category'],
                $item['notes'],
                $user_details['user_location'],
                date('Y-m-d'),
                null,
                null,
                null, // model_number
                null, // brand
                $item['notes'],
                $trade_in_id,
                $user_id
            ];
            
            $DB->query($sql, $params);
            $second_hand_id = $DB->lastInsertId();
            
            // Log the import
            $log_sql = "INSERT INTO second_hand_audit_log (user_id, item_id, action, action_details) 
                        VALUES (?, ?, 'import_trade_in_to_second_hand', ?)";
            $log_details = json_encode([
                'imported_by' => $user_id,
                'imported_at' => date('Y-m-d H:i:s'),
                'trade_in_id' => $trade_in_id,
                'second_hand_id' => $second_hand_id,
                'item_name' => $item['item_name']
            ]);
            $DB->query($log_sql, [$user_id, $second_hand_id, $log_details]);
        }
    } catch (Exception $e) {
        error_log("Error importing trade-in to second-hand: " . $e->getMessage());
    }
}

function searchCustomers() {
    global $DB;

    $query = $_GET['q'] ?? '';
    if (strlen($query) < 2) {
        echo json_encode(['customers' => []]);
        return;
    }

    // Try to connect to the siteground database for customer search
    try {
        // Get database connection details from the main $DB connection
        $db_info = $DB->query("SELECT DATABASE() as current_db")[0];
        $current_db = $db_info['current_db'];

        // Connect to siteground database
        $siteground_DB = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=siteground;charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Check if the customers table exists in siteground database
        $table_check = $siteground_DB->query("
            SELECT COUNT(*) as count
            FROM information_schema.tables
            WHERE table_schema = 'siteground'
            AND table_name = 'customers'
        ")->fetch();
        $customers_table_exists = $table_check['count'];

        if ($customers_table_exists) {
            // Use the customers table from siteground database
            $sql = "SELECT id, name, phone, email FROM customers
                    WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
                    ORDER BY name LIMIT 10";

            $stmt = $siteground_DB->prepare($sql);
            $stmt->execute(["%$query%", "%$query%", "%$query%"]);
            $customers = $stmt->fetchAll();

            echo json_encode(['customers' => $customers]);
        } else {
            // If customers table doesn't exist in siteground, try other common customer tables
            $possible_tables = ['users', 'customers_table', 'client', 'customer'];
            $found_table = null;
            $customer_data = [];

            foreach ($possible_tables as $table) {
                try {
                    $table_check_result = $siteground_DB->query("
                        SELECT COUNT(*) as count
                        FROM information_schema.tables
                        WHERE table_schema = 'siteground'
                        AND table_name = '$table'
                    ")->fetch();

                    if ($table_check_result['count'] > 0) {
                        $found_table = $table;

                        // Try to find customer-like fields in the table
                        $columns = $siteground_DB->query("
                            SELECT COLUMN_NAME
                            FROM information_schema.COLUMNS
                            WHERE table_schema = 'siteground'
                            AND table_name = '$table'
                        ")->fetchAll(PDO::FETCH_COLUMN);

                        // Determine appropriate fields based on available columns
                        $name_field = in_array('name', $columns) ? 'name' :
                                     (in_array('username', $columns) ? 'username' :
                                     (in_array('first_name', $columns) ? 'first_name' : 'id'));
                        $email_field = in_array('email', $columns) ? 'email' :
                                      (in_array('email_address', $columns) ? 'email_address' : '""');
                        $phone_field = in_array('phone', $columns) ? 'phone' :
                                      (in_array('telephone', $columns) ? 'telephone' :
                                      (in_array('mobile', $columns) ? 'mobile' : '""'));

                        $sql = "SELECT id, `$name_field` as name, `$email_field` as email, `$phone_field` as phone
                                FROM `$table`
                                WHERE `$name_field` LIKE ? OR `$email_field` LIKE ?
                                ORDER BY `$name_field` LIMIT 10";

                        $stmt = $siteground_DB->prepare($sql);
                        $stmt->execute(["%$query%", "%$query%"]);
                        $customer_data = $stmt->fetchAll();

                        break;
                    }
                } catch (Exception $e) {
                    // Continue to next table if current one fails
                    continue;
                }
            }

            if ($found_table && !empty($customer_data)) {
                echo json_encode(['customers' => $customer_data]);
            } else {
                // If no customer table found in siteground, try the main database
                // Check users table in main database
                $users_table_check = $DB->query("
                    SELECT COUNT(*) as count
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                    AND table_name = 'users'
                ")->fetch();
                $users_table_exists = $users_table_check['count'];

                if ($users_table_exists) {
                    $sql = "SELECT id, username as name, email FROM users
                            WHERE username LIKE ? OR email LIKE ?
                            ORDER BY username LIMIT 10";

                    $stmt = $DB->prepare($sql);
                    $stmt->execute(["%$query%", "%$query%"]);
                    $users = $stmt->fetchAll();

                    // Format the results to match the expected customer format
                    $customers = [];
                    foreach ($users as $user) {
                        $customers[] = [
                            'id' => $user['id'],
                            'name' => $user['name'],
                            'phone' => '', // No phone in users table
                            'email' => $user['email'] ?? ''
                        ];
                    }

                    echo json_encode(['customers' => $customers]);
                } else {
                    // If no tables found anywhere, return empty results
                    echo json_encode(['customers' => []]);
                }
            }
        }
    } catch (Exception $e) {
        // If siteground connection fails, fall back to main database
        error_log("Siteground DB connection failed: " . $e->getMessage());

        // Try users table in main database
        $users_table_check = $DB->query("
            SELECT COUNT(*) as count
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = 'users'
        ")->fetch();
        $users_table_exists = $users_table_check['count'];

        if ($users_table_exists) {
            $sql = "SELECT id, username as name, email FROM users
                    WHERE username LIKE ? OR email LIKE ?
                    ORDER BY username LIMIT 10";

            $stmt = $DB->prepare($sql);
            $stmt->execute(["%$query%", "%$query%"]);
            $users = $stmt->fetchAll();

            // Format the results to match the expected customer format
            $customers = [];
            foreach ($users as $user) {
                $customers[] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'phone' => '', // No phone in users table
                    'email' => $user['email'] ?? ''
                ];
            }

            echo json_encode(['customers' => $customers]);
        } else {
            // If no tables found, return empty results
            echo json_encode(['customers' => []]);
        }
    }
}

function verifyPassword() {
    global $DB, $user_id;

    $password = $_POST['password'] ?? '';

    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required']);
        return;
    }

    try {
        // Get the user's full record to check password
        $user = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id]);

        if (empty($user)) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        $user_record = $user[0];
        $stored_password = $user_record['password'];

        // Check if password is stored as a hash (starts with $)
        if (substr($stored_password, 0, 1) === '$') {
            // Standard password_verify for bcrypt hashed passwords
            $is_valid = password_verify($password, $stored_password);
        } else {
            // For non-hashed passwords (less secure, but some systems still use this)
            // Try MD5 first (common in older systems)
            $is_valid = (md5($password) === $stored_password);

            // If MD5 doesn't match, try plain text
            if (!$is_valid) {
                $is_valid = ($password === $stored_password);
            }
        }

        if ($is_valid) {
            echo json_encode(['success' => true, 'message' => 'Password verified']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
    } catch (Exception $e) {
        error_log("Password verification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error verifying password: ' . $e->getMessage()]);
    }
}
?>