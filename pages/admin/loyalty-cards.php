<?php
// ============================================================
// LOYALTY CARDS — Admin View
// Corrected tiers: 2, 4, 7, 10
// ============================================================
requireRole('admin');
$pdo = db();

$search = trim($_GET['q'] ?? '');
$tier   = $_GET['tier'] ?? 'all';

// ============================================================
// GET CLIENTS WITH LOYALTY DATA
// ============================================================
$sql = "SELECT u.id, u.name, u.email, u.phone,
               COALESCE(lc.total_bookings,0) AS total_bookings,
               COALESCE(lc.card_number,'—')  AS card_number,
               COALESCE(lc.status,'Pending') AS card_status,
               COALESCE(lc.rewards_used,'[]') AS rewards_used
        FROM users u
        LEFT JOIN loyalty_cards lc ON lc.user_id = u.id
        WHERE u.role = 'client'";
$params = [];
if ($search) {
    $sql    .= " AND u.name LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY total_bookings DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all = $stmt->fetchAll();

// Add tier to each
foreach ($all as &$c) {
    $c['tier'] = getLoyaltyTier((int)$c['total_bookings']);
}
unset($c);

// ============================================================
// TIER FILTER (Corrected: 2, 4, 7, 10)
// ============================================================
if ($tier === '2')  $all = array_filter($all, fn($c) => $c['total_bookings'] >= 2);
if ($tier === '4')  $all = array_filter($all, fn($c) => $c['total_bookings'] >= 4);
if ($tier === '7')  $all = array_filter($all, fn($c) => $c['total_bookings'] >= 7);
if ($tier === '10') $all = array_filter($all, fn($c) => $c['total_bookings'] >= 10);

// ============================================================
// STATS — CORRECTED TIERS
// ============================================================
$total   = count($all);
$lvl2    = count(array_filter($all, fn($c) => $c['total_bookings'] >= 2));
$lvl4    = count(array_filter($all, fn($c) => $c['total_bookings'] >= 4));
$lvl7    = count(array_filter($all, fn($c) => $c['total_bookings'] >= 7));
$lvl10   = count(array_filter($all, fn($c) => $c['total_bookings'] >= 10));

// ============================================================
// TIER BADGE HELPER
// ============================================================
function getLoyaltyBadge($totalBookings) {
    if ($totalBookings >= 10) {
        return '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:50px;font-size:11px;font-weight:700;background:linear-gradient(135deg,#FEF3C7,#FDE68A);color:#92400E;border:1px solid #EAB308;">🎉 50% OFF</span>';
    }
    if ($totalBookings >= 7) {
        return '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:50px;font-size:11px;font-weight:700;background:#F3E5F5;color:#7B1FA2;border:1px solid #CE93D8;">🎨 +1 BACKDROP</span>';
    }
    if ($totalBookings >= 4) {
        return '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:50px;font-size:11px;font-weight:700;background:#FFF3E0;color:#E65100;border:1px solid #FFB74D;">🖼️ +1 PRINT</span>';
    }
    if ($totalBookings >= 2) {
        return '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:50px;font-size:11px;font-weight:700;background:#E8F5E9;color:#2E7D32;border:1px solid #A5D6A7;">⏱️ +5 MINS</span>';
    }
    return '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:50px;font-size:11px;font-weight:700;background:#ECECEC;color:#6B6359;border:1px solid #E0E0E0;">No Card</span>';
}

// ============================================================
// PROGRESS HELPER
// ============================================================
function getProgressToNextTier($totalBookings) {
    $tiers = [2 => '+5 MIN', 4 => '+1 PRINT', 7 => '+1 BACKDROP', 10 => '50% OFF'];
    
    $nextTier = null;
    $prevTier = 0;
    
    foreach ($tiers as $count => $label) {
        if ($totalBookings < $count) {
            $nextTier = $count;
            break;
        }
        $prevTier = $count;
    }
    
    if ($nextTier === null) {
        return ['pct' => 100, 'next' => null, 'label' => 'Max tier reached', 'prev' => 10];
    }
    
    $progressInTier = $totalBookings - $prevTier;
    $tierSize = $nextTier - $prevTier;
    $pct = $prevTier === 0 
        ? round(($totalBookings / $nextTier) * 100)
        : round(($progressInTier / $tierSize) * 100);
    
    return [
        'pct'   => max(0, min(100, $pct)),
        'next'  => $tiers[$nextTier],
        'next_count' => $nextTier,
        'label' => $totalBookings . ' / ' . $nextTier,
        'prev'  => $prevTier,
    ];
}
?>

<!-- ============================================================ -->
<!-- PAGE BANNER -->
<!-- ============================================================ -->
<div class="page-banner" style="margin-bottom:20px;">
  <div class="page-banner-text">
    <div class="eyebrow">Admin View Only</div>
    <h2><strong>🎫 Loyalty Cards</strong></h2>
    <p>View all client loyalty metrics and tier progress.</p>
  </div>
  <div class="page-banner-art">🎫</div>
</div>

<!-- ============================================================ -->
<!-- STATS — CORRECTED TIERS -->
<!-- ============================================================ -->
<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-card">
    <div class="stat-eyebrow">Total Cardholders</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Registered clients</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">+5 MINS Tier</div>
    <div class="stat-value" style="color:#2E7D32;"><?= $lvl2 ?></div>
    <div class="stat-label">2+ bookings</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">+1 PRINT Tier</div>
    <div class="stat-value" style="color:#E65100;"><?= $lvl4 ?></div>
    <div class="stat-label">4+ bookings</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">+1 BACKDROP Tier</div>
    <div class="stat-value" style="color:#7B1FA2;"><?= $lvl7 ?></div>
    <div class="stat-label">7+ bookings</div>
  </div>
  <div class="stat-card" style="background:linear-gradient(135deg,#FEF3C7,#FDE68A);border:2px solid #EAB308;">
    <div class="stat-eyebrow" style="color:#92400E;">🎉 50% OFF Tier</div>
    <div class="stat-value" style="color:#92400E;"><?= $lvl10 ?></div>
    <div class="stat-label" style="color:#92400E;">10+ bookings</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- FILTER + SEARCH -->
<!-- ============================================================ -->
<div class="card" style="margin-bottom:20px;background:#F5F3F0;border:1px solid #E0E0E0;">
  <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:center;">
    
    <!-- Filter Tabs -->
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
      <div style="font-size:11px;font-weight:700;color:#4A453F;text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;margin-right:8px;">
        🎯 Filter:
      </div>
      
      <a href="index.php?page=loyalty-cards&tier=all<?= $search ? '&q=' . urlencode($search) : '' ?>" 
         style="padding:6px 14px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s;
                <?= $tier === 'all' 
                    ? 'background:#4A453F;color:#F5F3F0;border:1px solid #4A453F;' 
                    : 'background:#ECECEC;color:#6B6359;border:1px solid #E0E0E0;' ?>">
        👥 All (<?= count($all) ?>)
      </a>
      
      <a href="index.php?page=loyalty-cards&tier=2<?= $search ? '&q=' . urlencode($search) : '' ?>" 
         style="padding:6px 14px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s;
                <?= $tier === '2' 
                    ? 'background:#2E7D32;color:white;border:1px solid #2E7D32;' 
                    : 'background:#E8F5E9;color:#2E7D32;border:1px solid #A5D6A7;' ?>">
        ⏱️ +5 MINS (<?= $lvl2 ?>)
      </a>
      
      <a href="index.php?page=loyalty-cards&tier=4<?= $search ? '&q=' . urlencode($search) : '' ?>" 
         style="padding:6px 14px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s;
                <?= $tier === '4' 
                    ? 'background:#E65100;color:white;border:1px solid #E65100;' 
                    : 'background:#FFF3E0;color:#E65100;border:1px solid #FFB74D;' ?>">
        🖼️ +1 PRINT (<?= $lvl4 ?>)
      </a>
      
      <a href="index.php?page=loyalty-cards&tier=7<?= $search ? '&q=' . urlencode($search) : '' ?>" 
         style="padding:6px 14px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s;
                <?= $tier === '7' 
                    ? 'background:#7B1FA2;color:white;border:1px solid #7B1FA2;' 
                    : 'background:#F3E5F5;color:#7B1FA2;border:1px solid #CE93D8;' ?>">
        🎨 +1 BACKDROP (<?= $lvl7 ?>)
      </a>
      
      <a href="index.php?page=loyalty-cards&tier=10<?= $search ? '&q=' . urlencode($search) : '' ?>" 
         style="padding:6px 14px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s;
                <?= $tier === '10' 
                    ? 'background:#EAB308;color:#92400E;border:1px solid #EAB308;' 
                    : 'background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;' ?>">
        🎉 50% OFF (<?= $lvl10 ?>)
      </a>
    </div>
    
    <!-- Search -->
    <form method="GET" action="index.php" style="display:flex;gap:6px;">
      <input type="hidden" name="page" value="loyalty-cards">
      <input type="hidden" name="tier" value="<?= clean($tier) ?>">
      <input type="text" name="q" value="<?= clean($search) ?>" placeholder="Search client name…"
             style="padding:8px 14px;border:1px solid #E0E0E0;border-radius:50px;font-size:12px;width:200px;outline:none;background:white;">
      <?php if ($search): ?>
        <a href="index.php?page=loyalty-cards&tier=<?= clean($tier) ?>" 
           style="padding:8px 14px;background:#ECECEC;border:1px solid #E0E0E0;border-radius:50px;font-size:12px;text-decoration:none;color:#6B6359;">✕</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ============================================================ -->
<!-- LOYALTY CARDS TABLE -->
<!-- ============================================================ -->
<div class="card">
  <div class="section-header">
    <h3><strong>All Clients with Loyalty Status</strong></h3>
    <div style="display:inline-flex;align-items:center;gap:6px;background:#f0f0f0;padding:6px 14px;border-radius:50px;font-size:11px;font-weight:500;color:var(--muted);">
      🔒 Admin View Only — No edits permitted
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Client Name</th>
          <th>Card #</th>
          <th style="text-align:center;">Bookings</th>
          <th>Tier</th>
          <th>Progress to Next</th>
          <th>Rewards Earned</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($all as $c):
          $totalBookings = (int)$c['total_bookings'];
          $rewards = getLoyaltyRewards($totalBookings);
          $progress = getProgressToNextTier($totalBookings);
          $tierBadge = getLoyaltyBadge($totalBookings);
          
          // Row highlight for 50% OFF eligible
          $rowStyle = ($totalBookings >= 10) 
              ? 'background:linear-gradient(90deg, #FEF3C7 0%, #FFFFFF 8%);border-left:3px solid #EAB308;' 
              : 'border-left:3px solid transparent;';
        ?>
        <tr style="<?= $rowStyle ?>">
          <td>
            <strong><?= clean($c['name']) ?></strong>
            <div style="font-size:11px;color:var(--muted);"><?= clean($c['email']) ?></div>
          </td>
          <td><code style="font-size:11px;background:#ECECEC;padding:2px 8px;border-radius:4px;"><?= clean($c['card_number']) ?></code></td>
          <td style="text-align:center;">
            <strong style="font-size:16px;"><?= $totalBookings ?></strong>
            <div style="height:6px;background:#ECECEC;border-radius:3px;margin-top:6px;width:80px;margin-left:auto;margin-right:auto;overflow:hidden;">
              <div style="height:100%;width:<?= $progress['pct'] ?>%;background:linear-gradient(90deg,#6C63FF,#8B5CF6);border-radius:3px;transition:width 0.3s;"></div>
            </div>
          </td>
          <td><?= $tierBadge ?></td>
          <td style="font-size:12px;">
            <?php if ($progress['next'] !== null): ?>
              <div style="font-weight:600;color:#4A453F;"><?= $progress['label'] ?> bookings</div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px;">
                Next: <strong style="color:#6C63FF;"><?= $progress['next'] ?></strong>
                (<?= $progress['next_count'] - $totalBookings ?> more)
              </div>
            <?php else: ?>
              <div style="font-weight:700;color:#EAB308;">🏆 Maximum tier reached!</div>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;">
            <?php if (!empty($rewards)): ?>
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                <?php foreach ($rewards as $r): ?>
                  <span style="display:inline-block;padding:3px 8px;background:#E8F5E9;color:#2E7D32;border-radius:4px;font-size:10px;font-weight:600;">
                    ✅ <?= clean($r) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <span style="color:var(--muted);">—</span>
            <?php endif; ?>
          </td>
          <td><?= statusBadge($c['card_status']) ?></td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($all)): ?>
          <tr>
            <td colspan="7" style="text-align:center;color:var(--muted);padding:40px;">
              <div style="font-size:48px;margin-bottom:12px;">📭</div>
              <div style="font-size:15px;font-weight:500;">No clients found.</div>
              <?php if ($search || $tier !== 'all'): ?>
                <div style="font-size:13px;margin-top:4px;">
                  <a href="index.php?page=loyalty-cards" class="link-text">Clear filters</a>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================ -->
