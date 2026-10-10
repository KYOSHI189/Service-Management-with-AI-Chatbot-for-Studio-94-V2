<?php
requireRole('client');
$pdo = db();
$uid = $user['id'];

// ===== OPERATING HOURS =====
define('OPEN_HOUR_MINUTES', 10 * 60);
define('CLOSE_HOUR_MINUTES', 19 * 60);

// ===== CURRENT TIME (SERVER IS THE SOURCE OF TRUTH) =====
// config.php pins the timezone to Asia/Manila, so date() here is PHT.
// These two values are handed to the browser so the client-side checks
// agree with the validation that runs on submit — a client whose device
// clock or timezone differs still cannot book a past slot.
define('SERVER_TODAY',       date('Y-m-d'));
define('SERVER_NOW_MINUTES', ((int)date('H') * 60) + (int)date('i'));

// ===== RESERVATION FEE (FLAT) =====
define('RESERVATION_FEE', 100);

// ===== INLINE DURATION PARSER =====
if (!function_exists('extractHoursFromDuration')) {
    function extractHoursFromDuration($duration) {
        if ($duration === null || $duration === '') return 1.0;
        $d = strtolower(trim((string)$duration));
        if (strpos($d, 'half day') !== false || strpos($d, 'half-day') !== false) return 4.0;
        if (strpos($d, 'whole day') !== false || strpos($d, 'full day') !== false
            || strpos($d, 'whole-day') !== false || strpos($d, 'full-day') !== false) return 8.0;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:hour|hr)/', $d, $h)) {
            $hours = (float)$h[1];
            if (preg_match('/(\d+)\s*(?:min|minute)/', $d, $m)) {
                $hours += ((int)$m[1]) / 60.0;
            }
            return $hours;
        }
        if (preg_match('/(\d+)\s*(?:min|minute)/', $d, $m)) {
            return ((int)$m[1]) / 60.0;
        }
        if (preg_match('/(\d+(?:\.\d+)?)/', $d, $n)) {
            return (float)$n[1];
        }
        return 1.0;
    }
}

if (!function_exists('extractMinutesFromDuration')) {
    function extractMinutesFromDuration($duration) {
        return (int)round(extractHoursFromDuration($duration) * 60);
    }
}

if (!function_exists('checkBookingConflict')) {
    function checkBookingConflict($date, $time, $excludeId = null, $durationMinutes = 60) {
        $pdo = db();
        $newStart = strtotime("$date $time");
        if ($newStart === false) return [['error' => 'invalid_time']];
        $newEnd = $newStart + ($durationMinutes * 60);

        $sql = "SELECT b.id, b.booking_ref, b.time, b.status, b.duration_minutes,
                       b.notes, p.duration AS pkg_duration, p.name AS pkg_name
                FROM bookings b
                JOIN packages p ON b.package_id = p.id
                WHERE b.date = ?
                  AND b.status IN ('Approved (Unpaid)', 'Deposit Paid', 'Confirmed', 'In Progress', 'Completed')";
        $params = [$date];
        if ($excludeId) { $sql .= " AND b.id != ?"; $params[] = $excludeId; }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $conflicts = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!empty($row['duration_minutes'])) {
                $exMin = (int)$row['duration_minutes'];
            } else {
                $exMin = extractMinutesFromDuration($row['pkg_duration'] ?? '1 hour');
                if (!empty($row['notes']) && preg_match('/\+(\d+)\s*MIN bonus applied/i', $row['notes'], $m)) {
                    $exMin += (int)$m[1];
                }
            }
            $exStart = strtotime($date . ' ' . $row['time']);
            if ($exStart === false) continue;
            $exEnd = $exStart + ($exMin * 60);
            if ($newStart < $exEnd && $newEnd > $exStart) {
                $conflicts[] = $row;
            }
        }
        return $conflicts;
    }
}

// ============================================================
// ✅ FIXED: GET LOYALTY COUNT — DYNAMIC BASE SA COMPLETED BOOKINGS
// ============================================================
// Ang loyalty count ay hindi na kinukuha sa loyalty_cards.total_bookings.
// Sa halip, ito ay kinakalkula dynamically base sa ACTUAL COMPLETED bookings.
// Ito ay nagsisiguro na:
//   - Hindi madadagdagan ang count kapag nag-submit lang ng booking
//   - Hindi madadagdagan kapag na-cancel ang booking
//   - Madadagdagan lang kapag COMPLETED na ang session
// ============================================================
$loyaltyCount = getClientBookingCount($uid);

$loyaltyBonusMinutes = 0;
$loyaltyDiscountPct  = 0;
$loyaltyTierLabel    = 'New Client';

// Loyalty tiers (tama base sa system: 2, 4, 7, 10)
if ($loyaltyCount >= 10) {
    $loyaltyDiscountPct = 50;
    $loyaltyTierLabel   = '🏆 VIP (50% OFF)';
} elseif ($loyaltyCount >= 7) {
    $loyaltyBonusMinutes = 5;
    $loyaltyTierLabel    = '⭐ Loyalty Member (+1 BACKDROP)';
} elseif ($loyaltyCount >= 4) {
    $loyaltyBonusMinutes = 5;
    $loyaltyTierLabel    = '⭐ Loyalty Member (+1 PRINT OUT)';
} elseif ($loyaltyCount >= 2) {
    $loyaltyBonusMinutes = 5;
    $loyaltyTierLabel    = '⭐ Loyalty Member (+5 MIN)';
}

