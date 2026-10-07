<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Booking Management (Database Version)
// ============================================================

require_once __DIR__ . '/../config.php';

/**
 * Save booking to DATABASE (instead of JSON)
 */
function saveBooking($bookingData) {
    try {
        $pdo = getDBConnection();
        
        // Check if user exists, if not create one
        $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $userStmt->execute([$bookingData['email'] ?? '', $bookingData['contact'] ?? '']);
        $user = $userStmt->fetch();
        
        if ($user) {
            $userId = $user['id'];
        } else {
            // Create new user
            $randomPassword = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
            
            $insertUser = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, is_active, created_at) 
                                         VALUES (?, ?, ?, ?, 'client', 1, NOW())");
            $insertUser->execute([
                $bookingData['name'] ?? 'N/A',
                $bookingData['email'] ?? '',
                $bookingData['contact'] ?? 'N/A',
                $hashedPassword
            ]);
            $userId = $pdo->lastInsertId();
        }
        
        // Get package ID
        $packageName = $bookingData['package'] ?? '';
        $packageStmt = $pdo->prepare("SELECT id, price FROM packages WHERE name LIKE ? AND is_active = 1 LIMIT 1");
        $packageStmt->execute(["%$packageName%"]);
        $package = $packageStmt->fetch();
        
        if (!$package) {
            // Fallback: use default package (Self-Shoot)
            $packageStmt = $pdo->prepare("SELECT id, price FROM packages WHERE parent_id IS NULL LIMIT 1");
            $packageStmt->execute();
            $package = $packageStmt->fetch();
        }
        
        $packageId = $package['id'] ?? 1;
        $packagePrice = $package['price'] ?? 0;
        
        // Generate booking reference
        $ref = 'BK-' . date('Y') . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
        
        // Calculate deposit (50%)
        $depositPct = 50;
        $deposit = round($packagePrice * ($depositPct / 100));
        $remaining = $packagePrice - $deposit;
        
        // Insert booking
        $insert = $pdo->prepare("INSERT INTO bookings 
            (booking_ref, user_id, package_id, date, time, people, phone, notes, type, status, package_price, deposit_amount, remaining_balance, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'online', 'Awaiting Approval', ?, ?, ?, NOW())");
        
        $insert->execute([
            $ref,
            $userId,
            $packageId,
            $bookingData['date'] ?? date('Y-m-d'),
            $bookingData['time'] ?? '10:00 AM',
            $bookingData['people'] ?? 1,
            $bookingData['contact'] ?? 'N/A',
            $bookingData['requests'] ?? 'Booked via SnapBot AI',
            $packagePrice,
            $deposit,
            $remaining
        ]);
        
        $bookingId = $pdo->lastInsertId();
        
        // Create payment record
        $payRef = 'PAY-' . date('Y') . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
        $payStmt = $pdo->prepare("INSERT INTO payments (payment_ref, booking_id, user_id, amount, type, status, created_at) 
                                   VALUES (?, ?, ?, ?, 'DEPOSIT', 'UNPAID', NOW())");
        $payStmt->execute([$payRef, $bookingId, $userId, $deposit]);
        
        // Add loyalty card if not exists
        $lcCheck = $pdo->prepare("SELECT id FROM loyalty_cards WHERE user_id = ?");
        $lcCheck->execute([$userId]);
        if (!$lcCheck->fetch()) {
            $cardNo = 'LC-' . date('Y') . '-' . str_pad($userId, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO loyalty_cards (user_id, card_number, total_bookings, status) VALUES (?, ?, 0, 'Active')")
                ->execute([$userId, $cardNo]);
        }
        
        // Send notification to staff
        addNotificationByRole('admin', '📋 New Booking via SnapBot', 
            $bookingData['name'] . ' booked ' . $packageName . ' on ' . ($bookingData['date'] ?? 'TBD'), 
            'booking', '🤖');
        addNotificationByRole('staff', '📋 New Booking via SnapBot', 
            $bookingData['name'] . ' booked ' . $packageName . ' on ' . ($bookingData['date'] ?? 'TBD'), 
            'booking', '🤖');
        
        return $ref;
        
    } catch (PDOException $e) {
        error_log("Booking save error: " . $e->getMessage());
        // Fallback: Save to JSON if database fails
        return saveBookingToFile($bookingData);
    }
}

/**
 * Fallback: Save to JSON file
 */
function saveBookingToFile($bookingData) {
    $bookingsFile = __DIR__ . '/../data/bookings.json';
    
    if (!is_dir(__DIR__ . '/../data')) {
        mkdir(__DIR__ . '/../data', 0777, true);
    }
    
    $bookings = [];
    if (file_exists($bookingsFile)) {
        $content = file_get_contents($bookingsFile);
        $bookings = json_decode($content, true) ?? [];
    }
    
    $bookingId = 'BK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    $newBooking = [
        'booking_id' => $bookingId,
        'booking_ref' => $bookingId,
        'client_name' => $bookingData['name'] ?? 'N/A',
        'client_contact' => $bookingData['contact'] ?? 'N/A',
        'client_email' => $bookingData['email'] ?? 'N/A',
        'package' => $bookingData['package'] ?? 'N/A',
        'booking_date' => $bookingData['date'] ?? date('Y-m-d'),
        'booking_time' => $bookingData['time'] ?? '10:00 AM',
        'number_of_people' => $bookingData['people'] ?? 1,
        'special_requests' => $bookingData['requests'] ?? '',
        'status' => 'Awaiting Approval',
        'type' => 'online',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $bookings[] = $newBooking;
    file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
    
    return $bookingId;
}

/**
 * Get all bookings (from database)
 */
function getBookings($status = null) {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT b.*, u.name as client_name, u.phone as client_phone, 
                       p.name as pkg_name, p.price as pkg_price
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                JOIN packages p ON b.package_id = p.id";
        
        if ($status && $status !== 'all') {
            $sql .= " WHERE b.status = :status";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':status' => $status]);
        } else {
            $sql .= " ORDER BY b.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get bookings error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get booking statistics
 */
function getBookingStats() {
    try {
        $pdo = getDBConnection();
        $stats = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
        $stats['total'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Awaiting Approval'");
        $stats['pending'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Approved (Unpaid)'");
        $stats['approved'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Deposit Paid'");
        $stats['deposit_paid'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Completed'");
        $stats['completed'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Cancelled'");
        $stats['cancelled'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT SUM(package_price) FROM bookings WHERE status IN ('Approved (Unpaid)', 'Deposit Paid', 'Completed')");
        $stats['revenue'] = $stmt->fetchColumn() ?? 0;
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Get stats error: " . $e->getMessage());
        return [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'deposit_paid' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'revenue' => 0
        ];
    }
}

// ===== FUNCTION ALIASES FOR BACKWARD COMPATIBILITY =====
function getBookingsFromFile() {
    $bookingsFile = __DIR__ . '/../data/bookings.json';
    if (!file_exists($bookingsFile)) {
        return [];
    }
    return json_decode(file_get_contents($bookingsFile), true) ?? [];
}

function getBookingStatsFromFile() {
    $bookings = getBookingsFromFile();
    $stats = [
        'total' => count($bookings),
        'pending' => 0,
        'approved' => 0,
        'deposit_paid' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'revenue' => 0
    ];
    
    foreach ($bookings as $booking) {
        $status = $booking['status'] ?? 'pending';
        if (isset($stats[$status])) $stats[$status]++;
    }
    
    return $stats;
}
?>