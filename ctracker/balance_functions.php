<?php
// balance_functions.php

function updateDailyBalance($conn, $branchId, $date) {
    try {
        // Get all transactions for this day
        $sql = "SELECT 
                    COALESCE(SUM(cash_taken), 0) as cash_in,
                    0 as cash_out
                FROM daily_takings 
                WHERE branch_id = ? AND entry_date = ?
                
                UNION ALL
                
                SELECT 
                    COALESCE(SUM(CASE WHEN type = 'float_adjustment' AND amount > 0 
                                     THEN amount ELSE 0 END), 0) as cash_in,
                    COALESCE(SUM(CASE WHEN type IN ('banking', 'expense', 'wages', 'purchase') 
                                      OR (type = 'float_adjustment' AND amount < 0)
                                     THEN amount ELSE 0 END), 0) as cash_out
                FROM cash_movements
                WHERE branch_id = ? AND DATE(movement_date) = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$branchId, $date, $branchId, $date]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalCashIn = array_sum(array_column($results, 'cash_in'));
        $totalCashOut = abs(array_sum(array_column($results, 'cash_out')));

        // Get previous day's closing balance
        $prevSql = "SELECT closing_balance 
                    FROM daily_balances 
                    WHERE branch_id = ? AND date < ?
                    ORDER BY date DESC LIMIT 1";
        $stmt = $conn->prepare($prevSql);
        $stmt->execute([$branchId, $date]);
        $openingBalance = $stmt->fetchColumn() ?: 0;

        // Calculate closing balance
        $closingBalance = $openingBalance + $totalCashIn - $totalCashOut;

        // Update or insert daily balance
        $conn->beginTransaction();

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
            $totalCashIn,
            $totalCashOut,
            $closingBalance
        ]);

        // Update all future days' balances
        $updateFutureSql = "UPDATE daily_balances 
                           SET opening_balance = (
                               SELECT closing_balance 
                               FROM (SELECT * FROM daily_balances) as prev 
                               WHERE prev.branch_id = daily_balances.branch_id 
                               AND prev.date = DATE_SUB(daily_balances.date, INTERVAL 1 DAY)
                           ),
                           closing_balance = opening_balance + cash_in - cash_out
                           WHERE branch_id = ? AND date > ?";
        
        $stmt = $conn->prepare($updateFutureSql);
        $stmt->execute([$branchId, $date]);

        $conn->commit();
        return true;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
?>