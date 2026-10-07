<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Real-time Notification Checker
// File: pages/api/check_new_notifications.php
//
// Two modes:
//   1. ?all=1         → returns TOTAL unread count (for badge)
//   2. ?since=...     → returns NEW notifications since timestamp
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ============================================================
// AUTH CHECK
// ============================================================
if (!isLoggedIn()) {
    echo json_encode([
        'success'       => false,
        'new_count'     => 0,
        'notifications' => [],
        'checked_at'    => time(),
    ]);
    exit;
}

$user = currentUser();
if (!$user || !in_array($user['role'], ['client', 'staff', 'admin'], true)) {
    echo json_encode([
        'success'       => false,
        'new_count'     => 0,
        'notifications' => [],
        'checked_at'    => time(),
    ]);
    exit;
}

$uid = (int) $user['id'];

// ============================================================
// DATABASE
// ============================================================
try {
    $pdo = db();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('DB not available');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success'       => false,
        'new_count'     => 0,
        'notifications' => [],
        'checked_at'    => time(),
        'error'         => 'Database unavailable',
    ]);
    exit;
}

// ============================================================
// MODE 1: TOTAL UNREAD COUNT (para sa badge)
// ?all=1
// ============================================================
if (isset($_GET['all'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
              AND is_read = 0
        ");
        $stmt->execute([$uid]);
        $total = (int) $stmt->fetchColumn();

        echo json_encode([
            'success'    => true,
            'new_count'  => $total,
            'checked_at' => time(),
        ]);
        exit;

    } catch (Throwable $e) {
        error_log('check_new_notifications [all] error: ' . $e->getMessage());
        echo json_encode([
            'success'    => false,
            'new_count'  => 0,
            'checked_at' => time(),
        ]);
        exit;
    }
}

// ============================================================
// MODE 2: NEW NOTIFICATIONS SINCE TIMESTAMP
// ?since=...
// ============================================================
$since = isset($_GET['since']) ? (int) $_GET['since'] : (time() - 30);

// Sanity check
if ($since < 0 || $since > time() + 60) {
    $since = time() - 30;
}

try {
    // ============================================================
    // GET NEW NOTIFICATIONS (unread + created after $since)
    // ============================================================
    $stmt = $pdo->prepare("
        SELECT id, title, message, icon, link, created_at
        FROM notifications
        WHERE user_id = ?
          AND is_read = 0
          AND UNIX_TIMESTAMP(created_at) > ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$uid, $since]);
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // TOTAL UNREAD COUNT (para sa badge)
    // ============================================================
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ?
          AND is_read = 0
    ");
    $stmtTotal->execute([$uid]);
    $totalUnread = (int) $stmtTotal->fetchColumn();

    echo json_encode([
        'success'       => true,
        'new_count'     => $totalUnread,     // total unread (for badge)
        'new_since'     => count($notifs),    // notifications since last check
        'notifications' => $notifs,
        'checked_at'    => time(),
    ]);

} catch (Throwable $e) {
    error_log('check_new_notifications [since] error: ' . $e->getMessage());
    echo json_encode([
        'success'       => false,
        'new_count'     => 0,
        'notifications' => [],
        'checked_at'    => time(),
    ]);
}