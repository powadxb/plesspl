<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}
// Include the database connection setup
require __DIR__ . '/php/bootstrap.php';

$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

function format_name($name) {
    $name = trim($name);
    $name_length = strlen($name);
    if ($name_length > 87) {
        $name = substr($name, 0, 85) . '...';
    }
    return $name;
}

function format_price($price) {
    // Format price to 2 decimal places
    $formatted = number_format($price, 2, '.', '');
    // Split into pounds and pence
    $parts = explode('.', $formatted);
    return [
        'pounds' => '£' . $parts[0],
        'pence' => $parts[1]
    ];
}

$skus = explode(PHP_EOL, trim($_POST['sku_list']));
$labels = [];
foreach ($skus as $sku) {
    $sku = trim($sku);
    if (!empty($sku)) {
        // Use the database connection to query the products
        $result = $DB->query("SELECT sku, name, price, ean FROM master_products WHERE sku = ? OR ean = ?", [$sku, $sku]);
        if (count($result) > 0) {
            $labels[] = $result[0];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hanging Label Generator</title>
    <link rel="stylesheet" type="text/css" href="labels.css">
</head>
<body>
    <?php if (count($labels) > 0): ?>
      <div class="label-container">
      <?php foreach ($labels as $label): ?>
          <?php
              $sku = htmlspecialchars($label['sku']);
              $name = htmlspecialchars($label['name']);
              $ean = htmlspecialchars($label['ean']);
              $price = $label['price'] * 1.2;
              $formatted_price = format_price($price);
          ?>
          <div class="hanging_label">
              <div class="name"><?php echo format_name($name); ?></div>
              <div class="sku">SKU: <?php echo $sku; ?></div>
              <div class="hanging_ean">EAN: <?php echo $ean; ?></div>
              <div class="price">
                <span class="pounds"><?php echo $formatted_price['pounds']; ?></span>
                <span class="dot">.</span>
                <span class="pence"><?php echo $formatted_price['pence']; ?></span>
              </div>
          </div>
      <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p>No matching results found.</p>
    <?php endif; ?>
</body>
</html>