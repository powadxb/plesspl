<?php
// API endpoint for updating ticket status (api/update_ticket_status.php)
require_once '../config.php';
require_once '../auth.php';
require_once '../ticket.php';
$_SESSION['user_id']

header('Content-Type: application/json');

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['ticket_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$ticketManager = new TicketManager(getDB());
$success = $ticketManager->updateTicketStatus(
    $input['ticket_id'],
    $input['status'],
);

if ($success) {
    // Send notification if status is "ready"
    if ($input['status'] === 'ready') {
        $notificationManager = new NotificationManager(getDB());
        $notificationManager->notifyCustomer($input['ticket_id'], 'ready_pickup');
    }

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update ticket status']);
}
