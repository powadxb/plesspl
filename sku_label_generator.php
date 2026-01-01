<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
session_start();
$page_title = 'Products Control Panel';
require 'assets/header.php';

if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}
require __DIR__ . '/php/bootstrap.php';
$user_details = $DB->query(" SELECT * FROM users WHERE id=?", [$user_id])[0];

// all manufacturers
$all_manufacturers = $DB->query(" SELECT * FROM master_pless_manufacturers ORDER BY manufacturer_name ASC");

// master_categories
$categories = $DB->query(" SELECT * FROM master_categories ORDER BY pos_category ASC");

// settings
require 'php/settings.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skus = explode(PHP_EOL, trim($_POST['sku_list']));
    $labels = [];

    foreach ($skus as $sku) {
        $sku = trim($sku);

        if (!empty($sku)) {
            $result = $DB->query("SELECT sku, name, price, tax_rate_id FROM master_products WHERE sku = ?", [$sku]);

            if (count($result) > 0) {
                $labels[] = $result[0];
            }
        }
    }

    // Render labels as HTML
    ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shelf Label Generator</title>
    <style>
        /* include your styles here */
    </style>
</head>
<body>
    <?php if (isset($labels) && count($labels) > 0): ?>
        <!-- display labels -->
    <?php endif; ?>

<?php
    $html = ob_get_clean();
    echo $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shelf Label Generator</title>
<!-- Include your styles here -->
</head>
<body>
<form action="" method="post">
<label for="sku_list">Enter SKUs (1 per line):</label>
<br>
<textarea id="sku_list" name="sku_list" rows="10" cols="30"></textarea>
<br>
<input type="submit" name="submit" value="Generate Labels">
</form>
</body>
</html>
