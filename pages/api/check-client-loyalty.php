<?php
// ============================================================
// CHECK CLIENT LOYALTY
// Returns: loyalty_count, bonus_minutes, discount_pct, tier_label
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !in_array(currentUser()['role'], ['staff', 'admin'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$phone = trim($_GET['phone'] ?? '');
$email = trim($_GET['email'] ?? '');

if (!$phone && !$email) {
    echo json_encode(['success' => true, 'loyalty_count' => 0, 'bonus_minutes' => 0, 'discount_pct' => 0]);
    exit;
}

try {
    $pdo = db();

    // Find client by phone or email
    $sql = "SELECT id FROM users WHERE role = 'client'";
    $params = [];
    $where = [];

    if ($phone) {
        $where[] = 'phone = ?';
        $params[] = $phone;
    }
    if ($email) {
        $where[] = 'email = ?';
        $params[] = $email;
    }

    $sql .= ' AND (' . implode(' OR ', $where) . ') LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientId = $stmt->fetchColumn();

    if (!$clientId) {
        // New client
        echo json_encode([
            'success' => true,
            'loyalty_count' => 0,
            'bonus_minutes' => 0,
            'discount_pct' => 0,
            'tier_label' => 'New Client'
        ]);
        exit;
    }

    // Get loyalty count
    $lcStmt = $pdo->prepare("SELECT total_bookings FROM loyalty_cards WHERE user_id = ?");
    $lcStmt->execute([$clientId]);
    $loyaltyCount = (int)($lcStmt->fetchColumn() ?: 0);

    // Compute bonus
    $bonusMinutes = 0;
    $discountPct = 0;

    if ($loyaltyCount >= 10) {
        $discountPct = 50;
        // No bonus minutes when 50% OFF
    } elseif ($loyaltyCount >= 1) {
        $bonusMinutes = 5;
    }

    // Tier label
    $tierLabel = 'New Client';
    if ($loyaltyCount >= 10) $tierLabel = '🏆 VIP (50% OFF)';
    elseif ($loyaltyCount >= 7) $tierLabel = '🎨 +1 BACKDROP';
    elseif ($loyaltyCount >= 4) $tierLabel = '🖼️ +1 PRINT';
    elseif ($loyaltyCount >= 2) $tierLabel = '⏱️ +5 MIN';
    elseif ($loyaltyCount >= 1) $tierLabel = '⭐ Welcome';

    echo json_encode([
        'success' => true,
        'client_id' => (int)$clientId,
        'loyalty_count' => $loyaltyCount,
        'bonus_minutes' => $bonusMinutes,
        'discount_pct' => $discountPct,
        'tier_label' => $tierLabel
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}