<?php
require __DIR__.'/../../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if rma-permissions.php exists and load it
$permissions_file = __DIR__.'/../rma-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
} else {
    // Fallback: define functions inline if file doesn't exist
    if (!function_exists('canViewSupplierData')) {
        function canViewSupplierData($user_id, $DB) {
            try {
                $result = $DB->query("SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Supplier'", [$user_id]);
                return !empty($result) && $result[0]['has_access'] == 1;
            } catch (Exception $e) {
                return false;
            }
        }
    }
    if (!function_exists('canViewFinancialData')) {
        function canViewFinancialData($user_id, $DB) {
            try {
                $result = $DB->query("SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Financial'", [$user_id]);
                return !empty($result) && $result[0]['has_access'] == 1;
            } catch (Exception $e) {
                return false;
            }
        }
    }
}

// Get user details
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];
$is_authorized = ($user_details['admin'] == 1 || $user_details['useradmin'] >= 1);

// Check specific permissions
$can_view_supplier = false;
$can_view_financial = false;

try {
    $can_view_supplier = canViewSupplierData($user_id, $DB);
    $can_view_financial = canViewFinancialData($user_id, $DB);
} catch (Exception $e) {
    // Continue with no permissions
}

// Determine effective location
$effective_location = $user_details['user_location'];
if(!empty($user_details['temp_location']) && 
   !empty($user_details['temp_location_expires']) && 
   strtotime($user_details['temp_location_expires']) > time()) {
    $effective_location = $user_details['temp_location'];
}

// Get filters
$search = $_POST['search'] ?? '';
$filter_location = $_POST['location'] ?? 'all';
$filter_status = $_POST['status'] ?? 'all';
$limit = intval($_POST['limit'] ?? 50);
$offset = intval($_POST['offset'] ?? 0);

// Build SELECT fields based on permissions
$select_fields = [
    "r.id",
    "r.barcode",
    "r.tracking_number",
    "r.serial_number",
    "r.sku",
    "r.product_name",
    "r.status",
    "r.location",
    "r.date_discovered",
    "r.needs_review",
    "ft.fault_name",
    "DATEDIFF(CURDATE(), r.date_discovered) AS days_open"
];

// Add supplier info only if user has permission
if ($can_view_supplier) {
    $select_fields[] = "r.supplier_name";
}

// Add cost info only if user has permission
if ($can_view_financial) {
    $select_fields[] = "r.cost_at_creation";
}

// Build WHERE clauses
$where_clauses = [];
$params = [];

// Location filter
if(!$is_authorized) {
    // Basic staff - only see their location
    $where_clauses[] = "r.location = ?";
    $params[] = $effective_location;
} else {
    // Authorized staff - can filter by location
    if($filter_location !== 'all') {
        $where_clauses[] = "r.location = ?";
        $params[] = $filter_location;
    }
}

// Status filter
if($filter_status !== 'all') {
    $where_clauses[] = "r.status = ?";
    $params[] = $filter_status;
}

// Search filter
if(!empty($search)) {
    $where_clauses[] = "(
        r.barcode LIKE ? OR 
        r.tracking_number LIKE ? OR 
        r.serial_number LIKE ? OR 
        CAST(r.sku AS CHAR) LIKE ? OR 
        r.product_name LIKE ?
    )";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

$where_sql = '';
if(count($where_clauses) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

try {
    // Get RMAs
    $sql = "
        SELECT " . implode(", ", $select_fields) . "
        FROM rma_items r
        INNER JOIN rma_fault_types ft ON r.fault_type_id = ft.id
        {$where_sql}
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $rmas = $DB->query($sql, array_merge($params, [$limit, $offset]));

    echo json_encode([
        'success' => true,
        'data' => $rmas,
        'can_view_supplier' => $can_view_supplier,
        'can_view_financial' => $can_view_financial
    ]);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>