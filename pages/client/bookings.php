<?php
requireRole('client');
$pdo = db();
$uid = $user['id'];

// ============================================================
// RESERVATION FEE
// ============================================================
const RESERVATION_FEE = 100;

// ============================================================
// HANDLE CANCEL REQUEST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    verifyCsrf();
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($bookingId) {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$bookingId, $uid]);
        $booking = $stmt->fetch();

        if (!$booking) {
            setFlash('error', 'Booking not found.');
        } elseif (in_array($booking['status'], ['Completed', 'Cancelled', 'Rejected'])) {
            setFlash('error', 'This booking cannot be cancelled.');
        } else {
            $shootDate = new DateTime($booking['date']);
            $today     = new DateTime(date('Y-m-d'));
            $daysLeft  = (int)$today->diff($shootDate)->format('%r%a');

            if ($daysLeft < 1) {
                setFlash('error', 'Sorry, cancellations are not allowed 1 day before or on the shoot day. Please contact the studio.');
            } else {
                $upd = $pdo->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?");
                $upd->execute([$bookingId]);

                $pdo->prepare("UPDATE payments SET status = 'CANCELLED' WHERE booking_id = ? AND status IN ('UNPAID', 'PENDING')")
                    ->execute([$bookingId]);

                addNotificationByRole('admin', '❌ Booking Cancelled',
                    $user['name'] . ' cancelled booking ' . $booking['booking_ref'], 'booking', '❌');
                addNotificationByRole('staff', '❌ Booking Cancelled',
                    $user['name'] . ' cancelled booking ' . $booking['booking_ref'], 'booking', '❌');

                setFlash('success', '✅ Booking cancelled. Reservation fee is non-refundable.');
            }
        }
    }

    header('Location: index.php?page=bookings');
    exit;
}

// ============================================================
// GET LOYALTY INFO
// ============================================================
$lcStmt = $pdo->prepare("SELECT total_bookings, card_number FROM loyalty_cards WHERE user_id = ? LIMIT 1");
$lcStmt->execute([$uid]);
$lc = $lcStmt->fetch();
$loyaltyCount = $lc ? (int)$lc['total_bookings'] : 0;
$hasLoyaltyDiscount = ($loyaltyCount >= 10);

// ============================================================
// GET ALL BOOKINGS + PAYMENT STATUS
// ============================================================
$stmt = $pdo->prepare("
    SELECT b.*, 
           p.name AS pkg_name,
           p.duration AS pkg_duration,
           pk.name AS main_pkg_name,
           py.status AS payment_status,
           py.amount AS payment_amount,
           py.id AS payment_id
    FROM bookings b
    LEFT JOIN packages p ON b.package_id = p.id
    LEFT JOIN packages pk ON p.parent_id = pk.id
    LEFT JOIN payments py ON py.booking_id = b.id AND py.type = 'RESERVATION'
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$uid]);
$bookings = $stmt->fetchAll();

