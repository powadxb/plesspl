<?php
// inventory.php
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit();
}

// Only managers and admins can manage inventory
if (!$auth->hasPermission('manager')) {
    header('Location: dashboard.php');
    exit();
}

try {
    $db = getDB();
    
    // Get all inventory items for current location
    $inventory_query = $db->prepare("
        SELECT 
            p.*,
            COUNT(pu.id) as times_used,
            COALESCE(SUM(CASE WHEN pu.used_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) 
                            THEN 1 ELSE 0 END), 0) as used_last_30_days
        FROM parts_inventory p
        LEFT JOIN parts_used pu ON p.id = pu.part_id
        WHERE p.location_id = ?
        GROUP BY p.id
        ORDER BY p.name ASC
    ");
    $inventory_query->execute([$_SESSION['location_id']]);
    $inventory = $inventory_query->fetchAll(PDO::FETCH_ASSOC);

    // Get low stock items
    $low_stock_query = $db->prepare("
        SELECT * FROM parts_inventory 
        WHERE location_id = ? 
        AND quantity <= minimum_quantity
        ORDER BY quantity ASC
    ");
    $low_stock_query->execute([$_SESSION['location_id']]);
    $low_stock = $low_stock_query->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Inventory query error: " . $e->getMessage());
    $error = "Error loading inventory data";
}

// Handle stock updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_stock':
                    $stmt = $db->prepare("
                        UPDATE parts_inventory 
                        SET quantity = quantity + ?, 
                            updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmt->execute([$_POST['quantity'], $_POST['part_id']]);
                    $success = "Stock updated successfully";
                    break;

                case 'add_part':
                    $stmt = $db->prepare("
                        INSERT INTO parts_inventory 
                        (name, description, sku, quantity, minimum_quantity, location_id)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['sku'],
                        $_POST['quantity'],
                        $_POST['minimum_quantity'],
                        $_SESSION['location_id']
                    ]);
                    $success = "New part added successfully";
                    break;
            }
            // Refresh page to show updated data
            header('Location: inventory.php?success=' . urlencode($success));
            exit();
        }
    } catch(PDOException $e) {
        error_log("Inventory update error: " . $e->getMessage());
        $error = "Error updating inventory";
    }
}
?>

<?php include 'header.php'; ?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Inventory Management</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
            Add New Part
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>

    <?php if (!empty($low_stock)): ?>
    <div class="alert alert-warning">
        <h5>Low Stock Alert</h5>
        <ul class="mb-0">
            <?php foreach ($low_stock as $item): ?>
            <li>
                <?= htmlspecialchars($item['name']) ?> - 
                Current stock: <?= $item['quantity'] ?> 
                (Minimum: <?= $item['minimum_quantity'] ?>)
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
                            <th>In Stock</th>
                            <th>Min. Quantity</th>
                            <th>Usage (30 days)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                        <tr class="<?= $item['quantity'] <= $item['minimum_quantity'] ? 'table-warning' : '' ?>">
                            <td><?= htmlspecialchars($item['sku']) ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['description']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= $item['minimum_quantity'] ?></td>
                            <td><?= $item['used_last_30_days'] ?></td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#updateStockModal"
                                        data-part-id="<?= $item['id'] ?>"
                                        data-part-name="<?= htmlspecialchars($item['name']) ?>">
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
                <input type="hidden" name="action" value="add_stock">
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
                            <input type="number" class="form-control" name="quantity" id="quantityChange" value="0" required>
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

<?php include 'footer.php'; ?>