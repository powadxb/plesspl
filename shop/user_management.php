<?php
// User management interface (user_management.php)
require_once 'config.php';
require_once 'auth.php';
require_once 'user_manager.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated() || !$auth->hasPermission('admin')) {
    header('Location: login.php');
    exit();
}

$userManager = new UserManager(getDB());

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'create':
                $userManager->createUser($_POST);
                $message = "User created successfully";
                break;

            case 'update':
                $userManager->updateUser($_POST['user_id'], $_POST);
                $message = "User updated successfully";
                break;

            case 'deactivate':
                $userManager->deactivateUser($_POST['user_id']);
                $message = "User deactivated successfully";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get users list
$users = isset($_GET['search'])
    ? $userManager->searchUsers($_GET['search'])
    : $userManager->getUsersByLocation($_SESSION['location_id']);

// Get locations for dropdown
$stmt = getDB()->query("SELECT id, name FROM locations ORDER BY name");
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col">
            <h2>User Management</h2>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                Add New User
            </button>
        </div>
    </div>

    <?php if (isset($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search"
                               placeholder="Search users by username or email"
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Location</th>
                            <th>Tickets Completed</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' :
                                    ($user['role'] === 'manager' ? 'warning' : 'info') ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['location_name']) ?></td>
                            <td><?= $user['completed_tickets'] ?> / <?= $user['total_tickets'] ?></td>
                            <td><?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-user-id="<?= $user['id'] ?>"
                                        data-username="<?= htmlspecialchars($user['username']) ?>">
                                    Edit
                                </button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <button type="button" class="btn btn-sm btn-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deactivateUserModal"
                                        data-user-id="<?= $user['id'] ?>"
                                        data-username="<?= htmlspecialchars($user['username']) ?>">
                                    Deactivate
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="technician">Technician</option>
                            <option value="front_desk">Front Desk</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location_id" required>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="technician">Technician</option>
                            <option value="front_desk">Front Desk</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location_id" required>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Deactivate User Modal -->
<div class="modal fade" id="deactivateUserModal" tabindex="-1">
  <div class="modal-dialog">
          <div class="modal-content">
              <form method="POST">
                  <input type="hidden" name="action" value="deactivate">
                  <input type="hidden" name="user_id" id="deactivateUserId">
                  <div class="modal-header">
                      <h5 class="modal-title">Deactivate User</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                      <p>Are you sure you want to deactivate user: <strong id="deactivateUsername"></strong>?</p>
                      <p class="text-danger">This action will prevent the user from accessing the system.</p>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-danger">Deactivate User</button>
                  </div>
              </form>
          </div>
      </div>
  </div>

  <!-- User Activity Log Modal -->
  <div class="modal fade" id="userActivityModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title">User Activity Log</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                  <div class="table-responsive">
                      <table class="table">
                          <thead>
                              <tr>
                                  <th>Date</th>
                                  <th>Action</th>
                                  <th>Details</th>
                              </tr>
                          </thead>
                          <tbody id="userActivityLog">
                              <!-- Activity log data will be loaded here -->
                          </tbody>
                      </table>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <script>
  // Form validation
  (function () {
      'use strict'

      const forms = document.querySelectorAll('.needs-validation');

      Array.from(forms).forEach(form => {
          form.addEventListener('submit', event => {
              if (!form.checkValidity()) {
                  event.preventDefault();
                  event.stopPropagation();
              }
              form.classList.add('was-validated');
          }, false);
      });
  })();

  // Edit user modal functionality
  document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const userId = button.dataset.userId;
      const username = button.dataset.username;

      document.getElementById('editUserId').value = userId;

      // Fetch user details and populate form
      fetch(`api/get_user_details.php?id=${userId}`)
          .then(response => response.json())
          .then(user => {
              const modal = event.target;
              modal.querySelector('[name="email"]').value = user.email;
              modal.querySelector('[name="role"]').value = user.role;
              modal.querySelector('[name="location_id"]').value = user.location_id;
          })
          .catch(error => console.error('Error:', error));
  });

  // Deactivate user modal functionality
  document.getElementById('deactivateUserModal').addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const userId = button.dataset.userId;
      const username = button.dataset.username;

      document.getElementById('deactivateUserId').value = userId;
      document.getElementById('deactivateUsername').textContent = username;
  });

  // Load user activity log
  function loadUserActivity(userId) {
      fetch(`api/get_user_activity.php?id=${userId}`)
          .then(response => response.json())
          .then(activities => {
              const logContainer = document.getElementById('userActivityLog');
              logContainer.innerHTML = '';

              activities.forEach(activity => {
                  const row = document.createElement('tr');
                  row.innerHTML = `
                      <td>${new Date(activity.timestamp).toLocaleString()}</td>
                      <td>${activity.action}</td>
                      <td>${activity.details}</td>
                  `;
                  logContainer.appendChild(row);
              });
          })
          .catch(error => console.error('Error:', error));
  }

  // Password strength validation
  document.querySelectorAll('input[type="password"]').forEach(input => {
      input.addEventListener('input', function() {
          const strength = checkPasswordStrength(this.value);
          this.classList.remove('is-valid', 'is-invalid');

          if (this.value) {
              this.classList.add(strength >= 3 ? 'is-valid' : 'is-invalid');
          }
      });
  });

  function checkPasswordStrength(password) {
      let strength = 0;

      if (password.length >= 8) strength++;
      if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
      if (password.match(/\d/)) strength++;
      if (password.match(/[^a-zA-Z\d]/)) strength++;

      return strength;
  }

  // Role-based form field toggling
  document.querySelectorAll('select[name="role"]').forEach(select => {
      select.addEventListener('change', function() {
          const form = this.closest('form');
          const locationField = form.querySelector('[name="location_id"]');

          // Disable location selection for admin users
          if (this.value === 'admin') {
              locationField.disabled = true;
              locationField.value = '1'; // Set to headquarters or main location
          } else {
              locationField.disabled = false;
          }
      });
  });
  </script>
