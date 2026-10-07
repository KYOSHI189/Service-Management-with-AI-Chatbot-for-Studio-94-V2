<?php
// ============================================================
// SCHEDULE - Staff View (Split Online & Walk-in)
// ============================================================

requireRole('staff');
$pdo = db();

$view = $_GET['view'] ?? 'upcoming';

// ============================================================
// BUILD QUERY
// ============================================================
$where  = "b.status NOT IN ('Cancelled', 'Rejected')";
$params = [];

switch ($view) {
    case 'today':
        $where .= " AND b.date = CURDATE()";
        break;
    case 'tomorrow':
        $where .= " AND b.date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'week':
        $where .= " AND WEEK(b.date,1)=WEEK(CURDATE(),1) AND YEAR(b.date)=YEAR(CURDATE())";
        break;
    default:
        $where .= " AND b.date >= CURDATE()";
        break;
}

$stmt = $pdo->prepare("
    SELECT b.*, 
           CASE 
               WHEN u.id IS NULL THEN 'Walk-in Client'
               ELSE u.name 
           END as client_name,
           u.phone as client_phone,
           u.email as client_email,
           p.name as pkg_name, 
           p.duration,
           b.booking_ref,
           b.type as booking_type
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id
    JOIN packages p ON b.package_id = p.id
    WHERE $where 
    ORDER BY b.date ASC, b.time ASC
");
$stmt->execute($params);
$schedule = $stmt->fetchAll();

// ============================================================
// GROUP BY DATE, THEN BY TYPE
// ============================================================
$byDate = [];
foreach ($schedule as $b) {
    $date = $b['date'];
    $isWalkin = ($b['booking_type'] === 'walk-in') || (strpos($b['booking_ref'], 'WALK') !== false);
    $type = $isWalkin ? 'walkin' : 'online';

    if (!isset($byDate[$date])) {
        $byDate[$date] = ['online' => [], 'walkin' => []];
    }
    $byDate[$date][$type][] = $b;
}

$flash = getFlash();
?>

<!-- ============================================================ -->
<!-- PAGE BANNER — BLACK -->
<!-- ============================================================ -->
<div class="page-banner" style="background:#0A0A0A;color:#FFFFFF;">
  <div class="page-banner-text">
    <div class="eyebrow" style="color:rgba(255,255,255,0.7);">Schedule</div>
    <h2 style="color:#FFFFFF;"><strong>🗓️ Schedule</strong></h2>
    <p style="color:rgba(255,255,255,0.85);">View all upcoming sessions</p>
  </div>
  <div class="page-banner-art" style="opacity:0.8;">🗓️</div>
</div>

<!-- FLASH -->
<?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'error' : 'warning') ?>" style="margin-bottom:20px;">
    <?= clean($flash['msg']) ?>
  </div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- SCHEDULE CARD -->
