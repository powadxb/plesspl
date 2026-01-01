<?php
require 'php/bootstrap.php';

$response = ['success' => false, 'data' => [], 'message' => ''];
$search = $_GET['search'] ?? '';

$query = "SELECT id, date, time, courier, `from`, num_boxes, notes 
          FROM courier_logs 
          WHERE 
              date LIKE ? OR 
              time LIKE ? OR 
              courier LIKE ? OR 
              `from` LIKE ? OR 
              num_boxes LIKE ? OR 
              notes LIKE ?
          ORDER BY created_at DESC";

$searchTerm = '%' . $search . '%';
$params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
$logs = $DB->query($query, $params);

if ($logs) {
    $response['success'] = true;
    $response['data'] = $logs;
} else {
    $response['message'] = 'Failed to fetch courier logs.';
}

echo json_encode($response);
