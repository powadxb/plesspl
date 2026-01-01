<?php
/**
 * Shared permission checking functions
 * Include this file in scripts that need to check user permissions
 */

if (!function_exists('hasPermission')) {
    /**
     * Check if a user has a specific permission
     * 
     * @param int $user_id The user's ID
     * @param string $permission_name The permission to check (e.g., 'edit_stock_status')
     * @param object $DB The database connection object
     * @return bool True if user has permission, false otherwise
     */
    function hasPermission($user_id, $permission_name, $DB) {
        try {
            $result = $DB->query(
                "SELECT COUNT(*) as count FROM user_permissions WHERE user_id = ? AND page = ? AND has_access = 1", 
                [$user_id, $permission_name]
            );
            return isset($result[0]['count']) && $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getUserPermissions')) {
    /**
     * Get all permissions for a user
     * 
     * @param int $user_id The user's ID
     * @param object $DB The database connection object
     * @return array Array of permission names the user has access to
     */
    function getUserPermissions($user_id, $DB) {
        try {
            $result = $DB->query(
                "SELECT page FROM user_permissions WHERE user_id = ? AND has_access = 1", 
                [$user_id]
            );
            return array_column($result, 'page');
        } catch (Exception $e) {
            error_log("Get user permissions error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('checkPermissionOrDie')) {
    /**
     * Check permission and die with error message if user doesn't have access
     * 
     * @param int $user_id The user's ID
     * @param string $permission_name The permission to check
     * @param object $DB The database connection object
     * @param string $error_message Custom error message (optional)
     */
    function checkPermissionOrDie($user_id, $permission_name, $DB, $error_message = null) {
        if (!hasPermission($user_id, $permission_name, $DB)) {
            $message = $error_message ?: "Insufficient permissions for: $permission_name";
            die($message);
        }
    }
}
?>