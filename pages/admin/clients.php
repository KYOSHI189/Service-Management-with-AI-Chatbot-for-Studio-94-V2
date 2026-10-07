<?php
// ============================================================
// CLIENTS — Client Directory (New / Returning)
// Order: Newest to Oldest (by registration date)
// ============================================================
requireRole('admin');
$pdo = db();

$search = trim($_GET['q'] ?? '');
$segmentFilter = $_GET['segment'] ?? 'all';  // all | new | returning

// ============================================================
// GET CLIENTS WITH STATS
// ============================================================
$sql = "
    SELECT u.*,
           COUNT(DISTINCT b.id)                                            AS total_bookings,
           COUNT(DISTINCT CASE WHEN b.status = 'Completed' THEN b.id END)  AS completed_bookings,
           COALESCE(SUM(CASE WHEN py.status = 'PAID' THEN py.amount END), 0) AS total_spent,
           MAX(b.date)                                                     AS last_booking,
           MIN(b.date)                                                     AS first_booking,
           lc.total_bookings                                               AS loyalty_count,
           lc.card_number                                                  AS loyalty_card
    FROM users u
    LEFT JOIN bookings b  ON b.user_id = u.id
    LEFT JOIN payments py ON py.user_id = u.id
    LEFT JOIN loyalty_cards lc ON lc.user_id = u.id
    WHERE u.role = 'client'
";

$params = [];
if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allClients = $stmt->fetchAll();

// ============================================================
// SEGMENTATION LOGIC
// ============================================================
function getSegment($totalBookings) {
    if ($totalBookings >= 2) return 'Returning';
    return 'New';
}

function getSegmentInfo($segment) {
    if ($segment === 'Returning') {
        return [
            'badge' => 'badge-blue',
            'icon' => '🔄',
            'label' => 'Returning',
            'color' => '#3B82F6',
            'bg' => '#DBEAFE',
            'text' => '#1E40AF',
            'border' => '#93C5FD',
            'description' => '2+ bookings',
        ];
    }
    return [
        'badge' => 'badge-gray',
        'icon' => '✨',
        'label' => 'New',
        'color' => '#6B7280',
        'bg' => '#F3F4F6',
        'text' => '#4B5563',
        'border' => '#D1D5DB',
        'description' => '0-1 bookings',
    ];
}

// ============================================================
// APPLY SEGMENT FILTER
// ============================================================
$clients = [];
$newCount = 0;
$returningCount = 0;

foreach ($allClients as $c) {
    $seg = getSegment((int)$c['total_bookings']);
    if ($seg === 'Returning') $returningCount++;
    else $newCount++;

    if ($segmentFilter === 'all' || strtolower($segmentFilter) === strtolower($seg)) {
        $clients[] = $c;
    }
}

$totalClients = count($allClients);
?>

<!-- ============================================================ -->
<!-- PAGE BANNER -->
<!-- ============================================================ -->
<div class="page-banner">
    <div class="page-banner-text">
        <div class="eyebrow">Management</div>
        <h2><strong>Client Directory</strong></h2>
        <p>View all clients with segmentation, bookings, and total spending. <span style="font-size:11px;opacity:0.7;">⬇️ Newest first</span></p>
    </div>
    <div class="page-banner-art">👥</div>
</div>

<!-- ============================================================ -->
<!-- HIGHLIGHTED STATS SUMMARY -->
<!-- ============================================================ -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    
    <!-- Total Clients -->
    <div style="background:white;border:1px solid var(--border);border-radius:14px;padding:20px;position:relative;overflow:hidden;">
        <div style="position:absolute;top:0;right:0;width:80px;height:80px;background:radial-gradient(circle,rgba(108,99,255,0.08),transparent);border-radius:50%;"></div>
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:700;">Total Clients</div>
        <div style="font-size:32px;font-weight:700;color:var(--dark);margin-top:6px;"><?= $totalClients ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px;">Registered users</div>
    </div>
    
    <!-- New Clients — HIGHLIGHTED -->
    <div style="background:linear-gradient(135deg,#F3F4F6,#E5E7EB);border:2px solid #D1D5DB;border-radius:14px;padding:20px;position:relative;overflow:hidden;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="position:absolute;top:12px;right:16px;font-size:28px;opacity:0.4;">✨</div>
        <div style="font-size:11px;color:#4B5563;text-transform:uppercase;letter-spacing:1px;font-weight:700;">✨ New Clients</div>
        <div style="font-size:32px;font-weight:700;color:#374151;margin-top:6px;"><?= $newCount ?></div>
        <div style="font-size:12px;color:#6B7280;margin-top:4px;font-weight:500;">0-1 bookings</div>
    </div>
    
    <!-- Returning Clients — HIGHLIGHTED -->
    <div style="background:linear-gradient(135deg,#DBEAFE,#BFDBFE);border:2px solid #60A5FA;border-radius:14px;padding:20px;position:relative;overflow:hidden;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="position:absolute;top:12px;right:16px;font-size:28px;opacity:0.4;">🔄</div>
        <div style="font-size:11px;color:#1E40AF;text-transform:uppercase;letter-spacing:1px;font-weight:700;">🔄 Returning Clients</div>
        <div style="font-size:32px;font-weight:700;color:#1E3A8A;margin-top:6px;"><?= $returningCount ?></div>
        <div style="font-size:12px;color:#1E40AF;margin-top:4px;font-weight:500;">2+ bookings</div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SEGMENT FILTER TABS -->
