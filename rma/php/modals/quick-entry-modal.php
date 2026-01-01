<!-- Quick Entry Modal -->
<div class="modal fade" id="quickEntryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="zmdi zmdi-plus-circle"></i> Quick RMA Entry
                    <small style="display: block; font-size: 0.8em; margin-top: 5px;">Target: 30 seconds</small>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="quickEntryForm">
                    <!-- Step 1: Serial Number Lookup -->
                    <div class="form-section active" id="step1">
                        <h6 class="section-title">Step 1: Scan Serial Number</h6>
                        <div class="form-group">
                            <label for="serialNumber">Serial Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-lg" 
                                       id="serialNumber" 
                                       placeholder="Scan or enter serial number" 
                                       autofocus
                                       style="font-size: 1.2em; background: #fffacd;">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button" id="lookupSerialBtn">
                                        <i class="fas fa-search"></i> Lookup
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Press Enter or click Lookup after scanning</small>
                        </div>

                        <div id="serialNotFoundSection" style="display: none;">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Serial number not found in database. Please enter product details manually.
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="manualSku">SKU <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="manualSku" 
                                           placeholder="Enter SKU">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="manualEan">EAN (Optional)</label>
                                    <input type="text" class="form-control" id="manualEan" 
                                           placeholder="Enter EAN if available">
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-primary" id="validateSkuBtn">
                                <i class="fas fa-check"></i> Validate SKU
                            </button>
                        </div>

                        <div id="multipleMatchesSection" style="display: none; margin-top: 15px;">
                            <div id="multipleMatchesContent">
                                <!-- Multiple matches will be inserted here by JavaScript -->
                            </div>
                        </div>

                        <div id="productInfoSection" style="display: none; margin-top: 15px;">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Product found!
                            </div>
                            <table class="table table-sm table-bordered">
                                <tr>
                                    <th style="width: 150px;">SKU:</th>
                                    <td id="displaySku"></td>
                                </tr>
                                <tr>
                                    <th>Product Name:</th>
                                    <td id="displayProductName"></td>
                                </tr>
                                <tr>
                                    <th>EAN:</th>
                                    <td id="displayEan"></td>
                                </tr>
                                <tr id="supplierRow" style="<?=(!$is_authorized) ? 'display:none;' : ''?>">
                                    <th>Supplier:</th>
                                    <td id="displaySupplier"></td>
                                </tr>
                            </table>
                            
                            <button type="button" class="btn btn-success btn-block" id="continueToFaultBtn">
                                <i class="fas fa-arrow-right"></i> Continue to Fault Selection
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Fault Type -->
                    <div class="form-section" id="step2" style="display: none;">
                        <h6 class="section-title">Step 2: Select Fault Type</h6>
                        
                        <div class="form-group">
                            <label for="faultType">Fault Type <span class="text-danger">*</span></label>
                            <select class="form-control form-control-lg" id="faultType" style="font-size: 1.2em;">
                                <option value="">-- Select Fault Type --</option>
                                <?php foreach($fault_types as $fault): ?>
                                <option value="<?=$fault['id']?>"><?=htmlspecialchars($fault['fault_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="faultDescription">Additional Notes (Optional)</label>
                            <textarea class="form-control" id="faultDescription" rows="3" 
                                      placeholder="Enter any additional fault details..."></textarea>
                        </div>

                        <button type="button" class="btn btn-secondary" id="backToStep1Btn">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="btn btn-success" id="continueToIdBtn">
                            <i class="fas fa-arrow-right"></i> Continue to Barcode/Tracking
                        </button>
                    </div>

                    <!-- Step 3: Barcode/Tracking -->
                    <div class="form-section" id="step3" style="display: none;">
                        <h6 class="section-title">Step 3: Barcode or Tracking Number</h6>
                        
                        <div class="form-group">
                            <label>Choose One:</label>
                            <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                                <label class="btn btn-outline-primary active flex-fill">
                                    <input type="radio" name="idMethod" value="barcode" checked> 
                                    <i class="fas fa-barcode"></i> Preprinted Barcode
                                </label>
                                <label class="btn btn-outline-primary flex-fill">
                                    <input type="radio" name="idMethod" value="tracking"> 
                                    <i class="fas fa-hashtag"></i> Tracking Number
                                </label>
                            </div>
                        </div>

                        <div id="barcodeSection">
                            <div class="form-group">
                                <label for="barcodeInput">Scan Barcode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" 
                                       id="barcodeInput" 
                                       placeholder="Scan DRMA barcode"
                                       style="font-size: 1.2em; background: #e3f2fd;">
                                <small class="form-text text-muted">Format: DRMA1, DRMA2, etc.</small>
                            </div>
                        </div>

                        <div id="trackingSection" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                No barcode sticker available. A tracking number will be generated automatically.
                            </div>
                            <div class="form-group">
                                <label>Generated Tracking Number:</label>
                                <input type="text" class="form-control form-control-lg" 
                                       id="generatedTracking" 
                                       readonly
                                       style="font-size: 1.2em; background: #f0f0f0;">
                            </div>
                        </div>

                        <button type="button" class="btn btn-secondary" id="backToStep2Btn">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn btn-success" id="saveRmaBtn">
                            <i class="fas fa-save"></i> Save RMA
                        </button>
                    </div>

                    <!-- Hidden fields -->
                    <input type="hidden" id="hiddenSku">
                    <input type="hidden" id="hiddenProductName">
                    <input type="hidden" id="hiddenEan">
                    <input type="hidden" id="hiddenSupplier">
                    <input type="hidden" id="hiddenDocumentId">
                    <input type="hidden" id="hiddenDocumentNumber">
                    <input type="hidden" id="hiddenDocumentDate">
                    <input type="hidden" id="hiddenCost">
                    <input type="hidden" id="hiddenNeedsReview" value="0">
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.form-section {
    padding: 20px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: #f9f9f9;
}

.section-title {
    font-weight: bold;
    color: #2196F3;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #2196F3;
}

#quickEntryModal .modal-body {
    padding: 15px;
}

#quickEntryModal .form-control-lg {
    border-width: 2px;
}

#quickEntryModal .btn {
    padding: 10px 20px;
}

.input-group-lg > .form-control {
    height: calc(2.5em + 1rem + 2px);
}
</style>