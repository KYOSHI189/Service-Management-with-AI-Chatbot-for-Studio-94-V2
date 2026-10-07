// ============================================================
// STUDIO 94 SNAPTRACK - Complete Photography Booking System
// ============================================================

// ===== SAMPLE DATA INITIALIZATION =====
function initSampleData() {
  // Sample Users (enhanced with phone)
  if (!localStorage.getItem('s94_users')) {
    localStorage.setItem('s94_users', JSON.stringify([
      { email: 'client@studio94.com', password: 'password123', name: 'Juan Dela Cruz', role: 'client', phone: '09171234567' },
      { email: 'maria@email.com', password: 'maria', name: 'Maria Santos', role: 'client', phone: '09181234567' },
      { email: 'pedro@email.com', password: 'pedro', name: 'Pedro Reyes', role: 'client', phone: '09191234567' },
      { email: 'ana@email.com', password: 'ana', name: 'Ana Garcia', role: 'client', phone: '09201234567' },
      { email: 'carlo@email.com', password: 'carlo', name: 'Carlo Lopez', role: 'client', phone: '09211234567' },
      { email: 'bea@email.com', password: 'bea', name: 'Bea Fernandez', role: 'client', phone: '09221234567' },
      { email: 'staff@email.com', password: 'staff', name: 'Juan dela Cruz', role: 'staff', phone: '09171112233' },
      { email: 'rosa@studio94.com', password: 'rosa', name: 'Rosa Lim', role: 'staff', phone: '09172223344' },
      { email: 'admin@email.com', password: 'admin', name: 'Admin User', role: 'admin', phone: '09170001122' }
    ]));
  }

  // Sample Bookings
  if (!localStorage.getItem('s94_bookings')) {
    localStorage.setItem('s94_bookings', JSON.stringify([
      { id: 'B001', customer: 'Juan Dela Cruz', email:'client@studio94.com', phone: '09171234567', package: 'Self-Shoot', date: '2026-06-15', time: '2:00 PM', status: 'Deposit Paid', createdAt: '2026-06-01', people: 1 },
      { id: 'B002', customer: 'Maria Santos', email:'maria@email.com', phone: '09181234567', package: 'Family', date: '2026-06-18', time: '11:00 AM', status: 'Awaiting Approval', createdAt: '2026-06-02', people: 4 },
      { id: 'B003', customer: 'Pedro Reyes', email:'pedro@email.com', phone: '09191234567', package: 'Creative', date: '2026-06-20', time: '3:00 PM', status: 'Completed', createdAt: '2026-05-15', people: 1 },
      { id: 'B004', customer: 'Ana Garcia', email:'ana@email.com', phone: '09201234567', package: 'Studio Rental', date: '2026-06-22', time: '10:00 AM', status: 'Awaiting Approval', createdAt: '2026-06-03', people: 2 },
      { id: 'B005', customer: 'Carlo Lopez', email:'carlo@email.com', phone: '09211234567', package: 'Self-Shoot', date: '2026-05-10', time: '9:00 AM', status: 'Completed', createdAt: '2026-05-01', people: 1 },
      { id: 'B006', customer: 'Bea Fernandez', email:'bea@email.com', phone: '09221234567', package: 'Family', date: '2026-05-05', time: '1:00 PM', status: 'Completed', createdAt: '2026-04-28', people: 3 }
    ]));
  }

  // Sample Payments (50% deposits)
  if (!localStorage.getItem('s94_payments')) {
    localStorage.setItem('s94_payments', JSON.stringify([
      { id: 'PAY-001', bookingId: 'B001', client: 'Juan Dela Cruz', package: 'Self-Shoot', amount: 750, status: 'PAID', type: 'DEPOSIT', method: 'GCash', ref: 'GCASH123', date: '2026-06-01' },
      { id: 'PAY-002', bookingId: 'B002', client: 'Maria Santos', package: 'Family', amount: 1750, status: 'PENDING', type: 'DEPOSIT', method: 'Bank Transfer', ref: 'BANK789', date: '2026-06-02' },
      { id: 'PAY-003', bookingId: 'B003', client: 'Pedro Reyes', package: 'Creative', amount: 2500, status: 'PAID', type: 'DEPOSIT', method: 'Maya', ref: 'MAYA456', date: '2026-05-16' },
      { id: 'PAY-004', bookingId: 'B004', client: 'Ana Garcia', package: 'Studio Rental', amount: 1250, status: 'UNPAID', type: 'DEPOSIT', date: '2026-06-03' },
      { id: 'PAY-005', bookingId: 'B005', client: 'Carlo Lopez', package: 'Self-Shoot', amount: 750, status: 'PAID', type: 'DEPOSIT', method: 'GCash', ref: 'GC999', date: '2026-05-02' },
      { id: 'PAY-006', bookingId: 'B006', client: 'Bea Fernandez', package: 'Family', amount: 1750, status: 'PAID', type: 'DEPOSIT', method: 'Maya', ref: 'MY888', date: '2026-04-29' }
    ]));
  }

  // Sample Inventory
  if (!localStorage.getItem('s94_inventory')) {
    localStorage.setItem('s94_inventory', JSON.stringify([
      { id: 1, name: 'Maroon Backdrop', category: 'Backdrop', quantity: 2, threshold: 1 },
      { id: 2, name: 'Battery Pack', category: 'Equipment', quantity: 1, threshold: 2 },
      { id: 3, name: 'Softbox Light', category: 'Lighting', quantity: 4, threshold: 1 },
      { id: 4, name: 'White Backdrop', category: 'Backdrop', quantity: 3, threshold: 1 },
      { id: 5, name: 'LED Ring Light', category: 'Lighting', quantity: 0, threshold: 1 }
    ]));
  }

  // Sample Loyalty
  if (!localStorage.getItem('s94_loyalty')) {
    localStorage.setItem('s94_loyalty', JSON.stringify([
      { client: 'Juan Dela Cruz', bookings: 1, rewardsUsed: [] },
      { client: 'Maria Santos', bookings: 2, rewardsUsed: [] },
      { client: 'Pedro Reyes', bookings: 3, rewardsUsed: [] },
      { client: 'Ana Gonzales', bookings: 4, rewardsUsed: [] }
    ]));
  }

  // Sample Feedback
  if (!localStorage.getItem('s94_feedbacks')) {
    localStorage.setItem('s94_feedbacks', JSON.stringify([
      { id: 1, date: '2026-05-20', client: 'Juan Dela Cruz', comment: 'Ang ganda ng kuha! Satisfied ako.', ratings: { overall: 5, staff: 5, quality: 5, timeliness: 5 }, sentiment: 'positive', topics: ['photographer'], urgent: false },
      { id: 2, date: '2026-05-25', client: 'Maria Santos', comment: 'Ang bagal ng delivery ng photos.', ratings: { overall: 2, staff: 3, quality: 4, timeliness: 1 }, sentiment: 'negative', topics: ['delivery'], urgent: true },
      { id: 3, date: '2026-05-28', client: 'Pedro Reyes', comment: 'Maayos naman ang staff.', ratings: { overall: 4, staff: 4, quality: 4, timeliness: 4 }, sentiment: 'positive', topics: ['backdrop'], urgent: false },
      { id: 4, date: '2026-06-05', client: 'Current User', comment: 'Super nice studio setup!', ratings: { overall: 5, staff: 5, quality: 5, timeliness: 5 }, sentiment: 'positive', topics: ['photographer', 'backdrop'], urgent: false }
    ]));
  }

  // Sample Sales (enhanced with monthly data)
  if (!localStorage.getItem('s94_sales')) {
    localStorage.setItem('s94_sales', JSON.stringify([
      { date: '2026-01-15', amount: 2800, package: 'Family', bookingId: 'S01' },
      { date: '2026-02-10', amount: 4500, package: 'Creative', bookingId: 'S02' },
      { date: '2026-02-25', amount: 1500, package: 'Self-Shoot', bookingId: 'S03' },
      { date: '2026-03-05', amount: 2800, package: 'Family', bookingId: 'S04' },
      { date: '2026-03-20', amount: 8000, package: 'Studio Rental', bookingId: 'S05' },
      { date: '2026-04-10', amount: 1500, package: 'Self-Shoot', bookingId: 'S06' },
      { date: '2026-04-22', amount: 4500, package: 'Creative', bookingId: 'S07' },
      { date: '2026-05-02', amount: 750, package: 'Self-Shoot', bookingId: 'B005' },
      { date: '2026-05-16', amount: 2500, package: 'Creative', bookingId: 'B003' },
      { date: '2026-04-29', amount: 1750, package: 'Family', bookingId: 'B006' },
      { date: '2026-06-01', amount: 750, package: 'Self-Shoot', bookingId: 'B001' }
    ]));
  }

  // Sample Photos
  if (!localStorage.getItem('s94_v2_photos')) {
    localStorage.removeItem('s94_photos');
    localStorage.setItem('s94_photos', JSON.stringify([
      { bookingId: 'B003', package: 'Creative', client: 'Pedro Reyes', images: ['https://images.unsplash.com/photo-1542038784456-1ea8e935640e?q=80&w=400', 'https://images.unsplash.com/photo-1516726814777-61e813f56ceb?q=80&w=400'], uploadedAt: '2026-06-21', status: 'Ready' },
      { bookingId: 'B005', package: 'Premium Package', client: 'Current User', images: ['https://images.unsplash.com/photo-1511690656952-34342bb7c2f2?q=80&w=400', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=400', 'https://images.unsplash.com/photo-1506744626753-159f8072bba5?q=80&w=400'], uploadedAt: '2026-05-15', status: 'Ready' },
      { bookingId: 'B006', package: 'Basic Package', client: 'Current User', images: ['https://images.unsplash.com/photo-1519014816548-bf5fece59bf0?q=80&w=400', 'https://images.unsplash.com/photo-1554048665-27a3c306d755?q=80&w=400'], uploadedAt: '2026-04-10', status: 'Ready' }
    ]));
    localStorage.setItem('s94_v2_photos', '1');
  }

  // Sample Notifications (role-aware)
  if (!localStorage.getItem('s94_notifications')) {
    localStorage.setItem('s94_notifications', JSON.stringify([
      { id: 1, type: 'photo', role: 'client', title: 'Photos Ready for Download', message: 'Your photos from the Premium Package session are now ready.', time: '2026-06-10T10:00', icon: '📸', read: false },
      { id: 2, type: 'status', role: 'client', title: 'Booking Confirmed', message: 'Your Studio Rental session on June 22 has been confirmed.', time: '2026-06-09T14:00', icon: '✅', read: false },
      { id: 3, type: 'reminder', role: 'client', title: 'Session Reminder', message: 'Your Studio Rental session is in 3 days.', time: '2026-06-08T09:00', icon: '⏰', read: true },
      { id: 4, type: 'booking', role: 'staff', title: 'New Booking Request', message: 'Maria Santos requested a Family Package on June 18.', time: '2026-06-02T11:00', icon: '📅', read: false },
      { id: 5, type: 'booking', role: 'staff', title: 'New Booking Request', message: 'Ana Garcia requested a Studio Rental on June 22.', time: '2026-06-03T15:00', icon: '📅', read: false },
      { id: 6, type: 'payment', role: 'staff', title: 'Payment Verification Needed', message: 'Maria Santos submitted a payment proof for PAY-002.', time: '2026-06-02T12:00', icon: '💳', read: true },
      { id: 7, type: 'booking', role: 'admin', title: 'Booking Volume Alert', message: '4 new bookings this week. Revenue trending up 18%.', time: '2026-06-05T08:00', icon: '📊', read: false },
      { id: 8, type: 'system', role: 'admin', title: 'Low Stock Alert', message: 'LED Ring Light is out of stock. Battery Pack is below threshold.', time: '2026-06-04T10:00', icon: '⚠️', read: false }
    ]));
  }

  // Studio Settings
  if (!localStorage.getItem('s94_settings')) {
    localStorage.setItem('s94_settings', JSON.stringify({
      studioName: 'Studio 94',
      address: '94 Rizal Ave, Manila',
      email: 'hello@studio94.com',
      phone: '+63 2 8123 4567',
      hoursWeekday: '9:00 AM – 7:00 PM',
      hoursSaturday: '9:00 AM – 5:00 PM',
      hoursSunday: '10:00 AM – 3:00 PM'
    }));
  }

  // Packages
  if (!localStorage.getItem('s94_packages')) {
    localStorage.setItem('s94_packages', JSON.stringify([
      { id: 1, name: 'Basic', price: 1500, duration: '1 Hour', photos: 10, outfits: 1, color: '#B0C4DE', features: ['1 Hour Session', '10 Edited Photos', '1 Outfit Change'] },
      { id: 2, name: 'Premium', price: 2800, duration: '2 Hours', photos: 25, outfits: 3, color: '#D4A0A0', popular: true, features: ['2 Hour Session', '25 Edited Photos', '3 Outfit Changes'] },
      { id: 3, name: 'Deluxe', price: 4500, duration: '4 Hours', photos: 50, outfits: 99, color: '#A0C4A0', features: ['4 Hour Session', '50 Edited Photos', 'Unlimited Outfits'] },
      { id: 4, name: 'Event', price: 8000, duration: 'Full Day', photos: 100, outfits: 99, color: '#D4C4A0', features: ['Full Day Coverage', '100+ Photos', '2 Photographers'] }
    ]));
  }
}


// Initialize on first load
initSampleData();

// ===== UTILITY FUNCTIONS =====
function getBookings() { return JSON.parse(localStorage.getItem('s94_bookings')) || []; }
function saveBookings(data) { localStorage.setItem('s94_bookings', JSON.stringify(data)); }
function getPayments() { return JSON.parse(localStorage.getItem('s94_payments')) || []; }
function savePayments(data) { localStorage.setItem('s94_payments', JSON.stringify(data)); }
function getInventory() { return JSON.parse(localStorage.getItem('s94_inventory')) || []; }
function saveInventory(data) { localStorage.setItem('s94_inventory', JSON.stringify(data)); }
function getLoyalty() { return JSON.parse(localStorage.getItem('s94_loyalty')) || []; }
function saveLoyalty(data) { localStorage.setItem('s94_loyalty', JSON.stringify(data)); }
function getFeedbacks() { return JSON.parse(localStorage.getItem('s94_feedbacks')) || []; }
function saveFeedbacks(data) { localStorage.setItem('s94_feedbacks', JSON.stringify(data)); }
function getSales() { return JSON.parse(localStorage.getItem('s94_sales')) || []; }
function saveSales(data) { localStorage.setItem('s94_sales', JSON.stringify(data)); }
function getNotifications() { return JSON.parse(localStorage.getItem('s94_notifications')) || []; }
function saveNotifications(data) { localStorage.setItem('s94_notifications', JSON.stringify(data)); }
function getUsers() { return JSON.parse(localStorage.getItem('s94_users')) || []; }
function saveUsers(data) { localStorage.setItem('s94_users', JSON.stringify(data)); }
function getSettings() { return JSON.parse(localStorage.getItem('s94_settings')) || {}; }
function saveSettings(data) { localStorage.setItem('s94_settings', JSON.stringify(data)); }
function getPackages() { return JSON.parse(localStorage.getItem('s94_packages')) || []; }
function savePackages(data) { localStorage.setItem('s94_packages', JSON.stringify(data)); }

// ===== DYNAMIC HELPERS =====
function addNotification(title, message, icon, role) {
  const notifs = getNotifications();
  notifs.unshift({ id: Date.now(), type: 'system', role: role || 'all', title, message, time: new Date().toISOString(), icon: icon || '🔔', read: false });
  saveNotifications(notifs);
}

function checkConflict(date, time, excludeId) {
  const bookings = getBookings().filter(b => b.status !== 'Cancelled' && b.id !== excludeId);
  return bookings.filter(b => b.date === date && b.time === time);
}

function createWalkinBooking(data) {
  const bookings = getBookings();
  const id = 'W' + Date.now().toString().slice(-6);
  const booking = { id, customer: data.name, email: data.email || '', phone: data.phone, package: data.package, date: data.date, time: data.time, people: data.people || 1, status: 'Awaiting Approval', createdAt: new Date().toISOString().split('T')[0], type: 'walk-in', notes: data.notes || '' };
  bookings.push(booking);
  saveBookings(bookings);
  // Create payment record
  const pkgPrices = { 'Self-Shoot': 1500, 'Studio Rental': 2500, 'Family': 3500, 'Creative': 5000 };
  const payments = getPayments();
  payments.push({ id: 'PAY-' + id, bookingId: id, client: data.name, package: data.package, amount: (pkgPrices[data.package] || 1500) / 2, status: 'UNPAID', type: 'DEPOSIT', date: new Date().toISOString().split('T')[0] });
  savePayments(payments);
  addNotification('Walk-in Booking Created', data.name + ' — ' + data.package + ' on ' + data.date, '🚶', 'staff');
  return booking;
}

function formatTimeAgo(isoString) {
  if (!isoString || !isoString.includes('-')) return isoString || '';
  const d = new Date(isoString);
  const now = new Date();
  const diff = Math.floor((now - d) / 1000);
  if (diff < 60) return 'Just now';
  if (diff < 3600) return Math.floor(diff/60) + 'm ago';
  if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
  if (diff < 604800) return Math.floor(diff/86400) + 'd ago';
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function getClientStats(clientName) {
  const bookings = getBookings().filter(b => b.customer === clientName);
  const payments = getPayments().filter(p => p.client === clientName && p.status === 'PAID');
  return { totalBookings: bookings.length, totalSpent: payments.reduce((s,p) => s + p.amount, 0), bookings };
}

function exportTableCSV(headers, rows, filename) {
  let csv = headers.join(',') + '\n';
  rows.forEach(r => { csv += r.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',') + '\n'; });
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename + '_' + new Date().toISOString().split('T')[0] + '.csv';
  a.click();
  URL.revokeObjectURL(a.href);
  showToast('CSV exported!', 'success');
}

// ===== PAGE NAVIGATION =====
function showPage(pageId, navEl) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('page-' + pageId);
  if (target) target.classList.add('active');

  document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
  if (navEl) {
    navEl.classList.add('active');
  } else {
    document.querySelectorAll('.nav-item').forEach(item => {
      if (item.getAttribute('onclick') && item.getAttribute('onclick').includes("'" + pageId + "'")) {
        item.classList.add('active');
      }
    });
  }

  const titles = {
    dashboard: ['Dashboard', 'Welcome back!'],
    booking: ['Book a Session', 'Choose your package and date'],
    bookings: ['My Bookings', 'View and manage your sessions'],
    payments: ['My Payments', 'Manage your payments'],
    feedback: ['Feedback', 'Share your experience'],
    photos: ['My Photos', 'Download your memories'],
    notifications: ['Notifications', 'Stay up to date'],
    profile: ['My Profile', 'Manage your account'],
    clients: ['Clients', 'Manage client accounts'],
    schedule: ['Schedule', 'View weekly calendar'],
    inventory: ['Inventory', 'Manage studio equipment'],
    upload: ['Upload Photos', 'Send photos to clients'],
    'feedback-analytics': ['Feedback Analytics', 'Client reviews'],
    walkin: ['Walk-in Booking', 'Create walk-in session'],
    'loyalty-cards': ['Loyalty Cards', 'Admin View Only — Client tier metrics'],
    analytics: ['Dashboard', 'April 2026 Overview'],
    packages: ['Packages', 'Manage service packages'],
    users: ['Staff', 'Manage team members'],
    reports: ['Reports', 'Generate business reports'],
    settings: ['Settings', 'Studio configuration']
  };

  const titleEl = document.getElementById('page-title');
  const subEl = document.getElementById('page-sub');
  if (titleEl && titles[pageId]) titleEl.textContent = titles[pageId][0];
  if (subEl && titles[pageId]) subEl.textContent = titles[pageId][1];

    if (pageId === 'analytics' && typeof renderAdminAnalytics === 'function') renderAdminAnalytics();
    if (pageId === 'bookings' && typeof renderAdminBookings === 'function') renderAdminBookings();
    if (pageId === 'payments' && typeof renderAdminPayments === 'function') renderAdminPayments();
    if (pageId === 'loyalty-cards' && typeof renderAdminLoyalty === 'function') renderAdminLoyalty();
    
    // Page-specific renders for Client & Staff
    if (pageId === 'payments' && typeof renderClientPayments === 'function') renderClientPayments();
    if (pageId === 'bookings' && typeof renderClientBookings === 'function') renderClientBookings();
    if (pageId === 'inventory' && typeof renderInventory === 'function') renderInventory();
    if (pageId === 'feedback-analytics' && typeof renderFeedbackAnalytics === 'function') renderFeedbackAnalytics();
    if (pageId === 'feedback' && typeof renderClientFeedbackHistory === 'function') renderClientFeedbackHistory();
    if (pageId === 'photos' && typeof renderClientPhotos === 'function') renderClientPhotos();
    if (pageId === 'notifications' && typeof renderNotifications === 'function') renderNotifications();
    if (pageId === 'profile' && typeof renderProfile === 'function') renderProfile();
    if (pageId === 'dashboard') {
      // Check if we're on staff dashboard
      if (document.getElementById('staff-payments-body')) {
        if(typeof renderStaffPayments === 'function') renderStaffPayments();
        if(typeof renderStaffBookings === 'function') renderStaffBookings();
      }
    }
}

// ===== AUTH FUNCTIONS =====
function showTab(tab, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(tab + '-tab').classList.add('active');
  if (btn) btn.classList.add('active');
  const heading = document.getElementById('form-heading');
  if (heading) heading.innerHTML = tab === 'login' ? 'Welcome<br/>back.' : 'Create your<br/>account.';
}

function selectRole(btn, role) {
  document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

function handleLogin(e) {
  e.preventDefault();
  const email = e.target.querySelector('input[type="email"]').value;
  const password = e.target.querySelector('input[type="password"]').value;
  
  const users = JSON.parse(localStorage.getItem('s94_users')) || [];
  const user = users.find(u => u.email === email && u.password === password);
  
  if (user) {
    localStorage.setItem('s94_current_user', JSON.stringify(user));
    const pages = { client: 'client-dashboard.html', staff: 'staff-dashboard.html', admin: 'admin-dashboard.html' };
    window.location.href = pages[user.role] || 'client-dashboard.html';
  } else {
    // Attempt fallback authentication
    let role = 'client';
    if (email.includes('admin')) role = 'admin';
    if (email.includes('staff')) role = 'staff';
    
    if (password === 'admin' || password === 'staff' || password === 'maria' || password === 'password123') {
       const currentUser = { email, name: role.charAt(0).toUpperCase() + role.slice(1) + ' User', role };
       localStorage.setItem('s94_current_user', JSON.stringify(currentUser));
       const pages = { client: 'client-dashboard.html', staff: 'staff-dashboard.html', admin: 'admin-dashboard.html' };
       window.location.href = pages[role] || 'client-dashboard.html';
    } else {
       if (typeof showToast === 'function') showToast('Invalid email or password', 'error');
       else alert('Invalid email or password');
    }
  }
}

function handleRegister(e) {
  e.preventDefault();
  const inputs = e.target.querySelectorAll('input');
  const fname = inputs[0].value;
  const lname = inputs[1].value;
  const email = inputs[2].value;
  const password = inputs[4].value;
  
  const users = JSON.parse(localStorage.getItem('s94_users')) || [];
  users.push({ email, password, name: `${fname} ${lname}`, role: 'client' });
  localStorage.setItem('s94_users', JSON.stringify(users));
  
  if (typeof showToast === 'function') showToast('Account created! Please sign in.', 'success');
  else alert('Account created! Please sign in.');
  
  showTab('login');
}

// ===== PACKAGE DATA =====
const packageData = {
  'Self-Shoot': { price: '₱1,500', description: 'DIY photoshoot with professional lighting.', duration: '2 hours', inclusions: ['Studio access', 'Professional lighting', '30 edited photos'], images: ['assets/f1.JPG','assets/f2.JPG','assets/f3.JPG'] },
  'Studio Rental': { price: '₱2,500', description: 'Professional studio space with lighting.', duration: '1 hour', inclusions: ['Studio Space', 'Basic Lighting kit', 'Changing Room'], images: ['assets/pink.JPG','assets/p2.JPG','assets/p3.JPG'] },
  'Family': { price: '₱3,500', description: 'Inclusive session for families.', duration: '3 hours', inclusions: ['Family Props', '20 Edited Photos', 'Digital Copies'], images: ['assets/f1.JPG','assets/f2.JPG','assets/f3.JPG'] },
  'Creative': { price: '₱5,000', description: 'High-concept shoot with artistic direction.', duration: '4 hours', inclusions: ['Artistic Direction', 'Advanced Retouching', 'Pro Stylist'], images: ['assets/pink.JPG','assets/f3.JPG','assets/p2.JPG'] }
};

let selectedPackage = '';

function openPackageModal(pkgName) {
  selectedPackage = pkgName;
  const pkg = packageData[pkgName];
  
  document.getElementById('modal-pkg-name').textContent = pkgName.toUpperCase();
  document.getElementById('modal-pkg-price').textContent = pkg.price;
  document.getElementById('modal-pkg-desc').textContent = pkg.description;
  document.getElementById('modal-pkg-duration').textContent = pkg.duration;
  document.getElementById('form-selected-pkg').textContent = pkgName;
  document.getElementById('form-selected-price').textContent = pkg.price;
  
  document.getElementById('modal-pkg-inclusions').innerHTML = pkg.inclusions.map(i => `<li>${i}</li>`).join('');
  
  const sampleImagesContainer = document.querySelector('.sample-images');
  if (sampleImagesContainer && pkg.images) {
    const imgs = sampleImagesContainer.querySelectorAll('img');
    pkg.images.forEach((url, idx) => {
      if (imgs[idx]) imgs[idx].src = url;
    });
  }
  
  switchStep('step-details');
  document.getElementById('booking-modal').style.display = 'flex';
}

function switchStep(stepId) {
  document.querySelectorAll('.modal-step').forEach(s => s.classList.remove('active'));
  document.getElementById(stepId)?.classList.add('active');
}

function showBookingStep() {
  switchStep('step-booking-combined');
  renderCalendar();
  
  // Populate loyalty options
  const currentUser = JSON.parse(localStorage.getItem('s94_current_user'));
  const optionsContainer = document.getElementById('loyalty-options');
  const selectionSection = document.getElementById('loyalty-rewards-selection');
  const checkbox = document.getElementById('use-loyalty');
  
  if (currentUser && optionsContainer && selectionSection) {
    const status = getLoyaltyStatus(currentUser.name);
    if (status.earnedCount > 0) {
      selectionSection.style.display = 'block';
      checkbox.checked = false;
      optionsContainer.style.display = 'none';
      
      optionsContainer.innerHTML = status.rewards.map(r => {
        const isEarned = status.earnedCount >= r.id;
        return `
          <label style="display:flex; align-items:center; gap:8px; margin-bottom: 6px; font-size: 12px; cursor: ${isEarned ? 'pointer' : 'not-allowed'}; opacity: ${isEarned ? '1' : '0.5'};">
            <input type="radio" name="loyalty-reward" value="${r.short}" ${isEarned ? '' : 'disabled'}>
            <span style="flex:1;">${r.title}</span>
            <span style="font-size:10px; color:var(--muted);">${isEarned ? '(available)' : '(not yet available)'}</span>
          </label>
        `;
      }).join('');
    } else {
      selectionSection.style.display = 'none';
    }
  }
}

function toggleLoyaltyOptions() {
  const checkbox = document.getElementById('use-loyalty');
  const options = document.getElementById('loyalty-options');
  if (options) options.style.display = checkbox.checked ? 'block' : 'none';
}

function backToDetails() {
  switchStep('step-details');
}

function closePackageModal() {
  document.getElementById('booking-modal').style.display = 'none';
}

// ===== CALENDAR =====
let currentCalDate = new Date(2026, 5, 1);

function renderCalendar() {
  const grid = document.getElementById('calendar-grid');
  const monthLabel = document.getElementById('calendar-month-year');
  if (!grid) return;

  grid.innerHTML = '';
  const month = currentCalDate.getMonth();
  const year = currentCalDate.getFullYear();
  monthLabel.textContent = currentCalDate.toLocaleString('default', { month: 'long', year: 'numeric' });

  ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach(d => {
    const el = document.createElement('div');
    el.className = 'calendar-day-label';
    el.textContent = d;
    grid.appendChild(el);
  });

  const firstDay = new Date(year, month, 1).getDay();
  const lastDate = new Date(year, month + 1, 0).getDate();
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const bookings = getBookings();

  for (let i = 0; i < firstDay; i++) {
    grid.appendChild(document.createElement('div'));
  }

  for (let d = 1; d <= lastDate; d++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const dateObj = new Date(year, month, d);
    let status = 'available';

    if (dateObj < today) {
      status = 'past';
    } else {
      const match = bookings.find(b => b.date === dateStr);
      if (match) {
        status = match.status === 'Awaiting Approval' ? 'pending' : 'booked';
      }
    }

    const cell = document.createElement('div');
    cell.className = `calendar-date date-${status}`;
    cell.textContent = d;
    cell.onclick = () => selectDate(dateStr, status);
    grid.appendChild(cell);
  }
}

function selectDate(dateStr, status) {
  if (status === 'past' || status === 'booked') return;
  document.getElementById('book-date').value = dateStr;
  renderCalendar();
}

function changeMonth(delta) {
  currentCalDate.setMonth(currentCalDate.getMonth() + delta);
  renderCalendar();
}

// ===== BOOKING SUBMISSION =====
function handleBookingSubmit() {
  const date = document.getElementById('book-date').value;
  const time = document.getElementById('book-time').value;
  const phone = document.getElementById('book-phone').value;

  if (!date) {
    showToast('Please select a date', 'warning');
    return;
  }

  // Check loyalty reward
  const useLoyalty = document.getElementById('use-loyalty')?.checked;
  const selectedReward = document.querySelector('input[name="loyalty-reward"]:checked')?.value;
  let finalRequests = document.getElementById('book-requests').value;
  let discount = 1;

  if (useLoyalty && selectedReward) {
    finalRequests += `\n[LOYALTY REWARD APPLIED: ${selectedReward}]`;
    if (selectedReward === '50% OFF') discount = 0.5;
  }

  const pkgPrice = parseInt(packageData[selectedPackage].price.replace(/[₱,]/g, ''));
  const finalAmount = Math.floor(pkgPrice * discount * 0.5); // 50% deposit of final price

  const bookings = getBookings();
  const newBooking = {
    id: 'B' + String(bookings.length + 1).padStart(3, '0'),
    customer: 'Current User',
    phone: phone,
    package: selectedPackage,
    date: date,
    time: time,
    status: 'Awaiting Approval',
    createdAt: new Date().toISOString(),
    requests: finalRequests,
    loyaltyReward: useLoyalty ? selectedReward : null
  };

  bookings.unshift(newBooking);
  saveBookings(bookings);

  // Create payment record
  const payments = getPayments();
  payments.push({
    id: 'PAY-' + String(payments.length + 1).padStart(3, '0'),
    bookingId: newBooking.id,
    client: newBooking.customer,
    package: selectedPackage,
    amount: finalAmount,
    status: 'UNPAID',
    type: 'DEPOSIT'
  });
  savePayments(payments);

  document.getElementById('success-pkg-name').textContent = selectedPackage;
  switchStep('step-success');
  
  // Notifications
  addNotification('New Booking Request', newBooking.customer + ' — ' + newBooking.package, '📅', 'staff');
  addNotification('New Booking Request', newBooking.customer + ' — ' + newBooking.package, '📅', 'admin');
  
  showToast('Booking submitted! Awaiting approval.', 'success');
}

// ===== CLIENT BOOKINGS RENDER =====
function renderClientBookings() {
  const bookings = getBookings();
  const container = document.getElementById('client-bookings-body');
  if (!container) return;

  container.innerHTML = bookings.map(b => `
    <tr>
      <td><strong>${b.package}</strong></td>
      <td>${b.date}</td>
      <td>${b.time}</td>
      <td><span class="badge ${(b.status === 'Completed' || b.status === 'Deposit Paid') ? 'badge-green' : (b.status === 'Awaiting Approval' || b.status === 'Approved (Unpaid)') ? 'badge-amber' : 'badge-gray'}">${b.status}</span></td>
      <td><button class="btn-ghost btn-sm" onclick="viewBookingDetails('${b.id}')">Details</button></td>
    </tr>
  `).join('');
}

function viewBookingDetails(id) {
  const booking = getBookings().find(b => b.id === id);
  if (booking) {
    alert(`Booking: ${booking.id}\nPackage: ${booking.package}\nDate: ${booking.date}\nTime: ${booking.time}\nStatus: ${booking.status}`);
  }
}

// ===== STAFF BOOKING MANAGEMENT =====
function renderStaffBookings() {
  const bookings = getBookings();
  const container = document.getElementById('staff-bookings-body');
  if (!container) return;

  container.innerHTML = bookings.map(b => {
    let actionBtn = '';
    if (b.status === 'Awaiting Approval') {
      actionBtn = `
        <button class="btn-green btn-sm" onclick="approveBooking('${b.id}')">APPROVE</button>
        <button class="btn-red btn-sm" onclick="rejectBooking('${b.id}')">REJECT</button>
      `;
    } else if (b.status === 'Approved (Unpaid)') {
      actionBtn = '<span style="color: var(--amber); font-size:11px;">Waiting for client deposit...</span>';
    } else if (b.status === 'Deposit Paid') {
      actionBtn = `<button class="btn-primary btn-sm" onclick="completeBooking('${b.id}')">MARK COMPLETE</button>`;
    } else {
      actionBtn = '<span style="color: var(--muted);">—</span>';
    }

    const badgeClass = (b.status === 'Completed' || b.status === 'Deposit Paid') ? 'badge-green' : (b.status === 'Awaiting Approval' || b.status === 'Approved (Unpaid)') ? 'badge-amber' : 'badge-gray';

    return `
      <tr>
        <td><strong>${b.customer}</strong></td>
        <td>${b.package}</td>
        <td>${b.date}</td>
        <td>${b.time}</td>
        <td><span class="badge ${badgeClass}">${b.status}</span></td>
        <td>${actionBtn}</td>
      </tr>
    `;
  }).join('');
}

function approveBooking(id) {
  const bookings = getBookings();
  const index = bookings.findIndex(b => b.id === id);
  if (index !== -1) {
    bookings[index].status = 'Approved (Unpaid)';
    saveBookings(bookings);
    renderStaffBookings();
    if(typeof renderAdminAnalytics === 'function') renderAdminAnalytics();
    
    // Notifications
    addNotification('Booking Approved', 'Your booking ' + id + ' has been approved. Please pay deposit.', '✅', bookings[index].customer);
    addNotification('Booking Approved', bookings[index].customer + '\'s booking is approved.', '✅', 'admin');
    
    showToast('Booking approved! Waiting for deposit.', 'success');
  }
}

function rejectBooking(id) {
  const reason = prompt('Rejection reason:');
  if (reason === null) return;
  
  const bookings = getBookings();
  const index = bookings.findIndex(b => b.id === id);
  if (index !== -1) {
    bookings[index].status = 'Rejected';
    bookings[index].rejectionReason = reason;
    saveBookings(bookings);
    renderStaffBookings();
    
    // Notification
    addNotification('Booking Rejected', 'Your booking ' + id + ' was rejected: ' + reason, '❌', bookings[index].customer);
    
    showToast('Booking rejected', 'error');
  }
}

function completeBooking(id) {
  const bookings = getBookings();
  const b = bookings.find(x => x.id === id);
  if(b) {
    b.status = 'Completed';
    saveBookings(bookings);
    
    // Update Loyalty
    const loyalty = getLoyalty();
    let entry = loyalty.find(l => l.client === b.customer);
    if (entry) {
      entry.bookings++;
    } else {
      loyalty.push({ client: b.customer, bookings: 1, rewardsUsed: [] });
    }
    saveLoyalty(loyalty);

    // Notifications
    addNotification('Session Completed', 'Your session ' + id + ' is complete! Points awarded.', '📸', b.customer);
    addNotification('Session Completed', b.customer + '\'s session is complete.', '📸', 'admin');

    showToast('Booking completed!', 'success');
    renderStaffBookings();
    if(typeof renderAdminLoyalty === 'function') renderAdminLoyalty();
  }
}

// ===== PAYMENT MODULE =====
let currentPaymentId = null;

function renderClientPayments() {
  const payments = getPayments();
  const container = document.getElementById('payments-list');
  if (!container) return;

  // Update stats
  const totalPaid = payments.filter(p => p.status === 'PAID').reduce((s, p) => s + p.amount, 0);
  const totalPending = payments.filter(p => p.status === 'PENDING').reduce((s, p) => s + p.amount, 0);
  const totalUnpaid = payments.filter(p => p.status === 'UNPAID').reduce((s, p) => s + p.amount, 0);

  const statPaid = document.getElementById('stat-total-paid');
  const statPending = document.getElementById('stat-pending');
  const statUnpaid = document.getElementById('stat-unpaid');
  if (statPaid) statPaid.textContent = totalPaid.toLocaleString();
  if (statPending) statPending.textContent = totalPending.toLocaleString();
  if (statUnpaid) statUnpaid.textContent = totalUnpaid.toLocaleString();

  container.innerHTML = payments.map(p => {
    let badge = '', action = '';
    
    if (p.status === 'UNPAID') {
      badge = '<span class="badge badge-gray">UNPAID</span>';
      action = `<button class="btn-black btn-sm" onclick="openPaymentModal('${p.id}')">PAY NOW</button>`;
    } else if (p.status === 'PENDING') {
      badge = '<span class="badge badge-amber">PENDING</span>';
      action = '<span style="font-size:11px;color:var(--amber);">⏳ Verifying</span>';
    } else if (p.status === 'PAID') {
      badge = '<span class="badge badge-green">PAID</span>';
      action = '<span style="font-size:11px;color:var(--green);">✓ Complete</span>';
    } else if (p.status === 'REJECTED') {
      badge = '<span class="badge badge-red">REJECTED</span>';
      action = `<button class="btn-black btn-sm" onclick="openPaymentModal('${p.id}')">RE-SUBMIT</button>`;
    }

    return `
      <div style="display:flex;align-items:center;gap:16px;padding:16px 0;border-bottom:1px solid var(--border);">
        <div style="flex:1;">
          <div style="font-weight:600;">${p.bookingId} - ${p.package}</div>
          <div style="font-size:12px;color:var(--muted);">${p.type} Payment</div>
        </div>
        <div style="font-weight:700;">₱${p.amount.toLocaleString()}</div>
        <div>${badge}</div>
        <div style="min-width:100px;">${action}</div>
      </div>
    `;
  }).join('');
}

function openPaymentModal(paymentId) {
  const payment = getPayments().find(p => p.id === paymentId);
  if (!payment) return;

  currentPaymentId = paymentId;
  document.getElementById('pay-modal-package').textContent = payment.package;
  document.getElementById('pay-modal-amount').textContent = payment.amount.toLocaleString();
  document.getElementById('pay-modal-booking-id').textContent = payment.bookingId;
  
  // Reset payment method to Maya and show its account info
  const payMethodEl = document.getElementById('pay-method');
  if (payMethodEl) payMethodEl.value = 'Maya';
  togglePaymentAccountInfo();

  document.getElementById('payment-upload-modal').style.display = 'flex';
}

// ===== PAYMENT ACCOUNT INFO TOGGLE =====
function togglePaymentAccountInfo() {
  const method = document.getElementById('pay-method').value;
  const mayaSection = document.getElementById('account-maya');
  const gcashSection = document.getElementById('account-gcash');
  const bankSection = document.getElementById('account-bank');

  if (mayaSection) mayaSection.style.display = 'none';
  if (gcashSection) gcashSection.style.display = 'none';
  if (bankSection) bankSection.style.display = 'none';

  if (method === 'Maya' && mayaSection) {
    mayaSection.style.display = 'block';
  } else if (method === 'GCash' && gcashSection) {
    gcashSection.style.display = 'block';
  } else if (method === 'Bank Transfer' && bankSection) {
    bankSection.style.display = 'block';
  }
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    showToast('Copied to clipboard!', 'success');
  }).catch(() => {
    // Fallback for older browsers
    const temp = document.createElement('input');
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    document.body.removeChild(temp);
    showToast('Copied to clipboard!', 'success');
  });
}

