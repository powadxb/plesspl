<?php
// report.php
require_once 'config.php';
require_once 'auth.php';

// Ensure user has permission
requireRole(['manager', 'admin']);

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=cashtracker", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get branches
    $branches = $conn->query("SELECT id, name, is_main FROM branches ORDER BY name")
                    ->fetchAll(PDO::FETCH_ASSOC);
    
    // Get selected branch (default to first branch)
    $selectedBranch = isset($_GET['branch_id']) ? $_GET['branch_id'] : $branches[0]['id'];
    
    // Get date range (default to last 30 days if not specified)
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

    // Get the data including transfers and amendments
    $sql = "SELECT 
                db.date,
                db.opening_balance,
                db.cash_in,
                db.cash_out,
                db.closing_balance,
                dt.id as taking_id,
                dt.cash_taken,
                dt.card_payments,
                dt.paypal_amount,
                dt.bank_transfers,
                dt.notes as daily_notes,
                u.username as entered_by,
                GROUP_CONCAT(
                    DISTINCT 
                    CASE 
                        WHEN cm.type IN ('transfer_in', 'transfer_out') THEN
                            CONCAT(
                                cm.type, ': £', 
                                FORMAT(cm.amount, 2),
                                ' - ', 
                                cm.notes
                            )
                        ELSE
                            CONCAT(
                                cm.type, ': £', 
                                FORMAT(cm.amount, 2),
                                CASE WHEN cm.notes != '' 
                                     THEN CONCAT(' (', cm.notes, ')')
                                     ELSE ''
                                END
                            )
                    END
                    ORDER BY cm.movement_date
                    SEPARATOR '\n'
                ) as movements
            FROM daily_balances db
            LEFT JOIN daily_takings dt ON db.branch_id = dt.branch_id 
                AND db.date = dt.entry_date
            LEFT JOIN users u ON dt.user_id = u.id
            LEFT JOIN cash_movements cm ON db.branch_id = cm.branch_id 
                AND db.date = DATE(cm.movement_date)
            WHERE db.branch_id = :branch
            AND db.date BETWEEN :start_date AND :end_date
            GROUP BY db.date, db.opening_balance, db.cash_in, db.cash_out, 
                     db.closing_balance, dt.id, dt.cash_taken, dt.card_payments,
                     dt.paypal_amount, dt.bank_transfers, dt.notes, u.username
            ORDER BY db.date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'branch' => $selectedBranch,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get amendments for each daily taking
    $amendments = [];
    if (!empty($records)) {
        $takingIds = array_filter(array_column($records, 'taking_id'));
        if (!empty($takingIds)) {
            $amendSql = "SELECT 
                            dta.*,
                            u.username as amended_by
                        FROM daily_takings_amendments dta
                        JOIN users u ON dta.user_id = u.id
                        WHERE dta.daily_taking_id IN (" . implode(',', $takingIds) . ")
                        ORDER BY dta.amendment_date";
            $amendments = $conn->query($amendSql)->fetchAll(PDO::FETCH_GROUP|PDO_FETCH_ASSOC);
        }
    }
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Report</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            font-size: 14px;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        h1, h2 { 
            font-size: 20px;
            margin-bottom: 15px;
        }
        .filters {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 3px;
        }
        .filters label {
            margin-right: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            white-space: nowrap;
        }
        .positive { color: green; }
        .negative { color: red; }
        .balance { 
            font-weight: bold;
            background-color: #f8f8f8;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #666;
            text-decoration: none;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
        }
        .summary {
            margin: 20px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 3px;
        }
        .amendments {
            margin-top: 5px;
            padding: 5px;
            background-color: #fff3cd;
            border-radius: 3px;
            font-size: 12px;
        }
        .transfer {
            background-color: #e8f5e9;
            padding: 2px 4px;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Home</a>
        <h1>Cash Report</h1>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php else: ?>

            <div class="filters">
                <form method="GET">
                    <label>Branch:</label>
                    <select name="branch_id" onchange="this.form.submit()">
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" 
                                    <?php echo $branch['id'] == $selectedBranch ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                                <?php echo $branch['is_main'] ? ' (Main)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>From:</label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>">

                    <label>To:</label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>">

                    <button type="submit">Update</button>
                </form>
            </div>

            <?php if (!empty($records)): ?>
                <div class="summary">
                    <strong>Period Summary:</strong><br>
                    Opening Balance: £<?php echo number_format(end($records)['opening_balance'], 2); ?><br>
                    Closing Balance: £<?php echo number_format($records[0]['closing_balance'], 2); ?><br>
                    Total Cash In: £<?php echo number_format(array_sum(array_column($records, 'cash_in')), 2); ?><br>
                    Total Cash Out: £<?php echo number_format(array_sum(array_column($records, 'cash_out')), 2); ?>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Opening</th>
                            <th>Cash In</th>
                            <th>Cash Out</th>
                            <th>Closing</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($record['date'])); ?></td>
                                <td class="balance">£<?php echo number_format($record['opening_balance'], 2); ?></td>
                                <td class="positive">£<?php echo number_format($record['cash_in'], 2); ?></td>
                                <td class="negative">£<?php echo number_format($record['cash_out'], 2); ?></td>
                                <td class="balance">£<?php echo number_format($record['closing_balance'], 2); ?></td>
                                <td>
                                    <?php if ($record['cash_taken']): ?>
                                        <strong>Daily Takings</strong> 
                                        (entered by <?php echo htmlspecialchars($record['entered_by']); ?>):<br>
                                        Cash: £<?php echo number_format($record['cash_taken'], 2); ?><br>
                                        Cards: £<?php echo number_format($record['card_payments'], 2); ?><br>
                                        PayPal: £<?php echo number_format($record['paypal_amount'], 2); ?><br>
                                        Bank Transfers: £<?php echo number_format($record['bank_transfers'], 2); ?><br>
                                        <?php if ($record['daily_notes']): ?>
                                            Notes: <?php echo htmlspecialchars($record['daily_notes']); ?><br>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($amendments[$record['taking_id']])): ?>
                                            <div class="amendments">
                                                <strong>Amendments:</strong><br>
                                                <?php foreach ($amendments[$record['taking_id']] as $amendment): ?>
                                                    <?php echo date('d/m/Y H:i', strtotime($amendment['amendment_date'])); ?> - 
                                                    <?php echo htmlspecialchars($amendment['amended_by']); ?> changed 
                                                    <?php echo htmlspecialchars($amendment['field_name']); ?> from 
                                                    £<?php echo number_format($amendment['old_value'], 2); ?> to 
                                                    £<?php echo number_format($amendment['new_value'], 2); ?><br>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($record['movements']): ?>
                                        <strong>Movements:</strong><br>
                                        <pre><?php 
                                            $movements = explode("\n", $record['movements']);
                                            foreach ($movements as $movement) {
                                                if (strpos($movement, 'transfer_') === 0) {
                                                    echo '<span class="transfer">' . 
                                                         htmlspecialchars(str_replace(
                                                             ['transfer_in', 'transfer_out'],
                                                             ['Transfer In', 'Transfer Out'],
                                                             $movement
                                                         )) . 
                                                         '</span><br>';
                                                } else {
                                                    echo htmlspecialchars($movement) . '<br>';
                                                }
                                            }
                                        ?></pre>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No records found for the selected period.</p>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>