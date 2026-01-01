<?php
require '../php/bootstrap.php';

// Check admin permissions
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
if($user_details['admin'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : null;
$data = [
    'item_name' => isset($_POST['item_name']) ? trim($_POST['item_name']) : '',
    '`condition`' => isset($_POST['condition']) ? trim($_POST['condition']) : 'good', // Note the backticks
    'serial_number' => isset($_POST['serial_number']) ? trim($_POST['serial_number']) : null,
    'status' => isset($_POST['status']) ? trim($_POST['status']) : 'in_stock',
    'purchase_price' => isset($_POST['purchase_price']) && $_POST['purchase_price'] !== '' ? 
                       (float)$_POST['purchase_price'] : null,
    'customer_id' => isset($_POST['customer_id']) ? trim($_POST['customer_id']) : null,
    'notes' => isset($_POST['notes']) ? trim($_POST['notes']) : null
];

// Validate required fields
if(empty($data['item_name'])) {
    echo json_encode(['success' => false, 'message' => 'Item name is required']);
    exit;
}

try {
    if($id) {
        // Update
        $set_clause = [];
        $params = [];
        foreach($data as $key => $value) {
            $set_clause[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        
        $query = "UPDATE second_hand_items SET " . implode(", ", $set_clause) . " WHERE id = ?";
        $DB->query($query, $params);
    } else {
        // Insert
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $query = "INSERT INTO second_hand_items (" . implode(", ", $fields) . ") 
                  VALUES (" . implode(", ", $placeholders) . ")";
        $DB->query($query, array_values($data));
    }
    
    echo json_encode(['success' => true]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>