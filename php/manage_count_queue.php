<?php
// Clean start - no output allowed
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'bootstrap.php';

// Clean any unwanted output and start fresh
ob_end_clean();
ob_start();

$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

// Check if user has access to count_stock page (for viewing sessions)
$has_count_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'count_stock'", 
    [$user_id]
);

$is_admin = !empty($user_details) && $user_details['admin'] >= 1;
$can_view_sessions = !empty($has_count_access) && $has_count_access[0]['has_access'];

// Check access based on action
$action = $_POST['action'] ?? '';

// Actions that require admin access
$admin_only_actions = ['clear_all', 'remove_item', 'add_items', 'add_all_filtered', 'create_session', 'complete_session', 'assign_user_location'];

if (in_array($action, $admin_only_actions) && !$is_admin) {
    echo 'Access denied - Admin required';
    exit();
}

// Actions that require count_stock access (including viewing sessions)
$count_access_actions = ['get_active_sessions', 'get_user_accessible_sessions'];

if (in_array($action, $count_access_actions) && !$can_view_sessions) {
    echo json_encode(['success' => false, 'error' => 'Access denied - Count access required']);
    exit();
}

// General access check for other actions
if (!$can_view_sessions && !$is_admin) {
    echo 'Access denied';
    exit();
}

// Helper function to get user's effective location
function getUserEffectiveLocation($user_id, $DB) {
    $user = $DB->query("
        SELECT user_location, temp_location, temp_location_expires 
        FROM users 
        WHERE id = ?
    ", [$user_id]);
    
    if (empty($user)) {
        return null;
    }
    
    $user_data = $user[0];
    
    // Check if temporary location is active
    if (!empty($user_data['temp_location']) && 
        !empty($user_data['temp_location_expires']) && 
        strtotime($user_data['temp_location_expires']) > time()) {
        return $user_data['temp_location'];
    }
    
    return $user_data['user_location'];
}

switch ($action) {
    case 'create_session':
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = $_POST['location'] ?? '';
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Session name is required']);
            break;
        }
        
        // Validate location - must be 'cs' or 'as' (no 'both' allowed)
        if (!in_array($location, ['cs', 'as'])) {
            echo json_encode(['success' => false, 'error' => 'Session must target a specific location (CS or AS)']);
            break;
        }
        
        try {
    $DB->query(
        "INSERT INTO stock_count_sessions (name, description, created_by_user_id, location, created_for_location) VALUES (?, ?, ?, ?, ?)",
        [$name, $description, $user_id, $location, $location]
    );
    
    // Get the actual inserted ID
    $session_id = $DB->lastInsertId();
    
    echo json_encode(['success' => true, 'session_id' => $session_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_active_sessions':
        // Get all active sessions for admin, or location-specific sessions for regular users
        $user_location = getUserEffectiveLocation($user_id, $DB);
        
        if ($is_admin) {
            // Admin sees all active sessions
            $sessions = $DB->query("
                SELECT 
                    s.*,
                    u.username as created_by,
                    COALESCE(pending_counts.pending_items, 0) as pending_items,
                    COALESCE(completed_counts.completed_items, 0) as completed_items
                FROM stock_count_sessions s
                LEFT JOIN users u ON s.created_by_user_id = u.id
                LEFT JOIN (
                    SELECT session_id, COUNT(*) as pending_items 
                    FROM stock_count_queue 
                    WHERE status = 'pending' 
                    GROUP BY session_id
                ) pending_counts ON s.id = pending_counts.session_id
                LEFT JOIN (
                    SELECT session_id, COUNT(*) as completed_items 
                    FROM stock_count_entries 
                    GROUP BY session_id
                ) completed_counts ON s.id = completed_counts.session_id
                WHERE s.status = 'active'
                ORDER BY s.created_date DESC
            ");
        } else {
            // Regular users only see sessions for their location
            if (empty($user_location)) {
                echo json_encode(['success' => false, 'error' => 'User location not assigned. Contact admin.']);
                break;
            }
            
            $sessions = $DB->query("
                SELECT 
                    s.*,
                    u.username as created_by,
                    COALESCE(pending_counts.pending_items, 0) as pending_items,
                    COALESCE(completed_counts.completed_items, 0) as completed_items
                FROM stock_count_sessions s
                LEFT JOIN users u ON s.created_by_user_id = u.id
                LEFT JOIN (
                    SELECT session_id, COUNT(*) as pending_items 
                    FROM stock_count_queue 
                    WHERE status = 'pending' 
                    GROUP BY session_id
                ) pending_counts ON s.id = pending_counts.session_id
                LEFT JOIN (
                    SELECT session_id, COUNT(*) as completed_items 
                    FROM stock_count_entries 
                    GROUP BY session_id
                ) completed_counts ON s.id = completed_counts.session_id
                WHERE s.status = 'active' AND s.location = ?
                ORDER BY s.created_date DESC
            ", [$user_location]);
        }
        
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        break;
        
    case 'get_user_accessible_sessions':
        // Similar to get_active_sessions but with more detailed access info
        $user_location = getUserEffectiveLocation($user_id, $DB);
        
        $sessions = $DB->query("
            SELECT 
                s.*,
                u.username as created_by,
                COALESCE(pending_counts.pending_items, 0) as pending_items,
                COALESCE(completed_counts.completed_items, 0) as completed_items,
                ? as user_effective_location,
                CASE 
                    WHEN ? = 1 THEN 1
                    WHEN s.location = ? THEN 1
                    ELSE 0
                END as can_access
            FROM stock_count_sessions s
            LEFT JOIN users u ON s.created_by_user_id = u.id
            LEFT JOIN (
                SELECT session_id, COUNT(*) as pending_items 
                FROM stock_count_queue 
                WHERE status = 'pending' 
                GROUP BY session_id
            ) pending_counts ON s.id = pending_counts.session_id
            LEFT JOIN (
                SELECT session_id, COUNT(*) as completed_items 
                FROM stock_count_entries 
                GROUP BY session_id
            ) completed_counts ON s.id = completed_counts.session_id
            WHERE s.status = 'active'
            AND (? = 1 OR s.location = ?)
            ORDER BY s.created_date DESC
        ", [$user_location, $is_admin ? 1 : 0, $user_location, $is_admin ? 1 : 0, $user_location]);
        
        echo json_encode(['success' => true, 'sessions' => $sessions, 'user_location' => $user_location]);
        break;
        
    case 'assign_user_location':
        $target_user_id = $_POST['user_id'] ?? '';
        $location = $_POST['location'] ?? '';
        $is_temporary = $_POST['is_temporary'] ?? false;
        $expires_hours = $_POST['expires_hours'] ?? 24;
        
        if (empty($target_user_id) || !in_array($location, ['cs', 'as'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID or location']);
            break;
        }
        
        try {
            $DB->beginTransaction();
            
            if ($is_temporary) {
                // Set temporary location
                $expires_date = date('Y-m-d H:i:s', strtotime("+{$expires_hours} hours"));
                $DB->query("
                    UPDATE users 
                    SET temp_location = ?, temp_location_expires = ? 
                    WHERE id = ?
                ", [$location, $expires_date, $target_user_id]);
                
                // Log the assignment
                $DB->query("
                    INSERT INTO user_location_history (user_id, location, assignment_type, assigned_by, expires_date)
                    VALUES (?, ?, 'temporary', ?, ?)
                ", [$target_user_id, $location, $user_id, $expires_date]);
            } else {
                // Set permanent location and clear any temporary assignment
                $DB->query("
                    UPDATE users 
                    SET user_location = ?, temp_location = NULL, temp_location_expires = NULL 
                    WHERE id = ?
                ", [$location, $target_user_id]);
                
                // Log the assignment
                $DB->query("
                    INSERT INTO user_location_history (user_id, location, assignment_type, assigned_by)
                    VALUES (?, ?, 'permanent', ?)
                ", [$target_user_id, $location, $user_id]);
            }
            
            $DB->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $DB->rollback();
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'clear_all':
        $session_id = $_POST['session_id'] ?? '';
        if (empty($session_id)) {
            // Remove all items from all active sessions
            $DB->query("DELETE FROM stock_count_queue WHERE session_id IN (SELECT id FROM stock_count_sessions WHERE status = 'active')");
        } else {
            // Remove items from specific session
            $DB->query("DELETE FROM stock_count_queue WHERE session_id = ?", [$session_id]);
        }
        echo 'success';
        break;
        
    case 'remove_item':
        $sku = $_POST['sku'] ?? '';
        $session_id = $_POST['session_id'] ?? '';
        
        if (!empty($sku)) {
            if (!empty($session_id)) {
                $DB->query("DELETE FROM stock_count_queue WHERE sku = ? AND session_id = ?", [$sku, $session_id]);
            } else {
                $DB->query("DELETE FROM stock_count_queue WHERE sku = ?", [$sku]);
            }
            echo 'success';
        } else {
            echo 'Invalid SKU';
        }
        break;
        
    case 'add_items':
        $skus = $_POST['skus'] ?? [];
        $session_id = $_POST['session_id'] ?? '';
        
        // Validate session exists and is active
        if (empty($session_id)) {
            echo json_encode(['success' => false, 'error' => 'Session ID is required']);
            break;
        }
        
        $session = $DB->query("SELECT id, location FROM stock_count_sessions WHERE id = ? AND status = 'active'", [$session_id]);
        if (empty($session)) {
            echo json_encode(['success' => false, 'error' => 'Invalid or inactive session']);
            break;
        }
        
        $added_count = 0;
        
        foreach ($skus as $sku) {
            // Check if item is already in this session's queue
            $existing = $DB->query("SELECT id FROM stock_count_queue WHERE sku = ? AND session_id = ?", [$sku, $session_id]);
            
            if (empty($existing)) {
                $DB->query(
                    "INSERT INTO stock_count_queue (sku, added_by_user_id, session_id, status) VALUES (?, ?, ?, 'pending')",
                    [$sku, $user_id, $session_id]
                );
                $added_count++;
            }
        }
        
        echo json_encode(['success' => true, 'added_count' => $added_count, 'session_id' => $session_id]);
        break;
        
    case 'add_all_filtered':
        // Get session_id from POST
        $session_id = $_POST['session_id'] ?? '';
        
        if (empty($session_id)) {
            echo json_encode(['success' => false, 'error' => 'Session ID is required']);
            break;
        }
        
        // Validate session
        $session = $DB->query("SELECT id, location FROM stock_count_sessions WHERE id = ? AND status = 'active'", [$session_id]);
        if (empty($session)) {
            echo json_encode(['success' => false, 'error' => 'Invalid or inactive session']);
            break;
        }
        
        // Get all products matching the current filters (same logic as zlist_products.php)
        require_once 'odoo_connection.php';
        
        // Build WHERE clause (same as zlist_products.php)
        $where_conditions = [];
        $where_params = [];

        // General text search
        if (!empty($_POST['search_query'])) {
            $search_words = array_filter(explode(" ", trim($_POST['search_query'])));
            foreach ($search_words as $word) {
                $search_pattern = '%' . trim($word) . '%';
                $where_conditions[] = "(sku LIKE ? OR name LIKE ? OR manufacturer LIKE ? OR mpn LIKE ? OR ean LIKE ? OR pos_category LIKE ?)";
                array_push($where_params, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern);
            }
        }

        // SKU-specific search
        if (!empty($_POST['sku_search_query'])) {
            $where_conditions[] = "sku LIKE ?";
            $where_params[] = '%' . trim($_POST['sku_search_query']) . '%';
        }

        // Enabled filter
        if (!empty($_POST['enabled_products']) && $_POST['enabled_products'] === 'true') {
            $where_conditions[] = "enable = ?";
            $where_params[] = 'y';
        }

        // WWW filter
        if (!empty($_POST['www_filter']) && $_POST['www_filter'] === 'true') {
            $where_conditions[] = "export_to_magento = ?";
            $where_params[] = 'y';
        }

        // Category filter
        if (!empty($_POST['category'])) {
            $where_conditions[] = "pos_category = ?";
            $where_params[] = $_POST['category'];
        }

        // Build the query
        $query = "SELECT sku FROM master_products";
        if (!empty($where_conditions)) {
            $query .= " WHERE " . implode(' AND ', $where_conditions);
        }

        // Get all matching products
        $all_products = $DB->query($query, $where_params);
        
        if (empty($all_products)) {
            echo json_encode(['success' => false, 'error' => 'No products found matching filters']);
            break;
        }

        // Apply stock filters if any are set
        $filtered_skus = [];
        $stock_filters_applied = false;
        
        // Check if any stock filters are enabled
        if (!empty($_POST['cs_negative_stock']) || !empty($_POST['cs_zero_stock']) || 
            !empty($_POST['cs_above_stock']) || !empty($_POST['cs_below_stock']) ||
            !empty($_POST['as_negative_stock']) || !empty($_POST['as_zero_stock']) || 
            !empty($_POST['as_above_stock']) || !empty($_POST['as_below_stock'])) {
            
            $stock_filters_applied = true;
            $skus = array_column($all_products, 'sku');
            $cs_quantities = getOdooQuantities($skus, 12);
            $as_quantities = getOdooQuantities($skus, 19);
            
            foreach ($all_products as $product) {
                $cs_qty = floatval($cs_quantities[$product['sku']] ?? 0);
                $as_qty = floatval($as_quantities[$product['sku']] ?? 0);
                
                $include = true;

                // CS Stock Filters
                if (!empty($_POST['cs_negative_stock']) && $_POST['cs_negative_stock'] === 'true') {
                    if ($cs_qty >= 0) $include = false;
                }
                if (!empty($_POST['cs_zero_stock']) && $_POST['cs_zero_stock'] === 'true') {
                    if ($cs_qty !== 0) $include = false;
                }
                if (!empty($_POST['cs_above_stock']) && $_POST['cs_above_stock'] === 'true') {
                    $threshold = floatval($_POST['cs_above_value'] ?? 0);
                    if ($cs_qty < $threshold) $include = false;
                }
                if (!empty($_POST['cs_below_stock']) && $_POST['cs_below_stock'] === 'true') {
                    $threshold = floatval($_POST['cs_below_value'] ?? 0);
                    if ($cs_qty > $threshold) $include = false;
                }

                // AS Stock Filters
                if (!empty($_POST['as_negative_stock']) && $_POST['as_negative_stock'] === 'true') {
                    if ($as_qty >= 0) $include = false;
                }
                if (!empty($_POST['as_zero_stock']) && $_POST['as_zero_stock'] === 'true') {
                    if ($as_qty !== 0) $include = false;
                }
                if (!empty($_POST['as_above_stock']) && $_POST['as_above_stock'] === 'true') {
                    $threshold = floatval($_POST['as_above_value'] ?? 0);
                    if ($as_qty < $threshold) $include = false;
                }
                if (!empty($_POST['as_below_stock']) && $_POST['as_below_stock'] === 'true') {
                    $threshold = floatval($_POST['as_below_value'] ?? 0);
                    if ($as_qty > $threshold) $include = false;
                }

                if ($include) {
                    $filtered_skus[] = $product['sku'];
                }
            }
        } else {
            // No stock filters, use all products
            $filtered_skus = array_column($all_products, 'sku');
        }
        
        $total_found = count($filtered_skus);
        $added_count = 0;
        
        foreach ($filtered_skus as $sku) {
            // Check if item is already in this session's queue
            $existing = $DB->query("SELECT id FROM stock_count_queue WHERE sku = ? AND session_id = ?", [$sku, $session_id]);
            
            if (empty($existing)) {
                $DB->query(
                    "INSERT INTO stock_count_queue (sku, added_by_user_id, session_id, status) VALUES (?, ?, ?, 'pending')",
                    [$sku, $user_id, $session_id]
                );
                $added_count++;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'added_count' => $added_count,
            'total_found' => $total_found,
            'session_id' => $session_id,
            'stock_filters_applied' => $stock_filters_applied
        ]);
        break;
        
    case 'complete_session':
        $session_id = $_POST['session_id'] ?? '';
        $auto_zero_uncounted = $_POST['auto_zero_uncounted'] ?? false;
        $confirmation_text = $_POST['confirmation_text'] ?? '';
        
        if (empty($session_id)) {
            echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
            break;
        }
        
        // Get session details
        $session = $DB->query("SELECT * FROM stock_count_sessions WHERE id = ? AND status = 'active'", [$session_id]);
        if (empty($session)) {
            echo json_encode(['success' => false, 'error' => 'Session not found or already completed']);
            break;
        }
        
        $session_data = $session[0];
        $session_location = $session_data['location'];
        
        // Check for pending items
        $pending_items = $DB->query("
            SELECT sku FROM stock_count_queue 
            WHERE session_id = ? AND status = 'pending'
        ", [$session_id]);
        
        $pending_count = count($pending_items);
        
        // If there are pending items and auto_zero is not requested, ask admin what to do
        if ($pending_count > 0 && !$auto_zero_uncounted) {
            echo json_encode([
                'success' => false,
                'pending_items' => $pending_count,
                'requires_decision' => true,
                'message' => "This session has {$pending_count} uncounted items. What would you like to do?"
            ]);
            break;
        }
        
        // If auto_zero is requested, validate confirmation
        if ($auto_zero_uncounted && $confirmation_text !== 'CONFIRM') {
            echo json_encode([
                'success' => false,
                'error' => 'You must type "CONFIRM" to auto-zero uncounted items'
            ]);
            break;
        }
        
        try {
            $DB->beginTransaction();
            
            // Process uncounted items if auto_zero is requested
            if ($auto_zero_uncounted && $pending_count > 0) {
                // Include Odoo connection for stock lookup
                require_once 'odoo_connection.php';
                
                // Process each uncounted item
                foreach ($pending_items as $item) {
                    $sku = $item['sku'];
                    
                    // Get current system stock from Odoo for both locations
                    $cs_stock = getOdooQuantities([$sku], 12)[$sku] ?? 0;
                    $as_stock = getOdooQuantities([$sku], 19)[$sku] ?? 0;
                    
                    // Determine which system stock to use for variance calculation based on session location
                    $target_system_stock = ($session_location === 'cs') ? $cs_stock : $as_stock;
                    $counted_stock = 0; // Auto-zero for uncounted items
                    $variance = $counted_stock - $target_system_stock;
                    
                    // Insert zero count entry for uncounted item
                    $DB->query(
                        "INSERT INTO stock_count_entries (
                            sku, 
                            counted_by_user_id, 
                            counted_stock, 
                            system_cs_stock, 
                            system_as_stock, 
                            session_id, 
                            target_location,
                            variance_amount,
                            system_stock_used,
                            count_date,
                            auto_zeroed
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)",
                        [
                            $sku, 
                            $user_id, // Admin who completed the session
                            $counted_stock, 
                            $cs_stock, 
                            $as_stock, 
                            $session_id,
                            $session_location,
                            $variance,
                            $target_system_stock
                        ]
                    );
                    
                    // Update queue status to 'auto_zeroed'
                    $DB->query("
                        UPDATE stock_count_queue 
                        SET status = 'auto_zeroed' 
                        WHERE sku = ? AND session_id = ?
                    ", [$sku, $session_id]);
                }
            }
            
            // Complete the session
            $DB->query("UPDATE stock_count_sessions SET status = 'completed' WHERE id = ?", [$session_id]);
            
            $DB->commit();
            
            // Return success message
            if ($auto_zero_uncounted && $pending_count > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Session completed successfully. {$pending_count} uncounted items were automatically recorded as zero counts."
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Session completed successfully.'
                ]);
            }
            
        } catch (Exception $e) {
            $DB->rollback();
            error_log("Error completing session {$session_id}: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error completing session: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        echo 'Invalid action';
        break;
}
?>