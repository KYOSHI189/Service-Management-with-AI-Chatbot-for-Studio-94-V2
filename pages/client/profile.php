<?php
// ============================================================
// STUDIO 94 SNAPTRACK — User Profile Page (Shared)
// ============================================================

if (!isset($user) || !isset($role)) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$userId  = (int)$user['id'];
$message = '';
$error   = '';

// ============================================================
// HANDLE FORM SUBMISSIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- UPDATE PROFILE INFO ----------
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$name || !$email) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $check = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $check->execute([$email, $userId]);

            if ($check->fetch()) {
                $error = 'That email is already used by another account.';
            } else {
                $upd = db()->prepare('
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, updated_at = NOW() 
                    WHERE id = ?
                ');
                $upd->execute([$name, $email, $phone, $userId]);

                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;

                $message = 'Profile updated successfully!';

                $user['name']  = $name;
                $user['email'] = $email;
                $user['phone'] = $phone;
            }
        }
    }

    // ---------- CHANGE PASSWORD ----------
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $error = 'Please fill in all password fields.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $stmt = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($current, $row['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $upd  = db()->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
                $upd->execute([$hash, $userId]);

                $message = 'Password changed successfully!';
            }
        }
    }

    // ---------- UPLOAD AVATAR ----------
    if (isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
        if (!empty($_FILES['avatar']['name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $error = 'Only JPG, PNG, and WEBP images are allowed.';
            } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                $error = 'Avatar must be under 2MB.';
            } else {
                $ext      = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../assets/uploads/avatars/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
                    $oldStmt = db()->prepare('SELECT avatar FROM users WHERE id = ? LIMIT 1');
                    $oldStmt->execute([$userId]);
                    $oldAvatar = $oldStmt->fetchColumn();
                    if ($oldAvatar && file_exists($uploadDir . $oldAvatar)) {
                        @unlink($uploadDir . $oldAvatar);
                    }

                    $upd = db()->prepare('UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?');
                    $upd->execute([$filename, $userId]);

                    $_SESSION['user_avatar'] = $filename;
                    $user['avatar'] = $filename;

                    $message = 'Avatar updated successfully!';
                } else {
                    $error = 'Failed to upload avatar.';
                }
            }
        }
    }
}

// ============================================================
// FETCH FRESH USER DATA
// ============================================================
$stmt = db()->prepare('
    SELECT id, name, email, phone, role, avatar, created_at, last_login 
    FROM users WHERE id = ? LIMIT 1
');
$stmt->execute([$userId]);
$me = $stmt->fetch();

if (!$me) {
    echo '<div class="alert alert-danger">User not found.</div>';
    return;
}

$avatarUrl = !empty($me['avatar'])
    ? APP_URL . '/assets/uploads/avatars/' . $me['avatar'] . '?v=' . time()
    : null;
?>

<!-- ============================================================ -->
<!-- FULLY RESPONSIVE STYLES                                       -->
<!-- ============================================================ -->
<style>
/* ============================================================
   BASE (MOBILE-FIRST) — 320px pataas
   ============================================================ */

.profile-page {
    padding: 14px;
    max-width: 1200px;
    margin: 0 auto;
    box-sizing: border-box;
    width: 100%;
}

/* HEADER */
.profile-header {
    margin-bottom: 16px;
}
.profile-header h1 {
    font-size: 20px;
    margin: 0 0 4px;
    line-height: 1.25;
    word-break: break-word;
}
.profile-header p {
    color: var(--muted);
    font-size: 12px;
    margin: 0;
    line-height: 1.4;
}

/* ALERTS */
.profile-page .alert {
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: 13px;
    line-height: 1.4;
    word-break: break-word;
}

/* GRID — mobile first: 1 col */
.profile-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

/* CARD */
.profile-card {
    background: #fff;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
    border: 1px solid var(--border);
    margin-bottom: 14px;
    box-sizing: border-box;
    min-width: 0;
}
.profile-card h3 {
    font-size: 15px;
    margin: 0 0 4px;
    line-height: 1.3;
    word-break: break-word;
}

.card-desc {
    font-size: 12px;
    color: var(--muted);
    margin: 0 0 14px;
    line-height: 1.4;
    word-break: break-word;
}

/* SUMMARY COLUMN */
.profile-summary {
    text-align: center;
}

.avatar-wrapper {
    margin: 0 auto 14px;
    width: 100px;
    height: 100px;
    position: relative;
}

.avatar-img,
.avatar-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--dark);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(0,0,0,.1);
    box-sizing: border-box;
}

