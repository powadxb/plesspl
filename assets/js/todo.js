$(document).ready(function() {
    const todoApp = {
        showCompletedTasks: false,
        currentPage: 1,
        itemsPerPage: 10,
        categoriesMap: new Map(),
        
        priorityColors: {
            'high': '#dc3545',
            'medium': '#ffc107',
            'low': '#28a745'
        },

        initialize: function() {
            this.loadCategories();
            this.initializeEventHandlers();
            // this.setupSortable();
        },

        loadCategories: function() {
            $.ajax({
                url: 'api/get_categories.php',
                method: 'GET',
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.setupCategories(response.categories);
                        this.loadTasks();
                    }
                }
            });
        },

        setupCategories: function(categories) {
            const categorySelect = $('#taskCategory');
            const categoryFilters = $('.category-filters .btn-group');
            
            categorySelect.empty();
            categoryFilters.empty();
            
            categorySelect.append('<option value="">Select Category</option>');
            categoryFilters.append(`
                <button type="button" class="btn btn-outline-secondary active" 
                        data-category="all">All Tasks</button>
            `);
            
            categories.forEach(category => {
                this.categoriesMap.set(category.id, {
                    name: category.category_name,
                    color: this.getCategoryColor(category.id)
                });
                
                categorySelect.append(`
                    <option value="${category.id}">${category.category_name}</option>
                `);
                
                categoryFilters.append(`
                    <button type="button" class="btn btn-outline-secondary" 
                            data-category="${category.id}">
                        ${category.category_name}
                    </button>
                `);
            });
        },

        setupSortable: function() {
            $('#taskList').sortable({
                handle: '.drag-handle',
                items: '> .task-card:not(.subtask)',
                update: (event, ui) => this.handleTaskReorder(event, ui)
            });
        },

        initializeEventHandlers: function() {
            // Form submissions
            $('#addTaskForm').on('submit', (e) => {
                e.preventDefault();
                this.handleAddTask();
            });

            // Task actions
            $('#taskList').on('click', '.mark-complete', (e) => {
                const taskId = $(e.currentTarget).closest('.task-card').data('id');
                this.handleCompleteTask(taskId);
            });

            $('#taskList').on('click', '.delete-task', (e) => {
                const taskId = $(e.currentTarget).closest('.task-card').data('id');
                this.handleDeleteTask(taskId);
            });

            $('#taskList').on('click', '.edit-task', (e) => {
                const $task = $(e.currentTarget).closest('.task-card');
                this.handleEditTask($task);
            });

            $('#taskList').on('click', '.add-subtask', (e) => {
                const taskId = $(e.currentTarget).closest('.task-card').data('id');
                this.handleAddSubtask(taskId);
            });

            // UI controls
            $('#toggleCompleted').on('click', () => this.toggleCompletedTasks());
            $('.category-filters').on('click', '.btn', (e) => this.handleCategoryFilter(e));
            $('.pagination').on('click', '.page-link', (e) => this.handlePageChange(e));

            // Modal form submissions
            $('#editTaskForm').on('submit', (e) => {
                e.preventDefault();
                this.handleEditTaskSubmit();
            });

            $('#addSubtaskForm').on('submit', (e) => {
                e.preventDefault();
                this.handleAddSubtaskSubmit();
            });
        },

        getCategoryColor: function(categoryId) {
            const baseColors = [
                '#FFE0B2',  // Light Orange
                '#C8E6C9',  // Light Green
                '#B3E5FC',  // Light Blue
                '#F8BBD0',  // Light Pink
                '#D1C4E9',  // Light Purple
                '#FFECB3',  // Light Amber
                '#CFD8DC',  // Light Blue Grey
                '#F5F5F5',  // Light Grey
                '#E1BEE7',  // Light Purple
                '#C5CAE9'   // Light Indigo
            ];
            return baseColors[categoryId % baseColors.length];
        },

        loadTasks: function() {
            this.showLoadingState();
            $.ajax({
                url: 'api/get_tasks.php',
                method: 'GET',
                data: {
                    page: this.currentPage,
                    per_page: this.itemsPerPage,
                    show_completed: this.showCompletedTasks ? 1 : 0,
                    category: this.currentCategory || 'all'
                },
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.renderTasks(response.tasks);
                        this.renderPagination(response.total_pages);
                    } else {
                        this.showError('Failed to load tasks');
                    }
                    this.hideLoadingState();
                },
                error: () => {
                    this.showError('Failed to load tasks');
                    this.hideLoadingState();
                }
            });
        },
      renderTasks: function(tasks) {
            const $taskList = $('#taskList').empty();
            
            if (tasks.length === 0) {
                $('#noTasksMessage').show();
                return;
            }
            
            $('#noTasksMessage').hide();
            
            tasks.forEach(task => {
                if (!task.parent_id) {
                    const taskElement = this.createTaskElement(task);
                    $taskList.append(taskElement);
                    
                    // Render subtasks if any
                    const subtasks = tasks.filter(t => t.parent_id === task.id);
                    subtasks.forEach(subtask => {
                        $taskList.append(this.createTaskElement(subtask, true));
                    });
                }
            });
        },

        createTaskElement: function(task, isSubtask = false) {
            const description = this.escapeHtml(task.description);
            const categoryInfo = this.categoriesMap.get(parseInt(task.category_id)) || {
                name: 'No Category',
                color: '#e9ecef'
            };

            return `
                <div class="task-card ${isSubtask ? 'subtask' : ''} ${task.is_completed ? 'completed' : ''}" 
                     data-id="${task.id}" 
                     data-parent-id="${task.parent_id || ''}"
                     data-category-id="${task.category_id || ''}"
                     data-priority="${task.priority}">
                    <div class="d-flex justify-content-between align-items-start p-3">
                        <div class="task-content">
                            <div class="task-header">
                                ${!isSubtask ? '<span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>' : ''}
                                <span class="priority-indicator" 
                                      style="background-color: ${this.priorityColors[task.priority]}">
                                </span>
                                <span class="task-category-badge"
                                      style="background-color: ${categoryInfo.color}">
                                    ${categoryInfo.name}
                                </span>
                            </div>
                            <h6 class="mb-1 task-description">
                                ${description}
                            </h6>
                            <div class="task-details">
                                Added: ${this.formatDate(task.added_on)}
                                ${task.due_date ? ` · Due: ${this.formatDate(task.due_date)}` : ''}
                                · Priority: ${task.priority}
                                ${task.completed_on ? ` · Completed: ${this.formatDate(task.completed_on)}` : ''}
                            </div>
                        </div>
                        <div class="task-actions">
                            ${!task.is_completed ? `
                                <button class="btn btn-sm btn-success mark-complete me-1" title="Mark Complete">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            <button class="btn btn-sm btn-info edit-task me-1" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            ${!isSubtask ? `
                                <button class="btn btn-sm btn-secondary add-subtask me-1" title="Add Subtask">
                                    <i class="fas fa-tasks"></i>
                                </button>
                            ` : ''}
                            <button class="btn btn-sm btn-danger delete-task" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        },

