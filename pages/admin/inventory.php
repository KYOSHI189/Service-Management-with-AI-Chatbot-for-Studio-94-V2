<?php
// ============================================================
// INVENTORY MANAGEMENT - WITHOUT USAGE
// ============================================================

requireRole(['admin', 'staff']);
$pdo = db();
$user = currentUser();

// ============================================================
// HANDLE ADD / UPDATE / DELETE
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name      = trim($_POST['name'] ?? '');
        $category  = $_POST['category'] ?? 'Equipment';
        $qty       = cleanInt($_POST['quantity'] ?? 1);
        $threshold = cleanInt($_POST['threshold'] ?? 1);
        $isReusable= isset($_POST['is_reusable']) ? 1 : 0;
        $maxUsage  = cleanInt($_POST['max_usage'] ?? 0);
        $notes     = trim($_POST['notes'] ?? '');
        
        if ($name) {
            $stmt = $pdo->prepare("
                INSERT INTO inventory (name, category, quantity, threshold, is_reusable, max_usage, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $category, $qty, $threshold, $isReusable, $maxUsage ?: null, $notes]);
            setFlash('success', 'Item added successfully.');
            checkLowStockAndNotify();
        }
    } elseif ($action === 'update_quantity') {
        $id  = cleanInt($_POST['item_id']);
        $newQty = cleanInt($_POST['new_quantity'] ?? 0);
        
        if ($id && $newQty >= 0) {
            $pdo->prepare("UPDATE inventory SET quantity=? WHERE id=?")->execute([$newQty, $id]);
            checkLowStockAndNotify();
            setFlash('success', 'Quantity updated successfully.');
        }
    } elseif ($action === 'delete') {
        $id = cleanInt($_POST['item_id'] ?? 0);
        if ($id) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM booking_inventory WHERE inventory_id = ?");
            $checkStmt->execute([$id]);
            $usedCount = $checkStmt->fetchColumn();
            
            if ($usedCount > 0) {
                setFlash('error', 'Cannot delete: This item is used in ' . $usedCount . ' booking(s).');
            } else {
                $pdo->prepare("DELETE FROM inventory WHERE id=?")->execute([$id]);
                setFlash('success', 'Item deleted successfully.');
            }
        }
    }
    
    header('Location: index.php?page=inventory');
    exit;
}

// ============================================================
// GET ALL INVENTORY ITEMS
// ============================================================

