<?php
// ============================================================
// STAFF/ADMIN — Payment Verification
// White & Black Theme
// List view → Click name → Modal with details
// ============================================================

requireRole(['admin', 'staff']);
$pdo = db();

const RESERVATION_FEE = 100;

// ============================================================
// HANDLE ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action    = $_POST['action'] ?? '';
    $paymentId = cleanInt($_POST['payment_id'] ?? 0);

    if ($paymentId) {
        // VERIFY
        if ($action === 'verify') {
            $stmt = $pdo->prepare("
                SELECT p.*, b.package_price, b.deposit_amount, b.remaining_balance, b.user_id,
                       lc.total_bookings
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                LEFT JOIN loyalty_cards lc ON lc.user_id = p.user_id
                WHERE p.id = ?
            ");
            $stmt->execute([$paymentId]);
            $pay = $stmt->fetch();

            if ($pay) {
                $loyaltyCount = (int)($pay['total_bookings'] ?? 0);
                $hasDiscount  = ($loyaltyCount >= 10);
                $originalPrice  = (float)$pay['package_price'];
                $effectivePrice = $hasDiscount ? round($originalPrice * 0.5, 2) : $originalPrice;
                $paymentType = strtoupper($pay['type'] ?? 'RESERVATION');
                $remainingAmount = max(0, round($effectivePrice - RESERVATION_FEE, 2));

                if ($paymentType === 'BALANCE') {
                    $verifiedAmount  = (float)$pay['amount'];
                    $remainingAmount = 0;
                    $pdo->prepare("UPDATE bookings SET deposit_paid=1, fully_paid=1, remaining_balance=0, status='Confirmed' WHERE id=?")
                        ->execute([$pay['booking_id']]);
                    addNotification($pay['user_id'], '🎉 Fully Paid',
                        'Your balance of ₱' . number_format($verifiedAmount, 2) . ' has been verified! Booking is now fully paid.',
                        'payment', '🎉');
                } elseif ($paymentType === 'FULL') {
                    $verifiedAmount  = $effectivePrice;
                    $remainingAmount = 0;
                    $pdo->prepare("UPDATE bookings SET deposit_paid=1, fully_paid=1, remaining_balance=0, status='Confirmed' WHERE id=?")
                        ->execute([$pay['booking_id']]);
                    addNotification($pay['user_id'], '🎉 Fully Paid',
                        'Your full payment of ₱' . number_format($verifiedAmount, 2) . ' has been verified!',
                        'payment', '💰');
                } else {
                    $verifiedAmount = RESERVATION_FEE;
                    $pdo->prepare("UPDATE bookings SET deposit_paid=1, status='Deposit Paid', remaining_balance=? WHERE id=?")
                        ->execute([$remainingAmount, $pay['booking_id']]);
                    addNotification($pay['user_id'], '🎫 Reservation Verified',
                        'Your ₱' . number_format($verifiedAmount, 2) . ' reservation fee has been verified! Remaining balance: ₱' . number_format($remainingAmount, 2),
                        'payment', '✅');
                }

                $pdo->prepare("UPDATE payments SET status='PAID', verified_by=?, verified_at=NOW(), amount=? WHERE id=?")
                    ->execute([$_SESSION['user_id'], $verifiedAmount, $paymentId]);

                setFlash('success', 'Payment verified successfully!');
            }
        }

        // REJECT
        if ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? 'Invalid proof');
            $stmt   = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$paymentId]);
            $pay = $stmt->fetch();

            if ($pay) {
                $pdo->prepare("UPDATE payments SET status='REJECTED', rejection_reason=?, verified_by=?, verified_at=NOW() WHERE id=?")
                    ->execute([$reason, $_SESSION['user_id'], $paymentId]);
                $typeLabel = strtoupper($pay['type'] ?? '') === 'BALANCE' ? 'Balance payment' : 'Payment';
                addNotification($pay['user_id'], "❌ {$typeLabel} Rejected",
                    "Your {$typeLabel} was rejected: {$reason} Please re-submit.", 'payment', '❌');
            }
            setFlash('success', 'Payment rejected.');
        }

        // REFUND
        if ($action === 'refund') {
            $refundRef = trim($_POST['refund_reference'] ?? '');
            $stmt      = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$paymentId]);
            $pay = $stmt->fetch();

            if ($pay) {
                $pdo->prepare("UPDATE payments SET status='REFUNDED', refund_amount=?, refund_reference=?, refund_date=NOW(), refunded_by=? WHERE id=?")
                    ->execute([$pay['amount'], $refundRef, $_SESSION['user_id'], $paymentId]);
                addNotification($pay['user_id'], '💸 Refund Processed',
                    'Your payment of ₱' . number_format($pay['amount'], 2) . ' has been refunded.', 'payment', '💸');
            }
            setFlash('success', 'Refund processed.');
        }
    }

    header('Location: index.php?page=payments');
    exit;
}

