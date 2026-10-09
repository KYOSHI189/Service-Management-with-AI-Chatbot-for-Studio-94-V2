<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Login / Register Page
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/send-verification.php';

startSession();

// Already logged in → redirect
if (isLoggedIn()) {
    redirectToDashboard($_SESSION['role']);
}

$error   = '';
$success = '';
$tab     = 'login';

// Show OAuth error from google-callback (if any)
if (isset($_SESSION['oauth_error'])) {
    $error = $_SESSION['oauth_error'];
    unset($_SESSION['oauth_error']);
}

// ===== HANDLE FORM SUBMISSIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ---- LOGIN ----
    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error = 'Please enter your email and password.';
        }
        // Gmail Validation
        elseif (!preg_match('/@gmail\.com$/i', $email)) {
            $error = 'Only verified Gmail accounts (@gmail.com) are allowed to sign in.';
        }
        else {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Block if email not verified
                if (empty($user['email_verified'])) {
                    $error = 'Please verify your Gmail first. Check your inbox for the verification link.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role']       = $user['role'];

                    // Forced password change (set by force_password_change.php).
                    // The old shared "password" credential must never survive.
                    if (!empty($user['must_change_password'])) {
                        $_SESSION['must_change_password'] = 1;
                        header('Location: ' . APP_URL . '/change-password.php');
                        exit;
                    }

                    redirectToDashboard($user['role']);
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }

    // ---- REGISTER: STEP 1 — request a verification code (no account created yet) ----
    if ($action === 'register_request_code') {
        $tab      = 'register';
        $fname    = trim($_POST['fname'] ?? '');
        $lname    = trim($_POST['lname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!$fname || !$lname || !$email || !$password || !$confirm_password) {
            $error = 'Please fill in all required fields.';
        }
        elseif (!preg_match('/@gmail\.com$/i', $email)) {
            $error = 'Only Gmail accounts (@gmail.com) are allowed to register.';
        }
        elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        }
        elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        }
        else {
            try {
                $check = db()->prepare('SELECT id, email_verified FROM users WHERE email = ? LIMIT 1');
                $check->execute([$email]);
                $existing = $check->fetch();

                if ($existing && !empty($existing['email_verified'])) {
                    $error = 'An account with that email already exists. Please sign in instead.';
                } else {
                    $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_EXPIRY_HOURS . ' hours'));

                    $emailResult = sendVerificationCode($email, $fname, $code);

                    // Registration is blocked when the code cannot be delivered —
                    // an account that is never verified must not exist.
                    if ($emailResult !== 'sent') {
                        $error = 'We could not send a verification code to ' . htmlspecialchars($email)
                               . '. Please try again in a moment.';
                        error_log('[STUDIO94] Verification code not delivered (' . $emailResult . ') for: ' . $email);
                    } else {
                        // Pending details live in the session only. No user row yet.
                        $_SESSION['pending_registration'] = [
                            'fname'    => $fname,
                            'lname'    => $lname,
                            'email'    => $email,
                            'password' => $password,
                            'code'     => $code,
                            'expires'  => $expires,
                            'attempts' => 0,
                        ];

                        $success = 'We sent a 6-digit verification code to <strong>'
                                 . clean($email) . '</strong>. Check your Gmail inbox (and spam folder), then enter the code below.';
                        $tab = 'verify_code';
                    }
                }
            } catch (Throwable $e) {
                error_log('[STUDIO94] Registration code request error: ' . $e->getMessage());
                $error = 'Could not start registration. Please try again.';
            }
        }
    }

    // ---- REGISTER: STEP 2 — verify the code, then create the account ----
    if ($action === 'register_verify_code') {
        $tab   = 'verify_code';
        $code  = trim($_POST['code'] ?? '');
        $pend  = $_SESSION['pending_registration'] ?? null;

        if (!$pend) {
            $error = 'Your registration session expired. Please start again.';
            $tab   = 'register';
        }
        elseif (date('Y-m-d H:i:s') > $pend['expires']) {
            unset($_SESSION['pending_registration']);
            $error = 'That code has expired. Please request a new one.';
            $tab   = 'register';
        }
        elseif ($pend['attempts'] >= 5) {
            unset($_SESSION['pending_registration']);
            $error = 'Too many incorrect attempts. Please request a new code.';
            $tab   = 'register';
        }
        elseif (!preg_match('/^\d{6}$/', $code)) {
            $pend['attempts']++;
            $_SESSION['pending_registration'] = $pend;
            $error = 'Please enter the 6-digit code from your email.';
        }
        elseif (!hash_equals($pend['code'], $code)) {
            $pend['attempts']++;
            $_SESSION['pending_registration'] = $pend;
            $error = 'That code is not correct. Please check your email and try again.';
        }
        else {
            try {
                $hash = password_hash($pend['password'], PASSWORD_DEFAULT);
                $name = $pend['fname'] . ' ' . $pend['lname'];

                $check = db()->prepare('SELECT id, email_verified FROM users WHERE email = ? LIMIT 1');
                $check->execute([$pend['email']]);
                $existing = $check->fetch();

                if ($existing && !empty($existing['email_verified'])) {
                    unset($_SESSION['pending_registration']);
                    $error = 'An account with that email already exists. Please sign in instead.';
                    $tab   = 'login';
                } else {
                    if ($existing) {
                        $upd = db()->prepare('UPDATE users SET name = ?, password = ?, email_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?');
                        $upd->execute([$name, $hash, $existing['id']]);
                    } else {
                        $ins = db()->prepare('INSERT INTO users (name, email, password, role, is_active, email_verified, created_at) VALUES (?, ?, ?, "client", 1, 1, NOW())');
                        $ins->execute([$name, $pend['email'], $hash]);
                    }

                    unset($_SESSION['pending_registration']);
                    addNotificationByRole('admin', 'New Client Registered',
                        $name . ' (' . $pend['email'] . ') verified their Gmail and created an account.',
                        'new_client', '👤', 'index.php?page=clients');

                    $success = 'Email verified! Your account is ready. Please sign in.';
                    $tab     = 'login';
                }
            } catch (Throwable $e) {
                error_log('[STUDIO94] Account creation error: ' . $e->getMessage());
                $error = 'Could not create your account. Please try again.';
            }
        }
    }

    // ---- FORGOT PASSWORD ----
    if ($action === 'forgot_password') {
        $email = trim($_POST['email'] ?? '');

        if (!$email) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            // Check if email exists in database
            $check = db()->prepare('SELECT id, name FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $check->execute([$email]);
            $user = $check->fetch();

            if (!$user) {
                $error = 'No account found with the email address <strong>' . clean($email) . '</strong>. Please check your spelling or register.';
            } else {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = db()->prepare('INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)');
                $stmt->execute([$user['id'], $email, $token, $expires]);

                // Send password reset email directly to the user's email
                $mailSent = sendPasswordResetEmail($email, $user['name'], $token);

                if ($mailSent) {
                    $success = 'A password reset link has been sent to <strong>' . clean($email) . '</strong>. Please check your Gmail inbox (and spam folder) to reset your password.';
                } else {
                    $error = 'We could not send the password reset email to <strong>' . clean($email) . '</strong>. Please make sure your Gmail address is correct or try again in a few minutes.';
                }
            }
        }
    }

    // ---- RESET PASSWORD ----
    if ($action === 'reset_password') {
        $token            = $_POST['token'] ?? '';
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!$token || !$password || !$confirm_password) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $stmt = db()->prepare('SELECT user_id, email FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0 LIMIT 1');
            $stmt->execute([$token]);
            $reset = $stmt->fetch();

            if ($reset) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update->execute([$hash, $reset['user_id']]);

                $markUsed = db()->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
                $markUsed->execute([$token]);

                $success = 'Your password has been reset successfully. Please sign in.';
                $tab = 'login';
            } else {
                $error = 'Invalid or expired reset token. Please request a new password reset.';
            }
        }
    }
}