function submitPaymentProof(proofImg) {
  if (!currentPaymentId) return;

  const method = document.getElementById('pay-method').value;
  const ref = document.getElementById('pay-ref').value;

  if (!ref) {
    showToast('Please enter reference number', 'warning');
    return;
  }

  const payments = getPayments();
  const index = payments.findIndex(p => p.id === currentPaymentId);
  
  if (index !== -1) {
    payments[index].status = 'PENDING';
    payments[index].method = method;
    payments[index].ref = ref;
    payments[index].proof = true; // Mark as having proof
    savePayments(payments);
    
    // Save image proof to separate storage to avoid bloat
    const proofs = JSON.parse(localStorage.getItem('s94_proofs')) || {};
    proofs[currentPaymentId] = proofImg;
    localStorage.setItem('s94_proofs', JSON.stringify(proofs));
    
    if (typeof closeModal === 'function') closeModal('payment-upload-modal'); else document.getElementById('payment-upload-modal').style.display = 'none';
    renderClientPayments();
    
    // Notify Staff
    addNotification('New Payment Proof', payments[index].client + ' submitted proof for ' + payments[index].bookingId, '💳', 'staff');
    
    showToast('Proof submitted! Awaiting verification.', 'success');
  }
}

// ===== STAFF PAYMENT VERIFICATION =====
let currentVerifyingPaymentId = null;

