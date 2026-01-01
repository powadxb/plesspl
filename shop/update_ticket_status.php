<?php
// update_ticket_status.php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $ticketId = $_POST['ticket_id'] ?? null;
        $newStatus = $_POST['status'] ?? null;

        if (!$ticketId || !$newStatus) {
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit();
        }

        // Update the ticket status
        $stmt = $db->prepare("
            UPDATE repair_tickets 
            SET 
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $success = $stmt->execute([$newStatus, $ticketId]);

        if ($success) {
            // Log the status change
            $stmt = $db->prepare("
                INSERT INTO ticket_updates 
                (ticket_id, user_id, update_type, content) 
                VALUES (?, ?, 'status_change', ?)
            ");
            
            $stmt->execute([
                $ticketId,
                $_SESSION['user_id'],
                "Status updated to: $newStatus"
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Status updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update status'
            ]);
        }

    } catch (PDOException $e) {
        error_log("Status update error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred'
        ]);
    }
    exit();
}

echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
]);
exit();