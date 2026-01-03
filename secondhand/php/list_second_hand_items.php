<?php
/**
 * List Second Hand Items API
 * Returns items based on location, status, and search filters
 */

require '../../php/bootstrap.php';

try {
    // Ensure user is logged in
    if (!isset($_SESSION['dins_user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $user_id = $_SESSION['dins_user_id'];
    $user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
    
    // Determine effective location
    $effective_location = $user_details['user_location'];
    if(!empty($user_details['temp_location']) &&
       !empty($user_details['temp_location_expires']) &&
       strtotime($user_details['temp_location_expires']) > time()) {
        $effective_location = $user_details['temp_location'];
    }

    // Check permissions
    $can_view_all_locations_check = $DB->query(
        "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View All Locations'",
        [$user_id]
    );
    $can_view_all_locations = !empty($can_view_all_locations_check) && $can_view_all_locations_check[0]['has_access'];
    
    // Get filter parameters
    $location_filter = isset($_GET['location']) ? trim($_GET['location']) : $effective_location;
    $view_all_locations = isset($_GET['view_all_locations']) && $_GET['view_all_locations'] === 'true';

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
                $where[] = "1=0";
            }
        }
    } else {
        // If viewing all locations is requested but user doesn't have permission
        if($view_all_locations && !$can_view_all_locations) {
            $where[] = "location = ?";
            $params[] = $effective_location;
        }
        // Otherwise, if they have permission, no location filter needed (show all)
    }

    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get records - sorted by most recent first
    $query = "SELECT 
        id, 
        preprinted_code, 
        tracking_code, 
        item_name, 
        category, 
        `condition`, 
        item_source, 
        status, 
        purchase_price, 
        estimated_sale_price, 
        estimated_value,
        selling_price,
        lowest_price,
        location,
        customer_id, 
        customer_name, 
        customer_contact, 
        detailed_condition, 
        acquisition_date, 
        notes,
        brand,
        model_number,
        serial_number,
        warranty_info,
        supplier_info,
        created_at,
        updated_at
    FROM second_hand_items 
    $where_clause 
    ORDER BY created_at DESC, id DESC";

    $records = $DB->query($query, $params);

    // Format dates for display
    foreach ($records as &$record) {
        if ($record['acquisition_date']) {
            $record['acquisition_date_formatted'] = date('d/m/Y', strtotime($record['acquisition_date']));
        }
        if ($record['created_at']) {
            $record['created_at_formatted'] = date('d/m/Y H:i', strtotime($record['created_at']));
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'items' => $records,
        'count' => count($records)
    ]);

} catch (Exception $e) {
    error_log("Error in list_second_hand_items.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
