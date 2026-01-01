<?php
require_once '../php/bootstrap.php';

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

// Get action
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'valuation_report':
            // Calculate total inventory value based on different metrics
            $total_items = $DB->query("SELECT COUNT(*) as count FROM second_hand_items")[0]['count'];
            $in_stock_items = $DB->query("SELECT COUNT(*) as count FROM second_hand_items WHERE status = 'in_stock'")[0]['count'];
            
            // Calculate values
            $total_purchase_value = $DB->query("SELECT COALESCE(SUM(purchase_price), 0) as total FROM second_hand_items WHERE purchase_price IS NOT NULL")[0]['total'];
            $total_estimated_value = $DB->query("SELECT COALESCE(SUM(estimated_value), 0) as total FROM second_hand_items WHERE estimated_value IS NOT NULL")[0]['total'];
            $total_estimated_sales = $DB->query("SELECT COALESCE(SUM(estimated_sale_price), 0) as total FROM second_hand_items WHERE estimated_sale_price IS NOT NULL")[0]['total'];
            
            // Calculate by condition
            $value_by_condition = $DB->query("
                SELECT 
                    `condition`,
                    COUNT(*) as item_count,
                    COALESCE(SUM(purchase_price), 0) as purchase_value,
                    COALESCE(SUM(estimated_value), 0) as estimated_value,
                    COALESCE(SUM(estimated_sale_price), 0) as estimated_sales
                FROM second_hand_items 
                WHERE status = 'in_stock'
                GROUP BY `condition`
            ");
            
            // Calculate by source
            $value_by_source = $DB->query("
                SELECT 
                    item_source,
                    COUNT(*) as item_count,
                    COALESCE(SUM(purchase_price), 0) as purchase_value,
                    COALESCE(SUM(estimated_value), 0) as estimated_value,
                    COALESCE(SUM(estimated_sale_price), 0) as estimated_sales
                FROM second_hand_items 
                WHERE status = 'in_stock'
                GROUP BY item_source
            ");
            
            echo json_encode([
                'success' => true,
                'report_type' => 'valuation',
                'data' => [
                    'total_items' => $total_items,
                    'in_stock_items' => $in_stock_items,
                    'total_purchase_value' => $total_purchase_value,
                    'total_estimated_value' => $total_estimated_value,
                    'total_estimated_sales' => $total_estimated_sales,
                    'value_by_condition' => $value_by_condition,
                    'value_by_source' => $value_by_source
                ]
            ]);
            break;
            
        case 'inventory_aging':
            // Get inventory aging report (how long items have been in inventory)
            $aging_report = $DB->query("
                SELECT 
                    CASE 
                        WHEN DATEDIFF(CURDATE(), acquisition_date) <= 30 THEN '0-30 days'
                        WHEN DATEDIFF(CURDATE(), acquisition_date) <= 60 THEN '31-60 days'
                        WHEN DATEDIFF(CURDATE(), acquisition_date) <= 90 THEN '61-90 days'
                        WHEN DATEDIFF(CURDATE(), acquisition_date) <= 180 THEN '91-180 days'
                        WHEN DATEDIFF(CURDATE(), acquisition_date) <= 365 THEN '181-365 days'
                        ELSE 'Over 1 year'
                    END as age_range,
                    COUNT(*) as item_count,
                    COALESCE(SUM(purchase_price), 0) as purchase_value,
                    COALESCE(SUM(estimated_sale_price), 0) as estimated_sales
                FROM second_hand_items 
                WHERE status = 'in_stock'
                GROUP BY age_range
                ORDER BY 
                    CASE age_range
                        WHEN '0-30 days' THEN 1
                        WHEN '31-60 days' THEN 2
                        WHEN '61-90 days' THEN 3
                        WHEN '91-180 days' THEN 4
                        WHEN '181-365 days' THEN 5
                        ELSE 6
                    END
            ");
            
            echo json_encode([
                'success' => true,
                'report_type' => 'aging',
                'data' => $aging_report
            ]);
            break;
            
        case 'slow_moving':
            // Identify slow-moving inventory (items in stock for more than 90 days)
            $slow_moving = $DB->query("
                SELECT 
                    id,
                    item_name,
                    preprinted_code,
                    tracking_code,
                    category,
                    `condition`,
                    purchase_price,
                    estimated_sale_price,
                    DATEDIFF(CURDATE(), acquisition_date) as days_in_inventory,
                    acquisition_date
                FROM second_hand_items 
                WHERE status = 'in_stock' 
                AND DATEDIFF(CURDATE(), acquisition_date) > 90
                ORDER BY days_in_inventory DESC
                LIMIT 50
            ");
            
            echo json_encode([
                'success' => true,
                'report_type' => 'slow_moving',
                'data' => $slow_moving
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>