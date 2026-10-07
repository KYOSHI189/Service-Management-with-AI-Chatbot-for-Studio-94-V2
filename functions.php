<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Core Functions (COMPLETE)
// ============================================================

require_once __DIR__ . '/config.php';

// ============================================================
// ===== USE STATEMENTS
// ============================================================

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// ============================================================
// ===== DATABASE CONNECTION
// ============================================================

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {

            $dsn = 'mysql:host=' . DB_HOST .
                   (defined('DB_PORT') && DB_PORT ? ';port=' . DB_PORT : '') .
                   ';dbname=' . DB_NAME .
                   ';charset=' . DB_CHARSET;

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Auto-migrate missing columns/tables
            ensureDatabaseSchema($pdo);

        } catch (PDOException $e) {

            die(
                '<div style="
                    font-family:monospace;
                    padding:20px;
                    background:#fee;
                    border:1px solid #f00;
                    margin:20px;
                    border-radius:8px;
                ">
                    <strong>Database Connection Error:</strong><br>' .
                    htmlspecialchars($e->getMessage()) .
                    '<br><br>
                    Please run
                    <a href="' . APP_URL . '/sql/install.php">
                        sql/install.php
                    </a>
                    to set up the database.
                </div>'
            );
        }
    }

    return $pdo;
}

/**
 * Automatically checks and adds any missing columns to existing tables
 */
