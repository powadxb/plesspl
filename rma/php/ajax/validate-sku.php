<?php
/**
 * Validate SKU and return product details - CORRECT VERSION
 * Returns product info with optional supplier/cost based on permissions
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

try {
    require __DIR__.'/../../../php/bootstrap.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Bootstrap error: ' . $e->getMessage()]);
    exit;
}

// Check if rma-permissions.php exists and load it
$permissions_file = __DIR__.'/../rma-permissions.php';
if (file_exists($permissions_file)) {
    try {
        require $permissions_file;
    } catch (Exception $e) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permissions error: ' . $e->getMessage()]);
        exit;
    }
} else {
    // Fallback: define functions inline if file doesn't exist
    if (!function_exists('canViewSupplierData')) {
        function canViewSupplierData($user_id, $DB) {
            try {
                $result = $DB->query("SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Supplier'", [$user_id]);
                return !empty($result) && $result[0]['has_access'] == 1;
            } catch (Exception $e) {
                return false;
            }
        }
    }
    if (!function_exists('canViewFinancialData')) {
        function canViewFinancialData($user_id, $DB) {
            try {
                $result = $DB->query("SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'RMA-View Financial'", [$user_id]);
                return !empty($result) && $result[0]['has_access'] == 1;
            } catch (Exception $e) {
                return false;
            }
        }
    }
}

// Clear any unexpected output
ob_end_clean();

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get user ID
$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'];

// Check permissions separately
$can_view_supplier = false;
$can_view_financial = false;

try {
    $can_view_supplier = canViewSupplierData($user_id, $DB);
    $can_view_financial = canViewFinancialData($user_id, $DB);
} catch (Exception $e) {
    // Continue with no permissions
}

// Get SKU from request
$sku = $_POST['sku'] ?? '';

if (empty($sku)) {
    echo json_encode(['success' => false, 'message' => 'SKU required']);
    exit;
}

try {
    // Query master_products table (not products table!)
    // master_products has: sku, name, cost, ean, supplier
    $select_fields = ["mp.sku", "mp.name as product_name", "mp.ean"];

    // Add cost if authorized
    if ($can_view_financial) {
        $select_fields[] = "mp.cost";
    }
    
    // Add supplier if authorized
    if ($can_view_supplier) {
        $select_fields[] = "mp.supplier as supplier_name";
    }

    $sql = "
        SELECT " . implode(", ", $select_fields) . "
        FROM master_products mp
        WHERE mp.sku = ?
        LIMIT 1
    ";

    $results = $DB->query($sql, [$sku]);

    if (empty($results)) {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found with SKU: ' . htmlspecialchars($sku)
        ]);
        exit;
    }

    $product = $results[0];

    $response = [
        'success' => true,
        'data' => [
            'sku' => $product['sku'],
            'product_name' => $product['product_name'],
            'ean' => $product['ean'] ?? null
        ]
    ];

    // Add supplier info only if authorized
    if ($can_view_supplier && isset($product['supplier_name'])) {
        $response['data']['supplier_name'] = $product['supplier_name'];
    }

    // Add cost only if authorized
    if ($can_view_financial && isset($product['cost'])) {
        $response['data']['cost'] = $product['cost'];
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}