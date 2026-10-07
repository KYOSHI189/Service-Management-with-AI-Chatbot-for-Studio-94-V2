<?php
// ============================================================
// STUDIO 94 SNAPTRACK — SETTINGS
// Admin Only — GCash + Maribank Payment Settings
// ============================================================

requireRole(['admin']);

$pdo = db();

// ============================================================
// UPLOAD PATHS
// ============================================================
$uploadDir = dirname(__DIR__, 2) . '/uploads/payment/';
$uploadUrl = 'uploads/payment/';

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// ============================================================
// HANDLE POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    try {
        // ====================================================
        // TEXT SETTINGS
        // ====================================================
        $fields = [
            'studio_name',
            'studio_address',
            'studio_email',
            'studio_phone',
            'hours_weekday',
            'hours_saturday',
            'hours_sunday',
            'gcash_account_name',
            'gcash_account_number',
            'maribank_account_name',
            'maribank_account_number',
        ];

        foreach ($fields as $field) {
            $val = trim($_POST[$field] ?? '');

            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`, `value`)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `value` = ?
            ");
            $stmt->execute([$field, $val, $val]);
        }

        // ====================================================
        // QR UPLOAD — GCASH
        // ====================================================
        if (
            isset($_FILES['gcash_qr']) &&
            $_FILES['gcash_qr']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $qrPath = handleQrUpload(
                $_FILES['gcash_qr'],
                'gcash_qr',
                $uploadDir,
                $uploadUrl,
                $pdo
            );
        }

        // ====================================================
        // QR UPLOAD — MARIBANK
        // ====================================================
        if (
            isset($_FILES['maribank_qr']) &&
            $_FILES['maribank_qr']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $qrPath = handleQrUpload(
                $_FILES['maribank_qr'],
                'maribank_qr',
                $uploadDir,
                $uploadUrl,
                $pdo
            );
        }

        setFlash('success', 'Settings saved successfully.');

    } catch (Throwable $e) {
        setFlash('error', 'Unable to save settings: ' . $e->getMessage());
    }

    header('Location: index.php?page=settings');
    exit;
}

// ============================================================
// HELPER: HANDLE QR UPLOAD
// ============================================================
function handleQrUpload(array $file, string $settingKey, string $uploadDir, string $uploadUrl, PDO $pdo): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("$settingKey upload failed.");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("$settingKey must not exceed 5MB.");
    }

    $tmpName = $file['tmp_name'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmpName);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new Exception("Invalid $settingKey format. Use JPG, PNG, or WEBP.");
    }

    $extension = $allowed[$mime];

    $filename = $settingKey . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

    $destination = rtrim($uploadDir, '/') . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new Exception("Unable to save $settingKey.");
    }

    // Get old QR
    $oldStmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
    $oldStmt->execute([$settingKey]);
    $oldQr = $oldStmt->fetchColumn();

    // Save new QR path
    $qrPath = $uploadUrl . $filename;

    $stmt = $pdo->prepare("
        INSERT INTO settings (`key`, `value`)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = ?
    ");
    $stmt->execute([$settingKey, $qrPath, $qrPath]);

    // Delete old file
    if (!empty($oldQr)) {
        $oldFile = dirname(__DIR__, 2) . '/' . ltrim($oldQr, '/');
        if (is_file($oldFile) && realpath($oldFile) !== realpath($destination)) {
            @unlink($oldFile);
        }
    }

    return $qrPath;
}

// ============================================================
// LOAD SETTINGS
// ============================================================
$settings = [];
$stmt = $pdo->query("SELECT `key`, `value` FROM settings");
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['key']] = $row['value'];
}

// Backwards compatibility
if (empty($settings['gcash_account_name']) && !empty($settings['gcash_name'])) {
    $settings['gcash_account_name'] = $settings['gcash_name'];
}
if (empty($settings['gcash_account_number']) && !empty($settings['gcash_number'])) {
    $settings['gcash_account_number'] = $settings['gcash_number'];
}

// ============================================================
// ESCAPE HELPER
// ============================================================
function s(array $settings, string $key, string $default = ''): string
{
    return htmlspecialchars(
        $settings[$key] ?? $default,
        ENT_QUOTES,
        'UTF-8'
    );
}

// QR URLs
$gcashQrUrl = !empty($settings['gcash_qr'])
    ? APP_URL . '/' . ltrim($settings['gcash_qr'], '/') . '?v=' . time()
    : '';

$maribankQrUrl = !empty($settings['maribank_qr'])
    ? APP_URL . '/' . ltrim($settings['maribank_qr'], '/') . '?v=' . time()
    : '';
?>

<!-- ============================================================ -->
<!-- PAGE HEADER                                                   -->
<!-- ============================================================ -->
<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">System Configuration</div>
    <h2><strong>Settings</strong></h2>
    <p>Manage Studio 94 information and payment settings.</p>
  </div>
  <div class="page-banner-art">⚙️</div>
</div>

<form method="POST" action="index.php?page=settings" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="grid-2">

        <!-- ==================================================
             STUDIO SETTINGS
        =================================================== -->
        <div class="card">
            <div class="card-title"><strong>Studio Information</strong></div>

            <div class="form-group">
                <label for="studio_name"><strong>Studio Name</strong></label>
                <input type="text" id="studio_name" name="studio_name"
                       value="<?= s($settings, 'studio_name', 'Studio 94') ?>" required>
            </div>

            <div class="form-group">
                <label for="studio_address"><strong>Address</strong></label>
                <input type="text" id="studio_address" name="studio_address"
                       value="<?= s($settings, 'studio_address') ?>">
            </div>

            <div class="form-group">
                <label for="studio_email"><strong>Contact Email</strong></label>
                <input type="email" id="studio_email" name="studio_email"
                       value="<?= s($settings, 'studio_email') ?>">
            </div>

            <div class="form-group">
                <label for="studio_phone"><strong>Contact Phone</strong></label>
                <input type="tel" id="studio_phone" name="studio_phone"
                       value="<?= s($settings, 'studio_phone') ?>">
            </div>
        </div>

        <!-- ==================================================
             BUSINESS HOURS
        =================================================== -->
        <div class="card">
            <div class="card-title"><strong>Business Hours</strong></div>

            <div class="form-group">
                <label for="hours_weekday"><strong>Monday – Friday</strong></label>
                <input type="text" id="hours_weekday" name="hours_weekday"
                       value="<?= s($settings, 'hours_weekday', '9:00 AM – 7:00 PM') ?>">
            </div>

            <div class="form-group">
                <label for="hours_saturday"><strong>Saturday</strong></label>
                <input type="text" id="hours_saturday" name="hours_saturday"
                       value="<?= s($settings, 'hours_saturday', '9:00 AM – 5:00 PM') ?>">
            </div>

            <div class="form-group">
                <label for="hours_sunday"><strong>Sunday</strong></label>
                <input type="text" id="hours_sunday" name="hours_sunday"
                       value="<?= s($settings, 'hours_sunday', '10:00 AM – 3:00 PM') ?>">
            </div>
        </div>

        <!-- ==================================================
             GCASH PAYMENT
        =================================================== -->
        <div class="card" style="border:2px solid #2ECC71;">
            <div class="card-title" style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:20px;">💚</span>
                <strong>GCash Payment Settings</strong>
            </div>

            <div class="form-group">
                <label for="gcash_account_name"><strong>GCash Account Name</strong></label>
                <input type="text" id="gcash_account_name" name="gcash_account_name"
                       value="<?= s($settings, 'gcash_account_name') ?>"
                       placeholder="Enter GCash account name">
            </div>

            <div class="form-group">
                <label for="gcash_account_number"><strong>GCash Number</strong></label>
                <input type="tel" id="gcash_account_number" name="gcash_account_number"
                       value="<?= s($settings, 'gcash_account_number') ?>"
                       placeholder="09XXXXXXXXX" maxlength="13">
            </div>
        </div>

        <!-- ==================================================
             GCASH QR
        =================================================== -->
        <div class="card" style="border:2px solid #2ECC71;">
            <div class="card-title" style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:20px;">📱</span>
                <strong>GCash QR Code</strong>
            </div>

            <div class="form-group">
                <label for="gcash_qr"><strong>Upload GCash QR Code</strong></label>
                <input type="file" id="gcash_qr" name="gcash_qr"
                       accept="image/png,image/jpeg,image/webp">
                <small>JPG, PNG, WEBP. Max 5MB.</small>
            </div>

            <?php if (!empty($gcashQrUrl)): ?>
                <div style="margin-top:15px;padding:15px;border:1px solid #ddd;border-radius:10px;text-align:center;background:#fafafa;">
                    <div style="font-weight:600;margin-bottom:10px;">Current GCash QR</div>
                    <img src="<?= htmlspecialchars($gcashQrUrl) ?>" alt="GCash QR"
                         style="max-width:260px;width:100%;border-radius:8px;border:1px solid #e5e7eb;padding:8px;background:white;">
                </div>
            <?php else: ?>
                <div style="padding:15px;border:1px dashed #ccc;border-radius:10px;text-align:center;color:#777;">
                    No GCash QR uploaded yet.
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================================================
             MARIBANK PAYMENT
        =================================================== -->
        <div class="card" style="border:2px solid #1E40AF;">
            <div class="card-title" style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:20px;">🏦</span>
                <strong>Maribank Payment Settings</strong>
            </div>

            <div class="form-group">
                <label for="maribank_account_name"><strong>Maribank Account Name</strong></label>
                <input type="text" id="maribank_account_name" name="maribank_account_name"
                       value="<?= s($settings, 'maribank_account_name') ?>"
                       placeholder="Enter Maribank account name">
            </div>

            <div class="form-group">
                <label for="maribank_account_number"><strong>Maribank Number</strong></label>
                <input type="tel" id="maribank_account_number" name="maribank_account_number"
                       value="<?= s($settings, 'maribank_account_number') ?>"
                       placeholder="Enter Maribank account number" maxlength="20">
            </div>
        </div>

        <!-- ==================================================
             MARIBANK QR
        =================================================== -->
        <div class="card" style="border:2px solid #1E40AF;">
            <div class="card-title" style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:20px;">📱</span>
                <strong>Maribank QR Code</strong>
            </div>

            <div class="form-group">
                <label for="maribank_qr"><strong>Upload Maribank QR Code</strong></label>
                <input type="file" id="maribank_qr" name="maribank_qr"
                       accept="image/png,image/jpeg,image/webp">
                <small>JPG, PNG, WEBP. Max 5MB.</small>
            </div>

            <?php if (!empty($maribankQrUrl)): ?>
                <div style="margin-top:15px;padding:15px;border:1px solid #ddd;border-radius:10px;text-align:center;background:#fafafa;">
                    <div style="font-weight:600;margin-bottom:10px;">Current Maribank QR</div>
                    <img src="<?= htmlspecialchars($maribankQrUrl) ?>" alt="Maribank QR"
                         style="max-width:260px;width:100%;border-radius:8px;border:1px solid #e5e7eb;padding:8px;background:white;">
                </div>
            <?php else: ?>
                <div style="padding:15px;border:1px dashed #ccc;border-radius:10px;text-align:center;color:#777;">
                    No Maribank QR uploaded yet.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ======================================================
         SAVE BUTTON
    ======================================================= -->
    <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px;">
        <a href="index.php?page=dashboard" class="btn-ghost">Cancel</a>
        <button class="btn-primary" type="submit">💾 Save Settings</button>
    </div>

</form>