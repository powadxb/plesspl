<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'php/bootstrap.php';
require_once 'functions.php';

// Ensure session is active
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}


// Fetch user details
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0] ?? null;

if (!$user_details) {
    die("User not found. Please log in again.");
}

// Access control
if (!hasAccess($user_id, 'courier_log')) {
    die("Access Denied.");
}

// Page Title
$page_title = 'Courier Log';

// Include header and navbar
require_once 'assets/header.php';
require_once 'assets/navbar.php';

// Pagination settings
$records_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Fetch filters from GET request
$courier = $_GET['courier'] ?? '';
$from = $_GET['from'] ?? '';
$notes = $_GET['notes'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build the query with filters
$query = "SELECT SQL_CALC_FOUND_ROWS date, time, courier, `from`, num_boxes, notes, created_by 
          FROM courier_logs 
          WHERE 1=1";

$params = [];

if (!empty($courier)) {
    $query .= " AND courier LIKE ?";
    $params[] = "%$courier%";
}
if (!empty($from)) {
    $query .= " AND `from` LIKE ?";
    $params[] = "%$from%";
}
if (!empty($notes)) {
    $query .= " AND notes LIKE ?";
    $params[] = "%$notes%";
}
if (!empty($start_date)) {
    $query .= " AND date >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $query .= " AND date <= ?";
    $params[] = $end_date;
}

$query .= " ORDER BY created_at DESC LIMIT $records_per_page OFFSET $offset";
$courier_logs = $DB->query($query, $params);

// Get total number of records for pagination
$total_records = $DB->query("SELECT FOUND_ROWS() AS total")[0]['total'];
$total_pages = ceil($total_records / $records_per_page);
?>

<div class="page-container3">
    <section class="welcome p-t-20">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h1 class="title-4">Courier Log</h1>
                    <hr class="line-seprate">
                </div>
            </div>
        </div>
    </section>

    <section class="p-t-20">
        <div class="container">
            <!-- Add Data Section -->
            <div class="mb-5 p-4 border rounded bg-light">
                <h2 class="mb-3">Add Entry</h2>
                <form id="addCourierLogForm" method="POST">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="courier">Courier:</label>
                            <input type="text" name="courier" id="courier" class="form-control" placeholder="Enter courier name" required>
                        </div>
                        <div class="col-md-3">
                            <label for="from">From:</label>
                            <input type="text" name="from" id="from" class="form-control" placeholder="Enter sender's name" required>
                        </div>
                        <div class="col-md-2">
                            <label for="num_boxes">Number of Boxes:</label>
                            <input type="number" name="num_boxes" id="num_boxes" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="notes">Notes:</label>
                            <input type="text" name="notes" id="notes" class="form-control" placeholder="Enter notes (e.g., damages)">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <label for="username">Username:</label>
                            <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required>
                        </div>
                        <div class="col-md-3">
                            <label for="password">Password:</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">Add Entry</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Search Data Section -->
            <div class="mb-5 p-4 border rounded bg-white">
                <h2 class="mb-3">Search Data</h2>
                <form method="GET">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="courier">Courier:</label>
                            <input type="text" name="courier" id="courier" class="form-control" value="<?php echo htmlspecialchars($courier); ?>" placeholder="Search by courier">
                        </div>
                        <div class="col-md-3">
                            <label for="from">From:</label>
                            <input type="text" name="from" id="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>" placeholder="Search by sender">
                        </div>
                        <div class="col-md-3">
                            <label for="notes">Notes:</label>
                            <input type="text" name="notes" id="notes" class="form-control" value="<?php echo htmlspecialchars($notes); ?>" placeholder="Search notes">
                        </div>
                        <div class="col-md-3">
                            <label for="start_date">Start Date:</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-3 mt-3">
                            <label for="end_date">End Date:</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-3 mt-3">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">Search</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table Displaying Logs -->
            <div class="table-responsive mt-5">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Courier</th>
                            <th>From</th>
                            <th>Number of Boxes</th>
                            <th>Notes</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($courier_logs)): ?>
                            <?php foreach ($courier_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['date']); ?></td>
                                    <td><?php echo htmlspecialchars($log['time']); ?></td>
                                    <td><?php echo htmlspecialchars($log['courier']); ?></td>
                                    <td><?php echo htmlspecialchars($log['from']); ?></td>
                                    <td><?php echo htmlspecialchars($log['num_boxes']); ?></td>
                                    <td><?php echo htmlspecialchars($log['notes']); ?></td>
                                    <td><?php echo htmlspecialchars($log['created_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No courier logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <nav class="mt-3">
                <ul class="pagination">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&<?php echo http_build_query($_GET); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&<?php echo http_build_query($_GET); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </section>
</div>

<?php require 'assets/footer.php'; ?>

<!-- JavaScript to Handle Form Submission -->
<script>
document.getElementById('addCourierLogForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('add_courier_log.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message); // Success message
            location.reload(); // Reload the page to update the table
        } else {
            alert(data.message); // Error message
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the entry.');
    });
});
</script>
