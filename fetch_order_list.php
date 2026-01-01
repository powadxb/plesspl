<?php
session_start();
require 'php/bootstrap.php';
require 'php/odoo_connection.php'; // Add Odoo connection
if (!isset($_SESSION['dins_user_id'])) {
   echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
   exit();
}
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] >= 1;
// Map frontend sort names to actual column names
$column_mapping = [
   'category_name' => 'mp.pless_main_category',
   'added_at' => 'ol.added_at',
   'pless_main_category' => 'mp.pless_main_category',
   'last_ordered' => 'ol.last_ordered_at'
];
$sort_by = $_GET['sort_by'] ?? 'added_at';
$sort_direction = strtoupper($_GET['sort_direction'] ?? 'ASC');
$valid_directions = ['ASC', 'DESC'];
if (!in_array($sort_by, array_keys($column_mapping))) {
   $sort_by = 'added_at';
}
if (!in_array($sort_direction, $valid_directions)) {
   $sort_direction = 'ASC';
}
$actual_column = $column_mapping[$sort_by];
// Build the query with comment fields
$orderListQuery = "
   SELECT 
       ol.id,
       ol.sku,
       ol.name,
       ol.quantity,
       ol.status,
       ol.order_type,
       CASE 
           WHEN ol.last_ordered_at IS NULL THEN 'Never'
           ELSE DATE_FORMAT(ol.last_ordered_at, '%d/%m/%Y')
       END AS last_ordered,
       DATE_FORMAT(ol.added_at, '%d/%m/%Y') AS added_on,
       mp.pless_main_category AS category_name,
       FORMAT(mp.cost, 2) AS cost_price,
       COALESCE(ol.ean, mp.ean) AS ean,
       u.username AS requested_by,
       ol.public_comment,
       " . ($is_admin ? "ol.private_comment," : "NULL as private_comment,") . "
       ol.last_ordered_at,
       ol.added_at,
       mp.manufacturer,
       mp.pless_main_category,
       SUBSTRING_INDEX(COALESCE(mp.pless_main_category, 'uncategorized'), '/', 1) as root_category
   FROM 
       order_list ol
   LEFT JOIN 
       master_products mp ON ol.sku = mp.sku
   LEFT JOIN 
       users u ON ol.user_id = u.id
   WHERE 
       ol.status = 'pending' 
       OR (ol.status = 'ordered' AND ol.last_ordered_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY))
   ORDER BY 
       FIELD(ol.status, 'pending', 'ordered'),
       $actual_column $sort_direction,
       ol.added_at ASC
";
$orderList = $DB->query($orderListQuery);
// Add Odoo stock data if admin and we have results
if ($is_admin && !empty($orderList)) {
   // Extract SKUs from order list
   $skus = [];
   foreach ($orderList as $item) {
       if (!empty($item['sku'])) {
           $skus[] = $item['sku'];
       }
   }
   
   if (!empty($skus)) {
       // Get Odoo quantities for all SKUs
       $cs_quantities = getOdooQuantities($skus, 12); // CS warehouse
       $as_quantities = getOdooQuantities($skus, 19); // AS warehouse
       
       // Add stock data to each item
       foreach ($orderList as &$item) {
           $item['cs_stock'] = number_format($cs_quantities[$item['sku']] ?? 0);
           $item['as_stock'] = number_format($as_quantities[$item['sku']] ?? 0);
       }
   }
}

// Add category grouping
$grouped_data = [];
foreach ($orderList as $item) {
    $root_category = $item['root_category'] ?: 'Uncategorized';
    if (!isset($grouped_data[$root_category])) {
        $grouped_data[$root_category] = [
            'category_name' => ucfirst(str_replace(['_', '-'], ' ', $root_category)),
            'items' => [],
            'count' => 0
        ];
    }
    $grouped_data[$root_category]['items'][] = $item;
    $grouped_data[$root_category]['count']++;
}

// Sort categories
uksort($grouped_data, function($a, $b) {
    if ($a === 'Uncategorized') return 1;
    if ($b === 'Uncategorized') return -1;
    return strcmp($a, $b);
});

$response = [
   'success' => true,
   'data' => $orderList,
   'grouped_data' => $grouped_data,
   'is_admin' => $is_admin
];
echo json_encode($response);
?>