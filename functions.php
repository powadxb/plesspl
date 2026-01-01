<?php

/**
 * Check if the user has access to a specific page
 * 
 * @param int $user_id The ID of the user
 * @param string $page The name of the page
 * @return bool True if the user has access, false otherwise
 */
function hasAccess($user_id, $page) {
    global $DB;

    $query = "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = ?";
    $result = $DB->query($query, [$user_id, $page]);

    // Check if the query returned a result
    if (!$result || count($result) == 0) {
        return false;
    }

    // Return the access status
    return $result[0]['has_access'] == 1;
}

/**
 * Log a message to a file for debugging purposes
 * 
 * @param string $message The message to log
 */
function logMessage($message) {
    $logFile = __DIR__ . '/debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - {$message}\n", FILE_APPEND);
}

/**
 * Get a user-friendly display name for a user
 * 
 * @param int $user_id The ID of the user
 * @return string The display name of the user
 */
function getDisplayName($user_id) {
    global $DB;

    $query = "SELECT username FROM users WHERE id = ?";
    $result = $DB->query($query, [$user_id]);

    if ($result && count($result) > 0) {
        return $result[0]['username'];
    }

    return "Unknown User";
}

/**
 * Sanitize input data to prevent SQL injection
 * 
 * @param string $data The data to sanitize
 * @return string The sanitized data
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

?>