<!-- TIER GUIDE -->
<!-- ============================================================ -->
<div class="card" style="margin-top:20px;background:#FAF7F2;border:1px solid #E0E0E0;">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
    <span style="font-size:24px;">🎫</span>
    <div>
      <div style="font-weight:700;font-size:15px;color:#4A453F;">Loyalty Tier Guide</div>
      <div style="font-size:12px;color:#8B8177;margin-top:2px;">Rewards unlocked at every milestone</div>
    </div>
  </div>
  
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
    
    <div style="padding:14px;background:#E8F5E9;border:2px solid #A5D6A7;border-radius:10px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <span style="font-size:20px;">⏱️</span>
        <strong style="color:#2E7D32;font-size:14px;">2nd Booking</strong>
      </div>
      <div style="font-size:12px;color:#2E7D32;font-weight:600;">+5 MINUTES</div>
      <div style="font-size:11px;color:#6B7280;margin-top:4px;">Extra 5 minutes on shoot time</div>
    </div>
    
    <div style="padding:14px;background:#FFF3E0;border:2px solid #FFB74D;border-radius:10px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <span style="font-size:20px;">🖼️</span>
        <strong style="color:#E65100;font-size:14px;">4th Booking</strong>
      </div>
      <div style="font-size:12px;color:#E65100;font-weight:600;">+1 PRINT OUT</div>
      <div style="font-size:11px;color:#6B7280;margin-top:4px;">Free additional print</div>
    </div>
    
    <div style="padding:14px;background:#F3E5F5;border:2px solid #CE93D8;border-radius:10px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <span style="font-size:20px;">🎨</span>
        <strong style="color:#7B1FA2;font-size:14px;">7th Booking</strong>
      </div>
      <div style="font-size:12px;color:#7B1FA2;font-weight:600;">+1 BACKDROP</div>
      <div style="font-size:11px;color:#6B7280;margin-top:4px;">Extra backdrop access</div>
    </div>
    
    <div style="padding:14px;background:linear-gradient(135deg,#FEF3C7,#FDE68A);border:2px solid #EAB308;border-radius:10px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <span style="font-size:20px;">🎉</span>
        <strong style="color:#92400E;font-size:14px;">10th Booking</strong>
      </div>
      <div style="font-size:12px;color:#92400E;font-weight:700;">50% OFF</div>
      <div style="font-size:11px;color:#78350F;margin-top:4px;">Half price on next booking</div>
    </div>
    
  </div>
</div>