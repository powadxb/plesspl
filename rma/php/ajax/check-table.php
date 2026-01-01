<?php
/**
 * SIMPLE TABLE CHECK
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__.'/../../../php/bootstrap.php';

echo "<h2>Database Table Check</h2>";

try {
    // Check if table exists
    $tableCheck = $DB->query("SHOW TABLES LIKE 'rma_supplier_batches'");
    
    if (empty($tableCheck)) {
        echo "<p style='color: red; font-size: 20px;'><strong>❌ TABLE DOES NOT EXIST!</strong></p>";
        echo "<p>You need to run the SQL file: <strong>rma_phase2_table.sql</strong></p>";
        echo "<p>Steps:</p>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin</li>";
        echo "<li>Select database: <strong>plesspl</strong></li>";
        echo "<li>Click 'Import' tab</li>";
        echo "<li>Upload rma_phase2_table.sql</li>";
        echo "<li>Click 'Go'</li>";
        echo "</ol>";
    } else {
        echo "<p style='color: green; font-size: 20px;'><strong>✅ TABLE EXISTS!</strong></p>";
        
        // Count rows
        $count = $DB->query("SELECT COUNT(*) as cnt FROM rma_supplier_batches");
        echo "<p>Current batches in table: <strong>" . $count[0]['cnt'] . "</strong></p>";
        
        // Check structure
        echo "<h3>Table Structure:</h3>";
        $structure = $DB->query("DESCRIBE rma_supplier_batches");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach($structure as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p style='color: green;'><strong>Table is ready to use! ✅</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> " . $e->getMessage() . "</p>";
}