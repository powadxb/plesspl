<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require '../../php/bootstrap.php';

    // Get user details and check permissions directly from database
    $user_id = $_SESSION['dins_user_id'];
    $user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

    // Check permissions from database
    $financial_check = $DB->query(
        "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Financial'",
        [$user_id]
    );
    $can_view_financial = !empty($financial_check) && $financial_check[0]['has_access'];

    $customer_check = $DB->query(
        "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Customer Data'",
        [$user_id]
    );
    $can_view_customer = !empty($customer_check) && $customer_check[0]['has_access'];

    $documents_check = $DB->query(
        "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Documents'",
        [$user_id]
    );
    $can_view_documents = !empty($documents_check) && $documents_check[0]['has_access'];

    $manage_check = $DB->query(
        "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'",
        [$user_id]
    );
    $can_manage = !empty($manage_check) && $manage_check[0]['has_access'];

    $all_locations_check = $DB->query(
        "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View All Locations'",
        [$user_id]
    );
    $can_view_all_locations = !empty($all_locations_check) && $all_locations_check[0]['has_access'];

// Determine effective location
$effective_location = $user_details['user_location'];
if(!empty($user_details['temp_location']) &&
   !empty($user_details['temp_location_expires']) &&
   strtotime($user_details['temp_location_expires']) > time()) {
    $effective_location = $user_details['temp_location'];
}

// Get location filter from GET parameters
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';
$view_all_locations = isset($_GET['view_all_locations']) ? $_GET['view_all_locations'] === 'true' : false;

// Build WHERE clause
$where = [];
$params = [];

// Location filter - restrict to user's location if they don't have all locations permission
if(!empty($location_filter) && $location_filter !== 'all') {
    if($can_view_all_locations || $view_all_locations) {
        $where[] = "location = ?";
        $params[] = $location_filter;
    } else {
        // If user doesn't have permission to view all locations, only allow filtering on their location
        if($location_filter == $effective_location) {
            $where[] = "location = ?";
            $params[] = $location_filter;
        } else {
            // If they try to filter by a location they don't have access to, return empty results
            $where[] = "1=0"; // This will result in no records
        }
    }
} else {
    // If no specific location filter, apply location restriction based on permissions
    if(!($can_view_all_locations || $view_all_locations)) {
        $where[] = "location = ?";
        $params[] = $effective_location;
    }
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get records
$query = "SELECT id, preprinted_code, tracking_code, item_name, category, `condition`, item_source, status, purchase_price, estimated_sale_price, location, customer_name, customer_contact, detailed_condition, acquisition_date, notes FROM second_hand_items $where_clause ORDER BY created_at DESC";

$records = $DB->query($query, $params);

header('Content-Type: application/json');
echo json_encode(['items' => $records]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>