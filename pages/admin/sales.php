<?php

// ============================================================
// STUDIO 94 SNAPTRACK — SALES MANAGEMENT
// Admin Only
// ============================================================

requireRole('admin');

$pdo = db();

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function salesEsc($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function salesMoney($value): string
{
    return '₱' . number_format((float)$value, 2);
}

function salesStatusClass($status): string
{
    $status = strtoupper(trim((string)$status));

    if (in_array($status, ['PAID', 'VERIFIED', 'COMPLETED'], true)) {
        return 'sales-status sales-status-success';
    }

    if (in_array($status, ['PENDING', 'UNPAID', 'AWAITING_PAYMENT'], true)) {
        return 'sales-status sales-status-warning';
    }

    if (in_array($status, ['REJECTED', 'FAILED', 'CANCELLED'], true)) {
        return 'sales-status sales-status-danger';
    }

    return 'sales-status sales-status-neutral';
}

function salesStatusLabel($status): string
{
    $status = strtoupper(trim((string)$status));

    $labels = [
        'PAID'             => '✅ PAID',
        'VERIFIED'         => '✅ VERIFIED',
        'COMPLETED'        => '✅ COMPLETED',
        'PENDING'          => '⏳ PENDING',
        'UNPAID'           => '⚠️ UNPAID',
        'AWAITING_PAYMENT' => '💳 AWAITING PAYMENT',
        'REJECTED'         => '❌ REJECTED',
        'FAILED'           => '❌ FAILED',
        'CANCELLED'        => '❌ CANCELLED'
    ];

    return salesEsc($labels[$status] ?? ($status ?: 'UNKNOWN'));
}

// ============================================================
// FILTER INPUTS
// ============================================================

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');

$statusFilter = strtoupper(trim($_GET['status'] ?? 'PAID'));
$methodFilter = trim($_GET['method'] ?? '');
$search = trim($_GET['search'] ?? '');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-t');
}

// Make sure FROM is not later than TO
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

// ============================================================
// BUILD SALES FILTER
// ============================================================

$where = [
    "DATE(py.created_at) BETWEEN ? AND ?"
];

$params = [
    $from,
    $to
];

if ($statusFilter !== 'ALL') {
    $where[] = 'UPPER(py.status) = ?';
    $params[] = $statusFilter;
}

if ($methodFilter !== '') {
    $where[] = "COALESCE(py.method, '') = ?";
    $params[] = $methodFilter;
}

if ($search !== '') {

    $where[] = '(
        py.payment_ref LIKE ?
        OR b.booking_ref LIKE ?
        OR u.name LIKE ?
        OR p.name LIKE ?
    )';

    $term = '%' . $search . '%';

    array_push(
        $params,
        $term,
        $term,
        $term,
        $term
    );
}

$whereSql = implode(' AND ', $where);

// ============================================================
// SALES TRANSACTIONS
// ============================================================

$sql = "
    SELECT
        py.id,
        py.payment_ref,
        py.booking_id,
        py.user_id,
        py.amount,
        py.type,
        py.method,
        py.ref_number,
        py.status,
        py.verified_by,
        py.verified_at,
        py.created_at,

        b.booking_ref,
        b.date AS booking_date,
        b.time AS booking_time,
        b.package_price,
        b.deposit_amount,
        b.remaining_balance,
        b.status AS booking_status,

        u.name AS client_name,
        u.email AS client_email,

        p.name AS package_name,
        p.duration AS package_duration

    FROM payments py

    INNER JOIN bookings b
        ON b.id = py.booking_id

    INNER JOIN users u
        ON u.id = py.user_id

    INNER JOIN packages p
        ON p.id = b.package_id

    WHERE {$whereSql}

    ORDER BY
        py.created_at DESC,
        py.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// SALES METRICS
//
// Metrics follow the SAME selected date/status/method/search
// filters so that the summary cards match the table.
// ============================================================

