<?php
// ============================================================
// STUDIO 94 SNAPBOT AI — Complete Fixed (for gemini-3.6-flash)
// File: pages/api/chatbot-ai.php
//
// Fixes:
// - Removed unsupported params (temperature, topP, topK) for 3.6-flash
// - maxOutputTokens = 2000 (para kumpleto ang sagot)
// - Proper system_instruction separation
// - Logs MAX_TOKENS warning if truncated
// - Fixed model fallback chain
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================
// HELPERS
// ============================================================

function chatbotReply(string $reply, array $extra = []): void
{
    echo json_encode(
        array_merge(['reply' => cleanBotText($reply)], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function cleanBotText(string $text): string
{
    // Remove code fences
    $text = str_replace('```', '', $text);

    // Remove markdown bold/italic markers (keep words)
    $text = str_replace(['**', '__'], '', $text);

    // Remove inline code backticks
    $text = str_replace('`', '', $text);

    // Convert markdown bullets "- " or "* " at line start → "• "
    $text = preg_replace('/^[ \t]*[-*][ \t]+/m', '• ', $text);

    // Collapse 3+ blank lines into 2
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    // Trim trailing whitespace per line
    $text = preg_replace('/[ \t]+$/m', '', $text);

    return trim($text);
}

function normalizeText(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text);
    return $text;
}

// ============================================================
// INPUT
// ============================================================

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$message = isset($input['message']) ? trim((string)$input['message']) : '';

if ($message === '') {
    chatbotReply('Please enter a message. 😊');
}

if (mb_strlen($message, 'UTF-8') > 1000) {
    chatbotReply('Sorry, your message is too long. Please keep it shorter. 😊');
}

$text = normalizeText($message);

// ============================================================
// DATABASE
// ============================================================

$pdo = null;
$dbError = null;

try {
    if (function_exists('db')) {
        $pdo = db();
        if (!$pdo instanceof PDO) {
            $dbError = 'DB returned null';
        }
    } else {
        $dbError = 'db() function not defined';
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    error_log('SnapBot DB error: ' . $dbError);
}

function saveChatLog(?PDO $pdo, string $userMessage, string $botReply): void
{
    if (!$pdo instanceof PDO) return;
    try {
        $userId = function_exists('isLoggedIn') && isLoggedIn()
            ? (int)(currentUser()['id'] ?? 1)
            : 1;

        $stmt = $pdo->prepare("
            INSERT INTO chat_history (user_id, message, reply, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $userMessage, $botReply]);
    } catch (Throwable $e) {
        error_log('Chat log error: ' . $e->getMessage());
    }
}

// ============================================================
// STUDIO INFO
// ============================================================

const STUDIO_LOCATION  = '2F SBD Building Highway 1, San Isidro Poblacion, Nabua, Camarines Sur';
const STUDIO_HOURS     = 'Monday to Sunday, 10:00 AM – 7:00 PM';
const STUDIO_OPEN      = 600;    // 10:00 AM in minutes
const STUDIO_CLOSE     = 1140;   // 7:00 PM in minutes
const SLOT_INTERVAL    = 15;
const DEFAULT_DURATION = 60;

// ============================================================
// AVAILABILITY DETECTION
// ============================================================

function isAvailabilityQuestion(string $text): bool
{
    $words = [
        'available', 'availability', 'bakante', 'vacant', 'free slot',
        'may slot', 'may booking', 'may appointment', 'may schedule',
        'slot', 'sched', 'schedule', 'pwede ba', 'pwede sa',
        'may tao ba', 'may shoot ba', 'available pa', 'booked',
        'nakabook', 'schedule ng', 'anong oras', 'what time',
    ];

    foreach ($words as $w) {
        if (mb_strpos($text, $w, 0, 'UTF-8') !== false) return true;
    }

    if (
        preg_match('/\b(book|booking|appointment|shoot|photo shoot|slot)\b/u', $text) &&
        preg_match('/\b(today|tomorrow|bukas|ngayon|monday|tuesday|wednesday|thursday|friday|saturday|sunday|lunes|martes|miyerkules|huwebes|biyernes|sabado|linggo|\d{1,2}[\/\-]\d{1,2})\b/u', $text)
    ) {
        return true;
    }

    return false;
}

// ============================================================
// DATE PARSING
// ============================================================

function parseRequestedDate(string $text): ?DateTime
{
    $today = new DateTime('today', new DateTimeZone('Asia/Manila'));

    if (preg_match('/\b(today|ngayon)\b/u', $text)) return $today;

    if (preg_match('/\b(tomorrow|bukas)\b/u', $text)) {
        $d = clone $today;
        $d->modify('+1 day');
        return $d;
    }

    if (preg_match('/\b(20\d{2})-(\d{1,2})-(\d{1,2})\b/', $text, $m)) {
        $d = DateTime::createFromFormat('!Y-n-j', "{$m[1]}-{$m[2]}-{$m[3]}", new DateTimeZone('Asia/Manila'));
        if ($d && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return $d;
    }

    if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/', $text, $m)) {
        $y = (int)$m[3];
        if ($y < 100) $y += 2000;
        if (checkdate((int)$m[1], (int)$m[2], $y)) {
            return DateTime::createFromFormat('!Y-n-j', "$y-{$m[1]}-{$m[2]}", new DateTimeZone('Asia/Manila'));
        }
    }

    $months = [
        'enero' => 'january', 'pebrero' => 'february', 'marso' => 'march',
        'abril' => 'april', 'mayo' => 'may', 'hunyo' => 'june',
        'hulyo' => 'july', 'agosto' => 'august', 'setyembre' => 'september',
        'oktubre' => 'october', 'nobyembre' => 'november', 'disyembre' => 'december',
    ];
    $dateText = $text;
    foreach ($months as $tl => $en) $dateText = str_replace($tl, $en, $dateText);

    if (preg_match('/\b(january|february|march|april|may|june|july|august|september|sept|october|november|december)\s+(\d{1,2})(?:st|nd|rd|th)?(?:,\s*|\s+)(20\d{2})\b/i', $dateText, $m)) {
        $parsed = strtotime("{$m[1]} {$m[2]} {$m[3]}");
        if ($parsed !== false) {
            $d = new DateTime('@' . $parsed);
            $d->setTimezone(new DateTimeZone('Asia/Manila'));
            return $d;
        }
    }

    if (preg_match('/\b(january|february|march|april|may|june|july|august|september|sept|october|november|december)\s+(\d{1,2})(?:st|nd|rd|th)?\b/i', $dateText, $m)) {
        $y = (int)$today->format('Y');
        $parsed = strtotime("{$m[1]} {$m[2]} $y");
        if ($parsed !== false) {
            $d = new DateTime('@' . $parsed);
            $d->setTimezone(new DateTimeZone('Asia/Manila'));
            if ($d < $today) $d->modify('+1 year');
            return $d;
        }
    }

    $weekdays = [
        'monday' => 'monday', 'lunes' => 'monday',
        'tuesday' => 'tuesday', 'martes' => 'tuesday',
        'wednesday' => 'wednesday', 'miyerkules' => 'wednesday',
        'thursday' => 'thursday', 'huwebes' => 'thursday',
        'friday' => 'friday', 'biyernes' => 'friday',
        'saturday' => 'saturday', 'sabado' => 'saturday',
        'sunday' => 'sunday', 'linggo' => 'sunday',
    ];
    foreach ($weekdays as $w => $day) {
        if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $text)) {
            $d = clone $today;
            $d->modify('next ' . $day);
            return $d;
        }
    }

    return null;
}

function parseRequestedTime(string $text): ?int
{
    if (!preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $text, $m)) {
        return null;
    }
    $h = (int)$m[1];
    $min = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;
    $ampm = strtolower($m[3]);
    if ($min > 59 || $h > 12) return null;
    if ($ampm === 'pm' && $h < 12) $h += 12;
    if ($ampm === 'am' && $h === 12) $h = 0;
    return $h * 60 + $min;
}

