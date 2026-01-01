<?php
// ajax/search_products.php
session_start();
require '../php/bootstrap.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authorized']));
}

$search = $_POST['search'] ?? '';
$priceType = $_POST['priceType'] ?? 'R';
$exactSku = $_POST['exactSku'] ?? false;

if (empty($search)) {
    exit(json_encode([]));
}

// Build the search conditions
if ($exactSku) {
    $conditions = ["p.sku = ?"]; // Exact match for SKU updates
    $params = [$search];
} else {
    // Split search terms
    $terms = preg_split('/\s+/', trim($search));
    $conditions = [];
    $params = [];

    // Build search conditions for each term
    foreach ($terms as $term) {
        $conditions[] = "(
            p.name LIKE ? OR 
            p.ean LIKE ? OR 
            p.mpn LIKE ? OR 
            CAST(p.sku AS CHAR) LIKE ? OR
            p.supplier_sku LIKE ?
        )";
        $pattern = "%{$term}%";
        $params = array_merge($params, [$pattern, $pattern, $pattern, $pattern, $pattern]);
    }
}

// Construct the query
$query = "
    SELECT 
        p.*,
        t.tax_rate,
        CASE 
            WHEN ? = 'R' THEN p.price
            ELSE p.trade 
        END as base_price,
        CASE 
            WHEN ? = 'R' THEN ROUND(p.price * (1 + t.tax_rate), 2)
            ELSE ROUND(p.trade * (1 + t.tax_rate), 2)
        END as price_inc_vat
    FROM master_products p
    JOIN tax_rates t ON p.tax_rate_id = t.tax_rate_id
    WHERE p.enable = 'y'
    AND " . implode(" AND ", $conditions);

if (!$exactSku) {
    $query .= " ORDER BY 
        CASE 
            WHEN p.name LIKE ? THEN 1
            WHEN p.sku LIKE ? THEN 2
            ELSE 3 
        END,
        p.name ASC
    LIMIT 50";
}

// Add price type parameters at the start
array_unshift($params, $priceType, $priceType);

// Add sorting parameters if not exact SKU search
if (!$exactSku) {
    $exactPattern = "%{$search}%";
    $params = array_merge($params, [$exactPattern, $exactPattern]);
}

try {
    $results = $DB->query($query, $params);
    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}