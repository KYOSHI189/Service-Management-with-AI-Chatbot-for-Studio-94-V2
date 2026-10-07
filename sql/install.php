<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Automatic Database Installer
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$message = '';
$status  = '';
$installed = false;

try {
    $pdo = db();
    
    // Check if tables already exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $hasUsers = (bool) $stmt->fetch();

    if ($hasUsers && !isset($_POST['force_install'])) {
        $installed = true;
        $status = 'already_installed';
        $message = 'Database is already set up and tables are present!';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['run'])) {
        $sqlFile = __DIR__ . '/install.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("install.sql file not found at $sqlFile");
        }

        $sql = file_get_contents($sqlFile);

        // Remove CREATE DATABASE / USE statements if any remain
        $sql = preg_replace('/CREATE\s+DATABASE[^\;]+;/i', '', $sql);
        $sql = preg_replace('/USE\s+[^;]+;/i', '', $sql);

        // Execute queries
        $pdo->exec($sql);

        $installed = true;
        $status = 'success';
        $message = 'Database successfully installed with all tables and initial seed data!';
    }
} catch (Exception $e) {
    $status = 'error';
    $message = 'Error installing database: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Setup — Studio 94 SnapTrack</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #f4f6f8;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }
    .box {
      background: #fff;
      border-radius: 12px;
      padding: 32px;
      max-width: 520px;
      width: 100%;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    h1 { margin-top: 0; font-size: 22px; color: #111; }
    p { font-size: 14px; color: #555; line-height: 1.6; }
    .alert {
      padding: 14px 16px;
      border-radius: 8px;
      margin: 16px 0;
      font-size: 14px;
    }
    .alert-success { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }
    .alert-error { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; }
    .alert-info { background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; }
    .btn {
      display: inline-block;
      background: #1a73e8;
      color: #fff;
      padding: 10px 20px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      border: none;
      cursor: pointer;
    }
    .btn:hover { background: #1557b0; }
    .btn-secondary { background: #666; margin-left: 10px; }
    .btn-secondary:hover { background: #444; }
    .creds {
      background: #f8f9fa;
      padding: 12px 16px;
      border-radius: 6px;
      margin: 16px 0;
      font-family: monospace;
      font-size: 13px;
    }
  </style>
</head>
<body>
<div class="box">
  <h1>Studio 94 SnapTrack Database Setup</h1>
  
  <?php if ($status === 'success'): ?>
    <div class="alert alert-success"><?= $message ?></div>
    <p>Default login accounts created:</p>
    <div class="creds">
      <strong>Admin:</strong> admin@studio94.com / password<br>
      <strong>Staff:</strong> staff@studio94.com / password<br>
      <strong>Client:</strong> client@studio94.com / password
    </div>
    <a href="../login.php" class="btn">Go to Login</a>

  <?php elseif ($status === 'already_installed'): ?>
    <div class="alert alert-info"><?= $message ?></div>
    <p>Your database already has the required tables. You can sign in or register new accounts.</p>
    <a href="../login.php" class="btn">Go to Login</a>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="force_install" value="1">
      <button type="submit" class="btn btn-secondary" onclick="return confirm('Warning: This may overwrite existing tables. Continue?')">Re-run Installation</button>
    </form>

  <?php elseif ($status === 'error'): ?>
    <div class="alert alert-error"><?= $message ?></div>
    <p>Please check your database connection settings in your Railway Variables (<code>MYSQLHOST</code>, <code>MYSQLUSER</code>, <code>MYSQLPASSWORD</code>, <code>MYSQLDATABASE</code>).</p>
    <a href="install.php?run=1" class="btn">Retry Installation</a>

  <?php else: ?>
    <p>Click below to initialize all database tables and default sample data for Studio 94 SnapTrack.</p>
    <form method="POST">
      <button type="submit" class="btn">Start Database Installation</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
