<?php
// ticket.php
class TicketManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

private function generateTicketNumber() {
    do {
        // Get year's last two digits (23 for 2023, 24 for 2024, etc.)
        $year = date('y');
        
        // Convert to hex and ensure one digit
        $month = strtoupper(dechex(date('n'))); // 1-C for months
        $day = strtoupper(dechex(date('d')));   // 1-1F for days
        
        // Pad single digits with 0
        $month = str_pad($month, 1, '0', STR_PAD_LEFT);
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        
        // Generate 2 random hex digits (0-255 possibilities)
        $random = strtoupper(bin2hex(random_bytes(2))); // Get 4 digits, use 2
        $random = substr($random, 0, 2);
        
        // Combine to make 7 digits total: YYMDDXX
        $ticketNumber = $year . $month . $day . $random;
        
        // Check if exists
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM repair_tickets 
            WHERE ticket_number = ?
        ");
        $stmt->execute([$ticketNumber]);
        
    } while ($stmt->fetchColumn() > 0);
    
    return $ticketNumber;
}
    public function createTicket($customerData, $ticketData) {
        try {
            $this->db->beginTransaction();

            // Create or update customer
            $stmt = $this->db->prepare("
                INSERT INTO customers (first_name, last_name, email, phone)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $customerData['first_name'],
                $customerData['last_name'],
                $customerData['email'],
                $customerData['phone']
            ]);
            
            $customerId = $this->db->lastInsertId();

            // Generate unique ticket number
            $ticketNumber = $this->generateTicketNumber();

            // Create ticket
            $stmt = $this->db->prepare("
                INSERT INTO repair_tickets 
                (customer_id, ticket_number, device_type, serial_number, issue_description, 
                repair_cost, status, location_id)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $customerId,
                $ticketNumber,
                $ticketData['device_type'],
                $ticketData['serial_number'],
                $ticketData['issue_description'],
                $ticketData['repair_cost'] ?? null,
                $ticketData['location_id']
            ]);
            
            $ticketId = $this->db->lastInsertId();
            
            $this->db->commit();
            return [
                'id' => $ticketId,
                'ticket_number' => $ticketNumber
            ];
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Ticket creation error: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateTicketStatus($ticketId, $status, $userId = null) {
        try {
            $this->db->beginTransaction();

            // Update ticket status
            $stmt = $this->db->prepare("
                UPDATE repair_tickets 
                SET status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$status, $ticketId]);

            // Log the update if user ID provided
            if ($userId) {
                $stmt = $this->db->prepare("
                    INSERT INTO ticket_updates 
                    (ticket_id, user_id, update_type, content)
                    VALUES (?, ?, 'status_change', ?)
                ");
                $stmt->execute([
                    $ticketId,
                    $userId,
                    "Status updated to: $status"
                ]);
            }

            $this->db->commit();
            return true;
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Status update error: " . $e->getMessage());
            return false;
        }
    }

    public function updateRepairCost($ticketId, $cost, $userId = null) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE repair_tickets 
                SET repair_cost = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$cost, $ticketId]);

            if ($userId) {
                $stmt = $this->db->prepare("
                    INSERT INTO ticket_updates 
                    (ticket_id, user_id, update_type, content)
                    VALUES (?, ?, 'cost_update', ?)
                ");
                $stmt->execute([
                    $ticketId,
                    $userId,
                    "Repair cost updated to: £" . number_format($cost, 2)
                ]);
            }

            $this->db->commit();
            return true;
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Cost update error: " . $e->getMessage());
            return false;
        }
    }

    public function addComment($ticketId, $userId, $content) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ticket_updates 
                (ticket_id, user_id, update_type, content)
                VALUES (?, ?, 'comment', ?)
            ");
            return $stmt->execute([$ticketId, $userId, $content]);
        } catch(PDOException $e) {
            error_log("Comment addition error: " . $e->getMessage());
            return false;
        }
    }

    public function uploadPhoto($ticketId, $photoFile, $userId) {
        try {
            if ($photoFile['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed');
            }

            $fileInfo = pathinfo($photoFile['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedTypes = ['jpg', 'jpeg', 'png'];

            if (!in_array($extension, $allowedTypes)) {
                throw new Exception('Invalid file type');
            }

            $newFileName = uniqid() . '.' . $extension;
            $uploadPath = UPLOAD_DIR . $newFileName;

            if (!move_uploaded_file($photoFile['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to move uploaded file');
            }

            $stmt = $this->db->prepare("
                INSERT INTO ticket_photos 
                (ticket_id, photo_path, uploaded_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$ticketId, $newFileName, $userId]);

            return true;
        } catch(Exception $e) {
            error_log("Photo upload error: " . $e->getMessage());
            return false;
        }
    }

    public function getTicketsByLocation($locationId, $status = null) {
        try {
            $sql = "
                SELECT 
                    t.*,
                    c.first_name,
                    c.last_name
                FROM repair_tickets t
                JOIN customers c ON t.customer_id = c.id
                WHERE t.location_id = ?
            ";
            
            if ($status) {
                $sql .= " AND t.status = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$locationId, $status]);
            } else {
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$locationId]);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Ticket listing error: " . $e->getMessage());
            return [];
        }
    }

    public function getTicketDetails($ticketId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    t.*,
                    c.first_name,
                    c.last_name,
                    c.email,
                    c.phone,
                    l.name as location_name
                FROM repair_tickets t
                JOIN customers c ON t.customer_id = c.id
                JOIN locations l ON t.location_id = l.id
                WHERE t.id = ?
            ");
            $stmt->execute([$ticketId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Ticket details error: " . $e->getMessage());
            return false;
        }
    }

    public function getTicketUpdates($ticketId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    tu.*,
                    u.username
                FROM ticket_updates tu
                JOIN users u ON tu.user_id = u.id
                WHERE tu.ticket_id = ?
                ORDER BY tu.created_at DESC
            ");
            $stmt->execute([$ticketId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Updates retrieval error: " . $e->getMessage());
            return [];
        }
    }

    public function getTicketPhotos($ticketId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * 
                FROM ticket_photos
                WHERE ticket_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$ticketId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Photos retrieval error: " . $e->getMessage());
            return [];
        }
    }

    public function searchTickets($searchTerm, $locationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    t.*,
                    c.first_name,
                    c.last_name
                FROM repair_tickets t
                JOIN customers c ON t.customer_id = c.id
                WHERE t.location_id = ?
                AND (
                    t.ticket_number LIKE ? OR
                    c.first_name LIKE ? OR
                    c.last_name LIKE ? OR
                    t.device_type LIKE ? OR
                    t.serial_number LIKE ?
                )
                ORDER BY t.created_at DESC
            ");
            
            $searchPattern = "%$searchTerm%";
            $stmt->execute([
                $locationId,
                $searchPattern,
                $searchPattern,
                $searchPattern,
                $searchPattern,
                $searchPattern
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            return [];
        }
    }
}
?>