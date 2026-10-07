<?php
requireRole('admin');
$pdo = db();

// ============================================================
// RESERVATION FEE (fixed amount)
// ============================================================
const RESERVATION_FEE = 100;

$filter = $_GET['status'] ?? 'all';

// ============================================================
// STATS
// ============================================================
// Total revenue = all PAID payments (reservation + balance combined)
$totalRev = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) 
    FROM payments 
    WHERE status = 'PAID'
")->fetchColumn();

$pendingCnt = $pdo->query("
    SELECT COUNT(*) 
    FROM payments 
    WHERE status = 'PENDING'
")->fetchColumn();

// Collected = same as total revenue (money received)
$collected = $totalRev;

// Unpaid reservations = count of bookings that haven't paid reservation yet
$unpaid = $pdo->query("
    SELECT COALESCE(SUM(" . RESERVATION_FEE . "), 0) 
    FROM bookings 
    WHERE deposit_paid = 0 
      AND status IN ('Awaiting Approval', 'Approved (Unpaid)')
")->fetchColumn();

// Refunded
$refundedAmt = $pdo->query("
    SELECT COALESCE(SUM(refund_amount), 0) 
    FROM payments 
    WHERE status = 'REFUNDED'
")->fetchColumn();

// Pending balance count (bookings with reservation paid but not fully paid)
$pendingBalanceCnt = $pdo->query("
    SELECT COUNT(*) 
    FROM bookings 
    WHERE deposit_paid = 1 
      AND fully_paid = 0 
      AND status IN ('Deposit Paid', 'Confirmed')
")->fetchColumn();

// ============================================================
// GET PAYMENTS
// ============================================================
$where  = ['1=1'];
$params = [];

if ($filter !== 'all') {
    $where[]  = 'p.status = ?';
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT p.*, 
           u.name  AS client_name, 
           u.email AS client_email,
           b.booking_ref, 
           b.date  AS booking_date,
           b.type  AS booking_type,
           b.package_price,
           b.deposit_paid,
           b.fully_paid,
           b.remaining_balance,
           pkg.name AS pkg_name,
           refunder.name AS refunded_by_name,
           verifier.name AS verified_by_name
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN bookings b ON p.booking_id = b.id
    JOIN packages pkg ON b.package_id = pkg.id
    LEFT JOIN users refunder ON p.refunded_by = refunder.id
    LEFT JOIN users verifier ON p.verified_by = verifier.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.created_at DESC, p.id DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<!-- ============================================================ -->
<!-- STATS -->
<!-- ============================================================ -->
<div class="stats-grid" style="margin-bottom:20px;display:grid;grid-template-columns:repeat(5,1fr);gap:14px;">
  <div class="stat-card">
    <div class="stat-eyebrow">Total Revenue</div>
    <div class="stat-value" style="color:var(--green-text);"><?= formatMoney($totalRev) ?></div>
    <div class="stat-label">All time</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Pending</div>
    <div class="stat-value" style="color:var(--amber-text);"><?= $pendingCnt ?></div>
    <div class="stat-label">Awaiting verification</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Collected</div>
    <div class="stat-value"><?= formatMoney($collected) ?></div>
    <div class="stat-label">Reservations + balances</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Unpaid Reservations</div>
    <div class="stat-value" style="color:var(--red-text);"><?= formatMoney($unpaid) ?></div>
    <div class="stat-label">₱100 × unpaid bookings</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Refunded</div>
    <div class="stat-value" style="color:var(--muted);"><?= formatMoney($refundedAmt) ?></div>
    <div class="stat-label">Total refunds</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- PAYMENTS TABLE -->
<!-- ============================================================ -->
<div class="card">
  <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <h3>
      Payment Transactions 
      <span style="font-size:11px;color:var(--muted);font-weight:400;">⬇️ Newest first</span>
    </h3>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <?php foreach (['all','PENDING','PAID','REJECTED','UNPAID','REFUNDED'] as $s): ?>
        <a href="index.php?page=payments&status=<?= $s ?>"
           class="btn-sm <?= $filter === $s ? 'btn-primary' : 'btn-ghost' ?>">
          <?= $s === 'all' ? 'All' : $s ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Payment ID</th>
          <th>Client</th>
          <th>Type</th>
          <th>Source</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Ref #</th>
          <th>Status</th>
          <th>Paid On</th>
          <th>Refund Info</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): 

          // ====================================================
          // DETECT SOURCE
          // ====================================================
          $isWalkin = (strpos($p['booking_ref'], 'WALK') === 0) 
                      || (strpos($p['client_email'] ?? '', 'walkin_') !== false)
                      || ($p['booking_type'] === 'walk-in');
          
          $method = strtolower($p['method'] ?? '');
          $isOnsite = ($method === 'cash') || (strpos(strtolower($p['ref_number'] ?? ''), 'on-site') !== false);
          
          if ($isOnsite) {
            $sourceIcon  = '🏢';
            $sourceLabel = 'On-site';
            $sourceColor = '#F59E0B';
          } elseif ($isWalkin) {
            $sourceIcon  = '🚶';
            $sourceLabel = 'Walk-in';
            $sourceColor = '#8B5CF6';
          } else {
            $sourceIcon  = '🌐';
            $sourceLabel = 'Online';
            $sourceColor = '#10B981';
          }

          // ====================================================
          // DETECT PAYMENT TYPE (RESERVATION / BALANCE / FULL)
          // ====================================================
          $paymentType = strtoupper($p['type'] ?? 'RESERVATION');
          
          if ($paymentType === 'BALANCE') {
            $typeLabel = '💰 Balance';
            $typeBg    = '#FFF3E0';
            $typeColor = '#B8860B';
            $typeBorder= '#FFB74D';
          } elseif ($paymentType === 'FULL') {
            $typeLabel = '💳 Full';
            $typeBg    = '#E8EAF6';
            $typeColor = '#3949AB';
            $typeBorder= '#9FA8DA';
          } else {
            $typeLabel = '🎫 Reservation';
            $typeBg    = '#E8F5E9';
            $typeColor = '#2E7D32';
            $typeBorder= '#A5D6A7';
          }

          // ====================================================
          // FOR BOOKING REFERENCE
          // ====================================================
          $depositPaid = (int)($p['deposit_paid'] ?? 0);
          $fullyPaid   = (int)($p['fully_paid'] ?? 0);

        ?>
        <tr>
          <td>
            <code style="font-size:11px;"><?= clean($p['payment_ref']) ?></code>
          </td>
          
          <td>
            <strong><?= clean($p['client_name']) ?></strong>
            <div style="font-size:11px;color:var(--muted);"><?= clean($p['client_email']) ?></div>
          </td>

          <!-- TYPE COLUMN -->
          <td>
            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:600;background:<?= $typeBg ?>;color:<?= $typeColor ?>;border:1px solid <?= $typeBorder ?>;white-space:nowrap;">
              <?= $typeLabel ?>
            </span>
          </td>

          <!-- SOURCE COLUMN -->
          <td>
            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:600;background:<?= $sourceColor ?>20;color:<?= $sourceColor ?>;border:1px solid <?= $sourceColor ?>40;white-space:nowrap;">
              <?= $sourceIcon ?> <?= $sourceLabel ?>
            </span>
          </td>

          <td>
            <strong><?= formatMoney($p['amount']) ?></strong>
          </td>

          <td><?= clean($p['method'] ?? '—') ?></td>

          <td>
            <code style="font-size:11px;"><?= clean($p['ref_number'] ?? '—') ?></code>
          </td>

          <td><?= statusBadge($p['status']) ?></td>

          <!-- PAID ON COLUMN -->
          <td style="font-size:12px;">
            <?php if ($p['status'] === 'PAID' && !empty($p['verified_at'])): ?>
              <div style="font-weight:600;color:var(--green-text);">
                <?= date('M j, Y', strtotime($p['verified_at'])) ?>
              </div>
              <div style="font-size:10px;color:var(--muted);">
                <?= date('g:i A', strtotime($p['verified_at'])) ?>
              </div>
              <?php if (!empty($p['verified_by_name'])): ?>
                <div style="font-size:10px;color:var(--muted);">by <?= clean($p['verified_by_name']) ?></div>
              <?php endif; ?>
            <?php elseif (!empty($p['created_at'])): ?>
              <div style="color:var(--muted);">
                <?= date('M j, Y', strtotime($p['created_at'])) ?>
              </div>
              <div style="font-size:10px;color:var(--muted);">
                <?= date('g:i A', strtotime($p['created_at'])) ?>
              </div>
              <div style="font-size:10px;color:var(--amber-text);">⏳ Not yet verified</div>
            <?php else: ?>
              <span style="color:var(--muted);">—</span>
            <?php endif; ?>
          </td>

          <!-- REFUND INFO -->
          <td>
            <?php if ($p['status'] === 'REFUNDED'): ?>
              <div style="font-size:11px;line-height:1.5;">
                <div><strong style="color:var(--red-text);">💸 <?= formatMoney($p['refund_amount'] ?? 0) ?></strong></div>
                <?php if ($p['refund_date']): ?>
                  <div style="color:var(--muted);"><?= date('M j, Y', strtotime($p['refund_date'])) ?></div>
                <?php endif; ?>
                <?php if ($p['refunded_by_name']): ?>
                  <div style="color:var(--muted);">By: <?= clean($p['refunded_by_name']) ?></div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span style="color:var(--muted);font-size:12px;">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($payments)): ?>
          <tr>
            <td colspan="10" style="text-align:center;color:var(--muted);padding:30px;">
              📭 No payments found.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================ -->
<!-- PAYMENT POLICY -->
<!-- ============================================================ -->
<div class="card" style="margin-top:20px;">
  <div class="card-title"><strong>💳 Payment Policy</strong></div>

  <div style="display:flex;flex-direction:column;gap:12px;">

    <div style="display:flex;align-items:center;gap:16px;padding:16px;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border-radius:12px;border:1px solid var(--green);">
      <div style="width:50px;height:50px;background:var(--green);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;">₱100</div>
      <div>
        <div style="font-weight:600;color:var(--green-text);">₱100 Reservation Fee</div>
        <div style="font-size:12px;color:var(--dark2);">Flat reservation fee to confirm booking. Non-refundable.</div>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:16px;padding:16px;background:var(--amber-bg);border-radius:12px;">
      <div style="width:50px;height:50px;background:var(--amber);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--amber-text);font-weight:700;font-size:16px;">💰</div>
      <div>
        <div style="font-weight:600;color:var(--amber-text);">Remaining Balance</div>
        <div style="font-size:12px;color:var(--dark2);">Paid on shoot day — package price minus ₱100 reservation fee.</div>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:16px;padding:16px;background:var(--red-bg);border-radius:12px;">
      <div style="width:50px;height:50px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;">🚫</div>
      <div>
        <div style="font-weight:600;color:var(--red-text);">No Refund Policy</div>
        <div style="font-size:12px;color:var(--dark2);">All payments are final. Reservation fee is non-refundable once confirmed.</div>
      </div>
    </div>

  </div>

  <?php if ($pendingBalanceCnt > 0): ?>
    <div style="margin-top:16px;padding:12px 16px;background:#FFF8E1;border-left:4px solid #F59E0B;border-radius:8px;font-size:13px;color:#92400E;">
      ⚠️ <strong><?= $pendingBalanceCnt ?></strong> booking<?= $pendingBalanceCnt > 1 ? 's' : '' ?> with pending balance payments.
    </div>
  <?php endif; ?>
</div>

<style>
  @media (max-width: 1200px) {
    .stats-grid[style*="grid-template-columns:repeat(5"] {
      grid-template-columns: repeat(3, 1fr) !important;
    }
  }
  @media (max-width: 768px) {
    .stats-grid[style*="grid-template-columns:repeat(5"] {
      grid-template-columns: repeat(2, 1fr) !important;
    }
  }
</style>