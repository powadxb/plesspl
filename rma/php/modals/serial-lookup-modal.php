<!-- Serial Number Lookup Modal - Multiple Matches -->
<!-- NOTE: This modal requires serial-lookup.js to be loaded AFTER jQuery -->
<div class="modal fade" id="serialLookupModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-search"></i> Multiple Products Found
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Multiple products were found with this serial number. Please select the correct one:</p>
                
                <div class="table-responsive">
                    <table class="table table-hover" id="serialMatchesTable">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Serial Number</th>
                                <th class="supplier-column" style="display:none;">Supplier</th>
                                <th class="supplier-column" style="display:none;">Document</th>
                                <th class="financial-column" style="display:none;">Cost</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="serialMatchesBody">
                            <!-- Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
</div>