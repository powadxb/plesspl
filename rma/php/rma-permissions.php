<?php
/**
 * RMA Permission Helper Functions
 * Include this file in any RMA page to check permissions
 * 
 * Usage: require __DIR__.'/rma-permissions.php';
 */

/**
 * Check if user has a specific RMA permission
 * 
 * @param int $user_id User ID to check
 * @param string $permission Permission to check (rma_view, rma_manage, etc.)
 * @param object $DB Database connection object
 * @return bool True if user has permission, false otherwise
 */
function hasRMAPermission($user_id, $permission, $DB) {
    $result = $DB->query("
        SELECT has_access 
        FROM user_permissions 
        WHERE user_id = ? 
        AND page = ? 
        AND has_access = 1
        LIMIT 1
    ", [$user_id, $permission]);
    
    return !empty($result);
}

/**
 * Get all RMA permissions for a user
 * 
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return array Array of permission flags
 */
function getRMAPermissions($user_id, $DB) {
    $permissions = [
        'view' => false,
        'view_all' => false,
        'manage' => false,
        'view_supplier' => false,
        'view_financial' => false,
        'batches' => false,
        'batch_admin' => false
    ];
    
    $result = $DB->query("
        SELECT page 
        FROM user_permissions 
        WHERE user_id = ? 
        AND page LIKE 'RMA-%'
        AND has_access = 1
    ", [$user_id]);
    
    if (!empty($result)) {
        foreach ($result as $row) {
            switch ($row['page']) {
                case 'RMA-View':
                    $permissions['view'] = true;
                    break;
                case 'RMA-View All Locations':
                    $permissions['view_all'] = true;
                    break;
                case 'RMA-Manage':
                    $permissions['manage'] = true;
                    break;
                case 'RMA-View Supplier':
                    $permissions['view_supplier'] = true;
                    break;
                case 'RMA-View Financial':
                    $permissions['view_financial'] = true;
                    break;
                case 'RMA-Batch Management':
                    $permissions['batches'] = true;
                    break;
                case 'RMA-Batch Admin':
                    $permissions['batch_admin'] = true;
                    break;
            }
        }
    }
    
    return $permissions;
}

/**
 * Require specific RMA permission or die with error
 * 
 * @param string $permission Permission required
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @param string $error_message Custom error message (optional)
 */
function requireRMAPermission($permission, $user_id, $DB, $error_message = null) {
    if (!hasRMAPermission($user_id, $permission, $DB)) {
        if ($error_message === null) {
            $error_message = '<h1>Access Denied</h1><p>You do not have permission to access this feature.</p>';
        }
        die($error_message);
    }
}

/**
 * Check if user can view supplier information
 * 
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has RMA-View Supplier permission
 */
function canViewSupplierData($user_id, $DB) {
    return hasRMAPermission($user_id, 'RMA-View Supplier', $DB);
}

/**
 * Check if user can view costs and suppliers
 * 
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has RMA-View Financial permission
 */
function canViewFinancialData($user_id, $DB) {
    return hasRMAPermission($user_id, 'RMA-View Financial', $DB);
}

/**
 * Check if user can access batch management
 * 
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has RMA-Batch Management permission
 */
function canManageBatches($user_id, $DB) {
    return hasRMAPermission($user_id, 'RMA-Batch Management', $DB);
}

/**
 * Check if user can edit completed batches
 * 
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has RMA-Batch Admin permission
 */
function canEditCompletedBatches($user_id, $DB) {
    return hasRMAPermission($user_id, 'RMA-Batch Admin', $DB);
}

/**
 * Check if user can view all locations
 * 
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has RMA-View All Locations permission
 */
function canViewAllLocations($user_id, $DB) {
    return hasRMAPermission($user_id, 'RMA-View All Locations', $DB);
}
?>