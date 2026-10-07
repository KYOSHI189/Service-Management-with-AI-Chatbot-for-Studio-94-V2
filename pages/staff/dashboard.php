<?php
// ============================================================
// ADMIN / STAFF DASHBOARD
// ============================================================

requireRole(['admin', 'staff']);

$pdo = db();

// ============================================================
// LOGIN TRACKING — "Welcome back" vs "Hello"
// ============================================================
$loginCount  = (int)($_SESSION['login_count'] ?? 1);
$isReturning = $loginCount > 1;

// ============================================================
// GREETING
// ============================================================
if ($isReturning) {
    $greeting = 'Welcome back';
} else {
    $greeting = 'Hello';
}

// ============================================================
// BOOKING STATS
// ============================================================
$pendingApprovals = $pdo->query("
    SELECT COUNT(*) FROM bookings 
    WHERE status = 'Awaiting Approval'
")->fetchColumn();

$confirmedToday = $pdo->query("
    SELECT COUNT(*) FROM bookings 
    WHERE date = CURDATE() 
    AND status IN ('Deposit Paid', 'Approved (Unpaid)')
")->fetchColumn();

$walkinToday = $pdo->query("
    SELECT COUNT(*) FROM bookings 
    WHERE date = CURDATE() 
    AND (type = 'walk-in' OR booking_ref LIKE 'WALK%')
")->fetchColumn();

$thisWeek = $pdo->query("
    SELECT COUNT(*) FROM bookings 
    WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)
    AND status NOT IN ('Cancelled', 'Rejected')
")->fetchColumn();

// ============================================================
// CONFLICT DETECTION
// ============================================================
$conflicts = $pdo->query("
    SELECT b.date, b.time, COUNT(*) as cnt
    FROM bookings b
    WHERE b.status NOT IN ('Cancelled', 'Rejected') 
    AND b.date >= CURDATE()
    GROUP BY b.date, b.time HAVING cnt > 1
    ORDER BY b.date ASC LIMIT 5
")->fetchAll();

// ============================================================
// PENDING APPROVALS LIST
// ============================================================
$pendingList = $pdo->query("
    SELECT b.*, 
           CASE 
               WHEN u.id IS NULL THEN 'Walk-in Client'
               ELSE u.name 
           END as client_name,
           u.phone as client_phone,
           p.name as pkg_name,
           b.booking_ref
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    JOIN packages p ON b.package_id = p.id
    WHERE b.status = 'Awaiting Approval' 
    ORDER BY b.created_at ASC 
    LIMIT 10
")->fetchAll();

// ============================================================
// CONFIRMED TODAY LIST
// ============================================================
$confirmedList = $pdo->query("
    SELECT b.*, 
           CASE 
               WHEN u.id IS NULL THEN 'Walk-in Client'
               ELSE u.name 
           END as client_name,
           u.phone as client_phone,
           p.name as pkg_name,
           b.booking_ref
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    JOIN packages p ON b.package_id = p.id
    WHERE b.date = CURDATE() 
    AND b.status IN ('Deposit Paid', 'Approved (Unpaid)')
    ORDER BY b.time ASC
")->fetchAll();

// ============================================================
// UPCOMING BOOKINGS
// ============================================================
$upcomingBookings = $pdo->query("
    SELECT b.*, 
           CASE 
               WHEN u.id IS NULL THEN 'Walk-in Client'
               ELSE u.name 
           END as client_name,
           u.phone as client_phone,
           p.name as pkg_name,
           b.booking_ref
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    JOIN packages p ON b.package_id = p.id
    WHERE b.date > CURDATE() 
    AND b.date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND b.status NOT IN ('Cancelled', 'Rejected', 'Completed')
    ORDER BY b.date ASC, b.time ASC
    LIMIT 10
")->fetchAll();

// ============================================================
// TODAY'S SCHEDULE
// ============================================================
$todaySchedule = $pdo->query("
    SELECT b.*, 
           CASE 
               WHEN u.id IS NULL THEN 'Walk-in Client'
               ELSE u.name 
           END as client_name,
           u.phone as client_phone,
           p.name as pkg_name,
           b.booking_ref
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    JOIN packages p ON b.package_id = p.id
    WHERE b.date = CURDATE() 
    AND b.status NOT IN ('Cancelled', 'Rejected') 
    ORDER BY b.time ASC
")->fetchAll();

// ============================================================
// WALK-INS TODAY LIST
// ============================================================
$walkinTodayList = $pdo->query("
    SELECT b.*, 
           CASE 
               WHEN u.id IS NULL THEN 'Walk-in Client'
               ELSE u.name 
           END as client_name,
           u.phone as client_phone,
           p.name as pkg_name,
           b.booking_ref
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    JOIN packages p ON b.package_id = p.id
    WHERE b.date = CURDATE() 
    AND (b.type = 'walk-in' OR b.booking_ref LIKE 'WALK%')
    AND b.status NOT IN ('Cancelled', 'Rejected')
    ORDER BY b.time ASC
")->fetchAll();

// ============================================================
// FLASH MESSAGE
// ============================================================
$flash = function_exists('getFlash') ? getFlash() : null;
?>

<!-- ============================================================ -->
<!-- ADMIN DASHBOARD — FULLY RESPONSIVE STYLES                     -->
<!-- ============================================================ -->
<style>
/* ============================================================
   BASE (MOBILE-FIRST) — 320px pataas
   ============================================================ */

.admin-dashboard {
    width: 100%;
    box-sizing: border-box;
}

/* WELCOME HEADER */
.admin-welcome-header {
    background: #000000;
    color: #ffffff;
    padding: 18px 16px;
    border-radius: 12px;
    margin-bottom: 16px;
    box-sizing: border-box;
}

.admin-welcome-header h1 {
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
    line-height: 1.3;
    word-break: break-word;
}

.admin-welcome-header p {
    opacity: 0.85;
    font-size: 12px;
    margin-top: 6px;
    color: #d1d5db;
    line-height: 1.4;
    word-break: break-word;
}

/* ALERT */
.alert {
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 12px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-left: 4px solid #FF6B6B;
    background: #fef2f2;
    color: #721c24;
    flex-wrap: wrap;
    word-break: break-word;
    box-sizing: border-box;
}
.alert-icon { font-size: 16px; flex-shrink: 0; }

/* STATS GRID — mobile: 2 cols */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}

.admin-stat-card {
    background: #FFFFFF;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    padding: 12px 10px;
    text-align: center;
    transition: all 0.3s ease;
    min-width: 0;
    box-sizing: border-box;
}
.admin-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.admin-stat-eyebrow {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #636E72;
    font-weight: 600;
    line-height: 1.3;
    word-break: break-word;
}
.admin-stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #6C63FF;
    margin: 4px 0;
    line-height: 1;
}
.admin-stat-label {
    font-size: 10px;
    color: #636E72;
    line-height: 1.3;
    word-break: break-word;
}

/* TWO COLUMN LAYOUT — mobile: 1 col */
.admin-grid-2 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    margin-bottom: 14px;
}

/* CARD */
.admin-card {
    background: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    padding: 14px 12px;
    margin-bottom: 14px;
    box-sizing: border-box;
    min-width: 0;
}

.admin-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}
.admin-section-header h3 {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
    line-height: 1.3;
    word-break: break-word;
}

.admin-link-text {
    color: #6C63FF;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}
.admin-link-text:hover { text-decoration: underline; }

/* BUTTONS */
.btn-admin {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    box-sizing: border-box;
    white-space: nowrap;
}
.btn-admin-green {
    background: #2ECC71;
    color: white;
}
.btn-admin-green:hover {
    background: #27AE60;
    transform: translateY(-1px);
}
.btn-admin-ghost {
    background: #F0F2F5;
    color: #2D3436;
    border: 1px solid #DFE6E9;
}
.btn-admin-ghost:hover { background: #DFE6E9; }
.btn-admin-sm {
    padding: 4px 10px;
    font-size: 11px;
    border-radius: 6px;
}

/* WALK-IN TAG */
.admin-walkin-tag {
    background: #6C63FF;
    color: white;
    padding: 1px 8px;
    border-radius: 50px;
    font-size: 9px;
    margin-left: 4px;
    font-weight: 600;
    white-space: nowrap;
    display: inline-block;
}

/* TABLE */
.admin-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 8px;
    margin: 0 -4px;
    padding: 0 4px;
}
.admin-table {
    width: 100%;
    min-width: 520px;
    border-collapse: collapse;
    font-size: 12px;
}
.admin-table th,
.admin-table td {
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid #DFE6E9;
    white-space: nowrap;
}
.admin-table th {
    font-weight: 600;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #636E72;
    background: #F0F2F5;
    position: sticky;
    top: 0;
}
.admin-table tr:hover {
    background: rgba(108, 99, 255, 0.03);
}

/* BOOKING ITEM */
.admin-booking-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #DFE6E9;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s;
    flex-wrap: wrap;
    box-sizing: border-box;
    min-width: 0;
}
.admin-booking-item:hover {
    border-color: #6C63FF;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.admin-booking-date {
    text-align: center;
    min-width: 42px;
    flex-shrink: 0;
}
.admin-booking-date .day {
    font-size: 16px;
    font-weight: 700;
    color: #6C63FF;
    line-height: 1;
}
.admin-booking-date .month {
    font-size: 9px;
    text-transform: uppercase;
    color: #636E72;
    margin-top: 2px;
}
.admin-booking-info {
    flex: 1 1 140px;
    min-width: 0;
}
.admin-booking-info .pkg {
    font-weight: 600;
    font-size: 13px;
    word-break: break-word;
    line-height: 1.3;
}
.admin-booking-info .time {
    font-size: 11px;
    color: #636E72;
    margin-top: 2px;
}
.admin-booking-info .client {
    font-size: 11px;
    margin-top: 2px;
    word-break: break-word;
}

/* EMPTY / MUTED TEXT */
.admin-empty-text {
    color: #636E72;
    font-size: 12px;
    padding: 12px 0;
    line-height: 1.4;
}

/* ============================================================
   SMALL PHONE (min-width: 380px)
   ============================================================ */
@media (min-width: 380px) {
    .admin-welcome-header h1 { font-size: 19px; }
    .admin-stat-value { font-size: 24px; }
    .admin-stat-eyebrow { font-size: 10px; }
    .admin-stat-label { font-size: 11px; }
    .admin-card { padding: 16px 14px; }
    .admin-section-header h3 { font-size: 15px; }
    .admin-booking-date .day { font-size: 18px; }
    .admin-booking-info .pkg { font-size: 14px; }
}

/* ============================================================
   LARGE PHONE / SMALL TABLET (min-width: 600px)
   ============================================================ */
@media (min-width: 600px) {
    .admin-welcome-header {
        padding: 24px 28px;
        border-radius: 14px;
        margin-bottom: 20px;
    }
    .admin-welcome-header h1 { font-size: 22px; }
    .admin-welcome-header p { font-size: 13px; }

    .alert { padding: 12px 16px; font-size: 13px; margin-bottom: 14px; }
    .alert-icon { font-size: 18px; }

    .admin-stats-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .admin-stat-card { padding: 16px 12px; border-radius: 12px; }
    .admin-stat-value { font-size: 26px; }

    .admin-grid-2 {
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .admin-card { padding: 18px 16px; border-radius: 12px; }

    .admin-section-header h3 { font-size: 16px; }
    .admin-link-text { font-size: 13px; }

    .admin-table { font-size: 13px; }
    .admin-table th,
    .admin-table td { padding: 10px 12px; }
    .admin-table th { font-size: 11px; }

    .admin-booking-item { padding: 12px 14px; gap: 14px; flex-wrap: nowrap; }
    .admin-booking-date { min-width: 48px; }
    .admin-booking-date .day { font-size: 20px; }
    .admin-booking-date .month { font-size: 10px; }
    .admin-booking-info .pkg { font-size: 15px; }
    .admin-booking-info .client { font-size: 13px; }
    .admin-booking-info .time { font-size: 12px; }
}

/* ============================================================
   TABLET (min-width: 768px)
   ============================================================ */
@media (min-width: 768px) {
    .admin-welcome-header { padding: 28px 32px; border-radius: 16px; }
    .admin-welcome-header h1 { font-size: 24px; }
    .admin-welcome-header p { font-size: 14px; }

    .admin-stats-grid { gap: 14px; }
    .admin-stat-card { padding: 18px 14px; }
    .admin-stat-value { font-size: 30px; }
    .admin-stat-eyebrow { font-size: 11px; }
    .admin-stat-label { font-size: 12px; }

    .admin-card { padding: 20px 18px; }
    .admin-section-header h3 { font-size: 17px; }

    .admin-table { min-width: 560px; }
    .admin-table th,
    .admin-table td { padding: 11px 14px; }

    .admin-booking-item { padding: 14px 18px; gap: 16px; }
    .admin-booking-date { min-width: 52px; }
    .admin-booking-date .day { font-size: 22px; }
}

/* ============================================================
   LAPTOP / DESKTOP (min-width: 1024px)
   ============================================================ */
@media (min-width: 1024px) {
    .admin-welcome-header { padding: 30px 32px; }
    .admin-welcome-header h1 { font-size: 26px; }

    .admin-stats-grid { gap: 16px; margin-bottom: 24px; }
    .admin-stat-card { padding: 20px 16px; }
    .admin-stat-value { font-size: 32px; }

    .admin-grid-2 { gap: 20px; margin-bottom: 20px; }
    .admin-card { padding: 22px 20px; margin-bottom: 20px; }
    .admin-section-header { margin-bottom: 16px; }

    .admin-table th,
    .admin-table td { padding: 12px 16px; }
}

/* ============================================================
   LARGE DESKTOP (min-width: 1440px)
   ============================================================ */
@media (min-width: 1440px) {
    .admin-welcome-header { padding: 34px 40px; border-radius: 18px; }
    .admin-welcome-header h1 { font-size: 30px; }
    .admin-welcome-header p { font-size: 15px; }

    .admin-stats-grid { gap: 20px; }
    .admin-stat-card { padding: 24px 20px; border-radius: 14px; }
    .admin-stat-value { font-size: 36px; }

    .admin-grid-2 { gap: 24px; }
    .admin-card { padding: 26px 24px; border-radius: 14px; }

    .admin-table { font-size: 14px; }
    .admin-table th,
    .admin-table td { padding: 14px 18px; }

    .admin-booking-item { padding: 16px 20px; }
    .admin-booking-date .day { font-size: 24px; }
}

/* ============================================================
   ULTRAWIDE (min-width: 1920px)
   ============================================================ */
@media (min-width: 1920px) {
    .admin-welcome-header { padding: 38px 48px; }
    .admin-welcome-header h1 { font-size: 34px; }

    .admin-stats-grid { gap: 24px; }
    .admin-stat-card { padding: 28px 24px; }
    .admin-stat-value { font-size: 40px; }

    .admin-card { padding: 30px 28px; }
}

/* ============================================================
   LANDSCAPE MOBILE
   ============================================================ */
@media (max-height: 500px) and (orientation: landscape) {
    .admin-welcome-header { padding: 12px 16px; }
    .admin-welcome-header h1 { font-size: 18px; }
    .admin-stats-grid { grid-template-columns: repeat(4, 1fr); }
    .admin-grid-2 { grid-template-columns: 1fr 1fr; }
}

/* ============================================================
   PRINT
   ============================================================ */
@media print {
    .btn-admin,
    .admin-link-text { display: none !important; }
    .admin-card { break-inside: avoid; box-shadow: none; border: 1px solid #ccc; }
    .admin-stats-grid { grid-template-columns: repeat(4, 1fr); }
}
</style>

<!-- ============================================================ -->
<!-- ADMIN/STAFF DASHBOARD CONTENT                                 -->
<!-- ============================================================ -->

<div class="admin-dashboard">

    <!-- WELCOME HEADER -->
    <div class="admin-welcome-header">
        <h1>
            <strong>👋 <?= $greeting ?>, <?= clean($user['name']) ?>!</strong>
        </h1>
        <p>
            <?php if ($isReturning): ?>
                Good to see you again. Here's what's happening today at Studio 94.
            <?php else: ?>
                Here's what's happening today at Studio 94.
            <?php endif; ?>
        </p>
    </div>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
        <div class="alert" style="border-left-color: <?= $flash['type'] === 'success' ? '#2ECC71' : '#FF6B6B' ?>; background: <?= $flash['type'] === 'success' ? '#ecfdf5' : '#fef2f2' ?>;">
            <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
            <span><?= $flash['msg'] ?></span>
        </div>
    <?php endif; ?>

    <!-- Conflict Alerts -->
    <?php foreach ($conflicts as $c): ?>
    <div class="alert">
        <span class="alert-icon">⚠️</span>
        <strong>Conflict:</strong> <?= $c['cnt'] ?> bookings overlap on <?= formatDate($c['date']) ?> at <?= clean($c['time']) ?>.
        <a href="index.php?page=bookings" class="btn-admin btn-admin-ghost btn-admin-sm" style="margin-left:auto;">View</a>
    </div>
    <?php endforeach; ?>

    <!-- STATS GRID -->
    <div class="admin-stats-grid">
        <div class="admin-stat-card">
            <div class="admin-stat-eyebrow">Pending Approvals</div>
            <div class="admin-stat-value"><?= (int)$pendingApprovals ?></div>
            <div class="admin-stat-label">Awaiting review</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-eyebrow">Confirmed Today</div>
            <div class="admin-stat-value"><?= (int)$confirmedToday ?></div>
            <div class="admin-stat-label">Sessions today</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-eyebrow">Walk-ins Today</div>
            <div class="admin-stat-value"><?= (int)$walkinToday ?></div>
            <div class="admin-stat-label">Drop-in clients</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-eyebrow">This Week</div>
            <div class="admin-stat-value"><?= (int)$thisWeek ?></div>
            <div class="admin-stat-label">Total sessions</div>
        </div>
    </div>

    <!-- TWO COLUMN LAYOUT -->
    <div class="admin-grid-2">

        <!-- PENDING APPROVALS -->
        <div class="admin-card">
            <div class="admin-section-header">
                <h3><strong>⏳ Pending Approvals</strong></h3>
                <a href="index.php?page=bookings&status=Awaiting Approval" class="admin-link-text">View all →</a>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>Client</th><th>Package</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingList as $b): ?>
                        <tr>
                            <td>
                                <strong><?= clean($b['client_name']) ?></strong>
                                <?php if (strpos($b['booking_ref'], 'WALK') !== false): ?>
                                    <span class="admin-walkin-tag">🚶 Walk-in</span>
                                <?php endif; ?>
                            </td>
                            <td><?= clean($b['pkg_name']) ?></td>
                            <td><?= formatDate($b['date']) ?></td>
                            <td>
                                <form method="POST" action="index.php?page=bookings" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button class="btn-admin btn-admin-green btn-admin-sm">Approve</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pendingList)): ?>
                            <tr><td colspan="4" style="text-align:center;color:#636E72;padding:20px;">🎉 All caught up!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CONFIRMED TODAY -->
        <div class="admin-card">
            <div class="admin-section-header">
                <h3><strong>✅ Confirmed Today</strong></h3>
                <a href="index.php?page=schedule" class="admin-link-text">View schedule →</a>
            </div>
            <?php if (empty($confirmedList)): ?>
                <p class="admin-empty-text">No confirmed sessions for today.</p>
            <?php else: ?>
                <?php foreach ($confirmedList as $b): ?>
                <div class="admin-booking-item">
                    <div class="admin-booking-date">
                        <div class="day"><?= date('g', strtotime('2000-01-01 ' . $b['time'])) ?></div>
                        <div class="month"><?= date('A', strtotime('2000-01-01 ' . $b['time'])) ?></div>
                    </div>
                    <div class="admin-booking-info">
                        <div class="pkg">
                            <?= clean($b['pkg_name']) ?>
                            <?php if (strpos($b['booking_ref'], 'WALK') !== false): ?>
                                <span class="admin-walkin-tag">🚶 Walk-in</span>
                            <?php endif; ?>
                        </div>
                        <div class="client"><?= clean($b['client_name']) ?></div>
                        <div class="time"><?= clean($b['time']) ?></div>
                    </div>
                    <?= statusBadge($b['status']) ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- UPCOMING BOOKINGS -->
    <div class="admin-card">
        <div class="admin-section-header">
            <h3><strong>📅 Upcoming Bookings (Next 7 Days)</strong></h3>
            <a href="index.php?page=schedule" class="admin-link-text">View all →</a>
        </div>
        <?php if (empty($upcomingBookings)): ?>
            <p class="admin-empty-text">No upcoming bookings in the next 7 days.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Client</th>
                            <th>Package</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingBookings as $b): ?>
                        <tr>
                            <td><?= formatDate($b['date']) ?></td>
                            <td><?= clean($b['time']) ?></td>
                            <td>
                                <?= clean($b['client_name']) ?>
                                <?php if (strpos($b['booking_ref'], 'WALK') !== false): ?>
                                    <span class="admin-walkin-tag">🚶</span>
                                <?php endif; ?>
                            </td>
                            <td><?= clean($b['pkg_name']) ?></td>
                            <td><?= statusBadge($b['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>