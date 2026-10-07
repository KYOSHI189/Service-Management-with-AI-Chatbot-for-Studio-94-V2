<?php
// ============================================================
// STUDIO 94 SNAPTRACK
// REPORTS AND ANALYTICS MODULE
// 9 REPORTS
// ============================================================

requireRole('admin');
$pdo = db();

// ============================================================
// HELPERS
// ============================================================

if (!function_exists('reportEsc')) {
    function reportEsc($value)
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('reportDate')) {
    function reportDate($date)
    {
        if (empty($date)) {
            return '—';
        }

        $timestamp = strtotime($date);

        return $timestamp
            ? date('M d, Y', $timestamp)
            : reportEsc($date);
    }
}

if (!function_exists('reportMoney')) {
    function reportMoney($amount)
    {
        return '₱' . number_format((float)$amount, 2);
    }
}

if (!function_exists('reportStatusBadge')) {
    function reportStatusBadge($status)
    {
        $status = trim((string)$status);
        $upper  = strtoupper($status);

        if (
            in_array($upper, [
                'PAID',
                'COMPLETED',
                'APPROVED',
                'ACTIVE',
                'IN STOCK',
                'GOOD',
                'EXCELLENT'
            ], true)
        ) {
            $class = 'badge-green';

        } elseif (
            in_array($upper, [
                'PENDING',
                'AWAITING APPROVAL',
                'APPROVED (UNPAID)',
                'LOW STOCK',
                'WARNING',
                'ATTENTION'
            ], true)
        ) {
            $class = 'badge-amber';

        } elseif (
            in_array($upper, [
                'REJECTED',
                'CANCELLED',
                'OUT OF STOCK',
                'INACTIVE',
                'URGENT',
                'DANGER'
            ], true)
        ) {
            $class = 'badge-red';

        } else {
            $class = 'badge-gray';
        }

        return '<span class="badge ' . $class . '">' .
            reportEsc($status) .
            '</span>';
    }
}

if (!function_exists('validReportDate')) {
    function validReportDate($date)
    {
        if (!is_string($date) || $date === '') {
            return false;
        }

        $d = DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }
}

// ============================================================
// REPORT TYPE
// ============================================================

$type = $_GET['type'] ?? 'appointment';

$allowedTypes = [
    'inventory',
    'appointment',
    'payment',
    'sales',
    'loyalty',
    'analytics',
    'evaluation',
    'chatbot',
    'photos'
];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'appointment';
}

// ============================================================
// DATE RANGE
// ============================================================

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');

if (!validReportDate($from)) {
    $from = date('Y-m-01');
}

if (!validReportDate($to)) {
    $to = date('Y-m-t');
}

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$reportData = [];

// ============================================================
// 9 REPORT DEFINITIONS
// ============================================================

$reportTypes = [

    'inventory' => [
        'title'       => 'Inventory Report',
        'headers'     => [
            'Item Name',
            'Category',
            'Quantity',
            'Threshold',
            'Status'
        ],
        'icon'        => '📦',
        'description' => 'Current inventory items and stock status.',
        'dateBased'   => false
    ],

    'appointment' => [
        'title'       => 'Appointment Report',
        'headers'     => [
            'Booking Ref',
            'Client',
            'Package',
            'Date',
            'Time',
            'Status',
            'Type'
        ],
        'icon'        => '📅',
        'description' => 'Bookings and appointments within the selected date range.',
        'dateBased'   => true
    ],

    'payment' => [
        'title'       => 'Payment Report',
        'headers'     => [
            'Payment Ref',
            'Client',
            'Package',
            'Amount',
            'Method',
            'Status',
            'Date'
        ],
        'icon'        => '💳',
        'description' => 'Payment transactions and payment status within the selected date range.',
        'dateBased'   => true
    ],

    'sales' => [
        'title'       => 'Sales Report',
        'headers'     => [
            'Month',
            'Transactions',
            'Revenue',
            'Walk-in',
            'Online'
        ],
        'icon'        => '💰',
        'description' => 'Actual paid sales and revenue summarized by month.',
        'dateBased'   => true
    ],

    'loyalty' => [
        'title'       => 'Loyalty Report',
        'headers'     => [
            'Client',
            'Card Number',
            'Total Bookings',
            'Rewards Earned',
            'Status'
        ],
        'icon'        => '🎫',
        'description' => 'Current client loyalty cards, booking counts, and rewards.',
        'dateBased'   => false
    ],

    'analytics' => [
        'title'       => 'Analytics Report',
        'headers'     => [
            'Metric',
            'Value',
            'Change',
            'Status'
        ],
        'icon'        => '📊',
        'description' => 'Current system statistics and selected-period performance.',
        'dateBased'   => true
    ],

    'evaluation' => [
        'title'       => 'Evaluation Report',
        'headers'     => [
            'Client',
            'Rating',
            'Sentiment',
            'Urgent',
            'Comment',
            'Date'
        ],
        'icon'        => '⭐',
        'description' => 'Client feedback, ratings, sentiment, and urgent concerns.',
        'dateBased'   => true
    ],

    'chatbot' => [
        'title'       => 'Chatbot Log Report',
        'headers'     => [
            'User',
            'Message',
            'Reply',
            'Session',
            'Date'
        ],
        'icon'        => '🤖',
        'description' => 'AI chatbot conversation logs within the selected date range.',
        'dateBased'   => true
    ],

    'photos' => [
        'title'       => 'Photo Gallery Access Report',
        'headers'     => [
            'Booking Ref',
            'Client',
            'Photo',
            'Uploaded By',
            'Status',
            'Date'
        ],
        'icon'        => '🖼️',
        'description' => 'Photo gallery uploads associated with client bookings.',
        'dateBased'   => true
    ]
];

// ============================================================
// GET REPORT DATA
// ============================================================

