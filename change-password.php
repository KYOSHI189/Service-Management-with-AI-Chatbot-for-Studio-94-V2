<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Forced password change
// ============================================================
// Reached only when $_SESSION['must_change_password'] is set.
// Every other entry point calls requireLogin(), which rejects a
// user without this session flag, so this page is not reachable
// by simply typing the URL.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

if (empty($_SESSION['must_change_password'])) {
    // Nothing to do here - send them to their normal home.
    redirectToDashboard($_SESSION['role'] ?? 'client');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- LOGOUT ACTION ----
    if (($_POST['action'] ?? '') === 'logout') {
        unset($_SESSION['must_change_password']);
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    // ---- CHANGE PASSWORD ACTION ----
    verifyCsrf();

    $newPassword = $_POST['new_password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    }
    elseif ($newPassword === 'password') {
        // The compromised shared credential, explicitly rejected.
        $error = 'That password is not allowed. Choose something unique.';
    }
    elseif ($newPassword !== $confirm) {
        $error = 'Passwords do not match.';
    }
    else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = db()->prepare('
            UPDATE users
            SET password = ?, must_change_password = 0
            WHERE id = ?
        ');
        $stmt->execute([$hash, (int)$_SESSION['user_id']]);

        unset($_SESSION['must_change_password']);
        setFlash('success', 'Password updated. Welcome back!');

        redirectToDashboard($_SESSION['role'] ?? 'client');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password — Studio 94</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/styles.css"/>
<style>
  body { font-family: 'Trebuchet MS', Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #FAF7F2; padding: 20px; }
  .card { background: #fff; border: 1px solid #E0E0E0; border-radius: 16px; padding: 40px; max-width: 440px; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,.06); }
  h2 { margin: 0 0 6px; color: #111; }
  .warn { background: #FFF4E5; border: 1px solid #FFD8A8; border-radius: 8px; padding: 12px 14px; font-size: 14px; color: #8a5200; margin: 16px 0; }
  label { display: block; font-size: 13px; color: #555; margin: 14px 0 6px; }
  input[type=password] { width: 100%; box-sizing: border-box; padding: 12px; border: 1px solid #E0E0E0; border-radius: 8px; font-size: 15px; }
  button { width: 100%; margin-top: 20px; padding: 13px; background: #111; color: #fff; border: none; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; }
  button:hover { background: #333; }
  .logout { margin-top: 12px; }
  .err { background: #fce8e6; border: 1px solid #fad2cf; color: #c5221f; border-radius: 8px; padding: 12px 14px; font-size: 14px; margin: 16px 0; }
</style>
</head>
<body>
<div class="card">
  <h2>Set a new password</h2>
  <p style="color:#555;font-size:14px;margin:6px 0 0;">
    Signed in as <?= clean($_SESSION['user_email'] ?? '') ?>
  </p>

  <div class="warn">
    Your account password was reset after a security incident.
    You must choose a new, unique password before continuing.
  </div>

  <?php if ($error): ?>
    <div class="err"><?= clean($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="change">

    <label for="new_password">New password (min 8 characters)</label>
    <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">

    <label for="confirm_password">Confirm new password</label>
    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">

    <button type="submit">Update password</button>
  </form>

  <form method="POST" class="logout">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="logout">
    <button type="submit" style="background:#666;">Sign out instead</button>
  </form>
</div>
</body>
</html>