function renderStaffPayments(filter = 'all') {
  const payments = getPayments();
  const container = document.getElementById('staff-payments-body');
  if (!container) return;

  // Calculate stats
  const pending = payments.filter(p => p.status === 'PENDING');
  const paid = payments.filter(p => p.status === 'PAID');
  const rejected = payments.filter(p => p.status === 'REJECTED');

  document.getElementById('staff-stat-pending').textContent = pending.length;
  document.getElementById('staff-stat-verified').textContent = paid.length;
  document.getElementById('staff-stat-collections').textContent = paid.reduce((s, p) => s + p.amount, 0).toLocaleString();
  document.getElementById('staff-stat-rejected').textContent = rejected.length;

  // Show alert if pending
  const alertBox = document.getElementById('staff-payment-alert');
  if (alertBox) alertBox.style.display = pending.length > 0 ? 'flex' : 'none';

  // Filter
  let filtered = payments;
  if (filter !== 'all') {
    filtered = payments.filter(p => p.status === filter);
  }

  container.innerHTML = filtered.map(p => {
    let statusBadge = '';
    let actionsHtml = '';

    if (p.status === 'PENDING') {
      statusBadge = '<span class="badge badge-amber">⏳ PENDING</span>';
      actionsHtml = `
        <button class="btn-ghost btn-sm" onclick="openVerifyModal('${p.id}')">VIEW</button>
        <button class="btn-green btn-sm" onclick="verifyPaymentDirect('${p.id}')">VERIFY</button>
        <button class="btn-red btn-sm" onclick="rejectPaymentDirect('${p.id}')">REJECT</button>
      `;
    } else if (p.status === 'PAID') {
      statusBadge = '<span class="badge badge-green">✅ PAID</span>';
      actionsHtml = '<span style="font-size: 11px; color: var(--green);">Verified ✓</span>';
    } else if (p.status === 'REJECTED') {
      statusBadge = '<span class="badge badge-red">❌ REJECTED</span>';
      actionsHtml = `<span style="font-size: 11px; color: var(--red);" title="${p.reason || ''}">Rejected</span>`;
    } else {
      statusBadge = '<span class="badge badge-gray">UNPAID</span>';
      actionsHtml = '<span style="font-size: 11px; color: var(--muted);">Awaiting payment</span>';
    }

    return `
      <tr>
        <td><strong style="font-size: 12px;">${p.id}</strong></td>
        <td><strong>${p.bookingId}</strong></td>
        <td>${p.client}</td>
        <td><strong>₱${p.amount.toLocaleString()}</strong></td>
        <td>${p.method || '-'}</td>
        <td>${p.proof ? '<span style="color: var(--green);">✓ Uploaded</span>' : '<span style="color: var(--muted);">—</span>'}</td>
        <td>${statusBadge}</td>
        <td>${actionsHtml}</td>
      </tr>
    `;
  }).join('');
}