<!-- ============================================================ -->
<div class="card">
  <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <h3><strong>📋 Sessions</strong></h3>
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
      <?php 
      $viewOptions = [
        'upcoming' => 'All Upcoming',
        'today'    => 'Today',
        'tomorrow' => 'Tomorrow',
        'week'     => 'This Week'
      ];
      foreach ($viewOptions as $v => $l): 
        $isActive = ($view === $v);
      ?>
        <a href="index.php?page=schedule&view=<?= $v ?>"
           style="padding:6px 14px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s;
                  <?= $isActive 
                      ? 'background:#0A0A0A;color:#FFFFFF;border:1px solid #0A0A0A;' 
                      : 'background:#FFFFFF;color:#0A0A0A;border:1px solid #E0E0E0;' ?>">
          <?= $l ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($byDate)): ?>
    <div style="text-align:center;padding:60px 20px;">
      <div style="font-size:56px;margin-bottom:12px;">📭</div>
      <div style="font-size:16px;font-weight:600;color:#0A0A0A;margin-bottom:4px;">No sessions scheduled</div>
      <div style="font-size:13px;color:#8B8177;">for this period.</div>
    </div>
  <?php else: ?>
    <?php foreach ($byDate as $date => $groups): 
      $onlineCount = count($groups['online']);
      $walkinCount = count($groups['walkin']);
      $totalCount  = $onlineCount + $walkinCount;
    ?>
      <div style="margin-bottom:32px;">

        <!-- DATE HEADER -->
        <div style="font-size:12px;font-weight:700;color:#0A0A0A;text-transform:uppercase;letter-spacing:1px;padding:12px 16px;background:#F5F5F5;border-radius:8px;display:flex;align-items:center;gap:10px;margin-bottom:14px;border-left:4px solid #0A0A0A;">
          📅 <?= date('l, F j, Y', strtotime($date)) ?>
          <?php if ($date === date('Y-m-d')): ?>
            <span style="background:#0A0A0A;color:#FFFFFF;font-size:9px;padding:3px 10px;border-radius:50px;font-weight:700;letter-spacing:0.5px;">TODAY</span>
          <?php endif; ?>
          <span style="margin-left:auto;font-size:11px;color:#8B8177;font-weight:500;letter-spacing:0;text-transform:none;">
            <?= $totalCount ?> session<?= $totalCount !== 1 ? 's' : '' ?>
          </span>
        </div>

        <!-- ONLINE SECTION -->
        <div style="margin-bottom:20px;border-radius:10px;overflow:hidden;border:1px solid #E0E0E0;">
          <div style="padding:10px 16px;font-size:12px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;display:flex;align-items:center;gap:8px;background:#0A0A0A;color:#FFFFFF;">
            🌐 Online Bookings
            <span style="background:rgba(255,255,255,0.2);color:#FFFFFF;padding:2px 8px;border-radius:50px;font-size:10px;font-weight:700;margin-left:auto;">
              <?= $onlineCount ?>
            </span>
          </div>
          <?php if ($onlineCount === 0): ?>
            <div style="padding:20px;text-align:center;font-size:12px;color:#8B8177;background:#FFFFFF;font-style:italic;">
              No online bookings for this date
            </div>
          <?php else: ?>
            <div style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;">
              <table style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:13px;">
                <thead>
                  <tr>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:90px;">Time</th>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:220px;">Client</th>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:150px;">Package</th>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:110px;">Duration</th>
                    <th style="text-align:center;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:60px;">Pax</th>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:150px;">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($groups['online'] as $b): ?>
                  <tr onmouseover="this.style.background='#F5F5F5'" onmouseout="this.style.background='#FFFFFF'">
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;">
                      <span style="font-weight:700;font-size:13px;color:#0A0A0A;"><?= clean($b['time']) ?></span>
                    </td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;">
                      <div style="display:flex;flex-direction:column;gap:3px;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:#0A0A0A;word-wrap:break-word;"><?= clean($b['client_name']) ?></div>
                        <div style="font-size:11px;color:#8B8177;">📱 <?= clean($b['client_phone'] ?? 'No phone') ?></div>
                      </div>
                    </td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;">
                      <strong style="color:#0A0A0A;"><?= clean($b['pkg_name']) ?></strong>
                    </td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;color:#0A0A0A;"><?= clean($b['duration']) ?></td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;text-align:center;font-weight:600;color:#0A0A0A;"><?= $b['number_of_people'] ?? $b['people'] ?? 1 ?></td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;"><?= statusBadge($b['status']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- WALK-IN SECTION -->
        <div style="margin-bottom:20px;border-radius:10px;overflow:hidden;border:1px solid #E0E0E0;">
          <div style="padding:10px 16px;font-size:12px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;display:flex;align-items:center;gap:8px;background:#F5F5F5;color:#0A0A0A;border-bottom:1px solid #E0E0E0;">
            🚶 Walk-in Bookings
            <span style="background:#FFFFFF;color:#0A0A0A;border:1px solid #0A0A0A;padding:2px 8px;border-radius:50px;font-size:10px;font-weight:700;margin-left:auto;">
              <?= $walkinCount ?>
            </span>
          </div>
          <?php if ($walkinCount === 0): ?>
            <div style="padding:20px;text-align:center;font-size:12px;color:#8B8177;background:#FFFFFF;font-style:italic;">
              No walk-in bookings for this date
            </div>
          <?php else: ?>
            <div style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;">
              <table style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:13px;">
                <thead>
                  <tr>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:90px;">Time</th>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:220px;">Client</th>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:150px;">Package</th>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:110px;">Duration</th>
                    <th style="text-align:center;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:60px;">Pax</th>
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#8B8177;background:#FFFFFF;border-bottom:1px solid #E0E0E0;white-space:nowrap;width:150px;">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($groups['walkin'] as $b): ?>
                  <tr onmouseover="this.style.background='#F5F5F5'" onmouseout="this.style.background='#FFFFFF'">
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;">
                      <span style="font-weight:700;font-size:13px;color:#0A0A0A;"><?= clean($b['time']) ?></span>
                    </td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;">
                      <div style="display:flex;flex-direction:column;gap:3px;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:#0A0A0A;word-wrap:break-word;"><?= clean($b['client_name']) ?></div>
                        <div style="font-size:11px;color:#8B8177;">📱 <?= clean($b['client_phone'] ?? 'No phone') ?></div>
                      </div>
                    </td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;">
                      <strong style="color:#0A0A0A;"><?= clean($b['pkg_name']) ?></strong>
                    </td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;color:#0A0A0A;"><?= clean($b['duration']) ?></td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;text-align:center;font-weight:600;color:#0A0A0A;"><?= $b['number_of_people'] ?? $b['people'] ?? 1 ?></td>
                    <td style="padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FFFFFF;"><?= statusBadge($b['status']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>