function formatTime(int $min): string
{
    $h = intdiv($min, 60);
    $m = $min % 60;
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) $h12 = 12;
    return sprintf('%d:%02d %s', $h12, $m, $ampm);
}

function formatDateLong(DateTime $d): string
{
    return $d->format('l, F j, Y');
}

function isBlockingStatus(?string $status): bool
{
    if ($status === null || trim($status) === '') return true;
    $s = mb_strtolower(trim($status), 'UTF-8');
    $s = str_replace(['_', '-'], ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    $ignore = ['cancelled', 'canceled', 'rejected', 'declined', 'no show', 'void'];
    return !in_array($s, $ignore, true);
}

// ============================================================
// GET BOOKINGS
// ============================================================

function getBookingsForDate(PDO $pdo, DateTime $date): array
{
    $sql = "SELECT `time`, `status` FROM `bookings` WHERE DATE(`date`) = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date->format('Y-m-d')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bookings = [];
    foreach ($rows as $row) {
        if (!isBlockingStatus($row['status'] ?? null)) continue;

        $timeStr = trim((string)($row['time'] ?? ''));
        if ($timeStr === '') continue;

        $ts = strtotime($timeStr);
        if ($ts === false) continue;

        $start = ((int)date('H', $ts) * 60) + (int)date('i', $ts);
        $end = $start + DEFAULT_DURATION;

        $bookings[] = ['start' => $start, 'end' => $end];
    }

    usort($bookings, fn($a, $b) => $a['start'] - $b['start']);
    return $bookings;
}

function slotOverlaps(int $s, int $e, array $bookings): bool
{
    foreach ($bookings as $b) {
        if ($s < $b['end'] && $e > $b['start']) return true;
    }
    return false;
}

function mergeSlots(array $slots): array
{
    if (empty($slots)) return [];
    usort($slots, fn($a, $b) => $a['start'] - $b['start']);
    $merged = [];
    $current = $slots[0];
    for ($i = 1; $i < count($slots); $i++) {
        $next = $slots[$i];
        if ($next['start'] <= $current['end']) {
            if ($next['end'] > $current['end']) {
                $current['end'] = $next['end'];
            }
        } else {
            $merged[] = $current;
            $current = $next;
        }
    }
    $merged[] = $current;
    return $merged;
}

// ============================================================
// BUILD AVAILABILITY RESPONSE
// ============================================================

function buildAvailabilityResponse(DateTime $date, array $bookings, ?int $reqTime = null): string
{
    $label = formatDateLong($date);
    $out = "📅 " . $label . "\n";

    if ($reqTime !== null) {
        $end = $reqTime + DEFAULT_DURATION;
        $out .= "\n";

        if ($reqTime < STUDIO_OPEN || $end > STUDIO_CLOSE) {
            $out .= "❌ " . formatTime($reqTime) . " is outside our studio hours.\n";
            $out .= "\n🕐 Studio hours: 10:00 AM – 7:00 PM.";
            return $out;
        }

        $isBooked = slotOverlaps($reqTime, $end, $bookings);

        if (!$isBooked) {
            $out .= "✅ " . formatTime($reqTime) . " – " . formatTime($end) . " is available.\n";
            $out .= "\nYou may proceed with your booking. 😊";
            return $out;
        }

        $out .= "❌ " . formatTime($reqTime) . " – " . formatTime($end) . " is already booked.\n";
        $out .= "\nHere are the available times for this date:\n";
    }

    $out .= "\n📌 Booked schedule:\n";
    if (empty($bookings)) {
        $out .= "• None — the whole day is free!\n";
    } else {
        foreach (mergeSlots($bookings) as $b) {
            $out .= "• " . formatTime((int)$b['start']) . "–" . formatTime((int)$b['end']) . "\n";
        }
    }

    $availableSlots = [];
    for ($s = STUDIO_OPEN; $s + DEFAULT_DURATION <= STUDIO_CLOSE; $s += SLOT_INTERVAL) {
        $e = $s + DEFAULT_DURATION;
        if (!slotOverlaps($s, $e, $bookings)) {
            $availableSlots[] = ['start' => $s, 'end' => $e];
        }
    }

    $mergedAvailable = mergeSlots($availableSlots);

    $out .= "\n✅ Available time:\n";
    if (empty($mergedAvailable)) {
        $out .= "• No available slots on this date.\n";
        $out .= "\nPlease try another date. 😊";
    } else {
        foreach ($mergedAvailable as $m) {
            $out .= "• " . formatTime((int)$m['start']) . "–" . formatTime((int)$m['end']) . "\n";
        }
    }

    $out .= "\n🕐 Studio hours: 10:00 AM – 7:00 PM.";
    return $out;
}

// ============================================================
// FAQ
// ============================================================

function answerFAQ(string $text): ?string
{
    if (preg_match('/^(hi|hello|hey|kumusta|kamusta|musta|good morning|good afternoon|good evening|magandang umaga|magandang hapon|magandang gabi)[\s!?.]*$/iu', $text)) {
        return "👋 Hello! Welcome to Studio 94!\n\n" .
            "I'm SnapBot, your virtual assistant. I can help you with:\n\n" .
            "• 📸 Packages & prices\n" .
            "• 📅 Booking & availability\n" .
            "• 💳 Payment (GCash)\n" .
            "• 📍 Location & hours\n" .
            "• 📋 Cancellation policy\n" .
            "• 📷 Photos & gallery\n\n" .
            "What would you like to know? 😊";
    }

    if (preg_match('/\b(where (are|is)|saan (ba|po|kayo)|nasaan|location|address|paano pumunta|directions|map|landmark)\b/u', $text)) {
        return "📍 Studio 94 is located at:\n\n" .
            STUDIO_LOCATION . "\n\n" .
            "🕐 Open daily: 10:00 AM – 7:00 PM\n\n" .
            "You may visit us during studio hours. 😊";
    }

    if (preg_match('/\b(what time (do you |are you )?open|what time.*close|anong oras|opening hours|closing time|bukas ba kayo|sarado ba|open ba kayo)\b/u', $text)) {
        return "🕐 Studio 94 is open:\n\n" .
            "• Monday to Sunday\n" .
            "• 10:00 AM – 7:00 PM\n\n" .
            "Open every day, including weekends. 😊";
    }

    if (preg_match('/\b(what (are|is) (your |the )?(packages|package)|list of packages|ano (ang |mga )?packages|available packages|what packages)\b/u', $text)) {
        return "📸 Studio 94 Packages:\n\n" .
            "• Self-Shoot: ₱249–₱1,199\n" .
            "• Creative Shoot: ₱2,000–₱3,500\n" .
            "• Family: ₱2,500–₱4,500\n" .
            "• Studio Rental: ₱1,500–₱3,000\n\n" .
            "Which package are you interested in? 😊";
    }

    if (preg_match('/\b(how much.*self[- ]?shoot|self[- ]?shoot (price|cost)|magkano.*self[- ]?shoot)\b/u', $text)) {
        return "📸 Self-Shoot Packages:\n\n" .
            "• Package A: ₱249 — 15 mins, 1 person\n" .
            "• Package B: ₱399 — 15 mins, 1–2 persons\n" .
            "• Package C: ₱499 — 20 mins, 1–3 persons\n" .
            "• Package D: ₱549 — 20 mins, 1–2 persons with pet\n" .
            "• Package E: ₱899 — 35 mins, 1–5 persons\n" .
            "• Package F: ₱1,199 — 45 mins, 1–7 persons\n\n" .
            "All include enhanced photos and prints. 😊";
    }

    if (preg_match('/\b(how much.*creative|creative (price|cost)|magkano.*creative|classic (price|cost)|premium (price|cost))\b/u', $text)) {
        return "🎨 Creative Shoot Packages:\n\n" .
            "• Classic: ₱2,000 — 1 hour, up to 5 pax\n" .
            "• Premium: ₱3,500 — 3 hours, up to 10 pax\n\n" .
            "Includes professional photographer, complete lighting, and enhanced photos. 😊";
    }

    if (preg_match('/\b(how much.*family|family (price|cost)|magkano.*family)\b/u', $text)) {
        return "👨‍👩‍👧‍👦 Family Packages:\n\n" .
            "• 3 Pax: ₱2,500 — 45 mins\n" .
            "• 4–6 Pax: ₱3,500 — 1 hr 5 mins\n" .
            "• 7–10 Pax: ₱4,500 — 1 hr 25 mins\n\n" .
            "Includes photographer, self-shoot time, frame, and prints. 😊";
    }

    if (preg_match('/\b(how much.*rent|rental (price|cost)|magkano.*rent)\b/u', $text)) {
        return "🏢 Studio Rental Packages:\n\n" .
            "• Basic: ₱1,500 — 2 hours\n" .
            "• Half Day: ₱3,000 — 6 hours\n\n" .
            "You can bring your own photographer, or hire in-house for ₱500 (Basic) or ₱1,500 (Half Day). 😊";
    }

    if (preg_match('/\b(how (do|can) i pay|paano magbayad|payment method|what payment|gcash number|payment options)\b/u', $text)) {
        return "💳 Studio 94 accepts GCash payments only.\n\n" .
            "How to pay:\n" .
            "1. Choose your package and book a schedule\n" .
            "2. Scan the GCash QR or send to our GCash number\n" .
            "3. Submit your 13-digit GCash reference number and receipt\n" .
            "4. Wait for staff verification\n\n" .
            "A ₱100 reservation fee is required to confirm your booking. 😊";
    }

    if (preg_match('/\b(how much.*deposit|deposit (amount|price)|magkano.*deposit|down ?payment|reservation fee)\b/u', $text)) {
        return "💳 Reservation Fee:\n\n" .
            "• A flat ₱100 reservation fee is required to confirm your booking.\n" .
            "• Remaining balance is settled on the shoot day.\n\n" .
            "Example: If your package is ₱2,000, you pay ₱100 to reserve, then ₱1,900 on shoot day. 😊";
    }

    if (preg_match('/\b(cancellation policy|refund policy|cancel.*refund|paano.*cancel|may refund ba)\b/u', $text)) {
        return "📋 Studio 94 Cancellation Policy:\n\n" .
            "• All payments are non-refundable.\n" .
            "• The ₱100 reservation fee is forfeited upon cancellation.\n" .
            "• Please make sure of your schedule before booking.\n\n" .
            "To cancel, please contact Studio 94 staff. 😊";
    }

    if (preg_match('/\b(how (do|can) i book|paano mag[- ]?book|how to (make|place) a booking|booking process|i want to book)\b/u', $text)) {
        return "📅 How to Book:\n\n" .
            "1. Go to the booking page\n" .
            "2. Choose a package\n" .
            "3. Select an available date and time\n" .
            "4. Fill in your booking details\n" .
            "5. Pay the ₱100 reservation fee via GCash\n" .
            "6. Submit your GCash reference and receipt\n" .
            "7. Wait for staff verification\n\n" .
            "You'll receive a notification once confirmed. 😊";
    }

    if (preg_match('/\b(where.*(photos|pictures)|how.*download.*(photos|pictures)|saan.*(litrato|kuha)|photo gallery)\b/u', $text)) {
        return "📸 Photos & Gallery:\n\n" .
            "• Completed photos are uploaded to your Studio 94 photo gallery.\n" .
            "• You can view and download them from the gallery section.\n\n" .
            "You'll receive a notification when your photos are ready. 😊";
    }

    if (preg_match('/\b(how.*(submit|leave) feedback|paano.*feedback|feedback form)\b/u', $text)) {
        return "⭐ Feedback:\n\n" .
            "After your session, you can submit feedback including:\n" .
            "• Rating (1–5 stars)\n" .
            "• Comments\n\n" .
            "Thank you for helping us improve! 😊";
    }

    if (preg_match('/\b(who are you|what are you|sino ka|ano ka|are you (a )?(bot|ai|human)|snapbot)\b/u', $text)) {
        return "🤖 I'm SnapBot AI, Studio 94's virtual assistant.\n\n" .
            "I can help you with:\n" .
            "• 📸 Packages & prices\n" .
            "• 📅 Booking & availability\n" .
            "• 💳 Payments\n" .
            "• 📍 Location & hours\n" .
            "• 📋 Policies\n\n" .
            "How can I help you today? 😊";
    }

    if (preg_match('/^(thank(s| you)?|salamat|ty|tnx)[\s!?.]*$/iu', $text)) {
        return "You're welcome! 😊\n\nIf you have more questions, just ask. Salamat at welcome sa Studio 94! 📸";
    }

    if (preg_match('/^(bye|goodbye|paalam|see you|hanggang)[\s!?.]*$/iu', $text)) {
        return "Paalam! 👋\n\nSalamat sa pagbisita sa Studio 94! Balik ka ulit ha? 📸✨";
    }

    return null;
}

// ============================================================
// GEMINI API CALL — Optimized for gemini-3.6-flash
// NOTE: gemini-3.6-flash does NOT support temperature/topP/topK
// ============================================================

function callGemini(string $apiKey, string $message, array $models): array
{
    $systemPrompt = "You are SnapBot AI, the customer support assistant for Studio 94 Photography Studio in Nabua, Camarines Sur, Philippines.\n\n" .
        "STRICT RULES:\n" .
        "1. Answer ONLY Studio 94-related questions.\n" .
        "2. Always respond in ENGLISH.\n" .
        "3. Use simple English that is easy to understand.\n" .
        "4. Use emojis sparingly (max 2 per reply).\n" .
        "5. DO NOT use Markdown, asterisks, backticks, or bold formatting.\n" .
        "6. Do not invent prices, payment methods, or policies.\n" .
        "7. Format with clear line breaks and use the bullet character •.\n" .
        "8. IMPORTANT: Always COMPLETE your answer. Never cut off mid-sentence.\n" .
        "9. Keep answers concise but complete (2-6 short sentences).\n" .
        "10. For opinion/comparison/recommendation questions, give a helpful answer based on Studio 94 info.\n" .
        "11. If the question is off-topic (not about Studio 94), politely decline and offer Studio 94-related help.\n\n" .
        "STUDIO 94 INFO:\n" .
        "Location: 2F SBD Building Highway 1, San Isidro Poblacion, Nabua, Camarines Sur\n" .
        "Hours: Monday to Sunday, 10:00 AM – 7:00 PM\n" .
        "Payment: GCash only. A flat ₱100 reservation fee is required to confirm booking.\n" .
        "Remaining balance is paid on shoot day.\n\n" .
        "Packages:\n" .
        "• Self-Shoot: ₱249–₱1,199 (solo, budget-friendly, DIY)\n" .
        "• Creative Shoot: ₱2,000–₱3,500 (professional photographer, artistic)\n" .
        "• Family: ₱2,500–₱4,500 (for groups, with photographer + self-shoot time)\n" .
        "• Studio Rental: ₱1,500–₱3,000 (bring your own photographer)\n\n" .
        "Cancellation Policy: All payments are non-refundable. The ₱100 reservation fee is forfeited upon cancellation.\n\n" .
        "Why choose Studio 94:\n" .
        "• Budget-friendly packages (start at ₱249)\n" .
        "• Private self-shoot experience\n" .
        "• Complete studio setup (lighting, backdrops, props)\n" .
        "• Open every day 10 AM – 7 PM\n" .
        "• Convenient location in Nabua, Camarines Sur";

    // ============================================================
    // PAYLOAD — Compatible with gemini-3.6-flash
    // ⚠️ Removed temperature, topP, topK (NOT supported by 3.6-flash)
    // ============================================================
    $payload = [
        'system_instruction' => [
            'parts' => [['text' => $systemPrompt]]
        ],
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $message]]]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 2000,   // ✅ Higher limit para kumpleto ang sagot
        ],
    ];

    $lastError = '';
    $lastHttp  = 0;

    foreach ($models as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $lastError = 'cURL: ' . $curlError;
            $lastHttp  = 0;
            error_log("SnapBot [$model] cURL error: $curlError");
            continue;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errResult = json_decode($response, true);
            $errMsg    = $errResult['error']['message'] ?? 'Unknown error';
            $lastError = "HTTP $httpCode: $errMsg";
            $lastHttp  = $httpCode;
            error_log("SnapBot [$model] HTTP $httpCode: $errMsg");
            continue;
        }

        $result       = json_decode($response, true);
        $reply        = '';
        $finishReason = $result['candidates'][0]['finishReason'] ?? '';

        if (isset($result['candidates'][0]['content']['parts'])) {
            foreach ($result['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $reply .= $part['text'];
                }
            }
        }

        if (trim($reply) === '') {
            $lastError = 'Empty response';
            continue;
        }

        // Warning kung na-truncate
        if ($finishReason === 'MAX_TOKENS') {
            error_log("SnapBot [$model] Warning: response truncated (MAX_TOKENS). Increase maxOutputTokens.");
        }

        return [
            'success'       => true,
            'reply'         => $reply,
            'model'         => $model,
            'error'         => '',
            'http'          => $httpCode,
            'finish_reason' => $finishReason,
        ];
    }

    return [
        'success' => false,
        'reply'   => '',
        'model'   => '',
        'error'   => $lastError,
        'http'    => $lastHttp,
    ];
}

