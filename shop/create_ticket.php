<?php
// create_ticket.php
require_once 'config.php';
require_once 'auth.php';
require_once 'ticket.php';

$auth = new Auth(getDB());
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // Create customer
        $stmt = $db->prepare("
            INSERT INTO customers (first_name, last_name, email, phone)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone']
        ]);
        $customerId = $db->lastInsertId();

       do {
    // Get the year, month, and day
    $year = strtoupper(dechex(date('Y') % 100)); // Last two digits of the year in hex
    $month = strtoupper(dechex(date('n')));     // 1-C for months
    $day = strtoupper(dechex(date('d')));       // 1-1F for days

    // Pad components
    $year = str_pad($year, 2, '0', STR_PAD_LEFT); // 2 characters for year
    $month = str_pad($month, 1, '0', STR_PAD_LEFT); // 1 character for month
    $day = str_pad($day, 2, '0', STR_PAD_LEFT);     // 2 characters for day

    // Adjust random to fit remaining 2 characters
    $random = strtoupper(bin2hex(random_bytes(1))); // Generate 2 hex digits

    // Combine components to create exactly 7 characters: YYMDDRR
    $ticketNumber = $year . $month . $day . $random;
            
            // Check if exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM repair_tickets WHERE ticket_number = ?");
            $stmt->execute([$ticketNumber]);
        } while ($stmt->fetchColumn() > 0);

        // Create ticket
        $stmt = $db->prepare("
            INSERT INTO repair_tickets 
            (customer_id, ticket_number, device_type, serial_number, issue_description, repair_cost, status, location_id)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([
            $customerId,
            $ticketNumber,
            $_POST['device_type'],
            $_POST['serial_number'],
            $_POST['issue_description'],
            !empty($_POST['repair_cost']) ? $_POST['repair_cost'] : null,
            $_SESSION['location_id']
        ]);
        $ticketId = $db->lastInsertId();

        // Handle photo uploads
        if ($ticketId && isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $filename = uniqid() . '_' . $_FILES['photos']['name'][$key];
                    if (move_uploaded_file($tmp_name, UPLOAD_DIR . $filename)) {
                        $stmt = $db->prepare("
    INSERT INTO ticket_photos 
    (ticket_id, photo_path, uploaded_by, photo_type)
    VALUES (?, ?, ?, 'intake')
");$stmt = $db->prepare("
                            INSERT INTO ticket_photos 
                            (ticket_id, photo_path, uploaded_by)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$ticketId, $filename, $_SESSION['user_id']]);
                    }
                }
            }
        }

        header('Location: tickets.php?success=1');
        exit();

    } catch (Exception $e) {
        error_log("Ticket creation error: " . $e->getMessage());
        header('Location: tickets.php?error=' . urlencode("Failed to create ticket"));
        exit();
    }
}

header('Location: tickets.php');
exit();