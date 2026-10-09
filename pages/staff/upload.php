<?php
requireRole('staff');
$pdo = db();
$uid = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $bookingId = cleanInt($_POST['booking_id'] ?? 0);

    if ($bookingId && !empty($_FILES['photos']['name'][0])) {
        $uploaded = 0;
        foreach ($_FILES['photos']['name'] as $i => $fname) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext  = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','raw'], true)) continue;
            if ($_FILES['photos']['size'][$i] > 50*1024*1024) continue; // 50MB limit

            $newName = 'photo_' . $bookingId . '_' . time() . '_' . $i . '.' . $ext;
            $dest    = UPLOAD_DIR . $newName;
            if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $dest)) {
                $pdo->prepare("INSERT INTO photos (booking_id,uploader_id,filename,filepath,status,created_at)
                    VALUES (?,?,?,?,'Ready',NOW())")
                    ->execute([$bookingId,$uid,$newName,$dest]);
                $uploaded++;
            }
        }
        if ($uploaded > 0) {
            // Notify client
            $bStmt = $pdo->prepare("SELECT b.user_id, p.name as pkg_name FROM bookings b JOIN packages p ON b.package_id=p.id WHERE b.id=?");
            $bStmt->execute([$bookingId]);
            $bInfo = $bStmt->fetch();
            if ($bInfo) {
                addNotification($bInfo['user_id'],'Photos Ready for Download',
                    'Your photos from the ' . $bInfo['pkg_name'] . ' session are now ready to download.','photo','📸');
            }
            setFlash('success', $uploaded . ' photo(s) uploaded successfully.');
        } else {
            setFlash('error', 'No valid photos uploaded. Check file types and sizes.');
        }
    } else {
        setFlash('error', 'Please select a session and at least one photo.');
    }
    header('Location: index.php?page=upload');
    exit;
}

// Completed bookings available for photo upload
$sessions = $pdo->query("
    SELECT b.id, b.booking_ref, b.date, u.name as client_name, p.name as pkg_name,
           COUNT(ph.id) as photo_count
    FROM bookings b
    JOIN users u ON b.user_id=u.id
    JOIN packages p ON b.package_id=p.id
    LEFT JOIN photos ph ON ph.booking_id=b.id
    WHERE b.status IN ('Completed','Deposit Paid','Confirmed','In Progress')
    GROUP BY b.id ORDER BY b.date DESC LIMIT 20
")->fetchAll();

// Upload queue
$queue = $pdo->query("
    SELECT ph.*, b.booking_ref, b.date, u.name as client_name, p.name as pkg_name
    FROM photos ph
    JOIN bookings b ON ph.booking_id=b.id
    JOIN users u ON b.user_id=u.id
    JOIN packages p ON b.package_id=p.id
    ORDER BY ph.created_at DESC LIMIT 10
")->fetchAll();
?>

<div class="grid-2" style="align-items:start;">
  <div>
    <div class="card">
      <div class="card-title">Upload Photos</div>
      <form method="POST" action="index.php?page=upload" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="form-group">
          <label>Select Client Session</label>
          <select name="booking_id" required>
            <option value="">Select session…</option>
            <?php foreach ($sessions as $s): ?>
              <option value="<?= $s['id'] ?>">
                <?= clean($s['client_name']) ?> — <?= clean($s['pkg_name']) ?> (<?= formatDate($s['date']) ?>)
                <?= $s['photo_count'] > 0 ? ' · ' . $s['photo_count'] . ' photos' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="upload-zone" onclick="document.getElementById('photo-files').click()" id="drop-zone">
          <div class="upload-icon">📁</div>
          <div class="upload-title">Drop photos here</div>
          <p>or click to browse files</p>
          <span style="display:block;margin-top:6px;font-size:12px;color:var(--muted);">JPG, PNG, RAW — Max 50MB per file</span>
          <input type="file" id="photo-files" name="photos[]" multiple
                 accept="image/*" style="display:none;"
                 onchange="previewPhotos(event)">
        </div>
        <div id="photo-preview" class="photo-preview-grid" style="display:none;margin-top:14px;"></div>
        <div style="display:flex;gap:10px;margin-top:14px;">
          <button type="button" class="btn-ghost full-width" onclick="document.getElementById('photo-files').click()">Select Files</button>
          <button type="submit" class="btn-primary full-width">⬆ Upload Photos</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Upload History</div>
    <?php if (empty($queue)): ?>
      <p style="color:var(--muted);font-size:13px;">No uploads yet.</p>
    <?php else: ?>
      <?php
      $grouped = [];
      foreach ($queue as $ph) $grouped[$ph['booking_ref']][] = $ph;
      foreach ($grouped as $ref => $photos):
        $first = $photos[0];
      ?>
      <div class="booking-item">
        <div class="booking-date" style="background:var(--green-bg);">
          <div class="day" style="color:var(--green-text);font-size:16px;">✓</div>
        </div>
        <div class="booking-info">
          <div class="pkg"><?= clean($first['client_name']) ?> — <?= count($photos) ?> photos</div>
          <div class="time"><?= clean($first['pkg_name']) ?> · <?= formatDate($first['date']) ?></div>
        </div>
        <?= statusBadge($first['status']) ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function previewPhotos(e) {
  const files   = e.target.files;
  const preview = document.getElementById('photo-preview');
  preview.innerHTML = '';
  preview.style.display = files.length ? 'grid' : 'none';
  Array.from(files).forEach(file => {
    const reader = new FileReader();
    reader.onload = ev => {
      const div = document.createElement('div');
      div.className = 'photo-thumb';
      div.innerHTML = '<img src="' + ev.target.result + '" alt="">';
      preview.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
}

// Drag-and-drop
const zone = document.getElementById('drop-zone');
if (zone) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor='var(--dark)'; });
  zone.addEventListener('dragleave', () => { zone.style.borderColor='var(--border)'; });
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.style.borderColor = 'var(--border)';
    const inp = document.getElementById('photo-files');
    inp.files = e.dataTransfer.files;
    previewPhotos({ target: inp });
  });
}
</script>
