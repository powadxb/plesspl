<?php
require 'php/bootstrap.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courier = trim($_POST['courier']);
    $from = trim($_POST['from']);
    $num_boxes = (int) $_POST['num_boxes'];
    $notes = trim($_POST['notes']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $date = date('Y-m-d');
    $time = date('H:i:s');

    // Validate username and password
    $user_query = "SELECT * FROM users WHERE username = ?";
    $user = $DB->query($user_query, [$username]);

    if (!$user) {
        $response['message'] = 'Invalid username.';
    } elseif (!password_verify($password, $user[0]['password'])) {
        $response['message'] = 'Invalid password.';
    } elseif (empty($courier) || empty($from)) {
        $response['message'] = 'Courier and From fields are required.';
    } elseif ($num_boxes <= 0) {
        $response['message'] = 'Number of boxes must be greater than zero.';
    } else {
        // Add entry to the database, including the user who added it
        $query = "INSERT INTO courier_logs (date, time, courier, `from`, num_boxes, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $result = $DB->query($query, [$date, $time, $courier, $from, $num_boxes, $notes, $username]);

        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Courier log entry added successfully.';
        } else {
            $response['message'] = 'Failed to add courier log entry.';
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
