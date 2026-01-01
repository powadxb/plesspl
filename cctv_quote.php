<?php
session_start();
$page_title = 'CCTV System Quote';
require 'php/bootstrap.php';

// Ensure session is active
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check if user has permission for this page
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'cctv_quote'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Check if user is super admin (level 2) for showing profit margins
$is_admin = $user_details['admin'] >= 2;

// Check if we're in edit mode
$edit_quote_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$is_edit_mode = $edit_quote_id > 0;

require 'assets/header.php';
require 'assets/navbar.php';
?>

<script>
// Add admin class to body for CSS targeting
<?php if ($is_admin): ?>
document.body.classList.add('admin-user');
<?php endif; ?>
</script>

<div class="col text-right">
    <a href="cctv_quotes.php" class="btn btn-info btn-sm">Search Quotes</a>
    <a href="pc_quote.php" class="btn btn-secondary btn-sm">PC Quote</a>
</div>

<div class="cctv-quote-container">
    <!-- Hidden field to store quote ID for editing -->
    <input type="hidden" id="quote_id" value="<?php echo $edit_quote_id; ?>">
    
    <?php if ($is_edit_mode): ?>
    <div class="alert alert-warning" role="alert">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>⚠️ Edit Mode:</strong> You are editing CCTV Quote #<?php echo $edit_quote_id; ?>
            </div>
            <div>
                <a href="view_cctv_quote.php?id=<?php echo $edit_quote_id; ?>" class="btn btn-sm btn-secondary">Cancel</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Header Section -->
    <div class="header-controls">
        <div class="customer-controls">
            <div class="search-box">
                <input type="text" id="customerSearch" placeholder="Search customer..." class="form-control form-control-sm">
                <button id="searchCustomerBtn" class="btn btn-primary btn-sm">Find</button>
                <button id="newCustomerBtn" class="btn btn-success btn-sm">New</button>
            </div>
            <div id="customerDetails" style="display: none;" class="selected-customer-details">
                <!-- Customer details will appear here -->
            </div>
        </div>
        <div class="quote-controls">
            <div class="price-type-toggle">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-sm btn-primary active" data-type="R">R</button>
                    <button class="btn btn-sm btn-outline-primary" data-type="T">T</button>
                </div>
            </div>
            <div class="cost-toggle-control">
                <button type="button" class="btn btn-xs btn-outline-secondary" id="toggleCostInfo" title="Show Cost Information" style="<?php echo !$is_admin ? 'display: none;' : ''; ?>">
                    SC
                </button>
            </div>
        </div>
    </div>

    <div class="quote-grid">
        <!-- Left Column -->
        <div class="components-column">
            <h6 class="section-title">Recording Equipment</h6>
            
            <!-- Recorder -->
            <div class="component-section" data-component="recorder">
                <label>DVR/NVR Recorder</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Recorder...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Hard Drives -->
            <div class="component-section" data-component="hdd" data-allow-multiple="true">
                <label>Hard Drive(s)</label>
                <div class="multi-component-list" id="hddList">
                    <!-- Multiple HDDs will be added here -->
                </div>
                <button class="btn btn-sm btn-info btn-add-multi" data-target="hdd">+ Add Hard Drive</button>
            </div>

            <h6 class="section-title mt-4">Network & Power</h6>
            
            <!-- PoE Switch -->
            <div class="component-section" data-component="poe_switch">
                <label>PoE Switch</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select PoE Switch...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Network Switch -->
            <div class="component-section" data-component="network_switch">
                <label>Network Switch</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Network Switch...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Power Supplies -->
            <div class="component-section" data-component="power_supply" data-allow-multiple="true">
                <label>Power Supply(s)</label>
                <div class="multi-component-list" id="powerSupplyList">
                    <!-- Multiple power supplies will be added here -->
                </div>
                <button class="btn btn-sm btn-info btn-add-multi" data-target="power_supply">+ Add Power Supply</button>
            </div>

            <!-- UPS -->
            <div class="component-section" data-component="ups">
                <label>UPS (Battery Backup)</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select UPS...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Monitor -->
            <div class="component-section" data-component="monitor">
                <label>Monitor/Display</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Monitor...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="components-column">
            <h6 class="section-title">Cameras</h6>
            
            <!-- Cameras -->
            <div class="component-section" data-component="camera" data-allow-multiple="true">
                <label>Camera(s)</label>
                <div class="multi-component-list" id="cameraList">
                    <!-- Multiple cameras will be added here -->
                </div>
                <button class="btn btn-sm btn-info btn-add-multi" data-target="camera">+ Add Camera</button>
            </div>

            <h6 class="section-title mt-4">Cabling & Connectivity</h6>

            <!-- Camera Cable -->
            <div class="component-section" data-component="camera_cable" data-allow-multiple="true">
                <label>Camera Cable</label>
                <div class="multi-component-list" id="cameraCableList">
                    <!-- Multiple cable types will be added here -->
                </div>
                <button class="btn btn-sm btn-info btn-add-multi" data-target="camera_cable">+ Add Cable Type</button>
            </div>

            <!-- Connectors -->
            <div class="component-section" data-component="connectors" data-allow-multiple="true">
                <label>Connectors</label>
                <div class="multi-component-list" id="connectorsList">
                    <!-- Multiple connector types will be added here -->
                </div>
                <button class="btn btn-sm btn-info btn-add-multi" data-target="connectors">+ Add Connector Type</button>
            </div>

            <!-- Internet Cable -->
            <div class="component-section" data-component="internet_cable">
                <label>Internet Cable</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Cable...">
                    <select class="form-control form-control-sm item-qty">
                        <option value="1">1x</option>
                        <option value="2">2x</option>
                        <option value="3">3x</option>
                        <option value="4">4x</option>
                        <option value="5">5x</option>
                    </select>
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- HDMI/VGA Cable -->
            <div class="component-section" data-component="video_cable">
                <label>HDMI/VGA Cable</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Cable...">
                    <select class="form-control form-control-sm item-qty">
                        <option value="1">1x</option>
                        <option value="2">2x</option>
                        <option value="3">3x</option>
                    </select>
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Power Extension -->
            <div class="component-section" data-component="power_extension">
                <label>Power Extension</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Extension...">
                    <select class="form-control form-control-sm item-qty">
                        <option value="1">1x</option>
                        <option value="2">2x</option>
                        <option value="3">3x</option>
                    </select>
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <h6 class="section-title mt-4">Installation Materials</h6>

            <!-- Mounting Brackets -->
            <div class="component-section" data-component="mounting" data-allow-multiple="true">
                <label>Mounting Brackets</label>
                <div class="multi-component-list" id="mountingList">
                    <!-- Multiple mounting types will be added here -->
                </div>
                <button class="btn btn-sm btn-info btn-add-multi" data-target="mounting">+ Add Mounting</button>
            </div>

            <!-- Cable Management -->
            <div class="component-section" data-component="cable_management" data-allow-multiple="true">
                <label>Cable Management</label>
                <div class="multi-component-list" id="cableManagementList">
                    <!-- Multiple items will be added here -->
                </div>
                <button class="btn btn-sm btn-info btn-add-multi" data-target="cable_management">+ Add Item</button>
            </div>

            <!-- Accessories Section -->
            <div class="additional-items">
                <div class="section-header">
                    <label>Additional Items</label>
                    <button id="addItemBtn" class="btn btn-sm btn-success">+ Add Item</button>
                </div>
                <div id="additionalItemsList">
                    <!-- Additional items will be listed here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Services Section -->
    <div class="services-section">
        <h6 class="section-title">Services</h6>
        <div class="service-grid">
            <div class="service-item">
                <label>Installation Labor</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text">£</span>
                    </div>
                    <input type="number" id="installationCharge" value="0.00" step="0.01" class="form-control form-control-sm">
                </div>
            </div>
            <div class="service-item">
                <label>Configuration & Setup</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text">£</span>
                    </div>
                    <input type="number" id="configCharge" value="0.00" step="0.01" class="form-control form-control-sm">
                </div>
            </div>
            <div class="service-item">
                <label>Testing & Commissioning</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text">£</span>
                    </div>
                    <input type="number" id="testingCharge" value="0.00" step="0.01" class="form-control form-control-sm">
                </div>
            </div>
        </div>
    </div>

    <!-- Totals Section -->
    <div class="totals-section">
        <div class="totals">
            <span>Total: £<span id="totalAmount">0.00</span></span>
            <span class="vat-text">(Inc. VAT)</span>
            <span class="profit cost-sensitive" style="<?php echo !$is_admin ? 'display: none;' : ''; ?>">
                Profit: £<span id="totalProfit">0.00</span>
            </span>
        </div>
        <div class="actions">
            <button id="loadTemplateBtn" class="btn btn-info btn-sm">
                <i class="fas fa-folder-open"></i> Load Template
            </button>
            <button id="saveQuoteBtn" class="btn btn-success btn-sm" disabled>
                <?php echo $is_edit_mode ? 'Update Quote' : 'Save Quote'; ?>
            </button>
            <button id="printQuoteBtn" class="btn btn-primary btn-sm" disabled>Print</button>
            <?php if ($is_edit_mode): ?>
            <a href="view_cctv_quote.php?id=<?php echo $edit_quote_id; ?>" class="btn btn-secondary btn-sm">Cancel</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Template Save Modal -->
