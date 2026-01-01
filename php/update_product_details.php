<?php
require 'bootstrap.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure required POST data is present
if (!isset($_POST['tax_rate_id'], $_POST['categories'], $_POST['name'], $_POST['manufacturer'], $_POST['mpn'], $_POST['ean'], $_POST['pricing_method'], $_POST['retail_markup'], $_POST['trade_markup'], $_POST['sku'])) {
    die("Required form data is missing.");
}

try {
    // Fetch the tax rate
    $tax_rate_query = $DB->query("SELECT * FROM tax_rates WHERE tax_rate_id=?", [$_POST['tax_rate_id']]);
    if (empty($tax_rate_query)) {
        throw new Exception("Tax rate not found.");
    }
    $tax_rate = $tax_rate_query[0]['tax_rate'];
} catch (Exception $e) {
    die("Failed to fetch tax rate: " . $e->getMessage());
}

// Split the categories and check if valid
$category_details = explode("|", $_POST['categories']);
if (count($category_details) < 3) {
    die("Invalid category details.");
}

$category_id = $category_details[0];
$pless_main_category = $category_details[1];
$pos_category = $category_details[2];

// Enable, export_to_magento, and stock_status fields handling
$enable = isset($_POST['enable']) ? 'y' : 'n';
$export_to_magento = isset($_POST['export_to_magento']) ? 'y' : 'n';
$stock_status = isset($_POST['stock_status']) ? 1 : 0;

// Calculate prices based on pricing method
if ($_POST['pricing_method'] == 2) {
    // Fixed price method
    $price = $_POST['fixed_retail_pricing'] / (1 + $tax_rate);
    $trade = $_POST['fixed_trade_pricing'] / (1 + $tax_rate);
} else {
    // Markup price method
    $price = $_POST['retail_inc_vat'] / (1 + $tax_rate);
    $trade = $_POST['trade_inc_vat'] / (1 + $tax_rate);
}

$price = round($price, 2);
$trade = round($trade, 2);

// Handle fixed pricing if not provided, use NULL or 0
$fixed_retail = !empty($_POST['fixed_retail_pricing']) ? round($_POST['fixed_retail_pricing'], 2) : null;
$fixed_trade = !empty($_POST['fixed_trade_pricing']) ? round($_POST['fixed_trade_pricing'], 2) : null;

// Execute the update query with proper error handling
try {
    $response = $DB->query(
        "UPDATE master_products SET name=?, manufacturer=?, mpn=?, ean=?, category_id=?, pless_main_category=?, pos_category=?, supplier=?, enable=?, export_to_magento=?, stock_status=?, cost=?, pricing_cost=?, pricing_method=?, tax_rate_id=?, retail_markup=?, trade_markup=?, fixed_retail=?, fixed_trade=?, price=?, trade=? WHERE sku=?", 
        [
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
            $stock_status,
            $_POST['cost'], 
            $_POST['pricing_cost'], 
            $_POST['pricing_method'], 
            $_POST['tax_rate_id'], 
            $_POST['retail_markup'], 
            $_POST['trade_markup'], 
            $fixed_retail, 
            $fixed_trade, 
            $price, 
            $trade, 
            $_POST['sku']
        ]
    );

    if ($response > 0) {
        echo 'updated';
    } else {
        echo 'No rows affected';
    }
} catch (Exception $e) {
    die("Failed to update product: " . $e->getMessage());
}
?>