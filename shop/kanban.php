<?php
// kanban.php
require_once 'config.php';
require_once 'auth.php';
require_once 'ticket.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit();
}

$ticketManager = new TicketManager(getDB());
$tickets = $ticketManager->getTicketsByLocation($_SESSION['location_id']);

// Organize tickets by status
$columns = [
    'pending' => ['title' => 'New Tickets', 'tickets' => []],
    'in_progress' => ['title' => 'In Progress', 'tickets' => []],
    'ready' => ['title' => 'Ready for Pickup', 'tickets' => []],
    'completed' => ['title' => 'Completed', 'tickets' => []]
];

foreach ($tickets as $ticket) {
    if (isset($columns[$ticket['status']])) {
        $columns[$ticket['status']]['tickets'][] = $ticket;
    }
}
?>

<?php include 'header.php'; ?>

<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Ticket Board</h2>
        <div>
            <a href="tickets.php" class="btn btn-outline-primary me-2">List View</a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                Create New Ticket
            </button>
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="kanban-board">
        <?php foreach ($columns as $status => $column): ?>
        <div class="kanban-column" id="<?= $status ?>">
            <div class="kanban-column-header">
                <h5><?= htmlspecialchars($column['title']) ?></h5>
                <span class="badge bg-secondary"><?= count($column['tickets']) ?></span>
            </div>
            <div class="kanban-tickets" data-status="<?= $status ?>">
                <?php foreach ($column['tickets'] as $ticket): ?>
                <div class="kanban-ticket" data-ticket-id="<?= $ticket['id'] ?>">
                    <div class="card mb-2">
    <div class="card-body">
        <h6 class="card-title">Ticket #<?= $ticket['ticket_number'] ?></h6>
        <p class="card-text">
            <strong><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></strong><br>
            <?= htmlspecialchars($ticket['device_type']) ?>
        </p>
        <small class="text-muted">
            Created: <?= date('M d, Y', strtotime($ticket['created_at'])) ?>
        </small>
        <div class="mt-2">
            <a href="ticket_details.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-info">View Details</a>
        </div>
    </div>
</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Create Ticket Modal -->
<div class="modal fade" id="createTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="create_ticket.php">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Customer Information -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>

                    <!-- Device Information -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Device Type</label>
                            <select class="form-select" name="device_type" required>
                                <option value="">Select Device Type</option>
                                <option value="laptop">Laptop</option>
                                <option value="desktop">Desktop</option>
                                <option value="phone">Mobile Phone</option>
                                <option value="tablet">Tablet</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issue Description</label>
                        <textarea class="form-control" name="issue_description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
  
.kanban-board {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding: 1rem;
    min-height: calc(100vh - 200px);
}

.kanban-column {
    flex: 1;
    min-width: 300px;
    background: #f8f9fa;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
}

.kanban-column-header {
    padding: 1rem;
    background: #e9ecef;
    border-radius: 4px 4px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.kanban-tickets {
    padding: 1rem;
    flex: 1;
    overflow-y: auto;
    min-height: 200px;
}

.kanban-ticket {
    cursor: move;
}

.kanban-ticket .card {
    transition: transform 0.2s;
}

.kanban-ticket .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.ticket-ghost {
    opacity: 0.5;
}
  .kanban-ticket .card {
    cursor: pointer;
}
.kanban-ticket .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Sortable for each column
    document.querySelectorAll('.kanban-tickets').forEach(column => {
        new Sortable(column, {
            group: 'tickets',
            animation: 150,
            ghostClass: 'ticket-ghost',
            onEnd: function(evt) {
                const ticketId = evt.item.dataset.ticketId;
                const newStatus = evt.to.dataset.status;
                const originalStatus = evt.from.dataset.status;
                
                // Update the badge count for both columns
                updateColumnCount(originalStatus);
                updateColumnCount(newStatus);
                
                // Send update to server
                fetch('update_ticket_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ticket_id=${ticketId}&status=${newStatus}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        // Revert the move if update failed
                        evt.from.appendChild(evt.item);
                        updateColumnCount(originalStatus);
                        updateColumnCount(newStatus);
                        showAlert('Failed to update ticket status', 'danger');
                    } else {
                        showAlert('Ticket status updated successfully', 'success');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Revert the move on error
                    evt.from.appendChild(evt.item);
                    updateColumnCount(originalStatus);
                    updateColumnCount(newStatus);
                    showAlert('Error updating ticket status', 'danger');
                });
            }
        });
    });

    // Function to update column counts
    function updateColumnCount(status) {
        const column = document.querySelector(`#${status}`);
        if (column) {
            const tickets = column.querySelector('.kanban-tickets').children.length;
            column.querySelector('.badge').textContent = tickets;
        }
    }

    // Function to show alerts
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alertDiv.style.zIndex = '1050';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(alertDiv);

        // Remove alert after 3 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }
});
  document.querySelectorAll('.kanban-ticket .card').forEach(card => {
    card.addEventListener('click', (e) => {
        // If we clicked a button, don't navigate
        if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
            return;
        }
        
        const ticketId = card.closest('.kanban-ticket').dataset.ticketId;
        window.location.href = `ticket_details.php?id=${ticketId}`;
    });
});
</script>

<?php include 'footer.php'; ?>