// ===== GET MAIN PACKAGES ONLY =====
$mainPackages = $pdo->query("
    SELECT * FROM packages 
    WHERE parent_id IS NULL AND is_active = 1 
    ORDER BY id
")->fetchAll();

foreach ($mainPackages as &$main) {
    $subs = $pdo->prepare("
        SELECT * FROM packages 
        WHERE parent_id = ? AND is_active = 1 
        ORDER BY price ASC
    ");
    $subs->execute([$main['id']]);
    $main['sub_packages'] = $subs->fetchAll();
}
unset($main);

// ===== HANDLE BOOKING SUBMISSION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $sub_package_id = cleanInt($_POST['sub_package_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $people = cleanInt($_POST['people'] ?? 1);
    $phone = trim($_POST['phone'] ?? $user['phone']);
    $notes = trim($_POST['notes'] ?? '');

    $pkgStmt = $pdo->prepare("SELECT * FROM packages WHERE id = ? AND is_active = 1");
    $pkgStmt->execute([$sub_package_id]);
    $subPackage = $pkgStmt->fetch();

    $errors = [];
    if (!$subPackage) {
        $errors[] = 'Invalid package selected. Please choose a package.';
    } else {
        $mainStmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
        $mainStmt->execute([$subPackage['parent_id']]);
        $mainPackage = $mainStmt->fetch();
    }
    if (!$sub_package_id) $errors[] = 'Please select a package.';
    if (!$date) $errors[] = 'Please select a date.';
    if (!$time) $errors[] = 'Please select a time.';

    // The regex alone would accept 2026-13-45, and the string comparison below
    // relies on a zero-padded valid date, so check the calendar as well.
    if ($date && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4)))) {
        $errors[] = 'Invalid date format. Please pick a date from the calendar.';
    } elseif ($date && $date < SERVER_TODAY) {
        $errors[] = 'That date has already passed (' . date('M j, Y', strtotime($date)) . '). Please choose today or a future date.';
    }

    if ($subPackage && $subPackage['max_pax'] && $people > $subPackage['max_pax']) {
        $errors[] = 'Maximum of ' . $subPackage['max_pax'] . ' people allowed for this package.';
    }

    $baseMinutes = extractMinutesFromDuration($subPackage['duration'] ?? '1 hour');
    $adjustedMinutes = $baseMinutes + $loyaltyBonusMinutes;

    if (empty($errors) && $date && $time) {
        $startMin = null;
        if (preg_match('/(\d+):(\d+)\s*(AM|PM)/i', $time, $m)) {
            $h = (int)$m[1];
            $mm = (int)$m[2];
            $ap = strtoupper($m[3]);
            if ($ap === 'PM' && $h !== 12) $h += 12;
            if ($ap === 'AM' && $h === 12) $h = 0;
            $startMin = ($h * 60) + $mm;
        }
        if ($startMin === null) {
            $errors[] = 'Invalid time format. Use: 10:30 AM';
        } else {
            $endMin = $startMin + $adjustedMinutes;

            if ($startMin < OPEN_HOUR_MINUTES) $errors[] = 'Studio opens at 10:00 AM.';
            if ($startMin >= CLOSE_HOUR_MINUTES) $errors[] = 'Studio closes at 7:00 PM.';
            if ($endMin > CLOSE_HOUR_MINUTES) $errors[] = 'Session would end past 7:00 PM. Please choose an earlier time.';

            // A slot that has already started today cannot be booked.
            // Compared against the server clock, not the browser's.
            if ($date === SERVER_TODAY && $startMin <= SERVER_NOW_MINUTES) {
                $errors[] = 'This time slot has already passed. Current time: ' . date('g:i A') . '. Please choose a later time.';
            }
        }
    }

    if (empty($errors)) {
        $conflicts = checkBookingConflict($date, $time, null, $adjustedMinutes);
        if (!empty($conflicts)) {
            $errors[] = 'Schedule conflict detected. That date and time is already booked. Please choose another time.';
        }
    }

    if (empty($errors)) {
        try {
            $ref = 'B-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $checkRef = $pdo->prepare("SELECT id FROM bookings WHERE booking_ref = ?");
            $checkRef->execute([$ref]);
            if ($checkRef->fetch()) {
                $ref = 'B-' . date('YmdHis') . '-' . rand(100, 999);
            }

            $basePrice = (float)$subPackage['price'];
            $finalPrice = $loyaltyDiscountPct > 0
                ? round($basePrice * (100 - $loyaltyDiscountPct) / 100, 2)
                : $basePrice;

            // ✅ FLAT ₱100 RESERVATION FEE (hindi percentage-based)
            $deposit = RESERVATION_FEE;
            $remaining = max(0, round($finalPrice - $deposit, 2));

            $loyaltyNote = '';
            if ($loyaltyBonusMinutes > 0) {
                $loyaltyNote .= "\n[LOYALTY] +{$loyaltyBonusMinutes} MIN bonus applied (base: {$baseMinutes} min → adjusted: {$adjustedMinutes} min)";
            }
            if ($loyaltyDiscountPct > 0) {
                $loyaltyNote .= "\n[LOYALTY] {$loyaltyDiscountPct}% OFF applied (original: ₱{$basePrice} → final: ₱{$finalPrice})";
            }
            $finalNotes = trim($notes . $loyaltyNote);

            $ins = $pdo->prepare("INSERT INTO bookings (
                booking_ref, user_id, package_id, duration_minutes, date, time, people, phone, notes,
                type, status, package_price, deposit_amount, remaining_balance, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'online', 'Awaiting Approval', ?, ?, ?, NOW())");

            $ins->execute([
                $ref, $uid, $sub_package_id, $adjustedMinutes,
                $date, $time, $people, $phone, $finalNotes,
                $finalPrice, $deposit, $remaining
            ]);

            $bookingId = $pdo->lastInsertId();

            $payRef = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $checkPayRef = $pdo->prepare("SELECT id FROM payments WHERE payment_ref = ?");
            $checkPayRef->execute([$payRef]);
            if ($checkPayRef->fetch()) {
                $payRef = 'PAY-' . date('ymd') . '-' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            }

            // ✅ RESERVATION type + FLAT ₱100 amount
            $pdo->prepare("INSERT INTO payments (payment_ref, booking_id, user_id, amount, type, status, created_at)
                VALUES (?, ?, ?, ?, 'RESERVATION', 'UNPAID', NOW())")
                ->execute([$payRef, $bookingId, $uid, RESERVATION_FEE]);

            // ============================================================
            // ✅ FIXED: HUWAG NANG DAGDAGAN ANG LOYALTY COUNT DITO.
            // ============================================================
            // Ang loyalty count ay DYNAMIC na kinakalkula base sa
            // COMPLETED bookings lang — hindi sa pag-submit ng booking.
            // 
            // Ang loyalty_cards row ay gagawin lang (kung wala pa) para
            // may card_number reference. Ang total_bookings column ay
            // hindi na ginagamit — puro dynamic count na lang.
            // ============================================================
            $lcCheck = $pdo->prepare("SELECT id FROM loyalty_cards WHERE user_id=?");
            $lcCheck->execute([$uid]);
            if (!$lcCheck->fetch()) {
                $cardNo = 'LC-' . date('Y') . '-' . str_pad($uid, 3, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO loyalty_cards (user_id, card_number, total_bookings, status) VALUES (?, ?, 0, 'Active')")
                    ->execute([$uid, $cardNo]);
            }
            // ❌ WALA NANG UPDATE — hindi na dinadagdagan ang total_bookings

            $mainName = $mainPackage ? $mainPackage['name'] : 'Main Package';
            $notifMsg = $user['name'] . ' requested ' . $subPackage['name'] . ' (' . $mainName . ') on ' . $date . ' at ' . $time;
            if ($loyaltyBonusMinutes > 0) $notifMsg .= ' — 🎁 +' . $loyaltyBonusMinutes . ' MIN loyalty bonus';
            if ($loyaltyDiscountPct > 0)  $notifMsg .= ' — 🎁 ' . $loyaltyDiscountPct . '% loyalty discount';

            addNotificationByRole('staff', 'New Booking Request', $notifMsg, 'booking', '📅');

            setFlash('success', 'Booking request submitted! We will review and confirm your session.');
            $redirectUrl = APP_URL . '/index.php?page=bookings';
            header('Location: ' . $redirectUrl);
            echo "<script>window.location.href = '" . $redirectUrl . "';</script>";
            exit;
        } catch (Throwable $e) {
            error_log('[STUDIO94] Booking creation error: ' . $e->getMessage());
            $errors[] = 'Could not process booking: ' . $e->getMessage();
        }
    }
}

// ===== GET ALL BOOKINGS =====
$bookedSlots = $pdo->query("
    SELECT b.date, b.time, b.status, b.duration_minutes, b.notes,
           p.duration AS pkg_duration
    FROM bookings b 
    JOIN packages p ON b.package_id = p.id 
    WHERE b.status NOT IN ('Cancelled', 'Rejected') 
    ORDER BY b.date ASC, b.time ASC
")->fetchAll();

foreach ($bookedSlots as &$bs) {
    if (!empty($bs['duration_minutes'])) {
        $bs['effective_duration_minutes'] = (int)$bs['duration_minutes'];
    } else {
        $m = extractMinutesFromDuration($bs['pkg_duration'] ?? '1 hour');
        if (!empty($bs['notes']) && preg_match('/\+(\d+)\s*MIN bonus applied/i', $bs['notes'], $mm)) {
            $m += (int)$mm[1];
        }
        $bs['effective_duration_minutes'] = $m;
    }
}
unset($bs);

$errors = $errors ?? [];
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

/* ALERT */
.alert {
    display: flex;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 14px;
    align-items: flex-start;
}
.alert ul { padding-left: 16px; }
.alert li { margin: 2px 0; }

/* LOYALTY BANNER */
.loyalty-banner {
    padding: 14px 16px;
    background: linear-gradient(135deg, #0A0A0A, #2A2A2A);
    color: #fff;
    border-radius: 12px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-sizing: border-box;
}
.loyalty-banner .loyalty-icon { font-size: 28px; flex-shrink: 0; }
.loyalty-banner .loyalty-info {
    flex: 1 1 100%;
    min-width: 0;
    order: 3;
}
.loyalty-banner .loyalty-tier {
    font-weight: 800;
    font-size: 14px;
    letter-spacing: 0.3px;
    word-break: break-word;
}
.loyalty-banner .loyalty-desc {
    font-size: 12px;
    opacity: 0.85;
    margin-top: 4px;
    line-height: 1.4;
}
.loyalty-banner .loyalty-badge {
    padding: 6px 14px;
    background: rgba(255,255,255,0.15);
    border-radius: 50px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    white-space: nowrap;
    margin-left: auto;
}

/* SECTION TITLE */
.section-title {
    font-size: 15px;
    margin-bottom: 12px;
    line-height: 1.3;
    word-break: break-word;
}

/* MAIN PACKAGES GRID */
.backdrop-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}
.backdrop-card {
    cursor: pointer;
    border-radius: 14px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid var(--border);
    background: #fff;
    box-sizing: border-box;
    min-width: 0;
}
.backdrop-card .backdrop-color {
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
}
.backdrop-card .backdrop-info { padding: 14px; }
.backdrop-card .backdrop-info h4 {
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 4px;
    word-break: break-word;
}
.backdrop-card .backdrop-info p {
    font-size: 12px;
    color: var(--muted);
    margin: 0 0 10px;
    line-height: 1.4;
    word-break: break-word;
}
.backdrop-card .backdrop-info .feat-list {
    font-size: 12px;
    color: var(--dark2);
    margin-bottom: 12px;
}

/* SUB-PACKAGES GRID */
#sub-packages-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    align-items: stretch;
}

/* PACKAGE CARD */
.package-card {
    border: 2px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: 0.3s;
    background: white;
    display: flex;
    flex-direction: column;
    min-height: auto;
    position: relative;
    box-sizing: border-box;
    min-width: 0;
}
.package-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.package-card .popular-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--dark);
    color: white;
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 9px;
    font-weight: 700;
    z-index: 5;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.package-card .card-image {
    height: 180px;
    position: relative;
    flex-shrink: 0;
    overflow: hidden;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}
.package-card .card-image .package-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
    padding: 4px;
}
.package-card .card-image .overlay {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 8px 12px;
    background: linear-gradient(transparent, rgba(0,0,0,0.75));
    pointer-events: none;
}
.package-card .card-image .overlay .name {
    color: white;
    font-weight: 700;
    font-size: 14px;
    text-shadow: 0 1px 4px rgba(0,0,0,0.5);
    word-break: break-word;
}
.package-card .card-image .overlay .duration {
    color: rgba(255,255,255,0.95);
    font-size: 11px;
    text-shadow: 0 1px 3px rgba(0,0,0,0.4);
}
.package-card .card-body {
    padding: 12px 14px 14px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.package-card .card-body .price {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}
.package-card .card-body .max-pax {
    font-size: 11px;
    color: var(--muted);
}
.package-card .card-body .features {
    flex: 0 0 auto;
    margin: 4px 0 8px;
    padding: 0;
    list-style: none;
}
.package-card .card-body .features li {
    padding: 2px 0;
    color: var(--dark2);
    font-size: 11px;
    display: flex;
    align-items: flex-start;
    gap: 4px;
    line-height: 1.35;
}
.package-card .card-body .features li::before {
    content: "✓";
    color: var(--green);
    font-weight: 700;
    flex-shrink: 0;
}
.package-card .card-body .print-inclusions {
    margin-top: auto;
    margin-bottom: 8px;
    padding: 5px 10px;
    background: var(--amber-bg);
    border-radius: 6px;
    font-size: 10px;
    color: var(--amber-text);
    border: 1px solid var(--amber);
    flex-shrink: 0;
}
.package-card .card-body .btn-book {
    margin-top: 0;
    width: 100%;
    padding: 10px;
    font-size: 13px;
    font-weight: 600;
    background: var(--dark);
    color: white;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    transition: 0.2s;
    flex-shrink: 0;
    letter-spacing: 0.5px;
}
.package-card .card-body .btn-book:hover {
    background: #1A1A1A;
    transform: scale(1.02);
}

/* BOOKING FORM SECTION */
#booking-form-section .booking-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

/* CALENDAR */
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
}
.calendar-day-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--muted);
    text-align: center;
    padding: 4px 0;
}
.calendar-date {
    text-align: center;
    padding: 6px 0;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: 0.2s;
    font-weight: 600;
}
.calendar-date.date-available {
    background: var(--green-bg);
    color: #1B5E20;
    border: 2px solid #2E7D32;
}
.calendar-date.date-available:hover {
    background: #2E7D32;
    color: white;
    transform: scale(1.08);
}
.calendar-date.date-booked {
    background: var(--red-bg);
    color: #8E0000;
    border: 2px solid #C62828;
    cursor: not-allowed;
    opacity: 0.75;
}
.calendar-date.date-past {
    background: #EEEEEE;
    color: #616161;
    border: 2px solid #BDBDBD;
    cursor: not-allowed;
    opacity: 0.6;
}