<div id="templateSaveModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Save Quote</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Template Name (Optional)</label>
                    <input type="text" id="templateName" class="form-control" placeholder="e.g., 4 Camera Standard Setup">
                    <small class="form-text text-muted">Leave blank for regular quote, or name it to use as a template later</small>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="saveAsTemplate">
                    <label class="form-check-label" for="saveAsTemplate">
                        Save as reusable template (won't link to customer)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmSaveQuote">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Template Load Modal -->
<div id="templateLoadModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Load Template</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="templateList">
                    <!-- Templates will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Search Modal -->
<div id="searchModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Product</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="search-controls">
                    <div class="input-group">
                        <input type="text" id="productSearchInput" class="form-control" 
                               placeholder="Search for products...">
                        <div class="input-group-append">
                            <button class="btn btn-primary" id="searchProductsBtn">Search</button>
                        </div>
                    </div>
                </div>
                <div class="search-results mt-3">
                    <table class="table table-sm table-hover" id="searchResultsTable">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Name</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="searchResultsBody">
                            <!-- Results will be populated here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manual Entry Modal -->
<div id="manualEntryModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manual Entry</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" id="manualProductName" class="form-control">
                </div>
                <div class="form-group">
                    <label>Price (Inc. VAT)</label>
                    <input type="number" id="manualProductPrice" class="form-control" step="0.01">
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" id="manualProductQty" class="form-control" value="1" min="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveManualEntry">Add</button>
            </div>
        </div>
    </div>
</div>

<style>
.cctv-quote-container {
    max-width: 1400px;
    margin: 20px auto;
    padding: 15px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.header-controls {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 20px;
    gap: 20px;
}

.customer-controls {
    flex: 2;
}

.quote-controls {
    display: flex;
    gap: 15px;
    align-items: center;
}

.search-box {
    display: flex;
    gap: 5px;
}

.quote-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.section-title {
    font-weight: bold;
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
    margin-bottom: 15px;
}

.component-section {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
}

.component-input {
    display: flex;
    gap: 5px;
    margin-top: 5px;
}

.component-input input[type="text"] {
    flex: 1;
}

.item-qty {
    width: 70px;
}

.component-details {
    font-size: 0.875rem;
    color: #666;
    margin-top: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.price-display {
    display: flex;
    gap: 10px;
    align-items: center;
}

.price-editable {
    background: #fff3cd;
    border: 1px solid #ffc107;
    padding: 2px 5px;
    border-radius: 3px;
    cursor: pointer;
    font-weight: bold;
}

.price-editable:hover {
    background: #ffe69c;
}

.price-input {
    width: 80px;
    padding: 2px 5px;
}

.cost-info {
    font-size: 0.75rem;
    color: #6c757d;
}

/* Cost toggle button - subdued and small */
.cost-toggle-control {
    display: flex;
    align-items: center;
}

#toggleCostInfo {
    font-size: 0.7rem;
    padding: 3px 8px;
    transition: all 0.3s ease;
    min-width: 32px;
    font-weight: 600;
}

#toggleCostInfo.active {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

.price-type-toggle .btn-group {
    display: flex;
}

.price-type-toggle .btn {
    min-width: 40px;
}

/* Hide cost-sensitive elements when costs are toggled off */
body.hide-costs .cost-sensitive,
body.hide-costs .cost-info {
    display: none !important;
}

/* Additional security: hide costs for non-admin users regardless of toggle state */
body:not(.admin-user) .cost-sensitive,
body:not(.admin-user) .cost-info,
body:not(.admin-user) #toggleCostInfo {
    display: none !important;
}

.multi-component-list {
    margin: 10px 0;
}

.multi-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 8px;
    margin-bottom: 8px;
}

