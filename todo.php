<?php
session_start();
require 'php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['dins_user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head><style>
#taskList { 
    border: 1px solid red;
    min-height: 100px;
}
</style>

<body>
    <?php include 'assets/header.php'; ?>
    <?php include 'assets/navbar.php'; ?>

    <div class="container mt-4">
        <div class="card mb-4">
            <div class="card-body">
                <form id="addTaskForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="taskDescription" 
                                   placeholder="Task Description" required>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="taskCategory" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="priority">
                                <option value="low">Low Priority</option>
                                <option value="medium" selected>Medium Priority</option>
                                <option value="high">High Priority</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" id="dueDate">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Task
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="category-filters">
                    <div class="btn-group" role="group"></div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <button id="toggleCompleted" class="btn btn-outline-secondary">
                    <i class="fas fa-eye"></i> Show Completed
                </button>
            </div>
        </div>

        <div class="task-list-container">
            <div id="noTasksMessage" class="alert alert-info d-none">
                No tasks found. Add your first task above!
            </div>
            <div id="taskList"></div>
            <nav aria-label="Task pagination">
                <ul class="pagination justify-content-center"></ul>
            </nav>
        </div>
    </div>

    <div class="loading-overlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="alert-container"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/todo.js"></script>
  <script>
console.log('Todo.js loaded');
$(document).ready(function() {
    console.log('jQuery ready');
});
</script>
</body>
</html>