<?php
session_start();
require_once '../../php/bootstrap.php';

header('Content-Type: application/json');

if(!isset($_SESSION['dins_user_id'])){
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check permissions - ONLY use control panel permissions
$permissions_file = __DIR__.'/../php/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

$can_manage = (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-Manage', $DB));

if (!$can_manage) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Access denied']));
}

try {
    $trade_in_id = $_POST['trade_in_id'] ?? 0;
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $cash_amount = floatval($_POST['cash_amount'] ?? 0);
    $bank_amount = floatval($_POST['bank_amount'] ?? 0);
    $bank_account_name = $_POST['bank_account_name'] ?? null;
    $bank_account_number = $_POST['bank_account_number'] ?? null;
    $bank_sort_code = $_POST['bank_sort_code'] ?? null;
    
    // Validate
    if(empty($trade_in_id)) {
        throw new Exception('Trade-in ID required');
    }
    
    // Get trade-in
    $trade_in = $DB->query("SELECT * FROM trade_in_items WHERE id = ?", [$trade_in_id])[0] ?? null;
    if(!$trade_in) {
        throw new Exception('Trade-in not found');
    }
    
    // Validate status - can only set payment for accepted trades
    if($trade_in['status'] !== 'accepted') {
        throw new Exception('Can only set payment for accepted trade-ins');
    }
    
    // Validate amounts
    $total = $cash_amount + $bank_amount;
    if(abs($total - $trade_in['total_value']) > 0.01) {
        throw new Exception('Payment total (' . number_format($total, 2) . ') must match trade-in value (' . number_format($trade_in['total_value'], 2) . ')');
    }
    
    // Validate bank details if using bank transfer
    if(($payment_method === 'bank_transfer' || $payment_method === 'cash_bank') && $bank_amount > 0) {
        if(empty($bank_account_name) || empty($bank_account_number) || empty($bank_sort_code)) {
            throw new Exception('Bank details required for bank transfer');
        }
    }
    
    // Update payment details
    $sql = "UPDATE trade_in_items SET
                payment_method = ?,
                cash_amount = ?,
                bank_amount = ?,
                bank_account_name = ?,
                bank_account_number = ?,
                bank_sort_code = ?,
                paid_at = NOW()
            WHERE id = ?";
    
    $DB->query($sql, [
        $payment_method,
        $cash_amount,
        $bank_amount,
        $bank_account_name,
        $bank_account_number,
        $bank_sort_code,
        $trade_in_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment details saved successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
