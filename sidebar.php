<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Sidebar
// Variables: $role, $page, $user
// ============================================================

$navAdmin = [
    ['page' => 'dashboard',     'icon' => '📊', 'label' => 'Dashboard',     'section' => 'Main'],
    ['page' => 'bookings',      'icon' => '📅', 'label' => 'Bookings'],
    ['page' => 'payments',      'icon' => '💳', 'label' => 'Payments'],
    ['page' => 'sales',         'icon' => '💰', 'label' => 'Sales'],
    ['page' => 'feedback',      'icon' => '⭐', 'label' => 'Feedback'],
    ['page' => 'clients',       'icon' => '👥', 'label' => 'Clients',       'section' => 'Management'],
    ['page' => 'inventory',     'icon' => '📦', 'label' => 'Inventory'],
    ['page' => 'packages',      'icon' => '🎀', 'label' => 'Packages'],
    ['page' => 'loyalty-cards', 'icon' => '🎫', 'label' => 'Loyalty Cards'],
    ['page' => 'staff',         'icon' => '👤', 'label' => 'Staff'],
    ['page' => 'reports',       'icon' => '📄', 'label' => 'Reports',       'section' => 'System'],
    ['page' => 'settings',      'icon' => '⚙️', 'label' => 'Settings'],
];

$navStaff = [
    ['page' => 'dashboard',  'icon' => '🏠', 'label' => 'Dashboard',        'section' => 'Main'],
    ['page' => 'bookings',   'icon' => '📅', 'label' => 'Manage Bookings'],
    ['page' => 'payments',   'icon' => '💳', 'label' => 'Payment Status'],
    ['page' => 'schedule',   'icon' => '🗓️', 'label' => 'Schedule'],
    ['page' => 'clients',    'icon' => '👥', 'label' => 'Client Directory', 'section' => 'Clients'],
    ['page' => 'feedback',   'icon' => '⭐', 'label' => 'Feedback'],
    ['page' => 'inventory',  'icon' => '📦', 'label' => 'Inventory'],
    ['page' => 'upload',     'icon' => '📤', 'label' => 'Upload Photos'],
    ['page' => 'notifications','icon'=>'🔔', 'label' => 'Notifications',    'section' => 'Account'],
];

$navClient = [
    ['page' => 'dashboard',     'icon' => '🏠', 'label' => 'Dashboard',       'section' => 'Main'],
    ['page' => 'booking',       'icon' => '📅', 'label' => 'Book Session'],
    ['page' => 'bookings',      'icon' => '📋', 'label' => 'My Bookings'],
    ['page' => 'payments',      'icon' => '💳', 'label' => 'My Payments'],
    ['page' => 'feedback',      'icon' => '⭐', 'label' => 'Feedback'],
    ['page' => 'photos',        'icon' => '🖼️', 'label' => 'My Photos',       'section' => 'Content'],
    ['page' => 'notifications', 'icon' => '🔔', 'label' => 'Notifications'],
];

$navMap = ['admin' => $navAdmin, 'staff' => $navStaff, 'client' => $navClient];
$navItems = $navMap[$role] ?? $navClient;

$showWalkin = ($role === 'staff');

// ============================================================
// AVATAR PATH RESOLUTION
// ============================================================
$avatarFile = !empty($user['avatar']) ? $user['avatar'] : null;
$avatarPath = $avatarFile ? __DIR__ . '/assets/uploads/avatars/' . $avatarFile : null;
$hasAvatar  = $avatarFile && $avatarPath && file_exists($avatarPath);

