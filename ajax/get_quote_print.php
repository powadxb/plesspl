<?php
// ajax/get_quote_print.php
session_start();
require 'php/bootstrap.php';

// Ensure user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    http_response_code(401);
    exit('Not authorized');
}

try {
    $quoteData = json_decode($_POST['quote'], true);
    if (!$quoteData) {
        throw new Exception('Invalid quote data');
    }

    // Start building HTML
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>PC Build Quote</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.4; }
            .header { margin-bottom: 20px; }
            .customer-info { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f8f9fa; }
            .totals { margin-top: 20px; text-align: right; }
            .build-note { margin-top: 20px; font-size: 10pt; }
            @media print {
                body { margin: 1cm; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>PC Build Quote</h2>
            <p>Date: ' . date('d/m/Y') . '</p>
        </div>

        <div class="customer-info">
            <strong>' . htmlspecialchars($quoteData['customer']['name']) . '</strong><br>
            ' . nl2br(htmlspecialchars($quoteData['customer']['address'])) . '<br>
            ' . htmlspecialchars($quoteData['customer']['phone']) . '<br>
            ' . htmlspecialchars($quoteData['customer']['email']) . '
        </div>

        <table>
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Description</th>
                    <th style="text-align: right">Price</th>
                </tr>
            </thead>
            <tbody>';

    // Add components
    foreach ($quoteData['components'] as $component) {
        if (empty($component['name'])) continue;
        
        $html .= '
            <tr>
                <td>' . htmlspecialchars($component['type']) . '</td>
                <td>' . htmlspecialchars($component['name']) . '</td>
                <td style="text-align: right">£' . number_format($component['price'], 2) . '</td>
            </tr>';
    }

    // Add additional items
    foreach ($quoteData['additionalItems'] as $item) {
        $html .= '
            <tr>
                <td>Additional Item</td>
                <td>' . htmlspecialchars($item['name']) . '</td>
                <td style="text-align: right">£' . number_format($item['price'], 2) . '</td>
            </tr>';
    }

    // Add build charge
    $html .= '
            <tr>
                <td>Build Charge</td>
                <td>PC Build and Testing Service</td>
                <td style="text-align: right">£' . number_format($quoteData['buildCharge'], 2) . '</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <p><strong>Total (Including VAT): £' . number_format($quoteData['totals']['total'], 2) . '</strong></p>
    </div>

    <div class="build-note">
        <p>This quote includes:</p>
        <ul>
            <li>Professional assembly of all components</li>
            <li>System testing and optimization</li>
            <li>Installation of operating system (if included)</li>
            <li>All prices include VAT</li>
        </ul>
    </div>
    </body>
    </html>';

    echo $html;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error generating quote: ' . $e->getMessage();
}