$reset_token     = $_GET['token'] ?? '';
$show_reset_form = !empty($reset_token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Studio 94 SnapTrack — Sign In</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/styles.css"/>
  <style>
    .forgot-password-link {
      font-size: 12px;
      color: var(--primary);
      text-decoration: none;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
    }
    .forgot-password-link:hover { text-decoration: underline; }
    .back-to-login { display: block; text-align: center; margin-top: 15px; font-size: 13px; color: var(--muted); }
    .back-to-login a { color: var(--primary); text-decoration: none; }
    .back-to-login a:hover { text-decoration: underline; }
    .reset-info { text-align: center; color: var(--muted); font-size: 13px; margin-bottom: 20px; }

    .btn-google {
      background: #fff;
      color: #3c4043;
      border: 1px solid #dadce0;
      padding: 12px;
      border-radius: 6px;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      font-weight: 500;
      font-size: 14px;
      cursor: pointer;
      transition: background 0.2s;
      margin-top: 15px;
      font-family: inherit;
      text-decoration: none;
    }
    .btn-google:hover { background: #f8f9fa; text-decoration: none; }
    .btn-google img { width: 20px; height: 20px; }
    .divider { display: flex; align-items: center; text-align: center; margin: 20px 0; color: var(--muted); font-size: 12px; }
    .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--border); }
    .divider:not(:empty)::before { margin-right: .5em; }
    .divider:not(:empty)::after { margin-left: .5em; }

    .gmail-hint {
      display: block;
      font-size: 11px;
      color: var(--muted);
      margin-top: 4px;
    }
  </style>
