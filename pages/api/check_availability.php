<?php
// pages/api/check_availability.php
// WITH LOYALTY BONUS SUPPORT
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = currentUser();
if (!in_array($user['role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$date          = $_GET['date'] ?? '';
$package_id    = (int)($_GET['package_id'] ?? 0);
$loyalty_bonus = (int)($_GET['loyalty_bonus'] ?? 0);  // ← NEW: bonus minutes

if (empty($date)) {
    echo json_encode(['success' => false, 'error' => 'Date is required']);
    exit;
}
if ($package_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Package is required']);
    exit;
}

// Cap bonus to 60 minutes (safety)
if ($loyalty_bonus < 0)   $loyalty_bonus = 0;
if ($loyalty_bonus > 60)  $loyalty_bonus = 60;

$pdo = db();

// ============================================================
// STUDIO OPERATING HOURS (10 AM – 7 PM)
// ============================================================
define('STUDIO_OPEN_MIN',  10 * 60);   // 10:00 AM
define('STUDIO_CLOSE_MIN', 19 * 60);   // 7:00 PM
define('SLOT_STEP_MIN',    15);        // 15-min grid

// ============================================================
// HELPERS
// ============================================================
if (!function_exists('parseTimeToMinutes')) {
    function parseTimeToMinutes($timeStr) {
        if (!$timeStr) return null;
        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $timeStr, $m)) {
            $h = (int)$m[1]; $mm = (int)$m[2]; $ap = strtoupper($m[3]);
            if ($ap === 'PM' && $h !== 12) $h += 12;
            if ($ap === 'AM' && $h === 12) $h = 0;
            return ($h * 60) + $mm;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $m)) {
            return ((int)$m[1] * 60) + (int)$m[2];
        }
        return null;
    }
}

if (!function_exists('formatMinutesToTime')) {
    function formatMinutesToTime($minutes) {
        $minutes = ((int)$minutes) % 1440;
        if ($minutes < 0) $minutes += 1440;
        $h = intdiv($minutes, 60);
        $mm = $minutes % 60;
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $displayH = $h > 12 ? $h - 12 : ($h === 0 ? 12 : $h);
        return sprintf('%d:%02d %s', $displayH, $mm, $ampm);
    }
}

if (!function_exists('extractMinutesFromDuration')) {
    function extractMinutesFromDuration($duration) {
        if ($duration === null || $duration === '') return 60;
        $d = strtolower(trim((string)$duration));

        // Half day / whole day
        if (strpos($d, 'half day') !== false || strpos($d, 'half-day') !== false) return 240;
        if (strpos($d, 'whole day') !== false || strpos($d, 'full day') !== false
            || strpos($d, 'whole-day') !== false || strpos($d, 'full-day') !== false) return 480;

        // RANGE: "15-45 mins" or "1-4 hours" — use MAX
        if (preg_match('/(\d+)\s*-\s*(\d+)\s*(hour|hr)/', $d, $m)) {
            return ((int)$m[2]) * 60;
        }
        if (preg_match('/(\d+)\s*-\s*(\d+)\s*(min|minute)/', $d, $m)) {
            return (int)$m[2];
        }

        // "1 hour 30 mins"
        if (preg_match('/(\d+)\s*(?:hour|hr)\s*(\d+)\s*(?:min|minute)/', $d, $m)) {
            return ((int)$m[1] * 60) + (int)$m[2];
        }

        // "1.5 hours"
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:hour|hr)/', $d, $m)) {
            return (int)round((float)$m[1] * 60);
        }

        // "45 mins"
        if (preg_match('/(\d+)\s*(?:min|minute)/', $d, $m)) {
            return (int)$m[1];
        }

        // Fallback: treat as hours
        if (preg_match('/(\d+(?:\.\d+)?)/', $d, $m)) {
            return (int)round((float)$m[1] * 60);
        }

        return 60;
    }
}

// ============================================================
// GET PACKAGE
// ============================================================
$pkgStmt = $pdo->prepare("SELECT name, duration, price FROM packages WHERE id = ? AND is_active = 1");
$pkgStmt->execute([$package_id]);
$pkg = $pkgStmt->fetch();
if (!$pkg) {
    echo json_encode(['success' => false, 'error' => 'Invalid package']);
    exit;
}

$baseDurationMin = extractMinutesFromDuration($pkg['duration'] ?? '1 hour');
if ($baseDurationMin <= 0) $baseDurationMin = 60;