// ============================================================
// STATS
// ============================================================
$stats = [
    'total' => count($bookings),
    'pending' => 0,
    'approved' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

foreach ($bookings as $b) {
    if ($b['status'] === 'Awaiting Approval') $stats['pending']++;
    elseif (in_array($b['status'], ['Approved (Unpaid)', 'Deposit Paid', 'Confirmed'])) $stats['approved']++;
    elseif ($b['status'] === 'Completed') $stats['completed']++;
    elseif (in_array($b['status'], ['Cancelled', 'Rejected'])) $stats['cancelled']++;
}

// ============================================================
// STATUS BADGE HELPER
// ============================================================
function bookingStatusBadge($status) {
    $map = [
        'Awaiting Approval' => ['badge-amber', '⏳ Awaiting Approval'],
        'Approved (Unpaid)' => ['badge-amber', '🟡 Approved (Unpaid)'],
        'Deposit Paid'      => ['badge-green', '💳 Reservation Paid'],
        'Confirmed'         => ['badge-green', '✅ Confirmed'],
        'Completed'         => ['badge-green', '🎉 Completed'],
        'Cancelled'         => ['badge-red', '❌ Cancelled'],
        'Rejected'          => ['badge-red', '❌ Rejected'],
    ];
    $info = $map[$status] ?? ['badge-gray', htmlspecialchars($status)];
    return '<span class="badge ' . $info[0] . '">' . $info[1] . '</span>';
}
?>

<!-- ============================================================ -->
<!-- FULLY RESPONSIVE STYLES                                       -->
<!-- ============================================================ -->
<style>
/* ============================================================
   BASE (MOBILE-FIRST) — 320px pataas
   ============================================================ */

.page-banner {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    background: linear-gradient(135deg, #2C2C2C, #4A4A4A);
    color: #fff;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 14px;
    width: 100%;
    box-sizing: border-box;
}
.page-banner-text { width: 100%; min-width: 0; }
.page-banner .eyebrow {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    opacity: 0.6;
    margin-bottom: 6px;
}
.page-banner h2 {
    font-size: 18px;
    margin: 0 0 6px;
    line-height: 1.25;
    word-break: break-word;
}
.page-banner p {
    font-size: 12px;
    opacity: 0.8;
    margin: 0;
    line-height: 1.4;
}
.page-banner-art {
    font-size: 32px;
    align-self: flex-end;
}

.loyalty-banner-card {
    margin-bottom: 14px;
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-sizing: border-box;
    border: 2px solid;
}
.loyalty-banner-card .loyalty-icon { font-size: 30px; flex-shrink: 0; }
.loyalty-banner-card .loyalty-body {
    flex: 1 1 100%;
    min-width: 0;
}
.loyalty-banner-card .loyalty-title {
    font-weight: 700;
    font-size: 14px;
    line-height: 1.35;
    word-break: break-word;
}
.loyalty-banner-card .loyalty-desc {
    font-size: 12px;
    margin-top: 4px;
    line-height: 1.4;
    word-break: break-word;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-bottom: 14px;
}
.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    min-width: 0;
    box-sizing: border-box;
}
.stat-card .stat-eyebrow {
    font-size: 9px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 4px;
}
.stat-card .stat-value {
    font-family: serif;
    font-size: 22px;
    line-height: 1;
    color: var(--dark);
    margin-bottom: 2px;
}
.stat-card .stat-label {
    font-size: 10px;
    color: var(--muted);
}

.section-header-responsive {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.section-header-responsive h3 {
    font-size: 15px;
    margin: 0;
    word-break: break-word;
}

.bookings-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.booking-item-card {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: white;
    box-sizing: border-box;
    min-width: 0;
}

.booking-card-header {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.booking-info-col {
    flex: 1;
    min-width: 0;
}

.booking-info-col .pkg-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}
.booking-info-col .pkg-name {
    font-weight: 700;
    font-size: 15px;
    word-break: break-word;
}
.booking-info-col .ref-line {
    font-size: 11px;
    color: var(--muted);
    margin-bottom: 4px;
    word-break: break-word;
    line-height: 1.4;
}
.booking-info-col .ref-line strong { color: var(--dark); }

.booking-info-col .meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 14px;
    font-size: 12px;
    color: var(--dark2);
    margin-top: 8px;
}
.booking-info-col .meta-row > div {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.booking-info-col .notes-box {
    margin-top: 8px;
    padding: 8px 10px;
    background: var(--bg-soft);
    border-radius: 6px;
    font-size: 11px;
    color: var(--dark2);
    line-height: 1.4;
    word-break: break-word;
}

.booking-amounts-col {
    min-width: 0;
    text-align: left;
}
.booking-amounts-col .price-main {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}
.booking-amounts-col .price-strike {
    font-size: 11px;
    color: var(--green-text);
    text-decoration: line-through;
}
.booking-amounts-col .amounts-divider {
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px dashed var(--border);
}
.booking-amounts-col .amount-row {
    font-size: 11px;
    color: var(--muted);
    line-height: 1.5;
    word-break: break-word;
}

.booking-actions {
    margin-top: 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.booking-actions .btn-primary,
.booking-actions .btn-ghost,
.booking-actions button {
    width: 100%;
    text-align: center;
    justify-content: center;
    box-sizing: border-box;
}

.booking-footer {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border-light);
    font-size: 10px;
    color: var(--muted);
    word-break: break-word;
}

.empty-state {
    text-align: center;
    padding: 40px 16px;
}
.empty-state .empty-icon { font-size: 52px; margin-bottom: 10px; }
.empty-state .empty-title { font-size: 16px; font-weight: 600; margin-bottom: 6px; }
.empty-state .empty-desc { color: var(--muted); margin-bottom: 16px; font-size: 13px; }

.loyalty-pill {
    background: linear-gradient(135deg, #EAB308, #CA8A04);
    color: #fff;
    padding: 2px 10px;
    border-radius: 50px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

@media (min-width: 380px) {
    .page-banner h2 { font-size: 19px; }
    .stat-card .stat-value { font-size: 24px; }
    .booking-info-col .pkg-name { font-size: 16px; }
    .booking-amounts-col .price-main { font-size: 22px; }
}

@media (min-width: 600px) {
    .page-banner {
        flex-direction: row;
        align-items: center;
        padding: 20px 24px;
        border-radius: 14px;
    }
    .page-banner h2 { font-size: 22px; }
    .page-banner-art { font-size: 40px; align-self: center; }

    .loyalty-banner-card {
        padding: 16px 20px;
        border-radius: 14px;
        flex-wrap: nowrap;
    }
    .loyalty-banner-card .loyalty-icon { font-size: 34px; }
    .loyalty-banner-card .loyalty-body { flex: 1; }
    .loyalty-banner-card .loyalty-title { font-size: 15px; }

    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 16px;
    }
    .stat-card { padding: 14px 16px; border-radius: 12px; }
    .stat-card .stat-value { font-size: 26px; }

    .booking-item-card { padding: 16px 18px; border-radius: 14px; }

    .booking-card-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
    }
    .booking-amounts-col {
        text-align: right;
        min-width: 200px;
        flex-shrink: 0;
    }
    .booking-actions { align-items: stretch; }
}

@media (min-width: 768px) {
    .page-banner { padding: 24px 28px; border-radius: 16px; gap: 20px; }
    .page-banner h2 { font-size: 24px; }
    .page-banner p { font-size: 13px; }
    .page-banner-art { font-size: 48px; }

    .loyalty-banner-card { padding: 18px 22px; }
    .loyalty-banner-card .loyalty-title { font-size: 16px; }
    .loyalty-banner-card .loyalty-desc { font-size: 13px; }

    .stats-grid { gap: 12px; }
    .stat-card { padding: 16px 18px; }
    .stat-card .stat-value { font-size: 28px; }
    .stat-card .stat-eyebrow { font-size: 10px; }
    .stat-card .stat-label { font-size: 11px; }

    .section-header-responsive h3 { font-size: 17px; }

    .bookings-list { gap: 14px; }
    .booking-item-card { padding: 18px 20px; }

    .booking-info-col .pkg-name { font-size: 17px; }
    .booking-info-col .ref-line { font-size: 12px; }
    .booking-info-col .meta-row { font-size: 13px; gap: 14px 18px; }
    .booking-info-col .notes-box { font-size: 12px; padding: 10px 12px; }

    .booking-amounts-col { min-width: 220px; }
    .booking-amounts-col .price-main { font-size: 24px; }
    .booking-amounts-col .amount-row { font-size: 12px; }

    .booking-actions { gap: 8px; }
    .booking-actions .btn-primary,
    .booking-actions .btn-ghost { padding: 10px 16px; font-size: 13px; }
}

@media (min-width: 1024px) {
    .page-banner { padding: 28px 32px; }
    .page-banner h2 { font-size: 26px; }
    .page-banner-art { font-size: 52px; }

    .loyalty-banner-card { padding: 16px 20px; }
    .loyalty-banner-card .loyalty-icon { font-size: 36px; }

    .stats-grid { gap: 14px; margin-bottom: 20px; }
    .stat-card { padding: 18px 20px; }
    .stat-card .stat-value { font-size: 30px; }

    .booking-item-card { padding: 20px 22px; }
    .booking-amounts-col { min-width: 240px; }
    .booking-amounts-col .price-main { font-size: 26px; }
    .booking-actions .btn-primary,
    .booking-actions .btn-ghost { padding: 10px 18px; font-size: 13px; }
}

@media (min-width: 1440px) {
    .page-banner { padding: 32px 40px; border-radius: 18px; }
    .page-banner h2 { font-size: 30px; }
    .page-banner p { font-size: 14px; }
    .page-banner-art { font-size: 64px; }

    .stats-grid { gap: 16px; }
    .stat-card { padding: 22px 24px; border-radius: 14px; }
    .stat-card .stat-value { font-size: 34px; }
    .stat-card .stat-eyebrow { font-size: 11px; }
    .stat-card .stat-label { font-size: 12px; }

    .booking-item-card { padding: 22px 24px; border-radius: 14px; }
    .booking-info-col .pkg-name { font-size: 18px; }
    .booking-amounts-col .price-main { font-size: 28px; }
}

@media (min-width: 1920px) {
    .page-banner { padding: 36px 48px; }
    .page-banner h2 { font-size: 34px; }

    .stat-card { padding: 26px 28px; }
    .stat-card .stat-value { font-size: 38px; }

    .booking-item-card { padding: 26px 28px; }
}

@media (max-height: 500px) and (orientation: landscape) {
    .page-banner { padding: 12px 16px; }
    .page-banner h2 { font-size: 18px; }
    .page-banner-art { font-size: 32px; }
    .stats-grid { grid-template-columns: repeat(4, 1fr); }
}

@media print {
    .page-banner-art,
    .booking-actions,
    .btn-primary,
    .btn-ghost { display: none !important; }
    .booking-item-card { break-inside: avoid; border: 1px solid #ccc; }
}
</style>

<!-- ============================================================ -->
<!-- PAGE BANNER                                                   -->
<!-- ============================================================ -->
<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">My Bookings</div>
    <h2><strong>Booking History</strong></h2>
    <p>View all your booking requests and their status.</p>
  </div>
  <div class="page-banner-art">📋</div>
</div>

<!-- ============================================================ -->
<!-- LOYALTY DISCOUNT BANNER                                       -->
<!-- ============================================================ -->
<?php if ($hasLoyaltyDiscount): ?>
<div class="loyalty-banner-card" style="background:linear-gradient(135deg,#FEF3C7,#FDE68A);border-color:#EAB308;">
    <div class="loyalty-icon">🎉</div>
    <div class="loyalty-body">
        <div class="loyalty-title" style="color:#92400E;">
            You have <strong>50% OFF</strong> Loyalty Discount!
        </div>
        <div class="loyalty-desc" style="color:#92400E;">
            🎫 You've reached <strong><?= $loyaltyCount ?> bookings</strong> — enjoy 50% OFF on your next booking!
        </div>
    </div>
</div>
<?php elseif ($loyaltyCount > 0): ?>
<div class="loyalty-banner-card" style="background:linear-gradient(135deg,#EDE9FE,#DDD6FE);border-color:#8B5CF6;">
    <div class="loyalty-icon">🎫</div>
    <div class="loyalty-body">
        <div class="loyalty-title" style="color:#5B21B6;">
            Loyalty Card: <strong><?= $loyaltyCount ?> bookings</strong>
        </div>
        <div class="loyalty-desc" style="color:#5B21B6;">
            <?php if ($loyaltyCount >= 7): ?>
                🎨 +1 BACKDROP earned! 3 more bookings to unlock <strong>50% OFF</strong>
            <?php elseif ($loyaltyCount >= 4): ?>
                🖼️ +1 PRINT earned! 3 more bookings to unlock <strong>+1 BACKDROP</strong>
            <?php elseif ($loyaltyCount >= 2): ?>
                ⏱️ +5 MINUTES earned! 2 more bookings to unlock <strong>+1 PRINT</strong>
            <?php else: ?>
                Book <?= 2 - $loyaltyCount ?> more to unlock <strong>+5 MINUTES</strong>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- STATS                                                         -->
<!-- ============================================================ -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-eyebrow">Total</div>
    <div class="stat-value"><?= $stats['total'] ?></div>
    <div class="stat-label">All bookings</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Pending</div>
    <div class="stat-value" style="color:var(--amber-text);"><?= $stats['pending'] ?></div>
    <div class="stat-label">Awaiting approval</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Approved</div>
    <div class="stat-value" style="color:var(--green-text);"><?= $stats['approved'] ?></div>
    <div class="stat-label">Confirmed</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Completed</div>
    <div class="stat-value"><?= $stats['completed'] ?></div>
    <div class="stat-label">Done</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- BOOKINGS LIST                                                 -->
<!-- ============================================================ -->
<div class="card">
  <div class="section-header-responsive">
    <h3><strong>All Bookings</strong></h3>
    <a href="index.php?page=booking" class="btn-primary btn-sm">+ New Booking</a>
  </div>

  <?php if (empty($bookings)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <div class="empty-title">No bookings yet</div>
      <div class="empty-desc">Start by booking your first session.</div>
      <a href="index.php?page=booking" class="btn-primary">📅 Book a Session</a>
    </div>
  <?php else: ?>
    <div class="bookings-list">
      <?php foreach ($bookings as $b): 
        // ============================================================
        // COMPUTE PRICES
        // ============================================================
        $originalPrice  = (float)$b['package_price'];
        $effectivePrice = $hasLoyaltyDiscount ? round($originalPrice * 0.5, 2) : $originalPrice;
        $reservationFee = RESERVATION_FEE;
        $remainingAmount = max(0, round($effectivePrice - $reservationFee, 2));

        $depositPaid = (int)($b['deposit_paid'] ?? 0);
        $fullyPaid   = (int)($b['fully_paid'] ?? 0);

        $reservationPaid = $depositPaid === 1
            || in_array($b['status'], ['Deposit Paid', 'Confirmed', 'Completed'])
            || (isset($b['payment_status']) && $b['payment_status'] === 'PAID');

        // ============================================================
        // CANCEL LOGIC
        // ============================================================
        $shootDate = new DateTime($b['date']);
        $today     = new DateTime(date('Y-m-d'));
        $daysLeft  = (int)$today->diff($shootDate)->format('%r%a');

        $canCancel = !in_array($b['status'], ['Completed', 'Cancelled', 'Rejected'])
                  && $daysLeft >= 1;
      ?>
        <div class="booking-item-card">

          <div class="booking-card-header">

            <!-- LEFT: Info -->
            <div class="booking-info-col">
              <div class="pkg-row">
                <span class="pkg-name"><?= clean($b['pkg_name'] ?? 'Package') ?></span>
                <?= bookingStatusBadge($b['status']) ?>
                
                <?php if ($hasLoyaltyDiscount): ?>
                  <span class="loyalty-pill">🎉 50% OFF</span>
                <?php endif; ?>
              </div>

              <div class="ref-line">
                <strong>Ref:</strong> <?= clean($b['booking_ref']) ?>
                <?php if (!empty($b['main_pkg_name'])): ?>
                  · <?= clean($b['main_pkg_name']) ?>
                <?php endif; ?>
              </div>

              <div class="meta-row">
                <div>📅 <strong><?= formatDate($b['date']) ?></strong></div>
                <div>⏰ <strong><?= clean($b['time']) ?></strong></div>
                <div>👥 <?= (int)$b['people'] ?> person<?= $b['people'] > 1 ? 's' : '' ?></div>
              </div>

              <?php if (!empty($b['notes'])): ?>
                <div class="notes-box">
                  📝 <?= clean($b['notes']) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- RIGHT: Amounts -->
            <div class="booking-amounts-col">
              <div class="price-main">
                <?= formatMoney($effectivePrice) ?>
              </div>
              
              <?php if ($hasLoyaltyDiscount): ?>
                <div class="price-strike">
                  <?= formatMoney($originalPrice) ?>
                </div>
              <?php endif; ?>
              
              <div class="amounts-divider">
                <!-- RESERVATION FEE -->
                <div class="amount-row">
                  🎫 Reservation Fee: <strong style="color:<?= $reservationPaid ? 'var(--green-text)' : 'var(--amber-text)' ?>;">
                    <?= $reservationPaid ? '✅' : '⚠️' ?> <?= formatMoney($reservationFee) ?>
                  </strong>
                </div>

                <!-- BALANCE -->
                <?php if ($reservationPaid): ?>
                  <div class="amount-row">
                    💰 Balance: <strong style="color:<?= $fullyPaid ? 'var(--green-text)' : 'var(--amber-text)' ?>;">
                      <?= $fullyPaid ? '🎉' : '⏳' ?> <?= formatMoney($remainingAmount) ?>
                    </strong>
                  </div>
                <?php else: ?>
                  <div class="amount-row" style="margin-top:4px;font-style:italic;">
                    💰 Balance: <span style="color:var(--muted);">🔒 Pay reservation first</span>
                  </div>
                <?php endif; ?>
              </div>

              <!-- ACTION BUTTONS -->
              <div class="booking-actions">

                <?php if ($b['status'] === 'Approved (Unpaid)'): ?>
                  <!-- ✅ PAY RESERVATION — may anchor link papuntang tamang booking card -->
                  <a href="index.php?page=payments#booking-<?= $b['id'] ?>" 
                     class="btn-primary btn-sm">
                    🎫 Pay Reservation (₱100)
                  </a>
                <?php elseif ($b['status'] === 'Deposit Paid' && !$fullyPaid && $reservationPaid): ?>
                  <!-- ✅ PAY BALANCE — may anchor link papuntang tamang booking card -->
                  <a href="index.php?page=payments#booking-<?= $b['id'] ?>" 
                     class="btn-primary btn-sm">
                    💰 Pay Balance (<?= formatMoney($remainingAmount) ?>)
                  </a>
                <?php endif; ?>

                <!-- CANCEL BUTTON -->
                <?php if ($canCancel): ?>
                  <form method="POST" style="margin:0;" 
                        onsubmit="return confirm('⚠️ Cancel this booking?\n\nBooking: <?= clean($b['booking_ref']) ?>\nShoot: <?= formatDate($b['date']) ?> at <?= clean($b['time']) ?>\n\nNote: Reservation fee is NON-REFUNDABLE.\n\nAre you sure?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn-ghost btn-sm" 
                            style="color:var(--red-text);border-color:var(--red);">
                      ❌ Cancel Booking
                    </button>
                  </form>
                <?php elseif ($daysLeft < 1 && !in_array($b['status'], ['Completed', 'Cancelled', 'Rejected'])): ?>
                  <div style="font-size:11px;color:var(--muted);text-align:center;padding:6px 0;">
                    🔒 Cancellation closed (1 day before shoot)
                  </div>
                <?php endif; ?>

              </div>
            </div>

          </div>

          <!-- FOOTER -->
          <div class="booking-footer">
            Submitted: <?= formatDateTime($b['created_at']) ?>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>