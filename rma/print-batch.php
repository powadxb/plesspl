<?php
/**
 * Print Batch Sheet
 * Printable packing list for supplier RMA batches
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: ../login.php");
  exit();
}

require __DIR__.'/../php/bootstrap.php';

$batch_id = $_GET['batch_id'] ?? null;

if (!$batch_id) {
    die('Batch ID required');
}

// Get batch details
$batchQuery = "
    SELECT 
        b.*,
        CONCAT(u.first_name, ' ', u.last_name) AS creator_name
    FROM rma_supplier_batches b
    LEFT JOIN users u ON b.created_by = u.id
    WHERE b.id = ?
";

$batches = $DB->query($batchQuery, [$batch_id]);

if (empty($batches)) {
    die('Batch not found');
}

$batch = $batches[0];

// Get items
$itemsQuery = "
    SELECT 
        i.*,
        f.fault_name
    FROM rma_items i
    LEFT JOIN rma_fault_types f ON i.fault_type_id = f.id
    WHERE i.supplier_rma_batch_id = ?
    ORDER BY i.id
";

$items = $DB->query($itemsQuery, [$batch_id]);

$total_value = array_sum(array_column($items, 'cost_at_creation'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch #<?php echo $batch['id']; ?> - Packing List</title>
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
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
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
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px;">
            🖨️ Print This Page
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; margin-left: 10px;">
            ✖️ Close
        </button>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="company-name">PRICELESS COMPUTING</div>
        <h1>RMA BATCH PACKING LIST</h1>
        <div>Batch #<?php echo $batch['id']; ?> - <?php echo date('d/m/Y'); ?></div>
    </div>

    <!-- Batch Information -->
    <div class="info-grid">
        <div class="info-box">
            <h3>Batch Details</h3>
            <div class="info-row">
                <span class="info-label">Batch ID:</span>
                #<?php echo $batch['id']; ?>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <?php echo strtoupper($batch['batch_status']); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Created:</span>
                <?php echo date('d/m/Y', strtotime($batch['date_created'])); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Created By:</span>
                <?php echo $batch['creator_name']; ?>
            </div>
        </div>
        
        <div class="info-box">
            <h3>Supplier Information</h3>
            <div class="info-row">
                <span class="info-label">Supplier:</span>
                <strong><?php echo $batch['supplier_name']; ?></strong>
            </div>
            <div class="info-row">
                <span class="info-label">Supplier RMA #:</span>
                <strong><?php echo $batch['supplier_rma_number'] ?: 'Not Yet Assigned'; ?></strong>
            </div>
            <?php if ($batch['shipping_tracking']): ?>
            <div class="info-row">
                <span class="info-label">Tracking:</span>
                <?php echo $batch['shipping_tracking']; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Table -->
    <h3 style="margin-bottom: 10px;">Items in This Batch (<?php echo count($items); ?> items)</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 12%;">Tracking</th>
                <th style="width: 10%;">SKU</th>
                <th style="width: 30%;">Product</th>
                <th style="width: 15%;">Serial Number</th>
                <th style="width: 15%;">Fault</th>
                <th style="width: 10%;">Value</th>
            </tr>
        </thead>
        <tbody>
            <?php $counter = 1; foreach ($items as $item): ?>
            <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo $item['tracking_number'] ?: $item['barcode']; ?></td>
                <td><?php echo $item['sku'] ?: '-'; ?></td>
                <td><?php echo $item['product_name']; ?></td>
                <td><?php echo $item['serial_number'] ?: '-'; ?></td>
                <td><?php echo $item['fault_name']; ?></td>
                <td>£<?php echo number_format($item['cost_at_creation'] ?: 0, 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Summary -->
    <div class="summary">
        <div>Total Items: <strong><?php echo count($items); ?></strong></div>
        <div>Total Value: <strong>£<?php echo number_format($total_value, 2); ?></strong></div>
    </div>

    <!-- Notes -->
    <?php if ($batch['notes']): ?>
    <div style="margin-top: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd;">
        <strong>Notes:</strong><br>
        <?php echo nl2br(htmlspecialchars($batch['notes'])); ?>
    </div>
    <?php endif; ?>

    <!-- Signature Section -->
    <div class="signature-section">
        <div style="margin-bottom: 20px;">
            <strong>Packed By:</strong>
            <div class="signature-line" style="width: 300px;">
                Name & Signature
            </div>
        </div>
        
        <div>
            <strong>Date Packed:</strong>
            <div class="signature-line" style="width: 200px;">
                Date
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 10px;">
        Priceless Computing Ltd - Commerce Street, Glasgow - Generated <?php echo date('d/m/Y H:i'); ?>
    </div>
</body>
</html>
