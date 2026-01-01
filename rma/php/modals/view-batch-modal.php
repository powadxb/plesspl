<!-- View/Edit Batch Modal -->
<div class="modal fade" id="viewBatchModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-box-open"></i> Batch #<span id="viewBatchId">-</span>
                    <span class="badge ms-2" id="viewBatchStatusBadge">-</span>
                </h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                
                <!-- Batch Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Batch Information</h6>
                        
                        <div class="mb-2">
                            <strong>Supplier:</strong> 
                            <span id="viewSupplier">-</span>
                        </div>
                        
                        <div class="mb-2">
                            <strong>RMA Number:</strong>
                            <input type="text" class="form-control form-control-sm d-inline-block" 
                                   id="viewRMANumber" style="width: 250px;">
                            <button type="button" class="btn btn-sm btn-primary" id="btnUpdateRMANumber">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </div>
                        
                        <div class="mb-2">
                            <strong>Status:</strong>
                            <select class="form-select form-select-sm d-inline-block" 
                                    id="viewBatchStatus" style="width: 200px;">
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                                <option value="shipped">Shipped</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-primary" id="btnUpdateStatus">
                                <i class="fas fa-save"></i> Update
                            </button>
                        </div>

                        <div class="mb-2">
                            <strong>Created:</strong> <span id="viewCreated">-</span>
                        </div>
                        <div class="mb-2">
                            <strong>Created By:</strong> <span id="viewCreatedBy">-</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Shipping &amp; Dates</h6>
                        
                        <div class="mb-2">
                            <strong>Date Submitted:</strong> 
                            <input type="date" class="form-control form-control-sm d-inline-block" 
                                   id="viewDateSubmitted" style="width: 200px;">
                        </div>
                        
                        <div class="mb-2">
                            <strong>Date Shipped:</strong> 
                            <input type="date" class="form-control form-control-sm d-inline-block" 
                                   id="viewDateShipped" style="width: 200px;">
                        </div>
                        
                        <div class="mb-2">
                            <strong>Tracking Number:</strong> 
                            <input type="text" class="form-control form-control-sm d-inline-block" 
                                   id="viewShippingTracking" style="width: 250px;" 
                                   placeholder="Courier tracking">
                        </div>
                        
                        <div class="mb-2">
                            <strong>Shipping Cost:</strong> 
                            <div class="input-group input-group-sm d-inline-flex" style="width: 150px;">
                                <span class="input-group-text">£</span>
                                <input type="number" class="form-control" id="viewShippingCost" 
                                       step="0.01" min="0">
                            </div>
                        </div>

                        <button type="button" class="btn btn-sm btn-success mt-2" id="btnUpdateShipping">
                            <i class="fas fa-save"></i> Save Shipping Info
                        </button>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Notes</h6>
                    <textarea class="form-control" id="viewNotes" rows="3"></textarea>
                    <button type="button" class="btn btn-sm btn-primary mt-2" id="btnUpdateNotes">
                        <i class="fas fa-save"></i> Save Notes
                    </button>
                </div>

                <!-- Batch Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Items</h6>
                                <h3 id="viewTotalItems">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Value</h6>
                                <h3 id="viewTotalValue" class="text-success">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Credited</h6>
                                <h3 id="viewCredited" class="text-warning">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Pending</h6>
                                <h3 id="viewPending" class="text-info">-</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items in Batch -->
                <h6 class="text-muted mb-3">Items in This Batch</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Tracking</th>
                                <th>SKU</th>
                                <th>Product</th>
                                <th>Serial</th>
                                <th>Fault</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th>Credited</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="batchItemsList">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Danger Zone (for draft batches only) -->
                <div id="dangerZone" style="display: none;">
                    <hr>
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">Danger Zone</h6>
                        <p class="mb-2">This batch is in <strong>draft</strong> status. You can:</p>
                        <button type="button" class="btn btn-sm btn-warning" id="btnRemoveItems">
                            <i class="fas fa-minus-circle"></i> Remove Items from Batch
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="btnDeleteBatch">
                            <i class="fas fa-trash"></i> Delete Entire Batch
                        </button>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnPrintBatch">
                    <i class="fas fa-print"></i> Print Batch Sheet
                </button>
            </div>
        </div>
    </div>
</div>