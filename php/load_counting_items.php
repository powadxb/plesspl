<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent any output before this point
ob_start();
require_once 'bootstrap.php';
// Clean any unwanted output
ob_end_clean();

$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

if (empty($user_details)) {
    echo '<p>Access denied</p>';
    exit();
}

$is_admin = $user_details['admin'] >= 1;

// Helper function to get user's effective location (same as in manage_count_queue.php)
function getUserEffectiveLocation($user_id, $DB) {
    $user = $DB->query("
        SELECT user_location, temp_location, temp_location_expires 
        FROM users 
        WHERE id = ?
    ", [$user_id]);
    
    if (empty($user)) {
        return null;
    }
    
    $user_data = $user[0];
    
    // Check if temporary location is active
    if (!empty($user_data['temp_location']) && 
        !empty($user_data['temp_location_expires']) && 
        strtotime($user_data['temp_location_expires']) > time()) {
        return $user_data['temp_location'];
    }
    
    return $user_data['user_location'];
}

// Get user's effective location
$user_location = getUserEffectiveLocation($user_id, $DB);

// Get session_id from request (if admin selected a specific session)
$selected_session_id = $_GET['session_id'] ?? '';

// Build the query based on whether a session is selected
if (!empty($selected_session_id)) {
    // Load items from specific session - with location check for non-admins
    if ($is_admin) {
        // Admin can see items from any session
        $pending_items = $DB->query("
            SELECT 
                q.sku,
                q.status,
                q.session_id,
                mp.name,
                mp.ean,
                mp.manufacturer,
                mp.pos_category,
                s.name as session_name,
                s.location as session_location
            FROM stock_count_queue q
            LEFT JOIN master_products mp ON q.sku = mp.sku
            LEFT JOIN stock_count_sessions s ON q.session_id = s.id
            WHERE q.status = 'pending' AND q.session_id = ?
            ORDER BY q.added_date ASC
        ", [$selected_session_id]);
    } else {
        // Non-admin can only see items from sessions matching their location
        if (empty($user_location)) {
            $pending_items = [];
        } else {
            $pending_items = $DB->query("
                SELECT 
                    q.sku,
                    q.status,
                    q.session_id,
                    mp.name,
                    mp.ean,
                    mp.manufacturer,
                    mp.pos_category,
                    s.name as session_name,
                    s.location as session_location
                FROM stock_count_queue q
                LEFT JOIN master_products mp ON q.sku = mp.sku
                LEFT JOIN stock_count_sessions s ON q.session_id = s.id
                WHERE q.status = 'pending' AND q.session_id = ? AND s.location = ?
                ORDER BY q.added_date ASC
            ", [$selected_session_id, $user_location]);
        }
    }
    
    // Get completed counts for this session - with location filtering for non-admins
    if ($is_admin) {
        // Full details for admin with CORRECT variance calculation
        $completed_counts = $DB->query("
            SELECT 
                ce.sku,
                ce.counted_stock,
                ce.system_cs_stock,
                ce.system_as_stock,
                ce.count_date,
                ce.session_id,
                ce.target_location,
                ce.variance_amount,
                ce.system_stock_used,
                mp.name,
                mp.ean,
                mp.manufacturer,
                mp.pos_category,
                mp.cost,
                u.username as counted_by,
                s.name as session_name,
                s.location as session_location,
                (ce.variance_amount * mp.cost) as variance_value
            FROM stock_count_entries ce
            LEFT JOIN master_products mp ON ce.sku = mp.sku
            LEFT JOIN users u ON ce.counted_by_user_id = u.id
            LEFT JOIN stock_count_sessions s ON ce.session_id = s.id
            WHERE ce.session_id = ?
            ORDER BY ce.count_date DESC
            LIMIT 50
        ", [$selected_session_id]);
    } else {
        // Simplified view for staff - only their own counts from sessions matching their location
        if (empty($user_location)) {
            $completed_counts = [];
        } else {
            $completed_counts = $DB->query("
                SELECT 
                    ce.sku,
                    ce.counted_stock,
                    ce.count_date,
                    ce.session_id,
                    mp.name,
                    mp.ean,
                    mp.pos_category,
                    u.username as counted_by,
                    s.location as session_location
                FROM stock_count_entries ce
                LEFT JOIN master_products mp ON ce.sku = mp.sku
                LEFT JOIN users u ON ce.counted_by_user_id = u.id
                LEFT JOIN stock_count_sessions s ON ce.session_id = s.id
                WHERE ce.session_id = ? AND ce.counted_by_user_id = ? AND s.location = ?
                ORDER BY ce.count_date DESC
                LIMIT 20
            ", [$selected_session_id, $user_id, $user_location]);
        }
    }
} else {
    // Load items from all active sessions - with location filtering for non-admins
    if ($is_admin) {
        // Admin sees all items from all active sessions
        $pending_items = $DB->query("
            SELECT 
                q.sku,
                q.status,
                q.session_id,
                mp.name,
                mp.ean,
                mp.manufacturer,
                mp.pos_category,
                s.name as session_name,
                s.location as session_location
            FROM stock_count_queue q
            LEFT JOIN master_products mp ON q.sku = mp.sku
            LEFT JOIN stock_count_sessions s ON q.session_id = s.id
            WHERE q.status = 'pending' AND s.status = 'active'
            ORDER BY q.added_date ASC
        ");
    } else {
        // Non-admin only sees items from active sessions matching their location
        if (empty($user_location)) {
            $pending_items = [];
        } else {
            $pending_items = $DB->query("
                SELECT 
                    q.sku,
                    q.status,
                    q.session_id,
                    mp.name,
                    mp.ean,
                    mp.manufacturer,
                    mp.pos_category,
                    s.name as session_name,
                    s.location as session_location
                FROM stock_count_queue q
                LEFT JOIN master_products mp ON q.sku = mp.sku
                LEFT JOIN stock_count_sessions s ON q.session_id = s.id
                WHERE q.status = 'pending' AND s.status = 'active' AND s.location = ?
                ORDER BY q.added_date ASC
            ", [$user_location]);
        }
    }
    
    // Get completed counts from all active sessions - with location filtering for non-admins
    if ($is_admin) {
        // Full details for admin with CORRECT variance calculation
        $completed_counts = $DB->query("
            SELECT 
                ce.sku,
                ce.counted_stock,
                ce.system_cs_stock,
                ce.system_as_stock,
                ce.count_date,
                ce.session_id,
                ce.target_location,
                ce.variance_amount,
                ce.system_stock_used,
                mp.name,
                mp.ean,
                mp.manufacturer,
                mp.pos_category,
                mp.cost,
                u.username as counted_by,
                s.name as session_name,
                s.location as session_location,
                (ce.variance_amount * mp.cost) as variance_value
            FROM stock_count_entries ce
            LEFT JOIN master_products mp ON ce.sku = mp.sku
            LEFT JOIN users u ON ce.counted_by_user_id = u.id
            LEFT JOIN stock_count_sessions s ON ce.session_id = s.id
            WHERE s.status = 'active'
            ORDER BY ce.count_date DESC
            LIMIT 50
        ");
    } else {
        // Simplified view for staff - only their own counts from active sessions matching their location
        if (empty($user_location)) {
            $completed_counts = [];
        } else {
            $completed_counts = $DB->query("
                SELECT 
                    ce.sku,
                    ce.counted_stock,
                    ce.count_date,
                    ce.session_id,
                    mp.name,
                    mp.ean,
                    mp.pos_category,
                    u.username as counted_by,
                    s.location as session_location
                FROM stock_count_entries ce
                LEFT JOIN master_products mp ON ce.sku = mp.sku
                LEFT JOIN users u ON ce.counted_by_user_id = u.id
                LEFT JOIN stock_count_sessions s ON ce.session_id = s.id
                WHERE s.status = 'active' AND ce.counted_by_user_id = ? AND s.location = ?
                ORDER BY ce.count_date DESC
                LIMIT 20
            ", [$user_id, $user_location]);
        }
    }
}

// Show appropriate message if no items found
if (empty($pending_items) && empty($completed_counts)) {
    if ($is_admin) {
        echo '<p class="text-muted">No items pending count. Add items from <a href="zindex.php">Stock View</a>.</p>';
    } else {
        if (empty($user_location)) {
            echo '<p class="text-muted">Your location is not assigned. Contact an admin to assign your location before you can count stock.</p>';
        } else {
            echo '<p class="text-muted">No items assigned for counting at your location (' . strtoupper($user_location) . '). Please select an active session above or wait for items to be added.</p>';
        }
    }
    exit();
}
?>

<?php if (!empty($pending_items)): ?>
<h5 style="margin: 0 0 0.25rem 0; font-size: 0.8rem; font-weight: 600;">Items to Count (<?= count($pending_items) ?> pending)</h5>

<table class="table" style="margin-bottom: 1rem;">
    <thead>
        <tr>
            <th>SKU</th>
            <th>EAN/Barcode</th>
            <th>Product Name</th>
            <th>Manufacturer</th>
            <th>Category</th>
            <?php if ($is_admin && empty($selected_session_id)): ?>
            <th>Session</th>
            <?php endif; ?>
            <th>Session Location</th>
            <th>Your Count</th>
            <th>Action</th>
            <?php if ($is_admin): ?>
            <th>Status</th>
            <th>Admin</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pending_items as $item): ?>
        <tr id="pending-row-<?= htmlspecialchars($item['sku']) ?>">
            <td><strong><?= htmlspecialchars($item['sku']) ?></strong></td>
            <td><span style="font-family: monospace; font-size: 0.7rem;"><?= htmlspecialchars($item['ean']) ?></span></td>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= htmlspecialchars($item['manufacturer']) ?></td>
            <td><?= htmlspecialchars($item['pos_category']) ?></td>
            <?php if ($is_admin && empty($selected_session_id)): ?>
            <td><small><?= htmlspecialchars($item['session_name']) ?></small></td>
            <?php endif; ?>
            <td>
                <span class="location-badge location-<?= $item['session_location'] ?>">
                    <?= strtoupper($item['session_location']) ?>
                </span>
            </td>
            <td>
                <form class="count-form" style="display: inline-block;">
                    <input type="hidden" name="sku" value="<?= $item['sku'] ?>">
                    <input type="hidden" name="session_id" value="<?= $item['session_id'] ?>">
                    <input type="number" 
                           name="counted_stock" 
                           class="count-input" 
                           placeholder="0" 
                           min="0" 
                           step="1" 
                           required>
                </form>
            </td>
            <td>
                <button type="submit" 
                        class="btn btn-success btn-sm submit-count" 
                        data-sku="<?= $item['sku'] ?>"
                        data-session-id="<?= $item['session_id'] ?>">
                    Submit Count
                </button>
            </td>
            <?php if ($is_admin): ?>
            <td>
                <span class="badge badge-warning">
                    <?= ucfirst($item['status']) ?>
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-danger remove-from-queue" 
                        data-sku="<?= $item['sku'] ?>"
                        data-session-id="<?= $item['session_id'] ?>">
                    Remove
                </button>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($completed_counts)): ?>
<h5 style="margin: 1rem 0 0.25rem 0; font-size: 0.8rem; font-weight: 600;">
    <?php if ($is_admin): ?>
    Completed Counts (<?= count($completed_counts) ?> items)
    <?php else: ?>
    Your Recent Counts (<?= count($completed_counts) ?> items)
    <?php endif; ?>
</h5>

<table class="table">
    <thead>
        <tr>
            <th>SKU</th>
            <th>EAN/Barcode</th>
            <th>Product Name</th>
            <th>Category</th>
            <?php if ($is_admin && empty($selected_session_id)): ?>
            <th>Session</th>
            <?php endif; ?>
            <th>Location</th>
            <?php if ($is_admin): ?>
            <th>System Stock<br><small>(Target Location)</small></th>
            <th>CS Stock</th>
            <th>AS Stock</th>
            <?php endif; ?>
            <th>Counted</th>
            <?php if ($is_admin): ?>
            <th>Variance<br><small>(vs Target)</small></th>
            <th>Cost</th>
            <th>Value Impact</th>
            <?php endif; ?>
            <th>Counted By</th>
            <th>Date</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody id="completedCountsTable">
        <?php foreach ($completed_counts as $result): ?>
        <?php if ($is_admin): ?>
        <?php 
        // Use the stored variance_amount and system_stock_used for accurate location-specific variance
        $variance = floatval($result['variance_amount']);
        $target_stock = floatval($result['system_stock_used']);
        $variance_value = floatval($result['variance_value']);
        
        $variance_class = '';
        if ($variance > 0) $variance_class = 'style="background-color: #fef3c7;"'; // Yellow for overage
        else if ($variance < 0) $variance_class = 'style="background-color: #fecaca;"'; // Red for shortage
        else $variance_class = 'style="background-color: #d1fae5;"'; // Green for exact match
        ?>
        <tr <?= $variance_class ?>>
            <td><strong><?= htmlspecialchars($result['sku']) ?></strong></td>
            <td><span style="font-family: monospace; font-size: 0.7rem;"><?= htmlspecialchars($result['ean']) ?></span></td>
            <td><?= htmlspecialchars($result['name']) ?></td>
            <td><?= htmlspecialchars($result['pos_category']) ?></td>
            <?php if (empty($selected_session_id)): ?>
            <td><small><?= htmlspecialchars($result['session_name']) ?></small></td>
            <?php endif; ?>
            <td>
                <span class="location-badge location-<?= $result['session_location'] ?>">
                    <?= strtoupper($result['session_location']) ?>
                </span>
            </td>
            <td><strong><?= number_format($target_stock) ?></strong></td>
            <td><?= number_format($result['system_cs_stock']) ?></td>
            <td><?= number_format($result['system_as_stock']) ?></td>
            <td><strong><?= number_format($result['counted_stock']) ?></strong></td>
            <td><strong <?= $variance != 0 ? 'style="color: ' . ($variance > 0 ? '#d97706' : '#dc2626') . ';"' : '' ?>>
                <?= $variance > 0 ? '+' : '' ?><?= number_format($variance) ?>
            </strong></td>
            <td>£<?= number_format($result['cost'], 2) ?></td>
            <td><strong <?= $variance_value != 0 ? 'style="color: ' . ($variance_value > 0 ? '#d97706' : '#dc2626') . ';"' : '' ?>>
                £<?= $variance_value > 0 ? '+' : '' ?><?= number_format($variance_value, 2) ?>
            </strong></td>
            <td><?= htmlspecialchars($result['counted_by']) ?></td>
            <td><?= date('M j, H:i', strtotime($result['count_date'])) ?></td>
            <td>
                <button class="btn btn-sm btn-warning delete-and-recount" 
                        data-sku="<?= $result['sku'] ?>"
                        data-session-id="<?= $result['session_id'] ?>"
                        data-counted-by="<?= htmlspecialchars($result['counted_by']) ?>"
                        data-count="<?= $result['counted_stock'] ?>">
                    <i class="fas fa-undo"></i> Delete & Recount
                </button>
            </td>
        </tr>
        <?php else: ?>
        <!-- Simplified view for staff -->
        <tr>
            <td><strong><?= htmlspecialchars($result['sku']) ?></strong></td>
            <td><span style="font-family: monospace; font-size: 0.7rem;"><?= htmlspecialchars($result['ean']) ?></span></td>
            <td><?= htmlspecialchars($result['name']) ?></td>
            <td><?= htmlspecialchars($result['pos_category']) ?></td>
            <td>
                <span class="location-badge location-<?= $result['session_location'] ?>">
                    <?= strtoupper($result['session_location']) ?>
                </span>
            </td>
            <td><strong><?= number_format($result['counted_stock']) ?></strong></td>
            <td><?= htmlspecialchars($result['counted_by']) ?></td>
            <td><?= date('M j, H:i', strtotime($result['count_date'])) ?></td>
            <td>
                <?php
                // Check if current user can recount this item (only their own counts)
                $can_recount = false;
                $current_user = $DB->query("SELECT username FROM users WHERE id = ?", [$user_id])[0] ?? [];
                if (!empty($current_user) && $current_user['username'] === $result['counted_by']) {
                    $can_recount = true;
                }
                ?>
                <?php if ($can_recount): ?>
                <button class="btn btn-sm btn-warning delete-and-recount" 
                        data-sku="<?= $result['sku'] ?>"
                        data-session-id="<?= $result['session_id'] ?>"
                        data-counted-by="<?= htmlspecialchars($result['counted_by']) ?>"
                        data-count="<?= $result['counted_stock'] ?>">
                    <i class="fas fa-undo"></i> Recount
                </button>
                <?php else: ?>
                <span class="text-muted" style="font-size: 0.65rem;">-</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<style>
.badge {
    padding: 0.125rem 0.25rem;
    font-size: 0.65rem;
    border-radius: 0.125rem;
    color: white;
    font-weight: 500;
}
.badge-warning { background: #f59e0b; }
.badge-info { background: #3b82f6; }
.badge-success { background: #10b981; }

.location-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.location-cs {
    background: #dbeafe;
    color: #1e40af;
}

.location-as {
    background: #d1fae5;
    color: #065f46;
}

.btn-sm {
    padding: 0.125rem 0.375rem;
    font-size: 0.65rem;
    height: 24px;
    line-height: 1.2;
}
.table th:nth-child(1) { width: 60px; }   /* SKU */
.table th:nth-child(2) { width: 120px; }  /* EAN/Barcode */
.table th:nth-child(3) { width: 180px; }  /* Product Name */
.table th:nth-child(4) { width: 100px; }  /* Manufacturer */
.table th:nth-child(5) { width: 100px; }  /* Category */

/* Fade out animation for removed rows */
.fade-out {
    opacity: 0;
    transition: opacity 0.5s ease-out;
}
</style>