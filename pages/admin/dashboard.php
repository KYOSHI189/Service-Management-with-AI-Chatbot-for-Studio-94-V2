<?php
// ============================================================
// STUDIO 94 SNAPTRACK
// ADMIN DASHBOARD - SALES & ANALYTICS
// ============================================================

requireRole('admin');

$pdo = db();

// ============================================================
// HELPER
// ============================================================

function dashboardMoney($value): string
{
    return '₱' . number_format((float)$value, 2);
}

function dashboardEsc($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// STATS CARDS
// ============================================================

// Actual money received from PAID payment records.
$totalRevenue = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE UPPER(status) = 'PAID'
")->fetchColumn();

// Total value of completed bookings/services.
$totalSales = (float)$pdo->query("
    SELECT COALESCE(SUM(package_price), 0)
    FROM bookings
    WHERE status = 'Completed'
")->fetchColumn();

// Completed booking value for today.
$salesToday = (float)$pdo->query("
    SELECT COALESCE(SUM(package_price), 0)
    FROM bookings
    WHERE date = CURDATE()
      AND status = 'Completed'
")->fetchColumn();

// Bookings waiting for payment.
$pendingPayments = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE status IN ('Awaiting Approval', 'Approved (Unpaid)')
      AND package_price > 0
")->fetchColumn();

// Total bookings.
$totalBookings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
")->fetchColumn();

// Bookings waiting for admin approval.
$pendingApprove = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE status = 'Awaiting Approval'
")->fetchColumn();

// Walk-in bookings.
$walkinCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE type = 'walk-in'
       OR booking_ref LIKE 'WALK%'
")->fetchColumn();

// Online bookings.
$onlineCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE type = 'online'
      AND booking_ref NOT LIKE 'WALK%'
")->fetchColumn();

// Completed walk-in booking value.
$walkinRevenue = (float)$pdo->query("
    SELECT COALESCE(SUM(package_price), 0)
    FROM bookings
    WHERE (
        type = 'walk-in'
        OR booking_ref LIKE 'WALK%'
    )
    AND status = 'Completed'
")->fetchColumn();

// Completed online booking value.
$onlineRevenue = (float)$pdo->query("
    SELECT COALESCE(SUM(package_price), 0)
    FROM bookings
    WHERE type = 'online'
      AND booking_ref NOT LIKE 'WALK%'
      AND status = 'Completed'
")->fetchColumn();


// ============================================================
// DYNAMIC MONTH RANGE
// ============================================================

$earliestDate = $pdo->query("
    SELECT MIN(date)
    FROM bookings
    WHERE status NOT IN ('Cancelled', 'Rejected')
")->fetchColumn();

if (!$earliestDate) {
    $earliestDate = date('Y-m-d', strtotime('-5 months'));
}

$startMonth = date('Y-m', strtotime($earliestDate));
$endMonth   = date('Y-m');

$startDT = new DateTime($startMonth . '-01');
$endDT   = new DateTime($endMonth . '-01');

$diff = $startDT->diff($endDT);

$monthSpan = ($diff->y * 12) + $diff->m + 1;

// Keep chart within the most recent 24 months.
if ($monthSpan > 24) {
    $startMonth = date('Y-m', strtotime('-23 months'));
    $startDT = new DateTime($startMonth . '-01');
}


// ============================================================
// MONTHLY SALES CHART
// ============================================================

$monthlySales = [];

$period = new DatePeriod(
    $startDT,
    new DateInterval('P1M'),
    (clone $endDT)->modify('+1 month')
);

$salesStmt = $pdo->prepare("
    SELECT COALESCE(SUM(package_price), 0)
    FROM bookings
    WHERE DATE_FORMAT(date, '%Y-%m') = ?
      AND status = 'Completed'
");

foreach ($period as $dt) {

    $m     = $dt->format('Y-m');
    $label = $dt->format('M');
    $year  = $dt->format('Y');

    $salesStmt->execute([$m]);

    $monthlySales[] = [
        'label' => $label,
        'year'  => $year,
        'value' => (float)$salesStmt->fetchColumn()
    ];
}

$maxSales = !empty($monthlySales)
    ? (max(array_column($monthlySales, 'value')) ?: 1)
    : 1;


// ============================================================
// MONTHLY BOOKING TREND
// ============================================================

$monthlyBookings = [];

$bookingsStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM bookings
    WHERE DATE_FORMAT(date, '%Y-%m') = ?
      AND status NOT IN ('Cancelled', 'Rejected')
");

foreach ($period as $dt) {

    $m     = $dt->format('Y-m');
    $label = $dt->format('M');

    $bookingsStmt->execute([$m]);

    $monthlyBookings[] = [
        'label' => $label,
        'value' => (int)$bookingsStmt->fetchColumn()
    ];
}

$maxBookings = !empty($monthlyBookings)
    ? (max(array_column($monthlyBookings, 'value')) ?: 1)
    : 1;


// ============================================================
// TOP SELLING PACKAGES
// ============================================================

$topPackagesStmt = $pdo->query("
    SELECT
        p.name,
        COUNT(*) AS bookings,
        COALESCE(SUM(b.package_price), 0) AS revenue
    FROM bookings b
    INNER JOIN packages p
        ON b.package_id = p.id
    WHERE b.status = 'Completed'
    GROUP BY b.package_id, p.name
    ORDER BY revenue DESC
    LIMIT 5
");

$topPackages = $topPackagesStmt->fetchAll(PDO::FETCH_ASSOC);


// ============================================================
// SALES BY PACKAGE
// ============================================================

$categorySalesStmt = $pdo->query("
    SELECT
        p.name,
        COUNT(*) AS bookings,
        COALESCE(SUM(b.package_price), 0) AS revenue,
        COALESCE(AVG(b.package_price), 0) AS avg_price
    FROM bookings b
    INNER JOIN packages p
        ON b.package_id = p.id
    WHERE b.status = 'Completed'
    GROUP BY b.package_id, p.name
    ORDER BY revenue DESC
");

$categorySales = $categorySalesStmt->fetchAll(PDO::FETCH_ASSOC);


// ============================================================
// BUSIEST DAYS
// ============================================================

$busiestDaysStmt = $pdo->query("
    SELECT
        DAYNAME(date) AS day_name,
        COUNT(*) AS total_bookings,
        SUM(
            CASE
                WHEN status = 'Completed' THEN 1
                ELSE 0
            END
        ) AS completed
    FROM bookings
    WHERE status NOT IN ('Cancelled', 'Rejected')
    GROUP BY DAYNAME(date)
    ORDER BY FIELD(
        day_name,
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    )
");

$busiestDays = $busiestDaysStmt->fetchAll(PDO::FETCH_ASSOC);


// Find actual busiest day.
$busiestDayName  = '';
$busiestDayCount = 0;

foreach ($busiestDays as $d) {

    $count = (int)$d['total_bookings'];

    if ($count > $busiestDayCount) {
        $busiestDayCount = $count;
        $busiestDayName  = $d['day_name'];
    }
}


// ============================================================
// LOW STOCK ALERT
// ============================================================

$lowStockStmt = $pdo->query("
    SELECT
        name,
        quantity,
        threshold
    FROM inventory
    WHERE is_reusable = 0
      AND quantity <= threshold
    ORDER BY quantity ASC
");

$lowStockItems = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);


// ============================================================
// FLASH MESSAGE
// ============================================================

$flash = getFlash();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard - Studio 94</title>

    <style>

        :root {

            --primary: #1A1A1A;
            --primary-dark: #000000;
            --primary-light: #333333;
            --primary-bg: rgba(26, 26, 26, 0.05);

            --primary-gradient:
                linear-gradient(
                    135deg,
                    #1A1A1A,
                    #000000
                );

            --chart-purple:
                linear-gradient(
                    180deg,
                    #6C63FF 0%,
                    #4A42CC 100%
                );

            --chart-green:
                linear-gradient(
                    180deg,
                    #2ECC71 0%,
                    #27AE60 100%
                );

            --chart-blue:
                linear-gradient(
                    180deg,
                    #3B82F6 0%,
                    #1E40AF 100%
                );

            --chart-red:
                linear-gradient(
                    180deg,
                    #EF4444 0%,
                    #DC2626 100%
                );

            --success: #2ECC71;
            --danger: #FF6B6B;
            --warning: #F1C40F;
            --amber: #F59E0B;

            --bg: #F0F2F5;
            --card-bg: #FFFFFF;

            --text: #2D3436;
            --text-muted: #636E72;

            --border: #DFE6E9;

            --shadow:
                0 2px 10px rgba(0,0,0,0.08);

            --radius: 12px;
        }


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                sans-serif;

            background: var(--bg);
            color: var(--text);

            padding: 20px;
        }


        .container {

            max-width: 1400px;
            margin: 0 auto;
        }


        /* ========================================================
           HEADER
        ======================================================== */

        .page-header {

            background: var(--primary-gradient);

            color: white;

            padding: 30px 32px;

            border-radius: var(--radius);

            margin-bottom: 24px;

            box-shadow:
                0 4px 15px rgba(0,0,0,0.25);

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }


        .page-header h1 {

            font-size: 24px;
            font-weight: 700;
        }


        .page-header p {

            opacity: 0.9;

            font-size: 14px;

            margin-top: 4px;
        }


        /* ========================================================
           CARD
        ======================================================== */

        .card {

            background: var(--card-bg);

            border-radius: var(--radius);

            box-shadow: var(--shadow);

            padding: 20px 24px;

            margin-bottom: 20px;
        }


        .section-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            flex-wrap: wrap;

            gap: 12px;

            margin-bottom: 14px;
        }


        .section-header h3 {

            font-size: 16px;

            font-weight: 600;
        }


        /* ========================================================
           BUTTON
        ======================================================== */

        .dashboard-btn {

            background:
                rgba(255,255,255,0.2);

            backdrop-filter: blur(4px);

            color: white;

            padding: 8px 16px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 13px;

            font-weight: 500;

            border: 1px solid
                rgba(255,255,255,0.15);

            display: inline-block;
        }


        .dashboard-btn:hover {

            background:
                rgba(255,255,255,0.3);
        }


        /* ========================================================
           BADGES
        ======================================================== */

        .badge {

            padding: 2px 10px;

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


        /* ========================================================
           LINKS
        ======================================================== */

        .link-text {

            color: var(--primary);

            text-decoration: none;

            font-size: 13px;

            font-weight: 500;
        }


        .link-text:hover {

            text-decoration: underline;
        }


        /* ========================================================
           ALERT
        ======================================================== */

        .alert {

            padding: 12px 16px;

            border-radius: 8px;

            margin-bottom: 16px;

            font-size: 14px;

            display: flex;

            align-items: flex-start;

            gap: 10px;

            border-left:
                4px solid var(--danger);

            background: #fef2f2;

            color: #721c24;
        }


        .alert-icon {

            font-size: 18px;

            flex-shrink: 0;
        }


        /* ========================================================
           STATS
        ======================================================== */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(160px, 1fr));

            gap: 16px;

            margin-bottom: 20px;
        }


        .stat-card {

            background: var(--card-bg);

            border-radius: var(--radius);

            box-shadow: var(--shadow);

            padding: 16px 18px;

            text-align: center;

            transition: all 0.3s ease;

            border: 1px solid transparent;
        }


        .stat-card:hover {

            transform: translateY(-3px);

            box-shadow:
                0 6px 20px rgba(0,0,0,0.15);

            border-color:
                var(--primary-light);
        }


        .stat-card .stat-value {

            font-size: 28px;

            font-weight: 700;

            color: var(--primary);

            margin: 2px 0;
        }


        .stat-card .stat-value.green {

            color: var(--success);
        }


        .stat-card .stat-value.amber {

            color: var(--amber);
        }


        .stat-card .stat-value.red {

            color: var(--danger);
        }


        .stat-card .stat-label {

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            color: var(--text-muted);

            font-weight: 600;
        }


        .stat-card .stat-desc {

            font-size: 11px;

            color: var(--text-muted);
        }


        .stat-card.sales-bg {

            background:
                linear-gradient(
                    135deg,
                    #d4edda,
                    #c3e6cb
                );
        }


        .stat-card.sales-bg .stat-value {

            color: #155724;
        }


        .stat-card.revenue-bg {

            background:
                linear-gradient(
                    135deg,
                    #E8EBED,
                    #D8DBDD
                );
        }


        .stat-card.revenue-bg .stat-value {

            color: #1A1A1A;
        }


        .stat-card.warning-bg {

            background:
                linear-gradient(
                    135deg,
                    #fff3cd,
                    #ffeeba
                );
        }


        .stat-card.warning-bg .stat-value {

            color: #856404;
        }


        .stat-card.danger-bg {

            background:
                linear-gradient(
                    135deg,
                    #f8d7da,
                    #f5c6cb
                );
        }


        .stat-card.danger-bg .stat-value {

            color: #721c24;
        }


        /* ========================================================
           CHARTS
        ======================================================== */

        .chart-row {

            display: flex;

            align-items: flex-end;

            height: 160px;

            gap: 6px;

            padding: 10px 0;

            overflow-x: auto;
        }


        .chart-bar {

            flex: 1;

            min-width: 40px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: flex-end;

            height: 100%;

            position: relative;
        }


        .chart-bar .bar {

            width: 100%;

            min-height: 4px;

            border-radius:
                4px 4px 0 0;

            transition:
                height 0.6s ease;
        }


        .chart-bar .bar.primary {

            background: var(--chart-purple);

            box-shadow:
                0 2px 8px
                rgba(108,99,255,0.25);
        }


        .chart-bar .bar.green {

            background: var(--chart-green);

            box-shadow:
                0 2px 8px
                rgba(46,204,113,0.25);
        }


        .chart-bar .bar-val {

            font-size: 10px;

            font-weight: 700;

            color: var(--text);

            margin-bottom: 3px;

            white-space: nowrap;
        }


        .chart-bar .bar-label {

            font-size: 10px;

            color: var(--text-muted);

            margin-top: 4px;

            font-weight: 500;
        }


        .chart-bar .bar-year {

            font-size: 8px;

            color: var(--text-muted);

            opacity: 0.7;
        }


        .grid-2 {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;
        }


        /* ========================================================
           TABLE
        ======================================================== */

        .table-wrap {

            overflow-x: auto;

            max-height: 300px;

            overflow-y: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            font-size: 13px;
        }


        table th,
        table td {

            padding: 8px 10px;

            text-align: left;

            border-bottom:
                1px solid var(--border);
        }


        table th {

            font-weight: 600;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            color: var(--text-muted);

            background: var(--bg);

            position: sticky;

            top: 0;

            z-index: 10;
        }


        table tr:hover {

            background: var(--primary-bg);
        }


        /* ========================================================
           BUSIEST DAYS
        ======================================================== */

        .busy-day-bar {

            flex: 1;

            min-width: 36px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: flex-end;

            height: 100%;

            position: relative;
        }


        .busy-day-bar .bar {

            width: 75%;

            min-height: 4px;

            border-radius:
                4px 4px 0 0;

            transition:
                height 0.6s ease;
        }


        .busy-day-bar .bar-val {

            font-size: 13px;

            font-weight: 700;

            margin-bottom: 3px;

            white-space: nowrap;
        }


        .busy-day-bar .day-name {

            font-size: 10px;

            font-weight: 600;

            margin-top: 6px;

            text-transform: uppercase;

            letter-spacing: 0.3px;
        }


        .busy-day-bar .pct-text {

            font-size: 9px;

            margin-top: 2px;

            white-space: nowrap;
        }


        /* ========================================================
           EMPTY
        ======================================================== */

        .empty-state {

            text-align: center;

            padding: 40px 20px;

            color: var(--text-muted);

            font-size: 13px;
        }


        /* ========================================================
           RESPONSIVE
        ======================================================== */

        @media (max-width: 768px) {

            .grid-2 {

                grid-template-columns: 1fr;
            }

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .stat-card .stat-value {

                font-size: 22px;
            }

            .page-header {

                padding: 20px;
            }

            .page-header h1 {

                font-size: 20px;
            }

            .chart-row {

                height: 130px;
            }
        }


        @media (max-width: 480px) {

            body {

                padding: 10px;
            }

            .stats-grid {

                grid-template-columns:
                    1fr 1fr;

                gap: 10px;
            }

            .stat-card {

                padding: 12px;
            }

            .stat-card .stat-value {

                font-size: 18px;
            }

            .chart-bar {

                min-width: 32px;
            }

            .card {

                padding: 16px;
            }
        }

    </style>

</head>


<body>

<div class="container">


    <!-- ======================================================
         PAGE HEADER
    ======================================================= -->

    <div class="page-header">

        <div>

            <h1>
                📊 Admin Dashboard
            </h1>

            <p>
                Sales overview and studio analytics
            </p>

        </div>


        <div>

            <a
                href="index.php?page=reports"
                class="dashboard-btn"
            >
                📄 Generate Report
            </a>

        </div>

    </div>


    <!-- ======================================================
         FLASH MESSAGE
    ======================================================= -->

    <?php if ($flash): ?>

        <div
            class="alert"
            style="
                border-left-color:
                    <?= $flash['type'] === 'success'
                        ? 'var(--success)'
                        : 'var(--danger)' ?>;

                background:
                    <?= $flash['type'] === 'success'
                        ? '#ecfdf5'
                        : '#fef2f2' ?>;
            "
        >

            <span class="alert-icon">

                <?= $flash['type'] === 'success'
                    ? '✅'
                    : '❌' ?>

            </span>

            <span>
                <?= dashboardEsc($flash['msg']) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- ======================================================
         LOW STOCK ALERT
    ======================================================= -->

    <?php if (!empty($lowStockItems)): ?>

        <div class="alert">

            <span class="alert-icon">
                ⚠️
            </span>

            <div>

                <strong>
                    Low Stock Alert:
                </strong>

                <?= count($lowStockItems) ?>
                item(s) need restocking.

                <ul
                    style="
                        margin:5px 0 0 20px;
                        font-size:13px;
                    "
                >

                    <?php foreach ($lowStockItems as $item): ?>

                        <li>

                            <?= dashboardEsc($item['name']) ?>

                            —
                            only

                            <strong>
                                <?= (int)$item['quantity'] ?>
                            </strong>

                            left

                            (threshold:
                            <?= (int)$item['threshold'] ?>)

                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        </div>

    <?php endif; ?>


    <!-- ======================================================
         STATS CARDS
    ======================================================= -->

    <div class="stats-grid">


        <!-- TOTAL REVENUE -->

        <div class="stat-card revenue-bg">

            <div class="stat-value">

                <?= dashboardMoney($totalRevenue) ?>

            </div>

            <div class="stat-label">

                Total Revenue

            </div>

            <div class="stat-desc">

                Actual paid payments

            </div>

        </div>


        <!-- COMPLETED SALES -->

        <div class="stat-card sales-bg">

            <div class="stat-value">

                <?= dashboardMoney($totalSales) ?>

            </div>

            <div class="stat-label">

                Completed Sales

            </div>

            <div class="stat-desc">

                Completed booking value

            </div>

        </div>


        <!-- TODAY'S SALES -->

        <div class="stat-card warning-bg">

            <div class="stat-value">

                <?= dashboardMoney($salesToday) ?>

            </div>

            <div class="stat-label">

                Today's Completed Sales

            </div>

            <div class="stat-desc">

                Completed bookings today

            </div>

        </div>


        <!-- PENDING APPROVAL -->

        <div class="stat-card">

            <div class="stat-value amber">

                <?= number_format($pendingApprove) ?>

            </div>

            <div class="stat-label">

                Pending Approvals

            </div>

            <div class="stat-desc">

                Awaiting review

            </div>

        </div>


        <!-- PENDING PAYMENTS -->

        <div class="stat-card danger-bg">

            <div class="stat-value red">

                <?= number_format($pendingPayments) ?>

            </div>

            <div class="stat-label">

                Pending Payments

            </div>

            <div class="stat-desc">

                Awaiting payment

            </div>

        </div>


        <!-- WALK-IN / ONLINE -->

        <div class="stat-card">

            <div class="stat-value">

                <?= number_format($walkinCount) ?>

                /

                <?= number_format($onlineCount) ?>

            </div>

            <div class="stat-label">

                Walk-in / Online

            </div>

            <div class="stat-desc">

                🚶
                <?= dashboardMoney($walkinRevenue) ?>

                /

                📅
                <?= dashboardMoney($onlineRevenue) ?>

            </div>

        </div>


    </div>


    <!-- ======================================================
         MONTHLY CHARTS
    ======================================================= -->

    <div
        class="grid-2"
        style="margin-bottom:20px;"
    >


        <!-- MONTHLY SALES -->

        <div class="card">

            <div class="section-header">

                <h3>
                    📈 Monthly Sales Trend
                </h3>

                <span
                    style="
                        font-size:11px;
                        color:var(--text-muted);
                    "
                >

                    <?= count($monthlySales) ?>

                    month<?= count($monthlySales) !== 1 ? 's' : '' ?>

                </span>

            </div>


            <?php if (empty($monthlySales)): ?>

                <div class="empty-state">

                    No sales data yet.

                </div>

            <?php else: ?>

                <div class="chart-row">

                    <?php foreach ($monthlySales as $m): ?>

                        <?php

                        $h = $maxSales > 0
                            ? round(
                                ($m['value'] / $maxSales) * 100
                            )
                            : 0;

                        $visibleH =
                            $m['value'] > 0
                                ? max(8, $h)
                                : 2;

                        ?>

                        <div
                            class="chart-bar"
                            title="
                                <?= dashboardEsc($m['label']) ?>
                                <?= dashboardEsc($m['year']) ?>:
                                <?= dashboardMoney($m['value']) ?>
                            "
                        >

                            <span class="bar-val">

                                <?php if ($m['value'] > 0): ?>

                                    ₱<?= number_format(
                                        $m['value'] / 1000,
                                        1
                                    ) ?>k

                                <?php endif; ?>

                            </span>

                            <div
                                class="bar primary"
                                style="
                                    height:
                                    <?= $visibleH ?>%;
                                "
                            ></div>

                            <span class="bar-label">

                                <?= dashboardEsc($m['label']) ?>

                            </span>

                            <span class="bar-year">

                                <?= dashboardEsc($m['year']) ?>

                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>


        <!-- MONTHLY BOOKINGS -->

        <div class="card">

            <div class="section-header">

                <h3>
                    📊 Monthly Booking Trend
                </h3>

                <span
                    style="
                        font-size:11px;
                        color:var(--text-muted);
                    "
                >

                    <?= count($monthlyBookings) ?>

                    month<?= count($monthlyBookings) !== 1 ? 's' : '' ?>

                </span>

            </div>


            <?php if (empty($monthlyBookings)): ?>

                <div class="empty-state">

                    No booking data yet.

                </div>

            <?php else: ?>

                <div class="chart-row">

                    <?php foreach ($monthlyBookings as $m): ?>

                        <?php

                        $h = $maxBookings > 0
                            ? round(
                                ($m['value'] / $maxBookings) * 100
                            )
                            : 0;

                        $visibleH =
                            $m['value'] > 0
                                ? max(8, $h)
                                : 2;

                        ?>

                        <div
                            class="chart-bar"
                            title="
                                <?= dashboardEsc($m['label']) ?>:
                                <?= number_format($m['value']) ?>
                                booking<?= $m['value'] !== 1 ? 's' : '' ?>
                            "
                        >

                            <span class="bar-val">

                                <?=
                                    $m['value'] > 0
                                        ? number_format($m['value'])
                                        : ''
                                ?>

                            </span>

                            <div
                                class="bar green"
                                style="
                                    height:
                                    <?= $visibleH ?>%;
                                "
                            ></div>

                            <span class="bar-label">

                                <?= dashboardEsc($m['label']) ?>

                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ======================================================
         TOP PACKAGES + BUSIEST DAYS
    ======================================================= -->

    <div
        class="grid-2"
        style="margin-bottom:20px;"
    >


        <!-- TOP SELLING PACKAGES -->

        <div class="card">

            <div class="section-header">

                <h3>
                    🏆 Top Selling Packages
                </h3>

                <a
                    href="index.php?page=packages"
                    class="link-text"
                >
                    View all →
                </a>

            </div>


            <?php if (!empty($topPackages)): ?>

                <?php

                $maxRevenue =
                    max(
                        array_map(
                            static fn($item) =>
                                (float)$item['revenue'],
                            $topPackages
                        )
                    ) ?: 1;

                $colors = [

                    'linear-gradient(90deg, #6C63FF, #4A42CC)',

                    'linear-gradient(90deg, #3B82F6, #1E40AF)',

                    'linear-gradient(90deg, #10B981, #059669)',

                    'linear-gradient(90deg, #F59E0B, #D97706)',

                    'linear-gradient(90deg, #EF4444, #DC2626)'

                ];

                foreach (
                    $topPackages as $i => $pkg
                ):

                    $pct = $maxRevenue > 0
                        ? round(
                            (
                                (float)$pkg['revenue']
                                / $maxRevenue
                            ) * 100
                        )
                        : 0;

                ?>

                    <div
                        style="
                            margin-bottom:12px;
                        "
                    >

                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                font-size:13px;
                            "
                        >

                            <span>

                                <strong>
                                    <?= dashboardEsc($pkg['name']) ?>
                                </strong>

                            </span>

                            <span>

                                <strong>
                                    <?= dashboardMoney($pkg['revenue']) ?>
                                </strong>

                                <span
                                    style="
                                        color:var(--text-muted);
                                        font-size:11px;
                                    "
                                >

                                    (<?= number_format(
                                        (int)$pkg['bookings']
                                    ) ?> bookings)

                                </span>

                            </span>

                        </div>


                        <div
                            style="
                                height:6px;
                                background:var(--border);
                                border-radius:3px;
                                margin-top:3px;
                            "
                        >

                            <div
                                style="
                                    height:100%;
                                    width:<?= $pct ?>%;
                                    background:<?= $colors[
                                        $i % count($colors)
                                    ] ?>;
                                    border-radius:3px;
                                "
                            ></div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p
                    style="
                        color:var(--text-muted);
                        font-size:13px;
                        text-align:center;
                        padding:20px;
                    "
                >
                    No completed bookings yet.
                </p>

            <?php endif; ?>

        </div>


        <!-- BUSIEST DAYS -->

        <div class="card">

            <div class="section-header">

                <h3>
                    📆 Busiest Days of Week
                </h3>

                <span
                    style="
                        font-size:11px;
                        color:var(--text-muted);
                    "
                >

                    Total:
                    <?= number_format(
                        array_sum(
                            array_column(
                                $busiestDays,
                                'total_bookings'
                            )
                        )
                    ) ?>

                    bookings

                </span>

            </div>


            <?php if (!empty($busiestDays)): ?>

                <?php

                $maxDay =
                    max(
                        array_map(
                            static fn($item) =>
                                (int)$item['total_bookings'],
                            $busiestDays
                        )
                    ) ?: 1;

                ?>

                <div
                    style="
                        display:flex;
                        align-items:flex-end;
                        height:140px;
                        gap:6px;
                        padding:5px 0;
                    "
                >

                    <?php foreach ($busiestDays as $day): ?>

                        <?php

                        $totalDayBookings =
                            (int)$day['total_bookings'];

                        $completedBookings =
                            (int)$day['completed'];

                        $pct =
                            $maxDay > 0
                                ? round(
                                    (
                                        $totalDayBookings
                                        / $maxDay
                                    ) * 100
                                )
                                : 0;

                        $completionPct =
                            $totalDayBookings > 0
                                ? round(
                                    (
                                        $completedBookings
                                        / $totalDayBookings
                                    ) * 100
                                )
                                : 0;

                        $visibleH =
                            max(15, $pct);

                        $isBusiest =
                            $day['day_name']
                            === $busiestDayName;

                        ?>

                        <div
                            class="busy-day-bar"
                            title="
                                <?= dashboardEsc(
                                    $day['day_name']
                                ) ?>:
                                <?= $completedBookings ?>
                                completed out of
                                <?= $totalDayBookings ?>
                                bookings
                                (<?= $completionPct ?>%)
                            "
                        >

                            <span
                                class="bar-val"
                                style="
                                    color:
                                    <?= $isBusiest
                                        ? '#EF4444'
                                        : '#3B82F6' ?>;
                                "
                            >

                                <?= $totalDayBookings ?>

                            </span>


                            <div
                                class="bar"
                                style="
                                    height:
                                    <?= $visibleH ?>%;

                                    background:
                                    <?= $isBusiest
                                        ? 'linear-gradient(180deg, #EF4444 0%, #DC2626 100%)'
                                        : 'linear-gradient(180deg, #3B82F6 0%, #1E40AF 100%)' ?>;

                                    box-shadow:
                                    0 2px 8px
                                    <?= $isBusiest
                                        ? 'rgba(239,68,68,0.3)'
                                        : 'rgba(59,130,246,0.25)' ?>;
                                "
                            ></div>


                            <span
                                class="day-name"
                                style="
                                    color:
                                    <?= $isBusiest
                                        ? '#EF4444'
                                        : 'var(--text)' ?>;
                                "
                            >

                                <?= dashboardEsc(
                                    substr(
                                        $day['day_name'],
                                        0,
                                        3
                                    )
                                ) ?>

                            </span>


                            <span class="pct-text">

                                <span
                                    style="
                                        color:
                                        <?= $isBusiest
                                            ? '#EF4444'
                                            : 'var(--text)' ?>;

                                        font-weight:600;
                                    "
                                >

                                    <?= $completedBookings ?>
                                    /
                                    <?= $totalDayBookings ?>

                                </span>

                                <span
                                    style="
                                        color:var(--text-muted);
                                        margin-left:2px;
                                    "
                                >

                                    (<?= $completionPct ?>%)

                                </span>

                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>


                <div
                    style="
                        text-align:center;
                        font-size:12px;
                        color:var(--text-muted);
                        margin-top:12px;
                        padding-top:12px;
                        border-top:1px solid var(--border);
                    "
                >

                    🏆 Most bookings on:

                    <strong style="color:#EF4444;">

                        <?= dashboardEsc(
                            strtoupper($busiestDayName)
                        ) ?>

                    </strong>

                    <span
                        style="
                            font-size:11px;
                            color:var(--text-muted);
                        "
                    >

                        (
                        <?= number_format(
                            $busiestDayCount
                        ) ?>

                        booking<?= $busiestDayCount !== 1
                            ? 's'
                            : '' ?>

                        )

                    </span>

                </div>

            <?php else: ?>

                <p
                    style="
                        color:var(--text-muted);
                        font-size:13px;
                        text-align:center;
                        padding:20px;
                    "
                >
                    No data available.
                </p>

            <?php endif; ?>

        </div>

    </div>


    <!-- ======================================================
         SALES BREAKDOWN
    ======================================================= -->

    <div class="card">

        <div class="section-header">

            <div>

                <h3>
                    📋 Sales Breakdown by Package
                </h3>

                <div
                    style="
                        font-size:11px;
                        color:var(--text-muted);
                        margin-top:3px;
                    "
                >
                    Based on completed booking values.
                </div>

            </div>

            <a
                href="index.php?page=sales"
                class="link-text"
            >
                View detailed sales →
            </a>

        </div>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            Package
                        </th>

                        <th>
                            Bookings
                        </th>

                        <th>
                            Total Revenue
                        </th>

                        <th>
                            Average Price
                        </th>

                        <th>
                            % of Total
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php

                    $totalRevenueForPkg =
                        array_sum(
                            array_map(
                                static fn($item) =>
                                    (float)$item['revenue'],
                                $categorySales
                            )
                        );

                    if ($totalRevenueForPkg <= 0) {
                        $totalRevenueForPkg = 1;
                    }

                    ?>


                    <?php foreach ($categorySales as $pkg): ?>

                        <?php

                        $pkgRevenue =
                            (float)$pkg['revenue'];

                        $pct =
                            round(
                                (
                                    $pkgRevenue
                                    / $totalRevenueForPkg
                                ) * 100
                            );

                        ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= dashboardEsc(
                                        $pkg['name']
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= number_format(
                                    (int)$pkg['bookings']
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    <?= dashboardMoney(
                                        $pkgRevenue
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= dashboardMoney(
                                    $pkg['avg_price']
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="badge
                                    <?= $pct >= 30
                                        ? 'badge-green'
                                        : (
                                            $pct >= 15
                                                ? 'badge-amber'
                                                : 'badge-gray'
                                        )
                                    ?>"
                                >

                                    <?= $pct ?>%

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (empty($categorySales)): ?>

                        <tr>

                            <td
                                colspan="5"
                                style="
                                    text-align:center;
                                    padding:30px;
                                    color:var(--text-muted);
                                "
                            >

                                No sales data available.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


</div>

</body>

</html>