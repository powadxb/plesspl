<?php
session_start();
$page_title = 'PC Build Quote';
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
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'pc_quote'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Check if user is admin (for showing profit margins)
$is_admin = $user_details['admin'] > 0;

// Check if we're in edit mode
$edit_quote_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$is_edit_mode = $edit_quote_id > 0;

require 'assets/header.php';
require 'assets/navbar.php';
?>
<div class="col text-right">
    <a href="quotes.php" class="btn btn-info btn-sm">Search Quotes</a>
</div>
<div class="pc-quote-container">
    <!-- Hidden field to store quote ID for editing -->
    <input type="hidden" id="quote_id" value="<?php echo $edit_quote_id; ?>">
    
    <?php if ($is_edit_mode): ?>
    <div class="alert alert-warning" role="alert" id="priceWarning">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>⚠️ Price Notice:</strong> You are editing Quote #<?php echo $edit_quote_id; ?> with original prices. 
                <span id="priceStatus">Prices may be outdated.</span>
            </div>
            <div>
                <button class="btn btn-sm btn-primary" id="refreshPricesBtn">
                    <i class="fas fa-sync-alt"></i> Update to Latest Prices
                </button>
                <button class="btn btn-sm btn-secondary" id="revertPricesBtn" style="display: none;">
                    <i class="fas fa-undo"></i> Revert to Original Prices
                </button>
            </div>
        </div>
    </div>
    <div class="alert alert-info" role="alert" style="display: none;">
        <strong>📋 Copy Mode:</strong> Creating a new quote based on Quote #<?php echo $edit_quote_id; ?>. 
        The original quote will remain unchanged.
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
            <!-- Hidden fields for customer data -->
            <input type="hidden" id="customer_id" value="">
            <input type="hidden" id="customer_name" value="">
            <input type="hidden" id="customer_email" value="">
            <input type="hidden" id="customer_phone" value="">
            <input type="hidden" id="customer_address" value="">
        </div>
        <div class="quote-controls">
            <div class="price-type-toggle">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-sm btn-primary active" data-type="R">R</button>
                    <button class="btn btn-sm btn-outline-primary" data-type="T">T</button>
                </div>
            </div>
            <div class="build-charge">
                <label>Build: £</label>
                <input type="number" id="buildCharge" value="100.00" step="0.01" class="form-control form-control-sm">
            </div>
        </div>
    </div>

    <div class="quote-grid">
        <!-- Left Column -->
        <div class="components-column">
            <!-- CPU Section -->
            <div class="component-section" data-component="cpu">
                <label>CPU</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select CPU...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Cooler Section -->
            <div class="component-section" data-component="cooler">
                <label>Cooler</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Cooler...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- RAM Section -->
            <div class="component-section" data-component="ram">
                <label>RAM</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select RAM...">
                    <select class="form-control form-control-sm ram-qty">
                        <option value="1">1x</option>
                        <option value="2" selected>2x</option>
                        <option value="4">4x</option>
                    </select>
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Graphics Card Section -->
            <div class="component-section" data-component="gpu">
                <label>Graphics Card</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Graphics Card...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Motherboard Section -->
            <div class="component-section" data-component="motherboard">
                <label>Motherboard</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Motherboard...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- PSU Section -->
            <div class="component-section" data-component="psu">
                <label>PSU</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Power Supply...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Case Section -->
            <div class="component-section" data-component="case">
                <label>Case</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Case...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="components-column">
            <!-- Storage Sections -->
            <div class="component-section" data-component="ssd1">
                <label>SSD 1</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Primary Storage...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <div class="component-section" data-component="ssd2">
                <label>SSD 2</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Secondary Storage...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <div class="component-section" data-component="ssd3">
                <label>SSD 3</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select Additional Storage...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- WiFi Section -->
            <div class="component-section" data-component="wifi">
                <label>WiFi</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select WiFi...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- OS Section -->
            <div class="component-section" data-component="os">
                <label>Operating System</label>
                <div class="component-input">
                    <input type="text" readonly class="form-control form-control-sm component-display" placeholder="Select OS...">
                    <button class="btn btn-sm btn-primary btn-search">🔍</button>
                    <button class="btn btn-sm btn-success btn-manual">+</button>
                    <button class="btn btn-sm btn-danger btn-remove" style="display:none;">×</button>
                </div>
                <div class="component-details"></div>
            </div>

            <!-- Additional Items Section -->
            <div class="additional-items">
                <div class="section-header">
                    <label>Additional Items</label>
                    <button id="addItemBtn" class="btn btn-sm btn-success">Add Item</button>
                </div>
                <div id="additionalItemsList">
                    <!-- Additional items will be listed here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Totals Section -->
    <div class="totals-section">
        <div class="totals">
            <span>Total: £<span id="totalAmount">0.00</span></span>
            <span class="vat-text">(Inc. VAT)</span>
            <?php if ($is_admin): ?>
            <span class="profit" id="profitDisplay" style="display: none;">
                Profit: £<span id="totalProfit">0.00</span>
                <button type="button" class="btn btn-sm btn-link" id="toggleProfit" style="padding: 0; margin-left: 5px;">
                    <i class="fas fa-eye"></i>
                </button>
            </span>
            <?php endif; ?>
        </div>
        <div class="actions">
            <button id="saveQuoteBtn" class="btn btn-success btn-sm">
                <?php echo $is_edit_mode ? 'Save as New Quote' : 'Save Quote'; ?>
            </button>
            <button id="printQuoteBtn" class="btn btn-primary btn-sm">Print</button>
            <?php if ($is_edit_mode): ?>
            <a href="view_quote.php?id=<?php echo $edit_quote_id; ?>" class="btn btn-secondary btn-sm">Cancel</a>
            <?php endif; ?>
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
                               placeholder="Type any part of SKU, name, or product details...">
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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveManualEntry">Add</button>
            </div>
        </div>
    </div>