/* LEGEND */
.calendar-legend {
    margin-top: 14px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
    font-size: 11px;
}
.calendar-legend .legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px;
    border-radius: 6px;
}

/* FORM GROUP */
.form-group { margin-bottom: 12px; }
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
}
.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid var(--border);
    font-size: 14px;
    box-sizing: border-box;
    font-family: inherit;
}
.form-group textarea { min-height: 60px; resize: vertical; }

/* BOOKED SLOTS */
.booked-slots-panel {
    padding: 12px;
    background: var(--bg-soft);
    border-radius: 12px;
    position: static;
}
.booked-slots-panel .panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
}
.booked-slots-panel .panel-header > div:first-child {
    font-size: 13px;
    font-weight: 700;
}
.booked-slots-panel .panel-header .date-controls {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}
.booked-slots-panel .panel-header input[type="date"] {
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid var(--border);
    font-size: 12px;
    width: 130px;
    max-width: 100%;
}
.booked-slots-panel .panel-header button {
    padding: 2px 8px;
    font-size: 11px;
}

#booked-slots-display {
    background: white;
    border-radius: 8px;
    padding: 12px;
    min-height: 200px;
    border: 1px solid var(--border);
    max-height: 300px;
    overflow-y: auto;
}

#booking-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    margin-top: 10px;
}
#booking-stats > div {
    background: white;
    padding: 6px 8px;
    border-radius: 6px;
    border: 1px solid var(--border);
    text-align: center;
    min-width: 0;
}
#booking-stats > div .stat-label {
    font-size: 9px;
    color: var(--muted);
    white-space: nowrap;
}
#booking-stats > div .stat-number {
    font-size: 15px;
    font-weight: 700;
}

/* SLOT COLOR LEGEND */
.slot-color-legend {
    margin-top: 10px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    border: 1px solid var(--border);
}
.slot-color-legend .legend-title {
    font-size: 11px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 6px;
}
.slot-color-legend .legend-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    padding: 3px 0;
}
.slot-color-legend .legend-swatch {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    flex-shrink: 0;
}

/* BOOKING SUMMARY */
#booking-summary {
    margin-top: 10px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    border: 1px solid var(--border);
    display: none;
}
#booking-summary .summary-title {
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 4px;
    color: var(--dark);
}

/* FORM ACTIONS */
.form-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.form-actions button { flex: 1 1 auto; min-width: 120px; }

/* ============================================================
   SMALL PHONE
   ============================================================ */
@media (min-width: 380px) {
    .page-banner h2 { font-size: 19px; }
    .backdrop-card .backdrop-color { height: 130px; font-size: 52px; }
    .calendar-date { padding: 7px 0; font-size: 13px; }
    .calendar-legend { grid-template-columns: repeat(3, 1fr); }
    .calendar-legend .legend-item { padding: 5px; font-size: 10px; }
    .package-card .card-image { height: 200px; }
}

/* ============================================================
   LARGE PHONE / SMALL TABLET
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

    .loyalty-banner {
        padding: 16px 20px;
        border-radius: 14px;
        flex-wrap: nowrap;
    }
    .loyalty-banner .loyalty-icon { font-size: 32px; }
    .loyalty-banner .loyalty-info { order: 0; flex: 1; }
    .loyalty-banner .loyalty-tier { font-size: 15px; }

    .backdrop-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .backdrop-card .backdrop-color { height: 140px; font-size: 56px; }
    .backdrop-card .backdrop-info h4 { font-size: 16px; }

    #sub-packages-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }

    .package-card { min-height: 540px; }
    .package-card .card-image { height: 190px; }

    .calendar-date { font-size: 13px; }
    .booked-slots-panel { padding: 14px; }
}

/* ============================================================
   TABLET
   ============================================================ */
@media (min-width: 768px) {
    .page-banner { padding: 24px 28px; border-radius: 16px; gap: 20px; }
    .page-banner h2 { font-size: 24px; }
    .page-banner p { font-size: 13px; }
    .page-banner-art { font-size: 48px; }

    .loyalty-banner { padding: 18px 22px; }
    .loyalty-banner .loyalty-tier { font-size: 16px; }
    .loyalty-banner .loyalty-desc { font-size: 13px; }

    .section-title { font-size: 17px; }

    .backdrop-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .backdrop-card .backdrop-color { height: 150px; font-size: 60px; }

    #sub-packages-grid { grid-template-columns: repeat(2, 1fr); gap: 18px; }

    .package-card .card-image { height: 200px; }
    .package-card .card-body .price { font-size: 22px; }

    #booking-form-section .booking-form-grid {
        grid-template-columns: 1fr 1.2fr;
        gap: 20px;
    }
    .booked-slots-panel { position: sticky; top: 20px; }

    .form-group input,
    .form-group textarea,
    .form-group select { font-size: 14px; padding: 10px 14px; }

    .calendar-grid { gap: 4px; }
    .calendar-date { padding: 8px 0; font-size: 14px; }
    .calendar-day-label { font-size: 11px; }

    #booked-slots-display { min-height: 250px; max-height: 350px; }
    #booking-stats > div .stat-number { font-size: 16px; }
    #booking-stats > div .stat-label { font-size: 10px; }
}

/* ============================================================
   LAPTOP / DESKTOP
   ============================================================ */
@media (min-width: 1024px) {
    .page-banner { padding: 28px 32px; }
    .page-banner h2 { font-size: 26px; }
    .page-banner-art { font-size: 52px; }

    .loyalty-banner { padding: 16px 20px; }
    .loyalty-banner .loyalty-icon { font-size: 36px; }

    .backdrop-grid { grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .backdrop-card .backdrop-color { height: 140px; font-size: 56px; }

    #sub-packages-grid {
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
    }

    .package-card { min-height: 560px; }
    .package-card .card-image { height: 200px; }
}

/* ============================================================
   LARGE DESKTOP
   ============================================================ */
@media (min-width: 1440px) {
    .page-banner { padding: 32px 40px; border-radius: 18px; }
    .page-banner h2 { font-size: 30px; }
    .page-banner p { font-size: 14px; }
    .page-banner-art { font-size: 64px; }

    .backdrop-grid { gap: 22px; }
    .backdrop-card .backdrop-color { height: 160px; font-size: 64px; }

    #sub-packages-grid { gap: 20px; }
    .package-card { min-height: 580px; }
    .package-card .card-image { height: 220px; }
    .package-card .card-body .price { font-size: 24px; }

    #booking-form-section .booking-form-grid { gap: 24px; }
}

/* ============================================================
   ULTRAWIDE
   ============================================================ */
@media (min-width: 1920px) {
    .page-banner { padding: 36px 48px; }
    .page-banner h2 { font-size: 34px; }

    .backdrop-grid { gap: 24px; }
    .package-card { min-height: 600px; }
    .package-card .card-image { height: 240px; }
}

/* ============================================================
   LANDSCAPE MOBILE
   ============================================================ */
@media (max-height: 500px) and (orientation: landscape) {
    .page-banner { padding: 12px 16px; }
    .page-banner h2 { font-size: 18px; }
    .page-banner-art { font-size: 32px; }
    .calendar-date { padding: 4px 0; font-size: 11px; }
}

/* ============================================================
   PRINT
   ============================================================ */
@media print {
    .page-banner-art,
    .btn-book,
    .btn-primary,
    .btn-ghost { display: none !important; }
    .package-card { break-inside: avoid; }
}
</style>

<!-- ============================================================ -->
<!-- PAGE BANNER                                                   -->
<!-- ============================================================ -->
<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">Choose Your Package</div>
    <h2><strong>Book a Session</strong></h2>
    <p>Select from our professional photography packages. Studio hours: 10:00 AM – 7:00 PM.</p>
  </div>
  <div class="page-banner-art">📅</div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <span class="alert-icon">❌</span>
    <ul style="margin:0;padding-left:16px;">
      <?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- LOYALTY BANNER                                                -->
