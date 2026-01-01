<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require __DIR__ . '/php/bootstrap.php';

// Session start
session_start();

if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];
// Retrieve quote items from the database or set defaults
$quote_items = [];
$total = 0.00;

$stmt = $DB->prepare("SELECT * FROM master_products WHERE sku = ?");
$product_skus = [
    'cpu',
    'mainboard',
    'memory',
    'storage',
    'graphics_card',
    'power_supply',
    'case',
];
foreach ($product_skus as $sku) {
    $item = $DB->query("SELECT * FROM quote_items WHERE user_id=? AND sku=?", [$user_id, $sku])->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        $item = [
            'user_id' => $user_id,
            'sku' => $sku,
            'name' => '',
            'price' => 0.00,
            'quantity' => 1,
            'subtotal' => 0.00,
        ];
    } else {
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $total += $item['subtotal'];
    }

    $quote_items[] = $item;
}

// User-entered assembly charge
$assembly_charge = isset($_POST['assembly_charge']) ? (float)$_POST['assembly_charge'] : 0.00;
$total += $assembly_charge;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save customer details to session
    $_SESSION['customer_name'] = $_POST['customer_name'] ?? '';
    $_SESSION['customer_address'] = $_POST['customer_address'] ?? '';
    $_SESSION['customer_telephone'] = $_POST['customer_telephone'] ?? '';
    $_SESSION['customer_email'] = $_POST['customer_email'] ?? '';

    // Save quote items to database
    foreach ($_POST['quote_items'] as $item) {
        $stmt = $DB->prepare("INSERT INTO quote_items (user_id, sku, name, price, quantity) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=?, price=?, quantity=?");
        $stmt->execute([$user_id, $item['sku'], $item['name'], $item['price'], $item['quantity'], $item['name'], $item['price'], $item['quantity']]);
    }

    // Save assembly charge to database
    $stmt = $DB->prepare("INSERT INTO quote_items (user_id, sku, name, price, quantity) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price=?");
    $stmt->execute([$user_id, 'assembly', 'Assembly charge', $assembly_charge, 1, $assembly_charge]);

    // Redirect to quote preview page
    header("Location: quote_preview.php");
    exit();
}
<html>
<head>
    <title>Quote Form</title>
