<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Main Router (PHOTOGRAPHY DESIGN)
// Usage: index.php?page=dashboard
// ============================================================

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ============================================================
// CHECK IF USER IS LOGGED IN
// ============================================================
$isLoggedIn =
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role']);

if (!$isLoggedIn) {
    ob_end_clean();
    header('Location: ' . APP_URL . '/landing.php');
    exit;
}

// ============================================================
// GET USER DATA FROM SESSION
// ============================================================
$user = [
    'id'     => (int)($_SESSION['user_id'] ?? 0),
    'name'   => $_SESSION['user_name'] ?? '',
    'email'  => $_SESSION['user_email'] ?? '',
    'role'   => $_SESSION['role'] ?? '',
    'phone'  => $_SESSION['user_phone'] ?? '',
    'avatar' => $_SESSION['user_avatar'] ?? null,
];

// ============================================================
// REFRESH USER DATA FROM DATABASE
// ============================================================
if (!empty($user['id'])) {
    try {
        $stmt = db()->prepare('
            SELECT avatar, name, email, phone, role, is_active 
            FROM users 
            WHERE id = ? 
            LIMIT 1
        ');
        $stmt->execute([$user['id']]);
        $fresh = $stmt->fetch();

        if ($fresh) {
            if ((int)$fresh['is_active'] !== 1) {
                ob_end_clean();
                session_unset();
                session_destroy();
                header('Location: ' . APP_URL . '/login.php');
                exit;
            }

            $_SESSION['user_avatar'] = $fresh['avatar'];
            $_SESSION['user_name']   = $fresh['name'];
            $_SESSION['user_email']  = $fresh['email'];
            $_SESSION['user_phone']  = $fresh['phone'];
            $_SESSION['role']        = $fresh['role'];

            $user['avatar'] = $fresh['avatar'];
            $user['name']   = $fresh['name'];
            $user['email']  = $fresh['email'];
            $user['phone']  = $fresh['phone'];
            $user['role']   = $fresh['role'];
        }
    } catch (Throwable $e) {
        error_log('Session refresh error: ' . $e->getMessage());
    }
}

// ============================================================
// ROLE NORMALIZATION & VALIDATION
// ============================================================
$role = strtolower(trim($user['role']));
$validRoles = ['admin', 'staff', 'client'];

if (!in_array($role, $validRoles, true)) {
    ob_end_clean();
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// ============================================================
// ALLOWED PAGES PER ROLE
// ============================================================
$rolePages = [
    'admin' => [
        'dashboard', 'bookings', 'payments', 'sales', 'clients',
        'inventory', 'packages', 'loyalty-cards', 'staff',
        'feedback', 'reports', 'notifications', 'settings', 'walkin',
        'profile',
    ],
    'staff' => [
        'dashboard', 'bookings', 'payments', 'schedule', 'clients',
        'feedback', 'inventory', 'upload', 'notifications',
        'profile', 'walkin',
    ],
    'client' => [
        'dashboard', 'booking', 'bookings', 'payments', 'feedback',
        'photos', 'notifications', 'profile',
    ],
];

// ============================================================
// GET REQUESTED PAGE
// ============================================================
$page = preg_replace(
    '/[^a-z0-9\-]/',
    '',
    strtolower($_GET['page'] ?? 'dashboard')
);

if ($page === '') {
    $page = 'dashboard';
}

$allowed = $rolePages[$role] ?? [];

if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}

// ============================================================
// RESOLVE PAGE FILE
// ============================================================
$pageFile = null;
$triedPaths = [];

$rolePath = __DIR__ . '/pages/' . $role . '/' . $page . '.php';
$triedPaths[] = $rolePath;

if (file_exists($rolePath)) {
    $pageFile = $rolePath;
}

if ($pageFile === null) {
    $commonPath = __DIR__ . '/pages/' . $page . '.php';
    $triedPaths[] = $commonPath;

    if (file_exists($commonPath)) {
        $pageFile = $commonPath;
    }
}

if ($pageFile === null) {
    $otherRoles = ['admin', 'staff', 'client'];
    foreach ($otherRoles as $r) {
        if ($r === $role) continue;

        $fallbackPath = __DIR__ . '/pages/' . $r . '/' . $page . '.php';
        $triedPaths[] = $fallbackPath;

        if (file_exists($fallbackPath)) {
            $pageFile = $fallbackPath;
            break;
        }
    }
}

if ($pageFile === null && $page === 'walkin') {
    $sharedWalkin = __DIR__ . '/pages/walkin.php';
    $triedPaths[] = $sharedWalkin;

    if (file_exists($sharedWalkin)) {
        $pageFile = $sharedWalkin;
    }
}

if ($pageFile === null) {
    error_log(
        'Router: Page file not found. ' .
        'Role: ' . $role . ', ' .
        'Page: ' . $page . ', ' .
        'Tried: ' . implode(' | ', $triedPaths)
    );

    $page = 'dashboard';

    $dashboardCandidates = [
        __DIR__ . '/pages/' . $role . '/dashboard.php',
        __DIR__ . '/pages/dashboard.php',
        __DIR__ . '/pages/admin/dashboard.php',
    ];

    foreach ($dashboardCandidates as $candidate) {
        if (file_exists($candidate)) {
            $pageFile = $candidate;
            break;
        }
    }

    if ($pageFile === null) {
        ob_end_clean();
        http_response_code(500);

        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Page Error</title>
        </head>
        <body>
            <h1>Page Not Found</h1>
            <p>Role: ' . htmlspecialchars($role) . '</p>
            <p>Page: ' . htmlspecialchars($page) . '</p>
        </body>
        </html>';
        exit;
    }
}

// ============================================================
// PAGE META
// ============================================================
$pageMeta = [
    'dashboard'     => ['Dashboard', 'Control Center'],
    'bookings'      => ['Bookings', 'All booking records'],
    'payments'      => ['Payments', 'Revenue & payment tracking'],
    'sales'         => ['Sales', 'Sales & revenue tracking'],
    'clients'       => ['Clients', 'Client management'],
    'inventory'     => ['Inventory', 'Studio equipment tracking'],
    'packages'      => ['Packages', 'Manage service packages'],
    'loyalty-cards' => ['Loyalty Cards', 'Admin View Only'],
    'staff'         => ['Staff', 'Team management'],
    'feedback'      => ['Feedback', 'Client reviews & insights'],
    'reports'       => ['Reports & Analytics', 'Generate business reports'],
    'notifications' => ['Notifications', 'Stay up to date'],
    'settings'      => ['Settings', 'Studio configuration'],
    'schedule'      => ['Schedule', 'Upcoming sessions'],
    'upload'        => ['Upload Photos', 'Send photos to clients'],
    'booking'       => ['Book a Session', 'Choose your package and date'],
    'photos'        => ['My Photos', 'Download your memories'],
    'profile'       => ['My Profile', 'Manage your account'],
    'walkin'        => ['Walk-in Booking', 'Create walk-in booking'],
];

$title = $pageMeta[$page][0] ?? 'Dashboard';
$sub   = $pageMeta[$page][1] ?? '';

// ============================================================
// GET UNREAD NOTIFICATION COUNT
// ============================================================
$unread = 0;

if (!empty($user['id'])) {
    if (function_exists('getUnreadCount')) {
        try {
            $unread = getUnreadCount((int)$user['id']);
        } catch (Throwable $e) {
            error_log('Notification count error: ' . $e->getMessage());
            $unread = 0;
        }
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = ?
                  AND is_read = 0
            ");
            $stmt->execute([(int)$user['id']]);
            $unread = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Notification count fallback error: ' . $e->getMessage());
            $unread = 0;
        }
    }
}

