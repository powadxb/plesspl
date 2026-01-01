<?php
session_start();
require_once '../../php/bootstrap.php';
require_once '../../vendor/autoload.php'; // Assuming TCPDF is installed via composer

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

// Create PDF
class TradeInPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 18);
        $this->Cell(0, 15, 'Priceless Computing - Trade-In Agreement', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(10);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new TradeInPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('Priceless Computing');
$pdf->SetAuthor('Priceless Computing');
$pdf->SetTitle('Trade-In Agreement #' . $trade_in_id);
$pdf->SetSubject('Trade-In Agreement');

$pdf->SetMargins(10, 20, 10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 8);

// Document info
$location_name = ($trade_in['location'] == 'cs') ? 'Commerce Street' : 'Argyle Street';
$html = '<h3 style="font-size:11pt; margin:0 0 5px 0;">Trade-In: ' . htmlspecialchars($trade_in['trade_in_reference']) . ' | ' . $location_name . ' | ' . date('d/m/Y', strtotime($trade_in['created_at'])) . '</h3>';

// Seller info - compact table
$html .= '<h4 style="background-color:#e8f4ff; padding:3px; margin:5px 0 3px 0; font-size:9pt;">Seller</h4>';
$html .= '<table border="0" cellpadding="2" style="width:100%; font-size:8pt;">';
$html .= '<tr><td width="20%"><strong>Name:</strong></td><td width="30%">' . htmlspecialchars($trade_in['customer_name']) . '</td>';
$html .= '<td width="20%"><strong>Phone:</strong></td><td width="30%">' . htmlspecialchars($trade_in['customer_phone'] ?: 'N/A') . '</td></tr>';
$html .= '</table>';

// ID Documents - single line
$html .= '<h4 style="background-color:#fff3cd; padding:3px; margin:5px 0 3px 0; font-size:9pt;">ID Verification</h4>';
if(!empty($id_docs)) {
    $doc_types = [
        'passport' => 'Passport',
        'driving_license' => 'Driving License',
        'national_id' => 'National ID',
        'bank_statement' => 'Bank Statement',
        'council_tax' => 'Council Tax',
        'utility_bill' => 'Utility Bill'
    ];
    $doc_list = [];
    foreach($id_docs as $doc) {
        $type_name = $doc_types[$doc['document_type']] ?? $doc['document_type'];
        $doc_list[] = $type_name . ($doc['document_number'] ? ' (' . htmlspecialchars($doc['document_number']) . ')' : '');
    }
    $html .= '<p style="margin:2px 0; font-size:7pt;">' . implode(', ', $doc_list) . '</p>';
} else {
    $html .= '<p style="margin:2px 0; font-size:7pt;">No ID recorded</p>';
}

// Items - compact table
$html .= '<h4 style="background-color:#d4edda; padding:3px; margin:5px 0 3px 0; font-size:9pt;">Items</h4>';
$html .= '<table border="1" cellpadding="2" style="width:100%; font-size:7pt;">';
$html .= '<tr style="background-color:#f0f0f0;">';
$html .= '<th width="40%">Item</th><th width="15%">Condition</th><th width="20%">Code</th><th width="15%">Serial</th><th width="10%">Value</th>';
$html .= '</tr>';

$total = 0;
foreach($items as $item) {
    $html .= '<tr>';
    $html .= '<td>';
    $html .= '<strong>' . htmlspecialchars($item['item_name']) . '</strong>';
    if($item['category']) $html .= '<br><small>' . htmlspecialchars($item['category']) . '</small>';
    $html .= '</td>';
    $html .= '<td>' . ($item['condition'] ? ucfirst($item['condition']) : 'Not Set') . '</td>';
    $html .= '<td>';
    if($item['preprinted_code']) $html .= '<strong>' . htmlspecialchars($item['preprinted_code']) . '</strong><br>';
    if($item['tracking_code']) $html .= htmlspecialchars($item['tracking_code']);
    $html .= '</td>';
    $html .= '<td>' . ($item['serial_number'] ? htmlspecialchars($item['serial_number']) : '-') . '</td>';
    $html .= '<td><strong>£' . number_format($item['price_paid'], 2) . '</strong></td>';
    $html .= '</tr>';
    $total += $item['price_paid'];
}

$html .= '<tr style="background-color:#e8f4ff;">';
$html .= '<td colspan="4" align="right"><strong>TOTAL:</strong></td>';
$html .= '<td><strong>£' . number_format($total, 2) . '</strong></td>';
$html .= '</tr>';
$html .= '</table>';

// Payment details - compact
if($trade_in['payment_method']) {
    $html .= '<h4 style="background-color:#d4edda; padding:3px; margin:5px 0 3px 0; font-size:9pt;">Payment</h4>';
    $payment_methods = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'cash_bank' => 'Cash & Bank'
    ];
    $html .= '<table border="0" cellpadding="2" style="width:100%; font-size:7pt;">';
    $html .= '<tr><td width="25%"><strong>Method:</strong></td><td>' . ($payment_methods[$trade_in['payment_method']] ?? 'N/A') . '</td></tr>';

    if($trade_in['payment_method'] == 'cash' || $trade_in['payment_method'] == 'cash_bank') {
        $html .= '<tr><td><strong>Cash:</strong></td><td>£' . number_format($trade_in['cash_amount'], 2) . '</td></tr>';
    }

    if($trade_in['payment_method'] == 'bank_transfer' || $trade_in['payment_method'] == 'cash_bank') {
        $html .= '<tr><td><strong>Bank:</strong></td><td>£' . number_format($trade_in['bank_amount'], 2) . '</td></tr>';
        if($trade_in['bank_account_name']) {
            $html .= '<tr><td><strong>Account:</strong></td><td>' . htmlspecialchars($trade_in['bank_account_name']) . ' | ' . htmlspecialchars($trade_in['bank_account_number']) . ' | ' . htmlspecialchars($trade_in['bank_sort_code']) . '</td></tr>';
        }
    }
    $html .= '</table>';
}

