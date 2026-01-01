<?php
// ================ INITIALIZATION START ================
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}
// ================ INITIALIZATION END ================

// ================ MAIN LOGIC START ================
try {
    // Get search parameters
    $search_sku = $_GET['sku'] ?? '';
    $search_customer = $_GET['customer'] ?? '';
    $search_condition = $_GET['condition'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // Build search conditions
    $where_conditions = [];
    $params = [];

    if (!empty($search_sku)) {
        $where_conditions[] = "(ti.sku LIKE ? OR ti.custom_sku LIKE ? OR ti.serial_number LIKE ?)";
        $search_sku = "%{$search_sku}%";
        $params = array_merge($params, [$search_sku, $search_sku, $search_sku]);
    }

    if (!empty($search_condition)) {
        $where_conditions[] = "ti.condition_rating = ?";
        $params[] = $search_condition;
    }

    // Use centralized siteground database connection from bootstrap.php
    global $SitegroundDB;

    if (!empty($search_customer)) {
        // Search in siteground database customers table
        $customer_results = $SitegroundDB->query("
            SELECT id 
            FROM customers 
            WHERE name LIKE ?
        ", ["%{$search_customer}%"]);
        
        $customer_ids = array_column($customer_results, 'id');
        
        if (!empty($customer_ids)) {
            $placeholders = str_repeat('?,', count($customer_ids) - 1) . '?';
            $where_conditions[] = "ti.customer_id IN ({$placeholders})";
            $params = array_merge($params, $customer_ids);
        } else {
            // If no customers found matching the search, return empty result
            echo json_encode([
                'success' => true,
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_items' => 0,
                    'per_page' => $per_page
                ]
            ]);
            exit;
        }
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM trade_in_items ti";
    if (!empty($where_conditions)) {
        $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $total_items = $DB->query($count_sql, $params)[0]['total'];
    $total_pages = ceil($total_items / $per_page);

    // Get items
    $items_sql = "
        SELECT 
            ti.*,
            u.username as created_by_name
        FROM trade_in_items ti
        LEFT JOIN users u ON ti.created_by = u.id
    ";

    if (!empty($where_conditions)) {
        $items_sql .= " WHERE " . implode(" AND ", $where_conditions);
    }

    $items_sql .= " ORDER BY ti.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;

    $items = $DB->query($items_sql, $params);

    // Get customer details for each item
    foreach ($items as &$item) {
        $customer = $repairsDB->prepare("
            SELECT name as customer_name, phone, email, post_code, address
            FROM customers 
            WHERE id = ?
        ");
        $customer->execute([$item['customer_id']]);
        $customer_data = $customer->fetch(PDO::FETCH_ASSOC);
        
        $item['customer_name'] = $customer_data['customer_name'] ?? 'Unknown Customer';
        $item['customer_phone'] = $customer_data['phone'] ?? '';
        $item['customer_email'] = $customer_data['email'] ?? '';
        $item['customer_post_code'] = $customer_data['post_code'] ?? '';
        $item['customer_address'] = $customer_data['address'] ?? '';
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total_items,
            'per_page' => $per_page
        ]
    ]);

} catch (Exception $e) {
    error_log("Get trade-ins error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
// ================ MAIN LOGIC END ================