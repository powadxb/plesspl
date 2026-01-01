<?php
session_start();
require_once '../../php/bootstrap.php';

// Try to load mPDF - if not available, fall back to HTML
$use_mpdf = false;
if(file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
    if(class_exists('Mpdf\Mpdf')) {
        $use_mpdf = true;
    }
}

if(!isset($_SESSION['dins_user_id'])){
    die('Not authenticated');
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check permissions - ONLY use control panel permissions
$permissions_file = __DIR__.'/../php/secondhand-permissions.php';
if (file_exists($permissions_file)) {
    require $permissions_file;
}

$can_view = (function_exists('hasSecondHandPermission') && hasSecondHandPermission($user_id, 'SecondHand-View', $DB));

if (!$can_view) {
    die('Access denied - insufficient permissions');
}

$trade_in_id = $_GET['trade_in_id'] ?? 0;
if(!$trade_in_id) {
    die('Trade-in ID required');
}

// Get trade-in data
$trade_in = $DB->query("
    SELECT ti.*, u.username as created_by_name
    FROM trade_in_items ti
    LEFT JOIN users u ON ti.created_by = u.id
    WHERE ti.id = ?
", [$trade_in_id])[0] ?? null;

if(!$trade_in) {
    die('Trade-in not found');
}

// Get items
$items = $DB->query("
    SELECT * FROM trade_in_items_details
    WHERE trade_in_id = ?
    ORDER BY id
", [$trade_in_id]);

// Get ID documents
$id_docs = $DB->query("
    SELECT * FROM trade_in_id_photos
    WHERE trade_in_id = ?
    ORDER BY id
", [$trade_in_id]);

$location_name = ($trade_in['location'] == 'cs') ? 'Commerce Street' : 'Argyle Street';

$doc_types = [
    'passport' => 'Passport',
    'driving_license' => 'Driving License',
    'national_id' => 'National ID Card',
    'bank_statement' => 'Bank Statement',
    'council_tax' => 'Council Tax Bill',
    'utility_bill' => 'Utility Bill'
];

$payment_methods = [
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'cash_bank' => 'Cash & Bank Transfer'
];

// Build HTML content
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Trade-In Agreement #<?=$trade_in_id?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.2;
            margin: 12px;
        }
        h1 {
            text-align: center;
            color: #0066cc;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 5px;
            font-size: 13pt;
            margin: 0 0 8px 0;
        }
        h2 {
            background-color: #e8f4ff;
            padding: 4px 6px;
            margin: 8px 0 4px 0;
            font-size: 10pt;
        }
        h3 {
            background-color: #fff3cd;
            padding: 3px 5px;
            margin: 6px 0 3px 0;
            font-size: 9pt;
        }
        .info-box {
            border: 1px solid #ddd;
            padding: 5px;
            margin: 4px 0;
            font-size: 8pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        table.bordered {
            border: 1px solid #000;
        }
        table.bordered th {
            background-color: #f0f0f0;
            padding: 3px 5px;
            border: 1px solid #000;
            font-weight: bold;
            font-size: 8pt;
        }
        table.bordered td {
            padding: 3px 5px;
            border: 1px solid #666;
            font-size: 8pt;
        }
        table.plain td {
            padding: 2px 0;
            font-size: 8pt;
        }
        .total-row {
            background-color: #e8f4ff;
            font-weight: bold;
        }
        .signature-box {
            border: 1px solid #000;
            padding: 15px 5px 5px 5px;
            margin: 5px 0;
            min-height: 35px;
        }
        .agreement-text {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 6px;
            margin: 5px 0;
            font-size: 8pt;
        }
        ol, ul {
            line-height: 1.3;
            margin: 2px 0;
            padding-left: 18px;
        }
        ol li {
            margin-bottom: 2px;
            font-size: 8pt;
        }
        ul li {
            margin-bottom: 1px;
            font-size: 7pt;
        }
        .terms-section {
            margin-top: 8px;
        }
        .terms-section h3 {
            font-size: 9pt;
            margin-bottom: 2px;
        }
        .header-info {
            text-align: center;
            margin-bottom: 6px;
            font-size: 8pt;
        }
        @media print {
            body { margin: 8px; }
        }
            .terms-section { page-break-before: auto; }
        }
    </style>
</head>
<body>
    <h1>Priceless Computing - Trade-In</h1>
    
    <table class="plain" style="margin-bottom: 4px;">
        <tr>
            <td style="width: 25%;"><strong>Ref:</strong> <?=htmlspecialchars($trade_in['trade_in_reference'])?></td>
            <td style="width: 25%;"><strong>Location:</strong> <?=$location_name?></td>
            <td style="width: 25%;"><strong>Date:</strong> <?=date('d/m/Y', strtotime($trade_in['created_at']))?></td>
            <td style="width: 25%;"><strong>Staff:</strong> <?=htmlspecialchars($trade_in['created_by_name'])?></td>
        </tr>
    </table>
    
    <h2>Seller</h2>
    <table class="plain">
        <tr>
            <td style="width: 15%;"><strong>Name:</strong></td>
            <td style="width: 35%;"><?=htmlspecialchars($trade_in['customer_name'])?></td>
            <td style="width: 15%;"><strong>Phone:</strong></td>
            <td style="width: 35%;"><?=htmlspecialchars($trade_in['customer_phone'] ?: 'N/A')?></td>
        </tr>
    </table>
    
    <h3>ID Verification</h3>
    <?php if(!empty($id_docs)): ?>
    <p style="font-size: 7pt; margin: 2px 0;">
    <?php 
    $doc_list = [];
    foreach($id_docs as $doc) {
        $type_name = $doc_types[$doc['document_type']] ?? $doc['document_type'];
        $doc_list[] = $type_name . ($doc['document_number'] ? ' (' . htmlspecialchars($doc['document_number']) . ')' : '');
    }
    echo implode(', ', $doc_list);
    ?>
    </p>
    <?php else: ?>
    <p style="font-size: 7pt; margin: 2px 0;">No ID documents</p>
    <?php endif; ?>
    
    <h2>Items</h2>
    <table class="bordered">
        <tr>
            <th style="width: 30%;">Item</th>
            <th style="width: 15%;">Condition</th>
            <th style="width: 15%;">Code</th>
            <th style="width: 20%;">Serial</th>
            <th style="width: 12%;">Price</th>
        </tr>
        <?php 
        $total = 0;
        foreach($items as $item): 
            $total += $item['price_paid'];
        ?>
        <tr>
            <td>
                <strong><?=htmlspecialchars($item['item_name'])?></strong>
                <?php if($item['category']): ?>
                <br><small><?=htmlspecialchars($item['category'])?></small>
                <?php endif; ?>
            </td>
            <td><?=$item['condition'] ? ucfirst($item['condition']) : 'Not Set'?></td>
            <td>
                <?php if($item['preprinted_code']): ?>
                <strong><?=htmlspecialchars($item['preprinted_code'])?></strong>
                <?php elseif($item['tracking_code']): ?>
                <?=htmlspecialchars($item['tracking_code'])?>
                <?php else: ?>
                -
                <?php endif; ?>
            </td>
            <td><?=$item['serial_number'] ? htmlspecialchars($item['serial_number']) : '-'?></td>
            <td><strong>£<?=number_format($item['price_paid'], 2)?></strong></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="4" style="text-align: right;"><strong>TOTAL:</strong></td>
            <td><strong>£<?=number_format($total, 2)?></strong></td>
        </tr>
    </table>
    
    <h2>Payment</h2>
    <table class="plain">
        <tr>
            <td style="width: 18%;"><strong>Method:</strong></td>
            <td style="width: 32%;"><?=$payment_methods[$trade_in['payment_method']] ?? 'N/A'?></td>
            <?php if($trade_in['payment_method'] == 'cash' || $trade_in['payment_method'] == 'cash_bank'): ?>
            <td style="width: 18%;"><strong>Cash:</strong></td>
            <td style="width: 32%;">£<?=number_format($trade_in['cash_amount'], 2)?></td>
            <?php else: ?>
            <td colspan="2"></td>
            <?php endif; ?>
        </tr>
        <?php if($trade_in['payment_method'] == 'bank_transfer' || $trade_in['payment_method'] == 'cash_bank'): ?>
        <tr>
            <td><strong>Bank:</strong></td>
            <td>£<?=number_format($trade_in['bank_amount'], 2)?></td>
            <?php if($trade_in['bank_account_name']): ?>
            <td><strong>Account:</strong></td>
            <td><?=htmlspecialchars($trade_in['bank_account_name'])?> | <?=htmlspecialchars($trade_in['bank_account_number'])?> | <?=htmlspecialchars($trade_in['bank_sort_code'])?></td>
            <?php else: ?>
            <td colspan="2"></td>
            <?php endif; ?>
        </tr>
        <?php endif; ?>
    </table>
    
    <?php if($trade_in['compliance_notes']): ?>
    <p style="font-size: 7pt; margin: 4px 0;"><strong>Notes:</strong> <?=nl2br(htmlspecialchars($trade_in['compliance_notes']))?></p>
    <?php endif; ?>
    
    <div style="margin-top: 8px;">
        <h2 style="background-color: #0066cc; color: white; padding: 5px; text-align: center; font-size: 10pt; margin: 6px 0 4px 0;">Trade-In Declaration & Terms</h2>
        
        <h3 style="background-color: #e8f4ff; padding: 3px 5px; margin: 4px 0 2px 0; font-size: 9pt;">Seller Declaration</h3>
        <p style="margin: 2px 0 2px 10px; font-size: 8pt;"><strong>I confirm that:</strong></p>
        <ol style="margin: 0 0 4px 20px; line-height: 1.3; font-size: 8pt;">
            <li>I am the legal owner of the item(s) listed above and have the right to sell them.</li>
            <li>The item(s) are not stolen, lost, counterfeit, or subject to any finance, lease, or third-party claim.</li>
            <li>All details I have provided are true and accurate to the best of my knowledge.</li>
            <li>I am aged 16 or over and have provided valid identification when requested.</li>
        </ol>
        
        <h3 style="background-color: #e8f4ff; padding: 3px 5px; margin: 4px 0 2px 0; font-size: 9pt;">Sale & Payment</h3>
        <ol style="margin: 0 0 4px 20px; line-height: 1.3; font-size: 8pt;">
            <li>I agree to sell the item(s) listed above to Priceless Computing for the agreed price shown on this form.</li>
            <li>I understand that payment is made in full at the time of signing by the agreed method (cash, bank transfer, or store credit).</li>
            <li>Once payment has been made and this form is signed, the sale is final and cannot be reversed.</li>
        </ol>
        
        <h3 style="background-color: #e8f4ff; padding: 3px 5px; margin: 4px 0 2px 0; font-size: 9pt;">Ownership & Liability</h3>
        <ol style="margin: 0 0 4px 20px; line-height: 1.3; font-size: 8pt;">
            <li>Ownership of the item(s) transfers to Priceless Computing immediately upon signing and payment.</li>
            <li>If any item is later found to be stolen, illegal, or not legally owned by me, I understand that:
                <ul style="margin: 2px 0; font-size: 7pt;">
                    <li>Priceless Computing may retain the item(s),</li>
                    <li>the matter may be reported to the police, and</li>
                    <li>I may be required to repay the amount received.</li>
                </ul>
            </li>
        </ol>
        
        <h3 style="background-color: #e8f4ff; padding: 3px 5px; margin: 4px 0 2px 0; font-size: 9pt;">Data & Records</h3>
        <ol style="margin: 0 0 4px 20px; line-height: 1.3; font-size: 8pt;">
            <li>I understand that my details may be recorded and retained for legal, security, and record-keeping purposes in line with data protection law.</li>
        </ol>
        
        <h3 style="background-color: #e8f4ff; padding: 3px 5px; margin: 4px 0 2px 0; font-size: 9pt;">Governing Law</h3>
        <ol style="margin: 0 0 4px 20px; line-height: 1.3; font-size: 8pt;">
            <li>This agreement is governed by Scots law.</li>
        </ol>
        
        <div style="border: 1px solid #0066cc; padding: 4px; margin: 5px 0; background-color: #f8f9fa;">
            <p style="margin: 0; font-size: 7pt; color: #666;"><strong>Important:</strong> By signing below, you confirm that you have read, understood, and agree to all the terms and conditions stated above.</p>
        </div>
    </div>
    
    <table style="margin-top: 8px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <strong style="font-size: 8pt;">Seller Name:</strong>
                <div style="border-bottom: 1px solid #000; margin: 2px 0 8px 0; min-height: 14px; font-size: 8pt;">
                    <?=htmlspecialchars($trade_in['customer_name'])?>
                </div>
                
                <strong style="font-size: 8pt;">Seller Signature:</strong>
                <div class="signature-box"></div>
                
                <strong style="font-size: 8pt;">Date:</strong>
                <div style="border-bottom: 1px solid #000; margin: 2px 0; min-height: 14px; width: 120px; font-size: 8pt;">
                    <?=date('d/m/Y')?>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <strong style="font-size: 8pt;">Staff Name:</strong>
                <div style="border-bottom: 1px solid #000; margin: 2px 0 8px 0; min-height: 14px; font-size: 8pt;">
                    <?=htmlspecialchars($trade_in['created_by_name'])?>
                </div>
                
                <strong style="font-size: 8pt;">Staff Signature:</strong>
                <div class="signature-box"></div>
                
                <strong style="font-size: 8pt;">Date:</strong>
                <div style="border-bottom: 1px solid #000; margin: 2px 0; min-height: 14px; width: 120px; font-size: 8pt;">
                    <?=date('d/m/Y')?>
                </div>
            </td>
        </tr>
    </table>
    
    <div style="margin-top: 30px; font-size: 9pt; color: #666; text-align: center; border-top: 1px solid #ddd; padding-top: 10px;">
        Priceless Computing | <?=$location_name?> | Generated: <?=date('d/m/Y H:i')?>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

if($use_mpdf) {
    // Use mPDF if available
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);
        
        $mpdf->WriteHTML($html);
        $mpdf->Output('trade_in_' . $trade_in_id . '.pdf', 'D');
    } catch (Exception $e) {
        // If mPDF fails, fall back to HTML
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
} else {
    // Output as printable HTML
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    echo '<script>window.print();</script>';
}
