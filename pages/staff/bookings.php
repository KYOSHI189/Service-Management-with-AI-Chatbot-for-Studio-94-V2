<?php
// ============================================================
// BOOKINGS MANAGEMENT — ADMIN/STAFF
// Included inside index.php's page-content
// ₱100 Flat Reservation Fee System
// White & Black Theme
// ============================================================

requireRole(['admin', 'staff']);
$pdo = db();

// ============================================================
// RESERVATION FEE (fixed amount)
// ============================================================
const RESERVATION_FEE = 100;

// ============================================================
// HANDLE BOOKING ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve' || $action === 'complete' || $action === 'cancel') {
        $id = cleanInt($_POST['booking_id'] ?? 0);
        if ($id) {
            $bookingStmt = $pdo->prepare("SELECT package_id, status, user_id, booking_ref FROM bookings WHERE id = ?");
            $bookingStmt->execute([$id]);
            $bookingData = $bookingStmt->fetch();

            $statusMap = ['approve' => 'Approved (Unpaid)', 'complete' => 'Completed', 'cancel' => 'Cancelled'];
            $pdo->prepare("UPDATE bookings SET status=? WHERE id=?")->execute([$statusMap[$action], $id]);

            // ============================================================
            // ✅ NEW: SYNC LOYALTY CARD AFTER STATUS CHANGE
            // ============================================================
            // Ang loyalty count ay dynamic base sa COMPLETED bookings.
            // I-sync ang loyalty_cards.total_bookings column para consistent
            // ang database (kahit dynamic na ang display).
            // ============================================================
            if ($bookingData && $bookingData['user_id'] > 0) {
                syncLoyaltyCard((int)$bookingData['user_id']);
            }

            if ($action === 'complete') {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM package_inventory WHERE package_id = ?");
                $checkStmt->execute([$bookingData['package_id']]);
                $hasItems = $checkStmt->fetchColumn();

                if ($hasItems > 0) {
                    $pkgStmt = $pdo->prepare("
                        SELECT pi.*, i.name as inventory_name, i.quantity as current_stock
                        FROM package_inventory pi
                        JOIN inventory i ON pi.inventory_id = i.id
                        WHERE pi.package_id = ?
                    ");
                    $pkgStmt->execute([$bookingData['package_id']]);
                    $items = $pkgStmt->fetchAll();

                    $deducted = [];
                    $errors = [];

                    foreach ($items as $item) {
                        if ($item['current_stock'] < $item['quantity']) {
                            $errors[] = 'Not enough stock for: ' . $item['inventory_name'];
                            continue;
                        }

                        $deductStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
                        $deductStmt->execute([$item['quantity'], $item['inventory_id']]);

                        if ($deductStmt->rowCount() > 0) {
                            $deducted[] = $item['inventory_name'] . ' (-' . $item['quantity'] . ')';
                            $biStmt = $pdo->prepare("INSERT INTO booking_inventory (booking_id, inventory_id, quantity, used_at) VALUES (?, ?, ?, NOW())");
                            $biStmt->execute([$id, $item['inventory_id'], $item['quantity']]);
                        }
                    }

                    if (!empty($deducted)) {
                        $msg = 'Inventory deducted: ' . implode(', ', $deducted);
                        addNotificationByRole('admin', '📦 Inventory Updated', $msg, 'inventory', '📦');
                        addNotificationByRole('staff', '📦 Inventory Updated', $msg, 'inventory', '📦');
                        setFlash('success', 'Booking completed. Inventory deducted: ' . implode(', ', $deducted));
                    } elseif (!empty($errors)) {
                        setFlash('warning', 'Booking completed but inventory errors: ' . implode(', ', $errors));
                    } else {
                        setFlash('success', 'Booking completed successfully.');
                    }
                } else {
                    setFlash('success', 'Booking completed. No inventory items to deduct.');
                }
            } else {
                if ($action === 'approve') {
                    setFlash('success', 'Booking approved successfully.');
                } else {
                    setFlash('success', 'Booking cancelled successfully.');
                }
            }

            $info = $pdo->prepare("
                SELECT b.user_id, 
                       CASE 
                           WHEN u.id IS NULL THEN 'Walk-in Client'
                           ELSE u.name 
                       END as name,
                       p.name as pkg 
                FROM bookings b 
                LEFT JOIN users u ON b.user_id = u.id 
                JOIN packages p ON b.package_id = p.id 
                WHERE b.id=?
            ");
            $info->execute([$id]);
            $bInfo = $info->fetch();

            if ($bInfo && $bInfo['user_id'] > 0) {
                $msgs = [
                    'approve'  => ['Booking Approved', 'Your ' . $bInfo['pkg'] . ' booking has been approved.', '✅'],
                    'complete' => ['Session Completed', 'Your ' . $bInfo['pkg'] . ' session is now completed.', '🎉'],
                    'cancel'   => ['Booking Cancelled', 'Your booking has been cancelled.', '❌'],
                ];
                addNotification($bInfo['user_id'], $msgs[$action][0], $msgs[$action][1], 'status', $msgs[$action][2]);
            }

            header('Location: index.php?page=bookings');
            exit;
        }
    }

    // WALK-IN BOOKING
    if ($action === 'walkin') {
        $name    = trim($_POST['wi_name']  ?? '');
        $email   = trim($_POST['wi_email'] ?? '');
        $phone   = trim($_POST['wi_phone'] ?? '');
        $pkgId   = cleanInt($_POST['wi_package'] ?? 0);
        $date    = $_POST['wi_date']    ?? '';
        $time    = $_POST['wi_time']    ?? '';
        $people  = cleanInt($_POST['wi_people'] ?? 1);
        $notes   = trim($_POST['wi_notes'] ?? '');

        $errors = [];
        if (!$name || !$pkgId || !$date || !$time) $errors[] = 'Please fill all required fields.';

        if (empty($errors)) {
            $pkgR = $pdo->prepare("SELECT duration, price, name FROM packages WHERE id = ?");
            $pkgR->execute([$pkgId]);
            $pkg = $pkgR->fetch();
            $duration = extractHoursFromDuration($pkg['duration'] ?? '1 hour');

            $conflicts = checkBookingConflict($date, $time, null, $duration);
            if (!empty($conflicts)) {
                $errors[] = 'Conflict: that slot is already booked or overlapping with another booking.';
            }
        }

        if (empty($errors)) {
            $existUser = null;
            if ($email) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
                $uStmt->execute([$email]);
                $existUser = $uStmt->fetchColumn();
            }

            if (!$existUser && $phone) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE phone=?");
                $uStmt->execute([$phone]);
                $existUser = $uStmt->fetchColumn();
            }

            if (!$existUser) {
                $randomPassword = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

                if (empty($email)) {
                    $email = 'walkin_' . uniqid() . '@example.com';
                }

                $pdo->prepare("INSERT INTO users (name, email, phone, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 'client', 1, NOW())")
                    ->execute([$name, $email, $phone, $hashedPassword]);
                $existUser = $pdo->lastInsertId();

                addNotification($existUser, 'Welcome to Studio 94!', 
                    'Your account has been created. Your temporary password is: ' . $randomPassword, 
                    'system', '🎉');
            }

            $ref  = 'WALK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $pkgR = $pdo->prepare("SELECT price, duration, name FROM packages WHERE id=?");
            $pkgR->execute([$pkgId]);
            $pkg  = $pkgR->fetch();

            $reservationFee = RESERVATION_FEE;
            $remaining      = max(0, ($pkg['price'] ?? 0) - $reservationFee);

            $pdo->prepare("INSERT INTO bookings (booking_ref, user_id, package_id, date, time, people, notes, type, status, package_price, deposit_amount, remaining_balance, deposit_paid, fully_paid, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'walk-in', 'Awaiting Approval', ?, ?, ?, 0, 0, NOW())")
                ->execute([$ref, $existUser, $pkgId, $date, $time, $people, $notes, $pkg['price'], $reservationFee, $remaining]);
            $bookingId = $pdo->lastInsertId();

            $payRef = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $pdo->prepare("INSERT INTO payments (payment_ref, booking_id, user_id, amount, type, method, status, created_at) 
                VALUES (?, ?, ?, ?, 'RESERVATION', 'Pending', 'UNPAID', NOW())")
                ->execute([$payRef, $bookingId, $existUser, $reservationFee]);

            // ============================================================
            // ✅ FIXED: Loyalty card creation — total_bookings = 0
            // ============================================================
            $lcCheck = $pdo->prepare("SELECT id FROM loyalty_cards WHERE user_id=?");
            $lcCheck->execute([$existUser]);
            if (!$lcCheck->fetch()) {
                $cardNo = 'LC-' . date('Y') . '-' . str_pad($existUser, 3, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO loyalty_cards (user_id, card_number, total_bookings, status) VALUES (?, ?, 0, 'Active')")
                    ->execute([$existUser, $cardNo]);
            }

            addNotificationByRole('admin', '🚶 Walk-in Booking Created', 
                $name . ' booked ' . $pkg['name'] . ' on ' . date('M d, Y', strtotime($date)) . ' at ' . $time . ' (Ref: ' . $ref . ')', 
                'booking', '🚶');
            addNotificationByRole('staff', '🚶 Walk-in Booking Created', 
                $name . ' booked ' . $pkg['name'] . ' on ' . date('M d, Y', strtotime($date)) . ' at ' . $time . ' (Ref: ' . $ref . ')', 
                'booking', '🚶');

            setFlash('success', '✅ Walk-in booking created for ' . $name . ' (Ref: ' . $ref . ') — Reservation Fee: ₱' . number_format($reservationFee, 2));
        } else {
            setFlash('error', implode(' ', $errors));
        }
    }

    header('Location: index.php?page=bookings');
    exit;
}

