<?php
require 'bootstrap.php';

echo "Function exists: " . (function_exists('hasPermission') ? 'YES' : 'NO') . "\n";

if (function_exists('hasPermission')) {
    echo "Testing permission for user 10...\n";
    $result = hasPermission(10, 'edit_stock_status', $DB);
    echo "Result: " . ($result ? 'HAS PERMISSION' : 'NO PERMISSION') . "\n";
} else {
    echo "Function not available\n";
}
?>