function ensureDatabaseSchema(PDO $pdo): void
{
    static $migrated = false;
    if ($migrated) return;
    $migrated = true;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if (!$stmt || !$stmt->fetch()) {
            return;
        }

        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

        $needed = [
            'failed_login_attempts' => "ALTER TABLE users ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0",
            'locked_until'          => "ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL",
            'last_login'             => "ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL",
            'email_verified'         => "ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0",
            'google_id'              => "ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL",
            'verification_token'     => "ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) DEFAULT NULL",
            'verification_expires'   => "ALTER TABLE users ADD COLUMN verification_expires DATETIME DEFAULT NULL",
            'mfa_enabled'            => "ALTER TABLE users ADD COLUMN mfa_enabled TINYINT(1) DEFAULT 0",
            'mfa_secret'             => "ALTER TABLE users ADD COLUMN mfa_secret VARCHAR(255) DEFAULT NULL",
            'mfa_backup_codes'       => "ALTER TABLE users ADD COLUMN mfa_backup_codes TEXT DEFAULT NULL",
            'mfa_verified_at'        => "ALTER TABLE users ADD COLUMN mfa_verified_at DATETIME DEFAULT NULL"
        ];

        foreach ($needed as $col => $alterSql) {
            if (!in_array($col, $cols, true)) {
                $pdo->exec($alterSql);
            }
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // --- 2. Check bookings table ---
        $bStmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
        if ($bStmt && $bStmt->fetch()) {
            $bCols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
            $bNeeded = [
                'duration_minutes'   => "ALTER TABLE bookings ADD COLUMN duration_minutes INT DEFAULT NULL",
                'staff_notes'        => "ALTER TABLE bookings ADD COLUMN staff_notes TEXT DEFAULT NULL",
                'booking_session_id' => "ALTER TABLE bookings ADD COLUMN booking_session_id VARCHAR(50) DEFAULT NULL",
                'created_by'         => "ALTER TABLE bookings ADD COLUMN created_by VARCHAR(50) DEFAULT 'client'",
                'number_of_people'   => "ALTER TABLE bookings ADD COLUMN number_of_people INT DEFAULT 1"
            ];
            foreach ($bNeeded as $bCol => $bSql) {
                if (!in_array($bCol, $bCols, true)) {
                    $pdo->exec($bSql);
                }
            }
            try {
                $pdo->exec("ALTER TABLE bookings MODIFY COLUMN booking_ref VARCHAR(50) NOT NULL");
            } catch (Throwable $e) {}
            try {
                $pdo->exec("ALTER TABLE bookings MODIFY COLUMN status ENUM('Awaiting Approval','Approved (Unpaid)','Deposit Paid','Confirmed','In Progress','Completed','Cancelled','Rejected') NOT NULL DEFAULT 'Awaiting Approval'");
            } catch (Throwable $e) {}
        }

        // --- 3. Check payments table ---
        $pStmt = $pdo->query("SHOW TABLES LIKE 'payments'");
        if ($pStmt && $pStmt->fetch()) {
            $pCols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('refund_amount', $pCols, true)) {
                $pdo->exec("ALTER TABLE payments ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT NULL");
            }
            try {
                $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_ref VARCHAR(50) NOT NULL");
            } catch (Throwable $e) {}
            try {
                $pdo->exec("ALTER TABLE payments MODIFY COLUMN type ENUM('RESERVATION','BALANCE','DEPOSIT','FULL') NOT NULL DEFAULT 'RESERVATION'");
            } catch (Throwable $e) {}
            try {
                $pdo->exec("ALTER TABLE payments MODIFY COLUMN status ENUM('UNPAID','PENDING','PAID','REJECTED','REFUNDED','CANCELLED') DEFAULT 'UNPAID'");
            } catch (Throwable $e) {}
        }

        // --- 4. Check packages table ---
        $pkgStmt = $pdo->query("SHOW TABLES LIKE 'packages'");
        if ($pkgStmt && $pkgStmt->fetch()) {
            $pkgCols = $pdo->query("SHOW COLUMNS FROM packages")->fetchAll(PDO::FETCH_COLUMN);
            $pkgNeeded = [
                'duration_minutes'      => "ALTER TABLE packages ADD COLUMN duration_minutes INT DEFAULT NULL",
                'print_inclusions'     => "ALTER TABLE packages ADD COLUMN print_inclusions TEXT DEFAULT NULL",
                'photographer_included' => "ALTER TABLE packages ADD COLUMN photographer_included TINYINT(1) DEFAULT 0"
            ];
            foreach ($pkgNeeded as $pkgCol => $pkgSql) {
                if (!in_array($pkgCol, $pkgCols, true)) {
                    $pdo->exec($pkgSql);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[STUDIO94] Schema auto-migration notice: ' . $e->getMessage());
    }
}

// ============================================================
// ===== SESSION MANAGEMENT
// ============================================================

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {

        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }
}

function isLoggedIn(): bool
{
    startSession();

    return isset($_SESSION['user_id'])
        && isset($_SESSION['role']);
}

function currentUser(): ?array
{
    startSession();

    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id'    => $_SESSION['user_id'] ?? 0,
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['role'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? '',
    ];
}

function requireLogin(): void
{
    if (!isLoggedIn()) {

        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * ============================================================
 * requireRole
 * - Accepts both string and array
 * - Case-insensitive matching
 * - Trims whitespace
 * - Redirects to dashboard (not login) if unauthorized
 * ============================================================
 */
function requireRole(string|array $roles): void
{
    requireLogin();

    $user = currentUser();

    if (!$user) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_map(
        fn($r) => strtolower(trim((string)$r)),
        $allowed
    );

    $userRole = strtolower(trim((string)$user['role']));

    if (!in_array($userRole, $allowed, true)) {
        header('Location: ' . APP_URL . '/index.php?page=dashboard');
        exit;
    }
}

function redirectToDashboard(string $role): void
{
    $routes = [

        'admin' =>
            APP_URL . '/index.php?page=dashboard&role=admin',

        'staff' =>
            APP_URL . '/index.php?page=dashboard&role=staff',

        'client' =>
            APP_URL . '/index.php?page=dashboard&role=client',
    ];

    header(
        'Location: ' .
        ($routes[$role] ?? APP_URL . '/login.php')
    );

    exit;
}

// ============================================================
// ===== CSRF PROTECTION
// ============================================================

function csrfToken(): string
{
    startSession();

    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return
        '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals(csrfToken(), $token)) {

        http_response_code(403);

        die('Invalid CSRF token.');
    }
}

// ============================================================
// ===== INPUT SANITIZATION
// ============================================================

function clean(mixed $val): string
{
    return htmlspecialchars(
        trim((string)$val),
        ENT_QUOTES,
        'UTF-8'
    );
}

function cleanInt(mixed $val): int
{
    return (int)filter_var(
        $val,
        FILTER_SANITIZE_NUMBER_INT
    );
}

function cleanFloat(mixed $val): float
{
    return (float)filter_var(
        $val,
        FILTER_SANITIZE_NUMBER_FLOAT,
        FILTER_FLAG_ALLOW_FRACTION
    );
}

// ============================================================
// ===== FLASH MESSAGES
// ============================================================

function setFlash(string $type, string $msg): void
{
    startSession();

    $_SESSION['flash'] = [
        'type' => $type,
        'msg'  => $msg
    ];
}

function getFlash(): ?array
{
    startSession();

    if (isset($_SESSION['flash'])) {

        $f = $_SESSION['flash'];

        unset($_SESSION['flash']);

        return $f;
    }

    return null;
}

function showFlash(): string
{
    $f = getFlash();

    if (!$f) {
        return '';
    }

    $type =
        $f['type'] === 'success'
            ? 'success'
            : (
                $f['type'] === 'error'
                    ? 'error'
                    : 'warning'
            );

    return
        '<div class="alert alert-' .
        $type .
        '">' .
        clean($f['msg']) .
        '</div>';
}

// ============================================================
// ===== DATE & TIME HELPERS
// ============================================================

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);

    if ($diff < 60) {
        return 'Just now';
    }

    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }

    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }

    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }

    return date('M j', strtotime($datetime));
}

