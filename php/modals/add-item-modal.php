<!-- Add New Item Modal -->
<div class="modal fade" id="newItemPopup" tabindex="-1" aria-labelledby="exampleModalLabel"
     aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Add New Item</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form method="POST" id="newItemForm">
          <div class="row">
            <div class="col-sm-4" style="border-right: 3px solid #007bff;">
              <!-- Basic fields -->
              <div class="form-group">
                <input type="text" id="name" name="name" class="form-control requiredField" placeholder="Name">
              </div>
              <div class="form-group">
                <select id="manufacturer" name="manufacturer" class="form-control dropDownMenu requiredField">
                  <option value="" selected>Select Manufacturer</option>
                  <?php foreach($all_manufacturers as $m): ?>
                  <option value="<?=$m['manufacturer_name']?>"><?=$m['manufacturer_name']?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <input type="text" id="mpn" name="mpn" class="form-control" placeholder="Mpn">
              </div>
              <div class="form-group">
                <input type="text" id="ean" name="ean" class="form-control" placeholder="Barcode">
              </div>
              <div class="form-group">
                <select id="categories" name="categories" class="form-control dropDownMenu requiredField">
                  <option value="" selected>Select Category</option>
                  <?php foreach($categories as $c): ?>
                  <option value="<?=$c['id'].'|'.$c['pless_main_category'].'|'.$c['pos_category']?>">
                    <?=$c['pless_main_category']?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <select id="supplier" name="supplier" class="form-control dropDownMenu">
                  <option value="" selected>Select Supplier</option>
                </select>
              </div>
              
              <!-- Status Checkboxes -->
              <div class="form-check">
                <div class="checkbox">
                  <label for="enable" class="form-check-label">
                    <input type="checkbox" id="enable" name="enable" value="enabled" class="form-check-input" checked> Enabled
                  </label>
                </div>
              </div>
              <div class="form-check">
                <div class="checkbox">
                  <label for="export_to_magento" class="form-check-label">
                    <input type="checkbox" id="export_to_magento" name="export_to_magento" value="export_to_magento"
                           class="form-check-input" checked> On WWW
                  </label>
                </div>
              </div>
              <div class="form-check">
                <div class="checkbox">
                  <label for="stock_status" class="form-check-label">
                    <input type="checkbox" id="stock_status" name="stock_status" value="1" class="form-check-input" checked> In Stock
                  </label>
                </div>
              </div>
              <div class="form-check">
                <div class="checkbox">
                  <label for="on_pos" class="form-check-label">
                    <input type="checkbox" id="on_pos" name='on_pos' value="on_pos" class="form-check-input" disabled> On POS
                  </label>
                </div>
              </div>
            </div>
            <!-- Pricing & Calculations -->
            <div class="col-sm-8">
              <div class="row ml-0 mr-0">
                <div class="form-group col-sm-3">
                  <label for="cost">Cost</label>
                  <input type="text" id="cost" name="cost" class="form-control doCalculations cost" placeholder="Cost">
                </div>
                <div class="form-group col-sm-3">
                  <label for="pricing_cost">Pricing Cost</label>
                  <input type="text" id="pricing_cost" name="pricing_cost" class="form-control doCalculations pricing_cost" placeholder="Pricing Cost">
                </div>
                <div class="form-group col-sm-6">
                  <label for="pricing_method">Pricing Method</label>
                  <select name="pricing_method" id="pricing_method" class="form-control doCalculations pricing_method">
                    <option value="0">Markup on cost</option>
                    <option value="1" selected>Markup on P'Cost</option>
                    <option value="2">Fixed Price</option>
                  </select>
                </div>
              </div>
              <div class="row ml-0 mr-0">
                <div class="form-group col-sm-4">
                  <label for="targetRetail">Target Retail Inc Vat</label>
                  <input type="text" id="targetRetail" name="target_retail"
                         class="form-control bg-secondary text-white whitePlaceholder calculationField doCalculations targetRetail"
                         placeholder="Target Retail Inc Vat">
                  <p class="text-muted">
                    Profit = £<span id="targetRetailProfit" class="targetRetailProfit calculationResult">0.00</span>
                  </p>
                  <p class="text-muted">
                    Markup = <span id="targetRetailPercent" class="targetRetailPercent calculationResult">0.00</span>%
                  </p>
                </div>
                <div class="form-group col-sm-4">
                  <label for="targetTrade">Target Trade Inc Vat</label>
                  <input type="text" id="targetTrade" name="target_trade"
                         class="form-control bg-secondary text-white whitePlaceholder calculationField doCalculations targetTrade"
                         placeholder="Target Trade Inc Vat">
                  <p class="text-muted">
                    Profit = £<span id="targetTradeProfit" class="targetTradeProfit calculationResult">0.00</span>
                  </p>
                  <p class="text-muted">
                    Markup = <span id="targetTradePercent" class="targetTradePercent calculationResult">0.00</span>%
                  </p>
                </div>
                <div class="form-group col-sm-4">
                  <label for="vatScheme">Vat Scheme</label>
                  <select name="tax_rate_id" id="vatScheme" class="form-control dropDownMenu doCalculations vatScheme">
                    <?php foreach($tax_rates as $tax): ?>
                    <option value="<?=$tax['tax_rate_id']?>" data-tax_rate="<?=$tax['tax_rate']?>"
                            <?= ($tax['tax_rate']==0.2) ? "selected" : '' ?>>
                      <?=$tax['name']?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row ml-0 mr-0">
                <div class="form-group col-sm-6 markupPriceMehtod">
                  <label for="retailMarkup">Retail Markup %</label>
                  <input type="text" id="retailMarkup" name="retail_markup"
                         class="form-control bg-success text-white whitePlaceholder calculationField doCalculations retailMarkup"
                         placeholder="Retail Markup %">
                  <p class="text-muted">
                    Profit = £<span id="retailMarkupProfit" class="retailMarkupProfit calculationResult">0.00</span>
                  </p>
                  <p class="text-muted incVatPrice">
                    Inc Vat = £<span id="retailMarkupIncVatPrice" class="retailMarkupIncVatPrice calculationResult">0.00</span>
                  </p>
                </div>
                <div class="form-group col-sm-6 markupPriceMehtod">
                  <label for="tradeMarkup">Trade Markup %</label>
                  <input type="text" id="tradeMarkup" name="trade_markup"
                         class="form-control bg-primary text-white whitePlaceholder calculationField doCalculations tradeMarkup"
                         placeholder="Trade Markup %">
                  <p class="text-muted">
                    Profit = £<span id="tradeMarkupProfit" class="tradeMarkupProfit calculationResult">0.00</span>
                  </p>
                  <p class="text-muted incVatPrice">
                    Inc Vat = £<span id="tradeMarkupIncVatPrice" class="tradeMarkupIncVatPrice calculationResult">0.00</span>
                  </p>
                </div>
                <div class="form-group col-sm-6 fixedPriceMethod">
                  <label for="fixedRetailPricing">Fixed Retail Pricing</label>
                  <input type="text" id="fixedRetailPricing" name="fixed_retail_pricing"
                         class="form-control bg-success text-white whitePlaceholder calculationField doCalculations fixedRetailPricing"
                         placeholder="Fixed Retail Pricing">
                  <p class="text-muted">
                    Profit = £<span id="fixedRetailPricingProfit" class="fixedRetailPricingProfit calculationResult">0.00</span>
                  </p>
                </div>
                <div class="form-group col-sm-6 fixedPriceMethod">
                  <label for="fixedTradePricing">Fixed Trade Pricing</label>
                  <input type="text" id="fixedTradePricing" name="fixed_trade_pricing"
                         class="form-control bg-primary text-white whitePlaceholder calculationField doCalculations fixedTradePricing"
                         placeholder="Fixed Trade Pricing">
                  <p class="text-muted">
                    Profit = £<span id="fixedTradePricingProfit" class="fixedTradePricingProfit calculationResult">0.00</span>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <hr>
          <button class="btn btn-success d-block mx-auto">Add New Item</button>
          <input type="hidden" name="retail_inc_vat" class="retailIncVat">
          <input type="hidden" name="trade_inc_vat" class="tradeIncVat">
        </form>
      </div>
    </div>
  </div>
</div>
<!-- End Add New Item Modal -->