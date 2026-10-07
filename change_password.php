<?php
// =============================================
// CHANGE ALL PASSWORDS TO 'password'
// =============================================

require_once 'config.php';
require_once 'functions.php';

$pdo = db();

// Generate hash for 'password'
$hash = password_hash('password', PASSWORD_DEFAULT);

echo "✅ New hash: <code>$hash</code><br><br>";

// Update ALL users
$update = $pdo->prepare("UPDATE users SET password = ?");
$update->execute([$hash]);

echo "✅ All users password changed to '<strong>password</strong>'<br><br>";

// Verify
$stmt = $pdo->query("SELECT email FROM users");
$users = $stmt->fetchAll();

echo "Users updated:<br>";
foreach ($users as $user) {
    echo "• {$user['email']} → password<br>";
}

echo "<br><a href='login.php'>Go to Login →</a>";
?>