<!-- ============================================================ -->
<?php if ($loyaltyCount > 0): ?>
<div class="loyalty-banner">
  <div class="loyalty-icon">🎁</div>
  <div class="loyalty-badge">🎫 <?= $loyaltyCount ?> COMPLETED</div>
  <div class="loyalty-info">
    <div class="loyalty-tier"><?= clean($loyaltyTierLabel) ?></div>
    <div class="loyalty-desc">
      <?= $loyaltyCount ?> completed booking<?= $loyaltyCount > 1 ? 's' : '' ?> · 
      <?php if ($loyaltyBonusMinutes > 0): ?>
        <strong style="color:#FFD700;">+<?= $loyaltyBonusMinutes ?> MIN FREE</strong> added to your session!
      <?php elseif ($loyaltyDiscountPct > 0): ?>
        <strong style="color:#FFD700;"><?= $loyaltyDiscountPct ?>% OFF</strong> applied automatically!
      <?php else: ?>
        Thank you for being a loyal client!
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- STEP 1: MAIN PACKAGES                                         -->
<!-- ============================================================ -->
<div id="main-packages-section">
  <h3 class="section-title"><strong>📸 Choose Your Package Type</strong></h3>
  <div class="backdrop-grid">
    <?php 
    $icons = [
        'Self-Shoot' => '🤳',
        'Creative Shoot' => '🎨',
        'Family' => '👨‍👩‍👧',
        'Studio Rental' => '🏢'
    ];
    $colors = [
        'Self-Shoot' => '#D4A0A0',
        'Creative Shoot' => '#D4C4A0',
        'Family' => '#A0C4A0',
        'Studio Rental' => '#B0C4DE'
    ];
    
    foreach ($mainPackages as $main): 
      $icon = $icons[$main['name']] ?? '📷';
      $color = $colors[$main['name']] ?? '#B0C4DE';
    ?>
    <div class="backdrop-card"
         onclick="showSubPackages(<?= $main['id'] ?>, '<?= clean($main['name']) ?>')"
         onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 8px 30px rgba(0,0,0,0.12)'"
         onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'">
      <div class="backdrop-color" style="background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc);">
        <?= $icon ?>
      </div>
      <div class="backdrop-info">
        <h4><?= clean(strtoupper($main['name'])) ?></h4>
        <p><?= clean($main['description']) ?></p>
        <div class="feat-list">
          <?php 
          $feats = json_decode($main['features'] ?? '[]', true);
          if (is_array($feats)) {
              foreach (array_slice($feats, 0, 2) as $f): 
          ?>
            <div style="padding:2px 0;">✓ <?= clean($f) ?></div>
          <?php 
              endforeach;
          } else {
              echo '<div style="padding:2px 0;color:var(--muted);">No features listed</div>';
          }
          ?>
        </div>
        <button class="btn-black" style="width:100%;padding:10px;font-weight:600;letter-spacing:0.5px;font-size:13px;"
                onclick="event.stopPropagation(); showSubPackages(<?= $main['id'] ?>, '<?= clean($main['name']) ?>')">
          👁️ VIEW PACKAGES
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============================================================ -->
<!-- STEP 2: SUB-PACKAGES                                          -->
<!-- ============================================================ -->
<div id="sub-packages-section" style="display:none;margin-top:20px;">
  <div class="card">
    <div class="section-header">
      <h3 id="sub-packages-title"><strong>Select Your Package</strong></h3>
      <button class="btn-ghost btn-sm" onclick="backToMain()">← Back to Main</button>
    </div>
    <div id="sub-packages-grid"></div>
  </div>
</div>

<!-- ============================================================ -->
<!-- STEP 3: BOOKING FORM                                          -->
<!-- ============================================================ -->
<div id="booking-form-section" style="<?= !empty($errors) ? 'display:block;' : 'display:none;' ?>margin-top:20px;">
  <div class="card">
    <div class="section-header">
      <h3><strong>Complete Your Booking</strong></h3>
      <button class="btn-ghost btn-sm" onclick="clearBooking()">← Change Package</button>
    </div>

    <div class="booking-form-grid">
      
      <!-- LEFT: Calendar & Form -->
      <div>
        <div style="padding:12px;background:var(--bg-soft);border-radius:var(--radius-sm);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:8px;flex-wrap:wrap;">
            <strong id="cal-month-label" style="font-size:14px;"><?= date('F Y') ?></strong>
            <div style="display:flex;gap:6px;">
              <button class="btn-ghost btn-sm" type="button" onclick="changeCalMonth(-1)">❮</button>
              <button class="btn-ghost btn-sm" type="button" onclick="changeCalMonth(1)">❯</button>
            </div>
          </div>
          <div id="cal-grid" class="calendar-grid"></div>
          
          <div class="calendar-legend">
            <div class="legend-item" style="background:var(--green-bg);border:2px solid #2E7D32;">
              <div style="width:14px;height:14px;border-radius:3px;background:#2E7D32;flex-shrink:0;"></div>
              <strong style="color:#1B5E20;">Available</strong>
            </div>
            <div class="legend-item" style="background:var(--red-bg);border:2px solid #C62828;">
              <div style="width:14px;height:14px;border-radius:3px;background:#C62828;flex-shrink:0;"></div>
              <strong style="color:#8E0000;">Booked</strong>
            </div>
            <div class="legend-item" style="background:#EEEEEE;border:2px solid #9E9E9E;">
              <div style="width:14px;height:14px;border-radius:3px;background:#757575;flex-shrink:0;"></div>
              <strong style="color:#424242;">Past</strong>
            </div>
          </div>
        </div>

        <div style="padding:12px;background:var(--bg-soft);border-radius:var(--radius-sm);margin-top:12px;">
          <form method="POST" id="booking-form" action="<?= APP_URL ?>/index.php?page=booking">
            <?= csrfField() ?>
            <input type="hidden" name="sub_package_id" id="form-pkg-id">

            <div style="background:white;padding:12px;border-radius:8px;margin-bottom:16px;border:1px solid var(--border);">
              <div style="font-size:11px;color:var(--muted);">Selected Package</div>
              <div style="font-weight:700;font-size:15px;word-break:break-word;" id="form-pkg-display">Select a package</div>
              <div style="font-size:13px;color:var(--dark2);" id="form-pkg-price">₱0</div>
              <div style="font-size:12px;color:var(--muted);margin-top:4px;line-height:1.4;" id="form-pkg-duration"></div>
              <?php if ($loyaltyBonusMinutes > 0): ?>
                <div id="loyalty-bonus-display" style="margin-top:6px;padding:6px 10px;background:#FFF8E1;border-left:3px solid #F59E0B;border-radius:4px;font-size:11px;color:#92400E;font-weight:600;">
                  🎁 +<?= $loyaltyBonusMinutes ?> MIN loyalty bonus will be added
                </div>
              <?php endif; ?>
              <?php if ($loyaltyDiscountPct > 0): ?>
                <div id="loyalty-discount-display" style="margin-top:6px;padding:6px 10px;background:#FFF8E1;border-left:3px solid #F59E0B;border-radius:4px;font-size:11px;color:#92400E;font-weight:600;">
                  🎁 <?= $loyaltyDiscountPct ?>% loyalty discount will be applied
                </div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label><strong>📅 Date</strong></label>
              <input type="date" name="date" id="form-date" required min="<?= date('Y-m-d') ?>" style="background:white;font-weight:600;" onchange="onDateSelect(this.value)">
            </div>

            <div class="form-group">
              <label><strong>⏰ Select Time</strong> <span style="font-size:11px;color:var(--muted);font-weight:400;">(10:00 AM – 7:00 PM)</span></label>
              
              <div style="font-size:11px;color:var(--dark2);margin-bottom:8px;padding:8px 10px;background:var(--bg-soft);border-radius:6px;border-left:3px solid #6C63FF;line-height:1.4;">
                💡 <strong>Conflict Prevention:</strong> The system automatically checks for schedule conflicts. Slots with confirmed bookings cannot be selected.
              </div>
              
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="text" name="time" id="form-time" required placeholder="e.g., 10:30 AM" style="background:white;font-weight:600;flex:1 1 180px;min-width:0;text-transform:uppercase;" oninput="checkTimeAvailability(this.value)">
                <button type="button" class="btn-ghost btn-sm" onclick="showQuickTimes()" style="white-space:nowrap;">Quick Times ▼</button>
              </div>
              
              <div id="booked-times-display" style="margin-top:8px;padding:10px;background:var(--bg-soft);border-radius:6px;border:1px solid var(--border);display:none;">
                <div style="font-size:11px;font-weight:600;color:var(--dark);margin-bottom:6px;">
                  📋 Existing Bookings
                </div>
                <div id="booked-times-list" style="display:flex;flex-wrap:wrap;gap:4px;"></div>
              </div>
              
              <div id="quick-times-dropdown" style="display:none;margin-top:6px;background:white;border:1px solid var(--border);border-radius:6px;padding:8px;max-height:150px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
              <div id="time-status" style="margin-top:6px;font-size:13px;"></div>
            </div>

            <div class="form-group">
              <label><strong>👥 Number of People</strong></label>
              <input type="number" name="people" id="form-people" min="1" max="20" value="1">
            </div>

            <div class="form-group">
              <label><strong>📱 Contact Number</strong></label>
              <input type="tel" name="phone" value="<?= clean($user['phone']) ?>" placeholder="09XX XXX XXXX" required>
            </div>

            <div class="form-group">
              <label><strong>📝 Special Requests (optional)</strong></label>
              <textarea name="notes" placeholder="Any specific requirements…"></textarea>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary" style="flex:2 1 180px;" id="submit-btn" disabled>Confirm Booking</button>
              <button type="button" class="btn-ghost" style="flex:1 1 120px;" onclick="clearBooking()">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <!-- RIGHT: Booked Slots Display -->
      <div>
        <div class="booked-slots-panel">
          <div class="panel-header">
            <div>📋 Booked Slots</div>
            <div class="date-controls">
              <button class="btn-ghost btn-sm" onclick="changeViewDate(-1)">◀</button>
              <input type="date" id="view-date" value="<?= date('Y-m-d') ?>" onchange="onViewDateChange(this.value)">
              <button class="btn-ghost btn-sm" onclick="changeViewDate(1)">▶</button>
            </div>
          </div>

          <div id="booked-slots-display">
            <div style="text-align:center;color:var(--muted);padding:30px;">
              <div style="font-size:40px;margin-bottom:8px;">📭</div>
              <div style="font-weight:500;">Select a date to view booked slots</div>
              <div style="font-size:12px;margin-top:4px;">Click on a date in the calendar</div>
            </div>
          </div>

          <div id="booking-stats">
            <div>
              <div class="stat-label">Total</div>
              <div class="stat-number" id="stat-total">0</div>
            </div>
            <div>
              <div class="stat-label">Booked</div>
              <div class="stat-number" style="color:var(--red-text);" id="stat-booked">0</div>
            </div>
            <div>
              <div class="stat-label">Available</div>
              <div class="stat-number" style="color:var(--green-text);" id="stat-available">0</div>
            </div>
          </div>

          <div class="slot-color-legend">
            <div class="legend-title">🎨 Slot Color Legend</div>
            <div class="legend-row">
              <div class="legend-swatch" style="background:#2E7D32;border:2px solid #1B5E20;"></div>
              <span><strong style="color:#1B5E20;">Available</strong> — Pwedeng i-book</span>
            </div>
            <div class="legend-row">
              <div class="legend-swatch" style="background:#F9A825;border:2px solid #E65100;"></div>
              <span><strong style="color:#E65100;">Pending</strong> — Naghihintay ng approval</span>
            </div>
            <div class="legend-row">
              <div class="legend-swatch" style="background:#C62828;border:2px solid #8E0000;"></div>
              <span><strong style="color:#8E0000;">Confirmed</strong> — Hindi na pwedeng i-book</span>
            </div>
            <div class="legend-row">
              <div class="legend-swatch" style="background:#EEEEEE;border:2px solid #9E9E9E;"></div>
              <span><strong style="color:#616161;">Past</strong> — Naka-pass na, hindi na pwedeng i-book</span>
            </div>
          </div>

          <div id="booking-summary">
            <div class="summary-title">📌 Your Booking</div>
            <div id="summary-details" style="font-size:12px;"></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
