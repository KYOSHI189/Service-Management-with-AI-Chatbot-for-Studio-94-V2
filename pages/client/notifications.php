<?php
requireRole('client');
$pdo = db();
$uid = $user['id'];

// ============================================================
// MARK ALL AS READ
// ============================================================
if (isset($_GET['markread'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    setFlash('success', 'All notifications marked as read.');
    header('Location: index.php?page=notifications');
    exit;
}

// ============================================================
// MARK SINGLE AS READ (via GET ?read=ID)
// ============================================================
if (isset($_GET['read'])) {
    $nid = cleanInt($_GET['read']);
    if ($nid) {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$nid, $uid]);
    }
    $redirect = 'index.php?page=notifications';
    if (!empty($_GET['filter'])) $redirect .= '&filter=' . urlencode($_GET['filter']);
    header('Location: ' . $redirect);
    exit;
}

// ============================================================
// DELETE SINGLE NOTIFICATION
// ============================================================
if (isset($_GET['delete'])) {
    $nid = cleanInt($_GET['delete']);
    if ($nid) {
        $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([$nid, $uid]);
        setFlash('success', 'Notification deleted.');
    }
    $redirect = 'index.php?page=notifications';
    if (!empty($_GET['filter'])) $redirect .= '&filter=' . urlencode($_GET['filter']);
    header('Location: ' . $redirect);
    exit;
}

// ============================================================
// DELETE ALL READ NOTIFICATIONS
// ============================================================
if (isset($_GET['clearread'])) {
    $pdo->prepare("DELETE FROM notifications WHERE user_id=? AND is_read=1")->execute([$uid]);
    setFlash('success', 'All read notifications cleared.');
    header('Location: index.php?page=notifications');
    exit;
}

// ============================================================
// OPEN NOTIFICATION (mark as read + redirect to link)
// ============================================================
if (isset($_GET['open'])) {
    $nid = cleanInt($_GET['open']);
    if ($nid) {
        $stmt = $pdo->prepare("SELECT link FROM notifications WHERE id=? AND user_id=? LIMIT 1");
        $stmt->execute([$nid, $uid]);
        $n = $stmt->fetch();

        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$nid, $uid]);

        if ($n && !empty($n['link'])) {
            header('Location: ' . $n['link']);
            exit;
        }
    }
    header('Location: index.php?page=notifications');
    exit;
}

// ============================================================
// FILTER
// ============================================================
$filter = $_GET['filter'] ?? 'all';
$where  = "user_id = ?";
$params = [$uid];
if ($filter === 'unread') { $where .= " AND is_read=0"; }
if ($filter === 'read')   { $where .= " AND is_read=1"; }

// ============================================================
// GET NOTIFICATIONS
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT 50");
$stmt->execute($params);
$notifs = $stmt->fetchAll();

// ============================================================
// GET UNREAD COUNT
// ============================================================
$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$stmtUnread->execute([$uid]);
$unreadCount = (int) $stmtUnread->fetchColumn();

$stmtRead = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=1");
$stmtRead->execute([$uid]);
$readCount = (int) $stmtRead->fetchColumn();
?>

<!-- ============================================================ -->
<!-- FULLY RESPONSIVE STYLES                                       -->
<!-- ============================================================ -->
<style>
/* ============================================================
   BASE (MOBILE-FIRST) — 320px pataas
   ============================================================ */

/* PAGE BANNER */
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

/* CARD */
.card {
    padding: 14px;
    border-radius: 12px;
    box-sizing: border-box;
    min-width: 0;
}

/* SECTION HEADER */
.section-header {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 14px;
}

.section-title {
    font-size: 15px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    word-break: break-word;
}

.unread-pill {
    display: inline-block;
    background: #E74C3C;
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 50px;
    white-space: nowrap;
}

/* ACTION BUTTONS ROW */
.notif-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.notif-actions a {
    text-decoration: none;
    white-space: nowrap;
}

/* NOTIFICATION LIST */
.notif-list {
    display: flex;
    flex-direction: column;
}

/* NOTIF WRAPPER */
.notif-wrapper {
    position: relative;
    display: block;
    border-bottom: 1px solid var(--border);
}
.notif-wrapper:last-child {
    border-bottom: none;
}

