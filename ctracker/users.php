<?php
// users.php
require_once 'config.php';
require_once 'auth.php';

// Ensure only admin can access
requireRole('admin');

$conn = new PDO("mysql:host=" . DB_HOST . ";dbname=cashtracker", DB_USER, DB_PASS);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role, branch_id, active) 
                                          VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$_POST['username'], $hashed_password, $_POST['role'], 
                                  $_POST['branch_id'] ?: null]);
                    $message = "User added successfully";
                    break;

                case 'edit':
                    $sql = "UPDATE users SET role = ?, branch_id = ?, active = ? WHERE id = ?";
                    $params = [$_POST['role'], $_POST['branch_id'] ?: null, 
                             isset($_POST['active']) ? 1 : 0, $_POST['user_id']];
                    
                    if (!empty($_POST['password'])) {
                        $sql = "UPDATE users SET role = ?, branch_id = ?, active = ?, 
                               password = ? WHERE id = ?";
                        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $params = [$_POST['role'], $_POST['branch_id'] ?: null, 
                                 isset($_POST['active']) ? 1 : 0, $hashed_password, 
                                 $_POST['user_id']];
                    }
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    $message = "User updated successfully";
                    break;
            }
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all users
$users = $conn->query("SELECT u.*, b.name as branch_name 
                       FROM users u 
                       LEFT JOIN branches b ON u.branch_id = b.id 
                       ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get branches for dropdown
$branches = $conn->query("SELECT id, name FROM branches ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            font-size: 14px;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
        }
        h1 { 
            font-size: 20px;
            margin-bottom: 20px;
        }
        .form-group { 
            margin-bottom: 10px;
        }
        label { 
            display: inline-block;
            width: 100px;
        }
        input[type="text"], input[type="password"], select { 
            padding: 4px;
            width: 200px;
        }
        button { 
            background-color: #4CAF50;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .message { color: green; margin-bottom: 10px; }
        .error { color: red; margin-bottom: 10px; }
        .edit-form {
            display: none;
            background: #f9f9f9;
            padding: 10px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>User Management</h1>
        
        <a href="index.php">Back to Home</a>

        <?php if (isset($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <h2>Add New User</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select name="role" required>
                    <option value="staff">Staff</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-group">
                <label for="branch_id">Branch:</label>
                <select name="branch_id">
                    <option value="">No Branch</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>">
                            <?php echo htmlspecialchars($branch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Add User</button>
        </form>

        <h2>Existing Users</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo htmlspecialchars($user['branch_name'] ?? 'No Branch'); ?></td>
                        <td><?php echo $user['active'] ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <button onclick="toggleEditForm('<?php echo $user['id']; ?>')">
                                Edit
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="5">
                            <form method="POST" id="edit_<?php echo $user['id']; ?>" 
                                  class="edit-form">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="user_id" 
                                       value="<?php echo $user['id']; ?>">
                                
                                <div class="form-group">
                                    <label>New Password:</label>
                                    <input type="password" name="password" 
                                           placeholder="Leave blank to keep current">
                                </div>

                                <div class="form-group">
                                    <label>Role:</label>
                                    <select name="role" required>
                                        <option value="staff" <?php echo $user['role'] == 'staff' ? 'selected' : ''; ?>>Staff</option>
                                        <option value="manager" <?php echo $user['role'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Branch:</label>
                                    <select name="branch_id">
                                        <option value="">No Branch</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo $branch['id']; ?>"
                                                    <?php echo $user['branch_id'] == $branch['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($branch['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Active:</label>
                                    <input type="checkbox" name="active" 
                                           <?php echo $user['active'] ? 'checked' : ''; ?>>
                                </div>

                                <button type="submit">Update User</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function toggleEditForm(userId) {
            const form = document.getElementById('edit_' + userId);
            const currentDisplay = form.style.display;
            form.style.display = currentDisplay === 'none' || currentDisplay === '' ? 'block' : 'none';
        }
    </script>
</body>
</html>