const OPEN_HOUR_MINUTES  = 10 * 60;
const CLOSE_HOUR_MINUTES = 19 * 60;
const SLOT_STEP_MINUTES  = 15;

const LOYALTY_BONUS_MINUTES = <?= (int)$loyaltyBonusMinutes ?>;
const LOYALTY_DISCOUNT_PCT  = <?= (int)$loyaltyDiscountPct ?>;
const LOYALTY_COUNT         = <?= (int)$loyaltyCount ?>;

const mainPackages = <?= json_encode($mainPackages) ?>;
const bookedSlots = <?= json_encode($bookedSlots) ?>;
let calYear = new Date().getFullYear();
let calMonth = new Date().getMonth();
let selectedPackageDuration = '';
let selectedPackageBaseMinutes = 0;
let selectedSubPackageId = 0;
let selectedTime = '';

// ✅ NEW: Server clock (PHT) is the source of truth for "is this slot past?"
const SERVER_TODAY       = <?= json_encode(SERVER_TODAY) ?>;
const SERVER_NOW_MINUTES = <?= (int)SERVER_NOW_MINUTES ?>;
const PAGE_LOADED_AT     = Date.now();

// "Now" is anchored to the server clock and advanced by elapsed time here,
// so a wrong device clock or timezone can't unlock a past slot in the UI.
function getCurrentTimeInMinutes() {
    return SERVER_NOW_MINUTES + Math.floor((Date.now() - PAGE_LOADED_AT) / 60000);
}

// ✅ Check if a date string is today
function isToday(dateStr) {
    if (!dateStr) return false;
    return dateStr === SERVER_TODAY;
}

// A slot is "past" once its start time has arrived on the current day.
function isPastSlot(dateStr, startMinutes) {
    return isToday(dateStr) && startMinutes <= getCurrentTimeInMinutes();
}

// ISO date string for a JS Date, without the toISOString() UTC shift
// (which returns yesterday for anything before 8:00 AM PHT).
function toDateStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function todayStr() {
    return SERVER_TODAY;
}

function parseDurationHours(duration) {
    if (!duration) return 1;
    const d = String(duration).toLowerCase().trim();
    if (d.includes('half day') || d.includes('half-day')) return 4;
    if (d.includes('whole day') || d.includes('full day')
        || d.includes('whole-day') || d.includes('full-day')) return 8;
    const hMatch = d.match(/(\d+(?:\.\d+)?)\s*(?:hour|hr)/);
    if (hMatch) {
        let hrs = parseFloat(hMatch[1]);
        const mMatch = d.match(/(\d+)\s*(?:min|minute)/);
        if (mMatch) hrs += parseInt(mMatch[1]) / 60;
        return hrs;
    }
    const mMatch = d.match(/(\d+)\s*(?:min|minute)/);
    if (mMatch) return parseInt(mMatch[1]) / 60;
    const nMatch = d.match(/(\d+(?:\.\d+)?)/);
    if (nMatch) return parseFloat(nMatch[1]);
    return 1;
}

function getBaseDurationInMinutes(duration) {
    return Math.round(parseDurationHours(duration) * 60);
}

function getDurationInMinutes(duration) {
    return getBaseDurationInMinutes(duration) + LOYALTY_BONUS_MINUTES;
}

function parseTime(timeStr) {
    if (!timeStr) return 0;
    const parts = timeStr.match(/(\d+):(\d+)\s*(AM|PM)/i);
    if (!parts) return 0;
    let hours = parseInt(parts[1]);
    const minutes = parseInt(parts[2]);
    const ampm = parts[3].toUpperCase();
    if (ampm === 'PM' && hours !== 12) hours += 12;
    if (ampm === 'AM' && hours === 12) hours = 0;
    return hours * 60 + minutes;
}

function formatTime(minutes) {
    if (minutes < 0 || minutes >= 1440) return 'Invalid';
    const hours = Math.floor(minutes / 60);
    const mins = Math.floor(minutes % 60);
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours > 12 ? hours - 12 : (hours === 0 ? 12 : hours);
    const displayMin = String(mins).padStart(2, '0');
    return `${displayHour}:${displayMin} ${ampm}`;
}

function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return months[parseInt(parts[1]) - 1] + ' ' + parseInt(parts[2]) + ', ' + parts[0];
}

function getBookedSlotsForDate(date) {
    const booked = [];
    const bookings = bookedSlots.filter(b => b.date === date);

    bookings.forEach(booking => {
        const startMinutes = parseTime(booking.time);
        const durationMinutes = parseInt(booking.effective_duration_minutes)
            || getBaseDurationInMinutes(booking.pkg_duration || '1 hour');
        const endMinutes = startMinutes + durationMinutes;

        booked.push({
            start: booking.time,
            end: formatTime(endMinutes),
            startMinutes: startMinutes,
            endMinutes: endMinutes,
            duration: booking.pkg_duration,
            durationMinutes: durationMinutes,
            status: booking.status || 'Awaiting Approval'
        });
    });

    return booked.sort((a, b) => a.startMinutes - b.startMinutes);
}

function isSlotConfirmed(date, startTime, duration) {
    const durationMinutes = getDurationInMinutes(duration);
    const startMinutes = parseTime(startTime);
    if (startMinutes === 0) return false;
    const endMinutes = startMinutes + durationMinutes;

    const booked = getBookedSlotsForDate(date);
    for (const b of booked) {
        if (b.status === 'Approved (Unpaid)' ||
            b.status === 'Deposit Paid' ||
            b.status === 'Confirmed' ||
            b.status === 'In Progress' ||
            b.status === 'Completed') {
            if (startMinutes < b.endMinutes && endMinutes > b.startMinutes) {
                return true;
            }
        }
    }
    return false;
}

function isSlotPending(date, startTime, duration) {
    const durationMinutes = getDurationInMinutes(duration);
    const startMinutes = parseTime(startTime);
    if (startMinutes === 0) return false;
    const endMinutes = startMinutes + durationMinutes;

    const booked = getBookedSlotsForDate(date);
    for (const b of booked) {
        if (b.status === 'Awaiting Approval') {
            if (startMinutes < b.endMinutes && endMinutes > b.startMinutes) {
                return true;
            }
        }
    }
    return false;
}

// Buckets every 15-minute slot on a date so the UI can color-code them.
// Past slots are kept (not dropped) so they can be flagged gray instead of
// silently disappearing from the grid.
function getSlotMap(date, duration) {
    const durationMinutes = getDurationInMinutes(duration || '1 hour');
    const all = [], past = [], available = [], pending = [], confirmed = [];

    for (
        let timeMinutes = OPEN_HOUR_MINUTES;
        timeMinutes < CLOSE_HOUR_MINUTES;
        timeMinutes += SLOT_STEP_MINUTES
    ) {
        const timeStr = formatTime(timeMinutes);
        if (timeStr === 'Invalid') continue;
        all.push(timeStr);

        // Already started today — gray, never bookable
        if (isPastSlot(date, timeMinutes)) { past.push(timeStr); continue; }

        // Session would run past closing — not offered for this package
        if (timeMinutes + durationMinutes > CLOSE_HOUR_MINUTES) continue;

        if (isSlotConfirmed(date, timeStr, duration || '1 hour')) {
            confirmed.push(timeStr);
        } else if (isSlotPending(date, timeStr, duration || '1 hour')) {
            pending.push(timeStr);
        } else {
            available.push(timeStr);
        }
    }

    return { all, past, available, pending, confirmed };
}

// ✅ UPDATED: Skips past time slots for today
function getAvailableSlots(date, duration) {
    const durationMinutes = getDurationInMinutes(duration);

    return getSlotMap(date, duration).available.map(timeStr => {
        const startMinutes = parseTime(timeStr);
        return {
            start: timeStr,
            end: formatTime(startMinutes + durationMinutes),
            startMinutes: startMinutes,
            endMinutes: startMinutes + durationMinutes
        };
    });
}

// Past slots for a date, as {start, startMinutes} for gray rendering.
function getPastSlots(date, duration) {
    return getSlotMap(date, duration).past.map(timeStr => ({
        start: timeStr,
        startMinutes: parseTime(timeStr)
    }));
}