// ============================================================
// STEP 1: AVAILABILITY
// ============================================================

if (isAvailabilityQuestion($text)) {

    $reqDate = parseRequestedDate($text);

    if ($reqDate === null) {
        $reply = "📅 Which date would you like to check?\n\n" .
            "Examples:\n" .
            "• Is tomorrow available?\n" .
            "• Available ba sa Saturday?\n" .
            "• May slot ba sa September 26?\n" .
            "• Available ba sa 2026-09-30?";
        saveChatLog($pdo, $message, $reply);
        chatbotReply($reply, ['type' => 'availability_prompt']);
    }

    if (!$pdo instanceof PDO) {
        $reply = "📅 Sorry, I can't check live availability right now.\n\n" .
            "Please try again later or contact Studio 94 staff.";
        saveChatLog($pdo, $message, $reply);
        chatbotReply($reply, ['type' => 'availability_error']);
    }

    try {
        $reqTime  = parseRequestedTime($text);
        $bookings = getBookingsForDate($pdo, $reqDate);
        $response = buildAvailabilityResponse($reqDate, $bookings, $reqTime);

        saveChatLog($pdo, $message, $response);
        chatbotReply($response, [
            'type' => 'availability',
            'date' => $reqDate->format('Y-m-d'),
        ]);
    } catch (Throwable $e) {
        error_log('SnapBot availability error: ' . $e->getMessage());
        $reply = "📅 Sorry, I can't verify the live schedule right now.\n\n" .
            "Please contact Studio 94 staff to confirm availability.";
        saveChatLog($pdo, $message, $reply);
        chatbotReply($reply, ['type' => 'availability_error']);
    }
}

