<?php
// ============================================================
// AUTO INVENTORY DEDUCTION HELPER
// ============================================================

function deductInventory($bookingId, $packageId, $pdo) {
    // Get package name
    $stmt = $pdo->prepare("SELECT name FROM packages WHERE id = ?");
    $stmt->execute([$packageId]);
    $package = $stmt->fetch();
    
    if (!$package) {
        return ['success' => false, 'message' => 'Package not found', 'deducted' => []];
    }
    
    $packageName = $package['name'];
    
    // ===== PACKAGE TO INVENTORY MAPPING =====
    $packageInventoryMap = [
        'Package A' => ['Print (4R)' => 1, 'Memory Card' => 1],
        'Package B' => ['Print (4R)' => 2, 'Print (Strip)' => 2, 'Memory Card' => 1],
        'Package C' => ['Print (4R)' => 3, 'Print (2R)' => 2, 'Memory Card' => 1],
        'Package D' => ['Print (4R)' => 3, 'Print (Strip)' => 2, 'Memory Card' => 1],
        'Package E' => ['Print (4R)' => 5, 'Print (2R)' => 2, 'Memory Card' => 2, 'Backdrop Paper' => 2],
        'Package F' => ['Print (4R)' => 7, 'Print (2R)' => 4, 'Memory Card' => 2, 'Backdrop Paper' => 3],
        'Classic' => ['Print (A4)' => 1, 'Print (5R)' => 2, 'Print (4R)' => 2, 'Memory Card' => 2],
        'Premium' => ['Print (A4)' => 2, 'Print (5R)' => 3, 'Print (4R)' => 3, 'Memory Card' => 3, 'Frame' => 1],
        '3 Pax' => ['Print (A4)' => 1, 'Print (5R)' => 2, 'Print (4R)' => 2, 'Memory Card' => 2, 'Frame' => 1, 'Family Props' => 1],
        '4-6 Pax' => ['Print (A4)' => 2, 'Print (5R)' => 3, 'Print (4R)' => 3, 'Memory Card' => 2, 'Frame' => 1, 'Family Props' => 2],
        '7-10 Pax' => ['Print (A4)' => 3, 'Print (5R)' => 5, 'Print (4R)' => 5, 'Memory Card' => 3, 'Frame' => 1, 'Family Props' => 3],
        'Basic' => ['Backdrop Paper' => 1, 'Props' => 1],
        'Half Day' => ['Backdrop Paper' => 2, 'Props' => 2],
    ];
    
    $itemsToDeduct = $packageInventoryMap[$packageName] ?? [];
    
    if (empty($itemsToDeduct)) {
        return ['success' => true, 'message' => 'No consumable items for this package', 'deducted' => []];
    }
    
    $errors = [];
    $deductedItems = [];
    $reusableItems = [];
    
    foreach ($itemsToDeduct as $itemName => $qty) {
        $stmt = $pdo->prepare("SELECT id, quantity, threshold, is_reusable FROM inventory WHERE name = ?");
        $stmt->execute([$itemName]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $errors[] = "Item '$itemName' not found in inventory.";
            continue;
        }
        
        // ===== REUSABLE =====
        if ($item['is_reusable'] == 1) {
            recordReusableItemUsage($bookingId, $item['id'], $pdo);
            $reusableItems[] = "$itemName (booked)";
            continue;
        }
        
        // ===== CONSUMABLE =====
        if ($item['quantity'] < $qty) {
            $errors[] = "Not enough '$itemName'. Available: {$item['quantity']}, Required: $qty";
            continue;
        }
        
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$qty, $item['id']]);
        $deductedItems[] = "$itemName (-$qty)";
        
        // ===== CHECK LOW STOCK =====
        $newQty = $item['quantity'] - $qty;
        if ($newQty <= $item['threshold']) {
            addNotificationByRole('admin', '⚠️ Low Stock Alert', 
                "$itemName is low on stock. Available: $newQty (Threshold: {$item['threshold']})", 
                'inventory', '⚠️');
            addNotificationByRole('staff', '⚠️ Low Stock Alert', 
                "$itemName is low on stock. Available: $newQty (Threshold: {$item['threshold']})", 
                'inventory', '⚠️');
        }
    }
    
    // ===== LOG =====
    $success = empty($errors);
    $message = $success ? 'Inventory updated: ' . implode(', ', array_merge($deductedItems, $reusableItems)) : 'Errors: ' . implode(', ', $errors);
    
    $log = date('Y-m-d H:i:s') . " - Booking #$bookingId - $message\n";
    file_put_contents(__DIR__ . '/../logs/inventory.log', $log, FILE_APPEND);
    
    return ['success' => $success, 'message' => $message, 'deducted' => $deductedItems, 'reusable' => $reusableItems, 'errors' => $errors];
}

function recordReusableItemUsage($bookingId, $inventoryId, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO booking_inventory (booking_id, inventory_id, used_at) VALUES (?, ?, NOW())");
    $stmt->execute([$bookingId, $inventoryId]);
}