function filterStaffPayments(status) {
  // Update button styles
  document.querySelectorAll('[id^="filter-"]').forEach(btn => {
    btn.style.background = 'white';
    btn.style.borderColor = 'var(--border)';
  });
  const activeBtn = document.getElementById('filter-' + status.toLowerCase());
  if (activeBtn) {
    activeBtn.style.background = 'var(--pink-tint)';
    activeBtn.style.borderColor = 'var(--pink)';
  }
  renderStaffPayments(status);
}

function openVerifyModal(paymentId) {
  const payments = getPayments();
  const p = payments.find(pay => pay.id === paymentId);
  if (!p) return;

  currentVerifyingPaymentId = paymentId;

  document.getElementById('verify-payment-id').textContent = p.id;
  document.getElementById('verify-booking-id').textContent = p.bookingId;
  document.getElementById('verify-client').textContent = p.client;
  document.getElementById('verify-amount').textContent = p.amount.toLocaleString();
  document.getElementById('verify-method').textContent = p.method || '-';
  document.getElementById('verify-ref').textContent = p.ref || '-';

  // Show/hide proof
  const proofImg = document.getElementById('verify-proof-img');
  const noProofMsg = document.getElementById('no-proof-msg');
  
  if (p.proof) {
    const proofs = JSON.parse(localStorage.getItem('s94_proofs')) || {};
    proofImg.src = proofs[p.id] || 'https://picsum.photos/seed/proof' + p.id + '/400/300';
    proofImg.style.display = 'block';
    noProofMsg.style.display = 'none';
  } else {
    proofImg.style.display = 'none';
    noProofMsg.style.display = 'block';
  }

  // Reset rejection section
  document.getElementById('rejection-section').style.display = 'none';
  document.getElementById('rejection-reason').value = '';
  document.getElementById('reject-btn').textContent = 'Reject';

  document.getElementById('verify-payment-modal').style.display = 'flex';
}

