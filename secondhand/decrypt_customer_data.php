<?php
/**
 * DECRYPT CUSTOMER DATA MIGRATION
 * 
 * This script decrypts all encrypted customer data in trade_in_items table
 * and stores it as plain text.
 * 
 * Run once, then delete this file.
 */

require_once '../php/bootstrap.php';

echo "<pre>";
echo "==================================================\n";
echo "  DECRYPT CUSTOMER DATA MIGRATION\n";
echo "==================================================\n\n";

// Get all trade-ins
$trade_ins = $DB->query("SELECT id, customer_phone, customer_email, customer_address, customer_postcode FROM trade_in_items");

echo "Found " . count($trade_ins) . " trade-ins to check\n\n";

$decrypted_count = 0;
$already_plain_count = 0;
$error_count = 0;

foreach ($trade_ins as $ti) {
    $id = $ti['id'];
    $updated = false;
    $updates = [];
    $params = [];
    
    echo "Trade-in #$id: ";
    
    // Check and decrypt each field
    $fields = ['customer_phone', 'customer_email', 'customer_address', 'customer_postcode'];
    
    foreach ($fields as $field) {
        if (!empty($ti[$field])) {
            // Try to decrypt
            try {
                $decrypted = Encryption::decrypt($ti[$field]);
                
                // If decryption succeeded, the data was encrypted
                $updates[] = "$field = ?";
                $params[] = $decrypted;
                $updated = true;
                echo "✓";
                
            } catch (Exception $e) {
                // Decryption failed - data is probably already plain text
                echo ".";
            }
        } else {
            echo "-";
        }
    }
    
    // Update if we decrypted anything
    if ($updated) {
        $params[] = $id;
        $sql = "UPDATE trade_in_items SET " . implode(', ', $updates) . " WHERE id = ?";
        
        try {
            $DB->query($sql, $params);
            $decrypted_count++;
            echo " DECRYPTED\n";
        } catch (Exception $e) {
            $error_count++;
            echo " ERROR: " . $e->getMessage() . "\n";
        }
    } else {
        $already_plain_count++;
        echo " Already plain text\n";
    }
}

echo "\n==================================================\n";
echo "  SUMMARY\n";
echo "==================================================\n";
echo "Total trade-ins: " . count($trade_ins) . "\n";
echo "Decrypted: $decrypted_count\n";
echo "Already plain text: $already_plain_count\n";
echo "Errors: $error_count\n";
echo "\n";

if ($decrypted_count > 0) {
    echo "✅ SUCCESS! $decrypted_count trade-ins decrypted.\n";
    echo "Customer data is now stored as plain text.\n";
} else {
    echo "ℹ️  All data was already plain text.\n";
}

echo "\n==================================================\n";
echo "</pre>";
?>
