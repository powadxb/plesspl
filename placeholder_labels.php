<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/php/bootstrap.php';
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'];
$user_details = $DB->query(" SELECT * FROM users WHERE id=?", [$user_id])[0];

function format_name($name) {
    $name = trim($name);
    $name_length = strlen($name);

    if ($name_length > 150) {
        $name = substr($name, 0, 149) . '...';
    }

    return $name;
}

$skus = explode(PHP_EOL, trim($_POST['sku_list']));
$labels = [];

foreach ($skus as $sku) {
    $sku = trim($sku);

    if (!empty($sku)) {
        $result = $DB->query("SELECT sku, name, ean FROM master_products WHERE sku = ? OR ean = ?", [$sku, $sku]);

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
    <title>Shelf Label Generator</title>
    <link rel="stylesheet" type="text/css" href="labels.css">
</head>
<body>
    <?php if (count($labels) > 0): ?>
      <div class="placeholder_label-container">
      <?php foreach ($labels as $label): ?>
          <?php
              $sku = htmlspecialchars($label['sku']);
              $name = htmlspecialchars($label['name']);
              $ean = htmlspecialchars($label['ean']);
          ?>
          <div class="placeholder_label">
              <div class="placeholder_name"><?php echo format_name($name); ?></div>
              <div class="placeholder_sku">SKU: <?php echo $sku; ?></div>
              <div class="placeholder_ean">EAN: <?php echo $ean; ?></div>
          </div>
      <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p>No matching results found.</p>
    <?php endif; ?>
</body>
</html>
