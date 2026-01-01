<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();
require_once 'bootstrap.php';
ob_end_clean();

$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

// Only admin can access results
if (empty($user_details) || $user_details['admin'] < 1) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_overall_stats':
        $stats = [];
        
        // Total sessions (all statuses)
        $result = $DB->query("SELECT COUNT(*) as count FROM stock_count_sessions");
        $stats['total_sessions'] = $result[0]['count'];
        
        // Total items counted (all sessions) - using the new location-aware variance
        $result = $DB->query("SELECT COUNT(*) as count FROM stock_count_entries");
        $stats['total_items_counted'] = $result[0]['count'];
        
        // Pending items (ONLY from active sessions)
        $result = $DB->query("
            SELECT COUNT(*) as count 
            FROM stock_count_queue q
            LEFT JOIN stock_count_sessions s ON q.session_id = s.id
            WHERE q.status = 'pending' AND s.status = 'active'
        ");
        $stats['pending_items'] = $result[0]['count'] ?? 0;
        
        // Total variance value using location-specific variance calculations
        $result = $DB->query("
            SELECT SUM(e.variance_amount * mp.cost) as total_variance
            FROM stock_count_entries e
            LEFT JOIN master_products mp ON e.sku = mp.sku
            WHERE e.variance_amount IS NOT NULL
        ");
        $stats['total_variance_value'] = $result[0]['total_variance'] ?? 0;
        
        // Location-specific stats
        $location_stats = $DB->query("
            SELECT 
                s.location,
                COUNT(DISTINCT s.id) as session_count,
                COUNT(e.id) as items_counted,
                SUM(e.variance_amount * mp.cost) as location_variance_value
            FROM stock_count_sessions s
            LEFT JOIN stock_count_entries e ON s.id = e.session_id
            LEFT JOIN master_products mp ON e.sku = mp.sku
            GROUP BY s.location
        ");
        
        $stats['location_breakdown'] = $location_stats;
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;
        
    case 'get_sessions':
        $status = $_POST['status'] ?? '';
        
        $where_clause = '';
        $params = [];
        
        if (!empty($status)) {
            $where_clause = "WHERE s.status = ?";
            $params[] = $status;
        }
        
        $sessions = $DB->query("
            SELECT 
                s.*,
                u.username as created_by,
                COUNT(DISTINCT CASE WHEN q.status = 'pending' THEN q.id END) as pending_items,
                COUNT(DISTINCT e.id) as completed_items,
                COUNT(DISTINCT q.id) as total_items,
                SUM(e.variance_amount * mp.cost) as total_variance_value,
                COUNT(DISTINCT CASE WHEN e.variance_amount > 0 THEN e.id END) as items_with_overage,
                COUNT(DISTINCT CASE WHEN e.variance_amount < 0 THEN e.id END) as items_with_shortage,
                COUNT(DISTINCT CASE WHEN e.variance_amount = 0 THEN e.id END) as items_exact_match
            FROM stock_count_sessions s
            LEFT JOIN users u ON s.created_by_user_id = u.id
            LEFT JOIN stock_count_queue q ON s.id = q.session_id
            LEFT JOIN stock_count_entries e ON s.id = e.session_id
            LEFT JOIN master_products mp ON e.sku = mp.sku
            $where_clause
            GROUP BY s.id, s.name, s.description, s.created_by_user_id, s.created_date, s.status, s.location, u.username
            ORDER BY s.created_date DESC
        ", $params);
        
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        break;
        
    case 'get_session_results':
        $session_id = $_POST['session_id'] ?? '';
        
        if (empty($session_id)) {
            echo json_encode(['success' => false, 'error' => 'Session ID required']);
            break;
        }
        
        // Get session details first
        $session = $DB->query("SELECT * FROM stock_count_sessions WHERE id = ?", [$session_id]);
        if (empty($session)) {
            echo json_encode(['success' => false, 'error' => 'Session not found']);
            break;
        }
        
        $session_data = $session[0];
        
        $results = $DB->query("
            SELECT 
                e.*,
                mp.name,
                mp.pos_category,
                mp.cost,
                u.username as counted_by,
                e.variance_amount as variance,
                (e.variance_amount * mp.cost) as variance_value,
                s.location as session_location,
                CASE 
                    WHEN e.variance_amount > 0 THEN 'Overage'
                    WHEN e.variance_amount < 0 THEN 'Shortage'
                    WHEN e.variance_amount = 0 THEN 'Exact'
                    ELSE 'Unknown'
                END as variance_type,
                CASE 
                    WHEN e.system_stock_used = 0 AND e.counted_stock > 0 THEN 'Found Stock'
                    WHEN e.system_stock_used > 0 AND e.counted_stock = 0 THEN 'Missing Stock'
                    WHEN e.system_stock_used = 0 AND e.counted_stock = 0 THEN 'Confirmed Zero'
                    ELSE 'Normal Count'
                END as count_scenario
            FROM stock_count_entries e
            LEFT JOIN master_products mp ON e.sku = mp.sku
            LEFT JOIN users u ON e.counted_by_user_id = u.id
            LEFT JOIN stock_count_sessions s ON e.session_id = s.id
            WHERE e.session_id = ?
            ORDER BY ABS(e.variance_amount) DESC, e.count_date DESC
        ", [$session_id]);
        
        echo json_encode([
            'success' => true, 
            'results' => $results,
            'session' => $session_data
        ]);
        break;
        
    case 'mark_as_applied':
        $skus = $_POST['skus'] ?? [];
        
        if (empty($skus)) {
            echo json_encode(['success' => false, 'error' => 'No SKUs provided']);
            break;
        }
        
        // Ensure $skus is an array
        if (!is_array($skus)) {
            $skus = [$skus];
        }
        
        try {
            // Start transaction
            $DB->beginTransaction();
            
            $updated_count = 0;
            
            foreach ($skus as $sku) {
                // Update each SKU individually for better error handling
                $result = $DB->query("
                    UPDATE stock_count_entries 
                    SET applied_to_odoo = 'yes', 
                        applied_date = NOW(), 
                        applied_by_user_id = ?
                    WHERE sku = ? AND applied_to_odoo = 'no'
                ", [$user_id, $sku]);
                
                if ($result !== false) {
                    $updated_count++;
                }
            }
            
            // Commit transaction
            $DB->commit();
            
            echo json_encode([
                'success' => true, 
                'updated_count' => $updated_count,
                'total_requested' => count($skus)
            ]);
            
        } catch (Exception $e) {
            $DB->rollback();
            echo json_encode([
                'success' => false, 
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'archive_session':
        $session_id = $_POST['session_id'] ?? '';
        
        if (empty($session_id)) {
            echo json_encode(['success' => false, 'error' => 'Session ID required']);
            break;
        }
        
        try {
            // Get session stats before archiving
            $session_stats = $DB->query("
                SELECT 
                    COUNT(e.id) as result_count,
                    SUM(e.variance_amount * mp.cost) as total_variance_value
                FROM stock_count_entries e
                LEFT JOIN master_products mp ON e.sku = mp.sku
                WHERE e.session_id = ?
            ", [$session_id]);
            
            $result_count = $session_stats[0]['result_count'] ?? 0;
            $total_variance = $session_stats[0]['total_variance_value'] ?? 0;
            
            // Start transaction
            $DB->beginTransaction();
            
            // Insert into archive table
            $DB->query("
                INSERT INTO stock_count_archive (session_id, archived_by_user_id, result_count, total_variance_value, archived_date)
                VALUES (?, ?, ?, ?, NOW())
            ", [$session_id, $user_id, $result_count, $total_variance]);
            
            // Update session status
            $DB->query("UPDATE stock_count_sessions SET status = 'archived' WHERE id = ?", [$session_id]);
            
            $DB->commit();
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            $DB->rollback();
            echo json_encode(['success' => false, 'error' => 'Error archiving session: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_location_summary':
        // Get summary by location for dashboard
        $location_summary = $DB->query("
            SELECT 
                s.location,
                COUNT(DISTINCT s.id) as total_sessions,
                COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_sessions,
                COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_sessions,
                COUNT(e.id) as total_items_counted,
                SUM(CASE WHEN e.variance_amount > 0 THEN 1 ELSE 0 END) as items_with_overage,
                SUM(CASE WHEN e.variance_amount < 0 THEN 1 ELSE 0 END) as items_with_shortage,
                SUM(CASE WHEN e.variance_amount = 0 THEN 1 ELSE 0 END) as items_exact_match,
                SUM(e.variance_amount * mp.cost) as total_variance_value,
                AVG(ABS(e.variance_amount)) as avg_absolute_variance
            FROM stock_count_sessions s
            LEFT JOIN stock_count_entries e ON s.id = e.session_id
            LEFT JOIN master_products mp ON e.sku = mp.sku
            WHERE s.location IN ('cs', 'as')
            GROUP BY s.location
            ORDER BY s.location
        ");
        
        echo json_encode(['success' => true, 'location_summary' => $location_summary]);
        break;
        
    case 'get_variance_analysis':
        $session_id = $_POST['session_id'] ?? '';
        $location = $_POST['location'] ?? '';
        
        $where_conditions = [];
        $params = [];
        
        if (!empty($session_id)) {
            $where_conditions[] = "e.session_id = ?";
            $params[] = $session_id;
        }
        
        if (!empty($location)) {
            $where_conditions[] = "s.location = ?";
            $params[] = $location;
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $variance_analysis = $DB->query("
            SELECT 
                CASE 
                    WHEN e.variance_amount > 10 THEN 'Large Overage (>10)'
                    WHEN e.variance_amount BETWEEN 1 AND 10 THEN 'Small Overage (1-10)'
                    WHEN e.variance_amount = 0 THEN 'Exact Match'
                    WHEN e.variance_amount BETWEEN -10 AND -1 THEN 'Small Shortage (-1 to -10)'
                    WHEN e.variance_amount < -10 THEN 'Large Shortage (<-10)'
                    ELSE 'Unknown'
                END as variance_category,
                COUNT(*) as item_count,
                SUM(e.variance_amount * mp.cost) as category_value_impact,
                AVG(e.variance_amount) as avg_variance,
                MIN(e.variance_amount) as min_variance,
                MAX(e.variance_amount) as max_variance
            FROM stock_count_entries e
            LEFT JOIN master_products mp ON e.sku = mp.sku
            LEFT JOIN stock_count_sessions s ON e.session_id = s.id
            $where_clause
            GROUP BY variance_category
            ORDER BY avg_variance DESC
        ", $params);
        
        echo json_encode(['success' => true, 'variance_analysis' => $variance_analysis]);
        break;
        
    case 'get_user_performance':
        // Get counting performance by user
        $user_performance = $DB->query("
            SELECT 
                u.username,
                u.first_name,
                u.last_name,
                COUNT(e.id) as items_counted,
                COUNT(DISTINCT e.session_id) as sessions_participated,
                AVG(ABS(e.variance_amount)) as avg_absolute_variance,
                SUM(CASE WHEN e.variance_amount = 0 THEN 1 ELSE 0 END) as exact_matches,
                ROUND(
                    (SUM(CASE WHEN e.variance_amount = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(e.id)), 
                    2
                ) as accuracy_percentage,
                MIN(e.count_date) as first_count_date,
                MAX(e.count_date) as last_count_date
            FROM stock_count_entries e
            LEFT JOIN users u ON e.counted_by_user_id = u.id
            WHERE e.count_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.id, u.username, u.first_name, u.last_name
            HAVING items_counted > 0
            ORDER BY items_counted DESC
        ");
        
        echo json_encode(['success' => true, 'user_performance' => $user_performance]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>