<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();
require_once 'bootstrap.php';
ob_end_clean();

$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

// Only admin can manage user locations
if (empty($user_details) || $user_details['admin'] < 1) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_user_locations':
        $users = $DB->query("
            SELECT 
                id,
                username,
                email,
                first_name,
                last_name,
                enabled,
                user_location,
                temp_location,
                temp_location_expires
            FROM users 
            ORDER BY username ASC
        ");
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;
        
    case 'get_assignment_history':
        $history = $DB->query("
            SELECT 
                h.*,
                u.username,
                ab.username as assigned_by_username
            FROM user_location_history h
            LEFT JOIN users u ON h.user_id = u.id
            LEFT JOIN users ab ON h.assigned_by = ab.id
            WHERE h.is_active = 1
            ORDER BY h.assigned_date DESC
            LIMIT 50
        ");
        
        echo json_encode(['success' => true, 'history' => $history]);
        break;
        
    case 'assign_location':
        $target_user_id = $_POST['user_id'] ?? '';
        $location = $_POST['location'] ?? '';
        $is_temporary = $_POST['is_temporary'] === 'true';
        $expires_hours = intval($_POST['expires_hours'] ?? 24);
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($target_user_id) || !in_array($location, ['cs', 'as'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID or location']);
            break;
        }
        
        try {
            $DB->beginTransaction();
            
            if ($is_temporary) {
                // Set temporary location
                $expires_date = date('Y-m-d H:i:s', strtotime("+{$expires_hours} hours"));
                $DB->query("
                    UPDATE users 
                    SET temp_location = ?, temp_location_expires = ? 
                    WHERE id = ?
                ", [$location, $expires_date, $target_user_id]);
                
                // Log the assignment
                $DB->query("
                    INSERT INTO user_location_history (user_id, location, assignment_type, assigned_by, expires_date, notes)
                    VALUES (?, ?, 'temporary', ?, ?, ?)
                ", [$target_user_id, $location, $user_id, $expires_date, $notes]);
            } else {
                // Set permanent location and clear any temporary assignment
                $DB->query("
                    UPDATE users 
                    SET user_location = ?, temp_location = NULL, temp_location_expires = NULL 
                    WHERE id = ?
                ", [$location, $target_user_id]);
                
                // Log the assignment
                $DB->query("
                    INSERT INTO user_location_history (user_id, location, assignment_type, assigned_by, notes)
                    VALUES (?, ?, 'permanent', ?, ?)
                ", [$target_user_id, $location, $user_id, $notes]);
            }
            
            $DB->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $DB->rollback();
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'clear_temporary':
        $target_user_id = $_POST['user_id'] ?? '';
        
        if (empty($target_user_id)) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            break;
        }
        
        try {
            $DB->beginTransaction();
            
            // Clear temporary assignment
            $DB->query("
                UPDATE users 
                SET temp_location = NULL, temp_location_expires = NULL 
                WHERE id = ?
            ", [$target_user_id]);
            
            // Log the action
            $DB->query("
                INSERT INTO user_location_history (user_id, location, assignment_type, assigned_by, notes)
                VALUES (?, '', 'temporary_cleared', ?, 'Temporary assignment cleared by admin')
            ", [$target_user_id, $user_id]);
            
            $DB->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $DB->rollback();
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_user_effective_location':
        $target_user_id = $_POST['user_id'] ?? '';
        
        if (empty($target_user_id)) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            break;
        }
        
        $user = $DB->query("
            SELECT user_location, temp_location, temp_location_expires 
            FROM users 
            WHERE id = ?
        ", [$target_user_id]);
        
        if (empty($user)) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            break;
        }
        
        $user_data = $user[0];
        $effective_location = null;
        
        // Check if temporary location is active
        if (!empty($user_data['temp_location']) && 
            !empty($user_data['temp_location_expires']) && 
            strtotime($user_data['temp_location_expires']) > time()) {
            $effective_location = $user_data['temp_location'];
        } else {
            $effective_location = $user_data['user_location'];
        }
        
        echo json_encode([
            'success' => true, 
            'effective_location' => $effective_location,
            'permanent_location' => $user_data['user_location'],
            'temp_location' => $user_data['temp_location'],
            'temp_expires' => $user_data['temp_location_expires']
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>