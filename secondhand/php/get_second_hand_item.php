<?php
require '../../php/bootstrap.php';

// Check permissions directly from database
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

$view_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View'",
    [$user_id]
);
$has_view_permission = !empty($view_check) && $view_check[0]['has_access'];

if(!$has_view_permission) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Determine user's location for location-based restrictions
$effective_location = $user_details['user_location'];
if(!empty($user_details['temp_location']) &&
   !empty($user_details['temp_location_expires']) &&
   strtotime($user_details['temp_location_expires']) > time()) {
    $effective_location = $user_details['temp_location'];
}

// Check if user can view all locations
$all_locations_check = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View All Locations'",
    [$user_id]
);
$can_view_all_locations = !empty($all_locations_check) && $all_locations_check[0]['has_access'];

// Get the item
$item = $DB->query("SELECT * FROM second_hand_items WHERE id = ?", [$id])[0];

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
    exit;
}

// Check location permissions
if (!$can_view_all_locations && $item['location'] !== $effective_location) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to view this item']);
    exit;
}

// Get associated photos
$photos = $DB->query("
    SELECT id, file_path, file_type, upload_date
    FROM second_hand_item_photos
    WHERE item_id = ?
    ORDER BY upload_date DESC
", [$id]);

$item['photos'] = $photos;

echo json_encode(['success' => true, 'item' => $item]);
?>