/* NOTIF ITEM */
.notif-item {
    text-decoration: none;
    color: inherit;
    display: block;
    position: relative;
    padding: 12px 44px 12px 10px;
    transition: background 0.15s;
    box-sizing: border-box;
    min-width: 0;
}
.notif-item.has-link,
.notif-item.unread { cursor: pointer; }
.notif-item.unread {
    background: linear-gradient(90deg, #EFF6FF 0%, transparent 80%);
}
.notif-item.unread:hover {
    background: linear-gradient(90deg, #DBEAFE 0%, transparent 80%) !important;
}
.notif-item:not(.unread):hover {
    background: var(--bg-soft) !important;
}

/* NOTIF FLEX LAYOUT */
.notif-row {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    min-width: 0;
}

/* ICON */
.notif-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    border: 2px solid var(--border);
    background: #F5F5F5;
    box-sizing: border-box;
}
.notif-item.unread .notif-icon {
    background: #FEF3C7;
    border-color: #F59E0B;
}

/* BODY */
.notif-body {
    flex: 1;
    min-width: 0;
}

.notif-title {
    font-size: 13px;
    color: var(--dark);
    margin-bottom: 2px;
    line-height: 1.3;
    word-break: break-word;
    font-weight: 500;
    opacity: 0.75;
}
.notif-item.unread .notif-title {
    font-weight: 700;
    opacity: 1;
}

.notif-message {
    font-size: 12px;
    color: var(--dark2);
    line-height: 1.45;
    margin-bottom: 4px;
    word-wrap: break-word;
    word-break: break-word;
    opacity: 0.7;
}
.notif-item.unread .notif-message { opacity: 1; }

.notif-meta {
    font-size: 10px;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.notif-meta .click-hint {
    color: #6C63FF;
    font-weight: 600;
}

/* UNREAD DOT */
.notif-dot {
    flex-shrink: 0;
    align-self: center;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #E74C3C;
}

/* DELETE BUTTON */
.notif-delete {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: white;
    border: 1px solid var(--border);
    color: var(--muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    text-decoration: none;
    opacity: 1;
    transition: opacity 0.15s, background 0.15s, color 0.15s;
    cursor: pointer;
    z-index: 2;
    box-sizing: border-box;
}
.notif-delete:hover {
    background: #E74C3C;
    color: white;
    border-color: #E74C3C;
    transform: translateY(-50%) scale(1.1);
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 40px 16px;
}
.empty-state .empty-icon {
    font-size: 48px;
    margin-bottom: 10px;
}
.empty-state .empty-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--dark);
}
.empty-state .empty-desc {
    font-size: 12px;
    color: var(--muted);
    margin-top: 4px;
    line-height: 1.4;
}

/* FOOTER (Showing latest 50) */
.notif-footer {
    text-align: center;
    padding: 12px;
    font-size: 11px;
    color: var(--muted);
    border-top: 1px solid var(--border);
}

/* ============================================================
   SMALL PHONE (min-width: 380px)
   ============================================================ */
@media (min-width: 380px) {
    .page-banner h2 { font-size: 19px; }
    .section-title { font-size: 16px; }
    .notif-icon { width: 40px; height: 40px; font-size: 18px; }
    .notif-title { font-size: 14px; }
    .notif-message { font-size: 13px; }
    .notif-item { padding: 14px 46px 14px 12px; }
    .notif-delete { width: 30px; height: 30px; font-size: 13px; }
}

/* ============================================================
   LARGE PHONE / SMALL TABLET (min-width: 600px)
   ============================================================ */
@media (min-width: 600px) {
    .page-banner {
        flex-direction: row;
        align-items: center;
        padding: 20px 24px;
        border-radius: 14px;
    }
    .page-banner h2 { font-size: 22px; }

    .card { padding: 18px; }

    .section-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }

    .notif-item { padding: 14px 50px 14px 12px; }
}

/* ============================================================
   TABLET (min-width: 768px)
   ============================================================ */
@media (min-width: 768px) {
    .page-banner { padding: 24px 28px; border-radius: 16px; gap: 20px; }
    .page-banner h2 { font-size: 24px; }
    .page-banner p { font-size: 13px; }

    .card { padding: 20px 22px; border-radius: 14px; }

    .section-title { font-size: 17px; }

    .notif-icon { width: 42px; height: 42px; font-size: 20px; }
    .notif-title { font-size: 14px; }
    .notif-message { font-size: 13px; }
    .notif-item { padding: 16px 52px 16px 14px; }

    /* Desktop: delete button only shows on hover */
    .notif-delete {
        opacity: 0;
        width: 28px;
        height: 28px;
        font-size: 12px;
        right: 12px;
    }
    .notif-wrapper:hover .notif-delete {
        opacity: 1;
    }

    .empty-state { padding: 50px 20px; }
    .empty-state .empty-icon { font-size: 56px; }
    .empty-state .empty-title { font-size: 16px; }
    .empty-state .empty-desc { font-size: 13px; }
}

/* ============================================================
   LAPTOP / DESKTOP (min-width: 1024px)
   ============================================================ */
@media (min-width: 1024px) {
    .page-banner { padding: 28px 32px; }
    .page-banner h2 { font-size: 26px; }

    .card { padding: 22px 24px; }
    .notif-item { padding: 16px 56px 16px 16px; }
}

/* ============================================================
   LARGE DESKTOP (min-width: 1440px)
   ============================================================ */
@media (min-width: 1440px) {
    .page-banner { padding: 32px 40px; border-radius: 18px; }
    .page-banner h2 { font-size: 30px; }
    .page-banner p { font-size: 14px; }

    .card { padding: 26px 28px; }
    .section-title { font-size: 18px; }
    .notif-title { font-size: 15px; }
    .notif-message { font-size: 14px; }
    .notif-icon { width: 46px; height: 46px; font-size: 22px; }
}

