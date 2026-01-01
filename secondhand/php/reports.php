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

// Get report type
$report_type = $_GET['type'] ?? 'summary';

try {
    switch ($report_type) {
        case 'summary':
            // Get summary statistics
            $total_items = $DB->query("SELECT COUNT(*) as count FROM second_hand_items")[0]['count'];
            $in_stock_items = $DB->query("SELECT COUNT(*) as count FROM second_hand_items WHERE status = 'in_stock'")[0]['count'];
            $sold_items = $DB->query("SELECT COUNT(*) as count FROM second_hand_items WHERE status = 'sold'")[0]['count'];
            $total_value = $DB->query("SELECT COALESCE(SUM(purchase_price), 0) as total FROM second_hand_items")[0]['total'];
            $total_estimated_sales = $DB->query("SELECT COALESCE(SUM(estimated_sale_price), 0) as total FROM second_hand_items")[0]['total'];
            
            // Get items by source
            $items_by_source = $DB->query("
                SELECT item_source, COUNT(*) as count 
                FROM second_hand_items 
                GROUP BY item_source
            ");
            
            // Get items by condition
            $items_by_condition = $DB->query("
                SELECT `condition`, COUNT(*) as count 
                FROM second_hand_items 
                GROUP BY `condition`
            ");
            
            echo json_encode([
                'success' => true,
                'report_type' => 'summary',
                'data' => [
                    'total_items' => $total_items,
                    'in_stock_items' => $in_stock_items,
                    'sold_items' => $sold_items,
                    'total_value' => $total_value,
                    'total_estimated_sales' => $total_estimated_sales,
                    'items_by_source' => $items_by_source,
                    'items_by_condition' => $items_by_condition
                ]
            ]);
            break;
            
        case 'inventory':
            // Get full inventory report
            $items = $DB->query("
                SELECT * FROM second_hand_items 
                ORDER BY acquisition_date DESC
            ");
            
            echo json_encode([
                'success' => true,
                'report_type' => 'inventory',
                'data' => $items
            ]);
            break;
            
        case 'trade_ins':
            // Get trade-in specific report
            $trade_ins = $DB->query("
                SELECT * FROM second_hand_items 
                WHERE item_source = 'trade_in'
                ORDER BY acquisition_date DESC
            ");
            
            echo json_encode([
                'success' => true,
                'report_type' => 'trade_ins',
                'data' => $trade_ins
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid report type'
            ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}
?>