<?php
session_start();
require '../php/bootstrap.php';

if(!isset($_SESSION['dins_user_id'])){
    die("Not logged in");
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

echo "<h2>User Permission Check</h2>";
echo "<pre>";
echo "User ID: " . $user_id . "\n";
echo "Username: " . $user_details['username'] . "\n";
echo "Admin: " . $user_details['admin'] . "\n";
echo "</pre>";

// Check the specific permission
$customer_check = $DB->query(
    "SELECT * FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View Customer Data'",
    [$user_id]
);

echo "<h2>SecondHand-View Customer Data Permission</h2>";
echo "<pre>";
print_r($customer_check);
echo "</pre>";

// Get a sample item with customer data
$sample_items = $DB->query(
    "SELECT id, item_name, customer_name, customer_contact, customer_id 
     FROM second_hand_items 
     WHERE customer_name IS NOT NULL 
     LIMIT 5"
);

echo "<h2>Sample Items with Seller Data (from database)</h2>";
echo "<pre>";
print_r($sample_items);
echo "</pre>";

// Simulate what list_second_hand_items.php does
echo "<h2>What list_second_hand_items.php returns</h2>";
$query = "SELECT id, preprinted_code, tracking_code, item_name, category, `condition`, item_source, status, purchase_price, estimated_sale_price, location, customer_name, customer_contact, detailed_condition, acquisition_date, notes FROM second_hand_items LIMIT 5";
$records = $DB->query($query);
echo "<pre>";
print_r($records);
echo "</pre>";
?>