$metricSql = "
    SELECT
        COUNT(*) AS transaction_count,

        COALESCE(
            SUM(
                CASE
                    WHEN UPPER(py.status) = 'PAID'
                    THEN py.amount
                    ELSE 0
                END
            ),
            0
        ) AS total_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN UPPER(py.status) = 'PAID'
                    AND UPPER(py.type) = 'DEPOSIT'
                    THEN py.amount
                    ELSE 0
                END
            ),
            0
        ) AS deposit_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN UPPER(py.status) = 'PAID'
                    AND UPPER(py.type) = 'FULL'
                    THEN py.amount
                    ELSE 0
                END
            ),
            0
        ) AS full_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN UPPER(py.status) = 'UNPAID'
                    THEN py.amount
                    ELSE 0
                END
            ),
            0
        ) AS unpaid_amount

    FROM payments py

    INNER JOIN bookings b
        ON b.id = py.booking_id

    INNER JOIN users u
        ON u.id = py.user_id

    INNER JOIN packages p
        ON p.id = b.package_id

    WHERE {$whereSql}
";

$metricStmt = $pdo->prepare($metricSql);
$metricStmt->execute($params);

$metrics = $metricStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalTransactions = (int)($metrics['transaction_count'] ?? 0);
$totalSales = (float)($metrics['total_sales'] ?? 0);
$depositSales = (float)($metrics['deposit_sales'] ?? 0);
$fullSales = (float)($metrics['full_sales'] ?? 0);
$unpaidAmount = (float)($metrics['unpaid_amount'] ?? 0);

// ============================================================
// SALES CHART DATA
//
// Charts are based ONLY on PAID payments and selected date
// range, regardless of table status/search filters.
// ============================================================

// ------------------------------------------------------------
// Daily Revenue
// ------------------------------------------------------------