// ============================================================
// STEP 2: FAQ
// ============================================================

$faqReply = answerFAQ($text);
if ($faqReply !== null) {
    saveChatLog($pdo, $message, $faqReply);
    chatbotReply($faqReply, ['type' => 'faq']);
}

// ============================================================
// STEP 3: GEMINI FALLBACK
// ============================================================

$apiKey = defined('GEMINI_API_KEY') ? trim((string)GEMINI_API_KEY) : '';

$apiKeyValid = (
    strpos($apiKey, 'AIza') === 0 ||
    strpos($apiKey, 'AQ.') === 0 ||
    strlen($apiKey) > 20
);

if ($apiKey === '' || !$apiKeyValid) {
    $reply = "😊 I can help with Studio 94 packages, prices, location, hours, booking, availability, payments, cancellation policy, photos, and feedback.\n\n" .
        "Please ask me a Studio 94-related question. 😊";
    saveChatLog($pdo, $message, $reply);
    chatbotReply($reply, ['type' => 'fallback']);
}

// ============================================================
// MODEL FALLBACK CHAIN
// Primary: gemini-3.6-flash (from config.php)
// Fallbacks: 2.5-flash, 2.0-flash, 1.5-flash
// ============================================================
$modelsToTry = [
    'gemini-3.6-flash',      // ✅ Primary (from config)
    'gemini-2.5-flash',      // Fallback 1
    'gemini-2.0-flash',      // Fallback 2
    'gemini-1.5-flash',      // Fallback 3
];

if (defined('GEMINI_MODEL') && !in_array(GEMINI_MODEL, $modelsToTry)) {
    array_unshift($modelsToTry, GEMINI_MODEL);
}

$geminiResult = callGemini($apiKey, $message, $modelsToTry);

if ($geminiResult['success']) {
    saveChatLog($pdo, $message, $geminiResult['reply']);
    chatbotReply(
        cleanBotText($geminiResult['reply']),
        [
            'type'  => 'ai',
            'model' => $geminiResult['model'],
            'finish_reason' => $geminiResult['finish_reason'] ?? 'STOP',
        ]
    );
}

error_log('SnapBot: All Gemini models failed. Last error: ' . $geminiResult['error']);

$reply = "😊 Sorry, the AI assistant is not available right now.\n\n" .
    "But I can still help with:\n" .
    "• 📸 Packages & prices\n" .
    "• 📅 Availability\n" .
    "• 💳 Payments\n" .
    "• 📍 Location & hours\n" .
    "• 📋 Policies\n\n" .
    "Just ask! 😊";
saveChatLog($pdo, $message, $reply);
chatbotReply($reply, ['type' => 'fallback_error']);