// ============================================================
// APPLY LOYALTY BONUS
// ============================================================
$finalDurationMin = $baseDurationMin + $loyalty_bonus;

// Safety: make sure final duration fits in studio hours
$maxAllowed = STUDIO_CLOSE_MIN - STUDIO_OPEN_MIN; // 540 min (9 hours)
if ($finalDurationMin > $maxAllowed) {
    $finalDurationMin = $maxAllowed;
    $loyalty_bonus = $finalDurationMin - $baseDurationMin;
}

// ============================================================
// GET ALL BOOKINGS FOR THE DATE
// ============================================================
$bookedStmt = $pdo->prepare("
    SELECT b.time, b.status, p.duration, p.name AS package_name
    FROM bookings b
    JOIN packages p ON b.package_id = p.id
    WHERE b.date = ?
      AND b.status NOT IN ('Cancelled', 'Rejected')
");
$bookedStmt->execute([$date]);
$existingBookings = $bookedStmt->fetchAll();

// ============================================================
// BUILD BLOCKED INTERVALS
// ============================================================
$confirmedIntervals = [];  // RED
$pendingIntervals   = [];  // YELLOW

foreach ($existingBookings as $b) {
    $startMin = parseTimeToMinutes($b['time']);
    if ($startMin === null) continue;

    $exDurMin = extractMinutesFromDuration($b['duration'] ?? '1 hour');
    if ($exDurMin <= 0) $exDurMin = 60;
    $endMin = $startMin + $exDurMin;

    $interval = [
        'start' => $startMin,
        'end'   => $endMin,
        'label' => ($b['package_name'] ?? '') . ' (' . formatMinutesToTime($startMin) . ' → ' . formatMinutesToTime($endMin) . ')',
        'status' => $b['status'],
    ];

    if ($b['status'] === 'Awaiting Approval') {
        $pendingIntervals[] = $interval;
    } else {
        $confirmedIntervals[] = $interval;
    }
}

// ============================================================
// GENERATE SLOTS WITH 3-COLOR STATUS (using FINAL duration)
// ============================================================
$slots = [];
$availableCount = 0;
$pendingCount = 0;
$bookedCount = 0;

for ($start = STUDIO_OPEN_MIN; $start + $finalDurationMin <= STUDIO_CLOSE_MIN; $start += SLOT_STEP_MIN) {
    $end = $start + $finalDurationMin;

    // Check against CONFIRMED bookings first (RED takes priority)
    $isConfirmedBlock = false;
    foreach ($confirmedIntervals as $b) {
        if ($start < $b['end'] && $end > $b['start']) {
            $isConfirmedBlock = true;
            break;
        }
    }

    // Check against PENDING bookings
    $isPendingBlock = false;
    if (!$isConfirmedBlock) {
        foreach ($pendingIntervals as $b) {
            if ($start < $b['end'] && $end > $b['start']) {
                $isPendingBlock = true;
                break;
            }
        }
    }

    // Determine status: RED > YELLOW > GREEN
    if ($isConfirmedBlock) {
        $status = 'booked';
        $slotClass = 'booked';
        $slotLabel = 'Fully Booked';
        $bookedCount++;
    } elseif ($isPendingBlock) {
        $status = 'pending';
        $slotClass = 'pending';
        $slotLabel = 'Pending Approval';
        $pendingCount++;
    } else {
        $status = 'available';
        $slotClass = 'available';
        $slotLabel = 'Available';
        $availableCount++;
    }

    $slots[] = [
        'time'        => formatMinutesToTime($start),
        'end_time'    => formatMinutesToTime($end),
        'minutes'     => $start,
        'end_minutes' => $end,
        'available'   => ($status === 'available'),
        'status'      => $status,
        'slot_class'  => $slotClass,
        'slot_label'  => $slotLabel,
    ];
}

// ============================================================
// RESPONSE
// ============================================================
echo json_encode([
    'success'              => true,
    'date'                 => $date,
    'package_id'           => $package_id,
    'package_name'         => $pkg['name'],
    'base_duration_min'    => $baseDurationMin,
    'loyalty_bonus_min'    => $loyalty_bonus,
    'package_duration_min' => $finalDurationMin,
    'slots'                => $slots,
    'booked_count'         => $bookedCount,
    'pending_count'        => $pendingCount,
    'available_count'      => $availableCount,
    'total_slots'          => count($slots),
    'studio_hours'         => '10:00 AM - 7:00 PM',
    'step_minutes'         => SLOT_STEP_MIN,
]);