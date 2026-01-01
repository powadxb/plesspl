<?php
require 'bootstrap.php';

// Query to fetch user details, including the enabled status
$response = $DB->query("SELECT id, username, password, enabled FROM users WHERE username=?", [$_POST['username']]);

if (!empty($response)) {
    $user_details = $response[0];

    // Check if the user is enabled
    if ($user_details['enabled'] != 1) {
        echo 'account_disabled'; // Return a specific message for disabled accounts
        exit;
    }

    // Verify the password
    $hash = crypt($_POST['password'], $user_details['password']);
    if ($hash === $user_details['password']) {
        // Set a cookie if 'remember me' is selected
        if (isset($_POST['remember'])) {
            setcookie('dins_user_id', $user_details['id'], time() + (86400 * 30), "/"); // 86400 = 1 day
        }

        // Set the session and return success
        $_SESSION['dins_user_id'] = $user_details['id'];
        echo 'valid_login';
    } else {
        echo 'invalid_login'; // Invalid password
    }
} else {
    echo 'invalid_login'; // User not found
}
