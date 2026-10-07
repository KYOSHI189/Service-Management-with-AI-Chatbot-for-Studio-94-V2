<?php
// ============================================================
// WALK-IN BOOKING — Same design as online booking
// ============================================================

requireRole(['admin', 'staff']);
$pdo = db();
$user = currentUser();

// ============================================================
// AUTO-MIGRATION CHECK
// ============================================================
$hasDurationColumn = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'duration_minutes'")->fetchAll();
    $hasDurationColumn = count($cols) > 0;
    if (!$hasDurationColumn) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN duration_minutes INT DEFAULT NULL AFTER package_id");
        $hasDurationColumn = true;
    }
} catch (Exception $e) {
    $hasDurationColumn = false;
}

// ============================================================
// OPERATING HOURS
// ============================================================
if (!defined('OPEN_HOUR_MINUTES'))  define('OPEN_HOUR_MINUTES', 10 * 60);
if (!defined('CLOSE_HOUR_MINUTES')) define('CLOSE_HOUR_MINUTES', 19 * 60);
if (!defined('RESERVATION_FEE'))    define('RESERVATION_FEE', 100);
if (!defined('SLOT_STEP_MINUTES'))  define('SLOT_STEP_MINUTES', 15);

// ============================================================
// DURATION PARSER
// ============================================================
if (!function_exists('parsePackageDurationToMinutes')) {
    function parsePackageDurationToMinutes($duration) {
        if ($duration === null || $duration === '') return 60;
        $d = strtolower(trim((string)$duration));
        if (strpos($d, 'half day') !== false || strpos($d, 'half-day') !== false) return 240;
        if (strpos($d, 'whole day') !== false || strpos($d, 'full day') !== false
            || strpos($d, 'whole-day') !== false || strpos($d, 'full-day') !== false) return 480;
        if (preg_match('/(\d+)\s*-\s*(\d+)\s*(hour|hr)/', $d, $m))    return ((int)$m[2]) * 60;
        if (preg_match('/(\d+)\s*-\s*(\d+)\s*(min|minute)/', $d, $m))  return (int)$m[2];
        if (preg_match('/(\d+)\s*(?:hour|hr)\s*(\d+)\s*(?:min|minute)/', $d, $m))
            return ((int)$m[1] * 60) + (int)$m[2];
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:hour|hr)/', $d, $m))     return (int)round((float)$m[1] * 60);
        if (preg_match('/(\d+)\s*(?:min|minute)/', $d, $m))            return (int)$m[1];
        if (preg_match('/(\d+(?:\.\d+)?)/', $d, $m))                   return (int)round((float)$m[1] * 60);
        return 60;
    }
}

if (!function_exists('resolveBookingDurationMinutes')) {
    function resolveBookingDurationMinutes($row) {
        if (isset($row['duration_minutes']) && $row['duration_minutes'] !== null && (int)$row['duration_minutes'] > 0) {
            return (int)$row['duration_minutes'];
        }
        $base = parsePackageDurationToMinutes($row['pkg_duration'] ?? '1 hour');
        if (!empty($row['notes']) && preg_match('/\+(\d+)\s*MIN bonus applied/i', $row['notes'], $m)) {
            $base += (int)$m[1];
        }
        return $base;
    }
}

if (!function_exists('parseTimeToMinutes')) {
    function parseTimeToMinutes($timeStr) {
        if (!$timeStr) return null;
        if (preg_match('/(\d+):(\d+)\s*(AM|PM)/i', $timeStr, $m)) {
            $h = (int)$m[1]; $mm = (int)$m[2]; $ap = strtoupper($m[3]);
            if ($ap === 'PM' && $h !== 12) $h += 12;
            if ($ap === 'AM' && $h === 12) $h = 0;
            return ($h * 60) + $mm;
        }
        return null;
    }
}

if (!function_exists('minutesToTimeStr')) {
    function minutesToTimeStr($min) {
        $h = intdiv($min, 60); $m = $min % 60;
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12; if ($h12 === 0) $h12 = 12;
        return sprintf('%d:%02d %s', $h12, $m, $ampm);
    }
}

if (!function_exists('calculateWalkinLoyaltyBonus')) {
    function calculateWalkinLoyaltyBonus($totalBookings) {
        $bonusMinutes = 0; $discountPct = 0;
        if ($totalBookings >= 10)      $discountPct = 50;
        elseif ($totalBookings >= 1)   $bonusMinutes = 5;
        return [
            'bonus_minutes' => $bonusMinutes,
            'discount_pct'  => $discountPct,
            'has_loyalty'   => ($totalBookings >= 1),
            'has_discount'  => ($totalBookings >= 10),
        ];
    }
}

if (!function_exists('checkWalkinConflict')) {
    function checkWalkinConflict($date, $time, $durationMinutes, $excludeId = null) {
        $pdo = db();
        $newStart = strtotime("$date $time");
        if ($newStart === false) return false;
        $newEnd = $newStart + ($durationMinutes * 60);
        $sql = "SELECT b.id, b.time, b.notes,
                       " . (isset($GLOBALS['hasDurationColumn']) && $GLOBALS['hasDurationColumn'] ? "b.duration_minutes," : "NULL AS duration_minutes,") . "
                       p.duration AS pkg_duration
                FROM bookings b
                JOIN packages p ON b.package_id = p.id
                WHERE b.date = ?
                  AND b.status IN ('Confirmed', 'In Progress', 'Completed', 'Deposit Paid', 'Approved (Unpaid)')";
        $params = [$date];
        if ($excludeId) { $sql .= " AND b.id != ?"; $params[] = $excludeId; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $exMin = resolveBookingDurationMinutes($row);
            $exStart = strtotime($date . ' ' . $row['time']);
            if ($exStart === false) continue;
            $exEnd = $exStart + ($exMin * 60);
            if ($newStart < $exEnd && $newEnd > $exStart) return true;
        }
        return false;
    }
}