function openProofFullscreen(src) {
  document.getElementById('proof-fullscreen-img').src = src;
  document.getElementById('proof-fullscreen-modal').style.display = 'flex';
}

function toggleRejectSection() {
  const section = document.getElementById('rejection-section');
  const btn = document.getElementById('reject-btn');
  
  if (section.style.display === 'none') {
    section.style.display = 'block';
    btn.textContent = 'Confirm Reject';
    document.getElementById('rejection-reason').focus();
  } else {
    // Confirm rejection
    const reason = document.getElementById('rejection-reason').value.trim();
    if (!reason) {
      showToast('Please enter rejection reason', 'warning');
      return;
    }
    rejectPaymentWithReason(currentVerifyingPaymentId, reason);
    closeModal('verify-payment-modal');
  }
}

function verifyPaymentDirect(paymentId) {
  if (confirm('Verify this payment?')) {
    verifyPaymentNow(paymentId);
  }
}

function rejectPaymentDirect(paymentId) {
  const reason = prompt('Enter rejection reason:');
  if (reason === null) return;
  
  rejectPaymentWithReason(paymentId, reason);
}

function confirmVerifyPayment() {
  if (!currentVerifyingPaymentId) return;
  verifyPaymentNow(currentVerifyingPaymentId);
  closeModal('verify-payment-modal');
}

function verifyPaymentNow(paymentId) {
  const payments = getPayments();
  const index = payments.findIndex(p => p.id === paymentId);
  
  if (index !== -1) {
    payments[index].status = 'PAID';
    payments[index].verifiedAt = new Date().toISOString();
    savePayments(payments);

    // Add to sales
    const sales = getSales();
    sales.push({
      date: new Date().toISOString().split('T')[0],
      amount: payments[index].amount,
      package: payments[index].package,
      bookingId: payments[index].bookingId,
      paymentId: paymentId
    });
    saveSales(sales);

    // Update booking status
    const bookings = getBookings();
    const bIndex = bookings.findIndex(b => b.id === payments[index].bookingId);
    if (bIndex !== -1) {
      bookings[bIndex].status = 'Deposit Paid';
      saveBookings(bookings);
    }

    renderStaffPayments();
    if(typeof renderAdminAnalytics === 'function') renderAdminAnalytics();
    
    // Notifications
    addNotification('Payment Verified', 'Your payment for ' + payments[index].bookingId + ' is verified!', '💳', payments[index].client);
    addNotification('Payment Verified', '₱' + payments[index].amount.toLocaleString() + ' from ' + payments[index].client, '💰', 'admin');
    
    showToast('Payment verified! Added ₱' + payments[index].amount.toLocaleString() + ' to sales.', 'success');
  }
}

function rejectPaymentWithReason(paymentId, reason) {
  const payments = getPayments();
  const index = payments.findIndex(p => p.id === paymentId);
  
  if (index !== -1) {
    payments[index].status = 'REJECTED';
    payments[index].reason = reason;
    payments[index].rejectedAt = new Date().toISOString();
    savePayments(payments);

    renderStaffPayments();
    showToast('Payment rejected: ' + reason, 'error');
  }
}

// ===== ADMIN PAYMENT MANAGEMENT =====
function filterAdminPayments(status) {
  // Update button styles
  document.querySelectorAll('[id^="admin-filter-"]').forEach(btn => {
    btn.style.background = 'white';
    btn.style.borderColor = 'var(--border)';
  });
  const activeBtn = document.getElementById('admin-filter-' + status.toLowerCase());
  if (activeBtn) {
    activeBtn.style.background = 'var(--pink-tint)';
    activeBtn.style.borderColor = 'var(--pink)';
  }
  renderAdminPayments(status);
}

function renderAdminPayments(filter = 'all') {
  const payments = getPayments();
  const tbody = document.getElementById('admin-payments-body');
  if (!tbody) return;

  const totalRevenue = payments.filter(p => p.status === 'PAID').reduce((s, p) => s + p.amount, 0);
  const pendingCount = payments.filter(p => p.status === 'PENDING').length;
  const totalCollected = payments.filter(p => p.status === 'PAID' && p.type === 'DEPOSIT').reduce((s, p) => s + p.amount, 0);
  const totalUnpaid = payments.filter(p => p.status === 'UNPAID').reduce((s, p) => s + p.amount, 0);

  // Update Stats in Admin Payment Page
  const elRev = document.getElementById('admin-total-revenue');
  const elPend = document.getElementById('admin-pending-count');
  const elColl = document.getElementById('admin-collected');
  const elUnp = document.getElementById('admin-unpaid');

  if(elRev) elRev.textContent = totalRevenue.toLocaleString();
  if(elPend) elPend.textContent = pendingCount;
  if(elColl) elColl.textContent = totalCollected.toLocaleString();
  if(elUnp) elUnp.textContent = totalUnpaid.toLocaleString();

  let filtered = payments;
  if (filter !== 'all') filtered = payments.filter(p => p.status === filter);

  tbody.innerHTML = filtered.map(p => {
    const statusClass = p.status === 'PAID' ? 'badge-green' : p.status === 'PENDING' ? 'badge-amber' : 'badge-gray';
    return `
      <tr>
        <td><strong>${p.id}</strong></td>
        <td>${p.bookingId}</td>
        <td>${p.client}</td>
        <td>₱${p.amount.toLocaleString()}</td>
        <td>${p.type}</td>
        <td>${p.method || '—'}</td>
        <td><span class="badge ${statusClass}">${p.status}</span></td>
        <td>${p.date || '—'}</td>
      </tr>
    `;
  }).join('');
}

