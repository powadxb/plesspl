<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Clean any previous output
if (ob_get_level()) {
    ob_end_clean();
}

require __DIR__.'/../../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    $response = json_encode(['success' => false, 'message' => 'Not authenticated']);
    echo $response;
    flush();
    exit();
}

try {
    // Get user details for location
    $user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];

    // Determine effective location
    $effective_location = $user_details['user_location'];
    if(!empty($user_details['temp_location']) && 
       !empty($user_details['temp_location_expires']) && 
       strtotime($user_details['temp_location_expires']) > time()) {
        $effective_location = $user_details['temp_location'];
    }

    if(empty($effective_location)) {
        $response = json_encode(['success' => false, 'message' => 'User has no location assigned']);
        echo $response;
        flush();
        exit();
    }

    // Get form data
    $serial_number = $_POST['serial_number'] ?? '';
    $sku = $_POST['sku'] ?? '';
    $product_name = $_POST['product_name'] ?? '';
    $ean = $_POST['ean'] ?? '';
    $supplier_name = $_POST['supplier_name'] ?? null;
    $document_id = $_POST['document_id'] ?? null;
    $document_number = $_POST['document_number'] ?? null;
    $document_date = $_POST['document_date'] ?? null;
    $cost = $_POST['cost'] ?? null;
    $needs_review = $_POST['needs_review'] ?? 0;
    $fault_type_id = $_POST['fault_type_id'] ?? '';
    $fault_description = $_POST['fault_description'] ?? null;
    $barcode = $_POST['barcode'] ?? null;
    $tracking_number = $_POST['tracking_number'] ?? null;

    // If no cost provided (user lacks financial permission), fetch current cost from master_products
    if(empty($cost) && !empty($sku)) {
        $cost_lookup = $DB->query("SELECT cost FROM master_products WHERE sku = ? LIMIT 1", [$sku]);
        if(!empty($cost_lookup)) {
            $cost = $cost_lookup[0]['cost'];
        }
    }

    // Validation
    if(empty($sku)) {
        $response = json_encode(['success' => false, 'message' => 'SKU is required']);
        echo $response;
        flush();
        exit();
    }

    if(empty($product_name)) {
        $response = json_encode(['success' => false, 'message' => 'Product name is required']);
        echo $response;
        flush();
        exit();
    }

    if(empty($fault_type_id)) {
        $response = json_encode(['success' => false, 'message' => 'Fault type is required']);
        echo $response;
        flush();
        exit();
    }

    if(empty($barcode) && empty($tracking_number)) {
        $response = json_encode(['success' => false, 'message' => 'Either barcode or tracking number is required']);
        echo $response;
        flush();
        exit();
    }

    // Check for duplicate barcode
    if(!empty($barcode)) {
        $check = $DB->query("SELECT COUNT(*) as count FROM rma_items WHERE barcode = ?", [$barcode]);
        if($check[0]['count'] > 0) {
            $response = json_encode(['success' => false, 'message' => 'Barcode already exists']);
            echo $response;
            flush();
            exit();
        }
    }

    // Check for duplicate tracking number
    if(!empty($tracking_number)) {
        $check = $DB->query("SELECT COUNT(*) as count FROM rma_items WHERE tracking_number = ?", [$tracking_number]);
        if($check[0]['count'] > 0) {
            $response = json_encode(['success' => false, 'message' => 'Tracking number already exists']);
            echo $response;
            flush();
            exit();
        }
    }

    // Insert RMA
    $DB->query("
        INSERT INTO rma_items (
            barcode,
            tracking_number,
            serial_number,
            sku,
            ean,
            product_name,
            supplier_name,
            document_id,
            document_number,
            document_date,
            fault_type_id,
            fault_description,
            cost_at_creation,
            status,
            location,
            needs_review,
            date_discovered,
            created_by,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unprocessed', ?, ?, CURDATE(), ?, NOW()
        )
    ", [
        $barcode ?: null,
        $tracking_number ?: null,
        $serial_number ?: null,
        $sku,
        $ean ?: null,
        $product_name,
        $supplier_name ?: null,
        $document_id ?: null,
        $document_number ?: null,
        $document_date ?: null,
        $fault_type_id,
        $fault_description ?: null,
        $cost ?: null,
        $effective_location,
        $needs_review,
        $user_id
    ]);

    $response = json_encode([
        'success' => true,
        'message' => 'RMA created successfully'
    ]);
    
    echo $response;
    flush();

} catch(Exception $e) {
    $response = json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    echo $response;
    flush();
}
?>