// Compliance notes
if($trade_in['compliance_notes']) {
    $html .= '<p style="margin:5px 0; font-size:7pt;"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($trade_in['compliance_notes'])) . '</p>';
}

// Add new page for terms
$pdf->AddPage();

// Terms and Conditions - COMPACT
$html .= '<h4 style="background-color:#0066cc; color:white; padding:3px; text-align:center; margin:0 0 4px 0; font-size:9pt;">Trade-In Declaration & Terms</h4>';

$html .= '<p style="font-size:7pt; margin:2px 0;"><strong>Seller Declaration - I confirm:</strong></p>';
$html .= '<ol style="margin:0 0 3px 15px; line-height:1.2; font-size:7pt;">';
$html .= '<li>I am the legal owner with right to sell.</li>';
$html .= '<li>Items not stolen, counterfeit, or subject to finance/lease.</li>';
$html .= '<li>All details provided are accurate.</li>';
$html .= '<li>I am 16+ and provided valid ID.</li>';
$html .= '</ol>';

$html .= '<p style="font-size:7pt; margin:2px 0;"><strong>Sale & Payment:</strong></p>';
$html .= '<ol style="margin:0 0 3px 15px; line-height:1.2; font-size:7pt;">';
$html .= '<li>I agree to sell for the price shown.</li>';
$html .= '<li>Payment in full at signing (cash/bank/credit).</li>';
$html .= '<li>Sale is final once signed and paid.</li>';
$html .= '</ol>';

$html .= '<p style="font-size:7pt; margin:2px 0;"><strong>Ownership & Liability:</strong></p>';
$html .= '<ol style="margin:0 0 3px 15px; line-height:1.2; font-size:7pt;">';
$html .= '<li>Ownership transfers to Priceless Computing on signing.</li>';
$html .= '<li>If items found stolen/illegal: Priceless Computing keeps items, police may be notified, I repay amount received.</li>';
$html .= '</ol>';

$html .= '<p style="font-size:7pt; margin:2px 0;"><strong>Data & Records:</strong> Details recorded per data protection law. <strong>Governing Law:</strong> Scots law applies.</p>';

$html .= '<div style="border:1px solid #0066cc; padding:3px; margin:4px 0; background-color:#f8f9fa;">';
$html .= '<p style="margin:0; font-size:6pt;"><strong>Important:</strong> By signing, you confirm you have read and agree to all terms above.</p>';
$html .= '</div>';

// Signature section - compact
$html .= '<table border="0" cellpadding="3" style="width:100%; margin-top:6px; font-size:7pt;">';
$html .= '<tr>';
$html .= '<td width="50%" style="vertical-align:top;">';
$html .= '<strong>Seller Name:</strong><br>';
$html .= '<div style="border-bottom:1px solid #000; padding:2px;">' . htmlspecialchars($trade_in['customer_name']) . '</div>';
$html .= '<strong>Signature:</strong><br>';
$html .= '<div style="border:1px solid #000; height:40px; margin:3px 0;"></div>';
$html .= '<strong>Date:</strong> ' . date('d/m/Y');
$html .= '</td>';
$html .= '<td width="50%" style="vertical-align:top;">';
$html .= '<strong>Staff Name:</strong><br>';
$html .= '<div style="border-bottom:1px solid #000; padding:2px;">' . htmlspecialchars($trade_in['created_by_name']) . '</div>';
$html .= '<strong>Signature:</strong><br>';
$html .= '<div style="border:1px solid #000; height:40px; margin:3px 0;"></div>';
$html .= '<strong>Date:</strong> ' . date('d/m/Y');
$html .= '</td>';
$html .= '</tr>';
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('trade_in_' . $trade_in_id . '.pdf', 'D');
