<?php
require 'bootstrap.php';

$all_manufacturers = $DB->query(" SELECT * FROM master_pless_manufacturers ORDER BY manufacturer_name ASC");
$tax_rates = $DB->query(" SELECT * FROM tax_rates ORDER BY name ASC");
$categories = $DB->query(" SELECT * FROM master_categories ORDER BY pos_category ASC");

$response = $DB->query(" SELECT * FROM master_products WHERE sku=?", [$_POST['sku']]);
if(!empty($response)) {
  $details = $response[0];
}else{
  die();
}

?>

<form method="POST" id="updateDetailsForm">
  <input type="hidden" name="sku" value="<?=$details['sku']?>">
  <div class="row">
    <div class="col-sm-4" style="border-right: 3px solid #007bff;">
      <div class="form-group">
        <input type="text" id="u-name" name="name" class="form-control" placeholder="Name" value="<?=$details['name']?>">
      </div>
      <div class="form-group">
        <select id="u-manufacturer" name="manufacturer" class="form-control dropDownMenu requiredField">
          <option value="" selected>Select Manufacturer</option>
          <?php foreach($all_manufacturers as $manufacturer): ?>
          <option value="<?=$manufacturer['manufacturer_name']?>" <?=($details['manufacturer']==$manufacturer['manufacturer_name'])?'selected':''?> ><?=$manufacturer['manufacturer_name']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <input type="text" id="u-mpn" name="mpn" class="form-control" placeholder="Mpn" value="<?=$details['mpn']?>">
      </div>
      <div class="form-group">
        <input type="text" id="u-ean" name="ean" class="form-control" placeholder="Barcode" value="<?=$details['ean']?>">
      </div>
      <div class="form-group">
        <select id="u-categories" name="categories" class="form-control dropDownMenu requiredField">
          <option value="" selected>Select Category</option>
          <?php foreach($categories as $category): ?>
          <?php $category_txt = $category['id'].'|'.$category['pless_main_category'].'|'.$category['pos_category']; ?>
          <option value="<?=$category_txt?>" <?=($category['id']==$details['category_id'])?'selected':''?>><?=$category['pless_main_category']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <select id="u-supplier" name="supplier" class="form-control dropDownMenu">
          <option value="" selected>Select Supplier</option>
        </select>
      </div>
      <div class="form-check">
        <div class="checkbox">
          <label for="enable" class="form-check-label ">
            <input type="checkbox" id="u-enable" name="enable" value="enabled" class="form-check-input" <?=($details['enable']=='y')?'checked':''?>> Enabled
          </label>
        </div>
      </div>
      <div class="form-check">
        <div class="checkbox">
          <label for="export_to_magento" class="form-check-label ">
            <input type="checkbox" id="u-export_to_magento" name="export_to_magento" value="export_to_magento" class="form-check-input" <?=($details['export_to_magento']=='y')?'checked':''?>> On WWW
          </label>
        </div>
      </div>
      <div class="form-check">
        <div class="checkbox">
          <label for="on_pos" class="form-check-label ">
            <input type="checkbox" id="u-on_pos" name='on_pos' value="on_pos" class="form-check-input" disabled> On POS
          </label>
        </div>
      </div>
    </div>
    <div class="col-sm-8">
      <div class="row ml-0 mr-0">
        <div class="form-group col-sm-3">
          <label for="cost">Cost</label>
          <input type="text" id="u-cost" name="cost" class="form-control doCalculations cost" placeholder="Cost" value="<?=$details['cost']?>">
        </div>
        <div class="form-group col-sm-3">
          <label for="pricing_cost">Pricing Cost</label>
          <input type="text" id="u-pricing_cost" name="pricing_cost" class="form-control doCalculations pricing_cost" placeholder="Pricing Cost" value="<?=$details['pricing_cost']?>">
        </div>
        <div class="form-group col-sm-6">
          <label for="pricing_method">Pricing Method</label>
          <select name="pricing_method" id="u-pricing_method" class="form-control doCalculations pricing_method">
            <option value="0" <?=($details['pricing_method']==0)?'selected':''?> >Markup on cost</option>
            <option value="1" <?=($details['pricing_method']==1)?'selected':''?> >Markup on P'Cost</option>
            <option value="2" <?=($details['pricing_method']==2)?'selected':''?> >Fixed Price</option>
          </select>
        </div>
      </div>
      <div class="row ml-0 mr-0">
        <div class="form-group col-sm-4">
          <label for="targetRetail">Target Retail Inc Vat</label>
          <input type="text" id="u-targetRetail" name="target_retail" class="form-control bg-secondary text-white whitePlaceholder calculationField doCalculations targetRetail" placeholder="Target Retail Inc Vat">
          <p class="text-muted"> Profit = £<span id="u-targetRetailProfit" class="targetRetailProfit">0.00</span></p>
          <p class="text-muted"> Markup = <span id="u-targetRetailPercent" class="targetRetailPercent">0.00</span>%</p>
        </div>
        <div class="form-group col-sm-4">
          <label for="targetTrade">Target Trade Inc Vat</label>
          <input type="text" id="targetTrade" name="target_trade" class="form-control bg-secondary text-white whitePlaceholder calculationField doCalculations targetTrade" placeholder="Target Trade Inc Vat"  >
          <p class="text-muted"> Profit = £<span id="u-targetTradeProfit" class="targetTradeProfit">0.00</span></p>
          <p class="text-muted"> Markup = <span id="u-targetTradePercent" class="targetTradePercent">0.00</span>%</p>
        </div>
        <div class="form-group col-sm-4">
          <label for="vatScheme">Vat Scheme</label>
          <select name="tax_rate_id" id="u-vatScheme" class="form-control dropDownMenu doCalculations vatScheme">
            <?php foreach($tax_rates as $tax_rate): ?>
              <option value="<?=$tax_rate['tax_rate_id']?>" data-tax_rate="<?=$tax_rate['tax_rate']?>" <?=($details['tax_rate_id']==$tax_rate['tax_rate_id'])?'selected':''?> ><?=$tax_rate['name']?></option>
              <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row ml-0 mr-0">
        <div class="form-group col-sm-6 markupPriceMehtod">
          <label for="retailMarkup">Retail Markup %</label>
          <input type="text" id="u-retailMarkup" name="retail_markup" class="form-control bg-success text-white whitePlaceholder calculationField doCalculations retailMarkup" placeholder="Retail Markup %" value="<?=$details['retail_markup']?>">
          <p class="text-muted"> Profit = <span id="u-retailMarkupProfit" class="retailMarkupProfit">0.00</span></p>
          <p class="text-muted incVatPrice"> Inc Vat = <span id="u-retailMarkupIncVatPrice" class="retailMarkupIncVatPrice">0.00</span></p>
        </div>
        <div class="form-group col-sm-6 markupPriceMehtod">
          <label for="tradeMarkup">Trade Markup %</label>
          <input type="text" id="u-tradeMarkup" name="trade_markup" class="form-control bg-primary text-white whitePlaceholder calculationField doCalculations tradeMarkup" placeholder="Trade Markup %" value="<?=$details['trade_markup']?>">
          <p class="text-muted"> Profit = <span id="u-tradeMarkupProfit" class="tradeMarkupProfit">0.00</span></p>
          <p class="text-muted incVatPrice"> Inc Vat = <span id="u-tradeMarkupIncVatPrice" class="tradeMarkupIncVatPrice">0.00</span></p>
        </div>
        <div class="form-group col-sm-6 fixedPriceMethod">
          <label for="fixedRetailPricing">Fixed Retail Pricing</label>
          <input type="text" id="u-fixedRetailPricing" name="fixed_retail_pricing" class="form-control bg-success text-white whitePlaceholder calculationField doCalculations fixedRetailPricing" placeholder="Fixed Retail Pricing" value="<?=$details['fixed_retail']?>">
          <p class="text-muted"> Profit = <span id="u-fixedRetailPricingProfit" class="fixedRetailPricingProfit">0.00</span></p>
        </div>
        <div class="form-group col-sm-6 fixedPriceMethod">
          <label for="fixedTradePricing">Fixed Trade Pricing</label>
          <input type="text" id="u-fixedTradePricing" name="fixed_trade_pricing" class="form-control bg-primary text-white whitePlaceholder calculationField doCalculations fixedTradePricing" placeholder="Fixed Trade Pricing" value="<?=$details['fixed_trade']?>">
          <p class="text-muted"> Profit = <span id="u-fixedTradePricingProfit" class="fixedTradePricingProfit">0.00</span></p>
        </div>
      </div>
    </div>

  </div>
  <hr>
  <button class="btn btn-success d-block mx-auto">Update Details</button>
  
  <input type="hidden" name="retail_inc_vat" class="retailIncVat" value="<?=($details['pricing_method']!=2)?$details['price']:null?>" >
  <input type="hidden" name="trade_inc_vat" class="tradeIncVat" value="<?=($details['pricing_method']!=2)?$details['trade']:null?>" >
</form>