// ===== INVENTORY MANAGEMENT =====
function renderInventory() {
  const inventory = getInventory();
  const container = document.getElementById('inventory-body');
  if (!container) return;

  container.innerHTML = inventory.map(item => {
    const isLow = item.quantity <= item.threshold;
    const status = item.quantity === 0 ? 'Out of Stock' : (isLow ? 'Low Stock' : 'In Stock');
    
    return `
      <tr style="${isLow ? 'background:var(--red-bg);' : ''}">
        <td><strong>${item.name}</strong></td>
        <td>${item.category}</td>
        <td>${item.quantity}</td>
        <td><span class="badge ${item.quantity === 0 ? 'badge-red' : isLow ? 'badge-amber' : 'badge-green'}">${status}</span></td>
        <td>
          <button class="btn-ghost btn-sm" onclick="updateInventory(${item.id}, 1)">+</button>
          <button class="btn-ghost btn-sm" onclick="updateInventory(${item.id}, -1)">-</button>
        </td>
      </tr>
    `;
  }).join('');
}

function updateInventory(id, change) {
  const inventory = getInventory();
  const index = inventory.findIndex(i => i.id === id);
  
  if (index !== -1) {
    inventory[index].quantity = Math.max(0, inventory[index].quantity + change);
    saveInventory(inventory);
    renderInventory();
    showToast('Inventory updated', 'success');
  }
}

function addInventoryItem() {
  const name = document.getElementById('new-item-name').value;
  const category = document.getElementById('new-item-category').value;
  const quantity = parseInt(document.getElementById('new-item-qty').value);

  if (!name || !quantity) {
    showToast('Please fill all fields', 'warning');
    return;
  }

  const inventory = getInventory();
  inventory.push({
    id: Date.now(),
    name,
    category,
    quantity,
    threshold: 1
  });
  saveInventory(inventory);
  renderInventory();
  
  document.getElementById('new-item-name').value = '';
  document.getElementById('new-item-qty').value = '';
  showToast('Item added!', 'success');
}

// ===== ADMIN ANALYTICS & DASHBOARD =====
function renderAdminAnalytics() {
  const bookings = getBookings();
  const sales = getSales();
  const inventory = getInventory();
  
  // 1. Stats Cards
  const elTotalBookings = document.getElementById('stat-admin-total-bookings');
  const elTotalRevenue = document.getElementById('stat-admin-total-revenue');
  const elPendingApprovals = document.getElementById('stat-admin-pending-approvals');
  const elLowStock = document.getElementById('stat-admin-low-stock');

  if (elTotalBookings) elTotalBookings.textContent = bookings.length;
  if (elTotalRevenue) elTotalRevenue.textContent = '₱' + sales.reduce((sum, s) => sum + s.amount, 0).toLocaleString();
  if (elPendingApprovals) elPendingApprovals.textContent = bookings.filter(b => b.status === 'Awaiting Approval').length;
  if (elLowStock) elLowStock.textContent = inventory.filter(i => i.quantity <= i.threshold).length;

  // 2. Monthly Revenue Chart (Last 6 Months)
  const chartContainer = document.getElementById('admin-revenue-chart');
  if (chartContainer) {
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const now = new Date();
    let chartHtml = '';
    
    // Get last 6 months
    const last6 = [];
    for(let i=5; i>=0; i--) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      last6.push({ 
        month: d.getMonth(), 
        year: d.getFullYear(), 
        label: monthNames[d.getMonth()] 
      });
    }

    const maxMonthly = 50000; // Reference for 100% height
    
    chartHtml = last6.map(m => {
      const monthSales = sales.filter(s => {
        const sd = new Date(s.date);
        return sd.getMonth() === m.month && sd.getFullYear() === m.year;
      }).reduce((sum, s) => sum + s.amount, 0);
      
      const height = Math.min(100, (monthSales / maxMonthly) * 100);
      const isCurrent = m.month === now.getMonth() && m.year === now.getFullYear();
      
      return `
        <div class="chart-bar" style="height:${Math.max(10, height)}%; ${isCurrent ? 'background:linear-gradient(180deg,var(--dark) 0%,#4A4A4A 100%);' : ''}">
          <span class="bar-val" style="${isCurrent ? 'color:var(--dark);' : ''}">₱${(monthSales/1000).toFixed(1)}K</span>
          <span class="bar-label">${m.label}</span>
        </div>
      `;
    }).join('');
    
    chartContainer.innerHTML = chartHtml;
  }

  // 3. Package Distribution
  const pkgList = document.getElementById('admin-package-list');
  if (pkgList) {
    const counts = {};
    bookings.forEach(b => {
      counts[b.package] = (counts[b.package] || 0) + 1;
    });
    
    pkgList.innerHTML = Object.entries(counts).sort((a,b) => b[1] - a[1]).map(([name, count]) => `
      <div style="display:flex;justify-content:space-between;">
        <span>${name}</span>
        <strong>${count} bookings</strong>
      </div>
    `).join('');
  }
}

function renderAdminBookings() {
  const tbody = document.getElementById('admin-bookings-body');
  if (!tbody) return;
  const bookings = getBookings();
  
  if (bookings.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px;">No bookings found.</td></tr>';
    return;
  }
  
  tbody.innerHTML = bookings.map(b => {
    const badgeClass = (b.status === 'Completed' || b.status === 'Deposit Paid') ? 'badge-green' : (b.status === 'Awaiting Approval' || b.status === 'Approved (Unpaid)') ? 'badge-amber' : 'badge-gray';
    return `
      <tr>
        <td><strong>${b.customer}</strong></td>
        <td>${b.package}</td>
        <td>${b.date}</td>
        <td>₱${(packageData.find(p => p.name === b.package)?.price || 0).toLocaleString()}</td>
        <td><span class="badge ${badgeClass}">${b.status}</span></td>
      </tr>
    `;
  }).join('');
}

// ===== FEEDBACK SYSTEM =====
function submitFeedback() {
  const comment = document.getElementById('feedback-comment').value;
  const overall = document.querySelector('input[name="rate-overall"]:checked')?.value || 0;

  if (!overall) {
    showToast('Please select a rating', 'warning');
    return;
  }
  
  if (!comment.trim()) {
    showToast('Please enter a comment', 'warning');
    return;
  }

  // Sentiment analysis
  const posWords = ['ganda', 'satisfied', 'perfect', 'magaling', 'sulit', 'maayos', 'okay'];
  const negWords = ['bagal', 'pangit', 'mahal', 'nawala', 'delay', 'problema', 'tagal'];
  const text = comment.toLowerCase();
  
  let score = 0;
  posWords.forEach(w => { if (text.includes(w)) score++; });
  negWords.forEach(w => { if (text.includes(w)) score--; });
  
  const sentiment = score > 0 ? 'positive' : score < 0 ? 'negative' : 'neutral';
  const urgent = parseInt(overall) <= 2 && sentiment === 'negative';

  // Topic detection
  const topics = [];
  if (text.includes('photographer') || text.includes('kuha')) topics.push('photographer');
  if (text.includes('delivery') || text.includes('tagal')) topics.push('delivery');
  if (text.includes('price') || text.includes('mahal')) topics.push('pricing');
  if (text.includes('backdrop')) topics.push('backdrop');
  if (topics.length === 0) topics.push('other');

  const feedbacks = getFeedbacks();
  feedbacks.unshift({
    id: Date.now(),
    date: new Date().toISOString().split('T')[0],
    client: 'Current User',
    comment,
    ratings: { overall: parseInt(overall) },
    sentiment,
    topics,
    urgent
  });
  saveFeedbacks(feedbacks);
  
  showToast('Thank you for your feedback!', 'success');
  document.getElementById('feedback-comment').value = '';
  document.querySelectorAll('input[name="rate-overall"]').forEach(r => r.checked = false);
  
  // Update the recent reviews display
  renderClientFeedbackHistory();
}

// ===== CLIENT FEEDBACK HISTORY =====
function renderClientFeedbackHistory() {
  const container = document.getElementById('client-feedback-history');
  if (!container) return;
  
  const feedbacks = getFeedbacks().filter(f => f.client === 'Current User');
  
  if (feedbacks.length === 0) {
    container.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:30px;">No reviews yet. Submit your first feedback above.</td></tr>';
    return;
  }
  
  container.innerHTML = feedbacks.slice(0, 5).map(f => {
    const stars = '★'.repeat(f.ratings.overall) + '☆'.repeat(5 - f.ratings.overall);
    
    return `
      <tr>
        <td><span style="color:#B8860B;font-size:14px;">${stars}</span></td>
        <td>${f.comment}</td>
        <td style="font-size:12px;color:var(--muted);">${f.date}</td>
      </tr>
    `;
  }).join('');
}

function renderFeedbackAnalytics() {
  const feedbacks = getFeedbacks();
  const container = document.getElementById('feedback-analytics-body');
  if (!container) return;

  container.innerHTML = feedbacks.map(f => `
    <tr>
      <td>${'★'.repeat(f.ratings.overall)}${'☆'.repeat(5 - f.ratings.overall)}</td>
      <td style="max-width:300px;">${f.comment}</td>
      <td><span class="badge ${f.sentiment === 'positive' ? 'badge-green' : f.sentiment === 'negative' ? 'badge-red' : 'badge-gray'}">${f.sentiment}</span></td>
      <td>${f.topics.map(t => `<span class="badge badge-blue" style="margin-right:4px;">${t}</span>`).join('')}</td>
      <td>${f.urgent ? '<span class="badge badge-red">⚠️ URGENT</span>' : '—'}</td>
    </tr>
  `).join('');
}

// ===== LOYALTY SYSTEM =====
const LOYALTY_RULES = [
  { id: 'R01', milestone: 1, label: '01', title: '+5 MINUTES', short: '+5 MIN', icon: '⏱️' },
  { id: 'R03', milestone: 2, label: '03', title: '+1 PRINT OUT', short: '+1 PRINT', icon: '🖼️' },
  { id: 'R05', milestone: 3, label: '05', title: '+1 BACKDROP', short: '+1 BACKDROP', icon: '🎨' },
  { id: 'R06', milestone: 4, label: '06', title: '50% OFF', short: '50% OFF', icon: '🔥' }
];

function getLoyaltyStatus(clientName) {
  const loyalty = getLoyalty();
  const entry = loyalty.find(l => l.client === clientName) || { client: clientName, bookings: 0, rewardsUsed: [] };
  
  // Count actual completed bookings
  const completed = getBookings().filter(b => b.customer === clientName && b.status === 'Completed').length;
  const totalCompleted = Math.max(entry.bookings, completed);
  
  const earnedRewards = LOYALTY_RULES.filter(r => totalCompleted >= r.milestone);
  const nextReward = LOYALTY_RULES.find(r => totalCompleted < r.milestone);

  return {
    count: totalCompleted,
    earned: earnedRewards,
    next: nextReward,
    used: entry.rewardsUsed || [],
    rules: LOYALTY_RULES
  };
}

