<?php
// ============================================================
// STUDIO 94 SNAPTRACK — NOTIFICATIONS
// Shared notification page for Admin, Staff, and Client
//
// File:
// /pages/notifications.php
//
// Requirements:
// - functions.php
// - notifications table
// - notifications.link column
// ============================================================


// ============================================================
// REQUIRE LOGIN
// ============================================================
if (function_exists('requireLogin')) {
    requireLogin();
}


// ============================================================
// GET CURRENT USER
// ============================================================
$currentUser = $user ?? currentUser();

$userId = (int)($currentUser['id'] ?? 0);

if ($userId <= 0) {
    echo '
        <div class="notification-error">
            Unable to identify the current user.
        </div>
    ';
    return;
}


// ============================================================
// HELPER — ESCAPE OUTPUT
// ============================================================
if (!function_exists('notificationClean')) {

    function notificationClean($value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


// ============================================================
// HELPER — VALIDATE INTERNAL NOTIFICATION LINK
// ============================================================
//
// Only allow links such as:
//
// index.php?page=bookings
// index.php?page=payments
// index.php?page=inventory
//
// External URLs are blocked.
// ============================================================
function isSafeNotificationLink(?string $link): bool
{
    if (!$link) {
        return false;
    }

    $link = trim($link);

    if ($link === '') {
        return false;
    }

    // Remove leading slash if present
    $link = ltrim($link, '/');

    // Only allow internal index.php routes
    if (
        !str_starts_with(
            strtolower($link),
            'index.php?page='
        )
    ) {
        return false;
    }

    // Block protocol-based URLs
    if (
        preg_match(
            '/^(https?:|javascript:|data:|\/\/)/i',
            $link
        )
    ) {
        return false;
    }

    return true;
}


// ============================================================
// HANDLE NOTIFICATION ACTIONS
// ============================================================
//
// Supported:
//
// ?open=ID
// ?mark_all=1
// ?delete=ID
// ?delete_read=1
// ============================================================
$actionMessage = '';
$actionError = '';


// ============================================================
// OPEN / CLICK NOTIFICATION
// ============================================================
//
// Example:
//
// index.php?page=notifications&open=15
//
// 1. Verify notification belongs to current user
// 2. Mark notification as read
// 3. Redirect to its internal link
// ============================================================
if (isset($_GET['open'])) {

    $notificationId = (int)$_GET['open'];

    if ($notificationId > 0) {

        try {

            $notification = null;

            if (function_exists('getNotification')) {

                $notification = getNotification(
                    $notificationId,
                    $userId
                );

            } else {

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
                    WHERE id = ?
                      AND user_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $notificationId,
                    $userId
                ]);

                $notification = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );
            }


            // ------------------------------------------------
            // Notification does not belong to this user
            // ------------------------------------------------
            if (!$notification) {

                $actionError =
                    'Notification not found.';

            } else {

                // --------------------------------------------
                // Mark as read
                // --------------------------------------------
                if (
                    (int)$notification['is_read'] === 0
                ) {

                    if (
                        function_exists(
                            'markNotificationRead'
                        )
                    ) {

                        markNotificationRead(
                            $notificationId,
                            $userId
                        );

                    } else {

                        $pdo = db();

                        $stmt = $pdo->prepare("
                            UPDATE notifications
                            SET is_read = 1
                            WHERE id = ?
                              AND user_id = ?
                        ");

                        $stmt->execute([
                            $notificationId,
                            $userId
                        ]);
                    }
                }


                // --------------------------------------------
                // Get destination link
                // --------------------------------------------
                $link = trim(
                    (string)(
                        $notification['link']
                        ?? ''
                    )
                );


                // --------------------------------------------
                // Redirect if safe internal link exists
                // --------------------------------------------
                if (
                    isSafeNotificationLink($link)
                ) {

                    header(
                        'Location: ' . $link
                    );

                    exit;

                } else {

                    // No valid destination.
                    // Stay on notification page.
                    header(
                        'Location: index.php?page=notifications'
                    );

                    exit;
                }
            }

        } catch (Throwable $e) {

            error_log(
                'Notification open error: ' .
                $e->getMessage()
            );

            $actionError =
                'Unable to open notification.';
        }
    }
}