require __DIR__ . '/header.php';
require __DIR__ . '/sidebar.php';
?>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<div class="main-content">

    <!-- ============================================================
         TOPBAR — PHOTOGRAPHY STYLE (Minimal)
         ============================================================ -->
    <header class="topbar-photo">

        <!-- LEFT: Hamburger only -->
        <div class="topbar-photo-left">
            <button
                class="mobile-nav-toggle-photo"
                onclick="toggleSidebar()"
                type="button"
                aria-label="Open navigation"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <!-- RIGHT: Empty -->

    </header>

    <!-- ============================================================
         FLASH MESSAGES
         ============================================================ -->
    <div class="flash-wrapper-photo">
        <?php
        if (function_exists('showFlash')) {
            echo showFlash();
        }
        ?>
    </div>

    <!-- ============================================================
         PAGE CONTENT
         ============================================================ -->
    <div class="page-content-photo">
        <?php require $pageFile; ?>
    </div>

</div>

<!-- ============================================================
     PHOTOGRAPHY-THEMED STYLES
     ============================================================ -->
<style>
/* ============================================================
   TOPBAR — PHOTOGRAPHY STYLE (Minimal)
   ============================================================ */
.topbar-photo {
    min-height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 32px;
    background: rgba(250, 247, 242, 0.9);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-bottom: 1px solid rgba(224, 224, 224, 0.5);
    position: sticky;
    top: 0;
    z-index: 100;
    transition: all 0.3s ease;
}

