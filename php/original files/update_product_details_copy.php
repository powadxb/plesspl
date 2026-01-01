<?php
require 'bootstrap.php';

$tax_rate = $DB->query(" SELECT * FROM tax_rates WHERE tax_rate_id=?", [$_POST['tax_rate_id']])[0]['tax_rate'];

$category_details = explode("|" , $_POST['categories']);
$category_id = $category_details[0];
$pless_main_category = $category_details[1];
$pos_category = $category_details[2];

$enable = isset($_POST['enable'])?'y':'n';
$export_to_magento = isset($_POST['export_to_magento'])?'y':'n';

if($_POST['pricing_method']==2){
  $price = $_POST['fixed_retail_pricing'] / (1+$tax_rate);
  $trade = $_POST['fixed_trade_pricing'] / (1+$tax_rate);
}else{
  $price = $_POST['retail_inc_vat'] / (1+$tax_rate);
  $trade = $_POST['trade_inc_vat'] / (1+$tax_rate);
}
$price = round($price , 2);
$trade = round($trade, 2);

$response = $DB->query(" UPDATE master_products SET name=?, manufacturer=?, mpn=?, ean=?, category_id=?, pless_main_category=?, pos_category=?, supplier=?, enable=?, export_to_magento=?, cost=?, pricing_cost=?, pricing_method=?, tax_rate_id=?, retail_markup=?, trade_markup=?, fixed_retail=?, fixed_trade=?, price=?, trade=? WHERE sku=? ", [$_POST['name'], $_POST['manufacturer'], $_POST['mpn'], $_POST['ean'], $category_id, $pless_main_category, $pos_category, $_POST['supplier'], $enable, $export_to_magento, $_POST['cost'], $_POST['pricing_cost'], $_POST['pricing_method'] , $_POST['tax_rate_id'], $_POST['retail_markup'], $_POST['trade_markup'], $_POST['fixed_retail_pricing'], $_POST['fixed_trade_pricing'], $price, $trade, $_POST['sku']]);

if($response>0) echo 'updated';