<?php
require 'bootstrap.php';

$message= '' ;

foreach ($_POST['items_ids'] as $key => $record_id) {
  
  // Get the details of the stock suppliers product
  $response = $DB->query(" SELECT * FROM all_supplier_stock WHERE id=? LIMIT 1", [$record_id]);
  if (!empty($response)) {
    $stock_details =$response[0] ;

    // Check if the manufacturer and mpn at all_supplier_stock table match the manufacturer and mpn at master_products table or not 
    $manufacturer = trim($stock_details['manufacturer']);
    $mpn = trim($stock_details['mpn']);
    $response = $DB->query(" SELECT * FROM master_products WHERE manufacturer=? AND mpn=? ", [$manufacturer, $mpn]);
    if (!empty($response)) {
      // If their is a match then update qty , cost, supplier & supplier_sku at master_products table 
      $product_details = $response[0] ;
      $response = $DB->query("UPDATE master_products SET qty=? ,cost=? , supplier=? ,supplier_sku=? WHERE sku=?" , [$stock_details['qty'], $stock_details['cost'], $stock_details['supplier'], $stock_details['supplier_sku'], $product_details['sku']]);
      if ($response>0) {
        $message .= "Product With ID : ( ".$record_id . " ) Updated <br>";
      }else{
        $message .= "Product With ID : ( ".$record_id . " ) Not Updated <br>";
      }
    }else{
      // If their is no match then insert new record with these values [ name, category (this will update first_import_category field), manufacturer, mpn, ean, qty, cost, supplier, supplier_sku ] at master_products table 
      extract($stock_details);
      $response = $DB->query(" INSERT INTO master_products (name,first_import_category,manufacturer,mpn,ean,qty,cost, supplier,supplier_sku) VALUES (?, ?, ?,? ,? ,?, ?, ?, ?) " , [$name , $category, $manufacturer, $mpn, $ean, $qty, $cost, $supplier, $supplier_sku]);
      if ($response>0) {
        $message .= "Product With ID : ( ".$record_id . " ) Added <br>";
      }else{
        $message .= "Product With ID : ( ".$record_id . " ) Not Added <br>";
      }
    }
  }
}
echo $message ;