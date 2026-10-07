<?php
requireRole('staff');
$pdo = db();
$uid = $user['id'];

if (isset($_GET['markread'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    header('Location: index.php?page=notifications');
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$where  = "user_id = ?";
$params = [$uid];
if ($filter === 'unread') $where .= " AND is_read=0";
if ($filter === 'read')   $where .= " AND is_read=1";

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT 50");
$stmt->execute($params);
$notifs = $stmt->fetchAll();
?>

<div class="card">
  <div class="section-header">
    <h3>Notifications</h3>
    <div style="display:flex;gap:8px;">
      <?php foreach (['all'=>'All','unread'=>'Unread','read'=>'Read'] as $k=>$v): ?>
        <a href="index.php?page=notifications&filter=<?= $k ?>"
           class="btn-sm <?= $filter===$k?'btn-primary':'btn-ghost' ?>"><?= $v ?></a>
      <?php endforeach; ?>
      <a href="index.php?page=notifications&markread=1" class="btn-ghost btn-sm">Mark All Read</a>
    </div>
  </div>

  <?php foreach ($notifs as $n): ?>
  <div class="notif-item <?= !$n['is_read']?'unread':'' ?>">
    <div class="notif-avatar"><?= $n['icon'] ?></div>
    <div class="notif-body">
      <div class="notif-title"><?= clean($n['title']) ?></div>
      <div class="notif-msg"><?= clean($n['message']) ?></div>
      <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
    </div>
    <?php if (!$n['is_read']): ?><div class="unread-dot"></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (empty($notifs)): ?>
    <p style="text-align:center;color:var(--muted);padding:30px;">No notifications.</p>
  <?php endif; ?>
</div>