function renderLoyaltyCard() {
  const currentUser = JSON.parse(localStorage.getItem('s94_current_user'));
  if (!currentUser) return;

  const status = getLoyaltyStatus(currentUser.name);
  
  // Update counts in dashboard
  const bVal = document.getElementById('loyalty-bookings-val');
  if (bVal) bVal.textContent = status.count;
  
  const bBadge = document.getElementById('loyalty-count-badge');
  if (bBadge) bBadge.textContent = status.count + ' COMPLETED BOOKING' + (status.count === 1 ? '' : 'S');

  // Render cards
  const grid = document.getElementById('loyalty-cards-grid');
  if (grid) {
    grid.innerHTML = LOYALTY_RULES.map(reward => {
      const isEarned = status.count >= reward.milestone;
      return `
        <div style="background: rgba(255,255,255,${isEarned ? '0.15' : '0.05'}); border: 1px solid rgba(255,255,255,${isEarned ? '0.3' : '0.1'}); border-radius: 12px; padding: 12px; text-align: center; position: relative; transition: 0.3s;">
          <div style="font-size: 10px; opacity: 0.6; letter-spacing: 1px;">REWARD ${reward.label}</div>
          <div style="font-size: 20px; margin: 8px 0;">${reward.icon}</div>
          <div style="font-size: 9px; font-weight: 600; text-transform: uppercase;">${reward.short}</div>
          <div style="position: absolute; top: -5px; right: -5px; background: ${isEarned ? 'var(--green)' : 'var(--amber)'}; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
            ${isEarned ? '✅' : '⏳'}
          </div>
        </div>
      `;
    }).join('');
  }

  // Next reward hint
  const hintVal = document.getElementById('loyalty-next-reward-val');
  if (hintVal) {
    if (status.next) {
      const remaining = status.next.milestone - status.count;
      hintVal.textContent = `${status.next.title} after ${remaining} more booking${remaining > 1 ? 's' : ''}`;
    } else {
      hintVal.textContent = "All rewards earned! 🏆";
    }
  }
}

function renderAdminLoyalty() {
  const container = document.getElementById('lc-table-body');
  if (!container) return;

  const users = getUsers().filter(u => u.role === 'client');
  const search = (document.getElementById('lc-search')?.value || '').toLowerCase();
  const filter = document.getElementById('lc-tier-filter')?.value || 'all';

  let filtered = users.map(u => {
    const status = getLoyaltyStatus(u.name);
    return { ...u, status };
  });

  if (search) filtered = filtered.filter(u => u.name.toLowerCase().includes(search));
  if (filter !== 'all') {
      const threshold = parseInt(filter);
      filtered = filtered.filter(u => u.status.count >= threshold);
  }

  // Update Admin Stats
  const statTotal = document.getElementById('lc-stat-total');
  const statLvl1 = document.getElementById('lc-stat-lvl1');
  const statLvl2 = document.getElementById('lc-stat-lvl2');
  const statTop = document.getElementById('lc-stat-top');

  if (statTotal) statTotal.textContent = filtered.length;
  if (statLvl1) statLvl1.textContent = filtered.filter(u => u.status.count >= 1).length;
  if (statLvl2) statLvl2.textContent = filtered.filter(u => u.status.count >= 2).length;
  if (statTop) statTop.textContent = filtered.filter(u => u.status.count >= 4).length;

  container.innerHTML = filtered.map(u => {
    const s = u.status;
    const earnedLabels = s.earned.map(e => e.label).join(', ');
    const statusBadge = s.count >= 4 ? '<span class="badge badge-green">COMPLETED</span>' : '<span class="badge badge-amber">IN PROGRESS</span>';
    
    return `
      <tr>
        <td><strong>${u.name}</strong><br><span style="font-size:11px;color:var(--muted);">${u.email}</span></td>
        <td><strong>${s.count}</strong></td>
        <td style="letter-spacing: 1px; font-weight: 600; color: var(--dark);">${earnedLabels || '—'}</td>
        <td>${statusBadge}</td>
      </tr>
    `;
  }).join('');
}