function getReportData($type, $from, $to, PDO $pdo)
{
    $data = [];

    switch ($type) {

        // ====================================================
        // 1. INVENTORY
        // ====================================================

        case 'inventory':

            $stmt = $pdo->query("
                SELECT
                    name,
                    category,
                    quantity,
                    threshold,
                    CASE
                        WHEN quantity = 0 THEN 'Out of Stock'
                        WHEN quantity <= threshold THEN 'Low Stock'
                        ELSE 'In Stock'
                    END AS status
                FROM inventory
                ORDER BY category ASC, name ASC
            ");

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ====================================================
        // 2. APPOINTMENT
        // ====================================================

        case 'appointment':

            $stmt = $pdo->prepare("
                SELECT
                    b.booking_ref,
                    u.name AS client,
                    p.name AS package,
                    b.date,
                    b.time,
                    b.status,
                    b.type
                FROM bookings b
                LEFT JOIN users u
                    ON b.user_id = u.id
                LEFT JOIN packages p
                    ON b.package_id = p.id
                WHERE b.date BETWEEN ? AND ?
                ORDER BY b.date DESC, b.time DESC, b.id DESC
            ");

            $stmt->execute([
                $from,
                $to
            ]);

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ====================================================
        // 3. PAYMENT
        // ====================================================

        case 'payment':

            $stmt = $pdo->prepare("
                SELECT
                    py.payment_ref,
                    u.name AS client,
                    p.name AS package,
                    py.amount,
                    py.method,
                    py.status,
                    DATE(py.created_at) AS date
                FROM payments py
                LEFT JOIN bookings b
                    ON py.booking_id = b.id
                LEFT JOIN users u
                    ON py.user_id = u.id
                LEFT JOIN packages p
                    ON b.package_id = p.id
                WHERE DATE(py.created_at) BETWEEN ? AND ?
                ORDER BY py.created_at DESC
            ");

            $stmt->execute([
                $from,
                $to
            ]);

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ====================================================
        // 4. SALES
        // IMPORTANT:
        // Revenue is based on ACTUAL PAID PAYMENTS.
        // ====================================================

        case 'sales':

            $stmt = $pdo->prepare("
                SELECT
                    DATE_FORMAT(py.created_at, '%M %Y') AS month_name,

                    COUNT(py.id) AS transactions,

                    COALESCE(SUM(py.amount), 0) AS revenue,

                    SUM(
                        CASE
                            WHEN b.type = 'walk-in'
                                 OR b.booking_ref LIKE 'WALK%'
                            THEN 1
                            ELSE 0
                        END
                    ) AS walkin,

                    SUM(
                        CASE
                            WHEN b.type = 'online'
                            THEN 1
                            ELSE 0
                        END
                    ) AS online

                FROM payments py

                INNER JOIN bookings b
                    ON py.booking_id = b.id

                WHERE UPPER(py.status) = 'PAID'
                  AND DATE(py.created_at) BETWEEN ? AND ?

                GROUP BY
                    YEAR(py.created_at),
                    MONTH(py.created_at)

                ORDER BY
                    YEAR(py.created_at) DESC,
                    MONTH(py.created_at) DESC
            ");

            $stmt->execute([
                $from,
                $to
            ]);

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ====================================================
        // 5. LOYALTY
        // Current status report
        // ====================================================

        case 'loyalty':

            $stmt = $pdo->query("
                SELECT
                    u.name AS client,
                    lc.card_number,
                    lc.total_bookings,

                    CASE
                        WHEN lc.total_bookings >= 4 THEN '50% OFF'
                        WHEN lc.total_bookings >= 3 THEN '+1 BACKDROP'
                        WHEN lc.total_bookings >= 2 THEN '+1 PRINT OUT'
                        WHEN lc.total_bookings >= 1 THEN '+5 MINUTES'
                        ELSE 'None'
                    END AS rewards,

                    lc.status

                FROM loyalty_cards lc

                INNER JOIN users u
                    ON lc.user_id = u.id

                ORDER BY
                    lc.total_bookings DESC,
                    u.name ASC
            ");

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ====================================================
        // 6. ANALYTICS
        // No hard-coded percentages.
        // ====================================================

        case 'analytics':

            // Current selected period
            $currentBookingsStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE date BETWEEN ? AND ?
            ");

            $currentBookingsStmt->execute([
                $from,
                $to
            ]);

            $currentBookings = (int)$currentBookingsStmt->fetchColumn();


            // Completed bookings
            $completedStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE status = 'Completed'
                  AND date BETWEEN ? AND ?
            ");

            $completedStmt->execute([
                $from,
                $to
            ]);

            $completedBookings = (int)$completedStmt->fetchColumn();


            // Pending approvals
            $pendingStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE status = 'Awaiting Approval'
                  AND date BETWEEN ? AND ?
            ");

            $pendingStmt->execute([
                $from,
                $to
            ]);

            $pendingBookings = (int)$pendingStmt->fetchColumn();


            // Actual paid revenue
            $revenueStmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM payments
                WHERE UPPER(status) = 'PAID'
                  AND DATE(created_at) BETWEEN ? AND ?
            ");

            $revenueStmt->execute([
                $from,
                $to
            ]);

            $periodRevenue = (float)$revenueStmt->fetchColumn();


            // Clients
            $totalUsers = (int)$pdo->query("
                SELECT COUNT(*)
                FROM users
                WHERE role = 'client'
            ")->fetchColumn();


            // Inventory
            $totalInventory = (int)$pdo->query("
                SELECT COUNT(*)
                FROM inventory
            ")->fetchColumn();


            // Low stock
            $lowStock = (int)$pdo->query("
                SELECT COUNT(*)
                FROM inventory
                WHERE is_reusable = 0
                  AND quantity <= threshold
            ")->fetchColumn();


            // Feedback
            $feedbackStmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_feedback,
                    AVG(rating_overall) AS avg_rating
                FROM feedback
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");

            $feedbackStmt->execute([
                $from,
                $to
            ]);

            $feedbackStats = $feedbackStmt->fetch(PDO::FETCH_ASSOC);

            $totalFeedback = (int)($feedbackStats['total_feedback'] ?? 0);
            $avgRating = (float)($feedbackStats['avg_rating'] ?? 0);


            // ------------------------------------------------
            // Previous equivalent period
            // Used for actual change calculation.
            // ------------------------------------------------

            $fromDate = new DateTime($from);
            $toDate   = new DateTime($to);

            $days = $fromDate->diff($toDate)->days + 1;

            $previousToDate = clone $fromDate;
            $previousToDate->modify('-1 day');

            $previousFromDate = clone $previousToDate;
            $previousFromDate->modify('-' . ($days - 1) . ' days');

            $previousFrom = $previousFromDate->format('Y-m-d');
            $previousTo   = $previousToDate->format('Y-m-d');


            // Previous bookings
            $prevBookingsStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE date BETWEEN ? AND ?
            ");

            $prevBookingsStmt->execute([
                $previousFrom,
                $previousTo
            ]);

            $previousBookings = (int)$prevBookingsStmt->fetchColumn();


            // Previous revenue
            $prevRevenueStmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM payments
                WHERE UPPER(status) = 'PAID'
                  AND DATE(created_at) BETWEEN ? AND ?
            ");

            $prevRevenueStmt->execute([
                $previousFrom,
                $previousTo
            ]);

            $previousRevenue = (float)$prevRevenueStmt->fetchColumn();


            // Previous completed bookings
            $prevCompletedStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE status = 'Completed'
                  AND date BETWEEN ? AND ?
            ");

            $prevCompletedStmt->execute([
                $previousFrom,
                $previousTo
            ]);

            $previousCompleted = (int)$prevCompletedStmt->fetchColumn();


            // Change helper
            $calculateChange = function ($current, $previous) {

                $current = (float)$current;
                $previous = (float)$previous;

                if ($previous == 0) {
                    if ($current == 0) {
                        return '0%';
                    }

                    return '+100%';
                }

                $change = (($current - $previous) / $previous) * 100;

                return ($change >= 0 ? '+' : '') .
                    number_format($change, 1) . '%';
            };


            $bookingChange = $calculateChange(
                $currentBookings,
                $previousBookings
            );

            $revenueChange = $calculateChange(
                $periodRevenue,
                $previousRevenue
            );

            $completedChange = $calculateChange(
                $completedBookings,
                $previousCompleted
            );


            // Status helper
            $performanceStatus = function ($change) {

                $numeric = (float)str_replace(
                    ['%', '+'],
                    '',
                    $change
                );

                if ($numeric > 0) {
                    return 'Improving';
                }

                if ($numeric < 0) {
                    return 'Declining';
                }

                return 'Stable';
            };


            $data = [

                [
                    'metric'  => 'Total Bookings',
                    'value'   => $currentBookings,
                    'change'  => $bookingChange,
                    'status'  => $performanceStatus($bookingChange)
                ],

                [
                    'metric'  => 'Total Clients',
                    'value'   => $totalUsers,
                    'change'  => '—',
                    'status'  => 'Current'
                ],

                [
                    'metric'  => 'Paid Revenue',
                    'value'   => reportMoney($periodRevenue),
                    'change'  => $revenueChange,
                    'status'  => $performanceStatus($revenueChange)
                ],

                [
                    'metric'  => 'Pending Bookings',
                    'value'   => $pendingBookings,
                    'change'  => '—',
                    'status'  => $pendingBookings > 0
                        ? 'Attention'
                        : 'Good'
                ],

                [
                    'metric'  => 'Completed Bookings',
                    'value'   => $completedBookings,
                    'change'  => $completedChange,
                    'status'  => $performanceStatus($completedChange)
                ],

                [
                    'metric'  => 'Inventory Items',
                    'value'   => $totalInventory,
                    'change'  => '—',
                    'status'  => 'Current'
                ],

                [
                    'metric'  => 'Low Stock Items',
                    'value'   => $lowStock,
                    'change'  => '—',
                    'status'  => $lowStock > 0
                        ? 'Warning'
                        : 'Good'
                ],

                [
                    'metric'  => 'Feedback Records',
                    'value'   => $totalFeedback,
                    'change'  => '—',
                    'status'  => 'Current'
                ],

                [
                    'metric'  => 'Average Rating',
                    'value'   => $avgRating > 0
                        ? number_format($avgRating, 1) . ' ★'
                        : 'No rating',
                    'change'  => '—',
                    'status'  => $avgRating >= 4
                        ? 'Good'
                        : ($avgRating > 0 ? 'Attention' : 'No Data')
                ]
            ];

            break;


        // ====================================================
        // 7. EVALUATION
        // ====================================================

        case 'evaluation':

            $stmt = $pdo->prepare("
                SELECT
                    u.name AS client,
                    f.rating_overall,
                    f.sentiment,

                    CASE
                        WHEN f.is_urgent = 1 THEN 'Yes'
                        ELSE 'No'
                    END AS urgent,

                    f.comment,
                    DATE(f.created_at) AS date

                FROM feedback f

                LEFT JOIN users u
                    ON f.user_id = u.id

                WHERE DATE(f.created_at) BETWEEN ? AND ?

                ORDER BY
                    f.created_at DESC
            ");

            $stmt->execute([
                $from,
                $to
            ]);

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            break;


        // ====================================================
        // 8. CHATBOT
        // ====================================================

        case 'chatbot':

            $stmt = $pdo->prepare("
                SELECT
                    u.name AS user,
                    cm.message,
                    cm.type,
                    cs.id AS session_id,
                    cm.created_at AS date

                FROM chat_messages cm

                INNER JOIN chat_sessions cs
                    ON cm.session_id = cs.id

                LEFT JOIN users u
                    ON cs.user_id = u.id

                WHERE DATE(cm.created_at) BETWEEN ? AND ?

                ORDER BY
                    cs.id ASC,
                    cm.created_at ASC
            ");

            $stmt->execute([
                $from,
                $to
            ]);

            $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            $temp = [];


            // Group by session
            foreach ($rawData as $row) {

                $sessionId = $row['session_id'];

                if (!isset($temp[$sessionId])) {
                    $temp[$sessionId] = [];
                }

                $temp[$sessionId][] = $row;
            }


            // Pair user message and bot reply
            foreach ($temp as $sessionId => $messages) {

                $userMsg = '';
                $lastUser = '';
                $userDate = '';

                foreach ($messages as $msg) {

                    $messageType = strtolower(
                        trim((string)$msg['type'])
                    );

                    if ($messageType === 'user') {

                        // Save previous unanswered message
                        if ($userMsg !== '') {

                            $data[] = [
                                'user'       => $lastUser,
                                'message'    => $userMsg,
                                'reply'      => '—',
                                'session_id' => $sessionId,
                                'date'       => $userDate
                            ];
                        }

                        $userMsg  = $msg['message'];
                        $lastUser = $msg['user'] ?? 'Client';
                        $userDate = $msg['date'];

                    } elseif (
                        in_array(
                            $messageType,
                            ['bot', 'assistant'],
                            true
                        )
                        && $userMsg !== ''
                    ) {

                        $data[] = [
                            'user'       => $lastUser,
                            'message'    => $userMsg,
                            'reply'      => $msg['message'],
                            'session_id' => $sessionId,
                            'date'       => $msg['date']
                        ];

                        $userMsg  = '';
                        $lastUser = '';
                        $userDate = '';
                    }
                }


                // Remaining unanswered message
                if ($userMsg !== '') {

                    $data[] = [
                        'user'       => $lastUser,
                        'message'    => $userMsg,
                        'reply'      => '—',
                        'session_id' => $sessionId,
                        'date'       => $userDate
                    ];
                }
            }


            // Newest first
            usort(
                $data,
                function ($a, $b) {
                    return strtotime($b['date'])
                        <=> strtotime($a['date']);
                }
            );

            break;


        // ====================================================
        // 9. PHOTO GALLERY
        // ====================================================

        case 'photos':

            $stmt = $pdo->prepare("
                SELECT
                    b.booking_ref,
                    u.name AS client,
                    p.filename AS photo,
                    uploader.name AS uploaded_by,
                    p.status,
                    DATE(p.created_at) AS date

                FROM photos p

                INNER JOIN bookings b
                    ON p.booking_id = b.id

                LEFT JOIN users u
                    ON b.user_id = u.id

                LEFT JOIN users uploader
                    ON p.uploader_id = uploader.id

                WHERE DATE(p.created_at) BETWEEN ? AND ?

                ORDER BY
                    p.created_at DESC
            ");

            $stmt->execute([
                $from,
                $to
            ]);

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            break;
    }

    return [
        'data' => $data
    ];
}

// ============================================================
// FPDF LOAD
// ============================================================

$fpdfLoaded = false;

if (class_exists('FPDF')) {
    $fpdfLoaded = true;
} else {

    $fpdfPaths = [
        __DIR__ . '/../../includes/fpdf/fpdf.php',
        __DIR__ . '/../../includes/fpdf.php',
        __DIR__ . '/../includes/fpdf/fpdf.php',
        __DIR__ . '/../includes/fpdf.php',
    ];

    foreach ($fpdfPaths as $path) {

        if (file_exists($path)) {

            require_once $path;

            if (class_exists('FPDF')) {
                $fpdfLoaded = true;
                break;
            }
        }
    }
}

// ============================================================
// PDF CLASS
// ============================================================

if ($fpdfLoaded) {

    class Studio94ReportPDF extends FPDF
    {
        public function safeEncode($txt)
        {
            $txt = (string)$txt;

            $converted = @iconv(
                'UTF-8',
                'windows-1252//TRANSLIT//IGNORE',
                $txt
            );

            return $converted !== false
                ? $converted
                : $txt;
        }

        public function Cell(
            $w,
            $h = 0,
            $txt = '',
            $border = 0,
            $ln = 0,
            $align = '',
            $fill = false,
            $link = ''
        ) {
            parent::Cell(
                $w,
                $h,
                $this->safeEncode($txt),
                $border,
                $ln,
                $align,
                $fill,
                $link
            );
        }

        public function MultiCell(
            $w,
            $h,
            $txt,
            $border = 0,
            $align = 'J',
            $fill = false
        ) {
            parent::MultiCell(
                $w,
                $h,
                $this->safeEncode($txt),
                $border,
                $align,
                $fill
            );
        }
    }
}

// ============================================================
// PDF GENERATION
// ============================================================

function generateReportPDF(
    $type,
    $from,
    $to,
    $result,
    $reportTypes
) {

    global $fpdfLoaded;

    if (!$fpdfLoaded) {

        if (function_exists('setFlash')) {
            setFlash(
                'warning',
                'PDF library was not found. Please check the FPDF installation.'
            );
        }

        header('Location: index.php?page=reports');
        exit;
    }

    $info = $reportTypes[$type];
    $data = $result['data'];

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $pdf = new Studio94ReportPDF(
        'L',
        'mm',
        'A4'
    );

    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();


    // ========================================================
    // TITLE
    // ========================================================

    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(108, 99, 255);

    $pdf->Cell(
        0,
        9,
        'STUDIO 94 SNAPTRACK',
        0,
        1,
        'C'
    );

    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('Arial', 'B', 14);

    $pdf->Cell(
        0,
        8,
        $info['title'],
        0,
        1,
        'C'
    );

    $pdf->SetFont('Arial', '', 9);

    $pdf->Cell(
        0,
        6,
        'Date Range: ' .
        date('M d, Y', strtotime($from)) .
        ' - ' .
        date('M d, Y', strtotime($to)),
        0,
        1,
        'C'
    );

    $pdf->Cell(
        0,
        6,
        'Generated: ' .
        date('F d, Y h:i A'),
        0,
        1,
        'C'
    );

    $pdf->Ln(5);


    // ========================================================
    // COLUMN WIDTHS
    // ========================================================

    $widths = [

        'inventory' => [
            55, 45, 25, 25, 35
        ],

        'appointment' => [
            35, 40, 50, 25, 20, 35, 25
        ],

        'payment' => [
            35, 40, 50, 30, 30, 30, 25
        ],

        'sales' => [
            55, 35, 50, 35, 35
        ],

        'loyalty' => [
            50, 45, 35, 45, 30
        ],

        'analytics' => [
            65, 40, 40, 40
        ],

        'evaluation' => [
            45, 20, 35, 25, 100, 30
        ],

        'chatbot' => [
            35, 75, 75, 25, 30
        ],

        'photos' => [
            35, 40, 70, 45, 30, 30
        ]
    ];

    $colWidths = $widths[$type];

    $headers = $info['headers'];


    // ========================================================
    // TABLE HEADER
    // ========================================================

    $pdf->SetFont('Arial', 'B', 8);

    $pdf->SetFillColor(
        230,
        230,
        230
    );

    foreach ($headers as $index => $header) {

        $width = $colWidths[$index]
            ?? (277 / count($headers));

        $pdf->Cell(
            $width,
            7,
            $header,
            1,
            0,
            'C',
            true
        );
    }

    $pdf->Ln();


    // ========================================================
    // TABLE BODY
    // ========================================================

    $pdf->SetFont(
        'Arial',
        '',
        7
    );

    $rowNum = 0;


    foreach ($data as $row) {

        // --------------------------------------------
        // Prepare row
        // --------------------------------------------

        switch ($type) {

            case 'inventory':

                $rowData = [
                    $row['name'],
                    $row['category'],
                    $row['quantity'],
                    $row['threshold'],
                    $row['status']
                ];

                break;


            case 'appointment':

                $rowData = [
                    $row['booking_ref'],
                    $row['client'],
                    $row['package'],
                    $row['date'],
                    $row['time'],
                    $row['status'],
                    $row['type']
                ];

                break;


            case 'payment':

                $rowData = [
                    $row['payment_ref'],
                    $row['client'],
                    $row['package'],
                    'PHP ' . number_format(
                        (float)$row['amount'],
                        2
                    ),
                    $row['method'] ?? '—',
                    $row['status'],
                    $row['date']
                ];

                break;


            case 'sales':

                $rowData = [
                    $row['month_name'],
                    $row['transactions'],
                    'PHP ' . number_format(
                        (float)$row['revenue'],
                        2
                    ),
                    $row['walkin'],
                    $row['online']
                ];

                break;


            case 'loyalty':

                $rowData = [
                    $row['client'],
                    $row['card_number'],
                    $row['total_bookings'],
                    $row['rewards'],
                    $row['status']
                ];

                break;


            case 'analytics':

                $rowData = [
                    $row['metric'],
                    $row['value'],
                    $row['change'],
                    $row['status']
                ];

                break;


            case 'evaluation':

                $rating = (int)$row['rating_overall'];

                $rating = max(
                    0,
                    min(5, $rating)
                );

                $stars =
                    str_repeat('★', $rating) .
                    str_repeat('☆', 5 - $rating);

                $rowData = [
                    $row['client'],
                    $stars,
                    ucfirst(
                        $row['sentiment'] ?? '—'
                    ),
                    $row['urgent'],
                    $row['comment'] ?? '—',
                    $row['date']
                ];

                break;


            case 'chatbot':

                $message = (string)$row['message'];
                $reply   = (string)($row['reply'] ?? '—');

                $rowData = [
                    $row['user'],
                    mb_substr($message, 0, 70) .
                        (mb_strlen($message) > 70 ? '...' : ''),
                    mb_substr($reply, 0, 70) .
                        (mb_strlen($reply) > 70 ? '...' : ''),
                    $row['session_id'],
                    $row['date']
                ];

                break;


            case 'photos':

                $rowData = [
                    $row['booking_ref'],
                    $row['client'],
                    $row['photo'],
                    $row['uploaded_by'],
                    $row['status'],
                    $row['date']
                ];

                break;


            default:

                $rowData = [];

                break;
        }


        // --------------------------------------------
        // Alternating row fill
        // --------------------------------------------

        $fill = ($rowNum % 2 === 0);

        $pdf->SetFillColor(
            248,
            248,
            248
        );


        // --------------------------------------------
        // Normal rows
        // --------------------------------------------

        foreach ($rowData as $index => $cell) {

            $width = $colWidths[$index]
                ?? (277 / count($rowData));

            $text = strip_tags(
                (string)$cell
            );

            $pdf->Cell(
                $width,
                6,
                mb_substr($text, 0, 100),
                1,
                0,
                'L',
                $fill
            );
        }

        $pdf->Ln();

        $rowNum++;
    }


    // ========================================================
    // FOOTER
    // ========================================================

    $pdf->Ln(5);

    $pdf->SetFont(
        'Arial',
        'I',
        8
    );

    $pdf->Cell(
        0,
        5,
        'Generated by Studio 94 SnapTrack.',
        0,
        1,
        'C'
    );

    $pdf->Cell(
        0,
        5,
        'Total Records: ' . count($data),
        0,
        1,
        'C'
    );


    $filename =
        'report_' .
        $type .
        '_' .
        date('Y-m-d') .
        '.pdf';

    $pdf->Output(
        'D',
        $filename
    );

    exit;
}

// ============================================================
// EXPORT PDF
// ============================================================

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'pdf'
) {

    $result = getReportData(
        $type,
        $from,
        $to,
        $pdo
    );

    if (empty($result['data'])) {

        if (function_exists('setFlash')) {
            setFlash(
                'warning',
                'No data available to export.'
            );
        }

        header(
            'Location: index.php?page=reports&type=' .
            urlencode($type)
        );

        exit;
    }

    generateReportPDF(
        $type,
        $from,
        $to,
        $result,
        $reportTypes
    );
}

// ============================================================
// EXPORT CSV
// ============================================================

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'csv'
) {

    $result = getReportData(
        $type,
        $from,
        $to,
        $pdo
    );

    $data = $result['data'];

    if (empty($data)) {

        if (function_exists('setFlash')) {
            setFlash(
                'warning',
                'No data available to export.'
            );
        }

        header(
            'Location: index.php?page=reports&type=' .
            urlencode($type)
        );

        exit;
    }


    while (ob_get_level() > 0) {
        ob_end_clean();
    }


    $headers =
        $reportTypes[$type]['headers'];


    header(
        'Content-Type: text/csv; charset=UTF-8'
    );

    header(
        'Content-Disposition: attachment; filename="report_' .
        $type .
        '_' .
        date('Y-m-d') .
        '.csv"'
    );

    header(
        'Pragma: no-cache'
    );

    header(
        'Expires: 0'
    );


    $output = fopen(
        'php://output',
        'w'
    );


    // UTF-8 BOM for Excel
    fprintf(
        $output,
        chr(0xEF) .
        chr(0xBB) .
        chr(0xBF)
    );


    fputcsv(
        $output,
        $headers
    );


    foreach ($data as $row) {

        switch ($type) {

            case 'inventory':

                fputcsv(
                    $output,
                    [
                        $row['name'],
                        $row['category'],
                        $row['quantity'],
                        $row['threshold'],
                        $row['status']
                    ]
                );

                break;


            case 'appointment':

                fputcsv(
                    $output,
                    [
                        $row['booking_ref'],
                        $row['client'],
                        $row['package'],
                        $row['date'],
                        $row['time'],
                        $row['status'],
                        $row['type']
                    ]
                );

                break;


            case 'payment':

                fputcsv(
                    $output,
                    [
                        $row['payment_ref'],
                        $row['client'],
                        $row['package'],
                        $row['amount'],
                        $row['method'] ?? '—',
                        $row['status'],
                        $row['date']
                    ]
                );

                break;


            case 'sales':

                fputcsv(
                    $output,
                    [
                        $row['month_name'],
                        $row['transactions'],
                        $row['revenue'],
                        $row['walkin'],
                        $row['online']
                    ]
                );

                break;


            case 'loyalty':

                fputcsv(
                    $output,
                    [
                        $row['client'],
                        $row['card_number'],
                        $row['total_bookings'],
                        $row['rewards'],
                        $row['status']
                    ]
                );

                break;


            case 'analytics':

                fputcsv(
                    $output,
                    [
                        $row['metric'],
                        $row['value'],
                        $row['change'],
                        $row['status']
                    ]
                );

                break;


            case 'evaluation':

                fputcsv(
                    $output,
                    [
                        $row['client'],
                        $row['rating_overall'],
                        $row['sentiment'],
                        $row['urgent'],
                        $row['comment'] ?? '—',
                        $row['date']
                    ]
                );

                break;


            case 'chatbot':

                fputcsv(
                    $output,
                    [
                        $row['user'],
                        $row['message'],
                        $row['reply'] ?? '—',
                        $row['session_id'],
                        $row['date']
                    ]
                );

                break;


            case 'photos':

                fputcsv(
                    $output,
                    [
                        $row['booking_ref'],
                        $row['client'],
                        $row['photo'],
                        $row['uploaded_by'],
                        $row['status'],
                        $row['date']
                    ]
                );

                break;
        }
    }


    fclose($output);

    exit;
}

// ============================================================
// GENERATE REPORT FOR DISPLAY
// ============================================================

if (isset($_GET['generate'])) {

    $result = getReportData(
        $type,
        $from,
        $to,
        $pdo
    );

    $reportData = $result['data'];
}

$currentReport = $reportTypes[$type];

$flash = function_exists('getFlash')
    ? getFlash()
    : null;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Reports and Analytics | Studio 94 SnapTrack
</title>

<style>

:root {

    --primary: #6C63FF;
    --primary-dark: #4A42CC;
    --primary-light: #8B83FF;

    --primary-bg:
        rgba(108, 99, 255, 0.08);

    --primary-gradient:
        linear-gradient(
            135deg,
            #6C63FF,
            #4A42CC
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
        0 2px 10px
        rgba(0,0,0,0.08);

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


/* =========================================================
   PAGE BANNER
   ========================================================= */

.page-banner {

    background:
        var(--primary-gradient);

    color: white;

    padding:
        30px 32px;

    border-radius:
        var(--radius);

    margin-bottom:
        24px;

    box-shadow:
        0 4px 15px
        rgba(108,99,255,0.3);
}

.page-banner h2 {

    font-size: 24px;
    font-weight: 700;
}

.page-banner p {

    opacity: 0.92;

    font-size: 14px;

    margin-top: 5px;
}


/* =========================================================
   CARDS
   ========================================================= */

.card {

    background:
        var(--card-bg);

    border-radius:
        var(--radius);

    box-shadow:
        var(--shadow);

    padding:
        20px 24px;

    margin-bottom:
        20px;
}

.card-title {

    font-size: 18px;

    font-weight: 600;

    margin-bottom:
        16px;

    display: flex;

    align-items: center;

    gap: 8px;
}


/* =========================================================
   SECTION
   ========================================================= */

.section-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 12px;

    margin-bottom: 14px;
}

.section-header h3 {

    font-size: 16px;
    font-weight: 600;
}


/* =========================================================
   FORM
   ========================================================= */

.form-group {

    margin-bottom: 14px;
}

.form-group label {

    display: block;

    font-weight: 500;

    font-size: 13px;

    margin-bottom: 5px;
}

.form-group input,
.form-group select {

    width: 100%;

    padding:
        9px 12px;

    border:
        1px solid var(--border);

    border-radius: 8px;

    font-size: 14px;

    background: white;
}

.form-group input:focus,
.form-group select:focus {

    outline: none;

    border-color:
        var(--primary);

    box-shadow:
        0 0 0 3px
        rgba(108,99,255,0.10);
}

.form-row {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 14px;
}


/* =========================================================
   BUTTONS
   ========================================================= */

.btn {

    padding:
        9px 16px;

    border: none;

    border-radius: 8px;

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;

    transition:
        all 0.2s;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;
}

.btn-primary {

    background:
        var(--primary);

    color: white;
}

.btn-primary:hover {

    background:
        var(--primary-dark);

    transform:
        translateY(-1px);

    box-shadow:
        0 4px 15px
        rgba(108,99,255,0.3);
}

.btn-ghost {

    background:
        var(--bg);

    color: var(--text);

    border:
        1px solid var(--border);
}

.btn-ghost:hover {

    background:
        var(--border);
}

.btn-dark {

    background:
        #2C3E50;

    color: white;
}

.btn-dark:hover {

    background:
        #1a252f;

    transform:
        translateY(-1px);
}


/* =========================================================
   REPORT CARDS
   ========================================================= */

.grid-3 {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(200px, 1fr)
        );

    gap: 12px;
}

.report-card {

    border:
        1px solid var(--border);

    border-radius:
        10px;

    padding:
        15px 14px;

    background:
        var(--bg);

    transition:
        all 0.2s;

    text-align:
        center;
}

.report-card:hover {

    border-color:
        var(--primary-light);

    box-shadow:
        var(--shadow);

    transform:
        translateY(-2px);
}

.report-card.active {

    border-color:
        var(--primary);

    background:
        var(--primary-bg);

    box-shadow:
        0 0 0 2px
        rgba(108,99,255,0.08);
}

.report-card .icon {

    font-size: 25px;
}

.report-card .title {

    font-weight: 600;

    font-size: 13px;

    margin-top: 5px;
}

.report-card .description {

    font-size: 11px;

    color:
        var(--text-muted);

    margin-top: 5px;

    line-height: 1.4;
}


/* =========================================================
   GRID
   ========================================================= */

.grid-2 {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {

    overflow-x: auto;

    max-height: 500px;

    overflow-y: auto;

    border:
        1px solid var(--border);

    border-radius:
        8px;
}

table {

    width: 100%;

    border-collapse:
        collapse;

    font-size:
        13px;

    min-width:
        700px;
}

table th,
table td {

    padding:
        9px 10px;

    text-align:
        left;

    border-bottom:
        1px solid var(--border);

    vertical-align:
        top;
}

table th {

    font-weight:
        600;

    font-size:
        11px;

    text-transform:
        uppercase;

    letter-spacing:
        0.5px;

    color:
        var(--text-muted);

    background:
        var(--bg);

    position:
        sticky;

    top:
        0;

    z-index:
        10;
}

table tr:hover {

    background:
        var(--primary-bg);
}


/* =========================================================
   BADGES
   ========================================================= */

.badge {

    padding:
        3px 10px;

    border-radius:
        50px;

    font-size:
        11px;

    font-weight:
        600;

    display:
        inline-block;

    white-space:
        nowrap;
}

.badge-green {

    background:
        #d4edda;

    color:
        #155724;
}

.badge-amber {

    background:
        #fff3cd;

    color:
        #856404;
}

.badge-red {

    background:
        #f8d7da;

    color:
        #721c24;
}

.badge-gray {

    background:
        var(--bg);

    color:
        var(--text-muted);
}


/* =========================================================
   ALERT
   ========================================================= */

.alert {

    padding:
        12px 16px;

    border-radius:
        8px;

    margin-bottom:
        16px;

    font-size:
        14px;

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    border-left:
        4px solid
        var(--danger);

    background:
        #fef2f2;

    color:
        #721c24;
}

.alert.success {

    border-left-color:
        var(--success);

    background:
        #ecfdf5;

    color:
        #155724;
}

.alert-icon {

    font-size:
        18px;
}


/* =========================================================
   ACTIONS
   ========================================================= */

.action-buttons {

    display:
        flex;

    gap:
        8px;

    flex-wrap:
        wrap;

    margin-top:
        10px;
}


/* =========================================================
   INFO
   ========================================================= */

.report-description {

    padding:
        10px 12px;

    background:
        var(--primary-bg);

    border-left:
        3px solid
        var(--primary);

    border-radius:
        6px;

    color:
        var(--text-muted);

    font-size:
        12px;

    margin-bottom:
        16px;

    line-height:
        1.5;
}

.no-data {

    text-align:
        center;

    color:
        var(--text-muted);

    padding:
        35px 20px;
}

.results-count {

    font-size:
        12px;

    color:
        var(--text-muted);

    font-weight:
        400;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1000px) {

    .grid-2 {

        grid-template-columns:
            1fr;
    }
}

@media (max-width: 768px) {

    body {

        padding:
            12px;
    }

    .form-row {

        grid-template-columns:
            1fr;
    }

    .page-banner {

        padding:
            22px;
    }

    .page-banner h2 {

        font-size:
            20px;
    }

    .action-buttons .btn {

        flex:
            1;
    }
}

</style>

</head>

<body>

<div class="container">


    <!-- =====================================================
         PAGE BANNER
         ===================================================== -->

    <div class="page-banner">

        <h2>
            📊 Reports and Analytics
        </h2>

        <p>
            Generate, view, and export detailed Studio 94 reports and analytics.
        </p>

    </div>


    <!-- =====================================================
         FLASH MESSAGE
         ===================================================== -->

    <?php if ($flash): ?>

        <?php
        $flashType =
            strtolower(
                (string)($flash['type'] ?? 'error')
            );

        $isSuccess =
            $flashType === 'success';
        ?>

        <div class="alert <?= $isSuccess ? 'success' : '' ?>">

            <span class="alert-icon">
                <?= $isSuccess ? '✅' : '⚠️' ?>
            </span>

            <span>
                <?= reportEsc($flash['msg'] ?? '') ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         9 AVAILABLE REPORTS
         ===================================================== -->

    <div class="card">

        <div class="section-header">

            <h3>
                📋 Available Reports
            </h3>

        </div>


        <div class="grid-3">

            <?php foreach ($reportTypes as $key => $info): ?>

                <a
                    href="index.php?page=reports&type=<?= urlencode($key) ?>"
                    style="text-decoration:none;color:inherit;"
                >

                    <div
                        class="report-card <?= $type === $key ? 'active' : '' ?>"
                    >

                        <div class="icon">
                            <?= $info['icon'] ?>
                        </div>

                        <div class="title">
                            <?= reportEsc($info['title']) ?>
                        </div>

                        <div class="description">
                            <?= reportEsc($info['description']) ?>
                        </div>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    </div>


    <!-- =====================================================
         GENERATOR + RESULTS
         ===================================================== -->

    <div class="grid-2">


        <!-- =================================================
             GENERATOR
             ================================================= -->

        <div class="card">

            <div class="card-title">

                <?= $currentReport['icon'] ?>

                Generate Report

            </div>


            <div class="report-description">

                <?= reportEsc(
                    $currentReport['description']
                ) ?>

                <?php if (!$currentReport['dateBased']): ?>

                    <br><br>

                    <strong>
                        Note:
                    </strong>

                    This report shows current system records.
                    The selected date range does not restrict this report.

                <?php endif; ?>

            </div>


            <form
                method="GET"
                action="index.php"
            >

                <input
                    type="hidden"
                    name="page"
                    value="reports"
                >

                <input
                    type="hidden"
                    name="generate"
                    value="1"
                >


                <!-- REPORT TYPE -->

                <div class="form-group">

                    <label>
                        Report Type
                    </label>

                    <select name="type">

                        <?php foreach (
                            $reportTypes
                            as $key => $info
                        ): ?>

                            <option
                                value="<?= reportEsc($key) ?>"
                                <?= $type === $key
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= $info['icon'] ?>

                                <?= reportEsc(
                                    $info['title']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- DATE -->

                <div class="form-row">

                    <div class="form-group">

                        <label>
                            From Date
                        </label>

                        <input
                            type="date"
                            name="from"
                            value="<?= reportEsc($from) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            To Date
                        </label>

                        <input
                            type="date"
                            name="to"
                            value="<?= reportEsc($to) ?>"
                        >

                    </div>

                </div>


                <!-- BUTTONS -->

                <div class="action-buttons">

                    <button
                        type="submit"
                        class="btn btn-primary"
                        style="flex:2;"
                    >
                        📊 Generate & View
                    </button>


                    <?php if (!empty($reportData)): ?>

                        <a
                            href="index.php?page=reports&type=<?= urlencode($type) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=pdf"
                            class="btn btn-dark"
                            style="flex:1;"
                        >
                            📄 PDF
                        </a>


                        <a
                            href="index.php?page=reports&type=<?= urlencode($type) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=csv"
                            class="btn btn-ghost"
                            style="flex:1;"
                        >
                            📥 CSV
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>


        <!-- =================================================
             RESULTS
             ================================================= -->

        <div class="card">

            <div class="card-title">

                <?= $currentReport['icon'] ?>

                Report Results

                <?php if (!empty($reportData)): ?>

                    <span class="results-count">

                        (
                        <?= count($reportData) ?>
                        records
                        )

                    </span>

                <?php endif; ?>

            </div>


            <?php if (
                empty($reportData) &&
                !isset($_GET['generate'])
            ): ?>

                <div class="no-data">

                    <div style="font-size:35px;margin-bottom:10px;">
                        📊
                    </div>

                    Select a report type, choose a date range,
                    and click <strong>Generate & View</strong>
                    to display the report.

                </div>


            <?php elseif (empty($reportData)): ?>

                <div class="no-data">

                    <div style="font-size:35px;margin-bottom:10px;">
                        📭
                    </div>

                    No records found for the selected period.

                </div>


            <?php else: ?>


                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <?php foreach (
                                    $currentReport['headers']
                                    as $header
                                ): ?>

                                    <th>
                                        <?= reportEsc($header) ?>
                                    </th>

                                <?php endforeach; ?>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach (
                            $reportData
                            as $row
                        ): ?>

                            <tr>


                                <!-- =================================
                                     INVENTORY
                                     ================================= -->

                                <?php if (
                                    $type === 'inventory'
                                ): ?>

                                    <td>
                                        <?= reportEsc(
                                            $row['name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['category']
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= (int)$row['quantity'] ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= (int)$row['threshold'] ?>
                                    </td>

                                    <td>
                                        <?= reportStatusBadge(
                                            $row['status']
                                        ) ?>
                                    </td>


                                <!-- =================================
                                     APPOINTMENT
                                     ================================= -->

                                <?php elseif (
                                    $type === 'appointment'
                                ): ?>

                                    <td>
                                        <?= reportEsc(
                                            $row['booking_ref']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['client'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['package'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportDate(
                                            $row['date']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['time']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportStatusBadge(
                                            $row['status']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['type']
                                        ) ?>
                                    </td>


                                <!-- =================================
                                     PAYMENT
                                     ================================= -->

                                <?php elseif (
                                    $type === 'payment'
                                ): ?>

                                    <td>
                                        <?= reportEsc(
                                            $row['payment_ref']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['client'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['package'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= reportMoney(
                                                $row['amount']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['method'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportStatusBadge(
                                            $row['status']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportDate(
                                            $row['date']
                                        ) ?>
                                    </td>


                                <!-- =================================
                                     SALES
                                     ================================= -->

                                <?php elseif (
                                    $type === 'sales'
                                ): ?>

                                    <td>
                                        <?= reportEsc(
                                            $row['month_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int)$row['transactions'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= reportMoney(
                                                $row['revenue']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= (int)$row['walkin'] ?>
                                    </td>

                                    <td>
                                        <?= (int)$row['online'] ?>
                                    </td>


                                <!-- =================================
                                     LOYALTY
                                     ================================= -->

                                <?php elseif (
                                    $type === 'loyalty'
                                ): ?>

                                    <td>
                                        <?= reportEsc(
                                            $row['client']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['card_number']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int)$row['total_bookings'] ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['rewards']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportStatusBadge(
                                            $row['status']
                                        ) ?>
                                    </td>


                                <!-- =================================
                                     ANALYTICS
                                     ================================= -->

                                <?php elseif (
                                    $type === 'analytics'
                                ): ?>

                                    <td>
                                        <strong>
                                            <?= reportEsc(
                                                $row['metric']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= reportEsc(
                                                $row['value']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>

                                        <?php if (
                                            $row['change'] !== '—'
                                        ): ?>

                                            <?= reportEsc(
                                                $row['change']
                                            ) ?>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= reportStatusBadge(
                                            $row['status']
                                        ) ?>
                                    </td>


                                <!-- =================================
                                     EVALUATION
                                     ================================= -->

                                <?php elseif (
                                    $type === 'evaluation'
                                ): ?>

                                    <?php
                                    $rating =
                                        max(
                                            0,
                                            min(
                                                5,
                                                (int)$row['rating_overall']
                                            )
                                        );

                                    $sentiment =
                                        strtolower(
                                            (string)(
                                                $row['sentiment']
                                                ?? ''
                                            )
                                        );

                                    if (
                                        $sentiment === 'positive'
                                    ) {
                                        $sentimentClass =
                                            'badge-green';

                                    } elseif (
                                        $sentiment === 'negative'
                                    ) {
                                        $sentimentClass =
                                            'badge-red';

                                    } else {
                                        $sentimentClass =
                                            'badge-amber';
                                    }
                                    ?>

                                    <td>
                                        <?= reportEsc(
                                            $row['client'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= str_repeat(
                                            '★',
                                            $rating
                                        ) .
                                        str_repeat(
                                            '☆',
                                            5 - $rating
                                        ) ?>
                                    </td>

                                    <td>

                                        <span
                                            class="badge <?= $sentimentClass ?>"
                                        >
                                            <?= reportEsc(
                                                ucfirst(
                                                    $sentiment ?: '—'
                                                )
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <?= $row['urgent'] === 'Yes'
                                            ? reportStatusBadge('Urgent')
                                            : reportStatusBadge('No') ?>

                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['comment'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportDate(
                                            $row['date']
                                        ) ?>
                                    </td>


                                <!-- =================================
                                     CHATBOT
                                     ================================= -->

                                <?php elseif (
                                    $type === 'chatbot'
                                ): ?>

                                    <?php
                                    $message =
                                        (string)(
                                            $row['message']
                                            ?? ''
                                        );

                                    $reply =
                                        (string)(
                                            $row['reply']
                                            ?? '—'
                                        );
                                    ?>

                                    <td>
                                        <?= reportEsc(
                                            $row['user'] ?? 'Client'
                                        ) ?>
                                    </td>

                                    <td title="<?= reportEsc($message) ?>">

                                        <?= reportEsc(
                                            mb_substr(
                                                $message,
                                                0,
                                                60
                                            )
                                        ) ?>

                                        <?php if (
                                            mb_strlen($message) > 60
                                        ): ?>

                                            ...

                                        <?php endif; ?>

                                    </td>

                                    <td title="<?= reportEsc($reply) ?>">

                                        <?= reportEsc(
                                            mb_substr(
                                                $reply,
                                                0,
                                                60
                                            )
                                        ) ?>

                                        <?php if (
                                            mb_strlen($reply) > 60
                                        ): ?>

                                            ...

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['session_id']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportDate(
                                            $row['date']
                                        ) ?>
                                    </td>


                                <!-- =================================
                                     PHOTOS
                                     ================================= -->

                                <?php elseif (
                                    $type === 'photos'
                                ): ?>

                                    <td>
                                        <?= reportEsc(
                                            $row['booking_ref']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['client'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['photo']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEsc(
                                            $row['uploaded_by'] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportStatusBadge(
                                            $row['status']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportDate(
                                            $row['date']
                                        ) ?>
                                    </td>

                                <?php endif; ?>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>

</html>