// ============================================================
// MARK ALL AS READ
// ============================================================
if (
    isset($_GET['mark_all']) &&
    (string)$_GET['mark_all'] === '1'
) {

    try {

        if (
            function_exists(
                'markAllNotificationsRead'
            )
        ) {

            markAllNotificationsRead($userId);

        } else {

            $pdo = db();

            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE user_id = ?
                  AND is_read = 0
            ");

            $stmt->execute([
                $userId
            ]);
        }

        $actionMessage =
            'All notifications have been marked as read.';

    } catch (Throwable $e) {

        error_log(
            'Mark all notifications error: ' .
            $e->getMessage()
        );

        $actionError =
            'Unable to mark all notifications as read.';
    }
}


// ============================================================
// DELETE SINGLE NOTIFICATION
// ============================================================
if (isset($_GET['delete'])) {

    $notificationId = (int)$_GET['delete'];

    if ($notificationId > 0) {

        try {

            if (
                function_exists(
                    'deleteNotification'
                )
            ) {

                deleteNotification(
                    $notificationId,
                    $userId
                );

            } else {

                $pdo = db();

                $stmt = $pdo->prepare("
                    DELETE FROM notifications
                    WHERE id = ?
                      AND user_id = ?
                ");

                $stmt->execute([
                    $notificationId,
                    $userId
                ]);
            }

            $actionMessage =
                'Notification deleted.';

        } catch (Throwable $e) {

            error_log(
                'Delete notification error: ' .
                $e->getMessage()
            );

            $actionError =
                'Unable to delete notification.';
        }
    }
}


// ============================================================
// DELETE ALL READ NOTIFICATIONS
// ============================================================
if (
    isset($_GET['delete_read']) &&
    (string)$_GET['delete_read'] === '1'
) {

    try {

        if (
            function_exists(
                'deleteReadNotifications'
            )
        ) {

            deleteReadNotifications($userId);

        } else {

            $pdo = db();

            $stmt = $pdo->prepare("
                DELETE FROM notifications
                WHERE user_id = ?
                  AND is_read = 1
            ");

            $stmt->execute([
                $userId
            ]);
        }

        $actionMessage =
            'All read notifications have been deleted.';

    } catch (Throwable $e) {

        error_log(
            'Delete read notifications error: ' .
            $e->getMessage()
        );

        $actionError =
            'Unable to delete read notifications.';
    }
}


// ============================================================
// GET NOTIFICATIONS
// ============================================================
$notifications = [];

try {

    if (function_exists('getNotifications')) {

        $notifications = getNotifications(
            $userId,
            100
        );

    } else {

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
            ORDER BY created_at DESC, id DESC
            LIMIT 100
        ");

        $stmt->execute([
            $userId
        ]);

        $notifications =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

} catch (Throwable $e) {

    error_log(
        'Get notifications error: ' .
        $e->getMessage()
    );

    $notifications = [];

    $actionError =
        'Unable to load notifications.';
}


// ============================================================
// COUNT UNREAD
// ============================================================
$unreadCount = 0;

foreach ($notifications as $notification) {

    if (
        (int)(
            $notification['is_read'] ?? 0
        ) === 0
    ) {

        $unreadCount++;
    }
}


// ============================================================
// FORMAT NOTIFICATION TIME
// ============================================================
function formatNotificationDate(
    ?string $date
): string {

    if (!$date) {
        return '';
    }

    try {

        $time = new DateTime($date);

        return $time->format(
            'M d, Y • h:i A'
        );

    } catch (Throwable $e) {

        return (string)$date;
    }
}


// ============================================================
// GET NOTIFICATION ICON
// ============================================================
function getNotificationIcon(
    array $notification
): string {

    $icon = trim(
        (string)(
            $notification['icon'] ?? ''
        )
    );

    if ($icon !== '' && $icon !== '?') {
        return $icon;
    }

    $type = strtolower(
        (string)(
            $notification['type'] ?? 'system'
        )
    );

    switch ($type) {

        case 'booking':
            return '📅';

        case 'payment':
            return '💳';

        case 'inventory':
            return '📦';

        case 'feedback':
            return '⭐';

        case 'urgent_feedback':
            return '⚠️';

        case 'new_client':
            return '👤';

        case 'photo':
            return '🖼️';

        case 'system':
        default:
            return '🔔';
    }
}


// ============================================================
// GET NOTIFICATION TYPE CLASS
// ============================================================
function getNotificationTypeClass(
    array $notification
): string {

    $type = strtolower(
        (string)(
            $notification['type'] ?? 'system'
        )
    );

    switch ($type) {

        case 'booking':
            return 'type-booking';

        case 'payment':
            return 'type-payment';

        case 'inventory':
            return 'type-inventory';

        case 'feedback':
        case 'urgent_feedback':
            return 'type-feedback';

        case 'new_client':
            return 'type-client';

        case 'photo':
            return 'type-photo';

        default:
            return 'type-system';
    }
}

?>

<!-- ============================================================
     NOTIFICATIONS PAGE
============================================================= -->

<div class="notifications-page">


    <!-- ========================================================
         PAGE HEADER
    ========================================================= -->
    <div class="notifications-header">

        <div>

            <div class="notifications-title-row">

                <h1>
                    Notifications
                </h1>

                <?php if ($unreadCount > 0): ?>

                    <span class="header-unread-badge">
                        <?= $unreadCount > 99
                            ? '99+'
                            : $unreadCount ?>
                    </span>

                <?php endif; ?>

            </div>

            <p class="notifications-subtitle">
                Stay updated with your Studio 94 activities.
            </p>

        </div>


        <!-- ====================================================
             ACTIONS
        ===================================================== -->
        <div class="notification-actions">

            <?php if ($unreadCount > 0): ?>

                <a
                    href="index.php?page=notifications&mark_all=1"
                    class="notification-action-btn"
                    onclick="
                        return confirm(
                            'Mark all notifications as read?'
                        );
                    "
                >
                    ✓ Mark all as read
                </a>

            <?php endif; ?>


            <?php

            $hasRead = false;

            foreach ($notifications as $n) {

                if (
                    (int)(
                        $n['is_read'] ?? 0
                    ) === 1
                ) {

                    $hasRead = true;
                    break;
                }
            }

            ?>

            <?php if ($hasRead): ?>

                <a
                    href="index.php?page=notifications&delete_read=1"
                    class="notification-action-btn danger"
                    onclick="
                        return confirm(
                            'Delete all read notifications?'
                        );
                    "
                >
                    🗑 Delete read
                </a>

            <?php endif; ?>

        </div>

    </div>


    <!-- ========================================================
         ACTION MESSAGE
    ========================================================= -->
    <?php if ($actionMessage !== ''): ?>

        <div class="notification-alert success">

            <span>✓</span>

            <span>
                <?= notificationClean($actionMessage) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         ACTION ERROR
    ========================================================= -->
    <?php if ($actionError !== ''): ?>

        <div class="notification-alert error">

            <span>⚠️</span>

            <span>
                <?= notificationClean($actionError) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         NOTIFICATION CONTENT
    ========================================================= -->
    <div class="notifications-card">


        <?php if (empty($notifications)): ?>


            <!-- ==================================================
                 EMPTY STATE
            =================================================== -->
            <div class="notifications-empty">

                <div class="empty-icon">
                    🔔
                </div>

                <h2>
                    No notifications
                </h2>

                <p>
                    You're all caught up.
                    New Studio 94 updates will appear here.
                </p>

            </div>


        <?php else: ?>


            <!-- ==================================================
                 NOTIFICATION LIST
            =================================================== -->
            <div class="notification-list">


                <?php foreach ($notifications as $notification): ?>

                    <?php

                    $notificationId =
                        (int)(
                            $notification['id'] ?? 0
                        );

                    $isRead =
                        (int)(
                            $notification['is_read'] ?? 0
                        ) === 1;

                    $title =
                        (string)(
                            $notification['title'] ?? 'Notification'
                        );

                    $message =
                        (string)(
                            $notification['message'] ?? ''
                        );

                    $link =
                        trim(
                            (string)(
                                $notification['link'] ?? ''
                            )
                        );

                    $icon =
                        getNotificationIcon(
                            $notification
                        );

                    $typeClass =
                        getNotificationTypeClass(
                            $notification
                        );

                    $createdAt =
                        formatNotificationDate(
                            $notification['created_at'] ?? null
                        );

                    ?>


                    <!-- ==========================================
                         NOTIFICATION ITEM
                    =========================================== -->
                    <div
                        class="
                            notification-item
                            <?= $isRead
                                ? 'is-read'
                                : 'is-unread' ?>
                            <?= $typeClass ?>
                        "
                    >


                        <!-- ======================================
                             CLICK AREA
                        ======================================= -->
                        <?php if (
                            $notificationId > 0
                        ): ?>

                            <a
                                href="
                                    index.php?page=notifications&open=<?= $notificationId ?>
                                "
                                class="notification-main-link"
                            >

                                <!-- ==============================
                                     ICON
                                =============================== -->
                                <div class="notification-icon-wrap">

                                    <span
                                        class="notification-icon"
                                        aria-hidden="true"
                                    >
                                        <?= notificationClean($icon) ?>
                                    </span>

                                </div>


                                <!-- ==============================
                                     CONTENT
                                =============================== -->
                                <div class="notification-content">

                                    <div class="notification-title-row">

                                        <h3>
                                            <?= notificationClean($title) ?>
                                        </h3>

                                        <?php if (!$isRead): ?>

                                            <span
                                                class="unread-dot"
                                                aria-label="Unread"
                                            ></span>

                                        <?php endif; ?>

                                    </div>


                                    <?php if ($message !== ''): ?>

                                        <p class="notification-message">
                                            <?= nl2br(
                                                notificationClean(
                                                    $message
                                                )
                                            ) ?>
                                        </p>

                                    <?php endif; ?>


                                    <div class="notification-meta">

                                        <span>
                                            <?= notificationClean($createdAt) ?>
                                        </span>


                                        <?php if (
                                            isSafeNotificationLink($link)
                                        ): ?>

                                            <span class="notification-open-hint">
                                                Click to view →
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </a>

                        <?php endif; ?>


                        <!-- ======================================
                             DELETE BUTTON
                        ======================================= -->
                        <a
                            href="
                                index.php?page=notifications&delete=<?= $notificationId ?>
                            "
                            class="notification-delete"
                            title="Delete notification"
                            aria-label="Delete notification"
                            onclick="
                                event.stopPropagation();

                                return confirm(
                                    'Delete this notification?'
                                );
                            "
                        >
                            ×
                        </a>

                    </div>

                <?php endforeach; ?>


            </div>

        <?php endif; ?>


    </div>

</div>


<!-- ============================================================
     NOTIFICATIONS PAGE STYLE
============================================================= -->

<style>

.notifications-page {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
}


/* ============================================================
   HEADER
============================================================ */

.notifications-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.notifications-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.notifications-title-row h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
    color: #1f2937;
}

.notifications-subtitle {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}

.header-unread-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 25px;
    height: 25px;
    padding: 0 8px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
}


/* ============================================================
   ACTION BUTTONS
============================================================ */

.notification-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.notification-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 8px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    background: #fff;
    color: #374151;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition:
        background 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease;
}