// ===== TOAST NOTIFICATIONS =====
function showToast(message, type = 'success') {
  const existing = document.getElementById('toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.id = 'toast';
  const colors = { success: '#2ECC71', error: '#E74C3C', warning: '#F59E0B' };
  toast.style.cssText = `
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    background: ${colors[type] || colors.success}; color: white;
    padding: 14px 20px; border-radius: 10px; font-size: 14px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
  `;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

// ===== MODAL HELPERS =====
function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.style.display = 'none';
}

function showCancellationPolicy() {
  document.getElementById('cancellation-policy-modal').style.display = 'flex';
}

// ===== PHOTO PREVIEW =====
function previewPhotos(event) {
  const files = Array.from(event.target.files);
  const preview = document.getElementById('photo-preview');
  if (!preview) return;
  preview.style.display = 'grid';
  preview.innerHTML = '';
  
  files.forEach(file => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const div = document.createElement('div');
      div.className = 'photo-thumb';
      div.innerHTML = `<img src="${e.target.result}"/><button class="remove-btn" onclick="this.parentElement.remove()">✕</button>`;
      preview.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
}

// ===== FILE SELECT FOR PAYMENT PROOF =====
function handleFileSelect(input) {
  const file = input.files[0];
  if (!file) return;
  
  const preview = document.getElementById('pay-file-preview');
  const previewImg = document.getElementById('pay-preview-img');
  
  if (preview && previewImg) {
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImg.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
}


// ===== CANCEL BOOKING =====
function confirmCancel() {
  document.getElementById('cancel-modal').style.display = 'flex';
}

function closeModal(modalId) {
  if (typeof modalId === 'string') {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
  } else {
    // Legacy support for non-string modal IDs
    const cancelModal = document.getElementById('cancel-modal');
    if (cancelModal) cancelModal.style.display = 'none';
  }
}

// ===== LIGHTBOX =====
function openLightbox(url) {
  const modal = document.getElementById('lightbox-modal');
  const img = document.getElementById('lightbox-img');
  if (modal && img) {
    img.src = url;
    modal.style.display = 'flex';
  }
}

function closeLightbox() {
  const modal = document.getElementById('lightbox-modal');
  if (modal) modal.style.display = 'none';
}

// ===== CONTENT RENDERING: PHOTOS, NOTIFICATIONS, PROFILE =====
function renderClientPhotos() {
  const container = document.getElementById('client-photos-body');
  if (!container) return;
  const photos = JSON.parse(localStorage.getItem('s94_photos')) || [];
  const userPhotos = photos.filter(p => p.client === 'Current User');
  
  if (userPhotos.length === 0) {
    container.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px;">No photos available yet.</td></tr>';
    return;
  }
  
  container.innerHTML = userPhotos.map(p => {
    const thumbs = p.images.map(url => `<div style="width: 50px; height: 50px; border-radius: var(--radius-sm); background: url('${url}') center/cover; display: inline-block; cursor: pointer; transition: 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1" onclick="openLightbox('${url}')"></div>`).join('');
    
    return `
    <tr>
      <td>
        <strong>${p.package}</strong>
        <div style="display: flex; gap: 8px; margin-top: 12px;">${thumbs}</div>
      </td>
      <td>${p.uploadedAt}</td>
      <td>${p.images.length} photos</td>
      <td><span class="badge ${p.status === 'Ready' ? 'badge-green' : 'badge-amber'}">${p.status}</span></td>
      <td><button class="btn-ghost btn-sm" onclick="showToast('Downloading photos...', 'success')" ${p.status !== 'Ready' ? 'disabled' : ''}>⬇ Download</button></td>
    </tr>
  `}).join('');
}

function renderNotifications() {
  const container = document.getElementById('client-notifications-body');
  if (!container) return;
  const notifs = getNotifications().filter(n => n.client === 'Current User');
  
  if (notifs.length === 0) {
    container.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:30px;">No notifications.</td></tr>';
    return;
  }
  
  container.innerHTML = notifs.map(n => `
    <tr style="background:var(--bg-soft);">
      <td><span style="font-size:18px;">${n.icon || '🔔'}</span></td>
      <td><strong>${n.title}</strong><br><span style="font-size:12px;color:var(--muted);">${n.message}</span></td>
      <td style="font-size:12px;color:var(--muted);white-space:nowrap;">${n.time}</td>
    </tr>
  `).join('');
}

function renderProfile() {
  const fnameEl = document.getElementById('profile-fname');
  const lnameEl = document.getElementById('profile-lname');
  const emailEl = document.getElementById('profile-email');
  
  if (fnameEl && lnameEl && emailEl) {
    const userStr = localStorage.getItem('s94_current_user');
    if (userStr) {
      const user = JSON.parse(userStr);
      const nameParts = user.name ? user.name.split(' ') : ['First', 'Last'];
      fnameEl.value = nameParts[0] || '';
      lnameEl.value = nameParts.slice(1).join(' ') || '';
      emailEl.value = user.email || '';
      
      const profileNameLg = document.getElementById('profile-name-lg');
      if (profileNameLg) profileNameLg.textContent = user.name;
    }
  }
}


function saveProfile() {
  const fname = document.getElementById('profile-fname').value;
  const lname = document.getElementById('profile-lname').value;
  const email = document.getElementById('profile-email').value;
  const phone = document.getElementById('profile-phone').value;
  
  let user = JSON.parse(localStorage.getItem('s94_current_user')) || {};
  user.name = `${fname} ${lname}`; 
  user.email = email; 
  user.phone = phone;
  localStorage.setItem('s94_current_user', JSON.stringify(user));
  
  const profileNameLg = document.getElementById('profile-name-lg');
  if (profileNameLg) profileNameLg.textContent = user.name;
  showToast('Profile updated successfully', 'success');
}

function updatePassword() {
  showToast('Password updated securely', 'success');
}


function confirmVerifyPayment() {
  const pId = activeVerifyPaymentId;
  const payments = getPayments();
  const p = payments.find(x=>x.id === pId);
  if(p) {
    p.status = 'PAID';
    savePayments(payments);
    
    const bookings = getBookings();
    const b = bookings.find(x=>x.id === p.bookingId);
    if(b && b.status === 'Awaiting Approval') {
      b.status = 'Deposit Paid';
      saveBookings(bookings);
    }
    
    if (typeof showToast === 'function') showToast('Payment successfully verified!', 'success');
    closeModal('verify-payment-modal');
    renderStaffPayments();
    if (typeof renderStaffBookings === 'function') renderStaffBookings();
  }
}

function openProofFullscreen(src) {
  document.getElementById('proof-fullscreen-img').src = src;
  document.getElementById('proof-fullscreen-modal').style.display = 'flex';
}

// ===== AI CHATBOT ENGINE =====
function toggleChatbot() {
  const widget = document.getElementById('chatbot-widget');
  if (widget) {
    if (widget.style.display === 'none' || widget.style.display === '') {
      widget.style.display = 'flex';
      setTimeout(() => {
        const input = document.getElementById('chat-input');
        if (input) input.focus();
      }, 100);
    } else {
      widget.style.display = 'none';
    }
  }
}

function sendChatMsg() {
  const inputEl = document.getElementById('chat-input');
  const msgContainer = document.getElementById('chat-messages');
  if(!inputEl || !msgContainer) return;
  
  const text = inputEl.value.trim();
  if(!text) return;
  
  // Create user bubble
  const userDiv = document.createElement('div');
  userDiv.className = 'chat-msg user';
  userDiv.textContent = text;
  msgContainer.appendChild(userDiv);
  
  inputEl.value = '';
  msgContainer.scrollTop = msgContainer.scrollHeight;
  
  // Show typing indicator
  const typingDiv = document.createElement('div');
  typingDiv.className = 'chat-msg bot';
  typingDiv.innerHTML = '<em style="color:var(--muted);font-size:12px;">typing...</em>';
  typingDiv.id = 'typing-indicator';
  msgContainer.appendChild(typingDiv);
  msgContainer.scrollTop = msgContainer.scrollHeight;
  
  // Simulate network delay before bot replies
  setTimeout(() => {
    // Remove typing indicator
    const typing = document.getElementById('typing-indicator');
    if (typing) typing.remove();
    
    const botDiv = document.createElement('div');
    botDiv.className = 'chat-msg bot';
    botDiv.innerHTML = getBotResponse(text);
    msgContainer.appendChild(botDiv);
    msgContainer.scrollTop = msgContainer.scrollHeight;
  }, 650);
}

function getBotResponse(input) {
  const q = input.toLowerCase();
  
  // Greetings
  if (q.includes('hello') || q.includes('hi') || q.includes('hey') || q.includes('kumusta') || q.includes('musta')) {
    return 'Hello! 👋 Welcome to Studio 94. How can I help you today? You can ask about our <strong>packages, prices, location, hours,</strong> or <strong>how to book</strong>!';
  }
  
  // Pricing & Packages
  if (q.includes('price') || q.includes('rate') || q.includes('how much') || q.includes('cost') || q.includes('magkano') || q.includes('package')) {
    return '📸 Our photography packages:<br><br>• <strong>Self-Shoot</strong> — ₱1,500 (2 hrs)<br>• <strong>Studio Rental</strong> — ₱2,500 (1 hr)<br>• <strong>Family</strong> — ₱3,500 (3 hrs)<br>• <strong>Creative</strong> — ₱5,000 (4 hrs)<br><br>All packages include professional lighting and studio access! A <strong>50% deposit</strong> is required to confirm your booking.';
  }
  
  // Location
  if (q.includes('where') || q.includes('location') || q.includes('address') || q.includes('saan') || q.includes('map')) {
    return '📍 We are located at <strong>Studio 12, Creative District, Metro Manila</strong>. We have ample parking space and are easily accessible via public transport!';
  }
  
  // Operating Hours
  if (q.includes('time') || q.includes('hour') || q.includes('open') || q.includes('schedule') || q.includes('oras') || q.includes('bukas')) {
    return '🕐 Our studio is open <strong>Monday to Saturday, 9:00 AM – 7:00 PM</strong>. We are closed on Sundays and holidays.';
  }
  
  // Booking process
  if (q.includes('book') || q.includes('reserve') || q.includes('appointment')) {
    return '📅 To book a session:<br>1️⃣ Go to <strong>Book Session</strong> in the sidebar<br>2️⃣ Select your preferred package<br>3️⃣ Pick a date and time from the calendar<br>4️⃣ Fill in your details and confirm!<br><br>You\'ll receive approval within 24 hours.';
  }
  
  // Payment & Deposit
  if (q.includes('pay') || q.includes('deposit') || q.includes('gcash') || q.includes('maya') || q.includes('bank') || q.includes('bayad')) {
    return '💳 We accept payments via:<br>• <strong>Maya (Maribank)</strong><br>• <strong>GCash</strong><br>• <strong>Bank Transfer (BDO)</strong><br><br>Go to <strong>My Payments</strong> and click <strong>PAY NOW</strong> to see our account details and QR code. Just enter your reference number after sending!';
  }
  
  // Cancellation & Refund
  if (q.includes('refund') || q.includes('cancel') || q.includes('rebook')) {
    return '📋 Our cancellation policy:<br>• <strong>7+ days</strong> before shoot → 100% refund<br>• <strong>3-6 days</strong> before shoot → 50% refund<br>• <strong>1-2 days</strong> before shoot → No refund<br><br>You can view the full policy under <strong>My Payments</strong>.';
  }
  
  // Photos
  if (q.includes('photo') || q.includes('picture') || q.includes('download') || q.includes('gallery') || q.includes('litrato')) {
    return '🖼️ Your edited photos will be uploaded to <strong>My Photos</strong> within 3-5 business days after your session. You\'ll receive a notification when they\'re ready to download!';
  }
  
  // Thank you
  if (q.includes('thank') || q.includes('salamat') || q.includes('thanks')) {
    return 'You\'re welcome! 😊 If you need anything else, don\'t hesitate to ask. We\'re here to help!';
  }
  
  // Bye
  if (q.includes('bye') || q.includes('paalam') || q.includes('goodbye')) {
    return 'Goodbye! 👋 Thank you for choosing Studio 94. See you at your next session! 📸';
  }
  
  // Contact
  if (q.includes('contact') || q.includes('email') || q.includes('phone') || q.includes('number')) {
    return '📞 You can reach us at:<br>• Email: <strong>studio94@email.com</strong><br>• Phone: <strong>0917 123 4567</strong><br>• Or message us through this chat anytime!';
  }
  
  // Default fallback
  return "🤖 I didn't quite catch that! Here are things I can help with:<br><br>• 💰 <strong>Prices & packages</strong><br>• 📍 <strong>Location & directions</strong><br>• 🕐 <strong>Operating hours</strong><br>• 📅 <strong>How to book</strong><br>• 💳 <strong>Payment methods</strong><br>• 📋 <strong>Cancellation policy</strong><br>• 🖼️ <strong>Photo delivery</strong><br><br>Just type a keyword and I'll help you out!";
}

function initDraggableChatbot() {
  const header = document.getElementById('chat-header');
  const widget = document.getElementById('chatbot-widget');
  if(!header || !widget) return;
  
  let isDragging = false;
  let currentX, currentY, initialX, initialY;
  let xOffset = 0, yOffset = 0;

  header.addEventListener('mousedown', dragStart);
  document.addEventListener('mousemove', drag);
  document.addEventListener('mouseup', dragEnd);

  function dragStart(e) {
    if(e.target.tagName === 'BUTTON') return; 
    initialX = e.clientX - xOffset;
    initialY = e.clientY - yOffset;
    isDragging = true;
    header.style.cursor = 'grabbing';
  }

  function drag(e) {
    if (isDragging) {
      e.preventDefault();
      currentX = e.clientX - initialX;
      currentY = e.clientY - initialY;
      xOffset = currentX;
      yOffset = currentY;
      widget.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;
    }
  }

  function dragEnd(e) {
    initialX = currentX;
    initialY = currentY;
    isDragging = false;
    header.style.cursor = 'grab';
  }
}

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => {
  if (typeof renderInventory === 'function') renderInventory();
  if (typeof renderLoyaltyCard === 'function') renderLoyaltyCard();
  if (typeof initDraggableChatbot === 'function') initDraggableChatbot();
  
  if (document.getElementById('stat-admin-total-bookings') && typeof renderAdminAnalytics === 'function') renderAdminAnalytics();
  if (document.getElementById('admin-bookings-body') && typeof renderAdminBookings === 'function') renderAdminBookings();
  if (document.getElementById('lc-table-body') && typeof renderAdminLoyalty === 'function') renderAdminLoyalty();
  
  // Page-specific renders
  if (document.getElementById('payments-list') && typeof renderClientPayments === 'function') renderClientPayments();
  if (document.getElementById('client-bookings-body') && typeof renderClientBookings === 'function') renderClientBookings();
  if (document.getElementById('staff-payments-body') && typeof renderStaffPayments === 'function') renderStaffPayments();
  if (document.getElementById('staff-bookings-body') && typeof renderStaffBookings === 'function') renderStaffBookings();
  if (document.getElementById('feedback-analytics-body') && typeof renderFeedbackAnalytics === 'function') renderFeedbackAnalytics();
  if (document.getElementById('calendar-grid') && typeof renderCalendar === 'function') renderCalendar();
  if (document.getElementById('client-feedback-history') && typeof renderClientFeedbackHistory === 'function') renderClientFeedbackHistory();
  if (document.getElementById('client-photos-body') && typeof renderClientPhotos === 'function') renderClientPhotos();
  if (document.getElementById('client-notifications-body') && typeof renderNotifications === 'function') renderNotifications();
  if (document.getElementById('profile-fname') && typeof renderProfile === 'function') renderProfile();
  
  // Check for urgent feedback
  const urgentList = document.getElementById('urgent-feedback-list');
  if (urgentList) {
    const urgentFeedbacks = getFeedbacks().filter(f => f.urgent);
    if (urgentFeedbacks.length === 0) {
      const sec = document.getElementById('urgent-section');
      if (sec) sec.style.display = 'none';
    } else {
      urgentList.innerHTML = urgentFeedbacks.map(f => `
        <div style="padding: 12px; border-left: 3px solid var(--red); background: white; border-radius: 0 8px 8px 0; margin-bottom: 10px;">
          <div style="font-weight: 600; color: var(--red);">${f.client} - ${'★'.repeat(f.ratings.overall)}${'☆'.repeat(5 - f.ratings.overall)}</div>
          <div style="font-size: 13px; margin-top: 4px;">"${f.comment}"</div>
          <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">${f.date}</div>
        </div>
      `).join('');
    }
  }
});
