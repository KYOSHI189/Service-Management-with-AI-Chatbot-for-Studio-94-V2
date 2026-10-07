<?php
requireRole('admin');
$pdo = db();

// ============================================================
// IMAGE UPLOAD HELPER
// ============================================================
function handleImageUpload($fileKey = 'image') {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$fileKey];

    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        setFlash('error', 'Image is too large. Maximum 5MB.');
        return null;
    }

    // Validate mime type
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed)) {
        setFlash('error', 'Invalid image format. Use JPG, PNG, GIF, or WebP.');
        return null;
    }

    // Create folder if not exists
    $uploadDir = __DIR__ . '/../assets/packages/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg',
    };
    $filename = 'pkg_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        setFlash('error', 'Failed to upload image.');
        return null;
    }

    return 'assets/packages/' . $filename;
}

function deletePackageImage($imagePath) {
    if (!$imagePath) return;
    $fullPath = __DIR__ . '/../' . $imagePath;
    if (file_exists($fullPath) && is_file($fullPath)) {
        @unlink($fullPath);
    }
}

// ============================================================
// HANDLE SAVE / DELETE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ===== SAVE (Add or Update) =====
    if ($action === 'save') {
        $id          = cleanInt($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $price       = cleanFloat($_POST['price'] ?? 0);
        $duration    = trim($_POST['duration'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $features    = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
        $color       = $_POST['color'] ?? '#B0C4DE';
        $isPopular   = isset($_POST['is_popular']) ? 1 : 0;
        $maxPax      = cleanInt($_POST['max_pax'] ?? 0);
        $printInclusions = trim($_POST['print_inclusions'] ?? '');
        $parentId    = isset($_POST['parent_id']) ? cleanInt($_POST['parent_id']) : null;
        if ($parentId === 0) $parentId = null;

        // Handle image upload
        $newImage = handleImageUpload('image');

        // Handle image removal
        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';

        if ($name && $price > 0) {
            if ($id) {
                // ===== UPDATE EXISTING =====
                // Get current image
                $currentStmt = $pdo->prepare("SELECT image FROM packages WHERE id=?");
                $currentStmt->execute([$id]);
                $currentImage = $currentStmt->fetchColumn();

                // Determine final image
                $finalImage = $currentImage;
                if ($newImage) {
                    // Delete old image if exists
                    if ($currentImage) deletePackageImage($currentImage);
                    $finalImage = $newImage;
                } elseif ($removeImage) {
                    if ($currentImage) deletePackageImage($currentImage);
                    $finalImage = null;
                }

                $stmt = $pdo->prepare("
                    UPDATE packages 
                    SET name=?, price=?, duration=?, description=?, features=?, color=?, 
                        is_popular=?, max_pax=?, print_inclusions=?, image=?, parent_id=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $name, $price, $duration, $desc,
                    json_encode(array_values($features)), $color,
                    $isPopular, $maxPax ?: null, $printInclusions,
                    $finalImage, $parentId,
                    $id
                ]);
                setFlash('success', 'Package updated.');
            } else {
                // ===== INSERT NEW =====
                $stmt = $pdo->prepare("
                    INSERT INTO packages 
                    (name, price, duration, description, features, color, is_popular, max_pax, print_inclusions, image, parent_id, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $name, $price, $duration, $desc,
                    json_encode(array_values($features)), $color,
                    $isPopular, $maxPax ?: null, $printInclusions,
                    $newImage, $parentId
                ]);
                setFlash('success', 'Package added.');
            }
        } else {
            setFlash('error', 'Package name and price are required.');
        }
        header('Location: index.php?page=packages');
        exit;
    }

    // ===== DELETE =====
    elseif ($action === 'delete') {
        $id = cleanInt($_POST['id']);

        // Delete image file first
        $imgStmt = $pdo->prepare("SELECT image FROM packages WHERE id=?");
        $imgStmt->execute([$id]);
        $img = $imgStmt->fetchColumn();
        if ($img) deletePackageImage($img);

        $pdo->prepare("UPDATE packages SET is_active=0 WHERE id=?")->execute([$id]);
        setFlash('success', 'Package removed.');
        header('Location: index.php?page=packages');
        exit;
    }
}

// ============================================================
// GET ALL ACTIVE PACKAGES
// ============================================================
$packages = $pdo->query("
    SELECT *, 
           CASE WHEN parent_id IS NULL THEN 'Main' ELSE 'Sub' END as type
    FROM packages 
    WHERE is_active=1 
    ORDER BY parent_id IS NULL DESC, parent_id, price ASC
")->fetchAll();

// Group
$mainPackages = [];
$subPackages = [];
foreach ($packages as $pkg) {
    if ($pkg['parent_id'] === null) {
        $mainPackages[$pkg['id']] = $pkg;
        $mainPackages[$pkg['id']]['subs'] = [];
    } else {
        $subPackages[$pkg['parent_id']][] = $pkg;
    }
}
foreach ($subPackages as $parentId => $subs) {
    if (isset($mainPackages[$parentId])) {
        $mainPackages[$parentId]['subs'] = $subs;
    }
}

// Editing
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE id=?");
    $stmt->execute([cleanInt($_GET['edit'])]);
    $editing = $stmt->fetch();
}

// All main packages (for parent dropdown)
$mainOnly = $pdo->query("
    SELECT id, name FROM packages 
    WHERE parent_id IS NULL AND is_active=1 
    ORDER BY name
")->fetchAll();
?>

<div class="grid-2" style="align-items:start;">
  
  <!-- ===== PACKAGE LIST ===== -->
  <div class="card">
    <div class="section-header">
      <h3>Service Packages</h3>
      <a href="index.php?page=packages" class="btn-ghost btn-sm">+ New</a>
    </div>
    
    <div style="display:flex;flex-direction:column;gap:14px;">
      <?php if (empty($mainPackages)): ?>
        <p style="color:var(--muted);">No packages yet.</p>
      <?php else: ?>
        <?php foreach ($mainPackages as $main): ?>
          <!-- Main Package Card -->
          <div class="package-card" style="border-left:4px solid <?= clean($main['color']) ?>;">
            <div style="display:flex;gap:12px;align-items:flex-start;">
              <!-- IMAGE THUMBNAIL -->
              <div style="flex-shrink:0;">
                <?php if (!empty($main['image'])): ?>
                  <img src="<?= APP_URL ?>/<?= clean($main['image']) ?>" 
                       alt="<?= clean($main['name']) ?>"
                       style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:2px solid var(--border);">
                <?php else: ?>
                  <div style="width:60px;height:60px;border-radius:8px;background:<?= clean($main['color']) ?>;display:flex;align-items:center;justify-content:center;font-size:24px;border:2px solid var(--border);">
                    📷
                  </div>
                <?php endif; ?>
              </div>

              <div style="flex:1;min-width:0;">
                <div class="pkg-name">
                  <?= clean($main['name']) ?>
                  <?php if ($main['is_popular']): ?>
                    <span class="badge badge-dark" style="font-size:9px;">🔥 POPULAR</span>
                  <?php endif; ?>
                </div>
                <div class="pkg-price"><?= formatMoney($main['price']) ?></div>
                <div style="font-size:12px;color:var(--muted);"><?= clean($main['duration']) ?></div>
                <?php if ($main['max_pax']): ?>
                  <div style="font-size:11px;color:var(--muted);">👥 Max <?= $main['max_pax'] ?> persons</div>
                <?php endif; ?>
                <?php if ($main['print_inclusions']): ?>
                  <div style="font-size:11px;color:var(--amber-text);">🖼️ <?= clean($main['print_inclusions']) ?></div>
                <?php endif; ?>
              </div>

              <div style="display:flex;gap:6px;flex-shrink:0;">
                <a href="index.php?page=packages&edit=<?= $main['id'] ?>" class="btn-ghost btn-sm">Edit</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this package and its sub-packages?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $main['id'] ?>">
                  <button class="btn-red btn-sm" type="submit">Remove</button>
                </form>
              </div>
            </div>
            
            <?php 
              $feats = json_decode($main['features'] ?? '[]', true) ?: [];
            ?>
            <?php if ($feats): ?>
              <ul class="pkg-features" style="margin-top:10px;">
                <?php foreach (array_slice($feats, 0, 4) as $f): ?>
                  <li><?= clean($f) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <!-- Sub-packages -->
            <?php if (!empty($main['subs'])): ?>
              <div style="margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light);">
                <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Sub-Packages</div>
                <?php foreach ($main['subs'] as $sub): ?>
                  <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid var(--border-light);gap:10px;">
                    <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                      <!-- SUB IMAGE -->
                      <?php if (!empty($sub['image'])): ?>
                        <img src="<?= APP_URL ?>/<?= clean($sub['image']) ?>" 
                             style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--border);flex-shrink:0;">
                      <?php else: ?>
                        <div style="width:36px;height:36px;border-radius:6px;background:<?= clean($sub['color']) ?>;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
                          📷
                        </div>
                      <?php endif; ?>
                      <div style="min-width:0;">
                        <div>
                          <span style="font-weight:500;"><?= clean($sub['name']) ?></span>
                          <?php if ($sub['is_popular']): ?>
                            <span class="badge badge-dark" style="font-size:8px;">🔥</span>
                          <?php endif; ?>
                          <span style="font-size:12px; color:var(--muted);"><?= formatMoney($sub['price']) ?></span>
                          <span style="font-size:11px; color:var(--muted);">⏱️ <?= clean($sub['duration']) ?></span>
                          <?php if ($sub['max_pax']): ?>
                            <span style="font-size:10px; color:var(--muted);">👥 <?= $sub['max_pax'] ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                      <a href="index.php?page=packages&edit=<?= $sub['id'] ?>" class="btn-ghost btn-sm" style="padding:2px 8px; font-size:10px;">Edit</a>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this sub-package?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                        <button class="btn-red btn-sm" style="padding:2px 8px; font-size:10px;" type="submit">🗑️</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== ADD / EDIT FORM ===== -->
  <div class="card">
    <div class="card-title"><?= $editing ? 'Edit Package' : 'Add Package' ?></div>
    <form method="POST" action="index.php?page=packages" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editing ? $editing['id'] : 0 ?>">

      <!-- ===== IMAGE UPLOAD ===== -->
      <div class="form-group">
        <label>Package Image</label>
        
        <!-- Current image preview -->
        <div id="image-preview-container" style="margin-bottom:10px;">
          <?php if ($editing && !empty($editing['image'])): ?>
            <div style="position:relative;display:inline-block;">
              <img id="image-preview" src="<?= APP_URL ?>/<?= clean($editing['image']) ?>" 
                   style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:2px solid var(--border);">
              <label style="display:block;margin-top:6px;font-size:11px;cursor:pointer;color:var(--red-text);">
                <input type="checkbox" name="remove_image" value="1" style="margin-right:4px;">
                Remove image
              </label>
            </div>
          <?php else: ?>
            <div id="image-placeholder" style="width:120px;height:120px;border-radius:8px;border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;background:var(--bg-soft);font-size:36px;color:var(--muted);">
              📷
            </div>
          <?php endif; ?>
        </div>

        <!-- File input -->
        <input type="file" name="image" id="image-input" accept="image/*"
               style="padding:10px;border:1.5px solid var(--border);border-radius:8px;width:100%;background:white;">
        
        <div style="font-size:11px;color:var(--muted);margin-top:4px;">
          JPG, PNG, GIF, or WebP · Max 5MB · Recommended: 800×600px
        </div>
      </div>

      <!-- Parent package selection -->
      <div class="form-group">
        <label>Package Type</label>
        <select name="parent_id" id="parent_id">
          <option value="">Main Package (top-level)</option>
          <?php foreach ($mainOnly as $mp): ?>
            <option value="<?= $mp['id'] ?>"
              <?= ($editing && $editing['parent_id'] == $mp['id']) ? 'selected' : '' ?>>
              Sub-package of: <?= clean($mp['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Package Name</label>
        <input type="text" name="name" value="<?= clean($editing['name'] ?? '') ?>" placeholder="e.g., Creative Session" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Price (₱)</label>
          <input type="number" name="price" value="<?= $editing['price'] ?? '' ?>" placeholder="1500" min="1" required>
        </div>
        <div class="form-group">
          <label>Duration</label>
          <input type="text" name="duration" value="<?= clean($editing['duration'] ?? '') ?>" placeholder="e.g., 2 hours, 15 mins">
        </div>
      </div>
      <div class="form-group">
        <label>Description</label>
        <input type="text" name="description" value="<?= clean($editing['description'] ?? '') ?>" placeholder="Short description">
      </div>
      <div class="form-group">
        <label>Features <small style="text-transform:none;color:var(--muted);">(one per line)</small></label>
        <textarea name="features" style="min-height:90px;" placeholder="1 Hour Session&#10;10 Edited Photos"><?php
          if ($editing) {
              $feats = json_decode($editing['features'] ?? '[]', true) ?: [];
              echo clean(implode("\n", $feats));
          }
        ?></textarea>
      </div>

      <div class="form-row">
        <div class="form-group" style="display:flex; align-items:center; gap:10px;">
          <label style="margin:0;">
            <input type="checkbox" name="is_popular" value="1" <?= ($editing && $editing['is_popular']) ? 'checked' : '' ?>>
            🔥 Popular
          </label>
        </div>
        <div class="form-group">
          <label>Max People</label>
          <input type="number" name="max_pax" value="<?= $editing['max_pax'] ?? '' ?>" placeholder="e.g., 5" min="0">
        </div>
      </div>
      <div class="form-group">
        <label>Print Inclusions</label>
        <input type="text" name="print_inclusions" value="<?= clean($editing['print_inclusions'] ?? '') ?>" placeholder="e.g., 5 Printouts (3 4R, 2 2R)">
      </div>

      <div class="form-group">
        <label>Accent Color</label>
        <select name="color">
          <?php
          $colorOpts = ['#D4A0A0'=>'Pink','#B0C4DE'=>'Blue','#A0C4A0'=>'Green','#D4C4A0'=>'Amber','#C4C4C4'=>'Gray'];
          foreach ($colorOpts as $val=>$lbl):
          ?>
          <option value="<?= $val ?>" <?= ($editing['color']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;gap:10px;">
        <?php if ($editing): ?>
          <a href="index.php?page=packages" class="btn-ghost" style="flex:1;text-align:center;">Cancel</a>
        <?php endif; ?>
        <button class="btn-primary" type="submit" style="flex:2;"><?= $editing ? 'Update Package' : 'Save Package' ?></button>
      </div>
    </form>
  </div>

</div>

<!-- ============================================================
     IMAGE PREVIEW SCRIPT
     ============================================================ -->
<script>
document.getElementById('image-input')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(evt) {
        let preview = document.getElementById('image-preview');
        const placeholder = document.getElementById('image-placeholder');

        if (!preview) {
            // Replace placeholder with img
            const container = document.getElementById('image-preview-container');
            if (placeholder) placeholder.remove();

            preview = document.createElement('img');
            preview.id = 'image-preview';
            preview.style.cssText = 'width:120px;height:120px;object-fit:cover;border-radius:8px;border:2px solid var(--border);';
            container.appendChild(preview);
        }

        preview.src = evt.target.result;
    };
    reader.readAsDataURL(file);
});
</script>