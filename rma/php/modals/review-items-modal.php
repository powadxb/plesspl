<!-- Review Items Modal (Authorized Staff Only) -->
<div class="modal fade" id="reviewItemsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Items Needing Review
                    <small style="display: block; font-size: 0.85em;">These items need supplier information assigned</small>
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 80px;">Action</th>
                                <th>Barcode/Tracking</th>
                                <th>Serial</th>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Location</th>
                                <th>Discovered</th>
                            </tr>
                        </thead>
                        <tbody id="reviewItemsList">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading items...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Supplier Modal -->
<div class="modal fade" id="assignSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-building"></i> Assign Supplier Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="assignProductInfo" class="alert alert-info">
                    <!-- Product info will be inserted here -->
                </div>

                <form id="assignSupplierForm">
                    <input type="hidden" id="assignRmaId">

                    <div class="form-group">
                        <label for="assignSupplierName">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="assignSupplierName" 
                               placeholder="e.g., Exertis Ltd" required>
                        <small class="form-text text-muted">Enter the correct supplier name (e.g., "Exertis Ltd" not "exertis")</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="assignDocumentNumber">Document Number</label>
                            <input type="text" class="form-control" id="assignDocumentNumber" 
                                   placeholder="e.g., INV-12345">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="assignDocumentDate">Document Date</label>
                            <input type="date" class="form-control" id="assignDocumentDate">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="markAsResolved"> 
                            Mark as reviewed (clear "needs review" flag)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Save Supplier Details
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
