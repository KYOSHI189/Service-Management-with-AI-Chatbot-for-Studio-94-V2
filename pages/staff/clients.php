<?php
// ============================================================
// STAFF — Client Directory
// White & Black Theme
// ============================================================
requireRole('staff');
$pdo    = db();
$search = trim($_GET['q'] ?? '');

$stmt = $pdo->prepare("
    SELECT u.*,
           COUNT(DISTINCT b.id) AS total_bookings,
           COALESCE(SUM(CASE WHEN py.status='PAID' THEN py.amount END),0) AS total_spent,
           MAX(b.date) AS last_booking
    FROM users u
    LEFT JOIN bookings b  ON b.user_id = u.id
    LEFT JOIN payments py ON py.user_id = u.id
    WHERE u.role = 'client'
    " . ($search ? "AND (u.name LIKE ? OR u.email LIKE ?)" : "") . "
    GROUP BY u.id ORDER BY u.name ASC
");
$searchParams = $search ? ["%$search%","%$search%"] : [];
$stmt->execute($searchParams);
$clients = $stmt->fetchAll();
?>

<!-- ============================================================ -->
<!-- PAGE BANNER — BLACK -->
<!-- ============================================================ -->
<div class="page-banner" style="background:#0A0A0A;color:#FFFFFF;">
  <div class="page-banner-text">
    <div class="eyebrow" style="color:rgba(255,255,255,0.7);">Directory</div>
    <h2 style="color:#FFFFFF;"><strong>👥 Client Directory</strong></h2>
    <p style="color:rgba(255,255,255,0.85);">Manage all registered clients</p>
  </div>
  <div class="page-banner-art" style="opacity:0.8;">👥</div>
</div>

<!-- ============================================================ -->
<!-- CLIENT DIRECTORY CARD -->
<!-- ============================================================ -->
<div class="card">

  <!-- HEADER with SEARCH -->
  <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <h3>
      <strong>Clients</strong>
      <span style="font-size:12px;color:#8B8177;font-weight:500;background:#F5F5F5;padding:3px 10px;border-radius:50px;margin-left:6px;">
        <?= count($clients) ?>
      </span>
    </h3>

    <form method="GET" action="index.php" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="page" value="clients">
      <div style="position:relative;">
        <input type="text" 
               name="q" 
               value="<?= clean($search) ?>" 
               placeholder="Search name or email…"
               style="padding:8px 36px 8px 14px;border:1px solid #E0E0E0;border-radius:50px;font-size:13px;width:240px;outline:none;background:#FFFFFF;color:#0A0A0A;">
        <?php if ($search): ?>
          <a href="index.php?page=clients"
             style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#8B8177;text-decoration:none;font-size:14px;">
            ✕
          </a>
        <?php endif; ?>
      </div>
      <button type="submit"
              style="padding:8px 18px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;">
        🔍 Search
      </button>
    </form>
  </div>

  <!-- SEARCH INFO -->
  <?php if ($search): ?>
    <div style="margin-bottom:16px;padding:10px 14px;background:#F5F5F5;border:1px solid #E0E0E0;border-radius:8px;font-size:12px;color:#8B8177;">
      Showing results for: <strong style="color:#0A0A0A;">"<?= clean($search) ?>"</strong>
      — <a href="index.php?page=clients" style="color:#0A0A0A;text-decoration:underline;">Clear search</a>
    </div>
  <?php endif; ?>

  <!-- TABLE -->
  <div class="table-wrap" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <thead>
        <tr>
          <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Client</th>
          <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Email</th>
          <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Phone</th>
          <th style="text-align:center;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Bookings</th>
          <th style="text-align:right;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Total Spent</th>
          <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Last Booking</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c): ?>
        <tr style="transition:background 0.15s;"
            onmouseover="this.style.background='#F5F5F5'"
            onmouseout="this.style.background='#FFFFFF'">

          <!-- CLIENT -->
          <td style="padding:14px;border-bottom:1px solid #E0E0E0;background:inherit;">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:36px;height:36px;background:#0A0A0A;color:#FFFFFF;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">
                <?= strtoupper(mb_substr($c['name'],0,1)) ?>
              </div>
              <strong style="color:#0A0A0A;"><?= clean($c['name']) ?></strong>
            </div>
          </td>

          <!-- EMAIL -->
          <td style="padding:14px;border-bottom:1px solid #E0E0E0;color:#0A0A0A;background:inherit;">
            <?= clean($c['email']) ?>
          </td>

          <!-- PHONE -->
          <td style="padding:14px;border-bottom:1px solid #E0E0E0;color:#0A0A0A;background:inherit;">
            <?= clean($c['phone'] ?: '—') ?>
          </td>

          <!-- BOOKINGS -->
          <td style="padding:14px;border-bottom:1px solid #E0E0E0;text-align:center;background:inherit;">
            <?php if ((int)$c['total_bookings'] > 0): ?>
              <span style="display:inline-block;background:#0A0A0A;color:#FFFFFF;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;">
                <?= (int)$c['total_bookings'] ?>
              </span>
            <?php else: ?>
              <span style="color:#8B8177;">0</span>
            <?php endif; ?>
          </td>

          <!-- TOTAL SPENT -->
          <td style="padding:14px;border-bottom:1px solid #E0E0E0;text-align:right;font-weight:700;color:#0A0A0A;background:inherit;">
            <?= formatMoney($c['total_spent']) ?>
          </td>

          <!-- LAST BOOKING -->
          <td style="padding:14px;border-bottom:1px solid #E0E0E0;font-size:12px;color:#8B8177;background:inherit;">
            <?= $c['last_booking'] ? formatDate($c['last_booking']) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($clients)): ?>
          <tr>
            <td colspan="6" style="text-align:center;padding:60px 20px;">
              <div style="font-size:48px;margin-bottom:12px;">📭</div>
              <div style="font-weight:600;color:#0A0A0A;">
                <?= $search ? 'No clients match your search' : 'No clients yet' ?>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>