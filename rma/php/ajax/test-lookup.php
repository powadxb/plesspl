<?php
/**
 * TEST SCRIPT - Check lookup-serial.php errors
 * Access this directly in browser to see actual error
 */

echo "<h1>Lookup Serial Test</h1>";
echo "<pre>";

// Test 1: Bootstrap
echo "Test 1: Loading bootstrap...\n";
try {
    require __DIR__.'/../../../php/bootstrap.php';
    echo "✅ Bootstrap loaded successfully\n\n";
} catch (Exception $e) {
    echo "❌ Bootstrap error: " . $e->getMessage() . "\n\n";
    die();
}

// Test 2: Database connection
echo "Test 2: Database connection...\n";
try {
    $result = $DB->query("SELECT 1");
    echo "✅ Database connected\n\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n\n";
}

// Test 3: Check user authentication
echo "Test 3: User authentication...\n";
if(isset($_SESSION['dins_user_id'])) {
    $user_id = $_SESSION['dins_user_id'];
    echo "✅ User authenticated: ID = $user_id\n\n";
} elseif(isset($_COOKIE['dins_user_id'])) {
    $user_id = $_COOKIE['dins_user_id'];
    echo "✅ User authenticated via cookie: ID = $user_id\n\n";
} else {
    echo "❌ User not authenticated\n\n";
    $user_id = null;
}

// Test 4: Check permissions file
echo "Test 4: Permissions file...\n";
$permissions_file = __DIR__.'/../rma-permissions.php';
if (file_exists($permissions_file)) {
    echo "✅ rma-permissions.php exists\n";
    try {
        require $permissions_file;
        echo "✅ rma-permissions.php loaded\n\n";
    } catch (Exception $e) {
        echo "❌ Error loading: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "❌ rma-permissions.php NOT FOUND at: $permissions_file\n\n";
}

// Test 5: Check if functions exist
echo "Test 5: Check functions...\n";
if (function_exists('canViewSupplierData')) {
    echo "✅ canViewSupplierData() exists\n";
} else {
    echo "❌ canViewSupplierData() NOT FOUND\n";
}
if (function_exists('canViewFinancialData')) {
    echo "✅ canViewFinancialData() exists\n";
} else {
    echo "❌ canViewFinancialData() NOT FOUND\n";
}
echo "\n";

// Test 6: Check tables exist
echo "Test 6: Check database tables...\n";
try {
    $tables = $DB->query("SHOW TABLES LIKE 'products'");
    echo $tables ? "✅ products table exists\n" : "❌ products table NOT FOUND\n";
    
    $tables = $DB->query("SHOW TABLES LIKE 'stock_suppliers'");
    echo $tables ? "✅ stock_suppliers table exists\n" : "⚠️  stock_suppliers table NOT FOUND (optional)\n";
    
    $tables = $DB->query("SHOW TABLES LIKE 'user_permissions'");
    echo $tables ? "✅ user_permissions table exists\n" : "❌ user_permissions table NOT FOUND\n";
} catch (Exception $e) {
    echo "❌ Table check error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Try a simple query
if ($user_id) {
    echo "Test 7: Test permissions check...\n";
    try {
        $result = $DB->query("SELECT * FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Supplier'", [$user_id]);
        if ($result) {
            echo "✅ Permissions query works\n";
            echo "   Result: " . print_r($result, true) . "\n";
        } else {
            echo "⚠️  No permissions found for this user\n";
        }
    } catch (Exception $e) {
        echo "❌ Query error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Test 8: Test product query
echo "Test 8: Test product query...\n";
try {
    $result = $DB->query("SELECT id, sku, product_name, serial_number FROM products LIMIT 1");
    if ($result) {
        echo "✅ Product query works\n";
        echo "   Sample product: " . $result[0]['sku'] . " - " . $result[0]['product_name'] . "\n";
    } else {
        echo "⚠️  No products in database\n";
    }
} catch (Exception $e) {
    echo "❌ Product query error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "========================================\n";
echo "DIAGNOSTIC COMPLETE\n";
echo "========================================\n";
echo "</pre>";
?>