handleAddTask: function() {
            console.log('Adding task...');
            const formData = {
                description: $('#taskDescription').val().trim(),
                category_id: $('#taskCategory').val(),
                priority: $('#priority').val(),
                due_date: $('#dueDate').val()
            };

            console.log('Form data:', formData);

            $.ajax({
                url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/api/add_task.php',
                method: 'POST',
                data: formData,
                success: (response) => {
                    console.log('Add task response:', response);
                    if (response.success) {
                        $('#addTaskForm')[0].reset();
                        this.loadTasks();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Add task error:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        },

        handleCompleteTask: function(taskId) {
            this.showLoadingState();
            $.ajax({
                url: 'api/complete_task.php',
                method: 'POST',
                data: { task_id: taskId },
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Task completed');
                        this.loadTasks();
                    } else {
                        this.showError('Failed to complete task');
                    }
                    this.hideLoadingState();
                },
                error: () => {
                    this.showError('Failed to complete task');
                    this.hideLoadingState();
                }
            });
        },

        handleDeleteTask: function(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) {
                return;
            }

            this.showLoadingState();
            $.ajax({
                url: 'api/delete_task.php',
                method: 'POST',
                data: { task_id: taskId },
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Task deleted');
                        this.loadTasks();
                    } else {
                        this.showError('Failed to delete task');
                    }
                    this.hideLoadingState();
                },
                error: () => {
                    this.showError('Failed to delete task');
                    this.hideLoadingState();
                }
            });
        },
      handleEditTask: function($task) {
            const taskId = $task.data('id');
            const currentDescription = $task.find('.task-description').text().trim();
            const currentPriority = $task.data('priority');
            const currentCategoryId = $task.data('category-id');

            const $taskContent = $task.find('.task-content');
            const originalContent = $taskContent.html();

            $taskContent.html(`
                <form class="edit-task-form">
                    <div class="mb-2">
                        <input type="text" class="form-control" value="${this.escapeHtml(currentDescription)}" required>
                    </div>
                    <div class="row g-2">
                        <div class="col">
                            <select class="form-select">
                                <option value="low" ${currentPriority === 'low' ? 'selected' : ''}>Low</option>
                                <option value="medium" ${currentPriority === 'medium' ? 'selected' : ''}>Medium</option>
                                <option value="high" ${currentPriority === 'high' ? 'selected' : ''}>High</option>
                            </select>
                        </div>
                        <div class="col">
                            <select class="form-select">
                                <option value="">No Category</option>
                                ${Array.from(this.categoriesMap.entries()).map(([id, data]) => `
                                    <option value="${id}" ${currentCategoryId == id ? 'selected' : ''}>
                                        ${data.name}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-success">Save</button>
                            <button type="button" class="btn btn-secondary cancel-edit">Cancel</button>
                        </div>
                    </div>
                </form>
            `);

            $taskContent.find('.cancel-edit').click(() => {
                $taskContent.html(originalContent);
            });

            $taskContent.find('form').submit((e) => {
                e.preventDefault();
                const $form = $(e.currentTarget);
                
                this.showLoadingState();
                $.ajax({
                    url: 'api/update_task.php',
                    method: 'POST',
                    data: {
                        task_id: taskId,
                        description: $form.find('input[type="text"]').val().trim(),
                        priority: $form.find('select').first().val(),
                        category_id: $form.find('select').last().val()
                    },
                    dataType: 'json',
                    success: (response) => {
                        if (response.success) {
                            this.showSuccess('Task updated');
                            this.loadTasks();
                        } else {
                            this.showError('Failed to update task');
                            $taskContent.html(originalContent);
                        }
                        this.hideLoadingState();
                    },
                    error: () => {
                        this.showError('Failed to update task');
                        $taskContent.html(originalContent);
                        this.hideLoadingState();
                    }
                });
            });
        },

        handleAddSubtask: function(taskId) {
            const $task = $(`[data-id="${taskId}"]`);
            $task.after(`
                <div class="task-card subtask p-3">
                    <form class="add-subtask-form">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Enter subtask" required>
                            <select class="form-select" style="max-width: 100px;">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                            <button type="submit" class="btn btn-success">Add</button>
                            <button type="button" class="btn btn-secondary cancel-subtask">Cancel</button>
                        </div>
                    </form>
                </div>
            `);

            const $subtaskForm = $('.add-subtask-form').last();

            $subtaskForm.find('.cancel-subtask').click(() => {
                $subtaskForm.closest('.task-card').remove();
            });

            $subtaskForm.submit((e) => {
                e.preventDefault();
                const $form = $(e.currentTarget);
                
                this.showLoadingState();
                $.ajax({
                    url: 'api/add_task.php',
                    method: 'POST',
                    data: {
                        parent_id: taskId,
                        description: $form.find('input').val().trim(),
                        priority: $form.find('select').val()
                    },
                    dataType: 'json',
                    success: (response) => {
                        if (response.success) {
                            this.showSuccess('Subtask added');
                            this.loadTasks();
                        } else {
                            this.showError('Failed to add subtask');
                        }
                        this.hideLoadingState();
                    },
                    error: () => {
                        this.showError('Failed to add subtask');
                        this.hideLoadingState();
                    }
                });
            });
        },

        handleTaskReorder: function(event, ui) {
            const taskId = ui.item.data('id');
            const prevTaskId = ui.item.prev('.task-card').data('id') || 0;
            const nextTaskId = ui.item.next('.task-card').data('id') || 0;
            
            $.ajax({
                url: 'api/reorder_task.php',
                method: 'POST',
                data: {
                    task_id: taskId,
                    prev_id: prevTaskId,
                    next_id: nextTaskId
                },
                success: (response) => {
                    if (!response.success) {
                        this.showError('Failed to reorder task');
                        this.loadTasks(); // Reload to restore original order
                    }
                },
                error: () => {
                    this.showError('Failed to reorder task');
                    this.loadTasks(); // Reload to restore original order
                }
            });
        },
      handleCategoryFilter: function(e) {
            const $btn = $(e.currentTarget);
            $('.category-filters .btn').removeClass('active');
            $btn.addClass('active');
            this.currentCategory = $btn.data('category');
            this.currentPage = 1;
            this.loadTasks();
        },

        handlePageChange: function(e) {
            e.preventDefault();
            const $link = $(e.currentTarget);
            if ($link.parent().hasClass('disabled')) return;
            
            this.currentPage = parseInt($link.data('page'));
            this.loadTasks();
        },

        renderPagination: function(totalPages) {
            const $pagination = $('.pagination').empty();
            if (totalPages <= 1) return;
            
            // Previous button
            $pagination.append(`
                <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${this.currentPage - 1}">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `);
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || Math.abs(i - this.currentPage) <= 1) {
                    $pagination.append(`
                        <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>
                    `);
                } else if (Math.abs(i - this.currentPage) === 2) {
                    $pagination.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
                }
            }
            
            // Next button
            $pagination.append(`
                <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${this.currentPage + 1}">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `);
        },

        toggleCompletedTasks: function() {
            this.showCompletedTasks = !this.showCompletedTasks;
            const $btn = $('#toggleCompleted');
            $btn.find('i').toggleClass('fa-eye fa-eye-slash');
            if (this.showCompletedTasks) {
                $btn.html('<i class="fas fa-eye-slash me-2"></i>Hide Completed');
            } else {
                $btn.html('<i class="fas fa-eye me-2"></i>Show Completed');
            }
            this.loadTasks();
        },

        showLoadingState: function() {
            $('#taskList').addClass('loading');
            $('.loading-overlay').show();
        },

        hideLoadingState: function() {
            $('#taskList').removeClass('loading');
            $('.loading-overlay').hide();
        },

        showSuccess: function(message) {
            const $alert = $('<div class="alert alert-success alert-dismissible fade show" role="alert">')
                .text(message)
                .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
            
            $('.alert-container').append($alert);
            setTimeout(() => $alert.alert('close'), 3000);
        },

        showError: function(message) {
            const $alert = $('<div class="alert alert-danger alert-dismissible fade show" role="alert">')
                .text(message)
                .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
            
            $('.alert-container').append($alert);
            setTimeout(() => $alert.alert('close'), 3000);
        },

        formatDate: function(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize the todo application
    todoApp.initialize();
});
      