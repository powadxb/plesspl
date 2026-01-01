// Photo upload API endpoint (api/photos/upload.php)
require_once '../core/config.php';
require_once '../core/ApiAuth.php';
require_once '../core/ApiResponse.php';

try {
    $apiAuth = new ApiAuth(getDB());
    $user = $apiAuth->authenticateRequest();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('Method not allowed', 405);
    }

    if (!isset($_FILES['photo']) || !isset($_POST['ticket_id'])) {
        ApiResponse::error('Missing required fields', 400);
    }

    $ticketManager = new TicketManager(getDB());
    $success = $ticketManager->uploadPhoto($_POST['ticket_id'], $_FILES['photo'], $user['id']);

    if (!$success) {
        ApiResponse::error('Failed to upload photo', 500);
    }

    ApiResponse::send(['message' => 'Photo uploaded successfully']);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), $e->getCode() ?: 500);
}

// Add API tokens table
$sql = "
CREATE TABLE api_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

// Example API usage (JavaScript)
const api = {
    baseUrl: 'https://your-domain.com/api',
    token: null,

    async login(username, password) {
        const response = await fetch(`${this.baseUrl}/auth/login.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();
        if (data.status === 'success') {
            this.token = data.data.token;
            localStorage.setItem('api_token', this.token);
            return true;
        }
        throw new Error(data.message);
    },

    async getTickets(filters = {}) {
        const queryString = new URLSearchParams(filters).toString();
        const response = await fetch(`${this.baseUrl}/tickets/index.php?${queryString}`, {
            headers: {
                'Authorization': `Bearer ${this.token}`
            }
        });

        const data = await response.json();
        if (data.status === 'success') {
            return data.data;
        }
        throw new Error(data.message);
    },

    async createTicket(ticketData) {
        const response = await fetch(`${this.baseUrl}/tickets/index.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(ticketData)
        });

        const data = await response.json();
        if (data.status === 'success') {
            return data.data;
        }
        throw new Error(data.message);
    }
};
