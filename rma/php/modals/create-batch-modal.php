<!-- Create Batch Modal -->
<div class="modal fade" id="createBatchModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle"></i> Create Supplier RMA Batch
                </h5>
                <button type="button" class="btn-close btn-close-white" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                
                <!-- Step 1: Select Supplier and Items -->
                <div id="createStep1">
                    <h6 class="mb-3">Step 1: Select Supplier &amp; Items</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Supplier *</label>
                            <select class="form-select" id="batchSupplierSelect" required>
                                <option value="">-- Select Supplier --</option>
                                <!-- Populated dynamically from unprocessed items -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Quick Filter</label>
                            <input type="text" class="form-control" id="itemQuickFilter" 
                                   placeholder="Filter by SKU, name, serial...">
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Select items with <strong>status = unprocessed</strong> that have supplier information.
                        Items without suppliers need review first.
                    </div>

                    <!-- Available Items Table -->
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover" id="availableItemsTable">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th style="width: 50px;">
                                        <input type="checkbox" id="selectAllItems" title="Select All">
                                    </th>
                                    <th>Tracking</th>
                                    <th>SKU</th>
                                    <th>Product</th>
                                    <th>Serial</th>
                                    <th>Fault</th>
                                    <th>Cost</th>
                                    <th>Discovered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated when supplier selected -->
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Selected Items:</strong> 
                            <span id="selectedCount" class="badge bg-primary">0</span>
                            <span class="ms-3"><strong>Total Value:</strong> 
                                <span id="selectedValue" class="text-primary">£0.00</span>
                            </span>
                        </div>
                        <button type="button" class="btn btn-primary" id="btnNextToStep2" disabled>
                            Next: Batch Details <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Batch Details -->
                <div id="createStep2" style="display: none;">
                    <h6 class="mb-3">Step 2: Batch Details</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Supplier</label>
                            <input type="text" class="form-control" id="batchSupplierDisplay" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Number of Items</label>
                            <input type="text" class="form-control" id="batchItemCount" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Supplier RMA Number</label>
                            <input type="text" class="form-control" id="batchRMANumber" 
                                   placeholder="Optional - can be added later">
                            <small class="text-muted">The RMA number provided by the supplier</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Initial Status *</label>
                            <select class="form-select" id="batchInitialStatus" required>
                                <option value="draft">Draft (can edit later)</option>
                                <option value="submitted">Submitted (RMA number issued)</option>
                            </select>
                            <small class="text-muted">Select 'Submitted' for quick batch creation</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea class="form-control" id="batchNotes" rows="3" 
                                  placeholder="Any additional notes about this batch..."></textarea>
                    </div>

                    <!-- Summary -->
                    <div class="alert alert-success">
                        <h6 class="alert-heading">Batch Summary</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Items:</strong> <span id="summaryItems">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Total Value:</strong> <span id="summaryValue">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Status:</strong> <span id="summaryStatus">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" id="btnBackToStep1">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="btn btn-success" id="btnSubmitBatch">
                            <i class="fas fa-check"></i> Create Batch
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>