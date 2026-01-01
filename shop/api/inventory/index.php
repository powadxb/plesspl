// Inventory API endpoints (api/inventory/index.php)
require_once '../core/config.php';
require_once '../core/ApiAuth.php';
require_once '../core/ApiResponse.php';

try {
    $apiAuth = new ApiAuth(getDB());
    $user = $apiAuth->authenticateRequest();

    if (!$auth->hasPermission('manager')) {
        ApiResponse::error('Unauthorized', 403);
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $inventoryManager = new InventoryManager(getDB());

            if (isset($_GET['id'])) {
                // Get single part details
                $part = $inventoryManager->getPartDetails($_GET['id']);
                if (!$part) {
                    ApiResponse::error('Part not found', 404);
                }
                ApiResponse::send($part);
            } else {
                // List inventory
                $inventory = $inventoryManager->getInventoryByLocation($user['location_id']);
                ApiResponse::send($inventory);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $inventoryManager = new InventoryManager(getDB());
            $success = $inventoryManager->addPart($input);

            if (!$success) {
                ApiResponse::error('Failed to add part', 500);
            }
            ApiResponse::send(['message' => 'Part added successfully'], 201);
            break;

        case 'PUT':
            if (!isset($_GET['id'])) {
                ApiResponse::error('Part ID required', 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $inventoryManager = new InventoryManager(getDB());
            $success = $inventoryManager->updateStock(
                $_GET['id'],
                $input['quantity_change'],
                $user['id']
            );

            if (!$success) {
                ApiResponse::error('Failed to update stock', 500);
            }
            ApiResponse::send(['message' => 'Stock updated successfully']);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), $e->getCode() ?: 500);
}
