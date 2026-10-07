<?php
// ============================================================
// API: Client Payments
// Handles: settings, list, submit
// ============================================================
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';

// Ensure JSON output
header('Content-Type: application/json; charset=utf-8');

// ============================================================
// AUTHENTICATION
// ============================================================
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated. Please log in.']);
    exit;
}

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

// Must be a client
if ($role !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Client account required.']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    $pdo = db();

    // ============================================================
    // ACTION: SETTINGS — GCash + Maribank details
    // ============================================================
    if ($action === 'settings') {
        $stmt = $pdo->query("
            SELECT `key`, `value` FROM settings 
            WHERE `key` IN (
                'gcash_qr', 'gcash_account_name', 'gcash_account_number',
                'maribank_qr', 'maribank_account_name', 'maribank_account_number'
            )
        ");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['key']] = $row['value'];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'gcash_qr'               => $settings['gcash_qr']               ?? '',
                'gcash_account_name'     => $settings['gcash_account_name']     ?? '',
                'gcash_account_number'   => $settings['gcash_account_number']   ?? '',
                'maribank_qr'            => $settings['maribank_qr']            ?? '',
                'maribank_account_name'  => $settings['maribank_account_name']  ?? '',
                'maribank_account_number'=> $settings['maribank_account_number']?? '',
            ],
        ]);
        exit;
    }

    // ============================================================
    // ACTION: LIST — Payments + stats
    // ============================================================
    if ($action === 'list') {
        // Loyalty count
        $lcStmt = $pdo->prepare("SELECT total_bookings FROM loyalty_cards WHERE user_id = ? LIMIT 1");
        $lcStmt->execute([$userId]);
        $lc = $lcStmt->fetch();
        $loyaltyCount = $lc ? (int)$lc['total_bookings'] : 0;

        // Fetch payments joined with bookings + packages
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.booking_id,
                p.user_id,
                p.payment_ref,
                p.type            AS payment_type,
                p.status,
                p.amount,
                p.method,
                p.ref_number,
                p.proof_image,
                p.rejection_reason,
                p.created_at      AS payment_created,
                p.verified_at,
                b.booking_ref,
                b.date            AS booking_date,
                b.time            AS booking_time,
                b.status          AS booking_status,
                b.package_price,
                b.deposit_paid,
                b.fully_paid,
                b.remaining_balance,
                pkg.name          AS pkg_name
            FROM payments p
            JOIN bookings b  ON p.booking_id = b.id
            LEFT JOIN packages pkg ON b.package_id = pkg.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$userId]);
        $payments = $stmt->fetchAll();

        // Add loyalty_count + cast numeric fields
        foreach ($payments as &$p) {
            $p['loyalty_count']    = $loyaltyCount;
            $p['package_price']    = (float)$p['package_price'];
            $p['amount']           = (float)$p['amount'];
            $p['remaining_balance']= (float)$p['remaining_balance'];
            $p['deposit_paid']     = (int)$p['deposit_paid'];
            $p['fully_paid']       = (int)$p['fully_paid'];
        }
        unset($p);

        // ====================================================
        // STATS
        // ====================================================
        $stats = [
            'total_paid'  => 0,
            'pending_amt' => 0,
            'unpaid_amt'  => 0,
            'active_cnt'  => 0,
        ];

        $RESERVATION_FEE = 100;

        $bkStmt = $pdo->prepare("
            SELECT b.id, b.package_price, b.deposit_paid, b.fully_paid, b.status
            FROM bookings b
            WHERE b.user_id = ?
        ");
        $bkStmt->execute([$userId]);
        $bookings = $bkStmt->fetchAll();

        foreach ($bookings as $b) {
            $isFully = (int)$b['fully_paid'] === 1;
            if (!$isFully && in_array($b['status'], ['Awaiting Approval', 'Approved (Unpaid)', 'Deposit Paid', 'Confirmed'])) {
                $stats['active_cnt']++;
            }
        }

        foreach ($payments as $p) {
            $amt = (float)$p['amount'];
            if ($p['status'] === 'PAID') {
                $stats['total_paid'] += $amt;
            } elseif ($p['status'] === 'PENDING') {
                $stats['pending_amt'] += $amt;
            }
        }

        foreach ($bookings as $b) {
            $isPaid  = (int)$b['deposit_paid'] === 1;
            $isFully = (int)$b['fully_paid'] === 1;
            if (!$isPaid && !$isFully && $b['status'] === 'Approved (Unpaid)') {
                $stats['unpaid_amt'] += $RESERVATION_FEE;
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'loyalty_count' => $loyaltyCount,
                'stats'         => $stats,
                'payments'      => $payments,
            ],
        ]);
        exit;
    }

    // ============================================================
    // ACTION: SUBMIT — Upload payment proof
    // ============================================================
    if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();

        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $refNumber = trim($_POST['ref_number'] ?? '');
        $type      = $_POST['payment_type'] ?? 'RESERVATION';
        $method    = $_POST['method'] ?? 'GCash';

        // ✅ DEBUG LOG
        error_log("=== SUBMIT PAYMENT ===");
        error_log("payment_id = $paymentId");
        error_log("ref_number = $refNumber");
        error_log("type = $type");
        error_log("method = $method");

        if (!$paymentId || !$refNumber) {
            error_log("ERROR: Missing payment_id or ref_number");
            echo json_encode(['success' => false, 'error' => 'Missing payment ID or reference number.']);
            exit;
        }

        // Verify payment belongs to this user
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$paymentId, $userId]);
        $payment = $stmt->fetch();

        error_log("Payment lookup: " . ($payment ? "FOUND id=" . $payment['id'] . " status=" . $payment['status'] : "NOT FOUND"));

        if (!$payment) {
            echo json_encode(['success' => false, 'error' => 'Payment record not found.']);
            exit;
        }

        // ============================================================
        // HANDLE FILE UPLOAD
        // ============================================================
        $proofFilename = null;
        if (!empty($_FILES['proof']['name']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
            $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext      = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.']);
                exit;
            }

            if ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File too large. Maximum 5 MB.']);
                exit;
            }

            $uploadDir = __DIR__ . '/../../assets/uploads/payments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $proofFilename = 'pay_' . $paymentId . '_' . time() . '.' . $ext;
            $targetPath    = $uploadDir . $proofFilename;

            if (!move_uploaded_file($_FILES['proof']['tmp_name'], $targetPath)) {
                error_log("ERROR: move_uploaded_file failed");
                echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file.']);
                exit;
            }
        } else {
            error_log("ERROR: No proof file uploaded");
            echo json_encode(['success' => false, 'error' => 'Payment proof screenshot is required.']);
            exit;
        }

        // ============================================================
        // UPDATE PAYMENT RECORD
        // ============================================================
        $upd = $pdo->prepare("
            UPDATE payments
            SET type = ?,
                ref_number = ?,
                proof_image = ?,
                method = ?,
                status = 'PENDING',
                rejection_reason = NULL,
                verified_by = NULL,
                verified_at = NULL
            WHERE id = ?
        ");
        $upd->execute([$type, $refNumber, $proofFilename, $method, $paymentId]);

        $rowCount = $upd->rowCount();
        error_log("UPDATE result: rowCount = $rowCount");
        error_log("New status should be PENDING for payment_id = $paymentId");

        echo json_encode([
            'success'    => true,
            'message'    => 'Payment proof submitted! We\'ll verify it within 24 hours.',
            'payment_id' => $paymentId,
            'new_status' => 'PENDING',
            'row_count'  => $rowCount,
        ]);
        exit;
    }

    // ============================================================
    // UNKNOWN ACTION
    // ============================================================
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
    exit;

} catch (Throwable $e) {
    error_log('[STUDIO94 API client-payments] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error: ' . $e->getMessage(),
    ]);
    exit;
}