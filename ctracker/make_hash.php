<?php
// make_hash.php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "New hash for 'admin123': " . $hash;

// Verify it works
$verify = password_verify($password, $hash);
echo "<br>Verification test: " . ($verify ? "PASS" : "FAIL");

// Now create the SQL to update the database
echo "<br><br>SQL to update admin password:<br>";
echo "UPDATE users SET password = '" . $hash . "' WHERE username = 'admin';";
?>