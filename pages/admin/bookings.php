<?php
requireRole('admin');
$pdo = db();

// ===== FILTERS =====
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');

$where   = ['1=1'];
$params  = [];
if ($statusFilter !== 'all') {
    $where[]  = 'b.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where[]  = '(u.name LIKE ? OR b.booking_ref LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ⭐ UPDATED: Solid newest to oldest ordering
$sql = "SELECT b.*, u.name as client_name, u.email as client_email,
               p.name as pkg_name, p.price as pkg_price,
               (SELECT COUNT(*) FROM booking_inventory bi WHERE bi.booking_id = b.id) as has_inventory
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN packages p ON b.package_id = p.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.created_at DESC, b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$statuses = ['all','Awaiting Approval','Approved (Unpaid)','Deposit Paid','Completed','Cancelled'];
?>

<div class="card">
  <div class="section-header">
    <h3>All Bookings <span style="font-size:12px;color:var(--muted);font-weight:400;">⬇️ Newest first</span></h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <form method="GET" action="index.php" style="display:flex;gap:8px;">
        <input type="hidden" name="page" value="bookings">
        <input type="text" name="q" value="<?= clean($search) ?>" placeholder="Search client, ref…"
               style="padding:7px 14px;border:1px solid var(--border);border-radius:50px;font-size:13px;width:200px;">
        <select name="status" onchange="this.form.submit()"
                style="padding:7px 14px;border:1px solid var(--border);border-radius:50px;font-size:13px;">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= $s==='all'?'All Statuses':$s ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <span class="badge badge-blue" style="font-size:10px;">👁️ View Only</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ref</th>
          <th>Client</th>
          <th>Package</th>
          <th>Date & Time</th>
          <th>Type</th>
          <th>Inventory</th>
          <th>Status</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr>
            <td colspan="8" style="text-align:center;color:var(--muted);padding:30px;">No bookings found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($bookings as $b): ?>
          <tr>
            <td><code style="font-size:11px;"><?= clean($b['booking_ref']) ?></code></td>
            <td>
              <strong><?= clean($b['client_name']) ?></strong>
              <div style="font-size:11px;color:var(--muted);"><?= clean($b['client_email']) ?></div>
            </td>
            <td>
              <?= clean($b['pkg_name']) ?>
              <div style="font-size:11px;color:var(--muted);"><?= formatMoney($b['pkg_price']) ?></div>
            </td>
            <td>
              <?= formatDate($b['date']) ?>
              <div style="font-size:11px;color:var(--muted);"><?= clean($b['time']) ?></div>
            </td>
            <td><span class="badge badge-gray"><?= clean($b['type']) ?></span></td>
            <td>
              <?php if ($b['has_inventory'] > 0): ?>
                <span class="badge badge-blue">📦 <?= $b['has_inventory'] ?> item(s)</span>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px;">—</span>
              <?php endif; ?>
            </td>
            <td><?= statusBadge($b['status']) ?></td>
            <td>
              <?php if ($b['status'] === 'Awaiting Approval'): ?>
                <span style="font-size:11px;color:var(--amber);">⏳ Pending approval by staff</span>
              <?php elseif ($b['status'] === 'Approved (Unpaid)'): ?>
                <span style="font-size:11px;color:var(--amber);">⏳ Waiting for client deposit</span>
              <?php elseif ($b['status'] === 'Deposit Paid'): ?>
                <span style="font-size:11px;color:var(--green);">✅ Deposit received</span>
              <?php elseif ($b['status'] === 'Completed'): ?>
                <span style="font-size:11px;color:var(--green);">✅ Session completed</span>
              <?php elseif ($b['status'] === 'Cancelled'): ?>
                <span style="font-size:11px;color:var(--red);">❌ Cancelled</span>
              <?php else: ?>
                <span style="font-size:11px;color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:12px;padding:12px;background:var(--amber-bg);border-radius:8px;border:1px solid var(--amber);font-size:12px;color:var(--amber-text);">
    <strong>👁️ Admin View Only</strong><br>
    Admin can view all bookings but cannot approve, complete, or cancel bookings. 
    Only staff members have permission to modify booking statuses.
  </div>
</div>