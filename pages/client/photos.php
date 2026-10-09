<?php
requireRole('client');
$pdo = db();
$uid = $user['id'];

$sessions = $pdo->prepare("
    SELECT b.id as booking_id, b.booking_ref, b.date, p.name as pkg_name,
           COUNT(ph.id) as photo_count,
           -- MAX() on an ENUM compares by declared order ('Processing' > 'Pending' >
           -- 'Ready' > 'Sent'), so a single 'Sent' row would mask the Ready ones and
           -- hide the download button. Report whether ANY photo is Ready instead.
           SUM(ph.status = 'Ready') as ready_count,
           MAX(ph.created_at) as upload_date
    FROM bookings b
    JOIN packages p ON b.package_id=p.id
    LEFT JOIN photos ph ON ph.booking_id=b.id
    WHERE b.user_id=?
      AND b.status IN ('Completed','Deposit Paid','Confirmed','In Progress')
    GROUP BY b.id ORDER BY b.date DESC
");
$sessions->execute([$uid]);
$all = $sessions->fetchAll();
foreach ($all as &$s) {
    // Any photo already sent to the client counts as delivered.
    $s['photo_status'] = ((int)($s['ready_count'] ?? 0) > 0) ? 'Ready' : null;
}
unset($s);
?>

<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">Your Memories</div>
    <h2>My Photos</h2>
    <p>Download your captured moments from past sessions.</p>
  </div>
  <div class="page-banner-art">📸</div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Session</th><th>Date</th><th>Photos</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($all as $s): ?>
        <tr>
          <td>
            <strong><?= clean($s['pkg_name']) ?></strong>
            <div style="font-size:11px;color:var(--muted);"><?= clean($s['booking_ref']) ?></div>
          </td>
          <td><?= formatDate($s['date']) ?></td>
          <td>
            <?php if ($s['photo_count']): ?>
              <strong><?= $s['photo_count'] ?></strong> photos
            <?php else: ?>
              <span style="color:var(--muted);">Pending upload</span>
            <?php endif; ?>
          </td>
          <td><?= $s['photo_status'] ? statusBadge($s['photo_status']) : '<span class="badge badge-amber">Pending</span>' ?></td>
          <td>
            <?php if ($s['photo_count'] && $s['photo_status'] === 'Ready'): ?>
              <a href="index.php?page=photos&download=<?= $s['booking_id'] ?>" class="btn-primary btn-sm">📥 Download</a>
            <?php else: ?>
              <span style="font-size:12px;color:var(--muted);">Not ready yet</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($all)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px;">
            No completed sessions with photos yet. <a href="index.php?page=booking" class="link-text">Book a session →</a>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Photo grid for a specific session
if (isset($_GET['download'])) {
    $bid   = cleanInt($_GET['download']);
    $check = $pdo->prepare("SELECT id FROM bookings WHERE id=? AND user_id=?");
    $check->execute([$bid, $uid]);
    if ($check->fetch()) {
        $photos = $pdo->prepare("SELECT * FROM photos WHERE booking_id=? AND status IN ('Ready','Sent') ORDER BY id");
        $photos->execute([$bid]);
        $imgs = $photos->fetchAll();
        if (!empty($imgs)):
?>
<div class="card" style="margin-top:20px;">
  <div class="section-header"><h3>Photos</h3></div>
  <div class="gallery-grid">
    <?php foreach ($imgs as $img): ?>
      <div class="gallery-item">
        <img src="<?= UPLOAD_URL . clean($img['filename']) ?>"
             alt="Photo" style="width:100%;height:100%;object-fit:cover;">
        <div class="gallery-overlay">
          <a href="<?= UPLOAD_URL . clean($img['filename']) ?>" download
             style="color:white;font-size:24px;text-decoration:none;">⬇</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; } } ?>