// ============================================================
// GET FILTERS
// ============================================================
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');

$where   = ['1=1'];
$params  = [];

if ($statusFilter !== 'all') { 
    $where[] = 'b.status = ?'; 
    $params[] = $statusFilter; 
}

if ($search) { 
    $where[] = '(u.name LIKE ? OR b.booking_ref LIKE ?)'; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}

// ============================================================
// ✅ FIXED: GET BOOKINGS + DYNAMIC LOYALTY COUNT
// ============================================================
// Ang loyalty count ay hindi na kinukuha sa loyalty_cards.total_bookings.
// Sa halip, ito ay kinakalkula dynamically base sa COMPLETED bookings.
// ============================================================
$stmt = $pdo->prepare("
    SELECT b.*, 
           CASE 
               WHEN u.id IS NULL THEN 'Walk-in Client'
               ELSE u.name 
           END as client_name,
           u.phone as client_phone,
           u.email as client_email,
           p.name as pkg_name, 
           p.price as pkg_price,
           b.type as booking_type,
           b.deposit_amount,
           b.remaining_balance,
           b.deposit_paid,
           b.fully_paid,
           lc.card_number as loyalty_card,
           lc.status as loyalty_status
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    LEFT JOIN loyalty_cards lc ON lc.user_id = b.user_id
    JOIN packages p ON b.package_id = p.id
    WHERE " . implode(' AND ', $where) . " 
    ORDER BY b.created_at DESC, b.date DESC, b.time DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// ============================================================
// ✅ FIXED: GET DYNAMIC LOYALTY COUNTS FOR ALL USERS
// ============================================================
// Kunin ang loyalty count base sa COMPLETED bookings para sa lahat ng users
// sa bookings list. Ito ay isang query lang para efficient.
// ============================================================
$userIds = array_filter(array_unique(array_column($bookings, 'user_id')));
$loyaltyCounts = [];

if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $loyaltyStmt = $pdo->prepare("
        SELECT user_id, COUNT(*) as completed_count
        FROM bookings
        WHERE user_id IN ($placeholders)
          AND status = 'Completed'
        GROUP BY user_id
    ");
    $loyaltyStmt->execute($userIds);
    foreach ($loyaltyStmt->fetchAll() as $row) {
        $loyaltyCounts[(int)$row['user_id']] = (int)$row['completed_count'];
    }
}

// ============================================================
// GET PACKAGES FOR WALK-IN FORM
// ============================================================
$packages = $pdo->query("
    SELECT * FROM packages 
    WHERE is_active = 1 
    ORDER BY parent_id, name, price ASC
")->fetchAll();
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
.page-banner-art {
    font-size: 32px;
    align-self: flex-end;
}

/* CARD */
.card {
    padding: 14px;
    border-radius: 12px;
    box-sizing: border-box;
    min-width: 0;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.section-header h3 {
    font-size: 15px;
    margin: 0;
    word-break: break-word;
}

/* WALK-IN FORM */
.walkin-info-banner {
    background: #F5F5F5;
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 12px;
    color: #0A0A0A;
    border: 1px solid #0A0A0A;
    line-height: 1.5;
    word-break: break-word;
}

.walkin-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.form-group { margin-bottom: 12px; }
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #0A0A0A;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #E0E0E0;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    font-family: inherit;
    background: #fff;
}
.form-group textarea { resize: vertical; min-height: 60px; }

.walkin-form-actions {
    display: flex;
    gap: 10px;
    margin-top: 8px;
    flex-wrap: wrap;
}
.walkin-form-actions .btn-ghost,
.walkin-form-actions .btn-primary {
    flex: 1 1 auto;
    min-width: 140px;
    justify-content: center;
    text-align: center;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    box-sizing: border-box;
}

/* SEARCH + FILTERS */
.filters-bar {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    align-items: center;
}
.filters-bar form {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
    width: 100%;
}
.filters-bar .search-input {
    padding: 7px 14px;
    border: 1px solid #E0E0E0;
    border-radius: 50px;
    font-size: 13px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    flex: 1 1 160px;
}
.filters-bar .filter-btn {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #E0E0E0;
    background: #FFFFFF;
    color: #0A0A0A;
    white-space: nowrap;
    box-sizing: border-box;
    transition: all 0.2s;
}
.filters-bar .filter-btn.active {
    background: #0A0A0A;
    color: #FFFFFF;
    border-color: #0A0A0A;
}

/* TABLE */
.table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 8px;
    margin: 0 -4px;
    padding: 0 4px;
}
.table-wrap table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
    font-size: 12px;
}
.table-wrap th,
.table-wrap td {
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid #E0E0E0;
    vertical-align: top;
}
.table-wrap th {
    font-weight: 600;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8B8177;
    background: #F5F5F5;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 2;
}
.table-wrap tr:hover { background: rgba(10, 10, 10, 0.02); }

