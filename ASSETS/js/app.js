// ============================================================
// STUDIO 94 SNAPTRACK — Frontend Utilities
// ============================================================

// ===== SIDEBAR TOGGLE =====
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  if (sidebar) sidebar.classList.toggle('active');
  if (overlay) overlay.classList.toggle('active');
}

// Close sidebar when a nav link is clicked on mobile
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth < 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
      }
    });
  });

  // Auto-dismiss flash alerts after 4 seconds
  const flash = document.querySelector('.alert');
  if (flash) {
    setTimeout(() => {
      flash.style.transition = 'opacity 0.4s ease';
      flash.style.opacity    = '0';
      setTimeout(() => flash.remove(), 400);
    }, 4000);
  }
});

// ===== TOAST NOTIFICATIONS =====
function showToast(message, type = 'success') {
  const existing = document.getElementById('snap-toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.id = 'snap-toast';
  const colors = { success: '#2e7d32', error: '#b91c1c', warning: '#b45309', info: '#0288d1' };
  const icons  = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };

  toast.style.cssText = `
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
    background:${colors[type]||colors.success};color:white;
    padding:12px 20px;border-radius:50px;font-size:13px;font-weight:500;
    box-shadow:0 4px 16px rgba(0,0,0,0.2);z-index:9999;
    display:flex;align-items:center;gap:8px;
    animation:slideUp 0.3s ease;
  `;
  toast.innerHTML = `<span>${icons[type]||'✅'}</span><span>${message}</span>`;
  document.body.appendChild(toast);

  const style = document.createElement('style');
  style.textContent = '@keyframes slideUp{from{transform:translateX(-50%) translateY(20px);opacity:0}to{transform:translateX(-50%) translateY(0);opacity:1}}';
  document.head.appendChild(style);

  setTimeout(() => {
    toast.style.transition = 'opacity 0.3s ease';
    toast.style.opacity    = '0';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// ===== CONFIRM DIALOG =====
function confirmAction(msg, callback) {
  if (window.confirm(msg)) callback();
}

// ===== TABLE SEARCH =====
function filterTable(inputId, tableId) {
  const query = document.getElementById(inputId)?.value.toLowerCase() || '';
  const rows  = document.querySelectorAll('#' + tableId + ' tbody tr');
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
  });
}

// ===== MODAL HELPERS =====
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}

// Close modal on Escape key
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay').forEach(m => {
      if (m.style.display === 'flex') { m.style.display = 'none'; document.body.style.overflow = ''; }
    });
  }
});

// ===== FORM VALIDATION HELPERS =====
function validateForm(formEl) {
  let valid = true;
  formEl.querySelectorAll('[required]').forEach(field => {
    if (!field.value.trim()) {
      field.style.borderColor = 'var(--red)';
      valid = false;
    } else {
      field.style.borderColor = '';
    }
  });
  return valid;
}

// ===== PRINT =====
function printSection(id) {
  const content = document.getElementById(id)?.innerHTML;
  if (!content) return;
  const win = window.open('', '_blank');
  win.document.write('<html><head><title>Print</title><link rel="stylesheet" href="' + window.location.origin + '/snaptrack/assets/css/styles.css"></head><body>' + content + '</body></html>');
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 500);
}
