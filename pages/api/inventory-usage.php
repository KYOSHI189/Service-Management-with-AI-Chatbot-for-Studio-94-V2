<?php
// pages/api/inventory-usage.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is staff/admin
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = currentUser();
if (!in_array($user['role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$inventoryId = (int)($_GET['id'] ?? 0);
if ($inventoryId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid inventory ID']);
    exit;
}

$pdo = db();

// Get usage history for this inventory item
$stmt = $pdo->prepare("
    SELECT 
        bi.*,
        b.booking_ref,
        b.date,
        b.time,
        b.status,
        b.booking_ref
    FROM booking_inventory bi
    JOIN bookings b ON bi.booking_id = b.id
    WHERE bi.inventory_id = ?
    ORDER BY bi.used_at DESC
    LIMIT 50
");
$stmt->execute([$inventoryId]);
$usage = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'usage' => $usage,
    'count' => count($usage)
]);
?>