<!-- ============================================================ -->
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;padding:16px 20px;background:#F5F3F0;border:1px solid #E0E0E0;border-radius:12px;">
    <div style="font-size:12px;font-weight:700;color:#4A453F;text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;margin-right:8px;">
        🎯 Filter by Segment:
    </div>
    
    <a href="index.php?page=clients&segment=all<?= $search ? '&q=' . urlencode($search) : '' ?>" 
       style="padding:7px 16px;border-radius:50px;font-size:12px;font-weight:700;text-decoration:none;transition:all 0.2s;
              <?= $segmentFilter === 'all' 
                  ? 'background:#4A453F;color:#F5F3F0;border:1px solid #4A453F;' 
                  : 'background:#ECECEC;color:#6B6359;border:1px solid #E0E0E0;' ?>">
        👥 All (<?= $totalClients ?>)
    </a>
    
    <a href="index.php?page=clients&segment=new<?= $search ? '&q=' . urlencode($search) : '' ?>" 
       style="padding:7px 16px;border-radius:50px;font-size:12px;font-weight:700;text-decoration:none;transition:all 0.2s;
              <?= $segmentFilter === 'new' 
                  ? 'background:#374151;color:#F3F4F6;border:1px solid #374151;' 
                  : 'background:#F3F4F6;color:#4B5563;border:1px solid #D1D5DB;' ?>">
        ✨ New (<?= $newCount ?>)
    </a>
    
    <a href="index.php?page=clients&segment=returning<?= $search ? '&q=' . urlencode($search) : '' ?>" 
       style="padding:7px 16px;border-radius:50px;font-size:12px;font-weight:700;text-decoration:none;transition:all 0.2s;
              <?= $segmentFilter === 'returning' 
                  ? 'background:#1E40AF;color:#DBEAFE;border:1px solid #1E40AF;' 
                  : 'background:#DBEAFE;color:#1E40AF;border:1px solid #93C5FD;' ?>">
        🔄 Returning (<?= $returningCount ?>)
    </a>
    
    <div style="margin-left:auto;display:flex;gap:6px;align-items:center;">
        <form method="GET" action="index.php" style="display:flex;gap:6px;">
            <input type="hidden" name="page" value="clients">
            <input type="hidden" name="segment" value="<?= clean($segmentFilter) ?>">
            <input type="text" name="q" value="<?= clean($search) ?>" placeholder="Search name, email, phone…"
                   style="padding:8px 14px;border:1.5px solid #E0E0E0;border-radius:50px;font-size:12px;width:200px;outline:none;background:white;">
            <?php if ($search): ?>
                <a href="index.php?page=clients<?= $segmentFilter !== 'all' ? '&segment=' . urlencode($segmentFilter) : '' ?>" 
                   class="btn-ghost btn-sm" style="display:inline-flex;align-items:center;padding:8px 12px;">✕</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- CLIENTS TABLE -->