</div>

<style>
.pc-quote-container {
    max-width: 1200px;
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

.component-input input {
    flex: 1;
}

.component-details {
    font-size: 0.875rem;
    color: #666;
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

.totals-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.ram-qty {
    width: 70px;
}

.selected-customer-details {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 0.875rem;
}

/* Modal styling */
.modal {
    z-index: 1050;
}

.modal-lg {
    max-width: 800px;
}

.search-results {
    max-height: 400px;
    overflow-y: auto;
}

/* Make form controls more compact */
.form-control-sm {
    height: calc(1.5em + 0.5rem + 2px);
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Price type toggle styling */
.price-type-toggle .btn {
    min-width: 40px;
    padding: 0.25rem 0.5rem;
}

/* Build charge input styling */
.build-charge {
    display: flex;
    align-items: center;
    gap: 5px;
}

.build-charge input {
    width: 100px;
}

/* Component details styling */
.component-details {
    padding: 3px 0;
    min-height: 22px;
}

/* Additional items list styling */
#additionalItemsList {
    margin-top: 10px;
}

/* Additional items styling - FIXED VERSION */
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

/* Totals styling */
.totals {
    font-size: 1.1em;
}

.vat-text {
    font-size: 0.8em;
    color: #666;
    margin-left: 5px;
}

.profit {
    margin-left: 20px;
    color: #28a745;
}

/* Search results table styling */
#searchResultsTable th,
#searchResultsTable td {
    padding: 0.5rem;
    vertical-align: middle;
}

.btn-remove {
    background-color: #dc3545;
    color: white;
}
</style>

<!-- Add jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Add our PC Quote scripts -->
<script src="assets/js/pc_quote/search.js"></script>
<script src="assets/js/pc_quote/calculations.js"></script>
<script src="assets/js/pc_quote/ui-handlers.js"></script>
<script src="assets/js/pc_quote/edit-handler.js"></script>

<?php require 'assets/footer.php'; ?>