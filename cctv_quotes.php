<?php
session_start();
$page_title = 'CCTV Quotes';
require 'php/bootstrap.php';

// Ensure session is active
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check if user has permission
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'cctv_quote'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Check if user is admin
$is_admin = $user_details['admin'] > 0;

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT q.*, u.username as created_by_name 
          FROM cctv_quotation_master q
          LEFT JOIN users u ON q.created_by = u.id
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (q.id = ? OR q.customer_name LIKE ? OR q.customer_email LIKE ? OR q.customer_phone LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $search; // For ID exact match
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($status_filter) {
    $query .= " AND q.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $query .= " AND DATE(q.date_created) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(q.date_created) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY q.date_created DESC LIMIT 100";

$quotes = $DB->query($query, $params);

require 'assets/header.php';
require 'assets/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2><i class="fas fa-video"></i> CCTV Quotes</h2>
        </div>
        <div class="col text-right">
            <a href="cctv_quote.php" class="btn btn-success">
                <i class="fas fa-plus"></i> New CCTV Quote
            </a>
            <a href="pc_quote.php" class="btn btn-secondary">
                <i class="fas fa-desktop"></i> PC Quote
            </a>
        </div>
    </div>

    <!-- Search Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="" class="form-inline">
                <div class="form-group mr-2 mb-2">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by ID, customer name, email, or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           style="min-width: 300px;">
                </div>
                
                <div class="form-group mr-2 mb-2">
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="accepted" <?php echo $status_filter == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="converted" <?php echo $status_filter == 'converted' ? 'selected' : ''; ?>>Converted</option>
                    </select>
                </div>
                
                <div class="form-group mr-2 mb-2">
                    <input type="date" name="date_from" class="form-control" 
                           placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group mr-2 mb-2">
                    <input type="date" name="date_to" class="form-control" 
                           placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary mr-2 mb-2">
                    <i class="fas fa-search"></i> Search
                </button>
                
                <a href="cctv_quotes.php" class="btn btn-secondary mb-2">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($quotes)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    No quotes found. <?php echo $search ? 'Try adjusting your search criteria.' : 'Create your first CCTV quote!'; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotes as $quote): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $quote['id']; ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($quote['customer_name']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($quote['customer_phone']): ?>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($quote['customer_phone']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($quote['customer_email']): ?>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($quote['customer_email']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>£<?php echo number_format($quote['total_price'], 2); ?></strong>
                                    <br><small class="text-muted">(Inc. VAT)</small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $quote['status'] == 'draft' ? 'secondary' : 
                                            ($quote['status'] == 'sent' ? 'info' : 
                                            ($quote['status'] == 'accepted' ? 'success' : 
                                            ($quote['status'] == 'rejected' ? 'danger' : 'warning'))); 
                                    ?>">
                                        <?php echo ucfirst($quote['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($quote['date_created'])); ?>
                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($quote['date_created'])); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($quote['created_by_name']); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_cctv_quote.php?id=<?php echo $quote['id']; ?>" 
                                           class="btn btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="cctv_quote.php?edit=<?php echo $quote['id']; ?>" 
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="printQuote(<?php echo $quote['id']; ?>)" 
                                                class="btn btn-primary" title="Print">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <?php if ($is_admin): ?>
                                        <button onclick="deleteQuote(<?php echo $quote['id']; ?>)" 
                                                class="btn btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <p class="text-muted">
                        Showing <?php echo count($quotes); ?> quote(s)
                        <?php if (count($quotes) >= 100): ?>
                        <br><small>Limited to 100 results. Use search filters to narrow results.</small>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function printQuote(quoteId) {
    window.open('view_cctv_quote.php?id=' + quoteId, '_blank');
}

function deleteQuote(quoteId) {
    Swal.fire({
        title: 'Delete Quote?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/delete_cctv_quote.php',
                method: 'POST',
                data: { quote_id: quoteId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Deleted!', 'Quote has been deleted.', 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Failed to delete quote', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to delete quote', 'error');
                }
            });
        }
    });
}
</script>

<?php require 'assets/footer.php'; ?>