<?php
requireRole('client');
$pdo  = db();
$uid  = $user['id'];

// ============================================================
// ✅ FIXED: LOYALTY COUNT — DYNAMIC BASE SA COMPLETED BOOKINGS
// ============================================================
// Ang loyalty count ay hindi na kinukuha sa loyalty_cards.total_bookings.
// Sa halip, ito ay kinakalkula dynamically base sa ACTUAL COMPLETED bookings.
// Ito ay nagsisiguro na:
//   - Hindi madadagdagan ang count kapag nag-submit lang ng booking
//   - Hindi madadagdagan kapag na-cancel ang booking
//   - Madadagdagan lang kapag COMPLETED na ang session
// ============================================================
$bookingCount = getClientBookingCount($uid);

$rewards  = getLoyaltyRewards($bookingCount);
$progress = getLoyaltyProgress($bookingCount);

// Kunin ang card_number (kung may existing loyalty card)
$lcStmt = $pdo->prepare("SELECT card_number FROM loyalty_cards WHERE user_id = ?");
$lcStmt->execute([$uid]);
$lc = $lcStmt->fetch();
$cardNumber = $lc ? $lc['card_number'] : null;

// Loyalty tiers definition
$tiers = [
    2  => ['label' => '+5 MIN',      'icon' => '⏱️'],
    4  => ['label' => '+1 PRINT',    'icon' => '🖼️'],
    7  => ['label' => '+1 BACKDROP', 'icon' => '🎨'],
    10 => ['label' => '50% OFF',     'icon' => '🎉'],
];

// ============================================================
// UPCOMING BOOKING
// ============================================================
$upcoming = $pdo->prepare("SELECT b.*, p.name as pkg_name, p.duration FROM bookings b JOIN packages p ON b.package_id=p.id
    WHERE b.user_id=? AND b.date >= CURDATE() AND b.status NOT IN ('Cancelled') ORDER BY b.date ASC LIMIT 1");
$upcoming->execute([$uid]);
$nextSession = $upcoming->fetch();

// ============================================================
// RECENT BOOKINGS
// ============================================================
$recentStmt = $pdo->prepare("SELECT b.*, p.name as pkg_name FROM bookings b JOIN packages p ON b.package_id=p.id
    WHERE b.user_id=? ORDER BY b.created_at DESC LIMIT 5");
$recentStmt->execute([$uid]);
$recentBookings = $recentStmt->fetchAll();

// ============================================================
// RECENT PHOTOS
// ============================================================
$photosStmt = $pdo->prepare("SELECT ph.*, b.booking_ref FROM photos ph JOIN bookings b ON ph.booking_id=b.id
    WHERE b.user_id=? AND ph.status='Ready' ORDER BY ph.created_at DESC LIMIT 3");
$photosStmt->execute([$uid]);
$recentPhotos = $photosStmt->fetchAll();

// ============================================================
// TOTAL BOOKINGS (para sa stats display)
// ============================================================
$totalBookings = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
$totalBookings->execute([$uid]);
$totalBookingsCount = (int)$totalBookings->fetchColumn();
$hasPreviousBookings = $totalBookingsCount > 0;

// ============================================================
// DYNAMIC GREETING
// ============================================================
$greeting = $hasPreviousBookings ? 'Welcome back,' : 'Welcome,';
?>

<!-- ============================================================ -->
<!-- DASHBOARD FULLY RESPONSIVE STYLES                             -->
<!-- ============================================================ -->
<style>
/* ============================================================
   CSS VARIABLES (fallback kung wala sa global)
   ============================================================ */
:root {
    --db-border: #e5e5e5;
    --db-muted: #8a8a8a;
    --db-bg-soft: #f5f3ef;
    --db-accent: #6C63FF;
    --db-dark: #2C2C2C;
}

/* ============================================================
   BASE / MOBILE-FIRST (320px pataas)
   ============================================================ */

/* WELCOME BANNER */
.welcome-banner {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    background: var(--db-dark);
    color: #fff;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 14px;
    width: 100%;
    box-sizing: border-box;
}

.welcome-banner-text {
    min-width: 0;
    width: 100%;
    flex: 1;
}

.welcome-banner .eyebrow {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.5);
    margin-bottom: 6px;
}

.welcome-banner h2 {
    font-family: serif;
    font-size: 20px;
    line-height: 1.2;
    margin: 0 0 6px;
    font-weight: 500;
    word-break: break-word;
}

.welcome-banner p {
    font-size: 12px;
    color: rgba(255,255,255,0.65);
    margin: 0;
}

.welcome-banner-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.welcome-banner .btn-primary,
.welcome-banner .btn-ghost {
    padding: 8px 14px;
    font-size: 12px;
    border-radius: 8px;
    white-space: nowrap;
}

.welcome-banner-art {
    font-size: 36px;
    opacity: 0.9;
    flex-shrink: 0;
    align-self: flex-end;
}

/* STATS GRID — mobile first */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 14px;
}

.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid var(--db-border);
    box-sizing: border-box;
    min-width: 0;
}