.notification-action-btn:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    transform: translateY(-1px);
}

.notification-action-btn.danger {
    color: #dc2626;
}

.notification-action-btn.danger:hover {
    background: #fef2f2;
    border-color: #fecaca;
}


/* ============================================================
   ALERTS
============================================================ */

.notification-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 16px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 14px;
    font-weight: 600;
}

.notification-alert.success {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #047857;
}

.notification-alert.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
}


/* ============================================================
   MAIN CARD
============================================================ */

.notifications-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
    box-shadow:
        0 4px 20px rgba(0, 0, 0, 0.05);
}


/* ============================================================
   NOTIFICATION LIST
============================================================ */

.notification-list {
    display: flex;
    flex-direction: column;
}

.notification-item {
    position: relative;
    display: flex;
    align-items: stretch;
    border-bottom: 1px solid #f0f0f0;
    transition:
        background 0.2s ease;
}

.notification-item:last-child {
    border-bottom: 0;
}

.notification-item:hover {
    background: #f9fafb;
}

.notification-item.is-unread {
    background: #f8fbff;
}

.notification-item.is-unread:hover {
    background: #f1f7ff;
}


/* ============================================================
   MAIN CLICK LINK
============================================================ */

.notification-main-link {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    flex: 1;
    min-width: 0;
    padding: 18px 50px 18px 20px;
    color: inherit;
    text-decoration: none;
}


