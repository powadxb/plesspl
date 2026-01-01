<?php
// Notification Class (notifications.php)
class NotificationManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function sendEmail($to, $subject, $message) {
        require 'vendor/autoload.php'; // Using PHPMailer

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_USER, 'Repair Shop System');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }

    public function notifyCustomer($ticketId, $type) {
        try {
            $ticket = $this->getTicketDetails($ticketId);

            $templates = [
                'status_update' => [
                    'subject' => 'Update on Your Repair Ticket #' . $ticketId,
                    'message' => "Dear {customer_name},<br><br>Your repair ticket #{ticket_id} status has been updated to: {status}.<br><br>Current Status: {status}<br>Estimated Completion: {estimated_completion}<br><br>
                    If you have any questions, please contact us at {location_phone}.<br><br>
                    Thank you for choosing our service!<br>
                    {location_name}"
                ],
                'ready_pickup' => [
                    'subject' => 'Your Device is Ready for Pickup - Ticket #' . $ticketId,
                    'message' => "Dear {customer_name},<br><br>Great news! Your device is ready for pickup at {location_name}.<br><br>
                    Ticket Details:<br>
                    - Ticket #: {ticket_id}<br>
                    - Device: {device_type}<br>
                    - Serial Number: {serial_number}<br><br>
                    Our location: {location_address}<br>
                    Business Hours: Mon-Fri 9AM-6PM<br><br>
                    Please bring a valid ID when picking up your device.<br><br>
                    Thank you for choosing our service!<br>
                    {location_name}"
                ]
            ];

            if (isset($templates[$type])) {
                $template = $templates[$type];

                // Replace placeholders with actual data
                $message = str_replace(
                    [
                        '{customer_name}',
                        '{ticket_id}',
                        '{status}',
                        '{estimated_completion}',
                        '{location_name}',
                        '{location_phone}',
                        '{location_address}',
                        '{device_type}',
                        '{serial_number}'
                    ],
                    [
                        $ticket['first_name'] . ' ' . $ticket['last_name'],
                        $ticketId,
                        $ticket['status'],
                        $ticket['estimated_completion'],
                        $ticket['location_name'],
                        $ticket['location_phone'],
                        $ticket['location_address'],
                        $ticket['device_type'],
                        $ticket['serial_number']
                    ],
                    $template['message']
                );

                if ($this->sendEmail($ticket['email'], $template['subject'], $message)) {
                    // Log the notification
                    $stmt = $this->db->prepare("
                        INSERT INTO ticket_updates
                        (ticket_id, user_id, update_type, content)
                        VALUES (?, ?, 'customer_notification', ?)
                    ");
                    $stmt->execute([
                        $ticketId,
                        $_SESSION['user_id'],
                        "Sent $type notification to customer"
                    ]);
                    return true;
                }
            }
            return false;
        } catch(Exception $e) {
            error_log("Customer notification error: " . $e->getMessage());
            return false;
        }
    }
}