.stat-eyebrow {
    font-size: 10px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--db-muted);
    margin-bottom: 6px;
}

.stat-value {
    font-family: serif;
    font-size: 26px;
    line-height: 1;
    color: #0A0A0A;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 11px;
    color: var(--db-muted);
}

/* LOYALTY CARD */
.loyalty-card {
    background: linear-gradient(135deg, #2C2C2C 0%, #4A4A4A 100%);
    color: #fff;
    padding: 16px !important;
    border: none !important;
    min-width: 0;
}

.loyalty-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.loyalty-title {
    font-family: serif;
    font-size: 14px;
    letter-spacing: 1px;
    word-break: break-word;
}

.loyalty-header .badge {
    background: rgba(255,255,255,0.2);
    color: #fff;
    border: none;
    padding: 5px 10px;
    font-size: 10px;
    border-radius: 20px;
    white-space: nowrap;
}

/* LOYALTY SLOTS — mobile: 4 cols pa rin, maliit */
.loyalty-slots {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
}

.loyalty-slot {
    border-radius: 8px;
    padding: 8px 4px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.1);
    min-width: 0;
    box-sizing: border-box;
}

.loyalty-slot .slot-icon {
    font-size: 14px;
    margin-bottom: 4px;
    line-height: 1;
}

.loyalty-slot .slot-label {
    font-size: 9px;
    font-weight: 700;
    line-height: 1.2;
    word-break: break-word;
}

.loyalty-slot .slot-sub {
    font-size: 8px;
    margin-top: 3px;
    line-height: 1.2;
}

.loyalty-next {
    margin-top: 12px;
    font-size: 11px;
    color: rgba(255,255,255,0.5);
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 10px;
    word-break: break-word;
}

.loyalty-earned {
    margin-top: 8px;
    font-size: 11px;
    color: rgba(255,255,255,0.7);
    word-break: break-word;
}

/* GRID 2 */
.grid-2 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

/* CARDS */
.card {
    padding: 14px 16px;
    border-radius: 12px;
    box-sizing: border-box;
    min-width: 0;
    overflow: hidden;
}

.card-title {
    font-size: 14px;
    margin-bottom: 12px;
}

/* TABLE WRAP — scrollable sa mobile */
.table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 8px;
}

.table-wrap table {
    width: 100%;
    min-width: 480px;
    font-size: 12px;
    border-collapse: collapse;
}

.table-wrap th,
.table-wrap td {
    padding: 8px 10px;
    text-align: left;
    white-space: nowrap;
}

