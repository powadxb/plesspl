<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'php/bootstrap.php';
require_once 'functions.php';

if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0] ?? null;

if (!$user_details) {
    die("User not found. Please log in again.");
}

$categories = $DB->query("SELECT * FROM task_categories ORDER BY category_name");
$page_title = 'Tasks';

require_once 'assets/header.php';
require_once 'assets/navbar.php';
?>

<style>
.task-container {
    max-width: 1000px;
    margin: 0 auto;
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
    border-radius: 8px;
    background: #fff;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.card.completed-task {
    background-color: #f8f9fa;
    opacity: 0.8;
}

.card-body {
    padding: 1.25rem;
}

.badge {
    color: white;
    padding: 0.5em 0.8em;
    font-weight: 500;
    border-radius: 6px;
    text-shadow: 0 1px 1px rgba(0,0,0,0.2);
}

.badge-secondary { background-color: #6c757d; }
.badge-primary { background-color: #007bff; }
.badge-danger { background-color: #dc3545; }

.btn-group-task {
    opacity: 0;
    transition: opacity 0.2s;
}

.card:hover .btn-group-task {
    opacity: 1;
}

.btn {
    border-radius: 6px;
    padding: 0.375rem 0.75rem;
    font-weight: 500;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
}

.modal-content {
    border: none;
    border-radius: 12px;
}

.modal-header {
    border-bottom: 1px solid rgba(0,0,0,0.1);
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid rgba(0,0,0,0.1);
    padding: 1.5rem;
}

.form-control {
    border-radius: 6px;
    border: 1px solid rgba(0,0,0,0.1);
    padding: 0.5rem 0.75rem;
}

.task-date {
    color: #6c757d;
    font-size: 0.9rem;
}

.subtask-container {
    margin-left: 2rem;
    position: relative;
}

.subtask-container::before {
    content: '';
    position: absolute;
    left: -1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgba(0,0,0,0.1);
}

.subtask-container.collapsed {
    display: none;
}

#categoryFilter {
    max-width: 200px;
}

.task-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pagination {
    margin: 0;
}

.pagination .page-link {
    padding: 0.5rem 0.75rem;
    margin: 0 0.25rem;
    border-radius: 4px;
    border: none;
    color: #007bff;
    background-color: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.pagination .page-item.active .page-link {
    background-color: #007bff;
    color: white;
}

.pagination .page-link:hover {
    background-color: #f8f9fa;
    color: #0056b3;
    text-decoration: none;
}

.pagination .page-item.active .page-link:hover {
    background-color: #0056b3;
    color: white;
}

.subtask-toggle {
    cursor: pointer;
    color: #6c757d;
    margin-right: 8px;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 3px;
}

.subtask-toggle:hover {
    background-color: rgba(0,0,0,0.05);
}

.task-description {
    display: flex;
    align-items: center;
}
</style>

<div class="page-container3">
    <section class="welcome p-t-20">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h1 class="title-4">Tasks</h1>
                    <hr class="line-seprate">
                </div>
            </div>
        </div>
    </section>

    <section class="p-t-20">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-3">
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addTaskModal">
                        <i class="fas fa-plus"></i> Add Task
                    </button>
                </div>
                <div class="col-md-3">
                    <select class="form-control" id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 text-right">
                    <button class="btn btn-outline-primary" id="showCompleted">
                        <i class="fas fa-eye"></i> Show Completed Tasks
                    </button>
                </div>
            </div>

            <div id="taskList"></div>
            <div id="pagination" class="mt-4 d-flex justify-content-center"></div>
        </div>
    </section>
</div>

<!-- Add/Edit Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Task</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="taskForm">
                <div class="modal-body">
                    <input type="hidden" id="taskId" name="id">
                    <input type="hidden" id="parentId" name="parent_id">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category_id">
                            <option value="">No Category</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select class="form-control" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" class="form-control" name="due_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'assets/footer.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<script>
function getCategoryColor(categoryId) {
    const colors = [
        '#3498db', // Blue
        '#e74c3c', // Red
        '#2ecc71', // Green
        '#9b59b6', // Purple
        '#f1c40f', // Yellow
        '#1abc9c', // Turquoise
        '#e67e22', // Orange
        '#34495e', // Navy
        '#7f8c8d', // Gray
        '#16a085', // Dark Turquoise
        '#d35400', // Dark Orange
        '#8e44ad', // Dark Purple
        '#2980b9', // Dark Blue
        '#27ae60', // Dark Green
        '#c0392b'  // Dark Red
    ];
    
    const colorIndex = (categoryId - 1) % colors.length;
    return colors[colorIndex];
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-GB', { 
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

$(document).ready(function() {
    loadTasks(1);
    
    $('#showCompleted').on('click', function() {
        $(this).toggleClass('btn-outline-primary btn-primary');
        if ($(this).hasClass('btn-primary')) {
            $(this).html('<i class="fas fa-eye-slash"></i> Hide Completed Tasks');
        } else {
            $(this).html('<i class="fas fa-eye"></i> Show Completed Tasks');
        }
        loadTasks(1);
    });

    $('#categoryFilter').change(function() {
        loadTasks(1);
    });

    $('#taskForm').submit(function(e) {
        e.preventDefault();
        $.post('task_actions.php?action=save', $(this).serialize(), function(response) {
            if (response.success) {
                $('#addTaskModal').modal('hide');
                $('#taskForm')[0].reset();
                const currentPage = $('#pagination .active .page-link').data('page') || 1;
                loadTasks(currentPage);
            }
        }, 'json');
    });

    $(document).on('click', '[data-target="#addTaskModal"]', function() {
        $('.modal-title').text('Add Task');
        $('#taskForm')[0].reset();
        $('#taskId').val('');
        $('#parentId').val('');
    });
    
    $(document).on('click', '.subtask-toggle', function(e) {
        e.stopPropagation();
        const taskId = $(this).data('task-id');
        const container = $(`#subtasks-${taskId}`);
        const icon = $(this).find('i');
        
        if (container.hasClass('collapsed')) {
            container.removeClass('collapsed');
            icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        } else {
            container.addClass('collapsed');
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        }
    });
});

function loadTasks(page = 1) {
    const showCompleted = $('#showCompleted').hasClass('btn-primary');
    const categoryId = $('#categoryFilter').val();
    
    $.getJSON('task_actions.php', {
        action: 'list',
        show_completed: showCompleted,
        category_id: categoryId,
        page: page
    }, function(response) {
        if (response.success) {
            displayTasks(response.data);
            displayPagination(response.pagination);
        }
    });
}

function displayTasks(tasks, parentId = null, level = 0) {
    const container = parentId === null ? $('#taskList') : $(`#subtasks-${parentId}`);
    if (parentId === null) container.empty();

    const filteredTasks = tasks.filter(task => task.parent_id === parentId);
    
    if (filteredTasks.length === 0 && parentId === null) {
        container.append('<div class="alert alert-info">No tasks found</div>');
        return;
    }

    filteredTasks.forEach(task => {
        const hasSubtasks = tasks.some(t => t.parent_id === task.id);
        
        const taskElement = $(`
            <div class="card mb-3 ${task.is_completed ? 'completed-task' : ''}" id="task-${task.id}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1 task-description ${task.is_completed ? 'text-muted' : ''}">
                            ${hasSubtasks ? 
                                `<span class="subtask-toggle" data-task-id="${task.id}">
                                    <i class="fas fa-chevron-down"></i>
                                </span>` : 
                                '<span style="width: 20px;"></span>'}
                            ${task.description}
                        </div>
                        <div class="btn-group-task ml-3">
                            <button class="btn btn-sm ${task.is_completed ? 'btn-success' : 'btn-outline-success'}" onclick="toggleComplete(${task.id}, ${!task.is_completed})">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-light" onclick="editTask(${task.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-light" onclick="addSubtask(${task.id})">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-sm btn-light text-danger" onclick="deleteTask(${task.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="badge badge-${getPriorityClass(task.priority)}">${task.priority}</span>
                        ${task.category_name ? 
    `<span class="badge ml-2" style="background-color: ${getCategoryColor(task.category_id)}">${task.category_name}</span>` 
    : ''}
                        <span class="task-date ml-2">
                            ${task.due_date ? `<i class="far fa-calendar"></i> Due: ${task.due_date}` : ''}
                        </span>
                        <span class="task-date ml-2">
                            <i class="far fa-clock"></i> Added: ${formatDate(task.added_on)}
                        </span>
                    </div>
                    <div class="subtask-container" id="subtasks-${task.id}"></div>
                </div>
            </div>
        `);
        container.append(taskElement);
        displayTasks(tasks, task.id, level + 1);
    });
}

function displayPagination(pagination) {
    const container = $('#pagination');
    container.empty();
    
    if (pagination.total_pages <= 1) return;

    const ul = $('<ul class="pagination"></ul>');

    // Previous button
    if (pagination.current_page > 1) {
        ul.append(`
            <li class="page-item">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `);
    }

    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (
            i === 1 || 
            i === pagination.total_pages || 
            (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)
        ) {
            ul.append(`
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        } else if (
            i === pagination.current_page - 3 || 
            i === pagination.current_page + 3
        ) {
            ul.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
        }
    }

    // Next button
    if (pagination.current_page < pagination.total_pages) {
        ul.append(`
            <li class="page-item">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `);
    }

    container.append(ul);

    // Add click handlers
    container.find('.page-link').click(function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) loadTasks(page);
    });
}

function getPriorityClass(priority) {
    return {
        'low': 'secondary',
        'medium': 'primary',
        'high': 'danger'
    }[priority] || 'secondary';
}

function toggleComplete(id, completed) {
    $.post('task_actions.php', {
        action: 'complete',
        id: id,
        completed: completed ? 1 : 0
    }).done(function(response) {
        if (response.success) {
            const currentPage = $('#pagination .active .page-link').data('page') || 1;
            loadTasks(currentPage);
        }
    });
}

function editTask(id) {
    $.getJSON('task_actions.php', { 
        action: 'get', 
        id: id 
    }).done(function(response) {
        if (response.success) {
            const task = response.data;
            $('.modal-title').text('Edit Task');
            $('#taskId').val(task.id);
            $('#parentId').val(task.parent_id);
            $('[name="description"]').val(task.description);
            $('[name="category_id"]').val(task.category_id);
            $('[name="priority"]').val(task.priority);
            $('[name="due_date"]').val(task.due_date);
            $('#addTaskModal').modal('show');
        }
    });
}

function addSubtask(parentId) {
    $('#taskForm')[0].reset();
    $('#taskId').val('');
    $('#parentId').val(parentId);
    $('.modal-title').text('Add Subtask');
    $('#addTaskModal').modal('show');
}

function deleteTask(id) {
    if (confirm('Are you sure you want to delete this task?')) {
        $.post('task_actions.php', { 
            action: 'delete', 
            id: id 
        }).done(function(response) {
            if (response.success) {
                const currentPage = $('#pagination .active .page-link').data('page') || 1;
                loadTasks(currentPage);
            }
        });
    }
}
</script>