// ============================================================
// UNREAD NOTIFICATIONS COUNT
// ============================================================
$notifUnread = 0;
if (!empty($user['id'])) {
    if (function_exists('getUnreadCount')) {
        try {
            $notifUnread = (int) getUnreadCount((int)$user['id']);
        } catch (Throwable $e) {
            $notifUnread = 0;
        }
    } else {
        try {
            $stmtN = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
            $stmtN->execute([(int)$user['id']]);
            $notifUnread = (int) $stmtN->fetchColumn();
        } catch (Throwable $e) {
            $notifUnread = 0;
        }
    }
}
?>
<aside id="sidebar" class="sidebar">
  <div class="sidebar-brand">
    <div class="logo-wrap">
      <span class="logo-s-mark">S</span>
      <span class="logo-studio">TUDIO</span>
      <span class="logo-num">94</span>
      <span class="logo-reg">®</span>
    </div>
    <div class="logo-sub">EST. 24</div>
  </div>

  <nav class="sidebar-nav">
    <?php
    $lastSection = null;
    foreach ($navItems as $item):
        if (!empty($item['section']) && $item['section'] !== $lastSection):
            $lastSection = $item['section'];
    ?>
      <div class="nav-section-label"><?= clean($item['section']) ?></div>
    <?php endif; ?>
    <a class="nav-item <?= $page === $item['page'] ? 'active' : '' ?>"
       href="index.php?page=<?= urlencode($item['page']) ?>"
       data-nav-page="<?= clean($item['page']) ?>"
       style="position:relative;">
      <span class="nav-icon"><?= $item['icon'] ?></span>
      <?= clean($item['label']) ?>

      <?php if ($item['page'] === 'notifications'): ?>
        <span class="notif-badge-count"
              data-notif-badge
              style="
                <?= $notifUnread > 0 ? '' : 'display:none;' ?>
                position:absolute;
                top:50%;
                right:12px;
                transform:translateY(-50%);
                background:#E74C3C;
                color:#fff;
                font-size:10px;
                font-weight:700;
                padding:2px 7px;
                border-radius:50px;
                min-width:20px;
                text-align:center;
                line-height:1.4;
                box-shadow:0 2px 6px rgba(231,76,60,0.4);
                animation:pulseBadge 2s infinite;
              ">
          <?= $notifUnread > 99 ? '99+' : $notifUnread ?>
        </span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <?php if ($showWalkin): ?>
    <div class="nav-section-label">Quick Actions</div>
    <a class="nav-item walkin-btn <?= $page === 'walkin' ? 'active' : '' ?>"
       href="index.php?page=walkin">
      <span class="nav-icon">🚶</span>
      Walk-in Booking
      <span class="badge-walkin">+ New</span>
    </a>
    <?php endif; ?>

    <a class="nav-item logout" href="<?= APP_URL ?>/logout.php">
      <span class="nav-icon">🚪</span>Sign Out
    </a>
  </nav>

  <a href="index.php?page=profile" class="sidebar-user-link" title="Edit Profile">
    <div class="sidebar-user">
      <div class="user-avatar" style="overflow:hidden;">
        <?php if ($hasAvatar): ?>
          <img 
            src="<?= APP_URL ?>/assets/uploads/avatars/<?= clean($avatarFile) ?>?v=<?= time() ?>" 
            alt="Avatar" 
            style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;"
          >
        <?php else: ?>
          <?= strtoupper(mb_substr($user['name'] ?: 'U', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <div class="name"><?= clean($user['name']) ?></div>
        <div class="role"><?= ucfirst($role) ?></div>
      </div>
      <div class="user-edit-icon"></div>
    </div>
  </a>
</aside>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
/* ============================================================
   RESET
   ============================================================ */
html, body {
    margin: 0 !important;
    padding: 0 !important;
}

.app-layout {
    margin: 0 !important;
    padding: 0 !important;
}

/* ============================================================
   SIDEBAR — FLAT NEUTRAL GRAY
   ============================================================ */
.sidebar {
    background: #ECECEC !important;
    border-right: 1px solid #E0E0E0 !important;
    box-shadow: 2px 0 12px rgba(0,0,0,0.02) !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    padding: 0 !important;
    margin: 0 !important;
    top: 0 !important;
}

/* ============================================================
   LOGO
   ============================================================ */
.sidebar-brand {
    padding: 0 20px !important;
    border-bottom: 1px solid #E0E0E0 !important;
    margin: 0 !important;
    text-align: center !important;
    background: #FFFFFF !important;
    height: 64px !important;
    min-height: 64px !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 2px !important;
}

.logo-wrap {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 1px !important;
    font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif !important;
    font-weight: 900 !important;
    line-height: 1 !important;
    color: #1A1A1A !important;
}

.logo-s-mark {
    background: #1A1A1A !important;
    color: #FFFFFF !important;
    font-size: 20px !important;
    font-weight: 900 !important;
    padding: 3px 7px 4px 7px !important;
    border-radius: 4px !important;
    letter-spacing: -1px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    line-height: 1 !important;
}

.logo-studio {
    color: #1A1A1A !important;
    font-size: 22px !important;
    font-weight: 900 !important;
    letter-spacing: -2px !important;
    margin-left: 2px !important;
}

.logo-num {
    color: #1A1A1A !important;
    font-size: 22px !important;
    font-weight: 900 !important;
    letter-spacing: -1.5px !important;
    margin-left: 5px !important;
}

.logo-reg {
    color: #1A1A1A !important;
    font-size: 8px !important;
    font-weight: 500 !important;
    margin-left: 2px !important;
    align-self: flex-start !important;
    margin-top: 1px !important;
}

.logo-sub {
    font-size: 7px !important;
    font-weight: 500 !important;
    letter-spacing: 3px !important;
    color: #8A8A8A !important;
    text-transform: uppercase !important;
    margin-top: 0 !important;
    line-height: 1 !important;
}

/* ============================================================
   SECTION LABELS
   ============================================================ */
.sidebar .nav-section-label {
    color: #9A9A9A !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    letter-spacing: 1.5px !important;
    text-transform: uppercase !important;
    padding: 16px 20px 6px !important;
    margin: 0 !important;
}

/* ============================================================
   NAV ITEMS
   ============================================================ */
.sidebar .nav-item {
    color: #4A4A4A !important;
    border-radius: 8px !important;
    margin: 2px 12px !important;
    padding: 10px 14px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    text-decoration: none !important;
}

.sidebar .nav-item:hover {
    background: #E0E0E0 !important;
    color: #1A1A1A !important;
    transform: translateX(2px);
}

.sidebar .nav-item.active {
    background: #D8D8D8 !important;
    color: #1A1A1A !important;
    font-weight: 600 !important;
    box-shadow: inset 3px 0 0 #6C63FF;
}

/* ============================================================
   ICONS
   ============================================================ */
.sidebar .nav-icon {
    color: #6A6A6A !important;
    font-size: 16px !important;
    flex-shrink: 0;
}

.sidebar .nav-item.active .nav-icon {
    color: #1A1A1A !important;
}

/* ============================================================
   NOTIFICATION BADGE
   ============================================================ */
.notif-badge-count {
    animation: pulseBadge 2s infinite;
}

@keyframes pulseBadge {
    0%, 100% { transform: translateY(-50%) scale(1); }
    50% { transform: translateY(-50%) scale(1.15); }
}

/* ============================================================
   WALK-IN BUTTON
   ============================================================ */
.nav-item.walkin-btn {
    background: linear-gradient(135deg, #6C63FF, #4A42CC) !important;
    color: #fff !important;
    margin: 6px 12px !important;
    border-radius: 10px !important;
    padding: 12px 16px !important;
    box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3) !important;
    transition: all 0.3s ease !important;
}

.nav-item.walkin-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 25px rgba(108, 99, 255, 0.5) !important;
    color: #fff !important;
    background: linear-gradient(135deg, #7B73FF, #5A52DC) !important;
}

.nav-item.walkin-btn.active {
    background: linear-gradient(135deg, #4A42CC, #3A32AA) !important;
    box-shadow: 0 4px 15px rgba(108, 99, 255, 0.5) !important;
}

.nav-item.walkin-btn .nav-icon {
    color: #fff !important;
}

.badge-walkin {
    background: rgba(255,255,255,0.25) !important;
    padding: 2px 10px !important;
    border-radius: 50px !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    color: #fff !important;
    margin-left: auto !important;
    animation: pulse-walkin 2s infinite !important;
}

@keyframes pulse-walkin {
    0% { background: rgba(255,255,255,0.2); }
    50% { background: rgba(255,255,255,0.4); }
    100% { background: rgba(255,255,255,0.2); }
}

/* ============================================================
   SIGN OUT
   ============================================================ */
.sidebar .nav-item.logout {
    color: #C75C5C !important;
    border-top: 1px solid #E0E0E0 !important;
    padding-top: 14px !important;
    margin-top: 16px !important;
    margin-left: 12px !important;
    margin-right: 12px !important;
    border-radius: 8px !important;
}

.sidebar .nav-item.logout:hover {
    color: #A04848 !important;
    background: rgba(199, 92, 92, 0.08) !important;
}

/* ============================================================
   USER PROFILE AT BOTTOM
   ============================================================ */
.sidebar-user-link {
    text-decoration: none !important;
    color: inherit !important;
    display: block !important;
    transition: background 0.3s ease !important;
    margin-top: auto !important;
}

.sidebar-user-link .sidebar-user {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 16px 20px !important;
    border-top: 1px solid #E0E0E0 !important;
    cursor: pointer !important;
    transition: background 0.3s ease !important;
}

.sidebar-user-link:hover .sidebar-user {
    background: #E0E0E0 !important;
}

.sidebar-user .user-info .name {
    color: #1A1A1A !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    line-height: 1.2 !important;
}

.sidebar-user .user-info .role {
    color: #8A8A8A !important;
    font-size: 11px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    margin-top: 2px !important;
}

.sidebar-user .user-avatar {
    width: 40px !important;
    height: 40px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: 700 !important;
    font-size: 16px !important;
    flex-shrink: 0 !important;
    background: #1A1A1A !important;
    color: #ECECEC !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
}

.user-edit-icon {
    margin-left: auto !important;
    font-size: 14px !important;
    opacity: 0.4 !important;
    transition: opacity 0.3s ease !important;
    color: #8A8A8A !important;
}

.sidebar-user-link:hover .user-edit-icon {
    opacity: 1 !important;
}

/* ============================================================
   ENSURE NAV ITEMS ARE CLICKABLE
   ============================================================ */
#sidebar,
#sidebar .sidebar-nav,
#sidebar .nav-item,
#sidebar .nav-item * {
    pointer-events: auto !important;
}

#sidebar .nav-item {
    cursor: pointer !important;
    position: relative !important;
    z-index: 10 !important;
}

.chat-toggle {
    z-index: 900 !important;
}
.chatbot-widget {
    z-index: 899 !important;
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    sidebar.classList.toggle('open');
    sidebar.classList.toggle('active');
    
    if (overlay) {
        overlay.classList.toggle('active');
    }
    
    // Prevent body scroll when sidebar is open on mobile
    if (window.innerWidth <= 768) {
        if (sidebar.classList.contains('open')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.remove('open');
        sidebar.classList.remove('active');
    }
    
    if (overlay) {
        overlay.classList.remove('active');
    }
    
    // Restore body scroll
    document.body.style.overflow = '';
}

document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const toggleBtn = document.querySelector('.mobile-nav-toggle, .mobile-nav-toggle-photo');
    
    // Close sidebar when clicking overlay
    if (overlay && overlay.classList.contains('active') && event.target === overlay) {
        closeSidebar();
    }
    
    // Close sidebar when clicking outside on mobile
    if (window.innerWidth <= 768) {
        if (sidebar && sidebar.classList.contains('open') && 
            !sidebar.contains(event.target) && 
            !toggleBtn?.contains(event.target)) {
            closeSidebar();
        }
    }
});