/* ============================================================
   ICON
============================================================ */

.notification-icon-wrap {
    flex: 0 0 auto;
}

.notification-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: #f3f4f6;
    font-size: 21px;
}

.type-booking .notification-icon {
    background: #eff6ff;
}

.type-payment .notification-icon {
    background: #f0fdf4;
}

.type-inventory .notification-icon {
    background: #fff7ed;
}

.type-feedback .notification-icon {
    background: #fefce8;
}

.type-client .notification-icon {
    background: #f5f3ff;
}

.type-photo .notification-icon {
    background: #fdf2f8;
}


/* ============================================================
   CONTENT
============================================================ */

.notification-content {
    min-width: 0;
    flex: 1;
}

.notification-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.notification-title-row h3 {
    margin: 0;
    color: #111827;
    font-size: 15px;
    font-weight: 800;
}

.notification-item.is-read
.notification-title-row h3 {
    font-weight: 700;
    color: #374151;
}

.notification-message {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.55;
}

.notification-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 9px;
    color: #9ca3af;
    font-size: 11px;
}

.notification-open-hint {
    color: #6b63ff;
    font-weight: 700;
}


/* ============================================================
   UNREAD DOT
============================================================ */

.unread-dot {
    flex: 0 0 auto;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ef4444;
}


/* ============================================================
   DELETE BUTTON
============================================================ */

