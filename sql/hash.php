<?php
// Generate correct password hash
$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo 'Password: ' . $password . '<br>';
echo 'Hash: ' . $hash . '<br><br>';

// Generate for all users
echo 'UPDATE users SET password = \'' . $hash . '\' WHERE email IN (\'admin@studio94.com\', \'staff@studio94.com\', \'client@studio94.com\', \'pedro@studio94.com\', \'ana@studio94.com\', \'carlo@studio94.com\', \'bea@studio94.com\');';
?>