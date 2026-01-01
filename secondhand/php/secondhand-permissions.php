<?php
/**
 * Second-Hand Inventory Permission Helper Functions
 * Include this file in any second-hand page to check permissions
 *
 * Usage: require __DIR__.'/secondhand-permissions.php';
 */

/**
 * Check if user has a specific second-hand permission
 *
 * @param int $user_id User ID to check
 * @param string $permission Permission to check (secondhand_view, secondhand_manage, etc.)
 * @param object $DB Database connection object
 * @return bool True if user has permission, false otherwise
 */
function hasSecondHandPermission($user_id, $permission, $DB) {
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
 * Get all second-hand permissions for a user
 *
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return array Array of permission flags
 */
function getSecondHandPermissions($user_id, $DB) {
    $permissions = [
        'view' => false,
        'view_all_locations' => false,
        'manage' => false,
        'view_financial' => false,
        'view_customer_data' => false,
        'view_documents' => false,
        'import_trade_ins' => false,
        'manage_compliance' => false
    ];

    $result = $DB->query("
        SELECT page
        FROM user_permissions
        WHERE user_id = ?
        AND page LIKE 'SecondHand-%'
        AND has_access = 1
    ", [$user_id]);

    if (!empty($result)) {
        foreach ($result as $row) {
            switch ($row['page']) {
                case 'SecondHand-View':
                    $permissions['view'] = true;
                    break;
                case 'SecondHand-View All Locations':
                    $permissions['view_all_locations'] = true;
                    break;
                case 'SecondHand-Manage':
                    $permissions['manage'] = true;
                    break;
                case 'SecondHand-View Financial':
                    $permissions['view_financial'] = true;
                    break;
                case 'SecondHand-View Customer Data':
                    $permissions['view_customer_data'] = true;
                    break;
                case 'SecondHand-View Documents':
                    $permissions['view_documents'] = true;
                    break;
                case 'SecondHand-Import Trade Ins':
                    $permissions['import_trade_ins'] = true;
                    break;
                case 'SecondHand-Manage Compliance':
                    $permissions['manage_compliance'] = true;
                    break;
            }
        }
    }

    return $permissions;
}

/**
 * Require specific second-hand permission or die with error
 *
 * @param string $permission Permission required
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @param string $error_message Custom error message (optional)
 */
function requireSecondHandPermission($permission, $user_id, $DB, $error_message = null) {
    if (!hasSecondHandPermission($user_id, $permission, $DB)) {
        if ($error_message === null) {
            $error_message = '<h1>Access Denied</h1><p>You do not have permission to access this feature.</p>';
        }
        die($error_message);
    }
}

/**
 * Check if user can view financial information
 *
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has SecondHand-View Financial permission
 */
function canViewFinancialData($user_id, $DB) {
    return hasSecondHandPermission($user_id, 'SecondHand-View Financial', $DB);
}

/**
 * Check if user can view customer information
 *
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has SecondHand-View Customer Data permission
 */
function canViewCustomerData($user_id, $DB) {
    return hasSecondHandPermission($user_id, 'SecondHand-View Customer Data', $DB);
}

/**
 * Check if user can view documents
 *
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has SecondHand-View Documents permission
 */
function canViewDocuments($user_id, $DB) {
    return hasSecondHandPermission($user_id, 'SecondHand-View Documents', $DB);
}

/**
 * Check if user can import trade-ins
 *
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has SecondHand-Import Trade Ins permission
 */
function canImportTradeIns($user_id, $DB) {
    return hasSecondHandPermission($user_id, 'SecondHand-Import Trade Ins', $DB);
}

/**
 * Check if user can manage compliance
 *
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has SecondHand-Manage Compliance permission
 */
function canManageCompliance($user_id, $DB) {
    return hasSecondHandPermission($user_id, 'SecondHand-Manage Compliance', $DB);
}

/**
 * Check if user can view all locations
 *
 * @param int $user_id User ID
 * @param object $DB Database connection object
 * @return bool True if user has SecondHand-View All Locations permission
 */
function canViewAllLocations($user_id, $DB) {
    return hasSecondHandPermission($user_id, 'SecondHand-View All Locations', $DB);
}
?>