function showBookedTimes(date) {
    const container = document.getElementById('booked-times-display');
    const listContainer = document.getElementById('booked-times-list');

    if (!date) { container.style.display = 'none'; return; }
    const booked = getBookedSlotsForDate(date);
    if (booked.length === 0) { container.style.display = 'none'; return; }

    container.style.display = 'block';
    listContainer.innerHTML = '';

    booked.forEach(b => {
        const hours = Math.floor(b.durationMinutes / 60);
        const mins = b.durationMinutes % 60;
        let durationText = '';
        if (hours > 0) durationText += hours + 'h';
        if (mins > 0) durationText += (durationText ? ' ' : '') + mins + 'm';

        const isPending = (b.status === 'Awaiting Approval');
        const isPast = isPastSlot(date, b.startMinutes);

        const bg     = isPast ? '#EEEEEE'                  : isPending ? 'var(--amber-bg)'   : 'var(--red-bg)';
        const color  = isPast ? '#616161'                  : isPending ? 'var(--amber-text)' : 'var(--red-text)';
        const border = isPast ? '#BDBDBD'                  : isPending ? 'var(--amber)'      : 'var(--red)';
        const icon   = isPast ? '⚫'                        : isPending ? '🟡'                : '🔴';

        const slotDiv = document.createElement('div');
        slotDiv.style.cssText = `padding:4px 10px;background:${bg};color:${color};border-radius:4px;font-size:11px;border:1px solid ${border};display:flex;align-items:center;gap:6px;opacity:${isPast ? '0.75' : '0.9'};`;
        slotDiv.innerHTML = `<span>${icon} ${b.start} → ${b.end}</span><span style="font-size:9px;color:${isPast ? '#616161' : 'var(--muted)'};">(${durationText})</span>`;
        listContainer.appendChild(slotDiv);
    });
}

function updateStats(total, booked, available) {
    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-booked').textContent = booked;
    document.getElementById('stat-available').textContent = available;
}

// ✅ Skips past time slots for today
function updateBookedSlotsDisplay(date) {
    const container = document.getElementById('booked-slots-display');
    if (!date) {
        container.innerHTML = `
            <div style="text-align:center;color:var(--muted);padding:30px;">
                <div style="font-size:40px;margin-bottom:8px;">📭</div>
                <div style="font-weight:500;">Select a date to view booked slots</div>
                <div style="font-size:12px;margin-top:4px;">Click on a date in the calendar</div>
            </div>`;
        updateStats(0, 0, 0);
        return;
    }

    const booked = getBookedSlotsForDate(date);
    const pkgDuration = selectedPackageDuration || '1 hour';

    const slots = getSlotMap(date, pkgDuration);
    const allSlots = slots.all;
    const pastSlots = slots.past;
    const availableSlots = slots.available;
    const pendingSlots = slots.pending;
    const confirmedSlots = slots.confirmed;

    const dateObj = new Date(date + 'T00:00:00');
    const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const dayName = dayNames[dateObj.getDay()];
    const dateDisplay = `${monthNames[dateObj.getMonth()]} ${dateObj.getDate()}, ${dateObj.getFullYear()}`;

    let html = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:8px;flex-wrap:wrap;">
            <div>
                <div style="font-size:13px;font-weight:700;color:var(--dark);">${dayName}</div>
                <div style="font-size:11px;color:var(--muted);">${dateDisplay}</div>
            </div>
            <div style="font-size:11px;color:var(--muted);background:var(--bg-soft);padding:2px 10px;border-radius:12px;">
                ${booked.length} booking${booked.length > 1 ? 's' : ''}
            </div>
        </div>`;

    if (booked.length === 0) {
        html += `
            <div style="text-align:center;color:var(--green-text);padding:20px;background:var(--green-bg);border-radius:6px;border:1px solid var(--green);">
                <div style="font-size:32px;margin-bottom:4px;">✅</div>
                <div style="font-weight:700;">No bookings yet</div>
                <div style="font-size:11px;color:var(--muted);">${pastSlots.length > 0 ? pastSlots.length + ' slot(s) today already passed' : 'All time slots are available'}</div>
            </div>`;
        html += buildPastSlotsHtml(pastSlots);
        container.innerHTML = html;
        updateStats(allSlots.length, 0, availableSlots.length);
        return;
    }

    html += `<div style="display:flex;flex-direction:column;gap:4px;max-height:200px;overflow-y:auto;padding-right:4px;">`;

    booked.forEach((b, index) => {
        const isSelected = (selectedTime === b.start);
        const isPending = (b.status === 'Awaiting Approval');
        const isPast = isPastSlot(date, b.startMinutes);

        const bg     = isPast ? '#EEEEEE' : isPending ? 'var(--amber-bg)' : 'var(--red-bg)';
        const border = isPast ? '#9E9E9E' : isPending ? 'var(--amber)'    : 'var(--red)';
        const labelColor = isPast ? '#616161' : isPending ? 'var(--amber-text)' : 'var(--red-text)';
        const label  = isPast ? '⚫ PAST' : isPending ? '🟡 PENDING' : '🔴 CONFIRMED';

        const hours = Math.floor(b.durationMinutes / 60);
        const mins = b.durationMinutes % 60;
        let durationText = '';
        if (hours > 0) durationText += hours + 'h';
        if (mins > 0) durationText += (durationText ? ' ' : '') + mins + 'm';

        html += `
            <div style="padding:6px 10px;border-radius:4px;background:${isSelected ? 'var(--dark)' : bg};color:${isSelected ? 'white' : (isPast ? '#616161' : 'inherit')};border-left:3px solid ${isSelected ? 'var(--dark)' : border};font-size:12px;display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap;${isPast ? 'opacity:0.7;' : ''}">
                <div>
                    <span style="font-weight:600;">${b.start}</span>
                    <span style="color:${isSelected ? 'rgba(255,255,255,0.7)' : 'var(--muted)'};margin:0 4px;">→</span>
                    <span>${b.end}</span>
                    <span style="font-size:9px;color:${isSelected ? 'rgba(255,255,255,0.7)' : 'var(--muted)'};margin-left:4px;">(${durationText})</span>
                </div>
                ${isSelected
                    ? '<span style="font-size:9px;font-weight:600;color:var(--dark);padding:1px 6px;background:white;border-radius:10px;">⬅️ YOUR PICK</span>'
                    : `<span style="font-size:8px;font-weight:700;color:${labelColor};padding:2px 6px;background:white;border-radius:10px;">${label}</span>`}
            </div>`;
    });

    html += `</div>`;

    if (availableSlots.length > 0) {
        html += `
            <div style="margin-top:8px;padding:6px 10px;background:var(--green-bg);border-radius:4px;border:1px solid var(--green);">
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--green-text);">
                    <span>✅ Available slots</span>
                    <span style="font-weight:700;">${availableSlots.length} slots</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;">
                    ${availableSlots.slice(0, 8).map(slot => `
                        <span style="padding:2px 8px;background:white;border:1px solid var(--green);border-radius:3px;font-size:9px;color:var(--green-text);cursor:pointer;transition:0.2s;"
                        onmouseover="this.style.background='var(--green)';this.style.color='white';"
                        onmouseout="this.style.background='white';this.style.color='var(--green-text)';"
                        onclick="document.getElementById('form-time').value='${slot}';checkTimeAvailability('${slot}');">${slot}</span>
                    `).join('')}
                    ${availableSlots.length > 8 ? `<span style="padding:2px 6px;font-size:9px;color:var(--muted);">+${availableSlots.length - 8} more</span>` : ''}
                </div>
            </div>`;
    }

    if (pendingSlots.length > 0) {
        html += `
            <div style="margin-top:6px;padding:6px 10px;background:var(--amber-bg);border-radius:4px;border:1px solid var(--amber);">
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--amber-text);">
                    <span>⏳ Pending slots (pwede pa mag-book)</span>
                    <span style="font-weight:700;">${pendingSlots.length} slots</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;">
                    ${pendingSlots.slice(0, 8).map(slot => `
                        <span style="padding:2px 8px;background:white;border:1px solid var(--amber);border-radius:3px;font-size:9px;color:var(--amber-text);cursor:pointer;transition:0.2s;"
                        onmouseover="this.style.background='var(--amber)';this.style.color='white';"
                        onmouseout="this.style.background='white';this.style.color='var(--amber-text)';"
                        onclick="document.getElementById('form-time').value='${slot}';checkTimeAvailability('${slot}');">${slot}</span>
                    `).join('')}
                    ${pendingSlots.length > 8 ? `<span style="padding:2px 6px;font-size:9px;color:var(--muted);">+${pendingSlots.length - 8} more</span>` : ''}
                </div>
            </div>`;
    }

    html += buildPastSlotsHtml(pastSlots);

    container.innerHTML = html;
    updateStats(allSlots.length, confirmedSlots.length + pendingSlots.length, availableSlots.length);
}

// Gray block listing the slots that already started today. Not clickable —
// they can no longer be booked.
function buildPastSlotsHtml(pastSlots) {
    if (!pastSlots || pastSlots.length === 0) return '';

    return `
        <div style="margin-top:6px;padding:6px 10px;background:#EEEEEE;border-radius:4px;border:1px solid #BDBDBD;">
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#616161;">
                <span>⚫ Past slots — <strong>naka-book na</strong></span>
                <span style="font-weight:700;">${pastSlots.length} slots</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;">
                ${pastSlots.slice(0, 12).map(slot => `
                    <span style="padding:2px 8px;background:#F5F5F5;border:1px solid #BDBDBD;border-radius:3px;font-size:9px;color:#757575;cursor:not-allowed;text-decoration:line-through;">${slot}</span>
                `).join('')}
                ${pastSlots.length > 12 ? `<span style="padding:2px 6px;font-size:9px;color:var(--muted);">+${pastSlots.length - 12} more</span>` : ''}
            </div>
        </div>`;
}

function changeViewDate(delta) {
    const viewDate = document.getElementById('view-date');
    if (!viewDate.value) viewDate.value = todayStr();
    const date = new Date(viewDate.value + 'T00:00:00');
    date.setDate(date.getDate() + delta);
    const newDate = toDateStr(date);
    viewDate.value = newDate;
    onViewDateChange(newDate);
}

function onDateSelect(date) {
    if (date) {
        document.getElementById('view-date').value = date;
        updateBookedSlotsDisplay(date);
        showBookedTimes(date);
        document.getElementById('form-time').value = '';
        document.getElementById('time-status').innerHTML = '';
        document.getElementById('submit-btn').disabled = true;
        document.getElementById('booking-summary').style.display = 'none';
        selectedTime = '';

        const qtd = document.getElementById('quick-times-dropdown');
        if (qtd) { qtd.style.display = 'none'; qtd.innerHTML = ''; }
    }
}

function onViewDateChange(date) {
    updateBookedSlotsDisplay(date);
    showBookedTimes(date);
}

// ✅ Added past-time validation
function checkTimeAvailability(timeStr) {
    const date = document.getElementById('form-date').value;
    const statusDiv = document.getElementById('time-status');
    const submitBtn = document.getElementById('submit-btn');
    const summaryDiv = document.getElementById('booking-summary');

    if (!date) {
        statusDiv.innerHTML = '<span style="color:var(--muted);">⚠️ Please select a date first</span>';
        submitBtn.disabled = true; return;
    }
    if (!selectedPackageDuration) {
        statusDiv.innerHTML = '<span style="color:var(--muted);">⚠️ Please select a package first</span>';
        submitBtn.disabled = true; return;
    }
    if (!timeStr || timeStr.length < 4) {
        statusDiv.innerHTML = '';
        submitBtn.disabled = true;
        summaryDiv.style.display = 'none';
        return;
    }

    const baseMinutes = getBaseDurationInMinutes(selectedPackageDuration);
    const durationMinutes = getDurationInMinutes(selectedPackageDuration);
    const timeMinutes = parseTime(timeStr);

    if (timeMinutes === 0) {
        statusDiv.innerHTML = '<span style="color:var(--red-text);">❌ Invalid time format. Use: 10:30 AM</span>';
        submitBtn.disabled = true; return;
    }
    if (timeMinutes < OPEN_HOUR_MINUTES) {
        statusDiv.innerHTML = '<span style="color:var(--red-text);">❌ Studio opens at 10:00 AM</span>';
        submitBtn.disabled = true; return;
    }
    if (timeMinutes >= CLOSE_HOUR_MINUTES) {
        statusDiv.innerHTML = '<span style="color:var(--red-text);">❌ Studio closes at 7:00 PM</span>';
        submitBtn.disabled = true; return;
    }

    // ✅ NEW: If today, prevent booking past time
    if (isPastSlot(date, timeMinutes)) {
        statusDiv.innerHTML = `
            <div style="padding:10px;background:#EEEEEE;border-radius:6px;border-left:4px solid #9E9E9E;">
                <div style="color:#616161;font-weight:700;font-size:13px;">⚫ This time has already passed</div>
                <div style="font-size:12px;color:#616161;margin-top:2px;">
                    Current time is ${formatTime(getCurrentTimeInMinutes())}. Past slots are grayed out and cannot be booked.
                </div>
            </div>`;
        submitBtn.disabled = true;
        summaryDiv.style.display = 'none';
        return;
    }

    const endMinutes = timeMinutes + durationMinutes;
    if (endMinutes > CLOSE_HOUR_MINUTES) {
        statusDiv.innerHTML = `<span style="color:var(--red-text);">❌ Session would end past 7:00 PM (ends at ${formatTime(endMinutes)})</span>`;
        submitBtn.disabled = true; return;
    }

    if (isSlotConfirmed(date, timeStr, selectedPackageDuration)) {
        const booked = getBookedSlotsForDate(date);
        let conflictInfo = '';
        
        for (const b of booked) {
            if ((b.status === 'Approved (Unpaid)' || b.status === 'Deposit Paid' || 
                 b.status === 'Confirmed' || b.status === 'In Progress' || 
                 b.status === 'Completed') &&
                timeMinutes < b.endMinutes && endMinutes > b.startMinutes) {
                conflictInfo = `<div style="font-size:11px;margin-top:4px;color:#8E0000;"><strong>Conflicting booking:</strong> ${b.start} → ${b.end}</div>`;
                break;
            }
        }
        
        statusDiv.innerHTML = `
            <div style="padding:12px;background:var(--red-bg);border-radius:6px;border-left:4px solid #C62828;">
                <div style="color:#8E0000;font-weight:700;font-size:13px;">❌ Schedule Conflict Detected</div>
                <div style="font-size:12px;color:#8E0000;margin-top:4px;">
                    This time slot is already booked by another client.
                </div>
                ${conflictInfo}
                <div style="font-size:11px;color:var(--dark2);margin-top:8px;padding:6px 8px;background:white;border-radius:4px;line-height:1.4;">
                    💡 <strong>Solution:</strong> Please choose a different time or click <strong>"Quick Times ▼"</strong> to see all available slots.
                </div>
            </div>`;
        submitBtn.disabled = true;
        summaryDiv.style.display = 'none';
        selectedTime = '';
        updateBookedSlotsDisplay(date);
        showBookedTimes(date);
        return;
    }

    const hasPending = isSlotPending(date, timeStr, selectedPackageDuration);

    selectedTime = timeStr;
    const endTime = formatTime(endMinutes);
    
    const baseHours = Math.floor(baseMinutes / 60);
    const baseMins = baseMinutes % 60;
    const adjHours = Math.floor(durationMinutes / 60);
    const adjMins = durationMinutes % 60;
    
    let baseText = '';
    if (baseHours > 0) baseText += baseHours + 'h';
    if (baseMins > 0) baseText += (baseText ? ' ' : '') + baseMins + 'm';
    
    let adjText = '';
    if (adjHours > 0) adjText += adjHours + 'h';
    if (adjMins > 0) adjText += (adjText ? ' ' : '') + adjMins + 'm';

    let durationDisplay = adjText;
    if (LOYALTY_BONUS_MINUTES > 0) {
        durationDisplay = `${baseText} <span style="color:#F59E0B;font-weight:700;">+ ${LOYALTY_BONUS_MINUTES} min loyalty</span> = <strong>${adjText}</strong>`;
    }

    if (hasPending) {
        statusDiv.innerHTML = `<span style="color:var(--amber-text);font-weight:600;">⚠️ May pending request na ito. Pwede ka pa ring mag-book — i-review ng staff.</span>`;
    } else {
        statusDiv.innerHTML = `<span style="color:var(--green-text);font-weight:600;">✅ Available! ${timeStr} → ${endTime} (${durationDisplay})</span>`;
    }
    submitBtn.disabled = false;

    summaryDiv.style.display = 'block';
    document.getElementById('summary-details').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;font-size:12px;">
            <span style="color:var(--muted);">Date:</span>
            <span style="font-weight:500;">${formatDateDisplay(date)}</span>
            <span style="color:var(--muted);">Time:</span>
            <span style="font-weight:500;color:var(--green-text);">${timeStr} → ${endTime}</span>
            <span style="color:var(--muted);">Base Duration:</span>
            <span style="font-weight:500;">${baseText}</span>
            ${LOYALTY_BONUS_MINUTES > 0 ? `
                <span style="color:var(--muted);">Loyalty Bonus:</span>
                <span style="font-weight:700;color:#F59E0B;">+ ${LOYALTY_BONUS_MINUTES} min 🎁</span>
            ` : ''}
            <span style="color:var(--muted);">Total Duration:</span>
            <span style="font-weight:700;color:var(--dark);">${adjText}</span>
            ${hasPending ? `<span style="color:var(--muted);">Note:</span><span style="font-weight:500;color:var(--amber-text);">May pending request</span>` : ''}
        </div>`;

    updateBookedSlotsDisplay(date);
    showBookedTimes(date);
    document.getElementById('form-time').value = timeStr;
}

