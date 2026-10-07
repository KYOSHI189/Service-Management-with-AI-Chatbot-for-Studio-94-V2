<?php
requireRole('admin');
$pdo = db();

// Handle add / toggle active / reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pw    = $_POST['password'] ?? '';

        if ($name && $email && $pw) {
            $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $check->execute([$email]);
            if ($check->fetch()) {
                setFlash('error', 'Email already in use.');
            } else {
                $pdo->prepare("INSERT INTO users (name,email,phone,password,role,is_active,created_at) VALUES (?,?,?,?,'staff',1,NOW())")
                    ->execute([$name,$email,$phone,password_hash($pw,PASSWORD_DEFAULT)]);
                setFlash('success', 'Staff member added.');
            }
        } else {
            setFlash('error', 'Please fill all required fields.');
        }
    } elseif ($action === 'toggle') {
        $id = cleanInt($_POST['staff_id']);
        $pdo->prepare("UPDATE users SET is_active = 1-is_active WHERE id=? AND role='staff'")->execute([$id]);
        setFlash('success', 'Staff status updated.');
    } elseif ($action === 'delete') {
        $id = cleanInt($_POST['staff_id']);
        $pdo->prepare("DELETE FROM users WHERE id=? AND role='staff'")->execute([$id]);
        setFlash('success', 'Staff member removed.');
    }
    header('Location: index.php?page=staff');
    exit;
}

$staff = $pdo->query("SELECT u.*,
    (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS bookings_managed
    FROM users u WHERE u.role='staff' ORDER BY u.name")->fetchAll();
?>

<div class="card">
  <div class="section-header">
    <h3>Staff Management</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($staff as $s): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="user-avatar" style="background:linear-gradient(135deg,#B0C4DE,#9BB5D0);">
                <?= strtoupper(mb_substr($s['name'],0,1)) ?>
              </div>
              <strong><?= clean($s['name']) ?></strong>
            </div>
          </td>
          <td><?= clean($s['email']) ?></td>
          <td><?= clean($s['phone'] ?: '—') ?></td>
          <td><?= $s['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-red">Inactive</span>' ?></td>
          <td style="font-size:12px;color:var(--muted);"><?= formatDate($s['created_at']) ?></td>
          <td>
            <div style="display:flex;gap:6px;">
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                <button class="btn-ghost btn-sm" type="submit"><?= $s['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this staff member?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                <button class="btn-red btn-sm" type="submit">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($staff)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px;">No staff members yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Staff -->
<div class="card" style="max-width:520px;">
  <div class="card-title">Add Staff Member</div>
  <form method="POST" action="index.php?page=staff">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-group"><label>Full Name</label><input type="text" name="name" required></div>
    <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
    <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
    <div class="form-group">
      <label>Password <small style="text-transform:none;color:var(--muted);">(min 8 chars)</small></label>
      <input type="password" name="password" minlength="8" required>
    </div>
    <button class="btn-primary" type="submit">Add Staff Member</button>
  </form>
</div>
