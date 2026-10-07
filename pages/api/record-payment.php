<?php
// ============================================================
// STAFF RECORD PAYMENT API
// Supports: RESERVATION (₱100) + BALANCE (remaining)
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);  // ⚠️ Set to 0 in production

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';

requireLogin();
requireRole(['staff', 'admin']);

header('Content-Type: application/json; charset=utf-8');

// ============================================================
// RESERVATION FEE
// ============================================================
const RESERVATION_FEE = 100;

// ============================================================
// HANDLE BOTH JSON AND FORMDATA INPUTS
// ============================================================
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    // JSON input
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    // FormData (may file upload)
    $input = $_POST;
}

$bookingId   = (int)($input['booking_id']   ?? 0);
$paymentType = strtoupper($input['payment_type'] ?? 'RESERVATION');
$method      = $input['method'] ?? 'Cash';
$ref         = trim($input['ref'] ?? 'On-site payment');

// Validate payment type
if (!in_array($paymentType, ['RESERVATION', 'BALANCE', 'FULL'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment type: ' . $paymentType]);
    exit;
}

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
    exit;
}

$pdo = db();
$currentUser = currentUser();
$uid = $currentUser['id'];

try {
    // ============================================================
    // GET BOOKING DETAILS
    // ============================================================
    $stmt = $pdo->prepare("
        SELECT b.*, 
               b.id            AS booking_id,
               b.user_id,
               b.booking_ref,
               b.package_price,
               b.deposit_amount,
               b.deposit_paid,
               b.remaining_balance,
               b.fully_paid,
               b.status,
               u.name          AS client_name, 
               p.name          AS pkg_name 
        FROM bookings b 
        JOIN users    u ON b.user_id    = u.id 
        JOIN packages p ON b.package_id = p.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Check if already completed/cancelled
    if (in_array($booking['status'], ['Completed', 'Cancelled'])) {
        echo json_encode(['success' => false, 'message' => 'Booking already ' . $booking['status']]);
        exit;
    }

    // ============================================================
    // HANDLE PROOF UPLOAD (OPTIONAL)
    // ============================================================
    $proofFilename = null;

    if (!empty($_FILES['proof']['name']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext     = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.']);
            exit;
        }

        if ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File too large. Max 5 MB.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../../assets/uploads/payments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $proofFilename = 'pay_onsite_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath    = $uploadDir . $proofFilename;

        if (!move_uploaded_file($_FILES['proof']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
            exit;
        }
    }

    // ============================================================
    // COMPUTE PRICES (with loyalty discount)
    // ============================================================
    $lcStmt = $pdo->prepare("SELECT total_bookings FROM loyalty_cards WHERE user_id = ? LIMIT 1");
    $lcStmt->execute([$booking['user_id']]);
    $lc = $lcStmt->fetch();
    $loyaltyCount = $lc ? (int)$lc['total_bookings'] : 0;
    $hasLoyalty   = $loyaltyCount >= 10;

    $originalPrice  = (float)$booking['package_price'];
    $effectivePrice = $hasLoyalty ? round($originalPrice * 0.5, 2) : $originalPrice;

    $reservationFee  = RESERVATION_FEE;
    $balanceAmount   = max(0, round($effectivePrice - $reservationFee, 2));

    // ============================================================
    // DETERMINE AMOUNT BASED ON PAYMENT TYPE
    // ============================================================
    $depositPaid = (int)$booking['deposit_paid'] === 1;
    $fullyPaid   = (int)$booking['fully_paid'] === 1;

    if ($paymentType === 'BALANCE' || $paymentType === 'FULL') {
        // Balance payment or full settlement
        if (!$depositPaid) {
            // Walang reservation pa — babayaran buo (reservation + balance)
            $amount       = $effectivePrice;
            $payType      = 'FULL';
            $newRemaining = 0;
            $newFullyPaid = 1;
        } else {
            // Reservation paid na — babayaran balance lang
            $amount       = $balanceAmount;
            $payType      = 'BALANCE';
            $newRemaining = 0;
            $newFullyPaid = 1;
        }

    } else {
        // RESERVATION — ₱100 flat
        if ($depositPaid) {
            echo json_encode(['success' => false, 'message' => 'Reservation fee already paid.']);
            exit;
        }

        $amount       = $reservationFee;
        $payType      = 'RESERVATION';
        $newRemaining = $balanceAmount;
        $newFullyPaid = 0;
    }

    // ============================================================
    // BEGIN TRANSACTION
    // ============================================================
    $pdo->beginTransaction();

    // ============================================================
    // CHECK FOR EXISTING PAYMENT RECORD
    // ============================================================
    $existingStmt = $pdo->prepare("
        SELECT id, amount, type, status 
        FROM payments 
        WHERE booking_id = ? 
          AND type = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $existingStmt->execute([$bookingId, $payType]);
    $existing = $existingStmt->fetch();

    // ============================================================
    // IF EXISTING UNPAID/RESERVATION RECORD, UPDATE
    // ELSE INSERT NEW
    // ============================================================
    if ($existing && in_array($existing['status'], ['UNPAID', 'REJECTED'])) {
        // Update existing record
        $upd = $pdo->prepare("
            UPDATE payments 
            SET amount = ?,
                method = ?,
                ref_number = ?,
                proof_image = COALESCE(?, proof_image),
                status = 'PAID',
                verified_by = ?,
                verified_at = NOW(),
                rejection_reason = NULL
            WHERE id = ?
        ");
        $upd->execute([$amount, $method, $ref, $proofFilename, $uid, $existing['id']]);
        $paymentMessage = 'Existing ' . $payType . ' record updated to PAID';

    } else {
        // Insert new payment record
        $payRef = 'PAY-ONSITE-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -3));

        $ins = $pdo->prepare("
            INSERT INTO payments (
                payment_ref, 
                booking_id, 
                user_id, 
                amount, 
                type, 
                method, 
                ref_number, 
                proof_image,
                status, 
                verified_by, 
                verified_at, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PAID', ?, NOW(), NOW())
        ");
        $ins->execute([
            $payRef,
            $bookingId,
            $booking['user_id'],
            $amount,
            $payType,
            $method,
            $ref,
            $proofFilename,
            $uid
        ]);
        $paymentMessage = 'New ' . $payType . ' payment recorded';
    }

    // ============================================================
    // UPDATE BOOKING STATUS
    // ============================================================
    if ($payType === 'RESERVATION') {
        $upd = $pdo->prepare("
            UPDATE bookings SET 
                status = 'Deposit Paid',
                deposit_paid = 1,
                remaining_balance = ?,
                fully_paid = 0
            WHERE id = ?
        ");
        $upd->execute([$newRemaining, $bookingId]);

    } elseif ($payType === 'BALANCE') {
        $upd = $pdo->prepare("
            UPDATE bookings SET 
                status = 'Confirmed',
                deposit_paid = 1,
                remaining_balance = 0,
                fully_paid = 1
            WHERE id = ?
        ");
        $upd->execute([$bookingId]);

    } else { // FULL
        $upd = $pdo->prepare("
            UPDATE bookings SET 
                status = 'Confirmed',
                deposit_paid = 1,
                remaining_balance = 0,
                fully_paid = 1
            WHERE id = ?
        ");
        $upd->execute([$bookingId]);
    }

    // ============================================================
    // NOTIFICATIONS
    // ============================================================
    if ($payType === 'RESERVATION') {
        addNotification(
            $booking['user_id'],
            '🎫 Reservation Recorded',
            'Your ₱' . number_format($amount, 2) . ' reservation fee via ' . $method . ' has been recorded. Remaining balance: ₱' . number_format($newRemaining, 2) . ' (due on shoot day).',
            'payment',
            '🎫'
        );

        addNotificationByRole(
            'admin',
            '🎫 On-site Reservation Recorded',
            'Staff recorded ₱' . number_format($amount, 2) . ' reservation via ' . $method . ' for ' . $booking['client_name'] . ' (' . $booking['booking_ref'] . ')',
            'payment',
            '🎫'
        );

    } else {
        // BALANCE or FULL
        addNotification(
            $booking['user_id'],
            '🎉 Fully Paid',
            'Your balance of ₱' . number_format($amount, 2) . ' via ' . $method . ' has been recorded. Your booking is now FULLY PAID!',
            'payment',
            '🎉'
        );

        addNotificationByRole(
            'admin',
            '💰 Full Payment Recorded (On-site)',
            'Staff recorded ₱' . number_format($amount, 2) . ' full payment via ' . $method . ' for ' . $booking['client_name'] . ' (' . $booking['booking_ref'] . ')',
            'payment',
            '💰'
        );
    }

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'message'       => $paymentMessage,
        'amount'        => $amount,
        'remaining'     => $newRemaining,
        'fully_paid'    => $newFullyPaid,
        'payment_type'  => $payType,
        'method'        => $method,
        'has_proof'     => $proofFilename ? true : false,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[STUDIO94 record-payment] ' . $e->getMessage());

    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}