function showQuickTimes() {
    const dropdown = document.getElementById('quick-times-dropdown');
    if (dropdown.style.display === 'block') { dropdown.style.display = 'none'; return; }

    const date = document.getElementById('form-date').value;
    if (!date) {
        dropdown.style.display = 'none';
        alert('Please select a date first');
        return;
    }
    if (!selectedPackageDuration) {
        dropdown.style.display = 'none';
        alert('Please select a package first');
        return;
    }

    const availableSlots = getAvailableSlots(date, selectedPackageDuration);
    const pastSlots = getPastSlots(date, selectedPackageDuration);

    let html = '';

    if (availableSlots.length > 0) {
        html += `<div style="font-size:10px;color:var(--green-text);font-weight:600;padding:4px 0;border-bottom:1px solid var(--green);margin-bottom:4px;">✅ Available Slots (${availableSlots.length})</div>`;

        availableSlots.slice(0, 15).forEach(slot => {
            html += `
                <div style="padding:6px 10px;cursor:pointer;border-radius:4px;margin:2px 0;background:var(--green-bg);color:var(--green-text);font-size:12px;display:flex;justify-content:space-between;align-items:center;transition:0.2s;"
                onmouseover="this.style.background='var(--green)';this.style.color='white';"
                onmouseout="this.style.background='var(--green-bg)';this.style.color='var(--green-text)';"
                onclick="selectQuickTime('${slot.start}')">
                    <span>${slot.start} → ${slot.end}</span>
                    <span>✅</span>
                </div>`;
        });

        if (availableSlots.length > 15) {
            html += `<div style="text-align:center;padding:4px;font-size:11px;color:var(--muted);">+${availableSlots.length - 15} more slots available</div>`;
        }
    } else {
        html += `<div style="text-align:center;padding:10px;color:var(--red-text);">❌ ${pastSlots.length > 0 ? 'All remaining slots for today have already passed' : 'No available slots for this date'}</div>`;
    }

    if (pastSlots.length > 0) {
        html += `<div style="font-size:10px;color:#616161;font-weight:600;padding:4px 0;border-bottom:1px solid #BDBDBD;margin:6px 0 4px;">⚫ Past — cannot be booked (${pastSlots.length})</div>`;
        html += `<div style="display:flex;flex-wrap:wrap;gap:3px;">`;
        pastSlots.slice(0, 12).forEach(slot => {
            html += `<div style="padding:4px 8px;border-radius:4px;background:#EEEEEE;color:#757575;border:1px solid #BDBDBD;font-size:11px;cursor:not-allowed;text-decoration:line-through;">${slot.start}</div>`;
        });
        html += `</div>`;
    }

    dropdown.innerHTML = html;
    dropdown.style.display = 'block';
}

function selectQuickTime(timeStr) {
    document.getElementById('form-time').value = timeStr;
    document.getElementById('quick-times-dropdown').style.display = 'none';
    checkTimeAvailability(timeStr);
}