<!-- ============================================================ -->
<div class="card">
    <div class="section-header">
        <h3>
            <strong>Client List</strong>
            <span style="font-size:13px;color:var(--muted);font-weight:400;">
                (<?= count($clients) ?> shown) · 
                <?php if ($segmentFilter === 'new'): ?>
                    ✨ New Clients
                <?php elseif ($segmentFilter === 'returning'): ?>
                    🔄 Returning Clients
                <?php else: ?>
                    👥 All Clients
                <?php endif; ?>
                · ⬇️ Newest first
            </span>
        </h3>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th style="text-align:center;">Bookings</th>
                    <th style="text-align:right;">Total Spent</th>
                    <th style="text-align:center;">Segment</th>
                    <th>Last Booking</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c):
                    $totalBookings = (int)$c['total_bookings'];
                    $segment = getSegment($totalBookings);
                    $segInfo = getSegmentInfo($segment);
                    $loyaltyCount = (int)($c['loyalty_count'] ?? 0);

                    // ✅ Highlight row for Returning clients
                    $rowHighlight = ($segment === 'Returning') 
                        ? 'background:linear-gradient(90deg, #EFF6FF 0%, #FFFFFF 8%);border-left:3px solid #3B82F6;' 
                        : 'border-left:3px solid transparent;';
                ?>
                <tr style="<?= $rowHighlight ?>">
                    <td>
                        <strong><?= clean($c['name']) ?></strong>
                        <?php if ($loyaltyCount > 0): ?>
                            <div style="font-size:10px;color:var(--muted);margin-top:3px;">
                                <span style="background:linear-gradient(135deg,#EAB308,#CA8A04);color:#fff;padding:2px 8px;border-radius:50px;font-size:9px;font-weight:700;">
                                    🎫 <?= $loyaltyCount ?> bookings
                                </span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= clean($c['email']) ?></td>
                    <td><?= clean($c['phone'] ?: '—') ?></td>
                    <td style="text-align:center;">
                        <strong><?= $totalBookings ?></strong>
                        <?php if ((int)$c['completed_bookings'] > 0): ?>
                            <div style="font-size:10px;color:var(--muted);">
                                <?= (int)$c['completed_bookings'] ?> completed
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <strong><?= formatMoney($c['total_spent']) ?></strong>
                    </td>
                    <td style="text-align:center;">
                        <!-- ===== HIGHLIGHTED SEGMENT BADGE ===== -->
                        <div style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:50px;font-size:11px;font-weight:700;
                                    background:<?= $segInfo['bg'] ?>;color:<?= $segInfo['text'] ?>;border:2px solid <?= $segInfo['border'] ?>;">
                            <span style="font-size:14px;"><?= $segInfo['icon'] ?></span>
                            <span><?= $segInfo['label'] ?></span>
                        </div>
                        <div style="font-size:10px;color:var(--muted);margin-top:4px;font-style:italic;">
                            <?= $segInfo['description'] ?>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--muted);">
                        <?= $c['last_booking'] ? formatDate($c['last_booking']) : '—' ?>
                    </td>
                    <td style="font-size:12px;color:var(--muted);">
                        <?= formatDate($c['created_at']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;color:var(--muted);padding:40px;">
                            <div style="font-size:48px;margin-bottom:12px;">📭</div>
                            <div style="font-size:15px;font-weight:500;">No clients found.</div>
                            <?php if ($search || $segmentFilter !== 'all'): ?>
                                <div style="font-size:13px;margin-top:4px;">
                                    <a href="index.php?page=clients" class="link-text">Clear filters</a>
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
<!-- SEGMENTATION LEGEND -->
<!-- ============================================================ -->
<div class="card" style="margin-top:20px;background:#FAF7F2;border:1px solid #E0E0E0;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
        <span style="font-size:20px;">📊</span>
        <div>
            <div style="font-weight:700;font-size:14px;color:#4A453F;">Client Segmentation Guide</div>
            <div style="font-size:11px;color:#8B8177;margin-top:2px;">How clients are classified by the system</div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
        <div style="padding:12px;background:#F3F4F6;border:1px solid #D1D5DB;border-radius:10px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <span style="font-size:18px;">✨</span>
                <strong style="color:#374151;">New Client</strong>
            </div>
            <div style="font-size:12px;color:#6B7280;">
                May <strong>0-1 bookings</strong> pa lang. Bagong customer na nagsisimula pa lang sa studio.
            </div>
        </div>
        <div style="padding:12px;background:#DBEAFE;border:1px solid #93C5FD;border-radius:10px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <span style="font-size:18px;">🔄</span>
                <strong style="color:#1E40AF;">Returning Client</strong>
            </div>
            <div style="font-size:12px;color:#1E40AF;">
                May <strong>2+ bookings</strong> na. Bumabalik na customer — loyal sa studio.
            </div>
        </div>
    </div>
</div>