// ============================================================
// GET MAIN + SUB PACKAGES (same as booking.php)
// ============================================================
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

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verifyCsrf')) verifyCsrf();

    $client_name = trim($_POST['client_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $package_id  = function_exists('cleanInt') ? cleanInt($_POST['package_id'] ?? 0) : (int)($_POST['package_id'] ?? 0);
    $date        = $_POST['date'] ?? '';
    $time        = $_POST['time'] ?? '';
    $people      = function_exists('cleanInt') ? cleanInt($_POST['people'] ?? 1) : (int)($_POST['people'] ?? 1);
    $notes       = trim($_POST['notes'] ?? '');

    $errors = [];
    if (empty($client_name)) $errors[] = 'Client name is required.';
    if (empty($phone))       $errors[] = 'Phone number is required.';
    if ($package_id <= 0)    $errors[] = 'Please select a package.';
    if (empty($date))        $errors[] = 'Date is required.';
    if (empty($time))        $errors[] = 'Time is required.';

    $clientId = null;
    $loyaltyCount = 0;

    if (empty($errors)) {
        if ($email) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $uStmt->execute([$email]);
            $clientId = $uStmt->fetchColumn();
        }
        if (!$clientId && $phone) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $uStmt->execute([$phone]);
            $clientId = $uStmt->fetchColumn();
        }
        if (!$clientId) {
            $randomPassword = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
            if (empty($email)) $email = 'walkin_' . uniqid() . '@example.com';
            $pdo->prepare("INSERT INTO users (name, email, phone, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 'client', 1, NOW())")
                ->execute([$client_name, $email, $phone, $hashedPassword]);
            $clientId = (int)$pdo->lastInsertId();
            if (function_exists('addNotification')) {
                addNotification($clientId, 'Welcome to Studio 94!',
                    'Your account has been created. Your temporary password is: ' . $randomPassword,
                    'system', '🎉');
            }
        } else {
            $lcStmt = $pdo->prepare("SELECT total_bookings FROM loyalty_cards WHERE user_id = ?");
            $lcStmt->execute([$clientId]);
            $loyaltyCount = (int)($lcStmt->fetchColumn() ?: 0);
        }
    }

    $pkg = null;
    $baseDurationMinutes = 60;
    $adjustedDurationMinutes = 60;
    $bonusMinutes = 0;
    $discountPct = 0;
    $originalPrice = 0;
    $finalPrice = 0;

    if (empty($errors)) {
        $pkgStmt = $pdo->prepare("
            SELECT id, name, price, duration, max_pax, parent_id
            FROM packages
            WHERE id = ? AND is_active = 1 AND parent_id IS NOT NULL
        ");
        $pkgStmt->execute([$package_id]);
        $pkg = $pkgStmt->fetch();

        if (!$pkg) {
            $errors[] = 'Invalid package selected.';
        } else {
            $baseDurationMinutes = parsePackageDurationToMinutes($pkg['duration'] ?? '1 hour');
            $loyalty = calculateWalkinLoyaltyBonus($loyaltyCount);
            $bonusMinutes = $loyalty['bonus_minutes'];
            $discountPct  = $loyalty['discount_pct'];
            $adjustedDurationMinutes = $baseDurationMinutes + $bonusMinutes;
            $originalPrice = (float)$pkg['price'];
            $finalPrice = $discountPct > 0
                ? round($originalPrice * (100 - $discountPct) / 100, 2)
                : $originalPrice;
        }
    }

    if (empty($errors) && $pkg) {
        $startMin = parseTimeToMinutes($time);
        if ($startMin === null) {
            $errors[] = 'Invalid time format. Use e.g., "10:30 AM".';
        } else {
            $endMin = $startMin + $adjustedDurationMinutes;
            if ($startMin < OPEN_HOUR_MINUTES) $errors[] = 'Studio opens at 10:00 AM.';
            if ($startMin >= CLOSE_HOUR_MINUTES) $errors[] = 'Studio closes at 7:00 PM.';
            if ($endMin > CLOSE_HOUR_MINUTES) $errors[] = 'Session would end past 7:00 PM. Please choose an earlier time.';
        }
    }

    if (empty($errors) && $pkg) {
        if (checkWalkinConflict($date, $time, $adjustedDurationMinutes)) {
            $errors[] = 'This time slot is already booked. Please choose another time.';
        }
    }

    if (empty($errors) && $pkg && $clientId) {
        $ref = 'WALK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $reservationFee = RESERVATION_FEE;
        $remaining      = max(0, $finalPrice - $reservationFee);

        $loyaltyNote = '';
        if ($bonusMinutes > 0) {
            $loyaltyNote .= "\n[LOYALTY] +{$bonusMinutes} MIN bonus applied (base: {$baseDurationMinutes} min → adjusted: {$adjustedDurationMinutes} min)";
        }
        if ($discountPct > 0) {
            $loyaltyNote .= "\n[LOYALTY] {$discountPct}% OFF applied (original: ₱{$originalPrice} → final: ₱{$finalPrice})";
        }
        $finalNotes = trim($notes . $loyaltyNote);

        if ($hasDurationColumn) {
            $pdo->prepare("
                INSERT INTO bookings 
                    (booking_ref, user_id, package_id, duration_minutes, date, time, people, phone, notes, type, status,
                     package_price, deposit_amount, remaining_balance, deposit_paid, fully_paid, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'walk-in', 'Awaiting Approval', ?, ?, ?, 0, 0, NOW())
            ")->execute([
                $ref, $clientId, $package_id, $adjustedDurationMinutes,
                $date, $time, $people, $phone, $finalNotes,
                $finalPrice, $reservationFee, $remaining
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO bookings 
                    (booking_ref, user_id, package_id, date, time, people, phone, notes, type, status,
                     package_price, deposit_amount, remaining_balance, deposit_paid, fully_paid, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'walk-in', 'Awaiting Approval', ?, ?, ?, 0, 0, NOW())
            ")->execute([
                $ref, $clientId, $package_id,
                $date, $time, $people, $phone, $finalNotes,
                $finalPrice, $reservationFee, $remaining
            ]);
        }
        $bookingId = (int)$pdo->lastInsertId();

        $payRef = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $pdo->prepare("
            INSERT INTO payments (payment_ref, booking_id, user_id, amount, type, method, status, created_at)
            VALUES (?, ?, ?, ?, 'RESERVATION', 'Pending', 'UNPAID', NOW())
        ")->execute([$payRef, $bookingId, $clientId, $reservationFee]);

        $lcCheck = $pdo->prepare("SELECT id, total_bookings FROM loyalty_cards WHERE user_id = ?");
        $lcCheck->execute([$clientId]);
        $existingCard = $lcCheck->fetch();
        if (!$existingCard) {
            $cardNo = 'LC-' . date('Y') . '-' . str_pad($clientId, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO loyalty_cards (user_id, card_number, total_bookings, status) VALUES (?, ?, 1, 'Active')")
                ->execute([$clientId, $cardNo]);
        } else {
            $pdo->prepare("UPDATE loyalty_cards SET total_bookings = total_bookings + 1 WHERE user_id = ?")
                ->execute([$clientId]);
        }

        if (function_exists('addNotificationByRole')) {
            $notifMsg = $client_name . ' booked ' . $pkg['name'] . ' on '
                . date('M d, Y', strtotime($date)) . ' at ' . $time
                . ' (Ref: ' . $ref . ')';
            if ($bonusMinutes > 0 || $discountPct > 0) {
                $notifMsg .= ' — 🎁 Loyalty: ';
                $parts = [];
                if ($bonusMinutes > 0) $parts[] = '+' . $bonusMinutes . ' MIN';
                if ($discountPct > 0)  $parts[] = $discountPct . '% OFF';
                $notifMsg .= implode(', ', $parts);
            }
            addNotificationByRole('admin', '🚶 Walk-in Booking Created', $notifMsg, 'booking', '🚶');
            addNotificationByRole('staff', '🚶 Walk-in Booking Created', $notifMsg, 'booking', '🚶');
        }

        $successMsg = '✅ Walk-in booking created for ' . $client_name . ' (Ref: ' . $ref . ')';
        if ($bonusMinutes > 0) $successMsg .= ' · +' . $bonusMinutes . ' MIN bonus applied';
        if ($discountPct > 0)  $successMsg .= ' · ' . $discountPct . '% loyalty discount';

        if (function_exists('setFlash')) setFlash('success', $successMsg);
        header('Location: index.php?page=bookings');
        exit;
    } else {
        if (function_exists('setFlash')) setFlash('error', implode('<br>', $errors));
        header('Location: index.php?page=walkin');
        exit;
    }
}

$flash = function_exists('getFlash') ? getFlash() : null;

// ============================================================
// PRELOAD BOOKED SLOTS FOR JS
// ============================================================
if ($hasDurationColumn) {
    $walkinBookedSlots = $pdo->query("
        SELECT b.date, b.time, b.status, b.duration_minutes, b.notes,
               p.duration AS pkg_duration
        FROM bookings b
        JOIN packages p ON b.package_id = p.id
        WHERE b.status NOT IN ('Cancelled', 'Rejected')
        ORDER BY b.date ASC, b.time ASC
    ")->fetchAll();
} else {
    $walkinBookedSlots = $pdo->query("
        SELECT b.date, b.time, b.status, b.notes,
               p.duration AS pkg_duration
        FROM bookings b
        JOIN packages p ON b.package_id = p.id
        WHERE b.status NOT IN ('Cancelled', 'Rejected')
        ORDER BY b.date ASC, b.time ASC
    ")->fetchAll();
}

foreach ($walkinBookedSlots as &$bs) {
    $bs['effective_duration_minutes'] = resolveBookingDurationMinutes($bs);
}
unset($bs);
?>

<!-- PAGE BANNER -->
<div class="page-banner" style="background:#0A0A0A;color:#FFFFFF;">
  <div class="page-banner-text">
    <div class="eyebrow" style="color:rgba(255,255,255,0.7);">Create</div>
    <h2 style="color:#FFFFFF;"><strong>🚶 Walk-in Booking</strong></h2>
    <p style="color:rgba(255,255,255,0.85);">Book a session for a walk-in client</p>
  </div>
  <div class="page-banner-art" style="opacity:0.8;">🚶</div>
</div>

<!-- FLASH -->
<?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'error' : 'warning') ?>" style="margin-bottom:20px;">
    <?= $flash['msg'] ?>
  </div>
<?php endif; ?>

<!-- ===== STEP 1: MAIN PACKAGES ===== -->
<div id="main-packages-section">
  <h3 style="margin-bottom:16px;"><strong>📸 Choose Package Type</strong></h3>
  <div class="backdrop-grid">
    <?php 
    $icons = ['Self-Shoot'=>'🤳','Creative Shoot'=>'🎨','Family'=>'👨‍👩‍👧','Studio Rental'=>'🏢'];
    $colors = ['Self-Shoot'=>'#D4A0A0','Creative Shoot'=>'#D4C4A0','Family'=>'#A0C4A0','Studio Rental'=>'#B0C4DE'];
    foreach ($mainPackages as $main): 
      $icon = $icons[$main['name']] ?? '📷';
      $color = $colors[$main['name']] ?? '#B0C4DE';
    ?>
    <div class="backdrop-card" style="cursor:pointer;border-radius:16px;overflow:hidden;transition:all 0.3s ease;border:1px solid var(--border);" 
         onclick="showSubPackages(<?= $main['id'] ?>, '<?= clean($main['name']) ?>')"
         onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 8px 30px rgba(0,0,0,0.12)'"
         onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'">
      <div style="height:140px;background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc);display:flex;align-items:center;justify-content:center;font-size:56px;">
        <?= $icon ?>
      </div>
      <div style="padding:16px;">
        <h4 style="font-size:17px;font-weight:700;margin:0 0 4px;"><?= clean(strtoupper($main['name'])) ?></h4>
        <p style="font-size:12px;color:var(--muted);margin:0 0 10px;line-height:1.4;"><?= clean($main['description']) ?></p>
        <div style="font-size:12px;color:var(--dark2);margin-bottom:12px;">
          <?php 
          $feats = json_decode($main['features'] ?? '[]', true);
          if (is_array($feats)) {
              foreach (array_slice($feats, 0, 2) as $f): 
          ?>
            <div style="padding:2px 0;">✓ <?= clean($f) ?></div>
          <?php endforeach; } ?>
        </div>
        <button class="btn-black" style="width:100%;padding:10px;font-weight:600;font-size:13px;"
                onclick="event.stopPropagation(); showSubPackages(<?= $main['id'] ?>, '<?= clean($main['name']) ?>')">
          👁️ VIEW PACKAGES
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ===== STEP 2: SUB-PACKAGES ===== -->
<div id="sub-packages-section" style="display:none;margin-top:20px;">
  <div class="card">
    <div class="section-header">
      <h3 id="sub-packages-title"><strong>Select Package</strong></h3>
      <button class="btn-ghost btn-sm" onclick="backToMain()">← Back to Main</button>
    </div>
    <div id="sub-packages-grid"></div>
  </div>
</div>

<!-- ===== STEP 3: WALK-IN FORM ===== -->
<div id="booking-form-section" style="display:none;margin-top:20px;">
  <div class="card" style="max-width:1200px;">
    <div class="section-header">
      <h3><strong>Complete Walk-in Booking</strong></h3>
      <button class="btn-ghost btn-sm" onclick="clearBooking()">← Change Package</button>
    </div>

    <form method="POST" id="bookingForm" autocomplete="off">
      <?= function_exists('csrfField') ? csrfField() : '' ?>
      <input type="hidden" name="package_id" id="form-pkg-id">
      <input type="hidden" name="date" id="form-date" value="">
      <input type="hidden" name="time" id="selected_time" value="">

      <!-- CLIENT DETAILS -->
      <div style="margin-bottom:20px;">
        <div style="font-weight:700;font-size:12px;color:#0A0A0A;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">
          👤 Client Details
        </div>
        <div style="display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:14px;">
          <div>
            <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">
              Client Name <span style="color:#E74C3C;">*</span>
            </label>
            <input type="text" name="client_name" id="client_name" required placeholder="Full name" autocomplete="off"
                   style="width:100%;padding:11px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
          </div>
          <div>
            <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">
              Phone <span style="color:#E74C3C;">*</span>
            </label>
            <input type="tel" name="phone" id="phone" required placeholder="09XX XXX XXXX" autocomplete="off"
                   style="width:100%;padding:11px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
          </div>
          <div>
            <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">
              Email <span style="font-size:11px;color:#8B8177;font-weight:400;">(optional)</span>
            </label>
            <input type="email" name="email" id="email" placeholder="client@email.com" autocomplete="off"
                   style="width:100%;padding:11px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
          </div>
        </div>
      </div>

      <!-- LOYALTY BANNER -->
      <div id="loyaltyBanner" style="display:none;padding:14px 18px;background:#0A0A0A;color:#FFFFFF;border-radius:10px;margin-bottom:18px;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <div style="font-size:24px;">🎁</div>
          <div style="flex:1;min-width:200px;">
            <div style="font-weight:700;font-size:14px;" id="loyaltyTitle">Loyalty Bonus Available</div>
            <div style="font-size:12px;opacity:0.85;margin-top:2px;" id="loyaltySubtitle">—</div>
          </div>
          <div id="loyaltyBadge" style="padding:6px 14px;background:rgba(255,255,255,0.2);border-radius:50px;font-size:12px;font-weight:700;">—</div>
        </div>
      </div>

      <!-- TWO COLUMN LAYOUT (like booking.php) -->
      <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:24px;">
        
        <!-- LEFT: Calendar + People + Notes -->
        <div>
          <div style="padding:14px;background:#F5F5F5;border-radius:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
              <strong id="cal-month-label" style="color:#0A0A0A;"><?= date('F Y') ?></strong>
              <div style="display:flex;gap:6px;">
                <button class="btn-ghost btn-sm" type="button" onclick="changeCalMonth(-1)">❮</button>
                <button class="btn-ghost btn-sm" type="button" onclick="changeCalMonth(1)">❯</button>
              </div>
            </div>
            <div id="cal-grid" class="calendar-grid"></div>

            <div style="margin-top:14px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;font-size:11px;">
              <div style="display:flex;align-items:center;gap:6px;padding:6px;background:#E8F5E9;border:2px solid #2E7D32;border-radius:6px;">
                <div style="width:14px;height:14px;border-radius:3px;background:#2E7D32;flex-shrink:0;"></div>
                <strong style="color:#1B5E20;">Available</strong>
              </div>
              <div style="display:flex;align-items:center;gap:6px;padding:6px;background:#FFEBEE;border:2px solid #C62828;border-radius:6px;">
                <div style="width:14px;height:14px;border-radius:3px;background:#C62828;flex-shrink:0;"></div>
                <strong style="color:#8E0000;">Booked</strong>
              </div>
              <div style="display:flex;align-items:center;gap:6px;padding:6px;background:#EEEEEE;border:2px solid #9E9E9E;border-radius:6px;">
                <div style="width:14px;height:14px;border-radius:3px;background:#757575;flex-shrink:0;"></div>
                <strong style="color:#424242;">Past</strong>
              </div>
            </div>
          </div>

          <div style="padding:14px;background:#F5F5F5;border-radius:10px;margin-top:12px;">
            <div style="margin-bottom:12px;">
              <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">👥 Number of People</label>
              <input type="number" name="people" id="form-people" min="1" max="20" value="1"
                     style="width:100%;padding:11px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
            </div>
            <div>
              <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">📝 Notes</label>
              <textarea name="notes" id="notes" rows="3" placeholder="Special requests..."
                        style="width:100%;padding:11px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;font-family:inherit;resize:vertical;"></textarea>
            </div>
          </div>
        </div>

        <!-- RIGHT: Time picker + summary -->
        <div>
          <div style="padding:14px;background:#F5F5F5;border-radius:10px;position:sticky;top:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
              <div style="font-size:14px;font-weight:700;color:#0A0A0A;">🕐 Select Time</div>
              <div id="slot-date-label" style="font-size:12px;color:#8B8177;">—</div>
            </div>

            <div style="padding:12px;background:#FFFFFF;border-radius:8px;margin-bottom:12px;border:1px solid #E0E0E0;">
              <div style="font-size:11px;color:#8B8177;text-transform:uppercase;letter-spacing:0.5px;">Session</div>
              <div style="font-weight:700;font-size:14px;color:#0A0A0A;margin-top:2px;" id="pickerPkgName">— Pumili ng package</div>
              <div style="font-size:12px;color:#8B8177;margin-top:2px;" id="pickerDate">— Pumili ng petsa</div>
            </div>

            <div style="position:relative;margin-bottom:10px;">
              <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">⏰ Time <span style="color:#E74C3C;">*</span></label>
              <div style="display:flex;gap:8px;">
                <input type="text" id="time-input" placeholder="e.g., 10:30 AM" autocomplete="off"
                       style="flex:1;padding:12px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;font-weight:600;background:#FFFFFF;color:#0A0A0A;text-transform:uppercase;box-sizing:border-box;">
                <button type="button" id="quick-times-btn"
                        style="padding:12px 16px;background:#FFFFFF;color:#0A0A0A;border:1px solid #E0E0E0;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;white-space:nowrap;">
                  Quick Times ▼
                </button>
              </div>
              <div id="quick-times-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:6px;background:#FFFFFF;border:1px solid #E0E0E0;border-radius:8px;padding:8px;max-height:280px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:50;"></div>
            </div>

            <div id="time-status" style="margin-top:6px;font-size:13px;min-height:20px;"></div>

            <div id="sessionSummary" style="display:none;margin-top:14px;padding:14px;background:#FFFFFF;border-radius:8px;border:1px solid #E0E0E0;">
              <div style="font-weight:700;font-size:12px;color:#0A0A0A;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">📊 Session Preview</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                <div style="color:#8B8177;">Base Duration:</div>
                <div style="font-weight:600;text-align:right;color:#0A0A0A;" id="summaryBaseDuration">—</div>
                <div style="color:#8B8177;">Loyalty Bonus:</div>
                <div style="font-weight:600;text-align:right;color:#0A0A0A;" id="summaryBonus">—</div>
                <div style="color:#8B8177;">Final Duration:</div>
                <div style="font-weight:700;text-align:right;color:#0A0A0A;" id="summaryFinalDuration">—</div>
                <div style="color:#8B8177;border-top:1px solid #E0E0E0;padding-top:8px;margin-top:4px;">Package Price:</div>
                <div style="font-weight:600;text-align:right;color:#0A0A0A;border-top:1px solid #E0E0E0;padding-top:8px;margin-top:4px;" id="summaryPrice">—</div>
                <div style="color:#8B8177;">Discount:</div>
                <div style="font-weight:600;text-align:right;color:#0A0A0A;" id="summaryDiscount">—</div>
                <div style="color:#8B8177;">Final Price:</div>
                <div style="font-weight:700;text-align:right;color:#0A0A0A;" id="summaryFinalPrice">—</div>
                <div style="color:#8B8177;">Reservation Fee:</div>
                <div style="font-weight:600;text-align:right;color:#0A0A0A;">₱100.00</div>
                <div style="color:#8B8177;">Remaining Balance:</div>
                <div style="font-weight:700;text-align:right;color:#0A0A0A;" id="summaryRemaining">—</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:20px;">
        <a href="index.php?page=bookings"
           style="flex:1;min-width:160px;padding:14px;background:#F5F5F5;color:#0A0A0A;border:1px solid #E0E0E0;border-radius:8px;font-weight:600;font-size:14px;text-decoration:none;text-align:center;box-sizing:border-box;">
          ← Cancel
        </a>
        <button type="submit" id="submitBtn" disabled
                style="flex:2;min-width:220px;padding:14px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;opacity:0.5;">
          ✅ Create Walk-in Booking
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const RESERVATION_FEE = 100;
const OPEN_HOUR_MINUTES  = 10 * 60;
const CLOSE_HOUR_MINUTES = 19 * 60;
const SLOT_STEP_MINUTES  = 15;

let CLIENT_LOYALTY_COUNT = 0;
let CLIENT_LOYALTY_BONUS_MINUTES = 0;
let CLIENT_LOYALTY_DISCOUNT_PCT = 0;

let calYear = new Date().getFullYear();
let calMonth = new Date().getMonth();
let selectedPackageDuration = '';
let selectedPackageId = 0;
let selectedTime = '';
let selectedDate = '';

const mainPackages = <?= json_encode($mainPackages) ?>;
const bookedSlots = <?= json_encode($walkinBookedSlots) ?>;

function parseDurationHours(duration) {
    if (!duration) return 1;
    const d = String(duration).toLowerCase().trim();
    if (d.includes('half day') || d.includes('half-day')) return 4;
    if (d.includes('whole day') || d.includes('full day') || d.includes('whole-day') || d.includes('full-day')) return 8;
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

function getDurationInMinutes(duration) {
    return Math.round(parseDurationHours(duration) * 60);
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
    return `${displayHour}:${String(mins).padStart(2, '0')} ${ampm}`;
}

function formatMinutes(min) {
    if (min < 60) return min + ' min';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m === 0 ? h + ' hr' : h + ' hr ' + m + ' min';
}

function fmtMoney(n) {
    return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return months[parseInt(parts[1]) - 1] + ' ' + parseInt(parts[2]) + ', ' + parts[0];
}

function showToast(message, bg = '#E74C3C') {
    const existing = document.getElementById('walkin-toast');
    if (existing) existing.remove();
    const msg = document.createElement('div');
    msg.id = 'walkin-toast';
    msg.style.cssText = `position:fixed;bottom:20px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:12px;font-weight:600;z-index:9999;color:white;background:${bg};box-shadow:0 4px 15px rgba(0,0,0,0.2);font-family:Inter,sans-serif;max-width:90vw;`;
    msg.textContent = message;
    document.body.appendChild(msg);
    setTimeout(() => msg.remove(), 3000);
}

function getBookedSlotsForDate(date) {
    const booked = [];
    bookedSlots.filter(b => b.date === date).forEach(booking => {
        const startMinutes = parseTime(booking.time);
        const durationMinutes = parseInt(booking.effective_duration_minutes) || getDurationInMinutes(booking.pkg_duration || '1 hour');
        const endMinutes = startMinutes + durationMinutes;
        booked.push({
            start: booking.time, end: formatTime(endMinutes),
            startMinutes: startMinutes, endMinutes: endMinutes,
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
        if (b.status === 'Approved (Unpaid)' || b.status === 'Deposit Paid' || b.status === 'Confirmed' || b.status === 'In Progress' || b.status === 'Completed') {
            if (startMinutes < b.endMinutes && endMinutes > b.startMinutes) return true;
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
            if (startMinutes < b.endMinutes && endMinutes > b.startMinutes) return true;
        }
    }
    return false;
}

async function checkClientLoyalty() {
    const phone = document.getElementById('phone')?.value.trim() || '';
    const email = document.getElementById('email')?.value.trim() || '';

    if (!phone && !email) {
        CLIENT_LOYALTY_COUNT = 0; CLIENT_LOYALTY_BONUS_MINUTES = 0; CLIENT_LOYALTY_DISCOUNT_PCT = 0;
        updateLoyaltyDisplay(); updateSessionSummary(); renderCalendar();
        return;
    }

    try {
        const url = new URL('pages/api/check-client-loyalty.php', window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/'));
        if (phone) url.searchParams.set('phone', phone);
        if (email) url.searchParams.set('email', email);
        const res = await fetch(url.toString());
        const data = await res.json();
        if (data.success) {
            CLIENT_LOYALTY_COUNT = parseInt(data.loyalty_count) || 0;
            CLIENT_LOYALTY_BONUS_MINUTES = parseInt(data.bonus_minutes) || 0;
            CLIENT_LOYALTY_DISCOUNT_PCT  = parseInt(data.discount_pct) || 0;
        } else {
            CLIENT_LOYALTY_COUNT = 0; CLIENT_LOYALTY_BONUS_MINUTES = 0; CLIENT_LOYALTY_DISCOUNT_PCT = 0;
        }
    } catch (err) {
        CLIENT_LOYALTY_COUNT = 0; CLIENT_LOYALTY_BONUS_MINUTES = 0; CLIENT_LOYALTY_DISCOUNT_PCT = 0;
    }
    updateLoyaltyDisplay(); updateSessionSummary(); renderCalendar();
}

function updateLoyaltyDisplay() {
    const banner = document.getElementById('loyaltyBanner');
    if (!banner) return;
    if (CLIENT_LOYALTY_COUNT > 0) {
        banner.style.display = 'block';
        const parts = [];
        if (CLIENT_LOYALTY_BONUS_MINUTES > 0) parts.push('+' + CLIENT_LOYALTY_BONUS_MINUTES + ' MIN');
        if (CLIENT_LOYALTY_DISCOUNT_PCT > 0)  parts.push(CLIENT_LOYALTY_DISCOUNT_PCT + '% OFF');
        document.getElementById('loyaltyTitle').textContent = '🎁 Loyalty Client Detected';
        document.getElementById('loyaltySubtitle').textContent = CLIENT_LOYALTY_COUNT + ' previous booking' +
            (CLIENT_LOYALTY_COUNT > 1 ? 's' : '') + ' · ' +
            (parts.length > 0 ? 'Entitled: ' + parts.join(' · ') : 'No bonus');
        document.getElementById('loyaltyBadge').textContent = '🎫 ' + CLIENT_LOYALTY_COUNT + ' BOOKINGS';
    } else {
        banner.style.display = 'none';
    }
}

function updateSessionSummary() {
    const summary = document.getElementById('sessionSummary');
    const pkgInfoBox = document.getElementById('pkgInfoBox');
    if (!selectedPackageId || !selectedPackageDuration) {
        if (summary) summary.style.display = 'none';
        if (pkgInfoBox) pkgInfoBox.style.display = 'none';
        return;
    }

    // Find package details from mainPackages
    let pkgObj = null;
    mainPackages.forEach(m => {
        (m.sub_packages || []).forEach(s => {
            if (s.id === selectedPackageId) pkgObj = s;
        });
    });
    if (!pkgObj) return;

    const basePrice = parseFloat(pkgObj.price) || 0;
    const baseDuration = getDurationInMinutes(selectedPackageDuration);
    let bonus = CLIENT_LOYALTY_BONUS_MINUTES;
    if (CLIENT_LOYALTY_DISCOUNT_PCT > 0) bonus = 0;
    const finalDuration = baseDuration + bonus;
    const discountPct = CLIENT_LOYALTY_DISCOUNT_PCT;
    const finalPrice = discountPct > 0 ? basePrice * (100 - discountPct) / 100 : basePrice;
    const remaining = Math.max(0, finalPrice - RESERVATION_FEE);

    if (summary) summary.style.display = 'block';
    document.getElementById('summaryBaseDuration').textContent = formatMinutes(baseDuration);
    document.getElementById('summaryBonus').textContent = bonus > 0 ? '+' + bonus + ' min' : '—';
    document.getElementById('summaryFinalDuration').textContent = formatMinutes(finalDuration);
    document.getElementById('summaryPrice').textContent = fmtMoney(basePrice);
    document.getElementById('summaryDiscount').textContent = discountPct > 0 ? discountPct + '% OFF' : '—';
    document.getElementById('summaryFinalPrice').textContent = fmtMoney(finalPrice);
    document.getElementById('summaryRemaining').textContent = fmtMoney(remaining);
}

function checkTimeAvailability(timeStr) {
    const statusDiv = document.getElementById('time-status');
    const submitBtn = document.getElementById('submitBtn');

    if (!selectedDate) { statusDiv.innerHTML = '<span style="color:#8B8177;">⚠️ Select date first</span>'; submitBtn.disabled = true; selectedTime = ''; return; }
    if (!selectedPackageDuration) { statusDiv.innerHTML = '<span style="color:#8B8177;">⚠️ Select package first</span>'; submitBtn.disabled = true; selectedTime = ''; return; }
    if (!timeStr || timeStr.length < 4) { statusDiv.innerHTML = ''; submitBtn.disabled = true; selectedTime = ''; return; }

    const durationMinutes = getDurationInMinutes(selectedPackageDuration);
    const timeMinutes = parseTime(timeStr);

    if (timeMinutes === 0) { statusDiv.innerHTML = '<span style="color:#C62828;">❌ Invalid time. Use: 10:30 AM</span>'; submitBtn.disabled = true; return; }
    if (timeMinutes < OPEN_HOUR_MINUTES) { statusDiv.innerHTML = '<span style="color:#C62828;">❌ Opens at 10:00 AM</span>'; submitBtn.disabled = true; return; }
    if (timeMinutes >= CLOSE_HOUR_MINUTES) { statusDiv.innerHTML = '<span style="color:#C62828;">❌ Closes at 7:00 PM</span>'; submitBtn.disabled = true; return; }
    const endMinutes = timeMinutes + durationMinutes;
    if (endMinutes > CLOSE_HOUR_MINUTES) { statusDiv.innerHTML = `<span style="color:#C62828;">❌ Ends past 7:00 PM (${formatTime(endMinutes)})</span>`; submitBtn.disabled = true; return; }

    if (isSlotConfirmed(selectedDate, timeStr, selectedPackageDuration)) {
        statusDiv.innerHTML = `<div style="padding:10px;background:#FEE2E2;border-radius:6px;border-left:4px solid #C62828;"><div style="color:#8E0000;font-weight:700;font-size:13px;">❌ Conflict Detected</div><div style="font-size:12px;color:#8E0000;">Slot is already booked.</div></div>`;
        submitBtn.disabled = true; selectedTime = ''; return;
    }

    const hasPending = isSlotPending(selectedDate, timeStr, selectedPackageDuration);
    selectedTime = timeStr;
    document.getElementById('selected_time').value = timeStr;
    const endTime = formatTime(endMinutes);

    if (hasPending) {
        statusDiv.innerHTML = `<span style="color:#92400E;font-weight:600;">⚠️ May pending request ito.</span>`;
    } else {
        statusDiv.innerHTML = `<span style="color:#1B5E20;font-weight:600;">✅ Available! ${timeStr} → ${endTime}</span>`;
    }
    submitBtn.disabled = false;
    submitBtn.style.opacity = '1';
}

function toggleQuickTimes() {
    const dropdown = document.getElementById('quick-times-dropdown');
    if (dropdown.style.display === 'block') { dropdown.style.display = 'none'; return; }
    if (!selectedDate) { showToast('Pumili muna ng date', '#E74C3C'); return; }
    if (!selectedPackageDuration) { showToast('Pumili muna ng package', '#E74C3C'); return; }

    const durationMinutes = getDurationInMinutes(selectedPackageDuration);
    const availableSlots = [];
    const pendingSlots = [];

    for (let timeMinutes = OPEN_HOUR_MINUTES; timeMinutes + durationMinutes <= CLOSE_HOUR_MINUTES; timeMinutes += SLOT_STEP_MINUTES) {
        const timeStr = formatTime(timeMinutes);
        if (isSlotConfirmed(selectedDate, timeStr, selectedPackageDuration)) continue;
        const slot = { start: timeStr, end: formatTime(timeMinutes + durationMinutes), isPending: isSlotPending(selectedDate, timeStr, selectedPackageDuration) };
        if (slot.isPending) pendingSlots.push(slot); else availableSlots.push(slot);
    }

    let html = '';
    if (availableSlots.length === 0 && pendingSlots.length === 0) {
        html = `<div style="text-align:center;padding:20px;color:#C62828;"><div style="font-size:32px;margin-bottom:6px;">❌</div><div style="font-weight:600;">Walang available</div></div>`;
    } else {
        if (availableSlots.length > 0) {
            html += `<div style="font-size:10px;color:#1B5E20;font-weight:700;padding:4px 6px;border-bottom:1px solid #E0E0E0;margin-bottom:4px;">✅ Available (${availableSlots.length})</div>`;
            availableSlots.forEach(slot => {
                html += `<div onclick="selectQuickTime('${slot.start}')" style="padding:8px 10px;cursor:pointer;border-radius:6px;margin:2px 0;background:#E8F5E9;color:#1B5E20;font-size:12px;display:flex;justify-content:space-between;align-items:center;font-weight:600;"><span>${slot.start} → ${slot.end}</span><span>✅</span></div>`;
            });
        }
        if (pendingSlots.length > 0) {
            html += `<div style="font-size:10px;color:#92400E;font-weight:700;padding:4px 6px;border-bottom:1px solid #E0E0E0;margin:8px 0 4px;">⏳ Pending (${pendingSlots.length})</div>`;
            pendingSlots.forEach(slot => {
                html += `<div onclick="selectQuickTime('${slot.start}')" style="padding:8px 10px;cursor:pointer;border-radius:6px;margin:2px 0;background:#FEF3C7;color:#92400E;font-size:12px;display:flex;justify-content:space-between;align-items:center;font-weight:600;"><span>${slot.start} → ${slot.end}</span><span>⏳</span></div>`;
            });
        }
    }
    dropdown.innerHTML = html;
    dropdown.style.display = 'block';
}

function selectQuickTime(timeStr) {
    document.getElementById('time-input').value = timeStr;
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
    const today = new Date(); today.setHours(0, 0, 0, 0);
    for (let i = 0; i < firstDay; i++) grid.appendChild(document.createElement('div'));

    for (let d = 1; d <= lastDate; d++) {
        const ds = calYear + '-' + String(calMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        const dObj = new Date(calYear, calMonth, d);
        const isPast = dObj < today;
        let hasAvailable = false;
        if (!isPast && selectedPackageDuration) hasAvailable = hasAnyAvailableSlot(ds, selectedPackageDuration);

        let cls = 'calendar-date';
        let statusText = '';
        if (isPast) { cls += ' date-past'; statusText = 'Past'; }
        else if (!hasAvailable && selectedPackageDuration) { cls += ' date-booked'; statusText = 'Fully Booked'; }
        else { cls += ' date-available'; statusText = 'Available'; }

        const cell = document.createElement('div');
        cell.className = cls;
        cell.textContent = d;
        cell.dataset.date = ds;
        cell.title = statusText;
        if (ds === selectedDate) cell.style.outline = '2px solid #0A0A0A';

        cell.onclick = () => {
            if (!cell.classList.contains('date-past') && !cell.classList.contains('date-booked')) {
                selectedDate = ds;
                document.getElementById('form-date').value = ds;
                document.getElementById('time-input').value = '';
                document.getElementById('selected_time').value = '';
                selectedTime = '';
                document.getElementById('submitBtn').disabled = true;
                document.getElementById('submitBtn').style.opacity = '0.5';
                document.getElementById('time-status').innerHTML = '';
                document.querySelectorAll('.calendar-date').forEach(c => c.style.outline = '');
                cell.style.outline = '2px solid #0A0A0A';
                updatePickerInfo();
            } else if (cell.classList.contains('date-booked')) {
                showToast('This date is fully booked.', '#E74C3C');
            }
        };
        grid.appendChild(cell);
    }
}

function hasAnyAvailableSlot(date, duration) {
    const durationMinutes = getDurationInMinutes(duration);
    for (let timeMinutes = OPEN_HOUR_MINUTES; timeMinutes + durationMinutes <= CLOSE_HOUR_MINUTES; timeMinutes += SLOT_STEP_MINUTES) {
        const timeStr = formatTime(timeMinutes);
        if (!isSlotConfirmed(date, timeStr, duration)) return true;
    }
    return false;
}

function changeCalMonth(delta) {
    calMonth += delta;
    if (calMonth > 11) { calMonth = 0; calYear++; }
    if (calMonth < 0)  { calMonth = 11; calYear--; }
    renderCalendar();
}

function updatePickerInfo() {
    document.getElementById('pickerDate').textContent = selectedDate ? formatDateDisplay(selectedDate) : '— Pumili ng petsa';
    document.getElementById('slot-date-label').textContent = selectedDate ? formatDateDisplay(selectedDate) : '—';

    if (selectedPackageId) {
        let pkgName = '—';
        mainPackages.forEach(m => {
            (m.sub_packages || []).forEach(s => { if (s.id === selectedPackageId) pkgName = s.name; });
        });
        document.getElementById('pickerPkgName').textContent = pkgName;
    }
}

// ============================================================
// SUB-PACKAGE NAVIGATION
// ============================================================
const sampleImages = {
    'Package A': 'assets/package a.png', 'Package B': 'assets/package b.png', 'Package C': 'assets/package c.png',
    'Package D': 'assets/package d.png', 'Package E': 'assets/package e.png', 'Package F': 'assets/package f.png',
    'Classic': 'assets/classic package.png', 'Premium': 'assets/premium package.png',
    '3 Pax': 'assets/3 pax.png', '4-6 Pax': 'assets/4-6pax.png', '7-10 Pax': 'assets/7-10pax.png',
    'Basic': 'assets/basic package.png', 'Half Day': 'assets/halfday package.png'
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
        grid.innerHTML = '<div style="grid-column:span 4;text-align:center;color:var(--muted);padding:30px;">No sub-packages.</div>';
        return;
    }

    main.sub_packages.forEach(sub => {
        let feats = [];
        try { feats = JSON.parse(sub.features || '[]'); } catch(e) { feats = []; }
        const img = sampleImages[sub.name] || 'assets/default-package.png';
        const price = Number(sub.price).toLocaleString();
        const popularBadge = sub.is_popular ? `<span class="popular-badge">🔥 POPULAR</span>` : '';
        let featuresHtml = '';
        feats.forEach(f => { featuresHtml += `<li>${f}</li>`; });
        const printHtml = sub.print_inclusions ? `<div class="print-inclusions">🖼️ ${sub.print_inclusions}</div>` : '';
        const maxPaxHtml = sub.max_pax ? `<div class="max-pax">👥 Max ${sub.max_pax} persons</div>` : '';

        const card = document.createElement('div');
        card.className = 'package-card';
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
    selectedPackageId = id;
    selectedPackageDuration = duration;
    selectedTime = '';

    document.getElementById('form-pkg-id').value = id;
    document.getElementById('sub-packages-section').style.display = 'none';
    document.getElementById('booking-form-section').style.display = 'block';

    updateSessionSummary();
    updatePickerInfo();
    renderCalendar();

    const today = new Date().toISOString().split('T')[0];
    selectedDate = today;
    document.getElementById('form-date').value = today;
    updatePickerInfo();

    document.getElementById('booking-form-section').scrollIntoView({ behavior: 'smooth' });
}

function clearBooking() {
    document.getElementById('booking-form-section').style.display = 'none';
    document.getElementById('sub-packages-section').style.display = 'block';
    document.getElementById('form-date').value = '';
    document.getElementById('time-input').value = '';
    document.getElementById('selected_time').value = '';
    document.getElementById('time-status').innerHTML = '';
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('sessionSummary').style.display = 'none';
    selectedTime = '';
    selectedPackageId = 0;
    selectedPackageDuration = '';
}

document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone');
    const emailInput = document.getElementById('email');
    const form = document.getElementById('bookingForm');
    const timeInput = document.getElementById('time-input');
    const quickBtn = document.getElementById('quick-times-btn');

    renderCalendar();
    const today = new Date().toISOString().split('T')[0];
    selectedDate = today;
    document.getElementById('form-date').value = today;

    timeInput.addEventListener('input', function() { checkTimeAvailability(this.value); });
    quickBtn.addEventListener('click', toggleQuickTimes);

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('quick-times-dropdown');
        if (!dropdown || dropdown.style.display !== 'block') return;
        if (e.target.closest('#quick-times-dropdown')) return;
        if (e.target.closest('#quick-times-btn')) return;
        dropdown.style.display = 'none';
    });

    let loyaltyTimer = null;
    function debouncedLoyaltyCheck() {
        clearTimeout(loyaltyTimer);
        loyaltyTimer = setTimeout(checkClientLoyalty, 500);
    }
    phoneInput.addEventListener('input', debouncedLoyaltyCheck);
    emailInput.addEventListener('input', debouncedLoyaltyCheck);

    form.addEventListener('submit', function(e) {
        if (!document.getElementById('selected_time').value) { e.preventDefault(); showToast('Please select a valid time slot.', '#E74C3C'); }
        if (!document.getElementById('form-date').value) { e.preventDefault(); showToast('Please select a date.', '#E74C3C'); }
    });
});
</script>

<style>
.backdrop-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
#sub-packages-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; align-items: stretch; }

.package-card {
    border: 2px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: 0.3s;
    background: white;
    display: flex;
    flex-direction: column;
    min-height: 560px;
    position: relative;
    cursor: pointer;
}
.package-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.12); }
.package-card .popular-badge { position: absolute; top: 12px; right: 12px; background: var(--dark); color: white; padding: 4px 12px; border-radius: 50px; font-size: 10px; font-weight: 700; z-index: 5; }
.package-card .card-image { height: 200px; position: relative; flex-shrink: 0; overflow: hidden; background: #ffffff; display: flex; align-items: center; justify-content: center; }
.package-card .card-image .package-img { width: 100%; height: 100%; object-fit: contain; object-position: center; display: block; padding: 4px; }
.package-card .card-image .overlay { position: absolute; bottom: 0; left: 0; right: 0; padding: 8px 14px; background: linear-gradient(transparent, rgba(0,0,0,0.75)); pointer-events: none; }
.package-card .card-image .overlay .name { color: white; font-weight: 700; font-size: 16px; }
.package-card .card-image .overlay .duration { color: rgba(255,255,255,0.95); font-size: 11px; }
.package-card .card-body { padding: 12px 14px 14px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
.package-card .card-body .price { font-size: 22px; font-weight: 700; color: var(--dark); line-height: 1.2; }
.package-card .card-body .max-pax { font-size: 11px; color: var(--muted); }
.package-card .card-body .features { flex: 0 0 auto; margin: 4px 0 8px; padding: 0; list-style: none; }
.package-card .card-body .features li { padding: 2px 0; color: var(--dark2); font-size: 11px; display: flex; align-items: flex-start; gap: 4px; line-height: 1.35; }
.package-card .card-body .features li::before { content: "✓"; color: var(--green); font-weight: 700; flex-shrink: 0; }
.package-card .card-body .print-inclusions { margin-top: auto; margin-bottom: 8px; padding: 5px 10px; background: var(--amber-bg); border-radius: 6px; font-size: 10px; color: var(--amber-text); border: 1px solid var(--amber); }
.package-card .card-body .btn-book { margin-top: 0; width: 100%; padding: 9px; font-size: 13px; font-weight: 600; background: var(--dark); color: white; border: none; border-radius: 50px; cursor: pointer; transition: 0.2s; }
.package-card .card-body .btn-book:hover { background: #1A1A1A; transform: scale(1.02); }

.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.calendar-day-label { font-size: 11px; font-weight: 700; color: var(--muted); text-align: center; padding: 4px 0; }
.calendar-date { text-align: center; padding: 6px 0; border-radius: 6px; font-size: 13px; cursor: pointer; transition: 0.2s; font-weight: 600; }
.calendar-date.date-available { background: var(--green-bg); color: #1B5E20; border: 2px solid #2E7D32; }
.calendar-date.date-available:hover { background: #2E7D32; color: white; transform: scale(1.08); }
.calendar-date.date-booked { background: var(--red-bg); color: #8E0000; border: 2px solid #C62828; cursor: not-allowed; opacity: 0.75; }
.calendar-date.date-past { background: #EEEEEE; color: #616161; border: 2px solid #BDBDBD; cursor: not-allowed; opacity: 0.6; }

@media (max-width: 1024px) { .backdrop-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) { .backdrop-grid { grid-template-columns: 1fr 1fr; gap: 12px; } #sub-packages-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .backdrop-grid { grid-template-columns: 1fr; } #sub-packages-grid { grid-template-columns: 1fr; } }

#quick-times-dropdown::-webkit-scrollbar { width: 4px; }
#quick-times-dropdown::-webkit-scrollbar-track { background: #F5F5F5; border-radius: 4px; }
#quick-times-dropdown::-webkit-scrollbar-thumb { background: #BDBDBD; border-radius: 4px; }

@media (max-width: 900px) {
    .card > form > div[style*="grid-template-columns:1fr 1.2fr"] { grid-template-columns: 1fr !important; }
}
</style>