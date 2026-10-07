<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Footer
// ============================================================
// Variables available: $role (from index.php), $user
// NOTE: Config and functions are already loaded by index.php
?>

</div><!-- /.app-layout -->

<!-- ===== CHATBOT (Client Only) ===== -->
<?php if (isset($role) && $role === 'client'): ?>
    <?php require_once __DIR__ . '/includes/chatbot.php'; ?>
<?php endif; ?>

<!-- ===== NOTIFICATION TOAST (Client Only) ===== -->
<?php if (isset($role) && $role === 'client'): ?>
<script>
(function() {
    let lastCheck = Math.floor(Date.now() / 1000) - 30;

    function showToast(n) {
        const toast = document.createElement('div');
        toast.className = 'notif-toast';
        toast.innerHTML = `
            <div class="notif-toast-icon">${n.icon || '🔔'}</div>
            <div class="notif-toast-body">
                <div class="notif-toast-title">${escapeHtml(n.title)}</div>
                <div class="notif-toast-msg">${escapeHtml(n.message)}</div>
                <a href="index.php?page=notifications" class="notif-toast-link">View →</a>
            </div>
            <button class="notif-toast-close" onclick="this.parentElement.remove()">✕</button>
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('notif-toast-out');
            setTimeout(() => toast.remove(), 300);
        }, 6000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function checkNewNotifications() {
        fetch('<?= APP_URL ?>/pages/api/check_new_notifications.php?since=' + lastCheck)
            .then(r => r.json())
            .then(data => {
                if (data.new_count > 0 && data.notifications) {
                    data.notifications.forEach((n, i) => {
                        setTimeout(() => showToast(n), i * 200);
                    });
                    lastCheck = Math.floor(Date.now() / 1000);
                }
            })
            .catch(err => console.warn('Notification check failed:', err));
    }

    setInterval(checkNewNotifications, 30000);
    setTimeout(checkNewNotifications, 3000);
})();
</script>

<style>
.notif-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-left: 4px solid var(--amber, #F1C40F);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    padding: 14px 18px;
    border-radius: 10px;
    max-width: 340px;
    width: calc(100% - 40px);
    z-index: 99999;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    animation: notifSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.notif-toast-icon {
    font-size: 24px;
    line-height: 1;
    flex-shrink: 0;
}
.notif-toast-body {
    flex: 1;
    min-width: 0;
}
.notif-toast-title {
    font-weight: 600;
    font-size: 14px;
    color: #2D3436;
    margin-bottom: 2px;
    word-wrap: break-word;
}
.notif-toast-msg {
    font-size: 12px;
    color: #636E72;
    line-height: 1.4;
    margin-bottom: 6px;
    word-wrap: break-word;
}
.notif-toast-link {
    font-size: 11px;
    color: #4A42CC;
    text-decoration: none;
    font-weight: 600;
}
.notif-toast-link:hover {
    text-decoration: underline;
}
.notif-toast-close {
    background: none;
    border: none;
    color: #636E72;
    cursor: pointer;
    font-size: 16px;
    padding: 0;
    line-height: 1;
    flex-shrink: 0;
    opacity: 0.6;
}
.notif-toast-close:hover {
    opacity: 1;
}
.notif-toast-out {
    animation: notifSlideOut 0.3s ease forwards;
}
@keyframes notifSlideIn {
    from { transform: translateX(120%); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
}
@keyframes notifSlideOut {
    from { transform: translateX(0); opacity: 1; }
    to   { transform: translateX(120%); opacity: 0; }
}
@media (max-width: 480px) {
    .notif-toast {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
        width: auto;
        padding: 12px 14px;
    }
}
</style>
<?php endif; ?>

<!-- ===== MAIN JAVASCRIPT ===== -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>

</body>
</html>