</head>
<body class="auth-page">
<div class="login-container">

  <!-- LEFT: Visual Panel -->
  <div class="login-left">
    <div class="twisted-photos">
      <div class="twisted-photo tp-1"></div>
      <div class="twisted-photo tp-2"></div>
      <div class="twisted-photo tp-3"></div>
      <div class="twisted-photo tp-4"></div>
    </div>
    <div class="login-left-content">
      <div style="font-size:14px;font-weight:500;letter-spacing:2px;color:var(--muted);text-transform:uppercase;margin-bottom:20px;">Studio 94</div>
      <h1>Every frame<br/>tells a story.</h1>
      <p>Professional photography session management,<br/>beautifully crafted.</p>
    </div>
  </div>

  <!-- RIGHT: Form Panel -->
  <div class="login-right">
    <div class="login-form-wrap">
      <h2><?= $show_reset_form ? 'Reset Password' : 'Welcome.' ?></h2>
      <p class="subtitle"><?= $show_reset_form ? 'Enter your new password below.' : 'Please enter your details to continue.' ?></p>

      <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;">
          <span class="alert-icon">❌</span>
          <span><?= $error ?></span>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:16px;background:var(--green-bg);border:1px solid var(--green);color:var(--green-text);">
          <span class="alert-icon">✅</span>
          <span><?= $success ?></span>
        </div>
      <?php endif; ?>

      <?php if ($show_reset_form): ?>
        <!-- RESET PASSWORD FORM -->
        <form method="POST" action="login.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="token" value="<?= clean($reset_token) ?>">
          <div class="form-group">
            <label>New Password <small style="text-transform:none;color:var(--muted);">(min 8 characters)</small></label>
            <input type="password" name="password" placeholder="••••••••" required minlength="8" autocomplete="new-password"/>
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="••••••••" required minlength="8" autocomplete="new-password"/>
          </div>
          <button type="submit" class="btn-primary full-width" style="padding:13px;justify-content:center;">Reset Password</button>
        </form>
        <div class="back-to-login">
          <a href="login.php">← Back to Sign In</a>
        </div>

      <?php else: ?>
        <!-- TABS: Login / Register -->
        <div class="tab-wrap">
          <button class="tab-btn <?= $tab==='login'?'active':'' ?>" onclick="showTab('login',this)">Sign In</button>
          <button class="tab-btn <?= $tab==='register'?'active':'' ?>" onclick="showTab('register',this)">Create Account</button>
        </div>

        <!-- LOGIN TAB -->
        <div id="login-tab" class="tab-content <?= $tab==='login'?'active':'' ?>">
          <form method="POST" action="login.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="login">
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" placeholder="you@gmail.com" required autocomplete="email"
                     pattern="^[A-Za-z0-9._%+-]+@gmail\.com$"
                     title="Please use a valid Gmail address (ending in @gmail.com)"/>
              <small class="gmail-hint">Only verified Gmail accounts can sign in.</small>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password"/>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
              <label style="font-size:12px;color:var(--dark);display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="remember"> Remember me
              </label>
              <button type="button" class="forgot-password-link" onclick="showForgotPassword()">Forgot Password?</button>
            </div>
            <button type="submit" class="btn-primary full-width" style="padding:13px;justify-content:center;">Sign In</button>
          </form>

          <div class="divider">or</div>
          <a href="google-login.php" class="btn-google">
            <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google">
            Continue with Google
          </a>
        </div>

        <!-- REGISTER TAB -->
        <div id="register-tab" class="tab-content <?= $tab==='register'?'active':'' ?>">
          <form method="POST" action="login.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="register_request_code">
            <div class="form-row">
              <div class="form-group">
                <label>First Name</label>
                <input type="text" name="fname" placeholder="Maria" required/>
              </div>
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lname" placeholder="Santos" required/>
              </div>
            </div>
            <div class="form-group">
              <label>Email Address (Gmail only)</label>
              <input type="email" name="email" placeholder="you@gmail.com" required autocomplete="email"
                     pattern="^[A-Za-z0-9._%+-]+@gmail\.com$"
                     title="Please use a valid Gmail address (ending in @gmail.com)"/>
              <small class="gmail-hint">Only verified Gmail accounts can register.</small>
            </div>
            <div class="form-group">
              <label>Password <small style="text-transform:none;color:var(--muted);">(min 8 characters)</small></label>
              <input type="password" name="password" placeholder="••••••••" required minlength="8" autocomplete="new-password"/>
            </div>
            <div class="form-group">
              <label>Confirm Password</label>
              <input type="password" name="confirm_password" placeholder="••••••••" required minlength="8" autocomplete="new-password"/>
            </div>
            <button type="submit" class="btn-primary full-width" style="padding:13px;justify-content:center;">Send Verification Code</button>
          </form>

          <div class="divider">or</div>
          <a href="google-login.php" class="btn-google">
            <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google">
            Sign up with Google
          </a>
        </div>

        <!-- VERIFY CODE TAB (shown after a code is sent) -->
        <div id="verify-code-tab" class="tab-content <?= $tab==='verify_code'?'active':'' ?>">
          <form method="POST" action="login.php" id="verify-code-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="register_verify_code">

            <h3 style="font-size:16px;margin-bottom:8px;">Enter verification code</h3>
            <p style="font-size:13px;color:var(--muted);margin-bottom:18px;">
              We emailed a 6-digit code to
              <strong><?= clean($_SESSION['pending_registration']['email'] ?? '') ?></strong>.
              Your account is created once you enter it.
            </p>

            <div class="form-group">
              <label>6-digit code</label>
              <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                     placeholder="000000" required autocomplete="one-time-code"
                     style="letter-spacing:10px;font-size:22px;text-align:center;font-weight:bold;"/>
            </div>

            <button type="submit" class="btn-primary full-width" style="padding:13px;justify-content:center;">
              Verify &amp; Create Account
            </button>
          </form>

          <div class="divider">or</div>
          <button type="button" class="btn-google" style="width:100%;background:none;border:none;cursor:pointer;"
                  onclick="showTab('register', document.querySelectorAll('.tab-btn')[1])">
            ← Back to registration
          </button>
        </div>

        <!-- FORGOT PASSWORD FORM -->
        <div id="forgot-password-form" style="display:none;margin-top:20px;border-top:1px solid var(--border);padding-top:20px;">
          <h3 style="font-size:16px;margin-bottom:10px;">Reset Password</h3>
          <p style="font-size:13px;color:var(--muted);margin-bottom:15px;">Enter your Gmail address and we'll send you a link to reset your password.</p>
          <form method="POST" action="login.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="forgot_password">
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" placeholder="you@gmail.com" required autocomplete="email"/>
            </div>
            <button type="submit" class="btn-primary full-width" style="padding:13px;justify-content:center;">Send Reset Link</button>
          </form>
          <div class="back-to-login">
            <button type="button" class="forgot-password-link" onclick="hideForgotPassword()" style="font-size:13px;">← Back to Sign In</button>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<script>
function showTab(tab, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(tab + '-tab').classList.add('active');
  if (btn) btn.classList.add('active');
  document.getElementById('forgot-password-form').style.display = 'none';
}

function showForgotPassword() {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('forgot-password-form').style.display = 'block';
}

function hideForgotPassword() {
  document.getElementById('forgot-password-form').style.display = 'none';
  document.getElementById('login-tab').classList.add('active');
  document.querySelector('.tab-btn:first-child').classList.add('active');
}
</script>
</body>
</html>