$dailyStmt = $pdo->prepare("
    SELECT
        DATE(py.created_at) AS sale_date,
        COALESCE(SUM(py.amount), 0) AS total

    FROM payments py

    WHERE UPPER(py.status) = 'PAID'
      AND DATE(py.created_at) BETWEEN ? AND ?

    GROUP BY DATE(py.created_at)

    ORDER BY DATE(py.created_at) ASC
");

$dailyStmt->execute([
    $from,
    $to
]);

$dailySales = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------
// Sales by Package
// ------------------------------------------------------------

$packageChartStmt = $pdo->prepare("
    SELECT
        COALESCE(p.name, 'Unknown Package') AS package_name,
        COALESCE(SUM(py.amount), 0) AS total

    FROM payments py

    INNER JOIN bookings b
        ON b.id = py.booking_id

    LEFT JOIN packages p
        ON p.id = b.package_id

    WHERE UPPER(py.status) = 'PAID'
      AND DATE(py.created_at) BETWEEN ? AND ?

    GROUP BY
        p.id,
        p.name

    ORDER BY
        total DESC,
        package_name ASC
");

$packageChartStmt->execute([
    $from,
    $to
]);

$packageSales = $packageChartStmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------
// Payment Method Revenue
// ------------------------------------------------------------

$methodChartStmt = $pdo->prepare("
    SELECT
        COALESCE(
            NULLIF(TRIM(py.method), ''),
            'Unknown'
        ) AS method_name,

        COALESCE(SUM(py.amount), 0) AS total

    FROM payments py

    WHERE UPPER(py.status) = 'PAID'
      AND DATE(py.created_at) BETWEEN ? AND ?

    GROUP BY
        COALESCE(
            NULLIF(TRIM(py.method), ''),
            'Unknown'
        )

    ORDER BY
        total DESC,
        method_name ASC
");

$methodChartStmt->execute([
    $from,
    $to
]);

$methodSales = $methodChartStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// PREPARE CHART ARRAYS
// ============================================================

$chartDailyLabels = array_map(
    static function ($row) {
        return date(
            'M j',
            strtotime($row['sale_date'])
        );
    },
    $dailySales
);

$chartDailyValues = array_map(
    static function ($row) {
        return (float)$row['total'];
    },
    $dailySales
);

$chartPackageLabels = array_map(
    static function ($row) {
        return $row['package_name'];
    },
    $packageSales
);

$chartPackageValues = array_map(
    static function ($row) {
        return (float)$row['total'];
    },
    $packageSales
);

$chartMethodLabels = array_map(
    static function ($row) {
        return $row['method_name'];
    },
    $methodSales
);

$chartMethodValues = array_map(
    static function ($row) {
        return (float)$row['total'];
    },
    $methodSales
);

// ============================================================
// PAYMENT METHODS FOR FILTER
// ============================================================

$methodStmt = $pdo->query("
    SELECT DISTINCT method
    FROM payments
    WHERE method IS NOT NULL
      AND TRIM(method) <> ''
    ORDER BY method ASC
");

$methods = $methodStmt->fetchAll(PDO::FETCH_COLUMN);

// ============================================================
// CSV EXPORT
// ============================================================

if (($_GET['export'] ?? '') === 'csv') {

    // Prevent accidental output before CSV headers
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');

    header(
        'Content-Disposition: attachment; filename="studio94_sales_' .
        date('Y-m-d') .
        '.csv"'
    );

    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM for Excel
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Payment Ref',
        'Booking Ref',
        'Client',
        'Package',
        'Type',
        'Amount',
        'Method',
        'Reference Number',
        'Payment Status',
        'Booking Date',
        'Booking Time',
        'Payment Date'
    ]);

    foreach ($sales as $row) {

        fputcsv($out, [
            $row['payment_ref'],
            $row['booking_ref'],
            $row['client_name'],
            $row['package_name'],
            $row['type'],

            number_format(
                (float)$row['amount'],
                2,
                '.',
                ''
            ),

            $row['method'] ?? '',
            $row['ref_number'] ?? '',
            $row['status'],
            $row['booking_date'],
            $row['booking_time'],
            $row['created_at']
        ]);
    }

    fclose($out);

    exit;
}

?>

<!-- =========================================================
     PAGE BANNER
========================================================= -->

<div class="page-banner">

    <div class="page-banner-text">

        <div class="eyebrow">
            Financial Records
        </div>

        <h2>
            Sales Management
        </h2>

        <p>
            Monitor payment transactions and sales revenue from Studio 94 bookings.
        </p>

    </div>

    <div class="page-banner-art">
        💰
    </div>

</div>

<!-- =========================================================
     SALES SUMMARY
========================================================= -->

<div class="sales-summary-grid">

    <div class="sales-card">

        <div class="sales-card-icon">
            💰
        </div>

        <div>

            <div class="sales-card-label">
                Total Sales
            </div>

            <div class="sales-card-value">
                <?= salesMoney($totalSales) ?>
            </div>

            <div class="sales-card-note">
                PAID transactions
            </div>

        </div>

    </div>


    <div class="sales-card">

        <div class="sales-card-icon">
            🧾
        </div>

        <div>

            <div class="sales-card-label">
                Transactions
            </div>

            <div class="sales-card-value">
                <?= number_format($totalTransactions) ?>
            </div>

            <div class="sales-card-note">
                Payment records
            </div>

        </div>

    </div>


    <div class="sales-card">

        <div class="sales-card-icon">
            💵
        </div>

        <div>

            <div class="sales-card-label">
                Deposit Sales
            </div>

            <div class="sales-card-value">
                <?= salesMoney($depositSales) ?>
            </div>

            <div class="sales-card-note">
                Paid deposits
            </div>

        </div>

    </div>


    <div class="sales-card">

        <div class="sales-card-icon">
            ✅
        </div>

        <div>

            <div class="sales-card-label">
                Full Sales
            </div>

            <div class="sales-card-value">
                <?= salesMoney($fullSales) ?>
            </div>

            <div class="sales-card-note">
                Paid full transactions
            </div>

        </div>

    </div>

</div>

<!-- =========================================================
     SALES ANALYTICS
========================================================= -->

<div class="sales-analytics-head">

    <div>

        <div class="sales-analytics-kicker">
            Sales Analytics
        </div>

        <h3>
            Revenue Overview
        </h3>

        <p>
            <?= salesEsc(date('M j, Y', strtotime($from))) ?>
            —
            <?= salesEsc(date('M j, Y', strtotime($to))) ?>
            · PAID transactions only
        </p>

    </div>

    <div class="sales-analytics-meta">
        Live from payment records
    </div>

</div>

<div class="sales-chart-grid">

    <!-- DAILY REVENUE -->

    <section class="sales-chart-panel sales-chart-wide">

        <div class="sales-chart-title-row">

            <div>

                <h4>
                    Daily Revenue
                </h4>

                <span>
                    Paid sales by payment date
                </span>

            </div>

            <div class="sales-chart-total">
                <?= salesMoney($totalSales) ?>
            </div>

        </div>

        <div class="sales-chart-wrap sales-chart-line-wrap">

            <canvas
                id="salesDailyChart"
                aria-label="Daily sales chart">
            </canvas>

            <?php if (empty($dailySales)): ?>

                <div class="sales-chart-empty">
                    No paid sales for the selected period.
                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- SALES BY PACKAGE -->

    <section class="sales-chart-panel">

        <div class="sales-chart-title-row">

            <div>

                <h4>
                    Sales by Package
                </h4>

                <span>
                    Revenue grouped by package
                </span>

            </div>

        </div>

        <div class="sales-chart-wrap sales-chart-bar-wrap">

            <canvas
                id="salesPackageChart"
                aria-label="Sales by package chart">
            </canvas>

            <?php if (empty($packageSales)): ?>

                <div class="sales-chart-empty">
                    No paid package sales for the selected period.
                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- PAYMENT METHOD -->

    <section class="sales-chart-panel sales-chart-method-panel">

        <div class="sales-chart-title-row">

            <div>

                <h4>
                    Payment Method Revenue
                </h4>

                <span>
                    Paid revenue grouped by method
                </span>

            </div>

        </div>

        <div class="sales-method-content">

            <div class="sales-chart-method-canvas">

                <canvas
                    id="salesMethodChart"
                    aria-label="Sales by payment method chart">
                </canvas>

                <?php if (empty($methodSales)): ?>

                    <div class="sales-chart-empty">
                        No paid payment methods for the selected period.
                    </div>

                <?php endif; ?>

            </div>

            <div
                id="salesMethodLegend"
                class="sales-method-legend">
            </div>

        </div>

    </section>

</div>

<!-- =========================================================
     CHART.JS
========================================================= -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function () {

    const dailyLabels =
        <?= json_encode(
            $chartDailyLabels,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;

    const dailyValues =
        <?= json_encode($chartDailyValues) ?>;


    const packageLabels =
        <?= json_encode(
            $chartPackageLabels,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;

    const packageValues =
        <?= json_encode($chartPackageValues) ?>;


    const methodLabels =
        <?= json_encode(
            $chartMethodLabels,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;

    const methodValues =
        <?= json_encode($chartMethodValues) ?>;


    function formatPeso(value) {

        return '₱' +
            Number(value || 0).toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

    }


    function hideEmptyCanvas(canvasId) {

        const canvas =
            document.getElementById(canvasId);

        if (!canvas) {
            return;
        }

        const parent =
            canvas.parentElement;

        const empty =
            parent
                ? parent.querySelector('.sales-chart-empty')
                : null;

        if (empty && canvas.getContext) {
            empty.style.display = 'flex';
        }

    }


    // Chart.js unavailable
    if (!window.Chart) {

        hideEmptyCanvas('salesDailyChart');
        hideEmptyCanvas('salesPackageChart');
        hideEmptyCanvas('salesMethodChart');

        return;
    }


    const commonFont = {

        family: 'inherit',
        size: 11

    };


    // ========================================================
    // DAILY REVENUE CHART
    // ========================================================

    const dailyCanvas =
        document.getElementById('salesDailyChart');

    if (
        dailyCanvas &&
        dailyValues.length
    ) {

        new Chart(
            dailyCanvas,
            {

                type: 'line',

                data: {

                    labels: dailyLabels,

                    datasets: [

                        {

                            label: 'Revenue',

                            data: dailyValues,

                            borderWidth: 2,

                            tension: 0.28,

                            pointRadius: 3,

                            pointHoverRadius: 5,

                            fill: true

                        }

                    ]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {
                            display: false
                        },

                        tooltip: {

                            callbacks: {

                                label: function (ctx) {

                                    return ' ' +
                                        formatPeso(
                                            ctx.parsed.y
                                        );

                                }

                            }

                        }

                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            ticks: {

                                font: commonFont,

                                callback: function (value) {

                                    return formatPeso(value);

                                }

                            },

                            grid: {
                                drawBorder: false
                            }

                        },

                        x: {

                            ticks: {

                                font: commonFont,

                                maxRotation: 0,

                                autoSkip: true

                            },

                            grid: {
                                display: false
                            }

                        }

                    }

                }

            }
        );

    }


    // ========================================================
    // SALES BY PACKAGE CHART
    // ========================================================

    const packageCanvas =
        document.getElementById('salesPackageChart');

    if (
        packageCanvas &&
        packageValues.length
    ) {

        new Chart(
            packageCanvas,
            {

                type: 'bar',

                data: {

                    labels: packageLabels,

                    datasets: [

                        {

                            label: 'Revenue',

                            data: packageValues,

                            borderWidth: 0,

                            borderRadius: 5,

                            maxBarThickness: 34

                        }

                    ]

                },

                options: {

                    indexAxis: 'y',

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {
                            display: false
                        },

                        tooltip: {

                            callbacks: {

                                label: function (ctx) {

                                    return ' ' +
                                        formatPeso(
                                            ctx.parsed.x
                                        );

                                }

                            }

                        }

                    },

                    scales: {

                        x: {

                            beginAtZero: true,

                            ticks: {

                                font: commonFont,

                                callback: function (value) {

                                    return formatPeso(value);

                                }

                            },

                            grid: {
                                drawBorder: false
                            }

                        },

                        y: {

                            ticks: {
                                font: commonFont
                            },

                            grid: {
                                display: false
                            }

                        }

                    }

                }

            }
        );

    }


    // ========================================================
    // PAYMENT METHOD DOUGHNUT CHART
    // ========================================================

    const methodCanvas =
        document.getElementById('salesMethodChart');

    if (
        methodCanvas &&
        methodValues.length
    ) {

        new Chart(
            methodCanvas,
            {

                type: 'doughnut',

                data: {

                    labels: methodLabels,

                    datasets: [

                        {

                            data: methodValues,

                            borderWidth: 2,

                            hoverOffset: 5

                        }

                    ]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    cutout: '64%',

                    plugins: {

                        legend: {
                            display: false
                        },

                        tooltip: {

                            callbacks: {

                                label: function (ctx) {

                                    return ' ' +
                                        ctx.label +
                                        ': ' +
                                        formatPeso(
                                            ctx.parsed
                                        );

                                }

                            }

                        }

                    }

                }

            }
        );


        // ====================================================
        // PAYMENT METHOD LEGEND
        // ====================================================

        const legend =
            document.getElementById(
                'salesMethodLegend'
            );

        if (legend) {

            methodLabels.forEach(
                function (label, index) {

                    const row =
                        document.createElement('div');

                    row.className =
                        'sales-method-row';


                    const name =
                        document.createElement('span');

                    name.className =
                        'sales-method-name';

                    name.textContent =
                        String(label);


                    const amount =
                        document.createElement('strong');

                    amount.textContent =
                        formatPeso(
                            methodValues[index]
                        );


                    row.appendChild(name);
                    row.appendChild(amount);

                    legend.appendChild(row);

                }
            );

        }

    }

})();
</script>

<!-- =========================================================
     SALES FILTERS
========================================================= -->

<div class="card sales-filter-card">

    <div class="section-header">

        <div>

            <h3>
                🔎 Sales Filters
            </h3>

            <div
                style="
                    font-size:11px;
                    color:var(--muted);
                    margin-top:3px;
                "
            >
                Revenue is counted from PAID payment records only.
            </div>

        </div>


        <div
            style="
                display:flex;
                gap:8px;
                flex-wrap:wrap;
            "
        >

            <a
                href="index.php?page=reports&type=sales&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&generate=1"
                class="btn-ghost btn-sm"
            >
                📊 Sales Report
            </a>

        </div>

    </div>


    <form
        method="GET"
        action="index.php"
    >

        <input
            type="hidden"
            name="page"
            value="sales"
        >


        <div class="sales-filter-grid">

            <!-- FROM -->

            <div class="form-group">

                <label>
                    From
                </label>

                <input
                    type="date"
                    name="from"
                    value="<?= salesEsc($from) ?>"
                >

            </div>


            <!-- TO -->

            <div class="form-group">

                <label>
                    To
                </label>

                <input
                    type="date"
                    name="to"
                    value="<?= salesEsc($to) ?>"
                >

            </div>


            <!-- STATUS -->

            <div class="form-group">

                <label>
                    Status
                </label>

                <select name="status">

                    <option
                        value="PAID"
                        <?= $statusFilter === 'PAID' ? 'selected' : '' ?>
                    >
                        ✅ PAID
                    </option>

                    <option
                        value="ALL"
                        <?= $statusFilter === 'ALL' ? 'selected' : '' ?>
                    >
                        All
                    </option>

                    <option
                        value="UNPAID"
                        <?= $statusFilter === 'UNPAID' ? 'selected' : '' ?>
                    >
                        ⚠️ UNPAID
                    </option>

                    <option
                        value="PENDING"
                        <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>
                    >
                        ⏳ PENDING
                    </option>

                    <option
                        value="REJECTED"
                        <?= $statusFilter === 'REJECTED' ? 'selected' : '' ?>
                    >
                        ❌ REJECTED
                    </option>

                </select>

            </div>


            <!-- PAYMENT METHOD -->

            <div class="form-group">

                <label>
                    Payment Method
                </label>

                <select name="method">

                    <option value="">
                        All Methods
                    </option>

                    <?php foreach ($methods as $method): ?>

                        <option
                            value="<?= salesEsc($method) ?>"
                            <?= $methodFilter === $method ? 'selected' : '' ?>
                        >
                            <?= salesEsc($method) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- SEARCH -->

            <div class="form-group sales-search-field">

                <label>
                    Search
                </label>

                <input
                    type="text"
                    name="search"
                    value="<?= salesEsc($search) ?>"
                    placeholder="Payment ref, booking ref, client, package..."
                >

            </div>


            <!-- APPLY -->

            <div class="sales-filter-action">

                <button
                    type="submit"
                    class="btn-primary"
                    style="width:100%;"
                >
                    🔎 Apply Filters
                </button>

            </div>

        </div>

    </form>

</div>

<!-- =========================================================
     SALES TRANSACTIONS
========================================================= -->

<div class="card">

    <div class="section-header">

        <div>

            <h3>
                💰 Sales Transactions
            </h3>

            <div
                style="
                    font-size:11px;
                    color:var(--muted);
                    margin-top:3px;
                "
            >
                <?= number_format(count($sales)) ?>
                record(s) found
            </div>

        </div>


        <div
            style="
                font-size:12px;
                color:var(--muted);
            "
        >

            <?= salesEsc(date('M j, Y', strtotime($from))) ?>

            —

            <?= salesEsc(date('M j, Y', strtotime($to))) ?>

        </div>

    </div>


    <?php if (empty($sales)): ?>

        <div class="sales-empty">

            <div style="font-size:44px;">
                📭
            </div>

            <div
                style="
                    font-size:16px;
                    font-weight:600;
                    margin-top:8px;
                "
            >
                No sales records found
            </div>

            <div
                style="
                    font-size:12px;
                    color:var(--muted);
                    margin-top:4px;
                "
            >
                Try another date range or filter.
            </div>

        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            Payment Ref
                        </th>

                        <th>
                            Booking
                        </th>

                        <th>
                            Client
                        </th>

                        <th>
                            Package
                        </th>

                        <th>
                            Type
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Method
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Payment Date
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($sales as $row): ?>

                        <?php

                        $status =
                            strtoupper(
                                (string)$row['status']
                            );

                        $isPaid =
                            in_array(
                                $status,
                                [
                                    'PAID',
                                    'VERIFIED',
                                    'COMPLETED'
                                ],
                                true
                            );

                        ?>

                        <tr>

                            <!-- PAYMENT REF -->

                            <td>

                                <code class="sales-ref">
                                    <?= salesEsc($row['payment_ref']) ?>
                                </code>

                            </td>


                            <!-- BOOKING -->

                            <td>

                                <strong>
                                    <?= salesEsc($row['booking_ref']) ?>
                                </strong>

                                <div
                                    style="
                                        font-size:10px;
                                        color:var(--muted);
                                    "
                                >
                                    <?= salesEsc($row['booking_date']) ?>
                                    ·
                                    <?= salesEsc($row['booking_time']) ?>
                                </div>

                            </td>


                            <!-- CLIENT -->

                            <td>

                                <strong>
                                    <?= salesEsc($row['client_name']) ?>
                                </strong>

                                <div
                                    style="
                                        font-size:10px;
                                        color:var(--muted);
                                    "
                                >
                                    <?= salesEsc($row['client_email']) ?>
                                </div>

                            </td>


                            <!-- PACKAGE -->

                            <td>

                                <strong>
                                    <?= salesEsc($row['package_name']) ?>
                                </strong>

                                <?php if (!empty($row['package_duration'])): ?>

                                    <div
                                        style="
                                            font-size:10px;
                                            color:var(--muted);
                                        "
                                    >
                                        <?= salesEsc($row['package_duration']) ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- TYPE -->

                            <td>

                                <span class="sales-type">
                                    <?= salesEsc($row['type'] ?: '—') ?>
                                </span>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <strong
                                    class="<?= $isPaid
                                        ? 'sales-paid-amount'
                                        : 'sales-unpaid-amount' ?>"
                                >
                                    <?= salesMoney($row['amount']) ?>
                                </strong>

                            </td>


                            <!-- METHOD -->

                            <td>

                                <?= salesEsc(
                                    $row['method'] ?: '—'
                                ) ?>

                                <?php if (!empty($row['ref_number'])): ?>

                                    <div
                                        style="
                                            font-size:9px;
                                            color:var(--muted);
                                        "
                                    >
                                        Ref:
                                        <?= salesEsc($row['ref_number']) ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="<?= salesStatusClass(
                                        $row['status']
                                    ) ?>"
                                >
                                    <?= salesStatusLabel(
                                        $row['status']
                                    ) ?>
                                </span>

                                <?php if (!empty($row['verified_at'])): ?>

                                    <div
                                        style="
                                            font-size:9px;
                                            color:var(--muted);
                                            margin-top:3px;
                                        "
                                    >
                                        Verified:
                                        <?= salesEsc(
                                            date(
                                                'M j, Y h:i A',
                                                strtotime(
                                                    $row['verified_at']
                                                )
                                            )
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- PAYMENT DATE -->

                            <td>

                                <?= salesEsc(
                                    date(
                                        'M j, Y',
                                        strtotime(
                                            $row['created_at']
                                        )
                                    )
                                ) ?>

                                <div
                                    style="
                                        font-size:9px;
                                        color:var(--muted);
                                    "
                                >
                                    <?= salesEsc(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $row['created_at']
                                            )
                                        )
                                    ) ?>
                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- TOTAL BAR -->

        <div class="sales-total-bar">

            <div>

                <span
                    style="
                        color:var(--muted);
                    "
                >
                    PAID revenue for selected period:
                </span>

                <strong>
                    <?= salesMoney($totalSales) ?>
                </strong>

            </div>


            <?php if ($unpaidAmount > 0): ?>

                <div
                    style="
                        color:var(--amber-text);
                    "
                >
                    Unpaid records:
                    <?= salesMoney($unpaidAmount) ?>
                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

