<?php
// ============================================================
// INVENTORY MANAGEMENT
// White & Black Theme + RED Low Stock Alert
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
        $name       = trim($_POST['name'] ?? '');
        $category   = $_POST['category'] ?? 'Equipment';
        $qty        = cleanInt($_POST['quantity'] ?? 1);
        $threshold  = cleanInt($_POST['threshold'] ?? 1);
        $isReusable = isset($_POST['is_reusable']) ? 1 : 0;
        $maxUsage   = cleanInt($_POST['max_usage'] ?? 0);
        $notes      = trim($_POST['notes'] ?? '');

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
        $id     = cleanInt($_POST['item_id']);
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

<!-- ============================================================ -->
<!-- PAGE BANNER — BLACK -->
<!-- ============================================================ -->
<div class="page-banner" style="background:#0A0A0A;color:#FFFFFF;">
  <div class="page-banner-text">
    <div class="eyebrow" style="color:rgba(255,255,255,0.7);">Manage</div>
    <h2 style="color:#FFFFFF;"><strong>📦 Inventory</strong></h2>
    <p style="color:rgba(255,255,255,0.85);">Studio equipment and consumables tracking</p>
  </div>
  <div class="page-banner-art" style="opacity:0.8;">📦</div>
</div>

<!-- FLASH MESSAGES -->
<?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'error' : 'warning') ?>" style="margin-bottom:20px;">
    <?= clean($flash['msg']) ?>
  </div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- LOW STOCK ALERT — RED -->
