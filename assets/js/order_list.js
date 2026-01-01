/**
 * ORDER LIST JAVASCRIPT MODULE
 * Handles all functionality for the order list page with category grouping
 */

// Wait for jQuery to be available before loading other dependencies
(function() {
    function loadDependencies() {
        // Load SweetAlert2 if not already loaded
        if (typeof Swal === 'undefined') {
            const sweetAlertScript = document.createElement('script');
            sweetAlertScript.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            sweetAlertScript.onload = initializeApp;
            document.head.appendChild(sweetAlertScript);
        } else {
            initializeApp();
        }
    }

    // Check if jQuery is loaded, if not wait
    if (typeof jQuery !== 'undefined') {
        loadDependencies();
    } else {
        // Wait for jQuery to load
        let checkJQuery = setInterval(function() {
            if (typeof jQuery !== 'undefined') {
                clearInterval(checkJQuery);
                loadDependencies();
            }
        }, 50);
    }

    function initializeApp() {
        if (typeof jQuery !== 'undefined' && typeof Swal !== 'undefined') {
            $(document).ready(() => {
                OrderListApp.init();
            });
        }
    }
})();

// Global state management
const OrderListApp = {
    currentSort: {
        sort_by: 'added_at',
        sort_direction: 'ASC'
    },
    orderListData: [],
    groupedData: {},
    selectedProduct: null,
    isValidating: false,
    isNonDbMode: false,
    isMobile: window.innerWidth <= 768,
    
    // Configuration from PHP
    config: {
        isAdmin: window.ORDER_LIST_CONFIG?.isAdmin || false,
        hasSupplierAccess: window.ORDER_LIST_CONFIG?.hasSupplierAccess || false
    },

    // Initialize the application
    init() {
        console.log('Initializing Order List App...');
        this.bindEvents();
        this.setupMobileDetection();
        this.fetchOrderList();
        this.initializeView();
    },

    // Set up mobile detection and resize handling
    setupMobileDetection() {
        const checkMobile = () => {
            this.isMobile = window.innerWidth <= 768;
            this.updateViewVisibility();
        };
        
        window.addEventListener('resize', checkMobile);
        checkMobile();
    },

    // Update view visibility based on screen size
    updateViewVisibility() {
        const desktopView = document.querySelector('.desktop-view');
        const mobileView = document.querySelector('.mobile-view');
        
        if (this.isMobile) {
            if (desktopView) desktopView.style.display = 'none';
            if (mobileView) mobileView.style.display = 'block';
        } else {
            if (desktopView) desktopView.style.display = 'block';
            if (mobileView) mobileView.style.display = 'none';
        }
    },

    // Initialize view-specific functionality
    initializeView() {
        if (this.isMobile) {
            this.initializeMobileView();
        } else {
            this.initializeDesktopView();
        }
    },

    // Bind all event listeners
    bindEvents() {
        // Form submission
        $(document).on('submit', '#addToOrderListForm', (e) => this.handleFormSubmission(e));
        
        // Product search
        $(document).on('input', '#name', (e) => this.handleProductSearch(e));
        $(document).on('click', '#productSuggestions .dropdown-item', (e) => this.handleSuggestionSelection(e));
        
        // SKU validation
        $(document).on('input', '#sku', (e) => this.handleSkuInput(e));
        $(document).on('blur', '#sku', (e) => this.handleSkuValidation(e));
        
        // Non-DB item handling
        $(document).on('input', '#non_db_item', (e) => this.handleNonDbInput(e));
        
        // Input clearing
        $(document).on('input', '#name, #sku, #non_db_item', (e) => this.handleInputClearing(e));
        
        // Hide suggestions when clicking outside
        $(document).on('click', (e) => this.handleOutsideClick(e));
        
        // Category toggle events
        $(document).on('click', '.category-header', (e) => this.handleCategoryToggle(e));
        
        // Desktop table events
        $(document).on('click', '.sort-link', (e) => this.handleSorting(e));
        $(document).on('click', '.action-btn', (e) => this.handleActionButton(e));
        $(document).on('click', '.supplier-lookup-icon', (e) => this.handleSupplierLookup(e));
        $(document).on('click', '.comment-text, .comment-edit-icon', (e) => this.handleCommentEdit(e));
        $(document).on('click', '#saveCommentsBtn', (e) => this.saveComments(e));
        
        // Mobile events
        $(document).on('click', '.mobile-order-item', (e) => this.handleMobileItemClick(e));
        $(document).on('change', '#mobileSortSelect', (e) => this.handleMobileSorting(e));
        $(document).on('click', '#closeModal', () => this.closeMobileModal());
        $(document).on('click', '.mobile-modal', (e) => this.handleModalBackdropClick(e));
    },

    // Handle category toggle
    handleCategoryToggle(e) {
        e.preventDefault();
        const header = $(e.currentTarget);
        const content = header.next('.category-content');
        const icon = header.find('.category-toggle-icon');
        
        if (content.is(':visible')) {
            content.slideUp(200);
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
            header.removeClass('expanded');
        } else {
            content.slideDown(200);
            icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
            header.addClass('expanded');
        }
    },

    // Fetch order list data from server
    async fetchOrderList() {
        try {
            const response = await $.getJSON('fetch_order_list.php', this.currentSort);
            
            if (response.success) {
                this.orderListData = response.data;
                this.groupedData = response.grouped_data;
                
                if (this.isMobile) {
                    this.renderMobileList(response.data);
                } else {
                    this.renderCategorizedDesktopView(response.grouped_data, response.is_admin);
                }
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            console.error('Error fetching order list:', error);
            this.showError('Failed to load order list. Please refresh the page.');
        }
    },

    // Initialize desktop view specific functionality
    initializeDesktopView() {
        // Category functionality will be handled by renderCategorizedDesktopView
    },

    // Initialize mobile view specific functionality
    initializeMobileView() {
        // Set up mobile sort options
        const sortSelect = document.getElementById('mobileSortSelect');
        if (sortSelect) {
            const currentValue = `${this.currentSort.sort_by}-${this.currentSort.sort_direction}`;
            sortSelect.value = currentValue;
        }
    },

    // Render categorized desktop view
    renderCategorizedDesktopView(groupedData, isAdmin) {
        const container = $('#categorizedOrderList');
        container.empty();

        if (!groupedData || Object.keys(groupedData).length === 0) {
            container.append(`
                <div class="no-orders-message">
                    <p>No orders found</p>
                </div>
            `);
            return;
        }

        // Create category sections
        Object.entries(groupedData).forEach(([categoryKey, categoryData]) => {
            const categorySection = this.createCategorySection(categoryKey, categoryData, isAdmin);
            container.append(categorySection);
        });
    },

    // Create a category section with header and collapsible content
    createCategorySection(categoryKey, categoryData, isAdmin) {
        const categoryName = categoryData.category_name;
        const itemCount = categoryData.count;
        const items = categoryData.items;
        
        // Create category header
        const header = $(`
            <div class="category-header expanded" data-category="${categoryKey}">
                <i class="fas fa-chevron-down category-toggle-icon"></i>
                <span class="category-name">${categoryName}</span>
                <span class="category-count">(${itemCount} items)</span>
            </div>
        `);
        
        // Create table for this category
        const table = $(`
            <table class="excel-table category-table">
                <thead>
                    <tr>
                        <th class="col-sku">SKU</th>
                        <th class="col-ean">EAN</th>
                        <th class="col-name">Name</th>
                        ${isAdmin ? '<th class="col-cost">Cost</th>' : ''}
                        ${isAdmin ? '<th class="col-stock">CS Stock</th>' : ''}
                        ${isAdmin ? '<th class="col-stock">AS Stock</th>' : ''}
                        <th class="col-qty">Qty</th>
                        <th class="col-status">Status</th>
                        <th class="col-order-type">Type</th>
                        <th class="col-comment">Comment</th>
                        ${isAdmin ? '<th class="col-comment">Private Comment</th>' : ''}
                        <th class="col-date">Last Ord.</th>
                        <th class="col-date">Added</th>
                        <th class="col-user">User</th>
                        ${isAdmin ? '<th class="col-actions">Actions</th>' : ''}
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        `);
        
        // Populate table with items
        const tbody = table.find('tbody');
        items.forEach(item => {
            const row = this.createDesktopTableRow(item, isAdmin);
            tbody.append(row);
        });
        
        // Create category content wrapper
        const content = $('<div class="category-content"></div>').append(table);
        
        // Create complete category section
        const section = $('<div class="category-section"></div>');
        section.append(header).append(content);
        
        return section;
    },

    // Create a desktop table row
    createDesktopTableRow(item, isAdmin) {
        const row = $('<tr>');
        
        // Add status class to the row
        row.addClass('status-' + item.status);
        
        // Only add order type class if it exists and is urgent
        if (item.order_type && item.order_type === 'urgent') {
            row.addClass('order-type-urgent');
        }
        
        // Add order type class for no_stock
        if (item.order_type && item.order_type === 'no_stock') {
            row.addClass('order-type-no_stock');
        }
        
        // SKU
        row.append($('<td>').text(item.sku || 'N/A'));
        
        // EAN with supplier lookup
        const eanCell = $('<td>').text(item.ean || 'N/A');
        if (this.config.hasSupplierAccess && item.ean && item.ean !== 'N/A') {
            const lookupIcon = $('<i>')
                .addClass('fas fa-search supplier-lookup-icon')
                .attr('title', 'Check supplier availability')
                .attr('data-ean', item.ean);
            eanCell.append(lookupIcon);
        }
        row.append(eanCell);
        
        // Name
        const truncatedName = item.name.length > 50 ? item.name.substring(0, 50) + '...' : item.name;
        row.append($('<td>')
            .text(truncatedName)
            .attr('title', item.name));
        
        // Admin columns
        if (isAdmin) {
            row.append($('<td>').text(`£${item.cost_price || 'N/A'}`));
            row.append($('<td>').text(item.cs_stock || '0'));
            row.append($('<td>').text(item.as_stock || '0'));
        }
        
        // Quantity
        row.append($('<td>').text(item.quantity || 'N/A'));
        
        // Status
        row.append($('<td>')
            .text(item.status)
            .addClass(item.status === 'pending' ? 'status-pending' : 'status-ordered'));
        
        // Order Type
        const orderTypeCell = $('<td>').addClass('order-type-cell');
        if (item.order_type) {
            const typeSpan = $('<span>')
                .text(item.order_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))
                .addClass('order-type-badge ' + item.order_type);
            orderTypeCell.append(typeSpan);
        } else {
            orderTypeCell.text('N/A');
        }
        row.append(orderTypeCell);
        
        // Public Comment
        const publicCommentCell = this.createCommentCell(item.public_comment, 'public', item.id);
        row.append(publicCommentCell);
        
        // Private Comment (admin only)
        if (isAdmin) {
            const privateCommentCell = this.createCommentCell(item.private_comment, 'private', item.id);
            row.append(privateCommentCell);
        }
        
        // Dates
        row.append($('<td>').text(item.last_ordered || 'N/A'));
        row.append($('<td>').text(item.added_on || 'N/A'));
        row.append($('<td>').text(item.requested_by || 'Unknown'));
        
        // Admin actions
        if (isAdmin) {
            const actions = $('<td>');
            
            const statusBtn = $('<button>')
                .addClass('action-btn ' + (item.status === 'pending' ? 'btn-status-ordered' : 'btn-status-pending'))
                .text(item.status === 'pending' ? '✓' : '↺')
                .attr('title', item.status === 'pending' ? 'Mark as Ordered' : 'Mark as Pending')
                .data('id', item.id)
                .data('action', 'status')
                .data('status', item.status === 'pending' ? 'ordered' : 'pending');
            
            const deleteBtn = $('<button>')
                .addClass('action-btn btn-delete')
                .text('×')
                .attr('title', 'Delete')
                .data('id', item.id)
                .data('action', 'delete');
            
            actions.append(statusBtn).append(deleteBtn);
            row.append(actions);
        }
        
        return row;
    },

    // Create a comment cell for the table
    createCommentCell(comment, type, orderId) {
        const cell = $('<td>').addClass('col-comment');
        
        if (this.config.isAdmin) {
            if (comment && comment.trim() !== '') {
                const commentSpan = $('<span>')
                    .addClass(`comment-text ${type}`)
                    .text(comment.length > 15 ? comment.substring(0, 15) + '...' : comment)
                    .attr('title', comment)
                    .data('order-id', orderId)
                    .data('comment-type', type);
                
                const editIcon = $('<i>')
                    .addClass('fas fa-edit comment-edit-icon')
                    .attr('title', 'Edit comment')
                    .data('order-id', orderId);
                
                cell.append(commentSpan).append(editIcon);
            } else {
                const emptyComment = $('<span>')
                    .addClass('comment-text empty')
                    .text('Click to add...')
                    .data('order-id', orderId)
                    .data('comment-type', type);
                
                cell.append(emptyComment);
            }
        } else {
            // Non-admin users can only see public comments
            if (type === 'public' && comment && comment.trim() !== '') {
                cell.text(comment.length > 15 ? comment.substring(0, 15) + '...' : comment)
                    .attr('title', comment)
                    .addClass('comment-text');
            } else {
                cell.text('-');
            }
        }
        
        return cell;
    },

    // Handle comment editing
    handleCommentEdit(e) {
        if (!this.config.isAdmin) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        const orderId = $(e.target).data('order-id');
        const item = this.orderListData.find(item => item.id == orderId);
        
        if (!item) return;
        
        // Populate modal
        $('#commentOrderId').val(orderId);
        $('#publicComment').val(item.public_comment || '');
        $('#privateComment').val(item.private_comment || '');
        $('#commentsModalLabel').text(`Edit Comments - ${item.name}`);
        
        // Show modal
        $('#commentsModal').modal('show');
    },

    // Save comments
    async saveComments(e) {
        e.preventDefault();
        
        const formData = {
            id: $('#commentOrderId').val(),
            public_comment: $('#publicComment').val().trim(),
            private_comment: $('#privateComment').val().trim()
        };
        
        const saveBtn = $('#saveCommentsBtn');
        const originalText = saveBtn.text();
        
        saveBtn.prop('disabled', true).text('Saving...');
        
        try {
            const response = await $.post('update_order_comments.php', formData, null, 'json');
            
            if (response.success) {
                this.showSuccess('Comments updated successfully');
                $('#commentsModal').modal('hide');
                this.fetchOrderList(); // Refresh the list
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            console.error('Error saving comments:', error);
            this.showError('Failed to save comments. Please try again.');
        } finally {
            saveBtn.prop('disabled', false).text(originalText);
        }
    },

    // Handle comment editing by ID (for mobile)
    handleCommentEditById(orderId) {
        if (!this.config.isAdmin) return;
        
        const item = this.orderListData.find(item => item.id == orderId);
        
        if (!item) return;
        
        // Populate modal
        $('#commentOrderId').val(orderId);
        $('#publicComment').val(item.public_comment || '');
        $('#privateComment').val(item.private_comment || '');
        $('#commentsModalLabel').text(`Edit Comments - ${item.name}`);
        
        // Show modal
        $('#commentsModal').modal('show');
    },

    // Render mobile list view
    renderMobileList(data) {
        const container = $('#mobileOrderList');
        container.empty();

        if (data.length === 0) {
            container.append(`
                <div class="mobile-order-item" style="text-align: center; color: #666;">
                    <p>No orders found</p>
                </div>
            `);
            return;
        }

        data.forEach(item => {
            const mobileItem = this.createMobileListItem(item);
            container.append(mobileItem);
        });
    },

    // Create a mobile list item
    createMobileListItem(item) {
        const statusClass = item.status === 'pending' ? 'pending' : 'ordered';
        const orderTypeDisplay = item.order_type ? 
            item.order_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
        
        return $(`
            <div class="mobile-order-item" data-item='${JSON.stringify(item)}' data-order-type="${item.order_type || ''}">
                <div class="mobile-item-header">
                    <div class="mobile-item-title">
                        <div class="mobile-item-sku">${item.sku || 'Non-DB Item'}</div>
                        <div class="mobile-item-name">${item.name}</div>
                    </div>
                    <div class="mobile-item-badges">
                        <div class="mobile-status-badge ${statusClass}">${item.status}</div>
                        <div class="order-type-badge ${item.order_type || ''}">${orderTypeDisplay}</div>
                    </div>
                </div>
                <div class="mobile-item-meta">
                    <span class="mobile-item-qty">Qty: ${item.quantity}</span>
                    <span>Added: ${item.added_on}</span>
                </div>
            </div>
        `);
    },

    // Handle mobile item click to show details
    handleMobileItemClick(e) {
        const item = $(e.currentTarget).data('item');
        this.showMobileModal(item);
    },

    // Show mobile detail modal
    showMobileModal(item) {
        const modal = $('#mobileDetailModal');
        const modalTitle = $('#modalTitle');
        const modalBody = $('#modalBody');
        const modalActionButtons = $('#modalActionButtons');
        
        // Set data attribute for conditional styling
        modal.attr('data-order-type', item.order_type || '');
        
        modalTitle.text(item.name);
        
        // Build modal content
        let modalContent = '';
        
        if (item.sku) {
            modalContent += `<div class="modal-detail-row">
                <span class="modal-detail-label">SKU:</span>
                <span class="modal-detail-value">${item.sku}</span>
            </div>`;
        }
        
        if (item.ean && item.ean !== 'N/A') {
            modalContent += `<div class="modal-detail-row">
                <span class="modal-detail-label">EAN:</span>
                <span class="modal-detail-value">${item.ean}</span>
            </div>`;
        }
        
        modalContent += `
            <div class="modal-detail-row">
                <span class="modal-detail-label">Quantity:</span>
                <span class="modal-detail-value">${item.quantity}</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Status:</span>
                <span class="modal-detail-value status-${item.status}">${item.status}</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Order Type:</span>
                <span class="modal-detail-value">${item.order_type ? item.order_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A'}</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Last Ordered:</span>
                <span class="modal-detail-value">${item.last_ordered || 'Never'}</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Added:</span>
                <span class="modal-detail-value">${item.added_on}</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Requested By:</span>
                <span class="modal-detail-value">${item.requested_by || 'Unknown'}</span>
            </div>
        `;
        
        if (this.config.isAdmin) {
            if (item.cost_price) {
                modalContent += `<div class="modal-detail-row">
                    <span class="modal-detail-label">Cost:</span>
                    <span class="modal-detail-value">£${item.cost_price}</span>
                </div>`;
            }
            
            modalContent += `
                <div class="modal-detail-row">
                    <span class="modal-detail-label">CS Stock:</span>
                    <span class="modal-detail-value">${item.cs_stock || '0'}</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">AS Stock:</span>
                    <span class="modal-detail-value">${item.as_stock || '0'}</span>
                </div>
            `;
        }
        
        // Add comment fields
        if (item.public_comment) {
            modalContent += `<div class="modal-detail-row">
                <span class="modal-detail-label">Comment:</span>
                <span class="modal-detail-value">${item.public_comment}</span>
            </div>`;
        }
        
        if (this.config.isAdmin && item.private_comment) {
            modalContent += `<div class="modal-detail-row">
                <span class="modal-detail-label">Private Comment:</span>
                <span class="modal-detail-value" style="color: #dc3545;">${item.private_comment}</span>
            </div>`;
        }
        
        modalBody.html(modalContent);
        
        // Build action buttons for admin
        let actionButtons = '';
        if (this.config.isAdmin) {
            const statusText = item.status === 'pending' ? 'Mark as Ordered' : 'Mark as Pending';
            const statusClass = item.status === 'pending' ? 'success' : 'primary';
            const statusIcon = item.status === 'pending' ? 'fas fa-check' : 'fas fa-undo';
            
            actionButtons += `
                <button class="modal-action-btn ${statusClass}" data-action="status" data-id="${item.id}" data-status="${item.status === 'pending' ? 'ordered' : 'pending'}">
                    <i class="${statusIcon}"></i>
                    ${statusText}
                </button>
                <button class="modal-action-btn danger" data-action="delete" data-id="${item.id}">
                    <i class="fas fa-trash"></i>
                    Delete
                </button>
            `;
        }
        
        // Add supplier lookup if available
        if (this.config.hasSupplierAccess && item.ean && item.ean !== 'N/A') {
            actionButtons += `
                <button class="modal-action-btn secondary" data-action="supplier" data-ean="${item.ean}">
                    <i class="fas fa-search"></i>
                    Check Suppliers
                </button>
            `;
        }
        
        // Add edit comments button for admins
        if (this.config.isAdmin) {
            actionButtons += `
                <button class="modal-action-btn primary" data-action="edit-comments" data-id="${item.id}">
                    <i class="fas fa-edit"></i>
                    Edit Comments
                </button>
            `;
        }
        
        modalActionButtons.html(actionButtons);
        
        // Show modal
        modal.addClass('show');
        
        // Bind action button events
        $('#modalActionButtons .modal-action-btn').off('click').on('click', (e) => {
            const action = $(e.target).data('action') || $(e.currentTarget).data('action');
            const id = $(e.target).data('id') || $(e.currentTarget).data('id');
            
            if (action === 'status') {
                const status = $(e.target).data('status') || $(e.currentTarget).data('status');
                this.updateOrderStatus(id, status);
            } else if (action === 'delete') {
                this.deleteOrderItem(id);
            } else if (action === 'supplier') {
                const ean = $(e.target).data('ean') || $(e.currentTarget).data('ean');
                this.checkSupplierAvailability(ean);
            } else if (action === 'edit-comments') {
                const id = $(e.target).data('id') || $(e.currentTarget).data('id');
                this.closeMobileModal();
                this.handleCommentEditById(id);
            }
        });
    },

    // Close mobile modal
    closeMobileModal() {
        $('#mobileDetailModal').removeClass('show');
    },

    // Handle modal backdrop click
    handleModalBackdropClick(e) {
        if (e.target === e.currentTarget) {
            this.closeMobileModal();
        }
    },

    // Handle form submission
    async handleFormSubmission(e) {
        e.preventDefault();
        
        if (this.isValidating) {
            this.showInfo('Please wait', 'Validating product...');
            return;
        }

        const formData = this.getFormData();
        const validation = this.validateFormData(formData);
        
        if (!validation.isValid) {
            this.showError(validation.message);
            return;
        }

        await this.submitForm(formData);
    },

    // Get form data
    getFormData() {
        return {
            sku: $('#sku').val().trim(),
            name: $('#name').val().trim(),
            non_db_item: $('#non_db_item').val().trim(),
            quantity: $('#quantity').val(),
            order_type: $('input[name="order_type"]:checked').val()
        };
    },

    // Validate form data
    validateFormData(data) {
        const hasDbInput = data.sku || data.name;
        const hasNonDbInput = data.non_db_item;

        if (!hasDbInput && !hasNonDbInput) {
            return {
                isValid: false,
                message: 'Please enter either a SKU/Product name or a Non-Database item.'
            };
        }

        if (hasDbInput && hasNonDbInput) {
            return {
                isValid: false,
                message: 'Please use either database product fields OR non-database field, not both.'
            };
        }

        if (!data.quantity || data.quantity < 1) {
            return {
                isValid: false,
                message: 'Please enter a valid quantity.'
            };
        }

        return { isValid: true };
    },

    // Submit form data
    async submitForm(formData) {
        const submitBtn = $('#addToOrderListForm button[type="submit"]');
        const originalHtml = submitBtn.html();
        
        // Show loading state
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adding...');
        
        try {
            const response = await $.post('add_to_order_list.php', formData, null, 'json');
            
            if (response.success) {
                this.showSuccess(response.message);
                this.resetForm();
                this.fetchOrderList();
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            this.showError('Failed to add product. Please try again.');
        } finally {
            submitBtn.prop('disabled', false).html(originalHtml);
        }
    },

    // Reset form to initial state
    resetForm() {
        $('#addToOrderListForm')[0].reset();
        this.selectedProduct = null;
        this.isNonDbMode = false;
        $('#sku, #name').removeClass('valid invalid loading');
        $('#productSuggestions').hide();
    },

    // Handle product search
    async handleProductSearch(e) {
        const nameQuery = $(e.target).val().trim();
        this.selectedProduct = null;
        this.isNonDbMode = false;
        
        // Clear other fields if user is typing in name field
        if (nameQuery !== '') {
            $('#sku').val('');
            $('#non_db_item').val('');
        }

        if (nameQuery.length < 3) {
            $('#productSuggestions').hide();
            return;
        }

        try {
            const results = await $.getJSON('search_products.php', { q: nameQuery });
            this.renderProductSuggestions(results);
        } catch (error) {
            console.error('Error searching products:', error);
            this.showSuggestionsError('Error searching products');
        }
    },

    // Render product suggestions
    renderProductSuggestions(results) {
        const suggestions = $('#productSuggestions');
        suggestions.empty();

        if (results.length > 0) {
            results.forEach(product => {
                const displayText = product.name;
                let subText = `SKU: ${product.sku}`;
                if (product.manufacturer) subText += ` | ${product.manufacturer}`;
                if (product.ean) subText += ` | EAN: ${product.ean}`;

                suggestions.append(`
                    <a href="#" class="dropdown-item" 
                       data-sku="${product.sku}" 
                       data-name="${product.name}"
                       data-manufacturer="${product.manufacturer || ''}"
                       data-ean="${product.ean || ''}">
                        <strong>${displayText}</strong><br>
                        <small class="text-muted">${subText}</small>
                    </a>
                `);
            });
            suggestions.show();
        } else {
            suggestions.html('<div class="dropdown-item-text text-muted"><em>No products found in database</em></div>').show();
        }
    },

    // Show suggestions error
    showSuggestionsError(message) {
        $('#productSuggestions')
            .html(`<div class="dropdown-item-text text-danger"><em>${message}</em></div>`)
            .show();
    },

    // Handle suggestion selection
    handleSuggestionSelection(e) {
        e.preventDefault();
        
        // Only allow selection of actual products (not error messages)
        if (!$(e.target).closest('.dropdown-item').hasClass('dropdown-item-text')) {
            const item = $(e.target).closest('.dropdown-item');
            this.selectedProduct = {
                sku: item.data('sku'),
                name: item.data('name'),
                manufacturer: item.data('manufacturer'),
                ean: item.data('ean')
            };
            
            $('#name').val(this.selectedProduct.name);
            $('#sku').val(this.selectedProduct.sku);
            
            // Add visual feedback
            $('#name').removeClass('invalid').addClass('valid');
            $('#sku').removeClass('invalid').addClass('valid');
        }
        
        $('#productSuggestions').hide();
    },

    // Handle SKU input
    handleSkuInput(e) {
        const currentSku = $(e.target).val().trim();
        
        // Clear other fields if user is typing in SKU field
        if (currentSku !== '') {
            $('#name').val('');
            $('#non_db_item').val('');
            this.selectedProduct = null;
            this.isNonDbMode = false;
        }
        
        // Remove validation classes while typing
        $(e.target).removeClass('valid invalid');
    },

    // Handle SKU validation
    async handleSkuValidation(e) {
        const sku = $(e.target).val().trim();
        
        if (sku === '') {
            $(e.target).removeClass('valid invalid');
            this.selectedProduct = null;
            return;
        }
        
        // Skip validation if already selected
        if (this.selectedProduct && this.selectedProduct.sku === sku) {
            return;
        }
        
        this.isValidating = true;
        $(e.target).addClass('loading');
        
        try {
            const results = await $.getJSON('search_products.php', { q: sku });
            const exactMatch = results.find(p => p.sku.toLowerCase() === sku.toLowerCase());
            
            if (exactMatch) {
                this.selectedProduct = exactMatch;
                $('#name').val(exactMatch.name);
                $('#sku').removeClass('invalid loading').addClass('valid');
                $('#name').removeClass('invalid').addClass('valid');
            } else {
                this.selectedProduct = null;
                $('#name').val('');
                $('#sku').removeClass('valid loading').addClass('invalid');
                $('#name').removeClass('valid');
            }
        } catch (error) {
            console.error('Error validating SKU:', error);
            this.selectedProduct = null;
            $('#sku').removeClass('valid loading').addClass('invalid');
        } finally {
            this.isValidating = false;
        }
    },

    // Handle non-DB item input
    handleNonDbInput(e) {
        const nonDbValue = $(e.target).val().trim();
        
        // Clear other fields if user is typing in non-db field
        if (nonDbValue !== '') {
            $('#sku').val('');
            $('#name').val('');
            this.selectedProduct = null;
            this.isNonDbMode = true;
            
            // Hide suggestions
            $('#productSuggestions').hide();
            
            // Remove validation classes from other fields
            $('#sku, #name').removeClass('valid invalid');
        } else {
            this.isNonDbMode = false;
        }
    },

    // Handle input clearing
    handleInputClearing(e) {
        if ($(e.target).val().trim() === '') {
            $(e.target).removeClass('valid invalid');
            const fieldId = $(e.target).attr('id');
            
            if (fieldId === 'name') {
                $('#sku').removeClass('valid invalid');
            } else if (fieldId === 'sku') {
                $('#name').removeClass('valid invalid');
            } else if (fieldId === 'non_db_item') {
                $('#sku, #name').removeClass('valid invalid');
                this.isNonDbMode = false;
            }
            
            this.selectedProduct = null;
        }
    },

    // Handle outside click to hide suggestions
    handleOutsideClick(e) {
        if (!$(e.target).closest('#name, #productSuggestions').length) {
            $('#productSuggestions').hide();
        }
    },

    // Handle desktop table sorting
    handleSorting(e) {
        e.preventDefault();

        const sortBy = $(e.target).data('sort');
        const currentDirection = $(e.target).data('direction') || 'ASC';
        const newDirection = currentDirection === 'ASC' ? 'DESC' : 'ASC';

        this.currentSort.sort_by = sortBy;
        this.currentSort.sort_direction = newDirection;

        // Update UI indicators
        $('.sort-link').each(function () {
            $(this).data('direction', 'ASC');
            $(this).find('i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
        });

        $(e.target)
            .data('direction', newDirection)
            .find('i')
            .removeClass('fa-sort')
            .addClass(newDirection === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');

        this.fetchOrderList();
    },

    // Handle mobile sorting
    handleMobileSorting(e) {
        const value = $(e.target).val();
        const [sortBy, direction] = value.split('-');
        
        this.currentSort.sort_by = sortBy;
        this.currentSort.sort_direction = direction;
        
        this.fetchOrderList();
    },

    // Handle action buttons (status/delete)
    handleActionButton(e) {
        const action = $(e.target).data('action');
        const id = $(e.target).data('id');
        
        if (action === 'status') {
            const status = $(e.target).data('status');
            this.updateOrderStatus(id, status);
        } else if (action === 'delete') {
            this.deleteOrderItem(id);
        }
    },

    // Update order status
    async updateOrderStatus(id, status) {
        try {
            const response = await $.post('update_order_status.php', { 
                id: id, 
                status: status 
            }, null, 'json');
            
            if (response.success) {
                this.showSuccess('Status updated successfully.');
                this.fetchOrderList();
                this.closeMobileModal(); // Close modal if open
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            console.error('Error updating status:', error);
            this.showError('Failed to update status. Please try again.');
        }
    },

    // Delete order item
    async deleteOrderItem(id) {
        const result = await Swal.fire({
            title: 'Are you sure?',
            text: 'This will delete the item from the order list.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        });

        if (result.isConfirmed) {
            try {
                const response = await $.post('delete_order_item.php', { id: id }, null, 'json');
                
                if (response.success) {
                    this.showSuccess('Item deleted successfully.');
                    this.fetchOrderList();
                    this.closeMobileModal(); // Close modal if open
                } else {
                    this.showError(response.message);
                }
            } catch (error) {
                console.error('Error deleting item:', error);
                this.showError('Failed to delete item. Please try again.');
            }
        }
    },

    // Handle supplier lookup
    handleSupplierLookup(e) {
        const ean = $(e.target).data('ean');
        this.checkSupplierAvailability(ean);
    },

    // Check supplier availability
    async checkSupplierAvailability(ean) {
        // Show loading state
        $(`.supplier-lookup-icon[data-ean="${ean}"]`).addClass('loading');
        
        try {
            const suppliers = await $.getJSON('get_supplier_availability.php', { ean: ean });
            this.showSupplierModal(ean, suppliers);
        } catch (error) {
            console.error('Error fetching supplier data:', error);
            this.showError('Failed to fetch supplier data. Please try again.');
        } finally {
            $(`.supplier-lookup-icon[data-ean="${ean}"]`).removeClass('loading');
        }
    },

    // Show supplier modal
    showSupplierModal(ean, suppliers) {
        let modalContent = `
            <div class="ean-info">
                <strong>EAN:</strong> ${ean}
            </div>
        `;

        if (suppliers.length === 0) {
            modalContent += `
                <div class="no-suppliers">
                    <i class="fas fa-info-circle" style="font-size: 2rem; color: #6c757d; margin-bottom: 1rem;"></i>
                    <p>No suppliers currently have this item in stock.</p>
                </div>
            `;
        } else {
            modalContent += `
                <p><strong>Available from ${suppliers.length} supplier(s):</strong></p>
                <table class="supplier-details-table">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Supplier SKU</th>
                            <th>Quantity</th>
                            <th>Cost</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            suppliers.forEach(supplier => {
                modalContent += `
                    <tr>
                        <td class="supplier-name">${supplier.supplier}</td>
                        <td>${supplier.supplier_sku || 'N/A'}</td>
                        <td class="supplier-qty">${supplier.qty}</td>
                        <td class="supplier-cost">£${supplier.cost}</td>
                        <td>${supplier.time_recorded || 'N/A'}</td>
                    </tr>
                `;
            });

            modalContent += `
                    </tbody>
                </table>
            `;
        }

        $('#supplierModalBody').html(modalContent);
        $('#supplierModal').modal('show');
    },

    // Utility methods for showing messages
    showSuccess(message) {
        Swal.fire({
            title: 'Success',
            text: message,
            icon: 'success',
            timer: 3000,
            timerProgressBar: true
        });
    },

    showError(message) {
        Swal.fire({
            title: 'Error',
            text: message,
            icon: 'error'
        });
    },

    showInfo(title, message) {
        Swal.fire({
            title: title,
            text: message,
            icon: 'info',
            timer: 1500,
            timerProgressBar: true
        });
    }
};

// Font Awesome CSS loading
if (!document.querySelector('link[href*="font-awesome"]')) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
    document.head.appendChild(link);
}