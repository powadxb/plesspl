<?php
session_start();
require '../php/bootstrap.php';

if (!isset($_SESSION['dins_user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

try {
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        exit(json_encode(['success' => false, 'message' => 'Invalid ID']));
    }

    // Check if item exists and user has permission
    $item = $DB->query("
        SELECT created_by 
        FROM trade_in_items 
        WHERE id = ?
    ", [$id])[0] ?? null;

    if (!$item) {
        exit(json_encode(['success' => false, 'message' => 'Item not found']));
    }

    // Start transaction
    $DB->beginTransaction();

    // Delete associated files first
    $DB->query("DELETE FROM trade_in_files WHERE trade_in_id = ?", [$id]);
    
    // Delete the trade-in item
    $DB->query("DELETE FROM trade_in_items WHERE id = ?", [$id]);

    // Commit transaction
    $DB->commit();
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }
    error_log("Delete trade-in error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}