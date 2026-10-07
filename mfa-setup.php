<?php
// ============================================================
// MFA Setup — Enable/Disable Two-Factor Authentication
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mfa-helper.php';

startSession();

// Must be logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Fetch current user
$stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = '';

// Handle cancel
if (isset($_GET['cancel'])) {
    unset($_SESSION['pending_mfa_secret']);
    header('Location: mfa-setup.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ---- GENERATE: Create secret + show QR ----
    if ($action === 'generate') {
        $_SESSION['pending_mfa_secret'] = MFAHelper::generateSecret();
        header('Location: mfa-setup.php');
        exit;
    }

    // ---- VERIFY: Confirm code + enable MFA ----
    if ($action === 'verify') {
        $code   = trim($_POST['code'] ?? '');
        $secret = $_SESSION['pending_mfa_secret'] ?? '';

        if (!$secret) {
            $error = 'Session expired. Please try again.';
        } elseif (!MFAHelper::verifyCode($secret, $code)) {
            $error = 'Invalid code. Please make sure your device time is correct and try again.';
        } else {
            // Generate + hash backup codes
            $backupCodes  = MFAHelper::generateBackupCodes();
            $hashedBackup = MFAHelper::hashBackupCodes($backupCodes);

            // Save to DB
            $upd = db()->prepare('UPDATE users SET mfa_enabled = 1, mfa_secret = ?, mfa_backup_codes = ?, mfa_verified_at = NOW() WHERE id = ?');
            $upd->execute([$secret, $hashedBackup, $user['id']]);

            unset($_SESSION['pending_mfa_secret']);
            $_SESSION['new_backup_codes'] = $backupCodes;

            header('Location: mfa-setup.php?step=backup');
            exit;
        }
    }

    // ---- DISABLE: Turn off MFA ----
    if ($action === 'disable') {
        $password = $_POST['password'] ?? '';
        if (!password_verify($password, $user['password'])) {
            $error = 'Password is incorrect.';
        } else {
            $upd = db()->prepare('UPDATE users SET mfa_enabled = 0, mfa_secret = NULL, mfa_backup_codes = NULL, mfa_verified_at = NULL WHERE id = ?');
            $upd->execute([$user['id']]);

            // Refresh user data
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();

            $success = 'Two-factor authentication has been disabled.';
        }
    }
}

$step = $_GET['step'] ?? '';
$pendingSecret = $_SESSION['pending_mfa_secret'] ?? '';
$backupCodes = $_SESSION['new_backup_codes'] ?? [];
$qrDataUri = $pendingSecret ? MFAHelper::getQRCodeDataURI($pendingSecret, $user['email'], 'Studio 94') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security Settings — Studio 94</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/styles.css">
<style>
  body { font-family: "Trebuchet MS", Arial, sans-serif; background: #f6f4f4; margin: 0; padding: 40px 20px; min-height: 100vh; }
  .card { max-width: 560px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
  h1 { margin: 0 0 8px; font-size: 24px; color: #111; }
  h2 { margin: 0 0 8px; font-size: 18px; color: #111; }
  p { color: #555; line-height: 1.6; margin: 0 0 16px; }
  .qr-wrap { text-align: center; margin: 24px 0; }
  .qr-wrap img { border: 8px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,.1); border-radius: 8px; display: inline-block; max-width: 220px; }
  .secret-box { background: #f6f4f4; padding: 12px 16px; border-radius: 8px; font-family: monospace; font-size: 14px; word-break: break-all; text-align: center; margin: 12px 0; letter-spacing: 2px; }
  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #333; }
  .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
  .form-group input[type="text"] { text-align: center; letter-spacing: 4px; font-family: monospace; font-size: 20px; }
  .btn { display: inline-block; width: 100%; padding: 14px; background: #111; color: #fff; border: 0; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; box-sizing: border-box; }
  .btn:hover { background: #333; }
  .btn-danger { background: #c0392b; }
  .btn-danger:hover { background: #a93226; }
  .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
  .alert-danger { background: #fdecea; color: #c0392b; border: 1px solid #f5c6c0; }
  .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
  .backup-codes { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0; }
  .backup-code { background: #f6f4f4; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 14px; text-align: center; letter-spacing: 1px; }
  .status-badge { display: inline-block; padding: 4px 10px; border-radius: 100px; font-size: 12px; font-weight: 600; vertical-align: middle; }
  .status-on { background: #e8f5e9; color: #2e7d32; }
  .status-off { background: #fdecea; color: #c0392b; }
  .back-link { display: block; text-align: center; margin-top: 20px; font-size: 13px; color: #888; text-decoration: none; }
  .back-link:hover { color: #111; }
  ul { color: #555; line-height: 1.8; padding-left: 20px; }
</style>
</head>
<body>
<div class="card">

<?php if ($step === 'backup' && !empty($backupCodes)): ?>
  <!-- ============ BACKUP CODES ============ -->
  <h1>Save Your Backup Codes</h1>
  <p>These codes can be used to access your account if you lose your phone. <strong>Save them now — they won't be shown again.</strong></p>

  <div class="backup-codes">
    <?php foreach ($backupCodes as $code): ?>
      <div class="backup-code"><?= htmlspecialchars($code) ?></div>
    <?php endforeach; ?>
  </div>

  <p style="font-size:13px;color:#888;text-align:center;">Store these in a safe place (password manager, printed, etc.)</p>

  <a href="mfa-setup.php" class="btn" onclick="return confirm('Have you saved your backup codes? They will NOT be shown again.');">I've Saved My Codes</a>

  <?php unset($_SESSION['new_backup_codes']); ?>

<?php elseif (!empty($pendingSecret)): ?>
  <!-- ============ SCAN QR + VERIFY ============ -->
  <h1>Set Up Two-Factor Authentication</h1>
  <p>Scan this QR code with <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>Microsoft Authenticator</strong>:</p>

  <div class="qr-wrap">
    <img src="<?= htmlspecialchars($qrDataUri) ?>" alt="MFA QR Code">
  </div>

  <p style="text-align:center;font-size:13px;color:#888;">Can't scan? Enter this key manually:</p>
  <div class="secret-box"><?= htmlspecialchars($pendingSecret) ?></div>

  <p>Then enter the 6-digit code from your app to confirm:</p>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="verify">
    <div class="form-group">
      <input type="text" name="code" placeholder="000000" maxlength="6" pattern="\d{6}" inputmode="numeric" autofocus required>
    </div>
    <button type="submit" class="btn">Verify &amp; Enable MFA</button>
  </form>

  <a href="mfa-setup.php?cancel=1" class="back-link">← Cancel setup</a>

<?php elseif ($user['mfa_enabled']): ?>
  <!-- ============ MFA ENABLED ============ -->
  <h1>Two-Factor Authentication <span class="status-badge status-on">ENABLED</span></h1>
  <p>Your account is protected with an extra layer of security.</p>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <p style="margin-top:24px;font-size:13px;color:#888;">To disable MFA, enter your password below.</p>

  <form method="POST" onsubmit="return confirm('Are you sure you want to disable MFA? Your account will be less secure.');">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="disable">
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="password" placeholder="Enter your password" required>
    </div>
    <button type="submit" class="btn btn-danger">Disable MFA</button>
  </form>

<?php else: ?>
  <!-- ============ MFA DISABLED ============ -->
  <h1>Two-Factor Authentication <span class="status-badge status-off">DISABLED</span></h1>
  <p>Add an extra layer of security to your account. Even if someone steals your password, they won't be able to log in without your phone.</p>

  <p style="margin-top:24px;font-weight:600;color:#111;">You'll need an authenticator app:</p>
  <ul>
    <li>Google Authenticator (iOS / Android)</li>
    <li>Microsoft Authenticator</li>
    <li>Authy</li>
  </ul>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="POST" style="margin-top:24px;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="generate">
    <button type="submit" class="btn">Enable Two-Factor Authentication</button>
  </form>

<?php endif; ?>

  <a href="index.php?page=dashboard" class="back-link">← Back to Dashboard</a>
</div>
</body>
</html>