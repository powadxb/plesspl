// Tickets API endpoints (api/tickets/index.php)
require_once '../core/config.php';
require_once '../core/ApiAuth.php';
require_once '../core/ApiResponse.php';

try {
    $apiAuth = new ApiAuth(getDB());
    $user = $apiAuth->authenticateRequest();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $ticketManager = new TicketManager(getDB());

            if (isset($_GET['id'])) {
                // Get single ticket
                $ticket = $ticketManager->getTicketDetails($_GET['id']);
                if (!$ticket) {
                    ApiResponse::error('Ticket not found', 404);
                }
                ApiResponse::send($ticket);
            } else {
                // List tickets with filtering
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'priority' => $_GET['priority'] ?? null,
                    'assigned_to' => $_GET['assigned_to'] ?? null,
                    'location_id' => $user['location_id']
                ];
                $tickets = $ticketManager->getTicketsByFilters($filters);
                ApiResponse::send($tickets);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $ticketManager = new TicketManager(getDB());
            $ticketId = $ticketManager->createTicket($input['customer'], $input['ticket']);

            if (!$ticketId) {
                ApiResponse::error('Failed to create ticket', 500);
            }
            ApiResponse::send(['ticket_id' => $ticketId], 201);
            break;

        case 'PUT':
            if (!isset($_GET['id'])) {
                ApiResponse::error('Ticket ID required', 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $ticketManager = new TicketManager(getDB());
            $success = $ticketManager->updateTicketStatus($_GET['id'], $input['status'], $user['id']);

            if (!$success) {
                ApiResponse::error('Failed to update ticket', 500);
            }
            ApiResponse::send(['message' => 'Ticket updated successfully']);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), $e->getCode() ?: 500);
}