function formatDate(string $date): string
{
    return date('M j, Y', strtotime($date));
}

function formatDateTime(string $datetime): string
{
    return date('M j, Y h:i A', strtotime($datetime));
}

/**
 * ============================================================
 * FIXED: formatMoney
 * - 2 decimals kung may fractional part (₱199.50)
 * - 0 decimals kung whole number (₱399)
 * ============================================================
 */
function formatMoney(int|float $amount): string
{
    $decimals = (round($amount, 2) == round($amount)) ? 0 : 2;
    return '₱' . number_format($amount, $decimals);
}

// ============================================================
// ===== STATUS BADGE
// ============================================================

function statusBadge(string $status): string
{
    $map = [

        'Completed'         => 'badge-green',
        'Deposit Paid'      => 'badge-green',
        'Confirmed'         => 'badge-green',

        'Awaiting Approval' => 'badge-amber',
        'Approved (Unpaid)' => 'badge-amber',

        'PENDING'           => 'badge-amber',
        'PAID'              => 'badge-green',
        'UNPAID'            => 'badge-gray',
        'REJECTED'          => 'badge-red',

        'Cancelled'         => 'badge-red',

        'Pending'           => 'badge-amber',
        'Approved'          => 'badge-green',
        'Verified'          => 'badge-green',
        'Unverified'        => 'badge-amber',
        'Failed'            => 'badge-red',

        'In Stock'          => 'badge-green',
        'Low Stock'         => 'badge-amber',
        'Out of Stock'      => 'badge-red',
    ];

    $cls = $map[$status] ?? 'badge-gray';

    return
        '<span class="badge ' .
        $cls .
        '">' .
        clean($status) .
        '</span>';
}

// ============================================================
// ===== NOTIFICATION FUNCTIONS
// ============================================================

function addNotification(
    int $userId,
    string $title,
    string $message,
    string $type = 'system',
    string $icon = '🔔',
    ?string $link = null
): bool {

    try {

        $pdo = db();

        $stmt = $pdo->prepare("
            INSERT INTO notifications
            (
                user_id,
                type,
                title,
                message,
                link,
                icon,
                is_read,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                0,
                NOW()
            )
        ");

        $success = $stmt->execute([
            $userId,
            $type,
            $title,
            $message,
            $link,
            $icon
        ]);

        if ($success) {
            sendPushNotification(
                $userId,
                $title,
                $message,
                $link
            );
        }

        return $success;

    } catch (Throwable $e) {

        error_log(
            'addNotification error: ' .
            $e->getMessage()
        );

        return false;
    }
}

function addNotificationByRole(
    string $role,
    string $title,
    string $message,
    string $type = 'system',
    string $icon = '🔔',
    ?string $link = null
): int {

    try {

        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE role = ?
        ");

        $stmt->execute([$role]);

        $users = $stmt->fetchAll();

        $count = 0;

        foreach ($users as $u) {

            if (
                addNotification(
                    (int)$u['id'],
                    $title,
                    $message,
                    $type,
                    $icon,
                    $link
                )
            ) {
                $count++;
            }
        }

        return $count;

    } catch (Throwable $e) {

        error_log(
            'addNotificationByRole error: ' .
            $e->getMessage()
        );

        return 0;
    }
}

function getUnreadCount(int $userId): int
{
    try {

        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
              AND is_read = 0
        ");

        $stmt->execute([$userId]);

        return (int)$stmt->fetchColumn();

    } catch (Throwable $e) {

        error_log(
            'getUnreadCount error: ' .
            $e->getMessage()
        );

        return 0;
    }
}

function getNotifications(
    int $userId,
    int $limit = 20
): array {

    try {

        $limit = max(1, min(100, (int)$limit));

        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT
                id,
                user_id,
                type,
                title,
                message,
                link,
                icon,
                is_read,
                created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");

        $stmt->execute([$userId]);

        return $stmt->fetchAll();

    } catch (Throwable $e) {

        error_log(
            'getNotifications error: ' .
            $e->getMessage()
        );

        return [];
    }
}

function getNotification(
    int $notificationId,
    int $userId
): ?array {

    try {

        $stmt = db()->prepare("
            SELECT
                id,
                user_id,
                type,
                title,
                message,
                link,
                icon,
                is_read,
                created_at
            FROM notifications
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $notificationId,
            $userId
        ]);

        $notification = $stmt->fetch();

        return $notification ?: null;

    } catch (Throwable $e) {

        error_log(
            'getNotification error: ' .
            $e->getMessage()
        );

        return null;
    }
}