function renderCalendar() {
    const grid = document.getElementById('cal-grid');
    const label = document.getElementById('cal-month-label');
    if (!grid) return;

    grid.innerHTML = '';
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    label.textContent = months[calMonth] + ' ' + calYear;

    ['S','M','T','W','T','F','S'].forEach(d => {
        const el = document.createElement('div');
        el.className = 'calendar-day-label';
        el.textContent = d;
        grid.appendChild(el);
    });

    const firstDay = new Date(calYear, calMonth, 1).getDay();
    const lastDate = new Date(calYear, calMonth + 1, 0).getDate();

    for (let i = 0; i < firstDay; i++) grid.appendChild(document.createElement('div'));

    for (let d = 1; d <= lastDate; d++) {
        const ds = calYear + '-' + String(calMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        const isPast = ds < SERVER_TODAY;
        const isTodayCell = ds === SERVER_TODAY;

        let hasAvailable = false;
        let allPast = false;
        if (!isPast && selectedPackageDuration) {
            const map = getSlotMap(ds, selectedPackageDuration);
            hasAvailable = map.available.length > 0;
            // Every slot for today has already started — gray, not "Fully Booked"
            allPast = isTodayCell && !hasAvailable && map.past.length > 0;
        }

        let cls = 'calendar-date';
        let statusText = '';

        if (isPast) { cls += ' date-past'; statusText = 'Past'; }
        else if (allPast) { cls += ' date-past'; statusText = 'All slots today have passed'; }
        else if (!hasAvailable && selectedPackageDuration) { cls += ' date-booked'; statusText = 'Fully Booked'; }
        else { cls += ' date-available'; statusText = 'Available'; }

        const cell = document.createElement('div');
        cell.className = cls;
        cell.textContent = d;
        cell.dataset.date = ds;
        cell.title = statusText;

        cell.onclick = () => {
            if (!cell.classList.contains('date-past') && !cell.classList.contains('date-booked')) {
                document.getElementById('form-date').value = ds;
                document.getElementById('view-date').value = ds;
                document.getElementById('form-time').value = '';
                document.getElementById('time-status').innerHTML = '';
                document.getElementById('submit-btn').disabled = true;
                document.getElementById('booking-summary').style.display = 'none';
                selectedTime = '';
                updateBookedSlotsDisplay(ds);
                showBookedTimes(ds);
                document.querySelectorAll('.calendar-date').forEach(c => c.style.outline = '');
                cell.style.outline = '2px solid var(--dark)';

                const qtd = document.getElementById('quick-times-dropdown');
                if (qtd) { qtd.style.display = 'none'; qtd.innerHTML = ''; }
            } else if (cell.classList.contains('date-booked')) {
                alert('This date is fully booked. Please choose another date.');
            } else if (allPast) {
                alert('All time slots for today have already passed. Please choose another date.');
            }
        };
        grid.appendChild(cell);
    }
}

const sampleImages = {
    'Package A': 'assets/package a.png',
    'Package B': 'assets/package b.png',
    'Package C': 'assets/package c.png',
    'Package D': 'assets/package d.png',
    'Package E': 'assets/package e.png',
    'Package F': 'assets/package f.png',
    'Classic': 'assets/classic package.png',
    'Premium': 'assets/premium package.png',
    '3 Pax': 'assets/3 pax.png',
    '4-6 Pax': 'assets/4-6pax.png',
    '7-10 Pax': 'assets/7-10pax.png',
    'Basic': 'assets/basic package.png',
    'Half Day': 'assets/halfday package.png'
};

function showSubPackages(mainId, mainName) {
    const main = mainPackages.find(p => p.id === mainId);
    if (!main) { alert('Package not found!'); return; }

    document.getElementById('main-packages-section').style.display = 'none';
    document.getElementById('sub-packages-section').style.display = 'block';
    document.getElementById('sub-packages-title').innerHTML = '<strong>' + mainName + ' Packages</strong>';

    const grid = document.getElementById('sub-packages-grid');
    grid.innerHTML = '';

    if (!main.sub_packages || main.sub_packages.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--muted);padding:30px;">No sub-packages available for this category.</div>';
        return;
    }

    main.sub_packages.forEach(sub => {
        let feats = [];
        try { feats = JSON.parse(sub.features || '[]'); } catch(e) { feats = []; }

        const img = sampleImages[sub.name] || 'assets/default-package.png';
        const price = Number(sub.price).toLocaleString();

        const card = document.createElement('div');
        card.className = 'package-card';

        const popularBadge = sub.is_popular ? `<span class="popular-badge">🔥 POPULAR</span>` : '';

        let featuresHtml = '';
        feats.forEach(f => { featuresHtml += `<li>${f}</li>`; });

        const printHtml = sub.print_inclusions ? `<div class="print-inclusions">🖼️ ${sub.print_inclusions}</div>` : '';
        const maxPaxHtml = sub.max_pax ? `<div class="max-pax">👥 Max ${sub.max_pax} persons</div>` : '';

        card.innerHTML = `
            ${popularBadge}
            <div class="card-image">
                <img src="${img}" alt="${sub.name}" class="package-img" onerror="this.src='assets/default-package.png'" loading="lazy">
                <div class="overlay">
                    <div class="name">${sub.name}</div>
                    <div class="duration">⏱️ ${sub.duration || 'Flexible'}</div>
                </div>
            </div>
            <div class="card-body">
                <div class="price">₱${price}</div>
                ${maxPaxHtml}
                <ul class="features">${featuresHtml}</ul>
                ${printHtml}
                <button class="btn-book" onclick="event.stopPropagation(); selectSubPackage(${sub.id}, ${JSON.stringify(sub.name)}, ${sub.price}, ${JSON.stringify(mainName)}, ${JSON.stringify(sub.duration || '1 hour')})">
                    📅 BOOK NOW
                </button>
            </div>`;

        card.addEventListener('click', function() {
            selectSubPackage(sub.id, sub.name, sub.price, mainName, sub.duration || '1 hour');
        });

        grid.appendChild(card);
    });
}

function backToMain() {
    document.getElementById('main-packages-section').style.display = 'block';
    document.getElementById('sub-packages-section').style.display = 'none';
    document.getElementById('booking-form-section').style.display = 'none';
}

function selectSubPackage(id, name, price, mainName, duration) {
    selectedSubPackageId = id;
    selectedPackageDuration = duration;
    selectedPackageBaseMinutes = getBaseDurationInMinutes(duration);
    selectedTime = '';

    document.getElementById('form-pkg-id').value = id;
    document.getElementById('form-pkg-display').textContent = mainName + ' — ' + name;
    document.getElementById('form-pkg-price').textContent = '₱' + Number(price).toLocaleString();

    const baseMinutes = getBaseDurationInMinutes(duration);
    const totalMinutes = getDurationInMinutes(duration);
    const baseHours = Math.floor(baseMinutes / 60);
    const baseMins = baseMinutes % 60;
    const totalHours = Math.floor(totalMinutes / 60);
    const totalMins = totalMinutes % 60;
    
    let baseText = '';
    if (baseHours > 0) baseText += baseHours + ' hour' + (baseHours > 1 ? 's' : '');
    if (baseMins > 0) baseText += (baseText ? ' ' : '') + baseMins + ' minutes';
    
    let totalText = '';
    if (totalHours > 0) totalText += totalHours + ' hour' + (totalHours > 1 ? 's' : '');
    if (totalMins > 0) totalText += (totalText ? ' ' : '') + totalMins + ' minutes';

    let durationDisplay = `⏱️ Duration: ${duration} (${baseText})`;
    if (LOYALTY_BONUS_MINUTES > 0) {
        durationDisplay += ` → <strong style="color:#F59E0B;">${totalText} with loyalty bonus 🎁</strong>`;
    }
    document.getElementById('form-pkg-duration').innerHTML = durationDisplay;

    document.getElementById('sub-packages-section').style.display = 'none';
    document.getElementById('booking-form-section').style.display = 'block';

    renderCalendar();

    const today = todayStr();
    document.getElementById('view-date').value = today;
    updateBookedSlotsDisplay(today);
    showBookedTimes(today);

    const main = mainPackages.find(p => p.name === mainName);
    if (main && main.sub_packages) {
        main.sub_packages.forEach(sub => {
            if (sub.id === id && sub.max_pax) {
                document.getElementById('form-people').max = sub.max_pax;
                document.getElementById('form-people').value = 1;
            }
        });
    }

    document.getElementById('booking-form-section').scrollIntoView({ behavior: 'smooth' });
}

function clearBooking() {
    document.getElementById('booking-form-section').style.display = 'none';
    document.getElementById('sub-packages-section').style.display = 'block';
    document.getElementById('form-date').value = '';
    document.getElementById('form-time').value = '';
    document.getElementById('time-status').innerHTML = '';
    document.getElementById('submit-btn').disabled = true;
    document.getElementById('booking-summary').style.display = 'none';
    document.getElementById('booked-times-display').style.display = 'none';
    selectedTime = '';
}

function changeCalMonth(delta) {
    calMonth += delta;
    if (calMonth > 11) { calMonth = 0; calYear++; }
    if (calMonth < 0) { calMonth = 11; calYear--; }
    renderCalendar();
}

document.addEventListener('DOMContentLoaded', function() {
    renderCalendar();
    const today = todayStr();
    document.getElementById('view-date').value = today;
    updateBookedSlotsDisplay(today);
    showBookedTimes(today);
});

// Keep the gray "past" flags honest while the page stays open: a slot that
// was still bookable when the page loaded must not stay bookable after
// its start time passes.
setInterval(function() {
    const viewDate = document.getElementById('view-date');
    if (!viewDate || !isToday(viewDate.value) || !selectedPackageDuration) return;
    updateBookedSlotsDisplay(viewDate.value);
    showBookedTimes(viewDate.value);
    renderCalendar();
}, 60000);
</script>