<!-- =========================================================
     SALES STYLES
========================================================= -->

<style>

.sales-summary-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:20px
}

.sales-card{
    display:flex;
    align-items:center;
    gap:14px;
    padding:18px;
    background:white;
    border:1px solid var(--border);
    border-radius:10px
}

.sales-card-icon{
    width:44px;
    height:44px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:var(--bg-soft);
    font-size:22px
}

.sales-card-label{
    font-size:11px;
    color:var(--muted)
}

.sales-card-value{
    font-size:22px;
    font-weight:700;
    margin-top:2px;
    color:var(--dark)
}

.sales-card-note{
    font-size:9px;
    color:var(--muted);
    margin-top:2px
}

.sales-filter-card{
    margin-bottom:20px
}

.sales-filter-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    align-items:end
}

.sales-search-field{
    grid-column:span 2
}

.sales-filter-action{
    grid-column:span 2
}

.sales-status{
    display:inline-block;
    padding:4px 8px;
    border-radius:20px;
    border:1px solid transparent;
    font-size:10px;
    font-weight:600;
    white-space:nowrap
}

.sales-status-success{
    background:var(--green-bg);
    color:var(--green-text);
    border-color:var(--green)
}

.sales-status-warning{
    background:var(--amber-bg);
    color:var(--amber-text);
    border-color:var(--amber)
}

