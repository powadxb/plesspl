<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/php/bootstrap.php';
require_once __DIR__ . '/fpdi/src/autoload.php';


use setasign\Fpdi\Fpdi;

$user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'];
$user_details = $DB->query(" SELECT * FROM users WHERE id=?", [$user_id])[0];

function format_name($name) {
    $name = trim($name);
    $name_length = strlen($name);

    if ($name_length > 87) {
        $name = substr($name, 0, 85) . '...';
    }

    return $name;
}

$skus = explode(PHP_EOL, trim($_POST['sku_list']));
$labels = [];

foreach ($skus as $sku) {
    $sku = trim($sku);

    if (!empty($sku)) {
        $result = $DB->query("SELECT sku, name, price, ean FROM master_products WHERE sku = ? OR ean = ?", [$sku, $sku]);

        if (count($result) > 0) {
            $labels[] = $result[0];
        }
    }
}

// Create PDF document
$pdf = new Fpdi('P', 'mm', 'A4');
$pdf->SetMargins(0, 0, 0);

// Get label dimensions
$label_width = 100; // mm
$label_height = 40; // mm

// Calculate number of labels per page
$labels_per_row = 4;
$rows_per_page = 5;
$total_labels_per_page = $labels_per_row * $rows_per_page;

// Loop through labels and add to PDF document
$label_count = 0;
foreach ($labels as $label) {
    $sku = htmlspecialchars($label['sku']);
    $name = htmlspecialchars($label['name']);
    $price = $label['price'] * 1.2;
    setlocale(LC_MONETARY, 'en_GB.UTF-8');
    $formatted_price = money_format('£%!.2n', $price);
    $formatted_price_pennies = substr($formatted_price, -2);
    $formatted_price_pounds = substr($formatted_price, 0, -3);

    // Calculate label position on page
    $row_number = floor($label_count / $labels_per_row);
    $col_number = $label_count % $labels_per_row;
    $x = $col_number * $label_width;
    $y = $row_number * $label_height;

    // Add label to PDF
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    // Add background color
    $pdf->SetFillColor(0, 0, 0);
    $pdf->Rect($x, $y, $label_width, $label_height, 'F');

    // Add SKU label
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY($x + 5, $y + 5);
$pdf->Write(0, 'SKU', '', 0, 'L', true, 0, false, false, 0);

// Add SKU
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', '', 12);
$pdf->SetXY($x + 5, $y + 12);
$pdf->Write(0, $sku, '', 0, 'L', true, 0, false, false, 0);

// Add name
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetXY($x + 5, $y + 25);
$pdf->MultiCell($label_width - 10, 8, $name, 0, 'C', false);

// Add price
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY($x + 5, $y + $label_height - 15);
$pdf->Write(0, 'Price', '', 0, 'L', true, 0, false, false, 0);

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', '', 12);
$pdf->SetXY($x + 5, $y + $label_height - 8);
$pdf->Write(0, $formatted_price, '', 0, 'L', true, 0, false, false, 0);

// Increment counters
if ($label_count % $labels_per_row == 0) {
    // Move to next row
    $x = $left_margin;
    $y += $row_height;
} else {
    // Move to next column
    $x += $label_width + $horizontal_gap;
}

$label_count++;

if ($label_count > $total_labels) {
    break;
}
}

// Output PDF to browser
$pdf->Output('I', 'shelf_labels.pdf');
ob_end_flush();
