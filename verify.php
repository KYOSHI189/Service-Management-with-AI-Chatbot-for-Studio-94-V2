<?php
// ============================================================
// verify.php — Handles email verification link
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

startSession();

$token   = $_GET['token'] ?? '';
$message = '';
$success = false;

if (empty($token)) {
    $message = 'Invalid verification link.';
} else {
    $stmt = db()->prepare('SELECT id FROM users WHERE verification_token = ? AND verification_expires > NOW() AND email_verified = 0 LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $upd = db()->prepare('UPDATE users SET email_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?');
        $upd->execute([$user['id']]);
        $success = true;
        $message = 'Your Gmail has been verified! You can now sign in.';
    } else {
        $message = 'This verification link is invalid, already used, or has expired. Please register again or request a new link.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Verification — Studio 94</title>
<style>
  body { font-family: "Trebuchet MS", Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f6f4f4; padding: 20px; }
  .card { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); max-width: 440px; text-align: center; }
  .icon { font-size: 52px; margin-bottom: 15px; }
  h2 { margin: 0 0 12px; color: #111; }
  p { color: #555; line-height: 1.6; margin-bottom: 20px; }
  a.btn { display: inline-block; margin-top: 10px; background: #111; color: #fff; padding: 13px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; }
  a.btn:hover { background: #333; }
</style>
</head>
<body>
  <div class="card">
    <div class="icon"><?= $success ? '✅' : '❌' ?></div>
    <h2><?= $success ? 'Verified!' : 'Verification Failed' ?></h2>
    <p><?= htmlspecialchars($message) ?></p>
    <a href="login.php" class="btn">Go to Sign In</a>
  </div>
</body>
</html>