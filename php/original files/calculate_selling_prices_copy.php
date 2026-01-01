<?php
require 'bootstrap.php';
$output = '';
$sql = "UPDATE  master_products SET price = CASE
                     WHEN  pricing_method = 0 THEN (cost +(cost * (retail_markup/100)))
                     WHEN  pricing_method = 1 THEN (pricing_cost +(pricing_cost * (retail_markup/100)))
                END ";
$response = $DB->query($sql);
if($response>0) $output = "Retail inc_done ";

$sql = "UPDATE  master_products SET trade = CASE
                     WHEN  pricing_method = 0 THEN (cost + (cost * (trade_markup/100)))
                     WHEN  pricing_method = 1 THEN (pricing_cost + (pricing_cost * (trade_markup/100)))
                END ";
$response = $DB->query($sql);
if($response>0) {
  if(!empty($output)) $output .= ' & ';
  $output .= "Trade inc_done ";
}
echo $output;