.sales-status-danger{
    background:var(--red-bg);
    color:var(--red-text);
    border-color:var(--red)
}

.sales-status-neutral{
    background:var(--bg-soft);
    color:var(--muted);
    border-color:var(--border)
}

.sales-ref{
    font-size:10px;
    background:var(--bg-soft);
    padding:3px 6px;
    border-radius:4px
}

.sales-type{
    display:inline-block;
    font-size:10px;
    font-weight:600;
    padding:3px 6px;
    border-radius:4px;
    background:var(--bg-soft);
    color:var(--dark2)
}

.sales-paid-amount{
    color:var(--green-text)
}

.sales-unpaid-amount{
    color:var(--amber-text)
}

.sales-empty{
    text-align:center;
    padding:60px 20px;
    color:var(--muted)
}

.sales-total-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-top:16px;
    padding:14px 16px;
    background:var(--bg-soft);
    border:1px solid var(--border);
    border-radius:8px;
    font-size:12px
}

.sales-total-bar strong{
    font-size:18px;
    margin-left:8px;
    color:var(--green-text)
}


/* ============================================================
   SALES ANALYTICS
============================================================ */

.sales-analytics-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:18px;
    margin:4px 0 14px;
    padding:4px 2px 0
}

.sales-analytics-kicker{
    text-transform:uppercase;
    letter-spacing:.12em;
    font-size:9px;
    font-weight:700;
    color:var(--muted);
    margin-bottom:4px
}