// ============================================================
// FILTERS & SEARCH
// ============================================================
$statusFilter = $_GET['status'] ?? 'active';
$search       = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];

if ($statusFilter === 'active') {
    $where[] = "p.status IN ('PENDING', 'UNPAID', 'REJECTED')";
} elseif ($statusFilter !== 'all') {
    $where[]  = 'p.status = ?';
    $params[] = strtoupper($statusFilter);
}

if ($search !== '') {
    $searchTerm = "%$search%";
    $where[]  = '(u.name LIKE ? OR p.payment_ref LIKE ? OR b.booking_ref LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    if ($statusFilter === 'active') {
        $where = ['1=1'];
        $params = [];
        $where[]  = '(u.name LIKE ? OR p.payment_ref LIKE ? OR b.booking_ref LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
}

// ============================================================
// GET PAYMENTS
// ============================================================
$stmt = $pdo->prepare("
    SELECT p.*,
           b.booking_ref, b.date AS booking_date, b.status AS booking_status,
           b.package_price, b.deposit_amount, b.remaining_balance, b.deposit_paid, b.fully_paid,
           pkg.name AS pkg_name,
           u.name AS client_name, u.email AS client_email, u.phone AS client_phone,
           lc.total_bookings AS loyalty_count
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN packages pkg ON b.package_id = pkg.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN loyalty_cards lc ON lc.user_id = p.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY 
        CASE p.status 
            WHEN 'PENDING' THEN 1 
            WHEN 'UNPAID' THEN 2 
            WHEN 'REJECTED' THEN 3 
            WHEN 'PAID' THEN 4 
            WHEN 'REFUNDED' THEN 5 
            ELSE 6 
        END,
        p.created_at DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Counts
$countAll      = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$countPending  = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='PENDING'")->fetchColumn();
$countPaid     = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='PAID'")->fetchColumn();
$countRejected = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='REJECTED'")->fetchColumn();
$countUnpaid   = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='UNPAID'")->fetchColumn();
$countRefunded = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='REFUNDED'")->fetchColumn();
$countActive   = (int)$countPending + (int)$countUnpaid + (int)$countRejected;
?>

<!-- PAGE BANNER -->
<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">Payment Management</div>
    <h2><strong>Payment Verification</strong></h2>
    <p>Click a payment to view details and verify.</p>
  </div>
  <div class="page-banner-art">💳</div>
</div>

<!-- FILTERS + SEARCH -->
<div class="card" style="margin-bottom:20px;background:#F5F5F5;border:1px solid #E0E0E0;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">

    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <?php 
      $filters = [
        'active'   => ['label' => '🔔 Needs Action', 'count' => $countActive],
        'pending'  => ['label' => 'Pending',          'count' => $countPending],
        'unpaid'   => ['label' => 'Unpaid',           'count' => $countUnpaid],
        'rejected' => ['label' => 'Rejected',         'count' => $countRejected],
        'paid'     => ['label' => 'Paid',             'count' => $countPaid],
        'refunded' => ['label' => 'Refunded',         'count' => $countRefunded],
        'all'      => ['label' => 'All',              'count' => $countAll],
      ];
      foreach ($filters as $key => $info): 
        $isActive = ($statusFilter === $key);
      ?>
        <a href="index.php?page=payments&status=<?= $key ?><?= $search ? '&q=' . urlencode($search) : '' ?>" 
           style="padding:6px 14px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;
                  <?= $isActive 
                      ? 'background:#0A0A0A;color:#FFFFFF;border:1px solid #0A0A0A;' 
                      : 'background:#FFFFFF;color:#0A0A0A;border:1px solid #E0E0E0;' ?>">
          <?= $info['label'] ?>
          <span style="background:<?= $isActive ? 'rgba(255,255,255,0.25)' : '#F5F5F5' ?>;padding:1px 7px;border-radius:50px;font-size:10px;font-weight:700;">
            <?= $info['count'] ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>

    <form method="GET" action="index.php" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="page" value="payments">
      <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
      <div style="position:relative;">
        <input type="text" 
               name="q" 
               value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search client…" 
               style="padding:8px 36px 8px 14px;border:1px solid #E0E0E0;border-radius:50px;font-size:13px;width:200px;background:#FFFFFF;">
        <?php if ($search): ?>
          <a href="index.php?page=payments&status=<?= htmlspecialchars($statusFilter) ?>" 
             style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#8B8177;text-decoration:none;font-size:14px;">
            ✕
          </a>
        <?php endif; ?>
      </div>
      <button type="submit" 
              style="padding:8px 18px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;">
        🔍
      </button>
    </form>
  </div>
</div>

<!-- PAYMENTS LIST (Compact) -->
<div class="card">
  <div class="section-header">
    <h3><strong>Payment Records</strong></h3>
    <span style="font-size:12px;color:#8B8177;">Total: <?= count($payments) ?></span>
  </div>

  <?php if (empty($payments)): ?>
    <div style="text-align:center;padding:60px;">
      <div style="font-size:48px;margin-bottom:12px;">📭</div>
      <div style="font-weight:600;color:#0A0A0A;">
        <?= $search ? 'No payments match your search' : 'No payments found' ?>
      </div>
    </div>
  <?php else: ?>

    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php foreach ($payments as $p): 
        $loyaltyCount       = (int)($p['loyalty_count'] ?? 0);
        $hasLoyaltyDiscount = ($loyaltyCount >= 10);

        $paymentType   = strtoupper($p['type'] ?? 'RESERVATION');
        $isBalance     = ($paymentType === 'BALANCE');
        $isFull        = ($paymentType === 'FULL');
        $isReservation = !$isBalance && !$isFull;

        $originalPrice  = (float)$p['package_price'];
        $effectivePrice = $hasLoyaltyDiscount ? round($originalPrice * 0.5, 2) : $originalPrice;
        $reservationFee = RESERVATION_FEE;
        $balanceAmount  = max(0, round($effectivePrice - $reservationFee, 2));

        if ($isBalance) {
            $expectedAmount = $balanceAmount;
        } elseif ($isFull) {
            $expectedAmount = $effectivePrice;
        } else {
            $expectedAmount = $reservationFee;
        }

        $amountLabel   = 'Amount Due';
        $amountDisplay = $expectedAmount;

        if ($p['status'] === 'PAID') {
            $amountLabel   = 'Paid';
            $amountDisplay = (float)$p['amount'];
        } elseif ($p['status'] === 'PENDING') {
            $amountLabel   = 'Submitted';
            $amountDisplay = (float)$p['amount'];
        } elseif ($p['status'] === 'REJECTED') {
            $amountLabel   = 'Rejected';
            $amountDisplay = (float)$p['amount'];
        } elseif ($p['status'] === 'REFUNDED') {
            $amountLabel   = 'Refunded';
            $amountDisplay = (float)$p['amount'];
        }

        // Status badge color
        $statusIcon = [
            'PAID'     => '✅',
            'PENDING'  => '⏳',
            'UNPAID'   => '⚠️',
            'REJECTED' => '❌',
            'REFUNDED' => '💸',
        ][$p['status']] ?? '•';

        $statusBorder = [
            'PAID'     => '#0A0A0A',
            'PENDING'  => '#F59E0B',
            'UNPAID'   => '#E74C3C',
            'REJECTED' => '#E74C3C',
            'REFUNDED' => '#6B7280',
        ][$p['status']] ?? '#E0E0E0';

        $hasProof = !empty($p['proof_image']);
      ?>

        <!-- COMPACT ROW -->
        <div onclick="openPaymentDetail(<?= $p['id'] ?>)" 
             style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;background:#FFFFFF;border:1px solid #E0E0E0;border-left:4px solid <?= $statusBorder ?>;border-radius:10px;cursor:pointer;transition:all 0.15s;"
             onmouseover="this.style.background='#F9F9F9';this.style.transform='translateX(2px)'"
             onmouseout="this.style.background='#FFFFFF';this.style.transform='translateX(0)'">

          <!-- LEFT: Client name + package -->
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
              <span style="font-weight:700;font-size:14px;color:#0A0A0A;">
                <?= clean($p['client_name'] ?? 'Walk-in Client') ?>
              </span>
              <?php if ($loyaltyCount > 0): ?>
                <span style="background:#0A0A0A;color:#FFFFFF;padding:1px 7px;border-radius:50px;font-size:10px;font-weight:700;">
                  🎫 <?= $loyaltyCount ?>
                </span>
              <?php endif; ?>
              <span style="font-size:11px;color:#8B8177;">·</span>
              <span style="font-size:12px;color:#8B8177;">
                <?= clean($p['pkg_name']) ?>
              </span>
            </div>
            <div style="font-size:11px;color:#8B8177;">
              📅 <?= formatDate($p['booking_date']) ?>
              <?php if ($hasProof): ?>
                <span style="color:#0A0A0A;">· 📎 with proof</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- RIGHT: Amount + Status -->
          <div style="text-align:right;display:flex;align-items:center;gap:12px;">
            <div>
              <div style="font-size:16px;font-weight:700;color:#0A0A0A;letter-spacing:-0.3px;">
                <?= formatMoney($amountDisplay) ?>
              </div>
              <div style="font-size:10px;color:#8B8177;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">
                <?= $amountLabel ?>
              </div>
            </div>
            <div style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:50px;font-size:10px;font-weight:700;border:1px solid <?= $statusBorder ?>;color:<?= $statusBorder ?>;background:#FFFFFF;white-space:nowrap;">
              <?= $statusIcon ?> <?= $p['status'] ?>
            </div>
          </div>
        </div>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- PAYMENT DETAIL MODAL -->
<!-- ============================================================ -->
<div id="paymentDetailModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this) closePaymentDetail()">
  <div style="background:#FFFFFF;border-radius:14px;max-width:700px;width:100%;max-height:90vh;overflow-y:auto;position:relative;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #E0E0E0;position:sticky;top:0;background:#FFFFFF;border-radius:14px 14px 0 0;z-index:10;">
      <div>
        <div style="font-weight:700;font-size:16px;color:#0A0A0A;">Payment Details</div>
        <div style="font-size:11px;color:#8B8177;margin-top:2px;">Click "Back" or press ESC to close</div>
      </div>
      <button type="button" onclick="closePaymentDetail()" 
              style="background:#F5F5F5;border:none;color:#0A0A0A;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">
        ✕
      </button>
    </div>

    <!-- Content -->
    <div id="paymentDetailContent" style="padding:24px;">
      <!-- Dynamic content -->
    </div>
  </div>
</div>

<!-- REJECT MODAL -->
<div id="rejectModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this) closeRejectModal()">
  <div style="background:#FFFFFF;border-radius:14px;padding:28px;max-width:500px;width:100%;">
    <h3 style="margin-bottom:16px;color:#0A0A0A;"><strong>❌ Reject Payment</strong></h3>
    <form method="POST" id="rejectForm">
      <?= csrfField() ?>
      <input type="hidden" name="payment_id" id="rejectPaymentId">
      <input type="hidden" name="action" value="reject">
      <div class="form-group" style="margin-bottom:16px;">
        <label style="font-weight:600;color:#0A0A0A;display:block;margin-bottom:8px;font-size:13px;">Reason for Rejection</label>
        <textarea name="rejection_reason" placeholder="e.g., Invalid reference number, blurry proof, etc." required 
                  style="width:100%;padding:12px;border:1px solid #E0E0E0;border-radius:10px;min-height:90px;font-family:inherit;font-size:13px;background:#F5F5F5;resize:vertical;"></textarea>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="button" style="flex:1;padding:10px;background:#F5F5F5;color:#0A0A0A;border:1px solid #E0E0E0;border-radius:8px;font-weight:600;cursor:pointer;" onclick="closeRejectModal()">Cancel</button>
        <button type="submit" style="flex:1;padding:10px;background:#0A0A0A;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">❌ Reject</button>
      </div>
    </form>
  </div>
</div>

<!-- REFUND MODAL -->
<div id="refundModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this) closeRefundModal()">
  <div style="background:#FFFFFF;border-radius:14px;padding:28px;max-width:500px;width:100%;">
    <h3 style="margin-bottom:16px;color:#0A0A0A;"><strong>💸 Process Refund</strong></h3>
    <div style="padding:14px 16px;background:#F5F5F5;border:1px solid #E0E0E0;border-radius:10px;margin-bottom:18px;">
      <div style="font-size:11px;color:#8B8177;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Refund Amount</div>
      <div style="font-size:26px;font-weight:700;color:#0A0A0A;margin-top:4px;" id="refundAmount">₱0</div>
    </div>
    <form method="POST" id="refundForm">
      <?= csrfField() ?>
      <input type="hidden" name="payment_id" id="refundPaymentId">
      <input type="hidden" name="action" value="refund">
      <div class="form-group" style="margin-bottom:18px;">
        <label style="font-weight:600;color:#0A0A0A;display:block;margin-bottom:8px;font-size:13px;">Refund Reference</label>
        <input type="text" name="refund_reference" placeholder="e.g., GCash ref number" required 
               style="width:100%;padding:12px;border:1px solid #E0E0E0;border-radius:10px;font-family:inherit;font-size:13px;background:#F5F5F5;">
      </div>
      <div style="display:flex;gap:10px;">
        <button type="button" style="flex:1;padding:10px;background:#F5F5F5;color:#0A0A0A;border:1px solid #E0E0E0;border-radius:8px;font-weight:600;cursor:pointer;" onclick="closeRefundModal()">Cancel</button>
        <button type="submit" style="flex:1;padding:10px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:8px;font-weight:600;cursor:pointer;">💸 Process</button>
      </div>
    </form>
  </div>
</div>

<!-- PROOF MODAL -->
<div id="proofModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:999999;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this) closeProofModal()">
  <div style="background:#FFFFFF;border-radius:14px;max-width:800px;width:100%;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #E0E0E0;">
      <div>
        <div style="font-weight:700;font-size:16px;color:#0A0A0A;">📎 Payment Proof</div>
        <div style="font-size:11px;color:#8B8177;margin-top:2px;">
          <span id="proof-ref">---</span> · <span id="proof-client">---</span>
        </div>
      </div>
      <button type="button" onclick="closeProofModal()" style="background:#F5F5F5;border:none;color:#0A0A0A;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">✕</button>
    </div>
    <div style="padding:24px;text-align:center;background:#F5F5F5;">
      <img id="proof-img" src="" alt="Payment Proof"
           style="max-width:100%;max-height:60vh;border-radius:10px;border:1px solid #E0E0E0;background:#FFFFFF;">
    </div>
    <div style="padding:16px 24px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #E0E0E0;gap:10px;">
      <button type="button" onclick="closeProofModal()" style="padding:10px 20px;background:#F5F5F5;color:#0A0A0A;border:1px solid #E0E0E0;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;">
        ← Back
      </button>
      <a id="proof-download-link" href="" download style="padding:10px 20px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
        📥 Download
      </a>
    </div>
  </div>
</div>

<script>
// ============================================================
// PAYMENT DATA (JSON) — for dynamic modal
// ============================================================
const PAYMENTS = <?= json_encode($payments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const RESERVATION_FEE = <?= RESERVATION_FEE ?>;

function fmtMoney(n) {
    return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    return dt.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
}

function openPaymentDetail(id) {
    const p = PAYMENTS.find(x => x.id == id);
    if (!p) return;

    const loyaltyCount = parseInt(p.loyalty_count) || 0;
    const hasDiscount = loyaltyCount >= 10;

    const paymentType = (p.type || 'RESERVATION').toUpperCase();
    const isBalance = paymentType === 'BALANCE';
    const isFull = paymentType === 'FULL';
    const isReservation = !isBalance && !isFull;

    const originalPrice = parseFloat(p.package_price) || 0;
    const effectivePrice = hasDiscount ? originalPrice * 0.5 : originalPrice;
    const balanceAmount = Math.max(0, effectivePrice - RESERVATION_FEE);
    const expectedAmount = isBalance ? balanceAmount : (isFull ? effectivePrice : RESERVATION_FEE);

    const hasProof = p.proof_image && p.proof_image.length > 0;
    const hasSubmitted = ['PENDING', 'PAID', 'REJECTED', 'REFUNDED'].includes(p.status);

    let amountLabel = 'Amount Due';
    let amountDisplay = expectedAmount;
    if (p.status === 'PAID') { amountLabel = 'Paid Amount'; amountDisplay = parseFloat(p.amount); }
    else if (p.status === 'PENDING') { amountLabel = 'Submitted Amount'; amountDisplay = parseFloat(p.amount); }
    else if (p.status === 'REJECTED') { amountLabel = 'Rejected Amount'; amountDisplay = parseFloat(p.amount); }
    else if (p.status === 'REFUNDED') { amountLabel = 'Refunded Amount'; amountDisplay = parseFloat(p.amount); }

    const statusBorder = {
        'PAID': '#0A0A0A', 'PENDING': '#F59E0B', 'UNPAID': '#E74C3C',
        'REJECTED': '#E74C3C', 'REFUNDED': '#6B7280'
    }[p.status] || '#E0E0E0';

    const content = document.getElementById('paymentDetailContent');

    content.innerHTML = `
        <!-- TOP: Amount + Status -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <div>
                <div style="font-weight:700;font-size:20px;color:#0A0A0A;">
                    ${esc(p.client_name || 'Walk-in Client')}
                    ${loyaltyCount > 0 ? `<span style="background:#0A0A0A;color:#FFF;padding:2px 8px;border-radius:50px;font-size:10px;font-weight:700;margin-left:6px;">🎫 ${loyaltyCount}</span>` : ''}
                </div>
                <div style="font-size:13px;color:#8B8177;margin-top:4px;">
                    ${esc(p.pkg_name || 'Package')}
                </div>
                <div style="font-size:12px;color:#8B8177;margin-top:4px;">
                    Session: <strong style="color:#0A0A0A;">${fmtDate(p.booking_date)}</strong>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:10px;color:#8B8177;text-transform:uppercase;letter-spacing:1px;font-weight:700;">${amountLabel}</div>
                <div style="font-size:32px;font-weight:700;color:#0A0A0A;margin-top:4px;letter-spacing:-1px;">${fmtMoney(amountDisplay)}</div>
                <div style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:50px;font-size:11px;font-weight:700;border:2px solid ${statusBorder};color:${statusBorder};margin-top:8px;">
                    ${p.status}
                </div>
            </div>
        </div>

        <!-- CLIENT INFO -->
        <div style="padding:14px;background:#F5F5F5;border-radius:10px;margin-bottom:16px;font-size:13px;border:1px solid #E0E0E0;">
            <div style="display:flex;flex-wrap:wrap;gap:14px;color:#0A0A0A;">
                <div><span>✉️</span> ${esc(p.client_email || '—')}</div>
                <div><span>📱</span> ${esc(p.client_phone || '—')}</div>
            </div>
        </div>

        <!-- PAYMENT BREAKDOWN -->
        ${hasSubmitted ? `
        <div style="padding:16px;background:#F5F5F5;border:1px solid #E0E0E0;border-radius:10px;margin-bottom:16px;">
            <div style="font-weight:700;font-size:12px;color:#0A0A0A;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;">
                💰 Payment Breakdown
            </div>

            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px dashed #E0E0E0;">
                <div style="color:#8B8177;font-size:13px;">Package Price</div>
                <div style="font-weight:700;color:#0A0A0A;">
                    ${hasDiscount ? `<span style="font-size:11px;text-decoration:line-through;color:#8B8177;margin-right:6px;">${fmtMoney(originalPrice)}</span>` : ''}
                    ${fmtMoney(effectivePrice)}
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px dashed #E0E0E0;">
                <div style="color:#8B8177;font-size:13px;">Payment Type</div>
                <div style="font-weight:600;color:#0A0A0A;">
                    ${isBalance ? '💰 Balance Payment' : (isFull ? '💳 Full Payment' : '🎫 Reservation Fee')}
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px dashed #E0E0E0;">
                <div style="color:#8B8177;font-size:13px;">Expected Amount</div>
                <div style="font-weight:700;color:#0A0A0A;">${fmtMoney(expectedAmount)}</div>
            </div>

            <div style="display:flex;justify-content:space-between;padding:10px 0;">
                <div style="color:#8B8177;font-size:13px;">Remaining Balance</div>
                <div style="font-weight:700;color:#0A0A0A;">${fmtMoney(p.fully_paid ? 0 : (isBalance || isFull ? 0 : balanceAmount))}</div>
            </div>

            ${hasDiscount ? `
            <div style="margin-top:14px;padding:12px;background:#FFFFFF;border-left:3px solid #0A0A0A;border-radius:6px;font-size:12px;color:#0A0A0A;">
                <strong>🎉 Loyalty Discount Applied</strong><br>
                Client has ${loyaltyCount} bookings — eligible for 50% OFF.
            </div>
            ` : ''}
        </div>
        ` : `
        <div style="padding:16px;background:#F5F5F5;border:1px dashed #E0E0E0;border-radius:10px;margin-bottom:16px;text-align:center;">
            <div style="font-size:13px;color:#8B8177;font-style:italic;">
                ⏳ Client has not submitted payment yet
            </div>
        </div>
        `}

        <!-- PROOF -->
        ${hasProof ? `
        <div style="margin-bottom:16px;">
            <button type="button"
                    onclick="openProofModal(
                        '<?= APP_URL ?>/assets/uploads/payments/' + p.proof_image,
                        p.payment_ref || '—',
                        p.client_name || 'Walk-in Client'
                    )"
                    style="width:100%;padding:12px;background:#F5F5F5;color:#0A0A0A;border:1px solid #E0E0E0;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">
                📎 View Payment Proof
            </button>
        </div>
        ` : ''}

        <!-- ACTIONS -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;padding-top:16px;border-top:1px solid #E0E0E0;">
            ${p.status === 'PENDING' ? `
                <form method="POST" style="flex:1;min-width:140px;" onsubmit="return confirm('Verify this payment?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="payment_id" value="${p.id}">
                    <input type="hidden" name="action" value="verify">
                    <button type="submit" style="width:100%;padding:14px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">
                        ✅ Verify Payment
                    </button>
                </form>
                <button type="button" style="flex:1;min-width:140px;padding:14px;background:#FFFFFF;color:#0A0A0A;border:2px solid #0A0A0A;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;"
                        onclick="closePaymentDetail(); setTimeout(()=>openRejectModal(${p.id}), 200);">
                    ❌ Reject
                </button>
            ` : ''}

            ${p.status === 'PAID' ? `
                <button type="button" style="flex:1;padding:14px;background:#F5F5F5;color:#0A0A0A;border:2px solid #0A0A0A;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;"
                        onclick="closePaymentDetail(); setTimeout(()=>openRefundModal(${p.id}, ${p.amount}), 200);">
                    💸 Process Refund
                </button>
            ` : ''}

            ${p.status === 'REJECTED' && p.rejection_reason ? `
                <div style="width:100%;padding:12px;background:#F5F5F5;border-left:3px solid #E74C3C;border-radius:6px;font-size:12px;color:#0A0A0A;">
                    <strong>Rejection Reason:</strong> ${esc(p.rejection_reason)}
                </div>
            ` : ''}

            ${p.status === 'REFUNDED' && p.refund_reference ? `
                <div style="width:100%;padding:12px;background:#F5F5F5;border-left:3px solid #6B7280;border-radius:6px;font-size:12px;color:#0A0A0A;">
                    <strong>Refund Ref:</strong> ${esc(p.refund_reference)}
                </div>
            ` : ''}

            <button type="button" style="flex:1;min-width:140px;padding:14px;background:#FFFFFF;color:#0A0A0A;border:1px solid #E0E0E0;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;"
                    onclick="closePaymentDetail()">
                ← Back
            </button>
        </div>
    `;

    document.getElementById('paymentDetailModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closePaymentDetail() {
    document.getElementById('paymentDetailModal').style.display = 'none';
    document.body.style.overflow = '';
}

function openProofModal(imgUrl, ref, client) {
    document.getElementById('proof-img').src = imgUrl;
    document.getElementById('proof-ref').textContent = ref || '—';
    document.getElementById('proof-client').textContent = client || '—';
    document.getElementById('proof-download-link').href = imgUrl;
    document.getElementById('proofModal').style.display = 'flex';
}
function closeProofModal() {
    document.getElementById('proofModal').style.display = 'none';
}

function openRejectModal(id) {
    document.getElementById('rejectPaymentId').value = id;
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
function openRefundModal(id, amount) {
    document.getElementById('refundPaymentId').value = id;
    document.getElementById('refundAmount').textContent = '₱' + Number(amount).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('refundModal').style.display = 'flex';
}
function closeRefundModal() {
    document.getElementById('refundModal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProofModal();
        closeRejectModal();
        closeRefundModal();
        closePaymentDetail();
    }
});
</script>