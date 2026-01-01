<?php
require_once '../php/bootstrap.php';

// Check authentication
if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    die('Not authenticated');
}

// Check permissions
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = ($user_details['admin'] != 0 || $user_details['useradmin'] >= 1);
$has_tradein_access = $is_admin || (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-View', $DB));

if (!$has_tradein_access) {
    die('Access denied');
}

$trade_in_id = $_GET['trade_in_id'] ?? 0;

if (!$trade_in_id) {
    die('Trade-in ID is required');
}

// Get trade-in details
$trade_in = $DB->query("
    SELECT ti.*, u.username as created_by_name
    FROM trade_in_items ti
    LEFT JOIN users u ON ti.created_by = u.id
    WHERE ti.id = ?
", [$trade_in_id])[0] ?? null;

if (!$trade_in) {
    die('Trade-in item not found');
}

// Get customer details from repairs database (if available)
$customer = null;
try {
    $repairsDB = new PDO(
        "mysql:host=localhost;dbname=sitegroundrepairs;charset=utf8mb4",
        "pcquote",
        "FPxUMQ5e4JxTWMIvFgO7"
    );
    $repairsDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $customer_stmt = $repairsDB->prepare("SELECT * FROM customers WHERE id = ?");
    $customer_stmt->execute([$trade_in['customer_id']]);
    $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If repairs database is not accessible, use the data from trade_in_items
    $customer = [
        'name' => $trade_in['customer_name'] ?? 'Unknown Customer',
        'phone' => $trade_in['customer_phone'] ?? '',
        'email' => $trade_in['customer_email'] ?? '',
        'address' => $trade_in['customer_address'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade-In Receipt - #<?php echo htmlspecialchars($trade_in['trade_in_reference']); ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            @page { margin: 1cm; }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
        }

        .header {
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #0066cc;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-box {
            border: 1px solid #ddd;
            padding: 10px;
        }

        .info-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .info-row {
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background-color: #f0f0f0;
            padding: 8px;
            text-align: left;
            border: 1px solid #000;
            font-size: 11px;
        }

        td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            font-size: 11px;
        }

        .summary {
            text-align: right;
            font-size: 14px;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #000;
        }

        .summary strong {
            font-size: 16px;
        }

        .signature-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .signature-box {
            display: inline-block;
            width: 45%;
            padding: 20px;
            border: 1px solid #ddd;
            margin-right: 2%;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }

        .terms {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            font-size: 10px;
            line-height: 1.3;
        }

        .customer-acknowledgment {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px;">
            🖨️ Print Trade-In Form
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; margin-left: 10px;">
            ✖️ Close
        </button>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="company-name">PRICELESS COMPUTING</div>
        <h1>TRADE-IN RECEIPT</h1>
        <div>Trade-In #<?php echo htmlspecialchars($trade_in['trade_in_reference']); ?> - <?php echo date('d/m/Y'); ?></div>
    </div>

    <!-- Customer Information -->
    <div class="info-grid">
        <div class="info-box">
            <h3>Customer Information</h3>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <?php echo htmlspecialchars($customer['name']); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Phone:</span>
                <?php echo htmlspecialchars($customer['phone'] ?? $trade_in['customer_phone'] ?? ''); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <?php echo htmlspecialchars($customer['email'] ?? $trade_in['customer_email'] ?? ''); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Address:</span>
                <?php echo htmlspecialchars($customer['address'] ?? $trade_in['customer_address'] ?? ''); ?>
            </div>
        </div>

        <div class="info-box">
            <h3>Trade-In Details</h3>
            <div class="info-row">
                <span class="info-label">Trade-In ID:</span>
                #<?php echo htmlspecialchars($trade_in['trade_in_reference']); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <?php echo date('d/m/Y', strtotime($trade_in['trade_in_date'])); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Processed By:</span>
                <?php echo htmlspecialchars($trade_in['created_by_name']); ?>
            </div>
            <?php if ($trade_in['id_document_type'] && $trade_in['id_document_number']): ?>
            <div class="info-row">
                <span class="info-label">ID Type:</span>
                <?php echo htmlspecialchars($trade_in['id_document_type']); ?>
            </div>
            <div class="info-row">
                <span class="info-label">ID Number:</span>
                <?php echo htmlspecialchars($trade_in['id_document_number']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Trade-In Items -->
    <h3 style="margin-bottom: 10px;">Trade-In Items</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Item Description</th>
                <th style="width: 15%;">Category</th>
                <th style="width: 15%;">Condition</th>
                <th style="width: 15%;">Serial Number</th>
                <th style="width: 20%;">Trade-In Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td><?php echo htmlspecialchars($trade_in['item_name']); ?></td>
                <td><?php echo htmlspecialchars($trade_in['category']); ?></td>
                <td><?php echo htmlspecialchars($trade_in['condition_rating']); ?></td>
                <td><?php echo htmlspecialchars($trade_in['serial_number'] ?: '-'); ?></td>
                <td>£<?php echo number_format($trade_in['purchase_price'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Summary -->
    <div class="summary">
        <div>Total Trade-In Value: <strong>£<?php echo number_format($trade_in['purchase_price'], 2); ?></strong></div>
    </div>

    <!-- Customer Acknowledgment -->
    <div class="customer-acknowledgment">
        <h4>CUSTOMER ACKNOWLEDGMENT AND AGREEMENT</h4>
        <p>I, <?php echo htmlspecialchars($customer['name']); ?>, hereby acknowledge and agree to the following terms:</p>
        <ol>
            <li>I am the legal owner of the item(s) listed above.</li>
            <li>I have the right to sell/trade these item(s).</li>
            <li>The item(s) are free from any liens or encumbrances.</li>
            <li>I understand that the trade-in value is based on the condition and market value at the time of trade-in.</li>
            <li>I understand that the trade-in value is non-negotiable after the transaction is completed.</li>
            <li>I understand that I have <?php echo $trade_in['collection_date'] ? 'agreed to collect my payment on ' . date('d/m/Y', strtotime($trade_in['collection_date'])) : '24 hours'; ?> from the date of this transaction.</li>
            <li>I understand that if I do not collect my payment within the agreed time, the item(s) become the property of Priceless Computing.</li>
        </ol>
    </div>

    <!-- Terms and Conditions -->
    <div class="terms">
        <h4>TERMS AND CONDITIONS</h4>
        <p>1. The trade-in value is determined based on the condition, age, and market demand for the item(s).</p>
        <p>2. All trade-ins are subject to verification and approval. We reserve the right to refuse any trade-in.</p>
        <p>3. Items must be in the same condition as represented at the time of trade-in.</p>
        <p>4. We are not responsible for any damage to items after they are accepted for trade-in.</p>
        <p>5. All sales of trade-in items are final. No returns or exchanges will be accepted.</p>
        <p>6. We comply with all applicable laws regarding second-hand goods and customer identification.</p>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <strong>Customer Signature:</strong>
            <div class="signature-line">
                Signature & Date
            </div>
            <p style="margin-top: 5px;">Print Name: _________________________</p>
        </div>

        <div class="signature-box">
            <strong>Staff Witness:</strong>
            <div class="signature-line">
                Signature & Date
            </div>
            <p style="margin-top: 5px;">Print Name: <?php echo htmlspecialchars($trade_in['created_by_name']); ?></p>
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 10px;">
        Priceless Computing Ltd - Commerce Street, Glasgow - Trade-In Receipt Generated <?php echo date('d/m/Y H:i'); ?>
    </div>
</body>
</html>