<!-- ============================================================ -->
<?php if (!empty($lowItems)): ?>
<div class="card" style="margin-bottom:20px;background:#FEF2F2;border:2px solid #DC2626;border-left:6px solid #DC2626;">
    <div style="display:flex;align-items:flex-start;gap:12px;">
        <div style="font-size:28px;line-height:1;">🚨</div>
        <div style="flex:1;">
            <div style="font-weight:800;color:#991B1B;font-size:15px;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">
                🚨 Low Stock Alert — <?= count($lowItems) ?> item(s) need restocking
            </div>
            <ul style="margin:8px 0 0 20px;font-size:13px;color:#7F1D1D;line-height:1.9;font-weight:500;">
                <?php foreach ($lowItems as $item): ?>
                    <li>
                        <strong><?= clean($item['name']) ?></strong> — only
                        <strong style="color:#DC2626;font-size:14px;"><?= $item['quantity'] ?></strong> left
                        <span style="color:#991B1B;">(threshold: <?= $item['threshold'] ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #DC2626;font-size:12px;color:#7F1D1D;">
                💡 <strong>Action needed:</strong> Restock these items as soon as possible to avoid booking conflicts.
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- INVENTORY TABLE -->
<!-- ============================================================ -->
<div class="card">
    <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <h3>
            <strong>📋 All Inventory Items</strong>
            <span style="font-size:12px;color:#8B8177;font-weight:500;background:#F5F5F5;padding:3px 10px;border-radius:50px;margin-left:6px;">
                <?= count($items) ?>
            </span>
        </h3>
    </div>

    <div class="table-wrap" style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Item Name</th>
                    <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Category</th>
                    <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Type</th>
                    <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Quantity</th>
                    <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Status</th>
                    <th style="text-align:left;padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#F5F5F5;border-bottom:1px solid #E0E0E0;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $isLow   = $item['is_reusable'] == 0 && $item['quantity'] <= $item['threshold'];
                    $isOut   = $item['quantity'] == 0;
                    $status  = $isOut ? 'Out of Stock' : ($isLow ? 'Low Stock' : 'In Stock');
                    $typeLabel = $item['is_reusable'] ? '🔄 Reusable' : '📦 Consumable';

                    // ============================================================
                    // STATUS BADGE — RED kapag low/out of stock
                    // ============================================================
                    $statusStyle = 'background:#0A0A0A;color:#FFFFFF;border:1px solid #0A0A0A;'; // In Stock (black)
                    
                    if ($status === 'Low Stock') {
                        $statusStyle = 'background:#DC2626;color:#FFFFFF;border:1px solid #991B1B;box-shadow:0 0 0 2px #FEE2E2;'; // RED
                    } elseif ($status === 'Out of Stock') {
                        $statusStyle = 'background:#991B1B;color:#FFFFFF;border:2px solid #7F1D1D;animation:pulseRed 2s infinite;'; // DARK RED + pulse
                    }
                ?>
                <tr style="transition:background 0.15s;<?= $isLow ? 'background:#FEF2F2;' : '' ?>"
                    onmouseover="this.style.background='<?= $isLow ? '#FEE2E2' : '#F5F5F5' ?>'"
                    onmouseout="this.style.background='<?= $isLow ? '#FEF2F2' : '#FFFFFF' ?>'">

                    <td style="padding:14px;border-bottom:1px solid #E0E0E0;">
                        <strong style="color:#0A0A0A;"><?= clean($item['name']) ?></strong>
                        <?php if ($item['notes']): ?>
                            <div style="font-size:11px;color:#8B8177;margin-top:2px;"><?= clean($item['notes']) ?></div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:14px;border-bottom:1px solid #E0E0E0;">
                        <span style="background:#F5F5F5;color:#0A0A0A;border:1px solid #E0E0E0;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:600;">
                            <?= clean($item['category']) ?>
                        </span>
                    </td>

                    <td style="padding:14px;border-bottom:1px solid #E0E0E0;">
                        <span style="padding:3px 10px;border-radius:50px;font-size:11px;font-weight:600;
                            <?= $item['is_reusable'] ? 'background:#FFFFFF;color:#0A0A0A;border:1px solid #0A0A0A;' : 'background:#F5F5F5;color:#0A0A0A;border:1px solid #E0E0E0;' ?>">
                            <?= $typeLabel ?>
                        </span>
                    </td>

                    <td style="padding:14px;border-bottom:1px solid #E0E0E0;">
                        <strong style="color:<?= $isLow ? '#DC2626' : '#0A0A0A' ?>;font-size:<?= $isLow ? '16px' : '14px' ?>;">
                            <?= $item['quantity'] ?>
                        </strong>
                        <?php if ($item['is_reusable'] == 0): ?>
                            <small style="color:#8B8177;">/ min <?= $item['threshold'] ?></small>
                        <?php endif; ?>
                    </td>

                    <td style="padding:14px;border-bottom:1px solid #E0E0E0;">
                        <span style="<?= $statusStyle ?>padding:4px 12px;border-radius:50px;font-size:11px;font-weight:700;display:inline-block;text-transform:uppercase;letter-spacing:0.3px;">
                            <?= $status ?>
                        </span>
                    </td>

                    <td style="padding:14px;border-bottom:1px solid #E0E0E0;">
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button type="button"
                                    onclick="openEditModal(
                                        <?= $item['id'] ?>, 
                                        '<?= addslashes(clean($item['name'])) ?>', 
                                        <?= $item['quantity'] ?>,
                                        <?= $item['threshold'] ?>
                                    )"
                                    style="padding:6px 12px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                                ✏️ Edit
                            </button>

                            <?php if ($user['role'] === 'admin'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item? This cannot be undone.')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button type="submit"
                                        style="padding:6px 12px;background:#FFFFFF;color:#0A0A0A;border:1px solid #0A0A0A;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                                    🗑️ Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:60px 20px;">
                            <div style="font-size:48px;margin-bottom:12px;">📭</div>
                            <div style="font-weight:600;color:#0A0A0A;margin-bottom:4px;">No inventory items found</div>
                            <div style="font-size:13px;color:#8B8177;">Add your first item below.</div>
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
    <div class="section-header">
        <h3><strong>➕ Add New Item</strong></h3>
    </div>

    <form method="POST" action="index.php?page=inventory">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">
                    Item Name <span style="color:#E74C3C;">*</span>
                </label>
                <input type="text" name="name" placeholder="Enter item name" required
                       style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">Category</label>
                <select name="category"
                        style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
                    <option value="Print">🖨️ Print</option>
                    <option value="Backdrop">🎭 Backdrop</option>
                    <option value="Equipment">📷 Equipment</option>
                    <option value="Lighting">💡 Lighting</option>
                    <option value="Props">🎬 Props</option>
                    <option value="Other">📦 Other</option>
                </select>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">Quantity</label>
                <input type="number" name="quantity" min="0" value="1"
                       style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">
                    Min Threshold <span style="font-size:11px;color:#8B8177;font-weight:400;">(low stock alert)</span>
                </label>
                <input type="number" name="threshold" min="1" value="1"
                       style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding-top:6px;">
                <label style="margin:0;display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:500;color:#0A0A0A;">
                    <input type="checkbox" name="is_reusable" value="1" id="is_reusable_check">
                    <span>🔄 Reusable Equipment</span>
                </label>
                <span style="font-size:11px;color:#8B8177;">(uncheck for consumable)</span>
            </div>
            <div id="max_usage_group" style="display:none;">
                <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">
                    Max Usage <span style="font-size:11px;color:#8B8177;font-weight:400;">(optional)</span>
                </label>
                <input type="number" name="max_usage" min="1" placeholder="e.g., 100"
                       style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">
                Notes <span style="font-size:11px;color:#8B8177;font-weight:400;">(optional)</span>
            </label>
            <input type="text" name="notes" placeholder="Additional details, brand, model, etc."
                   style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
        </div>

        <button type="submit"
                style="padding:12px 24px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
            ➕ Add Item
        </button>
    </form>
</div>

<!-- ============================================================ -->
<!-- EDIT QUANTITY MODAL -->
<!-- ============================================================ -->
<div id="edit-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this) closeModal('edit-modal')">
    <div style="background:#FFFFFF;border-radius:14px;padding:28px;max-width:500px;width:100%;position:relative;">

        <button type="button" onclick="closeModal('edit-modal')"
                style="position:absolute;top:14px;right:14px;background:#F5F5F5;border:none;color:#0A0A0A;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;">
            ✕
        </button>

        <h3 id="edit-title" style="margin-bottom:16px;color:#0A0A0A;font-size:18px;font-weight:700;">
            ✏️ Edit Quantity
        </h3>

        <form method="POST" action="index.php?page=inventory" id="editForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_quantity">
            <input type="hidden" name="item_id" id="edit_item_id">

            <div style="background:#F5F5F5;padding:14px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;border:1px solid #E0E0E0;">
                <div style="display:flex;justify-content:space-between;padding:4px 0;">
                    <span style="color:#8B8177;">Item:</span>
                    <strong id="edit_item_name" style="color:#0A0A0A;">---</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:4px 0;">
                    <span style="color:#8B8177;">Current Quantity:</span>
                    <strong id="edit_current_qty" style="color:#0A0A0A;">0</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:4px 0;">
                    <span style="color:#8B8177;">Threshold:</span>
                    <strong id="edit_threshold" style="color:#0A0A0A;">0</strong>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:600;font-size:13px;color:#0A0A0A;margin-bottom:6px;">
                    New Quantity <span style="color:#E74C3C;">*</span>
                </label>
                <input type="number" name="new_quantity" id="edit_new_qty" min="0" required
                       style="width:100%;padding:10px 14px;border:1px solid #E0E0E0;border-radius:8px;font-size:14px;background:#FFFFFF;color:#0A0A0A;box-sizing:border-box;">
                <div style="font-size:11px;color:#8B8177;margin-top:6px;line-height:1.5;">
                    💡 Enter the total new quantity (e.g., if you have 5 and add 10, enter 15).
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeModal('edit-modal')"
                        style="flex:1;padding:12px;background:#F5F5F5;color:#0A0A0A;border:1px solid #E0E0E0;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;">
                    Cancel
                </button>
                <button type="submit"
                        style="flex:2;padding:12px;background:#0A0A0A;color:#FFFFFF;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;">
                    💾 Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes pulseRed {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.85; }
}
</style>

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
    document.body.style.overflow = 'hidden';
}

// ============================================================
// CLOSE MODAL
// ============================================================
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = '';
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