.multi-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.multi-item-name {
    font-weight: 500;
    flex: 1;
}

.multi-item-controls {
    display: flex;
    gap: 5px;
    align-items: center;
}

.multi-item-qty {
    width: 60px;
}

.multi-item-details {
    font-size: 0.875rem;
    color: #666;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 5px;
}

.btn-add-multi {
    width: 100%;
    margin-top: 5px;
}

.additional-items {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    margin-top: 10px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.additional-item-row {
    background: #fff;
    padding: 10px;
    margin-bottom: 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.additional-item-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.additional-item-info {
    flex: 1;
}

.item-name {
    display: block;
    font-size: 0.95rem;
}

.item-price {
    display: block;
    font-size: 0.85rem;
    margin-top: 2px;
}

.additional-item-buttons {
    display: flex;
    gap: 5px;
}

.services-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.service-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.service-item label {
    display: block;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.totals-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.totals {
    font-size: 1.2em;
    font-weight: bold;
}

.vat-text {
    font-size: 0.8em;
    color: #666;
    margin-left: 5px;
    font-weight: normal;
}

.profit {
    margin-left: 20px;
    color: #28a745;
}

.selected-customer-details {
    margin-top: 10px;
    padding: 10px;
    background: #e7f3ff;
    border-radius: 4px;
    font-size: 0.875rem;
}

.price-type-toggle {
    display: flex;
    align-items: center;
}

.modal {
    z-index: 1050;
}

.modal-lg {
    max-width: 900px;
}

.search-results {
    max-height: 400px;
    overflow-y: auto;
}

@media (max-width: 1200px) {
    .quote-grid {
        grid-template-columns: 1fr;
    }
    
    .service-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .btn, .header-controls, .actions, .cost-toggle-control {
        display: none !important;
    }
    
    .cost-sensitive, .cost-info {
        display: none !important;
    }
}

/* Template Modal Styles */
.template-item {
    transition: all 0.2s ease;
}

.template-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.template-item h5 {
    color: #007bff;
    font-size: 1.1rem;
}

.template-item .badge {
    font-size: 0.85rem;
    margin-right: 5px;
}

#templateList .list-group {
    max-height: 400px;
    overflow-y: auto;
}
</style>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="assets/js/cctv_quote/search.js"></script>
<script src="assets/js/cctv_quote/calculations.js"></script>
<script src="assets/js/cctv_quote/ui-handlers.js"></script>

<script>
// Set admin status for JavaScript
const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;

// Cost visibility toggle
$(document).ready(function() {
    // Hide costs by default for security (even for admins)
    $('body').addClass('hide-costs');
    $('#toggleCostInfo').removeClass('active');
    
    // Only allow admin users to toggle costs
    if (IS_ADMIN) {
        $('#toggleCostInfo').click(function() {
            const isHidden = $('body').hasClass('hide-costs');
            
            if (isHidden) {
                // Show costs
                $('body').removeClass('hide-costs');
                $(this).addClass('active');
                $(this).attr('title', 'Hide Cost Information');
            } else {
                // Hide costs
                $('body').addClass('hide-costs');
                $(this).removeClass('active');
                $(this).attr('title', 'Show Cost Information');
            }
        });
    }
});
</script>

<?php require 'assets/footer.php'; ?>