.sales-analytics-head h3{
    margin:0;
    font-size:21px;
    color:var(--dark)
}

.sales-analytics-head p{
    margin:4px 0 0;
    font-size:11px;
    color:var(--muted)
}

.sales-analytics-meta{
    font-size:10px;
    color:var(--muted);
    padding-bottom:3px
}

.sales-chart-grid{
    display:grid;
    grid-template-columns:minmax(0,2fr) minmax(320px,1fr);
    gap:14px;
    margin-bottom:20px
}

.sales-chart-panel{
    background:var(--card,#fff);
    border:1px solid var(--border);
    border-radius:10px;
    padding:16px;
    min-width:0
}

.sales-chart-wide{
    min-height:330px
}

.sales-chart-title-row{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:12px
}

.sales-chart-title-row h4{
    margin:0;
    font-size:13px;
    color:var(--dark)
}

.sales-chart-title-row span{
    display:block;
    font-size:10px;
    color:var(--muted);
    margin-top:3px
}

.sales-chart-total{
    font-size:16px;
    font-weight:700;
    color:var(--green-text);
    white-space:nowrap
}

.sales-chart-wrap{
    position:relative;
    width:100%;
    height:260px
}

.sales-chart-line-wrap{
    height:270px
}

.sales-chart-bar-wrap{
    height:270px
}

.sales-chart-method-panel{
    grid-column:1/-1
}

.sales-method-content{
    display:grid;
    grid-template-columns:260px minmax(0,1fr);
    gap:24px;
    align-items:center
}

.sales-chart-method-canvas{
    position:relative;
    height:250px
}

.sales-method-legend{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:8px 18px
}

.sales-method-row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    padding:8px 0;
    border-bottom:1px dashed var(--border);
    font-size:11px
}

