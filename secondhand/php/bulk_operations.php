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
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_status':
            $ids = $_POST['ids'] ?? [];
            $new_status = $_POST['status'] ?? '';
            
            if (empty($ids) || empty($new_status)) {
                throw new Exception('IDs and status are required');
            }
            
            // Validate status
            $valid_statuses = ['in_stock', 'sold', 'repair_needed', 'for_parts', 'damaged'];
            if (!in_array($new_status, $valid_statuses)) {
                throw new Exception('Invalid status');
            }
            
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query = "UPDATE second_hand_items SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$new_status], $ids);
            
            $DB->query($query, $params);
            
            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' items updated successfully'
            ]);
            break;
            
        case 'update_location':
            $ids = $_POST['ids'] ?? [];
            $new_location = $_POST['location'] ?? '';
            
            if (empty($ids) || empty($new_location)) {
                throw new Exception('IDs and location are required');
            }
            
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query = "UPDATE second_hand_items SET location = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$new_location], $ids);
            
            $DB->query($query, $params);
            
            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' items location updated successfully'
            ]);
            break;
            
        case 'delete_items':
            $ids = $_POST['ids'] ?? [];
            
            if (empty($ids)) {
                throw new Exception('IDs are required');
            }
            
            // Check if items can be deleted (e.g., not sold)
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $check_query = "SELECT id, status FROM second_hand_items WHERE id IN ($placeholders)";
            $items = $DB->query($check_query, $ids);
            
            $deletable_ids = [];
            $undeletable = [];
            
            foreach ($items as $item) {
                if ($item['status'] === 'sold') {
                    $undeletable[] = $item['id'];
                } else {
                    $deletable_ids[] = $item['id'];
                }
            }
            
            if (!empty($deletable_ids)) {
                $placeholders = str_repeat('?,', count($deletable_ids) - 1) . '?';
                $delete_query = "DELETE FROM second_hand_items WHERE id IN ($placeholders)";
                $DB->query($delete_query, $deletable_ids);
            }
            
            $result_message = count($deletable_ids) . ' items deleted';
            if (!empty($undeletable)) {
                $result_message .= ', ' . count($undeletable) . ' items could not be deleted (already sold)';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $result_message,
                'deleted_count' => count($deletable_ids),
                'undeletable' => $undeletable
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