/* ============================================================
   ULTRAWIDE (min-width: 1920px)
   ============================================================ */
@media (min-width: 1920px) {
    .page-banner { padding: 36px 48px; }
    .page-banner h2 { font-size: 34px; }

    .card { padding: 30px 32px; }
}

/* ============================================================
   LANDSCAPE MOBILE
   ============================================================ */
@media (max-height: 500px) and (orientation: landscape) {
    .page-banner { padding: 12px 16px; }
    .page-banner h2 { font-size: 18px; }
    .notif-item { padding: 10px 44px 10px 10px; }
    .notif-icon { width: 34px; height: 34px; font-size: 15px; }
}

/* ============================================================
   PRINT
   ============================================================ */
@media print {
    .notif-actions,
    .notif-delete,
    .notif-dot { display: none !important; }
    .notif-item { padding: 10px; border: 1px solid #ccc; margin-bottom: 6px; }
    .notif-wrapper { break-inside: avoid; }
}
</style>

<!-- ============================================================ -->
<!-- PAGE BANNER (Walang Bell Emoji)                               -->
<!-- ============================================================ -->
<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">Stay Updated</div>
    <h2>Notifications</h2>
    <p>Important updates about your sessions and account.</p>
  </div>
</div>

<!-- ============================================================ -->
<!-- NOTIFICATIONS CARD                                            -->
<!-- ============================================================ -->
<div class="card">
  <div class="section-header">
    <h3 class="section-title">
      Notifications
      <?php if ($unreadCount > 0): ?>
        <span class="unread-pill"><?= $unreadCount ?> new</span>
      <?php endif; ?>
    </h3>

    <div class="notif-actions">
      <?php foreach (['all'=>'All','unread'=>'Unread','read'=>'Read'] as $k=>$v): ?>
        <a href="index.php?page=notifications&filter=<?= $k ?>"
           class="btn-sm <?= $filter===$k?'btn-primary':'btn-ghost' ?>">
           <?= $v ?>
           <?php if ($k === 'unread' && $unreadCount > 0): ?>
             <span style="margin-left:4px;font-size:10px;">(<?= $unreadCount ?>)</span>
           <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <?php if ($unreadCount > 0): ?>
        <a href="index.php?page=notifications&markread=1"
           class="btn-ghost btn-sm"
           onclick="return confirm('Mark all notifications as read?');">
           ✅ Mark All Read
        </a>
      <?php endif; ?>

      <?php if ($readCount > 0): ?>
        <a href="index.php?page=notifications&clearread=1"
           class="btn-ghost btn-sm"
           onclick="return confirm('Delete all <?= $readCount ?> read notifications? This cannot be undone.');"
           style="color:var(--red-text);">
           🗑️ Clear Read
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($notifs)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <div class="empty-title">No notifications</div>
      <div class="empty-desc">
        <?php if ($filter === 'unread'): ?>
          Wala kang unread notifications. 🎉
        <?php elseif ($filter === 'read'): ?>
          Wala ka pang nababasang notifications.
        <?php else: ?>
          Wala ka pang notifications. Lalabas dito ang updates.
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>

    <div class="notif-list">
      <?php foreach ($notifs as $n):
        $isUnread = !$n['is_read'];
        $typeIcon = $n['icon'] ?: '🔔';
        $hasLink  = !empty($n['link']);

        if ($hasLink) {
            $clickUrl = 'index.php?page=notifications&open=' . $n['id'];
        } elseif ($isUnread) {
            $clickUrl = 'index.php?page=notifications&read=' . $n['id'] . '&filter=' . urlencode($filter);
        } else {
            $clickUrl = '#';
        }
      ?>
      <div class="notif-wrapper">
        <a href="<?= $clickUrl ?>"
           class="notif-item <?= $isUnread ? 'unread' : '' ?> <?= ($hasLink || $isUnread) ? 'has-link' : '' ?>">
          <div class="notif-row">

            <!-- Icon -->
            <div class="notif-icon">
              <?= $typeIcon ?>
            </div>

            <!-- Body -->
            <div class="notif-body">
              <div class="notif-title">
                <?= clean($n['title']) ?>
              </div>
              <div class="notif-message">
                <?= clean($n['message']) ?>
              </div>
              <div class="notif-meta">
                <span>🕐 <?= timeAgo($n['created_at']) ?></span>
                <?php if ($hasLink): ?>
                  <span class="click-hint">· Click to view →</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Unread dot -->
            <?php if ($isUnread): ?>
              <div class="notif-dot"></div>
            <?php endif; ?>
          </div>
        </a>

        <!-- Delete Button -->
        <a href="index.php?page=notifications&delete=<?= $n['id'] ?>&filter=<?= urlencode($filter) ?>"
           class="notif-delete"
           onclick="return confirm('Delete this notification?');"
           title="Delete notification">
          ✕
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (count($notifs) >= 50): ?>
      <div class="notif-footer">
        Showing latest 50 notifications
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>