.sales-method-name{
    color:var(--muted);
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap
}

.sales-method-row strong{
    color:var(--dark);
    white-space:nowrap
}

.sales-chart-empty{
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    color:var(--muted);
    font-size:11px;
    background:rgba(255,255,255,.78);
    z-index:2
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media(max-width:1100px){

    .sales-summary-grid{
        grid-template-columns:repeat(
            2,
            minmax(0,1fr)
        )
    }

    .sales-filter-grid{
        grid-template-columns:repeat(
            2,
            minmax(0,1fr)
        )
    }

    .sales-search-field,
    .sales-filter-action{
        grid-column:span 1
    }

}


@media(max-width:980px){

    .sales-chart-grid{
        grid-template-columns:1fr
    }

    .sales-chart-method-panel{
        grid-column:auto
    }

    .sales-method-content{
        grid-template-columns:
            220px
            minmax(0,1fr)
    }

}


@media(max-width:640px){

    .sales-summary-grid,
    .sales-filter-grid{
        grid-template-columns:1fr
    }

    .sales-search-field,
    .sales-filter-action{
        grid-column:span 1
    }

    .sales-total-bar{
        flex-direction:column;
        align-items:flex-start
    }

    .sales-analytics-head{
        align-items:flex-start;
        flex-direction:column
    }

    .sales-method-content{
        grid-template-columns:1fr
    }

    .sales-method-legend{
        grid-template-columns:1fr
    }

    .sales-chart-wrap,
    .sales-chart-line-wrap,
    .sales-chart-bar-wrap{
        height:240px
    }

    .sales-chart-method-canvas{
        height:220px
    }

}

</style>