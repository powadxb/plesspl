<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'bootstrap.php';

try {
    // Fetch the tax rate
    $tax_rate_stmt = $DB->query("SELECT * FROM tax_rates WHERE tax_rate_id=?", [$_POST['tax_rate_id']]);
    if (empty($tax_rate_stmt)) {
        throw new Exception("Tax rate not found.");
    }
    $tax_rate = $tax_rate_stmt[0]['tax_rate'];

    // Extract category details
    $category_details = explode("|", $_POST['categories']);
    if (count($category_details) < 3) {
        throw new Exception("Invalid category details.");
    }
    $category_id = $category_details[0];
    $pless_main_category = $category_details[1];
    $pos_category = $category_details[2];

    // Set enable and export_to_magento flags
    $enable = isset($_POST['enable']) ? 'y' : 'n';
    $export_to_magento = isset($_POST['export_to_magento']) ? 'y' : 'n';

    // Validate and convert numeric values
    $fixed_retail_pricing = isset($_POST['fixed_retail_pricing']) && is_numeric($_POST['fixed_retail_pricing']) ? (float)$_POST['fixed_retail_pricing'] : 0;
    $fixed_trade_pricing = isset($_POST['fixed_trade_pricing']) && is_numeric($_POST['fixed_trade_pricing']) ? (float)$_POST['fixed_trade_pricing'] : 0;
    $retail_inc_vat = isset($_POST['retail_inc_vat']) && is_numeric($_POST['retail_inc_vat']) ? (float)$_POST['retail_inc_vat'] : 0;
    $trade_inc_vat = isset($_POST['trade_inc_vat']) && is_numeric($_POST['trade_inc_vat']) ? (float)$_POST['trade_inc_vat'] : 0;

    // Calculate price and trade
    if ($_POST['pricing_method'] == 2) {
        $price = $fixed_retail_pricing / (1 + $tax_rate);
        $trade = $fixed_trade_pricing / (1 + $tax_rate);
    } else {
        $price = $retail_inc_vat / (1 + $tax_rate);
        $trade = $trade_inc_vat / (1 + $tax_rate);
    }
    $price = round($price, 2);
    $trade = round($trade, 2);

    // Insert the new product into the master_products table
    $response = $DB->query("INSERT INTO master_products (name, manufacturer, mpn, ean, category_id, pless_main_category, pos_category, supplier, enable, export_to_magento, cost, pricing_cost, pricing_method, tax_rate_id, retail_markup, trade_markup, fixed_retail, fixed_trade, price, trade) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
        $_POST['name'],
        $_POST['manufacturer'],
        $_POST['mpn'],
        $_POST['ean'],
        $category_id,
        $pless_main_category,
        $pos_category,
        $_POST['supplier'],
        $enable,
        $export_to_magento,
        $_POST['cost'],
        $_POST['pricing_cost'],
        $_POST['pricing_method'],
        $_POST['tax_rate_id'],
        $_POST['retail_markup'],
        $_POST['trade_markup'],
        $fixed_retail_pricing,
        $fixed_trade_pricing,
        $price,
        $trade
    ]);

    if ($response > 0) {
        echo 'Product added successfully!';
    } else {
        throw new Exception("Failed to add the product.");
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