function markNotificationRead(
    int $notificationId,
    int $userId
): bool {

    try {

        $stmt = db()->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = ?
              AND user_id = ?
        ");

        return $stmt->execute([
            $notificationId,
            $userId
        ]);

    } catch (Throwable $e) {

        error_log(
            'markNotificationRead error: ' .
            $e->getMessage()
        );

        return false;
    }
}

function markAllNotificationsRead(
    int $userId
): bool {

    try {

        $stmt = db()->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
              AND is_read = 0
        ");

        return $stmt->execute([$userId]);

    } catch (Throwable $e) {

        error_log(
            'markAllNotificationsRead error: ' .
            $e->getMessage()
        );

        return false;
    }
}

function deleteNotification(
    int $notificationId,
    int $userId
): bool {

    try {

        $stmt = db()->prepare("
            DELETE FROM notifications
            WHERE id = ?
              AND user_id = ?
        ");

        return $stmt->execute([
            $notificationId,
            $userId
        ]);

    } catch (Throwable $e) {

        error_log(
            'deleteNotification error: ' .
            $e->getMessage()
        );

        return false;
    }
}

function deleteReadNotifications(
    int $userId
): bool {

    try {

        $stmt = db()->prepare("
            DELETE FROM notifications
            WHERE user_id = ?
              AND is_read = 1
        ");

        return $stmt->execute([$userId]);

    } catch (Throwable $e) {

        error_log(
            'deleteReadNotifications error: ' .
            $e->getMessage()
        );

        return false;
    }
}

// ============================================================
// ===== NOTIFICATION SHORTCUTS
// ============================================================

function notifyNewBooking(string $clientName = ''): int
{
    $message = $clientName !== ''
        ? $clientName . ' submitted a new booking that needs approval.'
        : 'A new booking is waiting for approval.';

    return addNotificationByRole(
        'staff',
        'New Booking',
        $message,
        'booking',
        '📅',
        'index.php?page=bookings'
    );
}

function notifyPaymentSubmitted(string $clientName = ''): int
{
    $message = $clientName !== ''
        ? $clientName . ' submitted a payment for verification.'
        : 'A client submitted a payment for verification.';

    return addNotificationByRole(
        'staff',
        'Payment Submitted',
        $message,
        'payment',
        '💳',
        'index.php?page=payments'
    );
}

function notifyNewFeedback(): int
{
    return addNotificationByRole(
        'admin',
        'New Customer Feedback',
        'A client submitted new feedback.',
        'feedback',
        '⭐',
        'index.php?page=feedback'
    );
}

function notifyUrgentFeedback(): int
{
    return addNotificationByRole(
        'admin',
        'Urgent Feedback',
        'A client submitted a low-rating feedback that needs attention.',
        'urgent_feedback',
        '⚠️',
        'index.php?page=feedback'
    );
}

function notifyNewClient(string $clientName = ''): int
{
    $message = $clientName !== ''
        ? $clientName . ' registered as a new client.'
        : 'A new client has registered in Studio 94.';

    return addNotificationByRole(
        'admin',
        'New Client Registered',
        $message,
        'new_client',
        '👤',
        'index.php?page=clients'
    );
}

// ============================================================
// ===== VAPID / PUSH NOTIFICATIONS
// ============================================================

function getVapidKeys(): array
{
    return [

        'publicKey' =>
            getenv('VAPID_PUBLIC_KEY')
            ?: ($_ENV['VAPID_PUBLIC_KEY'] ?? ''),

        'privateKey' =>
            getenv('VAPID_PRIVATE_KEY')
            ?: ($_ENV['VAPID_PRIVATE_KEY'] ?? ''),

        'subject' =>
            getenv('VAPID_SUBJECT')
            ?: (
                $_ENV['VAPID_SUBJECT']
                ?? 'mailto:your-email@example.com'
            ),
    ];
}

function hasVapidKeys(): bool
{
    $keys = getVapidKeys();

    return !empty($keys['publicKey'])
        && !empty($keys['privateKey']);
}

function sendPushNotification(
    int $userId,
    string $title,
    string $message,
    ?string $link = null
): void {

    if (!class_exists('Minishlink\WebPush\WebPush')) {
        return;
    }

    try {

        $pdo = db();

        $vapid = getVapidKeys();

        if (
            empty($vapid['publicKey']) ||
            empty($vapid['privateKey'])
        ) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT
                endpoint,
                auth_key,
                p256dh_key
            FROM push_subscriptions
            WHERE user_id = ?
        ");

        $stmt->execute([$userId]);

        $subscriptions = $stmt->fetchAll();

        if (empty($subscriptions)) {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject'    => $vapid['subject'],
                'publicKey'  => $vapid['publicKey'],
                'privateKey' => $vapid['privateKey'],
            ]
        ];

        $webPush = new WebPush($auth);

        $payload = json_encode([
            'title' => $title,
            'body'  => $message,
            'icon'  => '/assets/icon.png',
            'badge' => '/assets/badge.png',
            'url'   => $link
                ?: '/index.php?page=notifications',
        ]);

        foreach ($subscriptions as $sub) {

            $webPush->queueNotification(

                Subscription::create([
                    'endpoint'  => $sub['endpoint'],
                    'authToken' => $sub['auth_key'],
                    'publicKey' => $sub['p256dh_key'],
                ]),

                $payload
            );
        }

        foreach ($webPush->flush() as $report) {

            if (!$report->isSuccess()) {

                try {

                    $deleteStmt = $pdo->prepare("
                        DELETE FROM push_subscriptions
                        WHERE endpoint = ?
                    ");

                    $deleteStmt->execute([
                        $report->getEndpoint()
                    ]);

                } catch (Throwable $e) {

                    error_log(
                        'Push subscription cleanup error: ' .
                        $e->getMessage()
                    );
                }
            }
        }

    } catch (Throwable $e) {

        error_log(
            'sendPushNotification error: ' .
            $e->getMessage()
        );
    }
}

// ============================================================
// ===== USER FUNCTIONS
// ============================================================

function getUserById(int $userId): ?array
{
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function getUserByEmail(string $email): ?array
{
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function getUserName(int $userId): string
{
    $user = getUserById($userId);

    return $user
        ? $user['name']
        : 'Unknown User';
}

function getUserRole(int $userId): string
{
    $user = getUserById($userId);

    return $user
        ? $user['role']
        : 'guest';
}

// ============================================================
// ===== LOYALTY HELPERS — CORRECTED TIERS (2, 4, 7, 10)
// ============================================================

function getLoyaltyTier(int $bookings): string
{
    if ($bookings >= 10) return '🏆 10th Booking (50% OFF)';
    if ($bookings >= 7)  return '⭐ 7th Booking (+1 BACKDROP)';
    if ($bookings >= 4)  return '⭐ 4th Booking (+1 PRINT OUT)';
    if ($bookings >= 2)  return '⭐ 2nd Booking (+5 MINUTES)';
    return 'No tier yet';
}

function getLoyaltyRewards(int $bookings): array
{
    $earned = [];
    
    if ($bookings >= 2)  $earned[] = '+5 MINUTES';
    if ($bookings >= 4)  $earned[] = '+1 PRINT OUT';
    if ($bookings >= 7)  $earned[] = '+1 BACKDROP';
    if ($bookings >= 10) $earned[] = '50% OFF';
    
    return $earned;
}

function getLoyaltyProgress(int $bookings): array
{
    $tiers = [
        2  => ['label' => '+5 MINUTES',   'next' => '1 more booking to unlock +5 MINUTES'],
        4  => ['label' => '+1 PRINT OUT', 'next' => '1 more booking to unlock +1 PRINT OUT'],
        7  => ['label' => '+1 BACKDROP',  'next' => '1 more booking to unlock +1 BACKDROP'],
        10 => ['label' => '50% OFF',      'next' => '1 more booking to unlock 50% OFF'],
    ];
    
    if ($bookings >= 10) {
        return [
            'pct'   => 100,
            'next'  => '🏆 Maximum tier achieved!',
            'label' => '10 / 10 bookings',
        ];
    }
    
    $nextTier = 2;
    $prevTier = 0;
    
    foreach ($tiers as $count => $info) {
        if ($bookings >= $count) {
            $prevTier = $count;
        } else {
            $nextTier = $count;
            break;
        }
    }
    
    $progressInTier = $bookings - $prevTier;
    $tierSize = $nextTier - $prevTier;
    $pct = $prevTier === 0 
        ? round(($bookings / $nextTier) * 100)
        : round(($progressInTier / $tierSize) * 100);
    
    return [
        'pct'   => max(0, min(100, $pct)),
        'next'  => $tiers[$nextTier]['next'],
        'label' => $bookings . ' / ' . $nextTier . ' bookings',
    ];
}

function getClientBookingCount(int $userId): int
{
    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM bookings
        WHERE user_id = ?
          AND status IN (
              'Completed',
              'Deposit Paid',
              'Confirmed'
          )
    ");

    $stmt->execute([$userId]);

    return (int)$stmt->fetchColumn();
}

// ============================================================
// ===== BOOKING HELPERS
// ============================================================

function extractHoursFromDuration(string $duration): int
{
    if (empty($duration)) {
        return 1;
    }

    if (preg_match('/(\d+)\s*min/i', $duration, $matches)) {
        $minutes = (int)$matches[1];
        return max(1, (int)ceil($minutes / 60));
    }

    if (preg_match('/(\d+)\s*hour\s*(\d+)\s*min/i', $duration, $matches)) {
        $hours   = (int)$matches[1];
        $minutes = (int)$matches[2];
        return $hours + (int)ceil($minutes / 60);
    }

    if (preg_match('/(\d+)\s*-\s*(\d+)\s*hour/i', $duration, $matches)) {
        return (int)$matches[1];
    }

    if (preg_match('/(\d+)\s*hour/i', $duration, $matches)) {
        return max(1, (int)$matches[1]);
    }

    if (preg_match('/(\d+)/', $duration, $matches)) {
        return max(1, (int)$matches[1]);
    }

    return 1;
}

function checkBookingConflict(
    string $date,
    string $time,
    ?int $excludeId = null,
    ?int $durationHours = 1
): array {

    $pdo = db();

    if (empty($durationHours) || $durationHours <= 0) {
        $durationHours = 1;
    }

    $startTime = strtotime($time);
    $endTime   = strtotime("+{$durationHours} hours", $startTime);

    $sql = "
        SELECT
            b.*,
            p.duration AS package_duration,
            u.name AS client_name
        FROM bookings b
        JOIN packages p ON b.package_id = p.id
        JOIN users u ON b.user_id = u.id
        WHERE b.date = ?
          AND b.status NOT IN ('Cancelled', 'Rejected')
    ";

    $params = [$date];

    if ($excludeId !== null) {
        $sql .= " AND b.id != ?";
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $existingBookings = $stmt->fetchAll();
    $conflicts = [];

    foreach ($existingBookings as $existing) {

        $existingDuration = extractHoursFromDuration(
            $existing['package_duration'] ?? '1 hour'
        );

        if ($existingDuration <= 0) {
            $existingDuration = 1;
        }

        $existingStart = strtotime($existing['time']);
        $existingEnd   = strtotime("+{$existingDuration} hours", $existingStart);

        if ($startTime < $existingEnd && $endTime > $existingStart) {
            $conflicts[] = $existing;
        }
    }

    return $conflicts;
}

function isSlotAvailable(
    string $date,
    string $time,
    int $packageId,
    int $excludeBookingId = 0
): bool {

    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM bookings
        WHERE date = ?
          AND time = ?
          AND package_id = ?
          AND status NOT IN ('Cancelled', 'Rejected')
          AND id != ?
    ");

    $stmt->execute([
        $date,
        $time,
        $packageId,
        $excludeBookingId
    ]);

    return (int)$stmt->fetchColumn() === 0;
}

function getBookingStatusBadge(string $status): string
{
    $colors = [
        'pending'   => 'badge-warning',
        'confirmed' => 'badge-info',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
    ];

    return $colors[$status] ?? 'badge-secondary';
}

// ============================================================
// ===== PACKAGE FUNCTIONS
// ============================================================

function getPackageById(int $packageId): ?array
{
    $stmt = db()->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$packageId]);
    $package = $stmt->fetch();

    return $package ?: null;
}

function getPackagePrice(int $packageId): float
{
    $package = getPackageById($packageId);

    return $package
        ? (float)$package['price']
        : 0;
}

function getPackageDuration(int $packageId): string
{
    $package = getPackageById($packageId);

    return $package
        ? $package['duration']
        : '1 hour';
}

// ============================================================
// ===== PAGINATION
// ============================================================

function paginate(
    int $total,
    int $perPage,
    int $current
): array {

    $perPage = max(1, $perPage);
    $pages   = (int)ceil($total / $perPage);
    $pages   = max(1, $pages);
    $current = max(1, min($current, $pages));

    return [
        'total'   => $total,
        'pages'   => $pages,
        'current' => $current,
        'offset'  => ($current - 1) * $perPage,
        'limit'   => $perPage,
    ];
}

// ============================================================
// ===== CSV EXPORT
// ============================================================

function outputCSV(
    array $headers,
    array $rows,
    string $filename
): void {

    header('Content-Type: text/csv');

    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '_' .
        date('Y-m-d') .
        '.csv"'
    );

    $out = fopen('php://output', 'w');

    fputcsv($out, $headers);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    fclose($out);

    exit;
}

// ============================================================
// ===== UPLOAD FUNCTIONS
// ============================================================

function uploadFile(
    array $file,
    string $targetDir,
    array $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp']
): array {

    if (
        !isset($file['error']) ||
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        return ['success' => false, 'error' => 'Upload failed'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedTypes, true)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory'];
        }
    }

    $filename = uniqid('', true) . '.' . $ext;
    $targetPath = rtrim($targetDir, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Failed to move file'];
}

// ============================================================
// ===== INVENTORY FUNCTIONS
// ============================================================

function getPackageInventory(int $packageId): array
{
    $stmt = db()->prepare("
        SELECT
            pi.*,
            i.name AS inventory_name,
            i.quantity AS current_stock,
            i.threshold,
            i.is_reusable
        FROM package_inventory pi
        JOIN inventory i ON pi.inventory_id = i.id
        WHERE pi.package_id = ?
    ");

    $stmt->execute([$packageId]);

    return $stmt->fetchAll();
}

function deductInventoryOnComplete(
    int $bookingId,
    int $packageId
): array {

    $pdo = db();
    $items = getPackageInventory($packageId);

    $deducted = [];
    $skippedReusable = [];
    $errors = [];
    $details = [];

    if (empty($items)) {
        return [
            'success'         => true,
            'message'         => 'No inventory items to deduct.',
            'deducted'        => [],
            'skippedReusable' => [],
            'details'         => [],
            'errors'          => []
        ];
    }

    try {

        $pdo->beginTransaction();

        foreach ($items as $item) {

            $inventoryId = (int)$item['inventory_id'];
            $requiredQty = (int)$item['quantity'];
            $itemName    = $item['inventory_name'] ?? 'Unknown inventory';

            $checkStmt = $pdo->prepare("
                SELECT id, name, quantity, threshold, is_reusable
                FROM inventory
                WHERE id = ?
                FOR UPDATE
            ");

            $checkStmt->execute([$inventoryId]);
            $inventory = $checkStmt->fetch();

            if (!$inventory) {
                $errors[] = 'Inventory item not found: ' . $itemName;
                continue;
            }

            $currentStock = (int)$inventory['quantity'];
            $isReusable   = (int)$inventory['is_reusable'];

            if ($isReusable === 1) {
                $skippedReusable[] = $itemName;

                $details[] = [
                    'name'            => $itemName,
                    'quantity'        => $requiredQty,
                    'inventory_id'    => $inventoryId,
                    'is_reusable'     => true,
                    'deducted'        => false,
                    'remaining_stock' => $currentStock,
                ];

                continue;
            }

            if ($currentStock < $requiredQty) {
                $errors[] = 'Not enough stock for: ' . $itemName
                    . ' (Available: ' . $currentStock
                    . ', Needed: ' . $requiredQty . ')';
                continue;
            }

            $deductStmt = $pdo->prepare("
                UPDATE inventory
                SET quantity = quantity - ?
                WHERE id = ?
                  AND is_reusable = 0
                  AND quantity >= ?
            ");

            $deductStmt->execute([$requiredQty, $inventoryId, $requiredQty]);

            if ($deductStmt->rowCount() <= 0) {
                $errors[] = 'Unable to deduct inventory: ' . $itemName;
                continue;
            }

            $remainingStock = $currentStock - $requiredQty;

            $deducted[] = $itemName . ' (-' . $requiredQty . ')';

            $details[] = [
                'name'            => $itemName,
                'quantity'        => $requiredQty,
                'inventory_id'    => $inventoryId,
                'is_reusable'     => false,
                'deducted'        => true,
                'remaining_stock' => $remainingStock,
            ];

            $biStmt = $pdo->prepare("
                INSERT INTO booking_inventory
                (booking_id, inventory_id, quantity, used_at)
                VALUES (?, ?, ?, NOW())
            ");

            $biStmt->execute([$bookingId, $inventoryId, $requiredQty]);

            $threshold = (int)$inventory['threshold'];

            if ($remainingStock <= $threshold) {

                $message = $itemName . ' is low on stock. '
                    . $remainingStock . ' left; threshold is '
                    . $threshold . '.';

                addNotificationByRole(
                    'admin',
                    'Low Stock Alert',
                    $message,
                    'inventory',
                    '⚠️',
                    'index.php?page=inventory'
                );

                addNotificationByRole(
                    'staff',
                    'Low Stock Alert',
                    $message,
                    'inventory',
                    '⚠️',
                    'index.php?page=inventory'
                );
            }
        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('deductInventoryOnComplete error: ' . $e->getMessage());

        return [
            'success'         => false,
            'message'         => 'Inventory processing failed: ' . $e->getMessage(),
            'deducted'        => $deducted,
            'skippedReusable' => $skippedReusable,
            'details'         => $details,
            'errors'          => [$e->getMessage()]
        ];
    }

    if (!empty($errors)) {
        return [
            'success'         => false,
            'message'         => implode(' ', $errors),
            'deducted'        => $deducted,
            'skippedReusable' => $skippedReusable,
            'details'         => $details,
            'errors'          => $errors
        ];
    }

    return [
        'success'         => true,
        'message'         => !empty($deducted)
            ? 'Inventory deducted successfully.'
            : 'No consumable inventory was deducted.',
        'deducted'        => $deducted,
        'skippedReusable' => $skippedReusable,
        'details'         => $details,
        'errors'          => []
    ];
}

function restoreInventory(int $bookingId): bool
{
    $pdo = db();

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT inventory_id, quantity
            FROM booking_inventory
            WHERE booking_id = ?
        ");

        $stmt->execute([$bookingId]);
        $items = $stmt->fetchAll();

        if (empty($items)) {
            $pdo->commit();
            return true;
        }

        foreach ($items as $item) {
            $restoreStmt = $pdo->prepare("
                UPDATE inventory
                SET quantity = quantity + ?
                WHERE id = ?
                  AND is_reusable = 0
            ");

            $restoreStmt->execute([
                (int)$item['quantity'],
                (int)$item['inventory_id']
            ]);
        }

        $delStmt = $pdo->prepare("
            DELETE FROM booking_inventory
            WHERE booking_id = ?
        ");

        $delStmt->execute([$bookingId]);

        $pdo->commit();

        return true;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('restoreInventory error: ' . $e->getMessage());

        return false;
    }
}

function checkLowStockAndNotify(): void
{
    try {

        $pdo = db();

        $stmt = $pdo->query("
            SELECT id, name, quantity, threshold
            FROM inventory
            WHERE is_reusable = 0
              AND quantity <= threshold
            ORDER BY quantity ASC, name ASC
        ");

        $lowItems = $stmt->fetchAll();

        if (empty($lowItems)) {
            return;
        }

        $items = [];

        foreach ($lowItems as $item) {
            $items[] = $item['name']
                . ' (' . (int)$item['quantity']
                . ' left, threshold: '
                . (int)$item['threshold'] . ')';
        }

        $message = 'The following consumable inventory items are low: '
            . implode(', ', $items);

        addNotificationByRole(
            'admin',
            'Low Stock Alert',
            $message,
            'inventory',
            '⚠️',
            'index.php?page=inventory'
        );

        addNotificationByRole(
            'staff',
            'Low Stock Alert',
            $message,
            'inventory',
            '⚠️',
            'index.php?page=inventory'
        );

    } catch (Throwable $e) {

        error_log('checkLowStockAndNotify error: ' . $e->getMessage());
    }
}

// ============================================================
// ===== END OF FUNCTIONS
// ============================================================