/* LEFT */
.topbar-photo-left {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
}

/* MOBILE HAMBURGER */
.mobile-nav-toggle-photo {
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 4px;
    width: 42px;
    height: 42px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid #E0E0E0;
    border-radius: 12px;
    cursor: pointer;
    padding: 0;
    transition: all 0.2s ease;
    flex-shrink: 0;
    z-index: 102;
    position: relative;
}

.mobile-nav-toggle-photo:hover {
    background: white;
    border-color: #6C63FF;
}

.mobile-nav-toggle-photo span {
    width: 18px;
    height: 2px;
    background: #0A0A0A;
    border-radius: 2px;
    transition: all 0.3s ease;
}

/* ============================================================
   CONTENT WRAPPERS
   ============================================================ */
.main-content {
    position: relative;
    z-index: 10;
}

.flash-wrapper-photo {
    padding: 0 32px;
}

.page-content-photo {
    padding: 0;
    animation: fadeInContent 0.4s ease;
}

@keyframes fadeInContent {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ============================================================
   RESPONSIVE — MOBILE (Plain / No fancy design)
   ============================================================ */
@media (max-width: 768px) {
    .topbar-photo {
        padding: 12px 16px;
        min-height: 64px;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 100;
        /* Plain — tanggalin ang blur */
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        background: #FAF7F2;
        border-bottom: 1px solid #E0E0E0;
        transition: none;
    }

    .mobile-nav-toggle-photo {
        display: flex;
        position: relative;
        z-index: 102;
        background: #FFFFFF;
        border: 1px solid #E0E0E0;
        border-radius: 8px;
        transition: none;
    }

    .mobile-nav-toggle-photo:hover {
        background: #FFFFFF;
        border-color: #E0E0E0;
    }

    /* ✅ ISA LANG ang may margin-top para hindi mag-doble */
    .main-content {
        margin-top: 64px;
    }

    .flash-wrapper-photo {
        padding: 0 16px;
        margin-top: 0; /* ✅ Tanggalin ang doble margin */
    }

    .page-content-photo {
        padding: 16px;
        animation: none; /* Plain — walang fade */
    }
}

@media (max-width: 480px) {
    .topbar-photo {
        padding: 10px 12px;
        min-height: 60px;
    }

    .main-content {
        margin-top: 60px;
    }

    .flash-wrapper-photo {
        margin-top: 0; /* ✅ Tanggalin ang doble margin */
        padding: 0 12px;
    }

    .page-content-photo {
        padding: 12px;
    }

    .mobile-nav-toggle-photo {
        width: 38px;
        height: 38px;
        border-radius: 6px;
    }

    .mobile-nav-toggle-photo span {
        width: 16px;
        height: 1.5px;
    }
}
</style>

<?php
require __DIR__ . '/footer.php';
ob_end_flush();
?>