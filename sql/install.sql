-- ============================================================
-- STUDIO 94 SNAPTRACK — COMPLETE DATABASE
-- Lahat ng tables, columns, foreign keys, at sample data
-- ============================================================

-- Drop database kung may luma (optional, gamitin kung gusto mong magsimula sa simula)
-- DROP DATABASE IF EXISTS snaptrack;

-- CREATE DATABASE IF NOT EXISTS snaptrack
--   CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;

-- USE snaptrack;

-- ============================================================
-- 1. USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120)  NOT NULL,
    email       VARCHAR(180)  NOT NULL UNIQUE,
    phone       VARCHAR(30)   DEFAULT '',
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('admin','staff','client') NOT NULL DEFAULT 'client',
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    avatar      VARCHAR(255)  DEFAULT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. PACKAGES TABLE (with parent_id, is_popular, max_pax, features)
-- ============================================================
CREATE TABLE IF NOT EXISTS packages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT UNSIGNED  DEFAULT NULL,
    name        VARCHAR(80)   NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    duration    VARCHAR(50)   DEFAULT '',
    description TEXT          DEFAULT NULL,
    features    JSON          DEFAULT NULL,
    color       VARCHAR(30)   DEFAULT '#B0C4DE',
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    is_popular  TINYINT(1)    NOT NULL DEFAULT 0,
    max_pax     INT           DEFAULT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES packages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. BOOKINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS bookings (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_ref      VARCHAR(20)   NOT NULL UNIQUE,
    user_id          INT UNSIGNED  NOT NULL,
    package_id       INT UNSIGNED  NOT NULL,
    date             DATE          NOT NULL,
    time             VARCHAR(20)   NOT NULL,
    people           INT           NOT NULL DEFAULT 1,
    phone            VARCHAR(30)   DEFAULT '',
    notes            TEXT          DEFAULT NULL,
    type             ENUM('online','walk-in') NOT NULL DEFAULT 'online',
    status           ENUM('Awaiting Approval','Approved (Unpaid)','Deposit Paid','Completed','Cancelled') NOT NULL DEFAULT 'Awaiting Approval',
    package_price    DECIMAL(10,2) DEFAULT 0,
    deposit_amount   DECIMAL(10,2) DEFAULT 0,
    deposit_paid     BOOLEAN       DEFAULT FALSE,
    remaining_balance DECIMAL(10,2) DEFAULT 0,
    fully_paid       BOOLEAN       DEFAULT FALSE,
    loyalty_reward   VARCHAR(30)   DEFAULT NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. PAYMENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_ref     VARCHAR(20)   NOT NULL UNIQUE,
    booking_id      INT UNSIGNED  NOT NULL,
    user_id         INT UNSIGNED  NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    type            ENUM('DEPOSIT','FULL') NOT NULL DEFAULT 'DEPOSIT',
    method          VARCHAR(50)   DEFAULT NULL,
    ref_number      VARCHAR(100)  DEFAULT NULL,
    proof_image     VARCHAR(255)  DEFAULT NULL,
    status          ENUM('UNPAID','PENDING','PAID','REJECTED') NOT NULL DEFAULT 'UNPAID',
    rejection_reason VARCHAR(255) DEFAULT NULL,
    verified_by     INT UNSIGNED  DEFAULT NULL,
    verified_at     DATETIME      DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. INVENTORY TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS inventory (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120)  NOT NULL,
    category    ENUM('Backdrop','Equipment','Lighting','Props','Other') NOT NULL DEFAULT 'Equipment',
    quantity    INT           NOT NULL DEFAULT 0,
    threshold   INT           NOT NULL DEFAULT 1,
    notes       TEXT          DEFAULT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. FEEDBACK TABLE (with topics column)
-- ============================================================
CREATE TABLE IF NOT EXISTS feedback (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED  NOT NULL,
    booking_id          INT UNSIGNED  DEFAULT NULL,
    rating_overall      TINYINT       NOT NULL DEFAULT 5,
    rating_staff        TINYINT       DEFAULT NULL,
    rating_quality      TINYINT       DEFAULT NULL,
    rating_timeliness   TINYINT       DEFAULT NULL,
    comment             TEXT          DEFAULT NULL,
    sentiment           ENUM('positive','neutral','negative') NOT NULL DEFAULT 'positive',
    topics              VARCHAR(255)  DEFAULT NULL,
    is_urgent           TINYINT(1)    NOT NULL DEFAULT 0,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. PHOTOS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS photos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT UNSIGNED  NOT NULL,
    uploader_id INT UNSIGNED  NOT NULL,
    filename    VARCHAR(255)  NOT NULL,
    filepath    VARCHAR(500)  NOT NULL,
    status      ENUM('Pending','Processing','Ready','Sent') NOT NULL DEFAULT 'Pending',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (uploader_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. NOTIFICATIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    type        VARCHAR(30)   NOT NULL DEFAULT 'system',
    title       VARCHAR(200)  NOT NULL,
    message     TEXT          DEFAULT NULL,
    icon        VARCHAR(10)   DEFAULT '🔔',
    is_read     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. LOYALTY CARDS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS loyalty_cards (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL UNIQUE,
    card_number     VARCHAR(20)   NOT NULL UNIQUE,
    total_bookings  INT           NOT NULL DEFAULT 0,
    rewards_used    JSON          DEFAULT NULL,
    status          ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. SETTINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(80)  NOT NULL PRIMARY KEY,
    `value`     TEXT         DEFAULT NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ===== SETTINGS =====
INSERT INTO settings (`key`, `value`) VALUES
    ('studio_name',           'Studio 94'),
    ('studio_address',        '2F SBD Building Highway 1, San Isidro Poblacion, Nabua, Camarines Sur'),
    ('studio_email',          'hello@studio94.com'),
    ('studio_phone',          '+63 2 8123 4567'),
    ('hours_weekday',         '10:00 AM – 7:00 PM'),
    ('hours_saturday',        '10:00 AM – 7:00 PM'),
    ('hours_sunday',          '10:00 AM – 7:00 PM'),
    ('bank_name',             'GCash'),
    ('bank_account_name',     'Studio 94 Photography'),
    ('bank_account_no',       '0917 123 4567'),
    ('gcash_account_name',    'Studio 94 Photography'),
    ('gcash_account_number',  '0917 123 4567'),
    ('deposit_percent',       '50')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ===== USERS (Password = 'password' for all) =====
INSERT INTO users (name, email, phone, password, role, is_active) VALUES
    ('Admin User',    'admin@studio94.com',  '09170001122', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',  1),
    ('Juan dela Cruz','staff@studio94.com',  '09171112233', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',  1),
    ('Rosa Lim',      'rosa@studio94.com',   '09172223344', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',  1),
    ('Maria Santos',  'client@studio94.com', '09181234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 1),
    ('Pedro Reyes',   'pedro@studio94.com',  '09191234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 1),
    ('Ana Garcia',    'ana@studio94.com',    '09201234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 1),
    ('Carlo Lopez',   'carlo@studio94.com',  '09211234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 1),
    ('Bea Fernandez', 'bea@studio94.com',    '09221234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 1)
ON DUPLICATE KEY UPDATE email = email;

-- ===== MAIN PACKAGES =====
INSERT INTO packages (name, price, duration, description, features, color, is_active, is_popular) VALUES
    ('Self-Shoot',    1500, '2 hours', 'DIY photoshoot with professional lighting.',     '["Studio access","Professional lighting","30 edited photos"]',         '#D4A0A0', 1, 0),
    ('Studio Rental', 2500, '1 hour',  'Professional studio space with lighting.',       '["Studio Space","Basic Lighting kit","Changing Room"]',                '#B0C4DE', 1, 0),
    ('Family',        3500, '3 hours', 'Inclusive session for families.',                '["Family Props","20 Edited Photos","Digital Copies"]',                 '#A0C4A0', 1, 0),
    ('Creative',      5000, '4 hours', 'High-concept shoot with artistic direction.',    '["Artistic Direction","Advanced Retouching","Pro Stylist"]',           '#D4C4A0', 1, 0)
ON DUPLICATE KEY UPDATE name = name;

-- ===== SUB-PACKAGES (with parent_id) =====

-- Self-Shoot Sub-packages
INSERT IGNORE INTO packages (parent_id, name, price, duration, description, features, color, is_active, is_popular, max_pax)
SELECT 
    (SELECT id FROM packages WHERE name = 'Self-Shoot' AND parent_id IS NULL LIMIT 1),
    'Basic', 1500, '1 hour', 
    'Quick solo shoot with basic setup',
    '["1 Hour Session","10 Edited Photos","1 Outfit Change"]',
    '#D4A0A0', 1, 0, 1
UNION ALL
SELECT 
    (SELECT id FROM packages WHERE name = 'Self-Shoot' AND parent_id IS NULL LIMIT 1),
    'Premium', 2000, '2 hours',
    'Extended shoot with more time and photos',
    '["2 Hour Session","20 Edited Photos","3 Outfit Changes"]',
    '#D4A0A0', 1, 1, 2
UNION ALL
SELECT 
    (SELECT id FROM packages WHERE name = 'Self-Shoot' AND parent_id IS NULL LIMIT 1),
    'Deluxe', 2800, '3 hours',
    'Full creative session with unlimited outfits',
    '["3 Hour Session","30 Edited Photos","Unlimited Outfits"]',
    '#D4A0A0', 1, 0, 4;

-- Studio Rental Sub-packages
INSERT IGNORE INTO packages (parent_id, name, price, duration, description, features, color, is_active, is_popular, max_pax)
SELECT 
    (SELECT id FROM packages WHERE name = 'Studio Rental' AND parent_id IS NULL LIMIT 1),
    'Standard', 2500, '1 hour',
    'Basic studio rental with standard lighting',
    '["Studio Access","Basic Lighting","Changing Room"]',
    '#B0C4DE', 1, 0, 4
UNION ALL
SELECT 
    (SELECT id FROM packages WHERE name = 'Studio Rental' AND parent_id IS NULL LIMIT 1),
    'Extended', 3500, '2 hours',
    'Extended rental with professional lighting',
    '["Studio Access","Pro Lighting","Props","Changing Room"]',
    '#B0C4DE', 1, 1, 6;

-- Family Sub-packages
INSERT IGNORE INTO packages (parent_id, name, price, duration, description, features, color, is_active, is_popular, max_pax)
SELECT 
    (SELECT id FROM packages WHERE name = 'Family' AND parent_id IS NULL LIMIT 1),
    '3 Pax', 2500, '2 hours',
    'Perfect for small families',
    '["2 Hour Session","15 Edited Photos","Props","Digital Copies"]',
    '#A0C4A0', 1, 0, 3
UNION ALL
SELECT 
    (SELECT id FROM packages WHERE name = 'Family' AND parent_id IS NULL LIMIT 1),
    '4-6 Pax', 3500, '3 hours',
    'For medium to large families',
    '["3 Hour Session","25 Edited Photos","Props","Digital Copies"]',
    '#A0C4A0', 1, 1, 6;

-- Creative Sub-packages
INSERT IGNORE INTO packages (parent_id, name, price, duration, description, features, color, is_active, is_popular, max_pax)
SELECT 
    (SELECT id FROM packages WHERE name = 'Creative' AND parent_id IS NULL LIMIT 1),
    'Package A', 4500, '3 hours',
    'Artistic direction with professional stylist',
    '["3 Hour Session","30 Edited Photos","Professional Stylist","Advanced Retouching"]',
    '#D4C4A0', 1, 0, 2
UNION ALL
SELECT 
    (SELECT id FROM packages WHERE name = 'Creative' AND parent_id IS NULL LIMIT 1),
    'Package B', 5500, '5 hours',
    'Full creative suite with makeup artist',
    '["5 Hour Session","50 Edited Photos","Stylist + MUA","Advanced Retouching","Props"]',
    '#D4C4A0', 1, 1, 4;

-- ===== INVENTORY =====
INSERT INTO inventory (name, category, quantity, threshold) VALUES
    ('Maroon Backdrop', 'Backdrop',  2, 1),
    ('Battery Pack',    'Equipment', 1, 2),
    ('Softbox Light',   'Lighting',  4, 1),
    ('White Backdrop',  'Backdrop',  3, 1),
    ('LED Ring Light',  'Lighting',  0, 1),
    ('Tripod',          'Equipment', 3, 1),
    ('Reflector',       'Equipment', 2, 1)
ON DUPLICATE KEY UPDATE name = name;

-- ===== BOOKINGS =====
INSERT INTO bookings (booking_ref, user_id, package_id, date, time, people, phone, status, type, package_price, deposit_amount, deposit_paid, remaining_balance, fully_paid) VALUES
    ('B-2026-001', (SELECT id FROM users WHERE email='client@studio94.com' LIMIT 1), (SELECT id FROM packages WHERE name='Self-Shoot' AND parent_id IS NULL LIMIT 1), '2026-06-15', '2:00 PM', 1, '09181234567', 'Deposit Paid',      'online', 1500, 750, 1, 750, 0),
    ('B-2026-002', (SELECT id FROM users WHERE email='pedro@studio94.com' LIMIT 1),  (SELECT id FROM packages WHERE name='Family' AND parent_id IS NULL LIMIT 1),   '2026-06-18', '11:00 AM', 4, '09191234567', 'Awaiting Approval', 'online', 3500, 1750, 0, 1750, 0),
    ('B-2026-003', (SELECT id FROM users WHERE email='ana@studio94.com' LIMIT 1),    (SELECT id FROM packages WHERE name='Creative' AND parent_id IS NULL LIMIT 1), '2026-06-20', '3:00 PM',  1, '09201234567', 'Completed',         'online', 5000, 2500, 1, 0, 1),
    ('B-2026-004', (SELECT id FROM users WHERE email='carlo@studio94.com' LIMIT 1),  (SELECT id FROM packages WHERE name='Studio Rental' AND parent_id IS NULL LIMIT 1),'2026-06-22', '10:00 AM', 2, '09211234567', 'Awaiting Approval', 'online', 2500, 1250, 0, 1250, 0),
    ('B-2026-005', (SELECT id FROM users WHERE email='bea@studio94.com' LIMIT 1),    (SELECT id FROM packages WHERE name='Self-Shoot' AND parent_id IS NULL LIMIT 1), '2026-05-10', '9:00 AM',  1, '09221234567', 'Completed',         'online', 1500, 750, 1, 0, 1),
    ('B-2026-006', (SELECT id FROM users WHERE email='client@studio94.com' LIMIT 1), (SELECT id FROM packages WHERE name='Family' AND parent_id IS NULL LIMIT 1),   '2026-05-05', '1:00 PM',  3, '09181234567', 'Completed',         'online', 3500, 1750, 1, 0, 1)
ON DUPLICATE KEY UPDATE booking_ref = booking_ref;

-- ===== PAYMENTS =====
INSERT INTO payments (payment_ref, booking_id, user_id, amount, type, method, ref_number, status) VALUES
    ('PAY-2026-001', (SELECT id FROM bookings WHERE booking_ref='B-2026-001' LIMIT 1), (SELECT id FROM users WHERE email='client@studio94.com' LIMIT 1), 750.00,  'DEPOSIT', 'GCash',         'GCASH123', 'PAID'),
    ('PAY-2026-002', (SELECT id FROM bookings WHERE booking_ref='B-2026-002' LIMIT 1), (SELECT id FROM users WHERE email='pedro@studio94.com' LIMIT 1),  1750.00, 'DEPOSIT', 'Bank Transfer', 'BANK789',  'PENDING'),
    ('PAY-2026-003', (SELECT id FROM bookings WHERE booking_ref='B-2026-003' LIMIT 1), (SELECT id FROM users WHERE email='ana@studio94.com' LIMIT 1),    2500.00, 'DEPOSIT', 'Maya',          'MAYA456',  'PAID'),
    ('PAY-2026-004', (SELECT id FROM bookings WHERE booking_ref='B-2026-004' LIMIT 1), (SELECT id FROM users WHERE email='carlo@studio94.com' LIMIT 1),  1250.00, 'DEPOSIT', NULL,            NULL,       'UNPAID'),
    ('PAY-2026-005', (SELECT id FROM bookings WHERE booking_ref='B-2026-005' LIMIT 1), (SELECT id FROM users WHERE email='bea@studio94.com' LIMIT 1),    750.00,  'DEPOSIT', 'GCash',         'GC999',    'PAID'),
    ('PAY-2026-006', (SELECT id FROM bookings WHERE booking_ref='B-2026-006' LIMIT 1), (SELECT id FROM users WHERE email='client@studio94.com' LIMIT 1), 1750.00, 'DEPOSIT', 'Maya',          'MY888',    'PAID')
ON DUPLICATE KEY UPDATE payment_ref = payment_ref;

-- ===== FEEDBACK =====
INSERT INTO feedback (user_id, rating_overall, rating_staff, rating_quality, rating_timeliness, comment, sentiment, topics, is_urgent) VALUES
    ((SELECT id FROM users WHERE email='client@studio94.com' LIMIT 1), 5, 5, 5, 5, 'Ang ganda ng kuha! Satisfied ako.',          'positive', 'photographer,quality', 0),
    ((SELECT id FROM users WHERE email='pedro@studio94.com' LIMIT 1),  2, 3, 4, 1, 'Ang bagal ng delivery ng photos.',            'negative', 'delivery',             1),
    ((SELECT id FROM users WHERE email='ana@studio94.com' LIMIT 1),    4, 4, 4, 4, 'Maayos naman ang staff.',                    'positive', 'staff',                0),
    ((SELECT id FROM users WHERE email='carlo@studio94.com' LIMIT 1),  5, 5, 5, 5, 'Super nice studio setup!',                   'positive', 'studio',               0);

-- ===== NOTIFICATIONS =====
INSERT INTO notifications (user_id, type, title, message, icon, is_read) VALUES
    ((SELECT id FROM users WHERE email='client@studio94.com' LIMIT 1), 'photo',   'Photos Ready for Download', 'Your photos from the session are now ready.',              '📸', 0),
    ((SELECT id FROM users WHERE email='client@studio94.com' LIMIT 1), 'status',  'Booking Confirmed',         'Your session on June 22 has been confirmed.',              '✅', 0),
    ((SELECT id FROM users WHERE email='staff@studio94.com' LIMIT 1),  'booking', 'New Booking Request',       'Maria Santos requested a Family Package on June 18.',     '📅', 0),
    ((SELECT id FROM users WHERE email='staff@studio94.com' LIMIT 1),  'payment', 'Payment Verification Needed','Pedro Reyes submitted a payment proof.',                  '💳', 1),
    ((SELECT id FROM users WHERE email='admin@studio94.com' LIMIT 1),  'system',  'Low Stock Alert',           'LED Ring Light is out of stock. Battery Pack below threshold.', '⚠️', 0);

-- ===== LOYALTY CARDS =====
INSERT INTO loyalty_cards (user_id, card_number, total_bookings, status) VALUES
    ((SELECT id FROM users WHERE email='client@studio94.com' LIMIT 1), 'LC-2026-001', 2, 'Active'),
    ((SELECT id FROM users WHERE email='pedro@studio94.com' LIMIT 1),  'LC-2026-002', 1, 'Active'),
    ((SELECT id FROM users WHERE email='ana@studio94.com' LIMIT 1),    'LC-2026-003', 3, 'Active'),
    ((SELECT id FROM users WHERE email='carlo@studio94.com' LIMIT 1),  'LC-2026-004', 4, 'Active'),
    ((SELECT id FROM users WHERE email='bea@studio94.com' LIMIT 1),    'LC-2026-005', 1, 'Active')
ON DUPLICATE KEY UPDATE user_id = user_id;

-- ============================================================
-- KONPIRMASYON: Ipakita ang lahat ng tables at records
-- ============================================================
SELECT '✅ Database setup complete!' AS Status;
SELECT '📊 Users:' AS Info, COUNT(*) AS Count FROM users;
SELECT '📦 Packages:' AS Info, COUNT(*) AS Count FROM packages;
SELECT '📅 Bookings:' AS Info, COUNT(*) AS Count FROM bookings;
SELECT '💳 Payments:' AS Info, COUNT(*) AS Count FROM payments;
SELECT '📦 Inventory:' AS Info, COUNT(*) AS Count FROM inventory;
SELECT '⭐ Feedback:' AS Info, COUNT(*) AS Count FROM feedback;
SELECT '📸 Photos:' AS Info, COUNT(*) AS Count FROM photos;
SELECT '🔔 Notifications:' AS Info, COUNT(*) AS Count FROM notifications;
SELECT '🎫 Loyalty Cards:' AS Info, COUNT(*) AS Count FROM loyalty_cards;
SELECT '⚙️ Settings:' AS Info, COUNT(*) AS Count FROM settings;