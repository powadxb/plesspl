<?php
// daily.php
require_once 'config.php';
require_once 'auth.php';

// Require login for all users
requireLogin();

// Get user's branch if they're not admin/manager
$userBranch = $_SESSION['role'] === 'staff' ? $_SESSION['branch_id'] : null;
$todayOnly = $_SESSION['role'] === 'staff';

// Function to get existing entry for a date
function getExistingEntry($conn, $branchId, $date) {
    $sql = "SELECT dt.*, u.username as entered_by 
            FROM daily_takings dt
            JOIN users u ON dt.user_id = u.id
            WHERE dt.branch_id = ? AND dt.entry_date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$branchId, $date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to record amendment
function recordAmendment($conn, $dailyTakingId, $userId, $fieldName, $oldValue, $newValue, $notes) {
    $sql = "INSERT INTO daily_takings_amendments 
            (daily_taking_id, user_id, field_name, old_value, new_value, notes) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$dailyTakingId, $userId, $fieldName, $oldValue, $newValue, $notes]);
}

function updateDailyBalance($conn, $branchId, $date) {
    try {
        // Calculate totals for the day
        $sql = "SELECT 
                    COALESCE(SUM(dt.cash_taken), 0) as cash_in,
                    COALESCE(
                        (SELECT SUM(amount) 
                         FROM cash_movements 
                         WHERE branch_id = ? 
                         AND DATE(movement_date) = ?
                         AND type IN ('banking', 'expense', 'wages', 'purchase')
                        ), 0
                    ) as cash_out
                FROM daily_takings dt
                WHERE dt.branch_id = ? AND dt.entry_date = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$branchId, $date, $branchId, $date]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get previous day's closing balance
        $prevSql = "SELECT closing_balance 
                    FROM daily_balances 
                    WHERE branch_id = ? AND date < ?
                    ORDER BY date DESC LIMIT 1";
        $stmt = $conn->prepare($prevSql);
        $stmt->execute([$branchId, $date]);
        $openingBalance = $stmt->fetchColumn();
        if ($openingBalance === false) $openingBalance = 0;
        
        $closingBalance = $openingBalance + $totals['cash_in'] - $totals['cash_out'];
        
        // Update or insert balance record
        $upsertSql = "INSERT INTO daily_balances 
                        (branch_id, date, opening_balance, cash_in, cash_out, closing_balance)
                      VALUES (?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE 
                        opening_balance = VALUES(opening_balance),
                        cash_in = VALUES(cash_in),
                        cash_out = VALUES(cash_out),
                        closing_balance = VALUES(closing_balance)";
        
        $stmt = $conn->prepare($upsertSql);
        $stmt->execute([
            $branchId, 
            $date, 
            $openingBalance,
            $totals['cash_in'],
            $totals['cash_out'],
            $closingBalance
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=cashtracker", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get branches based on user role
    if ($userBranch) {
        $branches = $conn->query("SELECT id, name FROM branches WHERE id = $userBranch")
                        ->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $branches = $conn->query("SELECT id, name FROM branches ORDER BY name")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get selected date (today for staff, can be changed for managers/admin)
    $selectedDate = $todayOnly ? date('Y-m-d') : 
                   (isset($_POST['entry_date']) ? $_POST['entry_date'] : date('Y-m-d'));
    
    // Get selected branch
    $selectedBranch = $userBranch ?? ($_POST['branch_id'] ?? $branches[0]['id']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Handle form submission
        if (isset($_POST['existing_id'])) {
            // Record amendments if values have changed
            $fields = ['cash_taken', 'card_payments', 'paypal_amount', 'bank_transfers'];
            $changes = false;
            
            foreach ($fields as $field) {
                if ($_POST[$field] != $_POST['old_' . $field]) {
                    recordAmendment(
                        $conn,
                        $_POST['existing_id'],
                        $_SESSION['user_id'],
                        $field,
                        $_POST['old_' . $field],
                        $_POST[$field],
                        "Value updated"
                    );
                    $changes = true;
                }
            }
            
            if ($changes) {
                // Update existing entry
                $sql = "UPDATE daily_takings 
                        SET cash_taken = ?, card_payments = ?, 
                            paypal_amount = ?, bank_transfers = ?, 
                            notes = CONCAT(COALESCE(notes, ''), '\nAmended by " . $_SESSION['username'] . " on " . date('Y-m-d H:i:s') . "')
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $_POST['cash_taken'],
                    $_POST['card_payments'],
                    $_POST['paypal_amount'],
                    $_POST['bank_transfers'],
                    $_POST['existing_id']
                ]);
                
                updateDailyBalance($conn, $selectedBranch, $selectedDate);
                $message = "Daily takings updated successfully!";
            }
        } else {
            // Insert new entry
            $sql = "INSERT INTO daily_takings 
                    (branch_id, entry_date, cash_taken, card_payments, 
                     paypal_amount, bank_transfers, notes, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $selectedBranch,
                $selectedDate,
                $_POST['cash_taken'],
                $_POST['card_payments'],
                $_POST['paypal_amount'],
                $_POST['bank_transfers'],
                $_POST['notes'],
                $_SESSION['user_id']
            ]);
            
            updateDailyBalance($conn, $selectedBranch, $selectedDate);
            $message = "Daily takings recorded successfully!";
        }
    }
    
    // Get existing entry AFTER any updates
    $existingEntry = getExistingEntry($conn, $selectedBranch, $selectedDate);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Takings Entry</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            font-size: 14px;
        }
        .container { 
            max-width: 400px; 
            margin: 0 auto; 
        }
        h1, h2 { 
            font-size: 20px;
            margin-bottom: 15px;
        }
        .form-group { 
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        label { 
            width: 120px;
            padding-right: 10px;
        }
        input[type="number"], input[type="date"], select { 
            width: 150px;
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        textarea {
            width: 150px;
            height: 50px;
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        button { 
            background-color: #4CAF50;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin-top: 10px;
        }
        .message { 
            color: green; 
            margin-bottom: 10px;
            font-size: 14px;
        }
        .error { 
            color: red; 
            margin-bottom: 10px;
            font-size: 14px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #666;
            text-decoration: none;
        }
        .existing-entry {
            margin: 20px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Home</a>
        <h1>Daily Takings Entry</h1>
        
        <?php if (isset($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($existingEntry): ?>
            <div class="existing-entry">
                <h2>Existing Entry for <?php echo date('d/m/Y', strtotime($selectedDate)); ?></h2>
                <p>Entered by: <?php echo htmlspecialchars($existingEntry['entered_by']); ?></p>
                <p>Cash: £<?php echo number_format($existingEntry['cash_taken'], 2); ?></p>
                <p>Cards: £<?php echo number_format($existingEntry['card_payments'], 2); ?></p>
                <p>PayPal: £<?php echo number_format($existingEntry['paypal_amount'], 2); ?></p>
                <p>Bank Transfers: £<?php echo number_format($existingEntry['bank_transfers'], 2); ?></p>
                <?php if ($existingEntry['notes']): ?>
                    <p>Notes: <?php echo nl2br(htmlspecialchars($existingEntry['notes'])); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php if ($existingEntry): ?>
                <input type="hidden" name="existing_id" value="<?php echo $existingEntry['id']; ?>">
                <input type="hidden" name="old_cash_taken" value="<?php echo $existingEntry['cash_taken']; ?>">
                <input type="hidden" name="old_card_payments" value="<?php echo $existingEntry['card_payments']; ?>">
                <input type="hidden" name="old_paypal_amount" value="<?php echo $existingEntry['paypal_amount']; ?>">
                <input type="hidden" name="old_bank_transfers" value="<?php echo $existingEntry['bank_transfers']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="branch_id">Branch:</label>
                <select name="branch_id" id="branch_id" required <?php echo $userBranch ? 'disabled' : ''; ?>>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo htmlspecialchars($branch['id']); ?>"
                                <?php echo $branch['id'] == $selectedBranch ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!$todayOnly): ?>
            <div class="form-group">
                <label for="entry_date">Date:</label>
                <input type="date" name="entry_date" id="entry_date" required 
                       value="<?php echo $selectedDate; ?>">
            </div>
            <?php else: ?>
            <input type="hidden" name="entry_date" value="<?php echo date('Y-m-d'); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="cash_taken">Cash Taken:</label>
                <input type="number" name="cash_taken" id="cash_taken" step="0.01" required
                       value="<?php echo $existingEntry ? $existingEntry['cash_taken'] : ''; ?>">
            </div>

            <div class="form-group">
                <label for="card_payments">Card Payments:</label>
                <input type="number" name="card_payments" id="card_payments" step="0.01" required
                       value="<?php echo $existingEntry ? $existingEntry['card_payments'] : ''; ?>">
            </div>

            <div class="form-group">
                <label for="paypal_amount">PayPal:</label>
                <input type="number" name="paypal_amount" id="paypal_amount" step="0.01" 
                       value="<?php echo $existingEntry ? $existingEntry['paypal_amount'] : '0'; ?>">
            </div>

            <div class="form-group">
                <label for="bank_transfers">Bank Transfers:</label>
                <input type="number" name="bank_transfers" id="bank_transfers" step="0.01" 
                       value="<?php echo $existingEntry ? $existingEntry['bank_transfers'] : '0'; ?>">
            </div>

            <div class="form-group">
                <label for="notes">Notes:</label>
                <textarea name="notes" id="notes"></textarea>
            </div>

            <button type="submit"><?php echo $existingEntry ? 'Update' : 'Record'; ?> Takings</button>
        </form>
    </div>
</body>
</html>