// Close sidebar when navigation links are clicked (on mobile)
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.sidebar .nav-item:not(.logout)');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });
    
    // Handle logout link separately if needed
    const logoutLink = document.querySelector('.sidebar .nav-item.logout');
    if (logoutLink) {
        logoutLink.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    }
});

// ============================================================
// REAL-TIME NOTIFICATION BADGE UPDATE
// ============================================================
(function() {
    const badge = document.querySelector('[data-notif-badge]');
    if (!badge) return;

    let lastCheck = Math.floor(Date.now() / 1000);

    function updateBadge() {
        fetch('pages/api/check_new_notifications.php?since=' + lastCheck)
            .then(r => r.json())
            .then(data => {
                if (data.checked_at) {
                    lastCheck = data.checked_at;
                }

                // Fetch total unread count
                fetch('pages/api/check_new_notifications.php?all=1')
                    .then(r => r.json())
                    .then(countData => {
                        const count = parseInt(countData.new_count) || 0;
                        if (count > 0) {
                            badge.textContent = count > 99 ? '99+' : count;
                            badge.style.display = '';
                        } else {
                            badge.style.display = 'none';
                        }
                    })
                    .catch(() => {});
            })
            .catch(() => {});
    }

    // Poll every 15 seconds
    setInterval(updateBadge, 15000);
})();
</script>