.profile-summary h2 {
    font-size: 17px;
    margin: 0 0 6px;
    line-height: 1.3;
    word-break: break-word;
}

.role-badge {
    display: inline-block;
    background: rgba(108, 99, 255, 0.1);
    color: #6C63FF;
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 14px;
    white-space: nowrap;
}

/* UPLOAD BUTTON */
.btn-upload {
    display: inline-block;
    padding: 8px 14px;
    background: #f1f3f5;
    color: #333;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
    margin-bottom: 16px;
    white-space: nowrap;
}
.btn-upload:hover {
    background: #e1e4e8;
}

/* META INFO */
.profile-meta {
    border-top: 1px solid var(--border);
    padding-top: 14px;
    text-align: left;
}
.meta-item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 12px;
    padding: 6px 0;
    flex-wrap: wrap;
    word-break: break-word;
}
.meta-label { color: var(--muted); }
.meta-value {
    font-weight: 500;
    color: var(--dark);
    text-align: right;
    word-break: break-word;
}

/* FORM GROUP */
.form-group { margin-bottom: 12px; }
.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--dark);
}
.form-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.2s;
    box-sizing: border-box;
    background: #fff;
}
.form-group input:focus {
    outline: none;
    border-color: #6C63FF;
    box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
}

/* PRIMARY BUTTON */
.profile-page .btn-primary {
    width: 100%;
    padding: 11px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    box-sizing: border-box;
}

/* ============================================================
   SMALL PHONE (min-width: 380px)
   ============================================================ */
@media (min-width: 380px) {
    .profile-page { padding: 16px; }
    .profile-header h1 { font-size: 22px; }
    .profile-header p { font-size: 13px; }
    .profile-card { padding: 18px; }
    .profile-card h3 { font-size: 16px; }
    .avatar-wrapper,
    .avatar-img,
    .avatar-placeholder { width: 110px; height: 110px; }
    .avatar-placeholder { font-size: 44px; }
    .profile-summary h2 { font-size: 18px; }
    .meta-item { font-size: 13px; }
    .form-group input { font-size: 14px; padding: 10px 14px; }
    .profile-page .btn-primary { font-size: 14px; }
}

/* ============================================================
   LARGE PHONE / SMALL TABLET (min-width: 600px)
   ============================================================ */
@media (min-width: 600px) {
    .profile-page { padding: 20px; }

    .profile-header { margin-bottom: 20px; }
    .profile-header h1 { font-size: 24px; }
    .profile-header p { font-size: 13px; }

    .profile-grid {
        grid-template-columns: 240px 1fr;
        gap: 18px;
    }

    .profile-card { padding: 20px; margin-bottom: 18px; }

    .avatar-wrapper,
    .avatar-img,
    .avatar-placeholder { width: 120px; height: 120px; }
    .avatar-placeholder { font-size: 48px; }
    .profile-summary h2 { font-size: 20px; }

    .profile-page .btn-primary {
        width: auto;
        min-width: 160px;
    }
}

/* ============================================================
   TABLET (min-width: 768px)
   ============================================================ */
@media (min-width: 768px) {
    .profile-page { padding: 24px; }

    .profile-header h1 { font-size: 26px; }
    .profile-header p { font-size: 14px; }

    .profile-grid {
        grid-template-columns: 280px 1fr;
        gap: 22px;
    }

    .profile-card {
        padding: 22px;
        border-radius: 14px;
    }
    .profile-card h3 { font-size: 17px; }
    .card-desc { font-size: 13px; margin-bottom: 16px; }

    .avatar-wrapper,
    .avatar-img,
    .avatar-placeholder { width: 130px; height: 130px; }
    .avatar-placeholder { font-size: 52px; }

    .meta-item { font-size: 13px; padding: 7px 0; }

    .form-group { margin-bottom: 16px; }
    .form-group label { font-size: 13px; }
    .form-group input { padding: 10px 14px; }
}

/* ============================================================
   LAPTOP / DESKTOP (min-width: 1024px)
   ============================================================ */
@media (min-width: 1024px) {
    .profile-page { padding: 32px; }

    .profile-header { margin-bottom: 24px; }
    .profile-header h1 { font-size: 28px; }

    .profile-grid {
        grid-template-columns: 320px 1fr;
        gap: 24px;
    }

    .profile-card { padding: 24px; margin-bottom: 20px; }
    .profile-card h3 { font-size: 18px; }

    .avatar-wrapper,
    .avatar-img,
    .avatar-placeholder { width: 140px; height: 140px; }
    .avatar-placeholder { font-size: 56px; }

    .meta-item { font-size: 14px; }

    .form-group { margin-bottom: 16px; }
    .form-group input { padding: 10px 14px; font-size: 14px; }
}