</head>
<body>
    <h1>Quote Form</h1>

    <form method="post">
        <label for="customer_name">Name:</label>
        <input type="text" name="customer_name" id="customer_name" value="<?= htmlspecialchars($customer_name) ?>">

        <label for="customer_address">Address:</label>
        <input type="text" name="customer_address" id="customer_address" value="<?= htmlspecialchars($customer_address) ?>">

        <label for="customer_telephone">Telephone:</label>
        <input type="text" name="customer_telephone" id="customer_telephone" value="<?= htmlspecialchars($customer_telephone) ?>">

        <label for="customer_email">Email:</label>
        <input type="text" name="customer_email" id="customer_email" value="<?= htmlspecialchars($customer_email) ?>">

        <table>
            <thead>
                <tr>
                    <th>Component</th>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quote_items as $item): ?>
                <tr>
                    <td>CPU</td>
                    <td><input type="text" name="quote_items[0][sku]" value="<?= htmlspecialchars($quote_items[0]['sku'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[0][name]" value="<?= htmlspecialchars($quote_items[0]['name'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[0][price]" value="<?= number_format($quote_items[0]['price'] ?? 0, 2) ?>"></td>
                    <td><input type="text" name="quote_items[0][quantity]" value="<?= htmlspecialchars($quote_items[0]['quantity'] ?? 1) ?>"></td>
                    <td><?= number_format($quote_items[0]['subtotal'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td>Mainboard</td>
                    <td><input type="text" name="quote_items[1][sku]" value="<?= htmlspecialchars($quote_items[1]['sku'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[1][name]" value="<?= htmlspecialchars($quote_items[1]['name'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[1][price]" value="<?= number_format($quote_items[1]['price'] ?? 0, 2) ?>"></td>
                    <td><input type="text" name="quote_items[1][quantity]" value="<?= htmlspecialchars($quote_items[1]['quantity'] ?? 1) ?>"></td>
                    <td><?= number_format($quote_items[1]['subtotal'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td>Memory</td>
                    <td><input type="text" name="quote_items[2][sku]" value="<?= htmlspecialchars($quote_items[2]['sku'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[2][name]" value="<?= htmlspecialchars($quote_items[2]['name'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[2][price]" value                    <?= number_format($quote_items[2]['price'] ?? 0, 2) ?>"></td>
                    <td><input type="text" name="quote_items[2][quantity]" value="<?= htmlspecialchars($quote_items[2]['quantity'] ?? 1) ?>"></td>
                    <td><?= number_format($quote_items[2]['subtotal'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td>Storage</td>
                    <td><input type="text" name="quote_items[3][sku]" value="<?= htmlspecialchars($quote_items[3]['sku'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[3][name]" value="<?= htmlspecialchars($quote_items[3]['name'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[3][price]" value="<?= number_format($quote_items[3]['price'] ?? 0, 2) ?>"></td>
                    <td><input type="text" name="quote_items[3][quantity]" value="<?= htmlspecialchars($quote_items[3]['quantity'] ?? 1) ?>"></td>
                    <td><?= number_format($quote_items[3]['subtotal'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td>Graphics Card</td>
                    <td><input type="text" name="quote_items[4][sku]" value="<?= htmlspecialchars($quote_items[4]['sku'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[4][name]" value="<?= htmlspecialchars($quote_items[4]['name'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[4][price]" value="<?= number_format($quote_items[4]['price'] ?? 0, 2) ?>"></td>
                    <td><input type="text" name="quote_items[4][quantity]" value="<?= htmlspecialchars($quote_items[4]['quantity'] ?? 1) ?>"></td>
                    <td><?= number_format($quote_items[4]['subtotal'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td>Power Supply</td>
                    <td><input type="text" name="quote_items[5][sku]" value="<?= htmlspecialchars($quote_items[5]['sku'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[5][name]" value="<?= htmlspecialchars($quote_items[5]['name'] ?? '') ?>"></td>
                    <td><input type="text" name="quote_items[5][price]" value="<?= number_format($quote_items[5]['price'] ?? 0, 2) ?>"></td>
                    <td><input type="text" name="quote_items[5][quantity]" value="<?= htmlspecialchars($quote_items[5]['quantity'] ?? 1) ?>"></td>
                    <td><?= number_format($quote_items[5]['subtotal'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                  <td>Case</td>
<td><input type="text" name="quote_items[6][sku]" value="<?= htmlspecialchars($quote_items[6]['sku'] ?? '') ?>"></td>
<td><input type="text" name="quote_items[6][name]" value="<?= htmlspecialchars($quote_items[6]['name'] ?? '') ?>"></td>
<td><input type="text" name="quote_items[6][price]" value="<?= number_format($quote_items[6]['price'] ?? 0, 2) ?>"></td>
<td><input type="text" name="quote_items[6][quantity]" value="<?= htmlspecialchars($quote_items[6]['quantity'] ?? 1) ?>"></td>
<td><?= number_format($quote_items[6]['subtotal'] ?? 0, 2) ?></td>
</tr>
<tr>
  <td>Assembly Charge</td>
  <td></td>
  <td></td>
  <td><input type="text" name="assembly_charge" id="assembly_charge" value="<?= number_format($assembly_charge, 2) ?>"></td>
  <td>1</td>
  <td><?= number_format($assembly_charge, 2) ?></td>
</tr>
<tr>
  <td colspan="5" style="text-align:right">Total:</td>
  <td><?= number_format($total, 2) ?></td>
</tr>
</tbody>
</table>

<input type="submit" name="submit" value="Submit">
</form>
</body>
</html>
<?php
// Check if form was submitted
if (isset($_POST['submit'])) {

    // Get customer details from form
    $customer_name = $_POST['customer_name'];
    $customer_address = $_POST['customer_address'];
    $customer_telephone = $_POST['customer_telephone'];
    $customer_email = $_POST['customer_email'];

    // Get quote items from form
    $quote_items = $_POST['quote_items'];

    // Calculate total cost of items
    $total = 0;
    foreach ($quote_items as $item) {
        $subtotal = (float)$item['price'] * (int)$item['quantity'];
        $item['subtotal'] = $subtotal;
        $total += $subtotal;
    }

    // Get assembly charge from form
    $assembly_charge = (float)$_POST['assembly_charge'];
    $total += $assembly_charge;

    // Save quote details to database
    $DB->beginTransaction();

    try {
        // Save customer details
        $stmt = $DB->prepare("INSERT INTO customers (name, address, telephone, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$customer_name, $customer_address, $customer_telephone, $customer_email]);
        $customer_id = $DB->lastInsertId();

        // Save quote details
        $stmt = $DB->prepare("INSERT INTO quotes (customer_id, total, assembly_charge) VALUES (?, ?, ?)");
        $stmt->execute([$customer_id, $total, $assembly_charge]);
        $quote_id = $DB->lastInsertId();

        // Save quote item details
        $stmt = $DB->prepare("INSERT INTO quote_items (quote_id, sku, name, price, quantity) VALUES (?, ?, ?, ?, ?)");
        foreach ($quote_items as $item) {
            $stmt->execute([$quote_id, $item['sku'], $item['name'], $item['price'], $item['quantity']]);
        }

        $DB->commit();

        // Redirect to success page
        header("Location: quote_success.php?id=$quote_id");
        exit();

    } catch (PDOException $e) {
        $DB->rollBack();
        throw $e;
    }

}