/* ACTIONS CELL */
.actions-cell { min-width: 200px; }
.action-buttons {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 76px;
    padding: 6px 10px;
    font-size: 11px;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    text-decoration: none;
    line-height: 1.4;
    box-sizing: border-box;
}
.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.action-btn-approve { background: #0A0A0A; color: #FFFFFF; }
.action-btn-approve:hover { background: #2D3436; }
.action-btn-complete {
    background: #F5F5F5;
    color: #0A0A0A;
    border: 1px solid #0A0A0A;
}
.action-btn-complete:hover { background: #0A0A0A; color: #FFFFFF; }
.action-btn-cancel {
    background: #FFFFFF;
    color: #0A0A0A;
    border: 1px solid #0A0A0A;
}
.action-btn-cancel:hover { background: #0A0A0A; color: #FFFFFF; }
.action-btn-pay {
    background: #0A0A0A;
    color: #FFFFFF;
    border: 1px solid #0A0A0A;
}
.action-btn-pay:hover { background: #2D3436; }

/* LOYALTY PILLS */
.loyalty-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 90px;
}
.loyalty-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 10px;
    font-weight: 700;
    background: #0A0A0A;
    color: #FFFFFF;
    white-space: nowrap;
    width: fit-content;
}
.loyalty-sub {
    font-size: 9px;
    font-weight: 700;
    color: #0A0A0A;
    background: #F5F5F5;
    padding: 2px 6px;
    border-radius: 50px;
    border: 1px solid #0A0A0A;
    text-align: center;
    width: fit-content;
}
.loyalty-card-no {
    font-size: 9px;
    font-family: monospace;
    color: #8B8177;
    word-break: break-all;
}

/* BADGES */
.type-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}
.type-badge.walkin {
    background: #0A0A0A;
    color: #fff;
}
.type-badge.online {
    background: #F5F5F5;
    color: #0A0A0A;
    border: 1px solid #E0E0E0;
}

/* MODAL */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    justify-content: center;
    align-items: flex-start;
    padding: 16px;
    overflow-y: auto;
    box-sizing: border-box;
}
.modal-content {
    background: #FFFFFF;
    border-radius: 16px;
    width: 100%;
    max-width: 480px;
    position: relative;
    margin: auto;
    box-sizing: border-box;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
}
.modal-close {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #F5F5F5;
    border: 1px solid #E0E0E0;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    color: #0A0A0A;
}
.modal-close:hover { background: #E0E0E0; }
.modal-body { padding: 20px 16px; }

.modal-body h2 {
    font-size: 17px;
    margin-bottom: 16px;
    word-break: break-word;
}

/* MODAL INFO BOX */
.modal-info-box {
    background: #F5F5F5;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 16px;
}
.modal-info-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 13px;
    padding: 3px 0;
    flex-wrap: wrap;
}
.modal-info-row .label { color: #8B8177; }
.modal-info-row .value { font-weight: 600; word-break: break-word; text-align: right; }

.modal-remaining-divider {
    border-top: 1px solid #E0E0E0;
    margin: 8px 0;
    padding-top: 8px;
}
.modal-remaining-hint {
    font-size: 11px;
    color: #8B8177;
    text-align: right;
    margin-top: 4px;
    word-break: break-word;
}

/* PAYMENT TYPE SELECTOR */
.payment-type-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.payment-type-option {
    flex: 1 1 120px;
    padding: 12px;
    border: 2px solid #E0E0E0;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    transition: 0.2s;
    background: transparent;
    box-sizing: border-box;
    min-width: 0;
}
.payment-type-option.active {
    border-color: #0A0A0A;
    background: #F5F5F5;
}
.payment-type-option .pt-title {
    font-weight: 600;
    font-size: 13px;
    word-break: break-word;
}
.payment-type-option .pt-desc {
    font-size: 11px;
    color: #8B8177;
    margin-top: 2px;
}

/* AMOUNT DISPLAY */
.modal-amount-display {
    text-align: center;
    padding: 16px;
    background: #F5F5F5;
    border-radius: 8px;
    margin-bottom: 16px;
}
.modal-amount-display .mamount-label {
    font-size: 11px;
    color: #8B8177;
}
.modal-amount-display .mamount-value {
    font-size: 28px;
    font-weight: 700;
    color: #0A0A0A;
    line-height: 1.1;
    word-break: break-word;
}
.modal-amount-display .mamount-note {
    font-size: 11px;
    color: #8B8177;
    margin-top: 4px;
}

/* MODAL ACTIONS */
.modal-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.modal-actions button {
    flex: 1 1 auto;
    min-width: 120px;
    padding: 12px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    box-sizing: border-box;
    font-size: 13px;
}
.modal-actions .btn-cancel {
    background: #F5F5F5;
    color: #0A0A0A;
    border: 1px solid #E0E0E0;
}
.modal-actions .btn-confirm {
    background: #0A0A0A;
    color: #fff;
    border: none;
    flex: 2 1 auto;
}

/* ============================================================
   SMALL PHONE (min-width: 380px)
   ============================================================ */
@media (min-width: 380px) {
    .page-banner h2 { font-size: 19px; }
    .section-header h3 { font-size: 16px; }
    .table-wrap table { font-size: 13px; }
    .action-btn { padding: 6px 12px; font-size: 12px; min-width: 82px; }
    .modal-body { padding: 22px 18px; }
    .modal-body h2 { font-size: 18px; }
    .modal-amount-display .mamount-value { font-size: 32px; }
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
    .page-banner-art { font-size: 40px; align-self: center; }

    .card { padding: 18px; }

    /* Walk-in form: 2 columns na */
    .walkin-form-grid {
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .filters-bar .search-input { width: auto; max-width: 200px; }

    .modal-content { max-width: 520px; }
    .modal-body { padding: 24px 22px; }

    .modal-actions button { font-size: 14px; }
}

/* ============================================================
   TABLET (min-width: 768px)
   ============================================================ */
@media (min-width: 768px) {
    .page-banner { padding: 24px 28px; border-radius: 16px; gap: 20px; }
    .page-banner h2 { font-size: 24px; }
    .page-banner p { font-size: 13px; }
    .page-banner-art { font-size: 48px; }

    .card { padding: 20px 22px; border-radius: 14px; }
    .section-header { margin-bottom: 16px; }
    .section-header h3 { font-size: 17px; }

    .table-wrap table {
        font-size: 13px;
        min-width: 1000px;
    }
    .table-wrap th,
    .table-wrap td { padding: 10px 12px; }
    .table-wrap th { font-size: 11px; }

    .action-btn { padding: 6px 12px; font-size: 12px; min-width: 82px; }

    .modal-content { max-width: 560px; }
    .modal-body h2 { font-size: 20px; }
}

/* ============================================================
   LAPTOP / DESKTOP (min-width: 1024px)
   ============================================================ */
@media (min-width: 1024px) {
    .page-banner { padding: 28px 32px; }
    .page-banner h2 { font-size: 26px; }
    .page-banner-art { font-size: 52px; }

    .card { padding: 22px 24px; }

    .table-wrap table {
        font-size: 13px;
        min-width: 1050px;
    }
    .table-wrap th,
    .table-wrap td { padding: 11px 14px; }

    .action-btn { min-width: 88px; padding: 7px 14px; font-size: 12px; }
    .actions-cell { min-width: 280px; }

    .modal-content { max-width: 600px; }
    .modal-body { padding: 26px 24px; }
}

/* ============================================================
   LARGE DESKTOP (min-width: 1440px)
   ============================================================ */
@media (min-width: 1440px) {
    .page-banner { padding: 32px 40px; border-radius: 18px; }
    .page-banner h2 { font-size: 30px; }
    .page-banner p { font-size: 14px; }
    .page-banner-art { font-size: 64px; }

    .card { padding: 26px 28px; }
    .section-header h3 { font-size: 18px; }

    .table-wrap table {
        font-size: 14px;
        min-width: 1100px;
    }
    .table-wrap th,
    .table-wrap td { padding: 12px 16px; }

    .action-btn { min-width: 92px; padding: 8px 16px; font-size: 13px; }

    .modal-content { max-width: 640px; }
    .modal-body { padding: 28px 28px; }
    .modal-amount-display .mamount-value { font-size: 36px; }
}

/* ============================================================
   ULTRAWIDE (min-width: 1920px)
   ============================================================ */
@media (min-width: 1920px) {
    .page-banner { padding: 36px 48px; }
    .page-banner h2 { font-size: 34px; }

    .card { padding: 30px 32px; }
    .table-wrap th,
    .table-wrap td { padding: 14px 18px; }
    .table-wrap table { font-size: 15px; }
}

/* ============================================================
   LANDSCAPE MOBILE
   ============================================================ */
@media (max-height: 500px) and (orientation: landscape) {
    .page-banner { padding: 12px 16px; }
    .page-banner h2 { font-size: 18px; }
    .page-banner-art { font-size: 32px; }
    .walkin-form-grid { grid-template-columns: 1fr 1fr; }
    .modal-content { max-height: calc(100vh - 20px); }
}

/* ============================================================
   PRINT
   ============================================================ */
@media print {
    .page-banner-art,
    .action-buttons,
    .filters-bar,
    .modal-overlay,
    .btn-primary,
    .btn-ghost,
    .action-btn { display: none !important; }
    .card { break-inside: avoid; border: 1px solid #ccc; }
    .table-wrap { overflow: visible; }
    .table-wrap table { min-width: 0; }
    .table-wrap th,
    .table-wrap td { white-space: normal; }
}
</style>

<!-- ============================================================ -->
<!-- PAGE BANNER                                                   -->
<!-- ============================================================ -->
<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">Manage</div>
    <h2><strong>📋 Bookings</strong></h2>
    <p>All booking records <span style="font-size:12px;color:#8B8177;">⬇️ Newest first</span></p>
  </div>
  <div class="page-banner-art">📋</div>
</div>

<!-- ============================================================ -->
<!-- WALK-IN FORM                                                  -->
<!-- ============================================================ -->
<?php if (isset($_GET['action']) && $_GET['action'] === 'walkin'): ?>
<div class="card" style="margin-bottom:20px;border:1px solid #E0E0E0;">
    <div class="section-header">
        <h3><strong>🚶 Create Walk-in Booking</strong></h3>
        <a href="index.php?page=bookings" class="btn-ghost btn-sm">← Back</a>
    </div>

    <div class="walkin-info-banner">
        🕐 Studio Hours: <strong>10:00 AM - 7:00 PM</strong> · 🎫 Reservation Fee: <strong>₱100</strong>
    </div>

    <form method="POST" action="index.php?page=bookings" autocomplete="off">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="walkin">

        <div class="form-group">
            <label><strong>Client Name</strong> <span style="color:#E74C3C;">*</span></label>
            <input type="text" name="wi_name" required placeholder="Enter client's full name">
        </div>

        <div class="walkin-form-grid">
            <div class="form-group">
                <label><strong>Email</strong></label>
                <input type="email" name="wi_email" placeholder="client@email.com">
            </div>
            <div class="form-group">
                <label><strong>Phone</strong> <span style="color:#E74C3C;">*</span></label>
                <input type="tel" name="wi_phone" required placeholder="09XX XXX XXXX">
            </div>
        </div>

        <div class="form-group">
            <label><strong>Package</strong> <span style="color:#E74C3C;">*</span></label>
            <select name="wi_package" required>
                <option value="">Select Package…</option>
                <?php foreach ($packages as $p): ?>
                    <option value="<?= $p['id'] ?>">
                        <?= clean($p['name']) ?> — ₱<?= number_format($p['price']) ?> (<?= clean($p['duration']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="walkin-form-grid">
            <div class="form-group">
                <label><strong>Date</strong> <span style="color:#E74C3C;">*</span></label>
                <input type="date" name="wi_date" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label><strong>Time</strong> <span style="color:#E74C3C;">*</span></label>
                <select name="wi_time" required>
                    <option value="">Select time…</option>
                    <?php foreach (['10:00 AM','11:00 AM','12:00 PM','1:00 PM','2:00 PM','3:00 PM','4:00 PM','5:00 PM','6:00 PM','7:00 PM'] as $t): ?>
                        <option><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="walkin-form-grid">
            <div class="form-group">
                <label><strong>Number of People</strong></label>
                <input type="number" name="wi_people" min="1" value="1">
            </div>
            <div class="form-group">
                <label><strong>Notes</strong></label>
                <textarea name="wi_notes" placeholder="Special requests…" rows="2"></textarea>
            </div>
        </div>

        <div class="walkin-form-actions">
            <a href="index.php?page=bookings" class="btn-ghost">← Cancel</a>
            <button class="btn-primary" type="submit">✅ Create Walk-in Booking</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- BOOKINGS TABLE                                                -->
<!-- ============================================================ -->
<div class="card">
    <div class="section-header">
        <h3><strong>📋 All Bookings</strong></h3>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="index.php?page=bookings&action=walkin" style="background:#0A0A0A;color:#fff;text-decoration:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;">🚶 + Walk-in</a>
        </div>
    </div>

    <div class="filters-bar">
        <form method="GET" action="index.php">
            <input type="hidden" name="page" value="bookings">
            <input type="text" name="q" value="<?= clean($search) ?>" placeholder="Search…" class="search-input">

            <?php 
            $statusOptions = [
                'all' => 'All',
                'Awaiting Approval' => 'Pending',
                'Approved (Unpaid)' => 'Approved',
                'Deposit Paid' => 'Paid',
                'Confirmed' => 'Confirmed',
                'Completed' => 'Completed',
                'Cancelled' => 'Cancelled',
                'Rejected' => 'Rejected'
            ];
            foreach ($statusOptions as $v => $l): 
                $isActive = ($statusFilter === $v);
            ?>
                <button type="submit" name="status" value="<?= $v ?>" 
                        class="filter-btn <?= $isActive ? 'active' : '' ?>">
                    <?= $l ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Loyalty</th>
                    <th>Package</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): 
                    // ✅ FIXED: Kunin ang dynamic loyalty count base sa COMPLETED bookings
                    $loyaltyCount = $loyaltyCounts[(int)$b['user_id']] ?? 0;
                    $hasLoyaltyDiscount = ($loyaltyCount >= 10);
                    $originalPrice = (float)$b['pkg_price'];
                    $effectivePrice = $hasLoyaltyDiscount ? round($originalPrice * 0.5, 2) : $originalPrice;
                    $reservationFee = RESERVATION_FEE;
                    $remainingAmount = max(0, round($effectivePrice - $reservationFee, 2));
                ?>
                <tr>
                    <!-- CLIENT -->
                    <td>
                        <strong><?= clean($b['client_name']) ?></strong>

                        <div style="font-size:10px;color:#8B8177;margin-top:3px;font-family:monospace;font-weight:600;word-break:break-all;">
                            <?= clean($b['booking_ref']) ?>
                        </div>

                        <div style="font-size:11px;color:#8B8177;margin-top:2px;">
                            <?= clean($b['client_phone'] ?? '—') ?>
                        </div>
                    </td>

                    <!-- LOYALTY -->
                    <td>
                        <?php if ($loyaltyCount > 0): ?>
                            <div class="loyalty-cell">
                                <span class="loyalty-pill">
                                    🎫 <?= $loyaltyCount ?> completed
                                </span>
                                <?php if ($hasLoyaltyDiscount): ?>
                                    <span class="loyalty-sub">🎉 50% OFF</span>
                                <?php elseif ($loyaltyCount >= 7): ?>
                                    <span class="loyalty-sub">🎨 +1 BACKDROP</span>
                                <?php elseif ($loyaltyCount >= 4): ?>
                                    <span class="loyalty-sub">🖼️ +1 PRINT</span>
                                <?php elseif ($loyaltyCount >= 2): ?>
                                    <span class="loyalty-sub">⏱️ +5 MIN</span>
                                <?php endif; ?>
                                <?php if (!empty($b['loyalty_card'])): ?>
                                    <span class="loyalty-card-no"><?= clean($b['loyalty_card']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span style="font-size:11px;color:#8B8177;font-style:italic;">No completed yet</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div><?= clean($b['pkg_name']) ?></div>
                        <div style="font-size:11px;color:#8B8177;margin-top:2px;">
                            🎫 ₱<?= number_format($reservationFee, 2) ?> reservation
                        </div>
                    </td>

                    <td><?= formatDate($b['date']) ?></td>
                    <td><?= clean($b['time']) ?></td>

                    <td>
                        <?php if ($b['booking_type'] === 'walk-in' || strpos($b['booking_ref'], 'WALK') !== false): ?>
                            <span class="type-badge walkin">🚶 Walk-in</span>
                        <?php else: ?>
                            <span class="type-badge online">📅 Online</span>
                        <?php endif; ?>
                    </td>

                    <td><?= statusBadge($b['status']) ?></td>

                    <td class="actions-cell">
                        <div class="action-buttons">
                            <?php if ($b['status'] === 'Awaiting Approval'): ?>
                            <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Approve this booking?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="action-btn action-btn-approve" type="submit">✅ Approve</button>
                            </form>
                            <?php endif; ?>

                            <?php if (in_array($b['status'], ['Approved (Unpaid)', 'Deposit Paid', 'Confirmed'], true)): ?>
                            <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Mark this booking as completed?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="action" value="complete">
                                <button class="action-btn action-btn-complete" type="submit">✔️ Complete</button>
                            </form>
                            <?php endif; ?>

                            <?php if (!in_array($b['status'], ['Completed', 'Cancelled', 'Rejected'], true)): ?>
                            <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Cancel this booking?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button class="action-btn action-btn-cancel" type="submit">❌ Cancel</button>
                            </form>
                            <?php endif; ?>

                            <?php if (in_array($b['status'], ['Awaiting Approval', 'Approved (Unpaid)'], true) || 
                                     ($b['status'] === 'Deposit Paid' && $b['fully_paid'] == 0)): ?>
                            <button class="action-btn action-btn-pay" onclick="openPaymentModal(
                                '<?= $b['id'] ?>', 
                                '<?= $b['booking_ref'] ?>', 
                                <?= $effectivePrice ?>, 
                                <?= $reservationFee ?>, 
                                '<?= addslashes($b['client_name']) ?>',
                                <?= $remainingAmount ?>,
                                <?= $b['deposit_paid'] ?? 0 ?>,
                                <?= $loyaltyCount ?>
                            )">
                                💰 Pay
                            </button>
                            <?php endif; ?>

                            <?php if (!in_array($b['status'], ['Awaiting Approval', 'Approved (Unpaid)', 'Deposit Paid', 'Confirmed'], true) 
                                      && !($b['status'] === 'Deposit Paid' && $b['fully_paid'] == 0)): ?>
                                <span style="color:#8B8177;font-size:12px;">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:#8B8177;padding:40px;">
                        📭 No bookings found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- PAYMENT MODAL                                                 -->
<!-- ============================================================ -->
<div id="staff-payment-modal" class="modal-overlay" onclick="if(event.target===this) closeModal('staff-payment-modal')">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('staff-payment-modal')">✕</button>
        <div class="modal-body">
            <h2><strong>💰 Record Payment</strong></h2>

            <div id="sp-loyalty-info" style="display:none;background:#0A0A0A;color:#FFFFFF;padding:12px;border-radius:8px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                    <div style="font-weight:600;font-size:13px;">🎫 Loyalty Status</div>
                    <span style="background:rgba(255,255,255,0.25);padding:3px 10px;border-radius:50px;font-size:11px;font-weight:600;white-space:nowrap;">
                        <span id="sp-loyalty-count">0</span> COMPLETED
                    </span>
                </div>
                <div id="sp-loyalty-rewards" style="font-size:11px;margin-top:6px;opacity:0.9;word-break:break-word;"></div>
            </div>

            <div class="modal-info-box">
                <div class="modal-info-row">
                    <span class="label">Booking:</span>
                    <strong id="sp-booking-ref">---</strong>
                </div>
                <div class="modal-info-row">
                    <span class="label">Client:</span>
                    <strong id="sp-client-name">---</strong>
                </div>
                <div class="modal-info-row">
                    <span class="label">Type:</span>
                    <strong id="sp-booking-type">---</strong>
                </div>
                <div class="modal-info-row">
                    <span class="label">Total Package:</span>
                    <strong id="sp-total">₱0</strong>
                </div>
                <div class="modal-info-row">
                    <span class="label">🎫 Reservation Fee:</span>
                    <strong id="sp-deposit-amount-display">₱100</strong>
                </div>

                <div id="sp-remaining-section" class="modal-remaining-divider">
                    <div class="modal-info-row" style="padding-top:8px;">
                        <span style="font-weight:600;">Remaining Balance:</span>
                        <strong id="sp-remaining" style="color:#0A0A0A;">₱0</strong>
                    </div>
                    <div class="modal-remaining-hint" id="sp-remaining-hint">
                        ⏳ Client still owes ₱0
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label style="font-weight:600;display:block;margin-bottom:8px;">Payment Type</label>
                <div class="payment-type-row">
                    <label class="payment-type-option active" id="sp-label-deposit" onclick="selectPaymentType('RESERVATION')">
                        <input type="radio" name="sp_payment_type" value="RESERVATION" checked style="display:none;">
                        <div class="pt-title">🎫 Reservation Fee</div>
                        <div class="pt-desc">₱100</div>
                    </label>
                    <label class="payment-type-option" id="sp-label-full" onclick="selectPaymentType('FULL')">
                        <input type="radio" name="sp_payment_type" value="FULL" style="display:none;">
                        <div class="pt-title">💰 Full Balance</div>
                        <div class="pt-desc">Remaining</div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label style="display:block;margin-bottom:6px;font-weight:500;">Payment Method</label>
                <select id="sp-method" style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;box-sizing:border-box;">
                    <option value="Cash">💵 Cash</option>
                    <option value="GCash">📱 GCash</option>
                    <option value="Maribank">🏦 Maribank</option>
                </select>
            </div>

            <div class="form-group">
                <label style="display:block;margin-bottom:6px;font-weight:500;">Reference Number <span style="color:#8B8177;font-weight:400;">(optional)</span></label>
                <input type="text" id="sp-ref" placeholder="e.g., Cash payment, GCash ref, etc." style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;box-sizing:border-box;">
            </div>

            <div class="modal-amount-display">
                <div class="mamount-label">Amount to Record</div>
                <div class="mamount-value">₱<span id="sp-amount">0</span></div>
                <div class="mamount-note" id="sp-amount-note">Reservation fee</div>
                <div class="mamount-note" id="sp-remaining-note" style="margin-top:4px;"></div>
            </div>

            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal('staff-payment-modal')">Cancel</button>
                <button class="btn-confirm" onclick="recordStaffPayment()">✅ Record Payment</button>
            </div>

            <input type="hidden" id="sp-booking-id" value="">
            <input type="hidden" id="sp-deposit-amount" value="0">
            <input type="hidden" id="sp-total-amount" value="0">
            <input type="hidden" id="sp-remaining-balance" value="0">
            <input type="hidden" id="sp-deposit-paid" value="0">
        </div>
    </div>
</div>

<script>
// ============================================================
// PAYMENT MODAL FUNCTIONS
// ============================================================
let spBookingId = 0;
let spReservationAmount = 0;
let spTotalAmount = 0;
let spRemainingBalance = 0;
let spDepositPaid = 0;
let spLoyaltyCount = 0;

function openPaymentModal(bookingId, bookingRef, total, reservation, clientName, remaining, depositPaid, loyaltyCount) {
    spBookingId = bookingId;
    spReservationAmount = reservation || 0;
    spTotalAmount = total || 0;
    spRemainingBalance = remaining || 0;
    spDepositPaid = depositPaid || 0;
    spLoyaltyCount = loyaltyCount || 0;

    document.getElementById('sp-booking-id').value = bookingId;
    document.getElementById('sp-booking-ref').textContent = bookingRef;
    document.getElementById('sp-client-name').textContent = clientName;
    document.getElementById('sp-total').textContent = '₱' + (total || 0).toLocaleString();
    document.getElementById('sp-deposit-amount-display').textContent = '₱' + (reservation || 0).toLocaleString();
    document.getElementById('sp-deposit-amount').value = reservation || 0;
    document.getElementById('sp-total-amount').value = total || 0;
    document.getElementById('sp-remaining-balance').value = remaining || 0;
    document.getElementById('sp-deposit-paid').value = depositPaid || 0;
    document.getElementById('sp-remaining').textContent = '₱' + (remaining || 0).toLocaleString();

    const loyaltyInfo = document.getElementById('sp-loyalty-info');
    if (spLoyaltyCount > 0) {
        loyaltyInfo.style.display = 'block';
        document.getElementById('sp-loyalty-count').textContent = spLoyaltyCount;

        const rewards = [];
        if (spLoyaltyCount >= 2) rewards.push('+5 MIN');
        if (spLoyaltyCount >= 4) rewards.push('+1 PRINT');
        if (spLoyaltyCount >= 7) rewards.push('+1 BACKDROP');
        if (spLoyaltyCount >= 10) rewards.push('50% OFF');

        document.getElementById('sp-loyalty-rewards').textContent = 
            rewards.length > 0 ? '✅ Entitled: ' + rewards.join(', ') : '';
    } else {
        loyaltyInfo.style.display = 'none';
    }

    if (bookingRef && bookingRef.startsWith('WALK')) {
        document.getElementById('sp-booking-type').textContent = '🚶 Walk-in Booking';
    } else {
        document.getElementById('sp-booking-type').textContent = '📅 Online Booking';
    }

    if ((remaining || 0) > 0) {
        document.getElementById('sp-remaining-hint').textContent = '⏳ Client still owes ₱' + (remaining || 0).toLocaleString();
    } else {
        document.getElementById('sp-remaining-hint').textContent = '✅ No remaining balance';
    }

    if (depositPaid == 1) {
        document.getElementById('sp-label-deposit').style.opacity = '0.5';
        document.getElementById('sp-label-deposit').style.cursor = 'not-allowed';
        document.getElementById('sp-label-deposit').classList.remove('active');
        document.querySelector('input[name="sp_payment_type"][value="FULL"]').checked = true;
        selectPaymentType('FULL');
    } else {
        document.getElementById('sp-label-deposit').style.opacity = '1';
        document.getElementById('sp-label-deposit').style.cursor = 'pointer';
        selectPaymentType('RESERVATION');
    }

    document.getElementById('staff-payment-modal').style.display = 'flex';
}

function selectPaymentType(type) {
    document.querySelectorAll('input[name="sp_payment_type"]').forEach(r => r.checked = false);
    document.querySelector(`input[name="sp_payment_type"][value="${type}"]`).checked = true;

    const dep = document.getElementById('sp-label-deposit');
    const full = document.getElementById('sp-label-full');
    if (dep && full) {
        dep.classList.toggle('active', type === 'RESERVATION');
        full.classList.toggle('active', type === 'FULL');
    }

    updateStaffPaymentAmount();
}

function updateStaffPaymentAmount() {
    const type = document.querySelector('input[name="sp_payment_type"]:checked').value;
    const reservation = parseFloat(document.getElementById('sp-deposit-amount').value) || 0;
    const total = parseFloat(document.getElementById('sp-total-amount').value) || 0;
    const remaining = parseFloat(document.getElementById('sp-remaining-balance').value) || 0;
    const depositPaid = parseInt(document.getElementById('sp-deposit-paid').value) || 0;

    let amount, note, remainingNote;
    let hint = document.getElementById('sp-remaining-hint');
    let remainingSection = document.getElementById('sp-remaining-section');

    if (type === 'FULL') {
        if (depositPaid == 1 && remaining > 0) {
            amount = remaining;
        } else if (depositPaid == 1 && remaining == 0) {
            amount = 0;
        } else {
            amount = total;
        }

        if (amount == 0) {
            note = '⚠️ Already fully paid!';
            remainingNote = '💡 No balance to pay.';
            if (hint) hint.textContent = '✅ Booking is already fully paid.';
        } else if (depositPaid == 1) {
            note = '💰 PAY REMAINING BALANCE';
            remainingNote = '🎉 Client will be FULLY PAID!';
            if (hint) hint.textContent = '✅ This will mark the booking as FULLY PAID!';
        } else {
            note = '💰 FULL PAYMENT';
            remainingNote = '🎉 Client will be FULLY PAID!';
            if (hint) hint.textContent = '✅ This will mark the booking as FULLY PAID!';
        }

        if (remainingSection) remainingSection.style.display = 'none';
    } else {
        if (depositPaid == 1) {
            amount = 0;
            note = '⚠️ Reservation already paid!';
            remainingNote = '💡 Please select FULL BALANCE instead.';
            if (hint) hint.textContent = '⚠️ Reservation already paid. Select FULL BALANCE.';
        } else {
            amount = reservation;
            note = '🎫 RESERVATION FEE';
            remainingNote = '⏳ Remaining balance: ₱' + (total - reservation).toLocaleString() + ' due on shoot day';
            if (hint) hint.textContent = '⏳ Client still owes ₱' + (total - reservation).toLocaleString();
        }

        if (remainingSection) remainingSection.style.display = 'block';
    }

    document.getElementById('sp-amount').textContent = amount.toLocaleString();
    document.getElementById('sp-amount-note').textContent = note;
    document.getElementById('sp-remaining-note').textContent = remainingNote;
}

function recordStaffPayment() {
    const bookingId = document.getElementById('sp-booking-id').value;
    const paymentType = document.querySelector('input[name="sp_payment_type"]:checked').value;
    const method = document.getElementById('sp-method').value;
    const ref = document.getElementById('sp-ref').value || 'On-site payment';
    const remaining = parseFloat(document.getElementById('sp-remaining-balance').value) || 0;
    const amount = parseFloat(document.getElementById('sp-amount').textContent.replace(/,/g, '')) || 0;

    if (!bookingId) { alert('❌ Booking ID missing!'); return; }
    if (amount <= 0) { alert('❌ No amount to pay!'); return; }

    let confirmMsg = 'Record this payment?\n\n';
    confirmMsg += 'Booking: ' + document.getElementById('sp-booking-ref').textContent + '\n';
    confirmMsg += 'Client: ' + document.getElementById('sp-client-name').textContent + '\n';
    confirmMsg += 'Type: ' + (paymentType === 'FULL' ? 'FULL BALANCE' : 'RESERVATION FEE') + '\n';
    confirmMsg += 'Amount: ₱' + document.getElementById('sp-amount').textContent + '\n';
    confirmMsg += 'Method: ' + method;

    if (spLoyaltyCount > 0) {
        confirmMsg += '\n\n🎫 Loyalty: ' + spLoyaltyCount + ' completed bookings';
    }

    if (paymentType === 'FULL' && remaining > 0) {
        confirmMsg += '\n\n⚠️ This will mark the booking as FULLY PAID!';
    }

    if (!confirm(confirmMsg)) return;

    const btn = document.querySelector('#staff-payment-modal .btn-confirm');
    const originalText = btn.textContent;
    btn.textContent = '⏳ Processing...';
    btn.disabled = true;

    fetch('pages/api/record-payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            booking_id: bookingId,
            payment_type: paymentType,
            method: method,
            ref: ref
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.textContent = originalText;
        btn.disabled = false;

        if (data.success) {
            let msg = '✅ Payment recorded successfully!\n';
            msg += 'Amount: ₱' + (data.amount || 0).toLocaleString() + '\n';
            if (data.fully_paid === 1) {
                msg += '🎉 Booking is now FULLY PAID!';
            } else {
                msg += '💰 Reservation recorded. Remaining: ₱' + (data.remaining || 0).toLocaleString();
            }
            alert(msg);
            closeModal('staff-payment-modal');
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        btn.textContent = originalText;
        btn.disabled = false;
        alert('❌ Error recording payment: ' + error.message);
    });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal('staff-payment-modal');
});
</script>