/* RECENT PHOTOS GRID */
.recent-photos-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.recent-photos-grid > div {
    aspect-ratio: 1;
    border-radius: 10px;
    background: var(--db-bg-soft);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

/* ============================================================
   SMALL PHONE (min-width: 380px)
   ============================================================ */
@media (min-width: 380px) {
    .welcome-banner h2 { font-size: 21px; }
    .welcome-banner-art { font-size: 40px; }

    .loyalty-slot .slot-icon { font-size: 16px; }
    .loyalty-slot .slot-label { font-size: 10px; }
    .loyalty-slot .slot-sub { font-size: 9px; }

    .recent-photos-grid > div { font-size: 28px; }
}

/* ============================================================
   LARGE PHONE / SMALL TABLET (min-width: 600px)
   ============================================================ */
@media (min-width: 600px) {
    .welcome-banner {
        flex-direction: row;
        align-items: center;
        padding: 20px 24px;
        border-radius: 14px;
    }

    .welcome-banner h2 { font-size: 24px; }
    .welcome-banner-art { font-size: 48px; align-self: center; }

    /* Stats grid: bookings + loyalty side by side */
    .stats-grid {
        grid-template-columns: 1fr 2fr;
        gap: 14px;
    }

    .loyalty-card {
        grid-column: span 1 !important;
    }

    .loyalty-slot .slot-icon { font-size: 18px; }
    .loyalty-slot .slot-label { font-size: 11px; }
    .loyalty-slot .slot-sub { font-size: 10px; }

    .grid-2 {
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .card { padding: 16px 18px; }
    .card-title { font-size: 15px; }

    .recent-photos-grid > div { font-size: 32px; }
}

/* ============================================================
   TABLET (min-width: 768px)
   ============================================================ */
@media (min-width: 768px) {
    .welcome-banner {
        padding: 24px 28px;
        border-radius: 16px;
        gap: 20px;
    }

    .welcome-banner h2 { font-size: 26px; }
    .welcome-banner p { font-size: 13px; }
    .welcome-banner-art { font-size: 52px; }

    .welcome-banner .btn-primary,
    .welcome-banner .btn-ghost {
        padding: 9px 16px;
        font-size: 13px;
    }

    .stats-grid {
        grid-template-columns: 1fr 3fr;
        gap: 16px;
    }

    .stat-card { padding: 18px 20px; border-radius: 14px; }
    .stat-value { font-size: 30px; }
    .stat-eyebrow { font-size: 11px; }
    .stat-label { font-size: 12px; }

    .loyalty-card { padding: 20px !important; }
    .loyalty-title { font-size: 16px; }

    .loyalty-slots { gap: 10px; }
    .loyalty-slot { padding: 12px 8px; border-radius: 10px; }
    .loyalty-slot .slot-icon { font-size: 20px; margin-bottom: 6px; }
    .loyalty-slot .slot-label { font-size: 11px; }
    .loyalty-slot .slot-sub { font-size: 10px; }

    .loyalty-next { font-size: 12px; }
    .loyalty-earned { font-size: 12px; }

    .grid-2 { gap: 16px; }
    .card { padding: 20px 22px; border-radius: 14px; }
    .card-title { font-size: 16px; }

    .table-wrap table { font-size: 13px; min-width: 520px; }
    .table-wrap th,
    .table-wrap td { padding: 10px 12px; }

    .recent-photos-grid { gap: 10px; }
    .recent-photos-grid > div { font-size: 36px; border-radius: 12px; }
}

/* ============================================================
   LAPTOP / DESKTOP (min-width: 1024px)
   ============================================================ */
@media (min-width: 1024px) {
    .welcome-banner {
        padding: 28px 32px;
        gap: 24px;
    }

    .welcome-banner h2 { font-size: 28px; }
    .welcome-banner-art { font-size: 56px; }

    .stats-grid { gap: 18px; }

    .stat-card { padding: 20px 22px; }
    .stat-value { font-size: 32px; }

    .loyalty-card { padding: 22px !important; }

    .loyalty-slots { gap: 12px; }
    .loyalty-slot { padding: 14px 10px; }
    .loyalty-slot .slot-icon { font-size: 22px; }

    .grid-2 { gap: 18px; }
    .card { padding: 22px 24px; }

    .recent-photos-grid > div { font-size: 40px; }
}

/* ============================================================
   LARGE DESKTOP (min-width: 1440px)
   ============================================================ */
@media (min-width: 1440px) {
    .welcome-banner {
        padding: 32px 40px;
        border-radius: 18px;
    }

    .welcome-banner h2 { font-size: 32px; }
    .welcome-banner p { font-size: 14px; }
    .welcome-banner-art { font-size: 64px; }

    .stats-grid { gap: 20px; }
    .stat-card { padding: 24px 28px; border-radius: 16px; }
    .stat-value { font-size: 36px; }

    .loyalty-card { padding: 28px !important; }
    .loyalty-title { font-size: 18px; }

    .loyalty-slots { gap: 14px; }
    .loyalty-slot { padding: 16px 12px; }
    .loyalty-slot .slot-icon { font-size: 24px; }
    .loyalty-slot .slot-label { font-size: 12px; }
    .loyalty-slot .slot-sub { font-size: 11px; }

    .grid-2 { gap: 20px; }
    .card { padding: 26px 28px; border-radius: 16px; }
    .card-title { font-size: 17px; }

    .recent-photos-grid > div { font-size: 44px; }
}

/* ============================================================
   ULTRAWIDE (min-width: 1920px)
   ============================================================ */
@media (min-width: 1920px) {
    .welcome-banner { padding: 36px 48px; }
    .welcome-banner h2 { font-size: 36px; }
    .welcome-banner-art { font-size: 72px; }

    .stat-card { padding: 28px 32px; }
    .stat-value { font-size: 40px; }

    .loyalty-card { padding: 32px !important; }
    .loyalty-slots { gap: 16px; }
    .loyalty-slot { padding: 18px 14px; }

    .card { padding: 30px 32px; }
}

/* ============================================================
   LANDSCAPE MOBILE (mababa ang height)
   ============================================================ */
@media (max-height: 500px) and (orientation: landscape) {
    .welcome-banner { padding: 12px 16px; }
    .welcome-banner h2 { font-size: 18px; }
    .welcome-banner-art { font-size: 32px; }
}

/* ============================================================
   PRINT
   ============================================================ */
@media print {
    .welcome-banner-art,
    .welcome-banner-actions,
    .btn-primary,
    .btn-ghost { display: none !important; }

    .welcome-banner { background: #fff !important; color: #000 !important; border: 1px solid #ccc; }
    .welcome-banner h2,
    .welcome-banner p,
    .welcome-banner .eyebrow { color: #000 !important; }

    .card, .stat-card, .loyalty-card { break-inside: avoid; }
}
</style>

<!-- ============================================================ -->
<!-- WELCOME BANNER                                                -->
<!-- ============================================================ -->
<div class="welcome-banner">
  <div class="welcome-banner-text">
    <div class="eyebrow">Good <?= date('G') < 12 ? 'morning' : (date('G') < 17 ? 'afternoon' : 'evening') ?></div>
    <h2><?= $greeting ?><br/><?= clean(explode(' ', $user['name'])[0]) ?>.</h2>
    <p><?= $nextSession ? 'You have 1 upcoming session.' : 'No upcoming sessions scheduled.' ?></p>
    <div class="welcome-banner-actions">
      <a href="index.php?page=booking" class="btn-primary">Book a Session</a>
      <a href="index.php?page=photos" class="btn-ghost" style="color:rgba(255,255,255,0.7);border-color:rgba(255,255,255,0.2);">View Photos</a>
    </div>
  </div>
  <div class="welcome-banner-art">📸</div>
</div>

<!-- ============================================================ -->
<!-- STATS + LOYALTY CARD                                          -->
<!-- ============================================================ -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-eyebrow">Total Bookings</div>
    <div class="stat-value"><?= $totalBookingsCount ?></div>
    <div class="stat-label">All time sessions</div>
  </div>

  <!-- Loyalty Card -->
  <div class="stat-card loyalty-card">

    <!-- Header -->
    <div class="loyalty-header">
      <div class="loyalty-title">STUDIO 94 LOYALTY CARD</div>
      <span class="badge"><?= $bookingCount ?> COMPLETED</span>
    </div>

    <!-- Progress slots -->
    <div class="loyalty-slots">
      <?php foreach ($tiers as $count => $tier): ?>
        <?php $earned = $bookingCount >= $count; ?>
        <div class="loyalty-slot" style="background:<?= $earned?'rgba(255,255,255,0.25)':'rgba(255,255,255,0.07)' ?>;border:1px solid <?= $earned?'rgba(255,255,255,0.3)':'rgba(255,255,255,0.1)' ?>;">
          <div class="slot-icon"><?= $earned ? '✅' : '⬜' ?></div>
          <div class="slot-label" style="color:<?= $earned?'rgba(255,255,255,0.95)':'rgba(255,255,255,0.4)' ?>;">
            <?= $tier['label'] ?>
          </div>
          <div class="slot-sub" style="color:<?= $earned?'rgba(255,255,255,0.7)':'rgba(255,255,255,0.3)' ?>;">
            <?= $count ?> bookings
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Next reward -->
    <div class="loyalty-next">
      Next reward: <strong style="color:white;"><?= $progress['next'] ?></strong>
    </div>

    <!-- Already earned rewards -->
    <?php if (!empty($rewards)): ?>
      <div class="loyalty-earned">
        ✅ Entitled: <strong style="color:white;"><?= implode(', ', $rewards) ?></strong>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============================================================ -->
<!-- GRID 2 — Upcoming + Recent Photos                             -->
<!-- ============================================================ -->
<div class="grid-2">

  <!-- Upcoming Session -->
  <div class="card">
    <div class="card-title">Upcoming Session</div>
    <?php if ($nextSession): ?>
    <div class="booking-item">
      <div class="booking-date">
        <div class="day"><?= date('j', strtotime($nextSession['date'])) ?></div>
        <div class="month"><?= date('M', strtotime($nextSession['date'])) ?></div>
      </div>
      <div class="booking-info">
        <div class="pkg"><?= clean($nextSession['pkg_name']) ?> — <?= clean($nextSession['duration']) ?></div>
        <div class="time"><?= clean($nextSession['time']) ?></div>
      </div>
      <?= statusBadge($nextSession['status']) ?>
    </div>
    <div style="margin-top:14px;display:flex;gap:8px;">
      <a href="index.php?page=bookings" class="btn-ghost btn-sm">View Details</a>
    </div>
    <?php else: ?>
      <p style="color:var(--muted);font-size:13px;">No upcoming sessions.
        <a href="index.php?page=booking" class="link-text">Book one →</a></p>
    <?php endif; ?>
  </div>

  <!-- Recent Photos -->
  <div class="card">
    <div class="section-header">
      <h3>Recent Photos</h3>
      <a href="index.php?page=photos" class="link-text">View all →</a>
    </div>
    <?php if (!empty($recentPhotos)): ?>
      <div class="recent-photos-grid">
        <?php foreach ($recentPhotos as $ph): ?>
          <div>📷</div>
        <?php endforeach; ?>
      </div>
      <a href="index.php?page=photos" class="btn-primary full-width" style="margin-top:14px;">Download Photos</a>
    <?php else: ?>
      <p style="color:var(--muted);font-size:13px;">No photos available yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ============================================================ -->
<!-- BOOKING HISTORY                                               -->
<!-- ============================================================ -->
<div class="card" style="margin-top:20px;">
  <div class="section-header">
    <h3>Booking History</h3>
    <a href="index.php?page=bookings" class="link-text">View all →</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Package</th>
          <th>Date</th>
          <th>Time</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentBookings as $b): ?>
        <tr>
          <td><strong><?= clean($b['pkg_name']) ?></strong></td>
          <td><?= formatDate($b['date']) ?></td>
          <td><?= clean($b['time']) ?></td>
          <td><?= statusBadge($b['status']) ?></td>
          <td><a href="index.php?page=bookings" class="btn-ghost btn-sm">Details</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentBookings)): ?>
          <tr>
            <td colspan="5" style="text-align:center;color:var(--muted);padding:30px;">
              No bookings yet.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================ -->
<!-- CHATBOT INCLUDE                                               -->
<!-- ============================================================ -->
<?php require_once __DIR__ . '/../../includes/chatbot.php'; ?>