/* ============================================================
   LARGE DESKTOP (min-width: 1440px)
   ============================================================ */
@media (min-width: 1440px) {
    .profile-page { padding: 40px; }
    .profile-header h1 { font-size: 30px; }

    .profile-card { padding: 26px; border-radius: 16px; }

    .avatar-wrapper,
    .avatar-img,
    .avatar-placeholder { width: 150px; height: 150px; }
    .avatar-placeholder { font-size: 60px; }
}

/* ============================================================
   ULTRAWIDE (min-width: 1920px)
   ============================================================ */
@media (min-width: 1920px) {
    .profile-page { padding: 48px; }
    .profile-card { padding: 30px; }
}

/* ============================================================
   LANDSCAPE MOBILE
   ============================================================ */
@media (max-height: 500px) and (orientation: landscape) {
    .profile-page { padding: 12px; }
    .profile-header h1 { font-size: 20px; }

    .profile-grid {
        grid-template-columns: 200px 1fr;
        gap: 14px;
    }

    .avatar-wrapper,
    .avatar-img,
    .avatar-placeholder { width: 90px; height: 90px; }
    .avatar-placeholder { font-size: 36px; }
}

/* ============================================================
   PRINT
   ============================================================ */
@media print {
    .btn-upload,
    .profile-page .btn-primary { display: none !important; }
    .profile-card { break-inside: avoid; box-shadow: none; border: 1px solid #ccc; }
}
</style>

<!-- ============================================================ -->
<!-- PROFILE CONTENT                                               -->
<!-- ============================================================ -->
<div class="profile-page">

    <div class="profile-header">
        <h1><strong>👤 My Profile</strong></h1>
        <p>Manage your personal information and account security.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            ✅ <?= clean($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            ❌ <?= clean($error) ?>
        </div>
    <?php endif; ?>

    <div class="profile-grid">

        <!-- LEFT COLUMN: AVATAR -->
        <div class="profile-card profile-summary">
            <div class="avatar-wrapper">
                <?php if ($avatarUrl): ?>
                    <img src="<?= $avatarUrl ?>" alt="Avatar" class="avatar-img">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?= strtoupper(mb_substr($me['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <h2><strong><?= clean($me['name']) ?></strong></h2>
            <div class="role-badge"><?= ucfirst($me['role']) ?></div>

            <form method="POST" enctype="multipart/form-data" class="avatar-form">
                <input type="hidden" name="action" value="upload_avatar">
                <label for="avatarInput" class="btn-upload">
                    📷 Change Photo
                </label>
                <input 
                    type="file" 
                    id="avatarInput" 
                    name="avatar" 
                    accept="image/*" 
                    onchange="this.form.submit()"
                    style="display:none;"
                >
            </form>

            <div class="profile-meta">
                <div class="meta-item">
                    <span class="meta-label">Member Since</span>
                    <span class="meta-value">
                        <?= date('F j, Y', strtotime($me['created_at'])) ?>
                    </span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Last Login</span>
                    <span class="meta-value">
                        <?= $me['last_login'] ? date('M j, Y g:i A', strtotime($me['last_login'])) : 'N/A' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: FORMS -->
        <div class="profile-forms">

            <div class="profile-card">
                <h3><strong>📝 Personal Information</strong></h3>
                <p class="card-desc">Update your basic account details.</p>

                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label><strong>Full Name</strong></label>
                        <input type="text" name="name" value="<?= clean($me['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label><strong>Email Address</strong></label>
                        <input type="email" name="email" value="<?= clean($me['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label><strong>Phone Number</strong></label>
                        <input type="text" name="phone" value="<?= clean($me['phone']) ?>" placeholder="+63 9XX XXX XXXX">
                    </div>

                    <button type="submit" class="btn-primary">
                        💾 Save Changes
                    </button>
                </form>
            </div>

            <div class="profile-card">
                <h3><strong>🔒 Change Password</strong></h3>
                <p class="card-desc">Keep your account secure with a strong password.</p>

                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label><strong>Current Password</strong></label>
                        <input type="password" name="current_password" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label><strong>New Password</strong></label>
                        <input type="password" name="new_password" placeholder="Min 8 characters" minlength="8" required>
                    </div>

                    <div class="form-group">
                        <label><strong>Confirm New Password</strong></label>
                        <input type="password" name="confirm_password" placeholder="Repeat new password" minlength="8" required>
                    </div>

                    <button type="submit" class="btn-primary">
                        🔐 Update Password
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>