$items = $pdo->query("
    SELECT i.*, 
           (SELECT COUNT(*) FROM booking_inventory bi WHERE bi.inventory_id = i.id AND bi.returned_at IS NULL) as in_use_count
    FROM inventory i
    ORDER BY i.category, i.name
")->fetchAll();

$lowItems = array_filter($items, fn($i) => $i['is_reusable'] == 0 && $i['quantity'] <= $i['threshold']);
$flash = getFlash();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <style>
        :root {
            --primary: #6C63FF;
            --primary-dark: #4A42CC;
            --success: #2ECC71;
            --danger: #FF6B6B;
            --warning: #F1C40F;
            --bg: #F0F2F5;
            --card-bg: #FFFFFF;
            --text: #2D3436;
            --text-muted: #636E72;
            --border: #DFE6E9;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --radius: 12px;
            --red-bg: #fef2f2;
            --green-bg: #ecfdf5;
            --amber-bg: #fffbeb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }

        .page-header .eyebrow {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .page-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin: 4px 0;
        }

        .page-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .section-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-red {
            background: var(--danger);
            color: white;
        }

        .btn-red:hover {
            background: #E55A5A;
            transform: translateY(-1px);
        }

        .btn-ghost {
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--border);
        }

        .btn-sm {
            padding: 4px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table th,
        table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        table th {
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            background: var(--bg);
        }

        table tr:hover {
            background: rgba(108, 99, 255, 0.03);
        }

        .badge {
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-green {
            background: #d4edda;
            color: #155724;
        }

        .badge-amber {
            background: #fff3cd;
            color: #856404;
        }

        .badge-red {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-gray {
            background: var(--bg);
            color: var(--text-muted);
        }

        .badge-blue {
            background: #cce5ff;
            color: #004085;
        }

        /* ===== ALERTS ===== */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-success {
            background: var(--green-bg);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: var(--red-bg);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: var(--amber-bg);
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .alert-icon {
            font-size: 18px;
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-muted);
        }

        .modal-close:hover {
            color: var(--text);
        }

        .modal-body h3 {
            font-size: 20px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: stretch;
            }

            .table-wrap {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 6px 8px;
            }

            .modal-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Manage</div>
            <h2>📦 Inventory</h2>
            <p>Studio equipment and consumables tracking</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="index.php?page=bookings" class="btn btn-ghost">
                ← Back to Bookings
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'error' : 'warning') ?>">
            <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : ($flash['type'] === 'error' ? '❌' : '⚠️') ?></span>
            <span><?= $flash['msg'] ?></span>
        </div>
    <?php endif; ?>

    <!-- Low Stock Alert -->
    <?php if (!empty($lowItems)): ?>
    <div class="alert alert-warning" style="border-left:4px solid var(--danger);">
        <span class="alert-icon">⚠️</span>
        <div>
            <strong>Low Stock Alert:</strong> 
            <?= count($lowItems) ?> consumable item(s) need restocking.
            <ul style="margin:5px 0 0 20px; font-size:13px;">
                <?php foreach ($lowItems as $item): ?>
                    <li><?= clean($item['name']) ?> — only <strong><?= $item['quantity'] ?></strong> left (threshold: <?= $item['threshold'] ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- INVENTORY TABLE -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="section-header">
            <h3>📋 All Inventory Items</h3>
            <span style="font-size:13px;color:var(--text-muted);"><?= count($items) ?> items</span>
        </div>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): 
                        $isLow   = $item['is_reusable'] == 0 && $item['quantity'] <= $item['threshold'];
                        $isOut   = $item['quantity'] == 0;
                        $status  = $isOut ? 'Out of Stock' : ($isLow ? 'Low Stock' : 'In Stock');
                        $typeLabel = $item['is_reusable'] ? '🔄 Reusable' : '📦 Consumable';
                        $typeBadge = $item['is_reusable'] ? 'badge-blue' : 'badge-amber';
                    ?>
                    <tr style="<?= $isLow?'background:var(--red-bg);':'' ?>">
                        <td>
                            <strong><?= clean($item['name']) ?></strong>
                            <?php if ($item['notes']): ?>
                                <div style="font-size:11px;color:var(--text-muted);"><?= clean($item['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-gray"><?= clean($item['category']) ?></span></td>
                        <td><span class="badge <?= $typeBadge ?>"><?= $typeLabel ?></span></td>
                        <td>
                            <strong><?= $item['quantity'] ?></strong>
                            <?php if ($item['is_reusable'] == 0): ?>
                                <small style="color:var(--text-muted);">/ min <?= $item['threshold'] ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= statusBadge($status) ?></td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                <!-- ===== EDIT BUTTON ===== -->
                                <button class="btn btn-primary btn-sm" onclick="openEditModal(
                                    <?= $item['id'] ?>, 
                                    '<?= addslashes($item['name']) ?>', 
                                    <?= $item['quantity'] ?>,
                                    <?= $item['threshold'] ?>
                                )">
                                    ✏️ Edit
                                </button>
                                
                                <!-- ===== DELETE BUTTON (Admin only) ===== -->
                                <?php if ($user['role'] === 'admin'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item? This cannot be undone.')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <button class="btn btn-red btn-sm" type="submit">🗑️</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;color:var(--text-muted);padding:40px;">
                                📭 No inventory items found. Add your first item below.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ADD NEW ITEM FORM -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="card-title">➕ Add New Item</div>
        <form method="POST" action="index.php?page=inventory">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Item Name <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="name" placeholder="Enter item name" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="Print">🖨️ Print</option>
                        <option value="Backdrop">🎭 Backdrop</option>
                        <option value="Equipment">📷 Equipment</option>
                        <option value="Lighting">💡 Lighting</option>
                        <option value="Props">🎬 Props</option>
                        <option value="Other">📦 Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="0" value="1">
                </div>
                <div class="form-group">
                    <label>Min Threshold <span style="font-size:11px;color:var(--text-muted);">(low stock alert)</span></label>
                    <input type="number" name="threshold" min="1" value="1">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding-top:6px;">
                    <label style="margin:0;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="is_reusable" value="1" id="is_reusable_check">
                        <span>🔄 Reusable Equipment</span>
                    </label>
                    <span style="font-size:11px;color:var(--text-muted);">(uncheck for consumable items)</span>
                </div>
                <div class="form-group" id="max_usage_group" style="display:none;">
                    <label>Max Usage <span style="font-size:11px;color:var(--text-muted);">(optional)</span></label>
                    <input type="number" name="max_usage" min="1" placeholder="e.g., 100">
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes <span style="font-size:11px;color:var(--text-muted);">(optional)</span></label>
                <input type="text" name="notes" placeholder="Additional details, brand, model, etc.">
            </div>
            
            <button class="btn btn-primary" type="submit">➕ Add Item</button>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- EDIT QUANTITY MODAL -->
<!-- ============================================================ -->
<div id="edit-modal" class="modal-overlay" onclick="if(event.target===this) closeModal('edit-modal')">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('edit-modal')">✕</button>
        <div class="modal-body">
            <h3 id="edit-title">✏️ Edit Quantity</h3>
            <form method="POST" action="index.php?page=inventory" id="editForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_quantity">
                <input type="hidden" name="item_id" id="edit_item_id">
                
                <div style="margin-top:16px;">
                    <div style="background:var(--bg);padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-muted);">Item:</span>
                            <strong id="edit_item_name">---</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-top:4px;">
                            <span style="color:var(--text-muted);">Current Quantity:</span>
                            <strong id="edit_current_qty">0</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-top:4px;">
                            <span style="color:var(--text-muted);">Threshold:</span>
                            <strong id="edit_threshold">0</strong>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>New Quantity <span style="color:var(--danger);">*</span></label>
                        <input type="number" name="new_quantity" id="edit_new_qty" min="0" required>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                            💡 Enter the total new quantity (e.g., if you have 5 and you add 10, enter 15)
                        </div>
                    </div>
                </div>
                
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="button" class="btn btn-ghost" style="flex:1;" onclick="closeModal('edit-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:2;">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================================
// TOGGLE MAX USAGE FIELD
// ============================================================

document.getElementById('is_reusable_check').addEventListener('change', function() {
    document.getElementById('max_usage_group').style.display = this.checked ? 'block' : 'none';
});

// ============================================================
// OPEN EDIT MODAL
// ============================================================

function openEditModal(id, name, quantity, threshold) {
    document.getElementById('edit_item_id').value = id;
    document.getElementById('edit_item_name').textContent = name;
    document.getElementById('edit_current_qty').textContent = quantity;
    document.getElementById('edit_threshold').textContent = threshold;
    document.getElementById('edit_new_qty').value = quantity;
    document.getElementById('edit-title').textContent = '✏️ Edit ' + name;
    
    document.getElementById('edit-modal').style.display = 'flex';
}

// ============================================================
// CLOSE MODAL
// ============================================================

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// ============================================================
// CLOSE MODAL WITH ESCAPE KEY
// ============================================================

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('edit-modal');
    }
});
</script>

</body>
</html>