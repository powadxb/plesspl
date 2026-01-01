<?php
/**
 * Lookup product by serial number - SMART VERSION
 * - Fast exact match for barcode scans
 * - Partial search for browsing ranges
 * - Shows "see more" option when other matches exist
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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

// Check if rma-permissions.php exists
$permissions_file = __DIR__.'/../rma-permissions.php';
if (file_exists($permissions_file)) {
    try {
        require $permissions_file;
    } catch (Exception $e) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permissions file error: ' . $e->getMessage()]);
        exit;
    }
} else {
    // Define fallback functions
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

// Check permissions
$can_view_supplier = false;
$can_view_financial = false;

try {
    $can_view_supplier = canViewSupplierData($user_id, $DB);
    $can_view_financial = canViewFinancialData($user_id, $DB);
} catch (Exception $e) {
    // Continue with no permissions
}

// Get serial number and search mode
$serial_number = $_POST['serial_number'] ?? '';
$show_all = isset($_POST['show_all']) && $_POST['show_all'] == '1'; // Flag to force showing all matches

if (empty($serial_number)) {
    echo json_encode(['success' => false, 'message' => 'Serial number required']);
    exit;
}

// Minimum length for partial search
if (strlen($serial_number) < 3) {
    echo json_encode(['success' => false, 'message' => 'Serial number must be at least 3 characters']);
    exit;
}

try {
    // Build query
    $select_fields = [
        "sn.id",
        "p.sku",
        "mp.name as product_name",
        "sn.serial_num as serial_number",
        "mp.ean",
        "p.ean_upc"
    ];
    
    $joins = "
        INNER JOIN products p ON sn.product_id = p.id
        INNER JOIN master_products mp ON p.sku = mp.sku
    ";
    
    // Add supplier data if user has permission
    if ($can_view_supplier) {
        $select_fields[] = "d.supplier as supplier_name";
        $select_fields[] = "d.document_num as document_number";
        $select_fields[] = "d.document_type";
        $select_fields[] = "d.document_date";
        $select_fields[] = "d.id as document_id";
        $joins .= " LEFT JOIN documents d ON p.doc_id = d.id";
    }
    
    // Add cost if user has permission
    if ($can_view_financial) {
        $select_fields[] = "mp.cost";
    }

    $select_clause = implode(", ", $select_fields);
    
    // If show_all flag is set, skip exact match and go straight to partial
    if ($show_all) {
        // User wants to see all matches - do partial search
        $sql = "
            SELECT $select_clause
            FROM serial_nums sn
            $joins
            WHERE sn.serial_num LIKE ?
            ORDER BY sn.serial_num ASC
            LIMIT 50
        ";
        
        $results = $DB->query($sql, ['%' . $serial_number . '%']);
        $search_type = 'all_matches';
        
    } else {
        // Normal flow: try exact match first
        $sql_exact = "
            SELECT $select_clause
            FROM serial_nums sn
            $joins
            WHERE sn.serial_num = ?
            ORDER BY sn.id DESC
            LIMIT 1
        ";
        
        $exact_results = $DB->query($sql_exact, [$serial_number]);
        
        if (!empty($exact_results)) {
            // Found exact match - but check if there are other similar serials
            $sql_count = "
                SELECT COUNT(*) as count
                FROM serial_nums
                WHERE serial_num LIKE ?
                AND serial_num != ?
            ";
            
            $count_result = $DB->query($sql_count, ['%' . $serial_number . '%', $serial_number]);
            $other_matches = $count_result[0]['count'] ?? 0;
            
            $results = $exact_results;
            $search_type = 'exact_with_more';
            
        } else {
            // No exact match - try partial
            $sql_partial = "
                SELECT $select_clause
                FROM serial_nums sn
                $joins
                WHERE sn.serial_num LIKE ?
                ORDER BY sn.serial_num ASC
                LIMIT 50
            ";
            
            $results = $DB->query($sql_partial, ['%' . $serial_number . '%']);
            $search_type = 'partial_only';
            $other_matches = 0;
        }
    }

    if (empty($results)) {
        echo json_encode([
            'success' => false,
            'message' => 'No products found with serial number: ' . htmlspecialchars($serial_number)
        ]);
        exit;
    }

    // Helper function to format product data
    function formatProduct($product, $can_view_supplier, $can_view_financial) {
        $item = [
            'id' => $product['id'],
            'sku' => $product['sku'],
            'product_name' => $product['product_name'],
            'serial_num' => $product['serial_number'],
            'ean' => $product['ean'] ?? $product['ean_upc'] ?? null
        ];
        
        if ($can_view_supplier) {
            $item['supplier_name'] = $product['supplier_name'] ?? 'Unknown';
            $item['document_number'] = $product['document_number'] ?? '-';
            $item['document_type'] = $product['document_type'] ?? '';
            $item['document_id'] = $product['document_id'] ?? null;
            $item['document_date'] = $product['document_date'] ?? null;
        }
        
        if ($can_view_financial && isset($product['cost'])) {
            $item['cost'] = $product['cost'];
        }
        
        return $item;
    }

    // Single match (and not requesting all matches)
    if (count($results) == 1 && $search_type != 'all_matches') {
        $product = $results[0];
        
        $response = [
            'success' => true,
            'multiple' => false,
            'search_type' => $search_type,
            'data' => formatProduct($product, $can_view_supplier, $can_view_financial)
        ];
        
        // Add info about other matches if they exist
        if ($search_type == 'exact_with_more' && $other_matches > 0) {
            $response['other_matches'] = $other_matches;
            $response['message'] = "Found exact match. $other_matches other serial(s) contain '$serial_number'.";
        }
        
        echo json_encode($response);
        exit;
    }

    // Multiple matches
    $products = [];
    foreach ($results as $product) {
        $products[] = formatProduct($product, $can_view_supplier, $can_view_financial);
    }

    $response = [
        'success' => true,
        'multiple' => true,
        'search_type' => $search_type,
        'count' => count($products),
        'matches' => $products,
        'show_supplier' => $can_view_supplier,
        'show_financial' => $can_view_financial
    ];
    
    if ($search_type == 'partial_only') {
        $response['message'] = "Found " . count($products) . " serial(s) containing '$serial_number'";
    } else if ($search_type == 'all_matches') {
        $response['message'] = "Showing all serials containing '$serial_number'";
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}