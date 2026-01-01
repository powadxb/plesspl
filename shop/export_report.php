<?php
// Export functionality (export_report.php)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report.csv"');

    $output = fopen('php://output', 'w');

    // Write headers
    fputcsv($output, ['Report Type', 'Category', 'Value']);

    // Write ticket stats
    foreach ($ticketStats as $stat) {
        fputcsv($output, ['Ticket Type', $stat['device_type'], $stat['type_count']]);
    }

    // Write technician stats
    foreach ($technicianStats as $tech) {
        fputcsv($output, [
            'Technician',
            $tech['username'],
            $tech['completed_tickets'] . '/' . $tech['total_tickets']
        ]);
    }

    // Write inventory usage
    foreach ($inventoryUsage as $part) {
        fputcsv($output, [
            'Part Usage',
            $part['name'],
            $part['total_quantity_used']
        ]);
    }

    fclose($output);
    exit();
}
?>
