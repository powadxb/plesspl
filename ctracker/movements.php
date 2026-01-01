<?php
// movements.php
require_once 'config.php';
require_once 'auth.php';

// Require manager or admin role
requireRole(['manager', 'admin']);

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=cashtracker", DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Handle branch transfer
        if ($_POST['action'] === 'transfer') {
            $conn->beginTransaction();
            
            try {
                // Record the transfer
                $sql = "INSERT INTO branch_transfers 
                        (from_branch_id, to_branch_id, amount, user_id, notes) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $_POST['from_branch'],
                    $_POST['to_branch'],
                    $_POST['amount'],
                    $_SESSION['user_id'],
                    $_POST['notes']
                ]);
                
                // Add movement record for sending branch (negative amount)
                $sql = "INSERT INTO cash_movements 
                        (branch_id, movement_date, type, amount, notes) 
                        VALUES (?, NOW(), 'transfer_out', ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $_POST['from_branch'],
                    $_POST['amount'],
                    "Transfer to " . $_POST['to_branch_name']
                ]);
                
                // Add movement record for receiving branch (positive amount)
                $sql = "INSERT INTO cash_movements 
                        (branch_id, movement_date, type, amount, notes) 
                        VALUES (?, NOW(), 'transfer_in', ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $_POST['to_branch'],
                    $_POST['amount'],
                    "Transfer from " . $_POST['from_branch_name']
                ]);
                
                // Update balances for both branches
                updateDailyBalance($conn, $_POST['from_branch'], date('Y-m-d'));
                updateDailyBalance($conn, $_POST['to_branch'], date('Y-m-d'));
                
                $conn->commit();
                $message = "Branch transfer recorded successfully!";
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        } else {
            // Regular cash movement
            $sql = "INSERT INTO cash_movements (branch_id, movement_date, type, amount, notes) 
                    VALUES (?, NOW(), ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([
                $_POST['branch_id'],
                $_POST['type'],
                $_POST['amount'],
                $_POST['notes']
            ])) {
                updateDailyBalance($conn, $_POST['branch_id'], date('Y-m-d'));
                $message = "Cash movement recorded successfully!";
            }
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=cashtracker", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all branches
    $branches = $conn->query("SELECT id, name, is_main FROM branches ORDER BY name")
                    ->fetchAll(PDO::FETCH_ASSOC);
    
    // Get main branch
    $mainBranch = array_filter($branches, function($branch) {
        return $branch['is_main'] == 1;
    });
    $mainBranch = reset($mainBranch); // Get first (and should be only) main branch
    
} catch(PDOException $e) {
    $error = "Error loading branches: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Movements & Transfers</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            font-size: 14px;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
        }
        .forms-container {
            display: flex;
            gap: 40px;
            margin-top: 20px;
        }
        .form-section {
            flex: 1;
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
        input[type="number"], select, textarea { 
            width: 150px;
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        textarea {
            height: 50px;
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
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Home</a>
        <h1>Cash Movements & Transfers</h1>
        
        <?php if (isset($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="forms-container">
            <!-- Regular Cash Movement Form -->
            <div class="form-section">
                <h2>Record Cash Movement</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="movement">
                    
                    <div class="form-group">
                        <label for="branch_id">Branch:</label>
                        <select name="branch_id" id="branch_id" required>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch['id']); ?>">
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="type">Type:</label>
                        <select name="type" id="type" required>
                            <option value="banking">Banking</option>
                            <option value="purchase">Purchase</option>
                            <option value="expense">Expense</option>
                            <option value="wages">Wages</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount:</label>
                        <input type="number" name="amount" id="amount" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" placeholder="What was this payment for?"></textarea>
                    </div>

                    <button type="submit">Record Movement</button>
                </form>
            </div>

            <!-- Branch Transfer Form -->
            <div class="form-section">
                <h2>Branch Transfer</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="transfer">
                    
                    <div class="form-group">
                        <label for="from_branch">From Branch:</label>
                        <select name="from_branch" id="from_branch" required 
                                onchange="document.getElementById('from_branch_name').value = 
                                         this.options[this.selectedIndex].text">
                            <?php foreach ($branches as $branch): ?>
                                <?php if (!$branch['is_main']): ?>
                                    <option value="<?php echo htmlspecialchars($branch['id']); ?>">
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="from_branch_name" id="from_branch_name">
                    </div>

                    <div class="form-group">
                        <label for="to_branch">To Branch:</label>
                        <select name="to_branch" id="to_branch" required 
                                onchange="document.getElementById('to_branch_name').value = 
                                         this.options[this.selectedIndex].text">
                            <?php if ($mainBranch): ?>
                                <option value="<?php echo htmlspecialchars($mainBranch['id']); ?>">
                                    <?php echo htmlspecialchars($mainBranch['name']); ?> (Main)
                                </option>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" name="to_branch_name" id="to_branch_name">
                    </div>

                    <div class="form-group">
                        <label for="transfer_amount">Amount:</label>
                        <input type="number" name="amount" id="transfer_amount" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="transfer_notes">Notes:</label>
                        <textarea name="notes" id="transfer_notes" 
                                  placeholder="Any notes about this transfer"></textarea>
                    </div>

                    <button type="submit">Record Transfer</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>