.notification-delete {
    position: absolute;
    top: 50%;
    right: 16px;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 7px;
    color: #9ca3af;
    text-decoration: none;
    font-size: 20px;
    line-height: 1;
    transition:
        background 0.2s ease,
        color 0.2s ease;
}

.notification-delete:hover {
    background: #fee2e2;
    color: #dc2626;
}


/* ============================================================
   EMPTY STATE
============================================================ */

.notifications-empty {
    padding: 70px 25px;
    text-align: center;
}

.empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: #f3f4f6;
    font-size: 32px;
}

.notifications-empty h2 {
    margin: 0;
    color: #1f2937;
    font-size: 20px;
    font-weight: 800;
}

.notifications-empty p {
    max-width: 420px;
    margin: 8px auto 0;
    color: #9ca3af;
    font-size: 14px;
    line-height: 1.5;
}


/* ============================================================
   MOBILE
============================================================ */

@media (max-width: 768px) {

    .notifications-page {
        width: 100%;
    }

    .notifications-header {
        flex-direction: column;
        align-items: stretch;
    }

    .notifications-title-row h1 {
        font-size: 23px;
    }

    .notification-actions {
        width: 100%;
    }

    .notification-action-btn {
        flex: 1;
        min-width: 0;
    }

    .notification-main-link {
        gap: 11px;
        padding: 15px 46px 15px 14px;
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
        border-radius: 10px;
    }

    .notification-title-row h3 {
        font-size: 14px;
    }

    .notification-message {
        font-size: 12px;
    }

    .notification-meta {
        font-size: 10px;
        flex-wrap: wrap;
        gap: 7px;
    }

    .notification-delete {
        right: 10px;
    }
}

</style>