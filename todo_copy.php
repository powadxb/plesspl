<?php
session_start();
$page_title = 'To-Do List';
require 'php/bootstrap.php';
require 'assets/header.php';

// Ensure user is authenticated
if (!isset($_SESSION['dins_user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user details
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Ensure `user_details` is available for navbar.php
?>

<?php require 'assets/navbar.php'; ?>

<div class="page-container3">
    <section class="welcome p-t-20">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h1 class="title-4">To-Do List</h1>
                    <hr class="line-seprate">
                </div>
            </div>
        </div>
    </section>

    <section class="p-t-20">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <form id="addTaskForm">
                        <div class="form-group">
                            <label for="taskDescription">Task Description</label>
                            <input type="text" id="taskDescription" class="form-control" placeholder="Enter task description" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Task</button>
                    </form>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <h3>Tasks</h3>
                        <button id="toggleCompleted" class="btn btn-secondary btn-sm">Show Completed Tasks</button>
                    </div>
                    <ul id="taskList" class="list-group mt-3"></ul>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require 'assets/footer.php'; ?>

<script>
$(document).ready(function () {
    let showCompletedTasks = false; // State for showing completed tasks

    // Add task
    $('#addTaskForm').submit(function (e) {
        e.preventDefault();

        const taskDescription = $('#taskDescription').val();

        if (!taskDescription.trim()) {
            alert('Task description is required.');
            return;
        }

        $.ajax({
            url: 'add_task.php',
            method: 'POST',
            data: { task_description: taskDescription },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert('Task added successfully');
                    $('#taskDescription').val(''); // Clear input
                    loadTasks();
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Failed to add task. Check console for details.');
            }
        });
    });

    // Load tasks
    function loadTasks() {
        $.ajax({
            url: 'get_tasks.php',
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const taskList = $('#taskList');
                    taskList.empty(); // Clear current tasks

                    response.tasks.forEach((task) => {
                        // Skip completed tasks if they're hidden
                        if (!showCompletedTasks && task.is_completed) return;

                        const taskHtml = `
                            <li class="list-group-item d-flex justify-content-between align-items-center ${task.is_completed ? 'bg-light' : ''}">
                                <div>
                                    <strong>${task.description}</strong>
                                    <br>
                                    <small>Added: ${task.added_on}</small>
                                    ${task.is_completed ? `<br><small>Completed: ${task.completed_on}</small>` : ''}
                                </div>
                                <div>
                                    ${!task.is_completed ? `<button data-id="${task.id}" class="btn btn-success btn-sm mark-complete">Mark Complete</button>` : ''}
                                    <button data-id="${task.id}" class="btn btn-danger btn-sm delete-task">Delete</button>
                                </div>
                            </li>`;
                        taskList.append(taskHtml);
                    });
                } else {
                    alert('Failed to load tasks: ' + response.error);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Failed to load tasks.');
            }
        });
    }

    // Mark task as complete
    $(document).on('click', '.mark-complete', function () {
        const taskId = $(this).data('id');

        $.ajax({
            url: 'mark_task_complete.php',
            method: 'POST',
            data: { task_id: taskId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert('Task marked as complete');
                    loadTasks();
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Failed to mark task as complete.');
            }
        });
    });

    // Delete task
    $(document).on('click', '.delete-task', function () {
        const taskId = $(this).data('id');

        $.ajax({
            url: 'delete_task.php',
            method: 'POST',
            data: { task_id: taskId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert('Task deleted successfully');
                    loadTasks();
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Failed to delete task.');
            }
        });
    });

    // Toggle completed tasks
    $('#toggleCompleted').click(function () {
        showCompletedTasks = !showCompletedTasks;
        $(this).text(showCompletedTasks ? 'Hide Completed Tasks' : 'Show Completed Tasks');
        loadTasks();
    });

    // Initial load
    loadTasks();
});
</script>
