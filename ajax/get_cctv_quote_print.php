<?php
// ajax/get_cctv_quote_print.php
session_start();
require '../php/bootstrap.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    exit('Not authorized');
}

try {
    // Get quote data from POST
    if (!isset($_POST['quote'])) {
        throw new Exception('No quote data provided');
    }
    
    $quoteData = json_decode($_POST['quote'], true);
    if (!$quoteData) {
        throw new Exception('Invalid quote data');
    }

    $customer = $quoteData['customer'];
    $components = $quoteData['components'];
    $services = $quoteData['services'];
    $totals = $quoteData['totals'];
    $priceType = $quoteData['priceType'] == 'R' ? 'Retail' : 'Trade';

    // Component type labels
    $componentLabels = [
        'recorder' => 'DVR/NVR Recorder',
        'hdd' => 'Hard Drive',
        'camera' => 'Camera',
        'poe_switch' => 'PoE Switch',
        'network_switch' => 'Network Switch',
        'power_supply' => 'Power Supply',
        'ups' => 'UPS',
        'monitor' => 'Monitor',
        'camera_cable' => 'Camera Cable',
        'connectors' => 'Connectors',
        'internet_cable' => 'Internet Cable',
        'video_cable' => 'HDMI/VGA Cable',
        'power_extension' => 'Power Extension',
        'mounting' => 'Mounting Brackets',
        'cable_management' => 'Cable Management',
        'additional' => 'Additional Item'
    ];

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>CCTV System Quote - <?php echo htmlspecialchars($customer['name']); ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                padding: 20px;
                font-size: 12pt;
                line-height: 1.6;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #333;
                padding-bottom: 20px;
            }
            
            .header h1 {
                font-size: 24pt;
                color: #333;
                margin-bottom: 10px;
            }
            
            .header p {
                color: #666;
                font-size: 11pt;
            }
            
            .customer-info {
                margin-bottom: 30px;
                padding: 15px;
                background: #f8f9fa;
                border-left: 4px solid #007bff;
            }
            
            .customer-info h3 {
                margin-bottom: 10px;
                color: #007bff;
            }
            
            .customer-info p {
                margin: 5px 0;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            table th {
                background: #007bff;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: bold;
            }
            
            table td {
                padding: 10px 12px;
                border-bottom: 1px solid #ddd;
            }
            
            table tr:nth-child(even) {
                background: #f8f9fa;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .component-type {
                font-weight: bold;
                color: #333;
            }
            
            .badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 9pt;
                font-weight: bold;
            }
            
            .badge-info {
                background: #17a2b8;
                color: white;
            }
            
            .badge-warning {
                background: #ffc107;
                color: #333;
            }
            
            .totals-section {
                margin-top: 30px;
                padding: 20px;
                background: #f8f9fa;
                border: 2px solid #007bff;
            }
            
            .totals-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                font-size: 14pt;
            }
            
            .totals-row.final {
                border-top: 2px solid #333;
                margin-top: 10px;
                padding-top: 15px;
                font-weight: bold;
                font-size: 18pt;
                color: #007bff;
            }
            
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #ddd;
                text-align: center;
                color: #666;
                font-size: 10pt;
            }
            
            .terms {
                margin-top: 30px;
                padding: 15px;
                background: #f8f9fa;
                border: 1px solid #ddd;
            }
            
            .terms h4 {
                margin-bottom: 10px;
                color: #333;
            }
            
            .terms ul {
                margin-left: 20px;
            }
            
            .terms li {
                margin: 5px 0;
            }
            
            @media print {
                body {
                    padding: 0;
                }
                
                .page-break {
                    page-break-before: always;
                }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>CCTV System Quotation</h1>
            <p>Professional Security Camera Installation</p>
            <p>Quote Date: <?php echo date('d/m/Y'); ?> | Price Type: <?php echo $priceType; ?></p>
        </div>

        <div class="customer-info">
            <h3>Customer Details</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($customer['name']); ?></p>
            <?php if (!empty($customer['phone'])): ?>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></p>
            <?php endif; ?>
            <?php if (!empty($customer['email'])): ?>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
            <?php endif; ?>
            <?php if (!empty($customer['address'])): ?>
            <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
            <?php endif; ?>
        </div>

        <h3 style="margin-bottom: 15px; color: #333;">System Components</h3>
        <table>
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Description</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($components as $component): ?>
                <tr>
                    <td>
                        <span class="component-type">
                            <?php echo $componentLabels[$component['type']] ?? ucfirst($component['type']); ?>
                        </span>
                        <?php if ($component['isManual']): ?>
                        <br><span class="badge badge-info">Manual Entry</span>
                        <?php endif; ?>
                        <?php if (!empty($component['priceEdited'])): ?>
                        <br><span class="badge badge-warning">Custom Price</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($component['name']); ?>
                        <?php if (!empty($component['sku'])): ?>
                        <br><small style="color: #666;">SKU: <?php echo htmlspecialchars($component['sku']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo $component['quantity']; ?></td>
                    <td class="text-right">£<?php echo number_format($component['priceIncVat'], 2); ?></td>
                    <td class="text-right">£<?php echo number_format($component['priceIncVat'] * $component['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php if ($services['installation'] > 0): ?>
                <tr>
                    <td colspan="2"><strong>Installation Labor</strong></td>
                    <td class="text-center">1</td>
                    <td class="text-right">£<?php echo number_format($services['installation'], 2); ?></td>
                    <td class="text-right">£<?php echo number_format($services['installation'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($services['configuration'] > 0): ?>
                <tr>
                    <td colspan="2"><strong>Configuration & Setup</strong></td>
                    <td class="text-center">1</td>
                    <td class="text-right">£<?php echo number_format($services['configuration'], 2); ?></td>
                    <td class="text-right">£<?php echo number_format($services['configuration'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($services['testing'] > 0): ?>
                <tr>
                    <td colspan="2"><strong>Testing & Commissioning</strong></td>
                    <td class="text-center">1</td>
                    <td class="text-right">£<?php echo number_format($services['testing'], 2); ?></td>
                    <td class="text-right">£<?php echo number_format($services['testing'], 2); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-row final">
                <span>Total (Including VAT):</span>
                <span>£<?php echo number_format($totals['total'], 2); ?></span>
            </div>
        </div>

        <div class="terms">
            <h4>Terms & Conditions</h4>
            <ul>
                <li>Quote valid for 30 days from date of issue</li>
                <li>All prices include VAT at current rate</li>
                <li>Installation subject to site survey and feasibility</li>
                <li>Payment terms: 50% deposit, balance on completion</li>
                <li>Standard warranty applies to all equipment</li>
                <li>Professional installation by certified engineers</li>
            </ul>
        </div>

        <div class="footer">
            <p>Thank you for considering our CCTV installation services</p>
            <p>For any questions or to accept this quote, please contact us</p>
            <p style="margin-top: 10px;"><small>This quote was generated on <?php echo date('d/m/Y H:i'); ?></small></p>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    error_log('Print CCTV Quote Error: ' . $e->getMessage());
    echo '<html><body><h1>Error</h1><p>Failed to generate printable quote.</p></body></html>';
}