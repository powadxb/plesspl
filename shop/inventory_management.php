<?php
// Inventory management interface (inventory_management.php)
require_once 'config.php';
require_once 'auth.php';
require_once 'inventory.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated() || !$auth->hasPermission('manager')) {
    header('Location: login.php');
    exit();
}

$inventoryManager = new InventoryManager(getDB());
$parts = $inventoryManager->getInventoryByLocation($_SESSION['location_id']);
$lowStockParts = $inventoryManager->getLowStockParts($_SESSION['location_id']);

// Handle search
$searchResults = [];
if (isset($_GET['search'])) {
    $searchResults = $inventoryManager->searchParts($_GET['search'], $_SESSION['location_id']);
}

// Handle new part addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_part':
            $success = $inventoryManager->addPart([
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'sku' => $_POST['sku'],
                'quantity' => $_POST['quantity'],
                'location_id' => $_SESSION['location_id'],
                'minimum_quantity' => $_POST['minimum_quantity']
            ]);
            break;

        case 'update_stock':
            $success = $inventoryManager->updateStock(
                $_POST['part_id'],
                $_POST['quantity_change'],
                $_SESSION['user_id']
            );
            break;
    }

    if ($success) {
        header('Location: inventory_management.php?success=1');
        exit();
    }
}
?>

<?php include 'header.php'; ?>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Inventory Management</h2>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
                Add New Part
            </button>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search"
                               placeholder="Search parts by name, SKU, or description"
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <?php if (!empty($lowStockParts)): ?>
    <div class="alert alert-warning">
        <h5 class="alert-heading">Low Stock Alerts</h5>
        <ul class="mb-0">
            <?php foreach ($lowStockParts as $part): ?>
            <li>
                <?= htmlspecialchars($part['name']) ?> -
                Current stock: <?= $part['quantity'] ?>
                (Minimum: <?= $part['minimum_quantity'] ?>)
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Inventory Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Stock</th>
                            <th>Min. Quantity</th>
                            <th>Usage (30 days)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searchResults ?: $parts as $part): ?>
                        <tr class="<?= $part['quantity'] <= $part['minimum_quantity'] ? 'table-warning' : '' ?>">
                            <td><?= htmlspecialchars($part['sku']) ?></td>
                            <td><?= htmlspecialchars($part['name']) ?></td>
                            <td><?= htmlspecialchars($part['description']) ?></td>
                            <td><?= $part['quantity'] ?></td>
                            <td><?= $part['minimum_quantity'] ?></td>
                            <td><?= $part['used_last_30_days'] ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#updateStockModal"
                                        data-part-id="<?= $part['id'] ?>"
                                        data-part-name="<?= htmlspecialchars($part['name']) ?>">
                                    Update Stock
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Part Modal -->
<div class="modal fade" id="addPartModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_part">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Part</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Part Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" class="form-control" name="sku" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Initial Quantity</label>
                                <input type="number" class="form-control" name="quantity" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Quantity</label>
                                <input type="number" class="form-control" name="minimum_quantity" min="0" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Part</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="part_id" id="updatePartId">
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Updating stock for: <span id="updatePartName"></span></p>
                    <div class="mb-3">
                        <label class="form-label">Quantity Change</label>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary" onclick="adjustQuantity(-1)">-</button>
                            <input type="number" class="form-control" name="quantity_change" id="quantityChange" value="0" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="adjustQuantity(1)">+</button>
                        </div>
                        <small class="form-text text-muted">
                            Use negative numbers to remove stock
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update stock modal functionality
document.getElementById('updateStockModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const partId = button.dataset.partId;
    const partName = button.dataset.partName;

    document.getElementById('updatePartId').value = partId;
    document.getElementById('updatePartName').textContent = partName;
    document.getElementById('quantityChange').value = 0;
});

function adjustQuantity(amount) {
    const input = document.getElementById('quantityChange');
    input.value = parseInt(input.value) + amount;
}
</script>

<?php
// Add inventory logging table
$sql = "
CREATE TABLE inventory_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    part_id INT NOT NULL,
    user_id INT NOT NULL,
    quantity_change INT NOT NULL,
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (part_id) REFERENCES parts_inventory(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
)";
?>
