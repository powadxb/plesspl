<!-- Update Record Modal -->
<div class="modal fade" id="updateRecordPopup" tabindex="-1" aria-labelledby="exampleModalLabel"
     aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Update Record Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="updateRecordContent">
        <!-- This content will be populated via AJAX from php/get_product_details.php -->
        <!-- The AJAX response should include the stock_status checkbox in the same position -->
        <!-- 
        Expected structure when populated:
        <form method="POST" id="updateDetailsForm">
          <div class="row">
            <div class="col-sm-4" style="border-right: 3px solid #007bff;">
              [Basic fields like name, manufacturer, etc.]
              
              Status Checkboxes including:
              <div class="form-check">
                <div class="checkbox">
                  <label for="u-stock_status" class="form-check-label">
                    <input type="checkbox" id="u-stock_status" name="stock_status" value="1" 
                           class="form-check-input" [checked based on current value]> In Stock
                  </label>
                </div>
              </div>
            </div>
            <div class="col-sm-8">
              [Pricing calculations section]
            </div>
          </div>
        </form>
        -->
      </div>
    </div>
  </div>
</div>
<!-- End Update Record Modal -->