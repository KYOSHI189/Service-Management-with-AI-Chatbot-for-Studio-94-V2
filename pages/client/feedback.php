<?php
requireRole('client');
$pdo = db();
$uid = $user['id'];

// ================================================================
// REQUIRE AI HELPER
// ================================================================
require_once __DIR__ . '/../../ai_helper.php';

// ================================================================
// FALLBACK SENTIMENT (local)
// ================================================================
function analyzeSentimentLocal($comment) {
    $positiveWords = ['ganda', 'satisfied', 'perfect', 'magaling', 'sulit', 'maayos', 'okay', 'maganda', 'galing', 'bilib', 'wow', 'nice', 'great', 'excellent', 'amazing', 'beautiful', 'love', 'happy', 'thank', 'salamat'];
    $negativeWords = ['bagal', 'pangit', 'mahal', 'nawala', 'delay', 'problema', 'tagal', 'panget', 'sira', 'basura', 'disappoint', 'sad', 'bad', 'poor', 'terrible', 'worst', 'hindi maganda'];
    
    $text = strtolower($comment);
    $score = 0;
    foreach ($positiveWords as $w) if (strpos($text, $w) !== false) $score++;
    foreach ($negativeWords as $w) if (strpos($text, $w) !== false) $score--;
    
    if ($score > 0) return 'positive';
    if ($score < 0) return 'negative';
    return 'neutral';
}

// ================================================================
// FALLBACK TOPIC DETECTION (local)
// ================================================================
function detectTopicsLocal($comment) {
    $topics = [];
    $text = strtolower($comment);
    
    if (preg_match('/\b(kuha|shot|shots|photographer|camera|lens|pose|angle|photography|shoot)\b/', $text)) $topics[] = 'photographer';
    if (preg_match('/\b(edit|editing|retouch|ganda|maganda|pangit|panget|sira|blur|linaw|clear|sharp)\b/', $text)) $topics[] = 'quality';
    if (preg_match('/\b(print|printed|picture|photo|larawan|litrato|canvas|frame|album)\b/', $text)) $topics[] = 'print';
    if (preg_match('/\b(staff|crew|assistant|reception|employee|team|maayos|mabait|magalang|friendly|helpful)\b/', $text)) $topics[] = 'staff';
    if (preg_match('/\b(delivery|tagal|delay|late|mabilis|agad|release|send|receive|waiting|hintay)\b/', $text)) $topics[] = 'delivery';
    if (preg_match('/\b(price|mahal|mura|sulit|worth|budget|cost|presyo|bayad|payment)\b/', $text)) $topics[] = 'pricing';
    if (preg_match('/\b(studio|setup|props|backdrop|lighting|place|location|space|room|facility)\b/', $text)) $topics[] = 'studio';
    
    $topics = array_unique($topics);
    if (empty($topics)) $topics[] = 'general';
    return $topics;
}

// ================================================================
// SERVICE CLASSIFICATION
// ================================================================
function classifyFeedbackByService($pkgName) {
    $pkgName = strtolower(trim($pkgName ?? ''));
    
    if (strpos($pkgName, 'self') !== false || strpos($pkgName, 'package a') !== false 
        || strpos($pkgName, 'package b') !== false || strpos($pkgName, 'package c') !== false
        || strpos($pkgName, 'package d') !== false || strpos($pkgName, 'package e') !== false
        || strpos($pkgName, 'package f') !== false) {
        return ['key' => 'self-shoot', 'label' => 'Self-Shoot', 'icon' => '🤳', 'color' => '#D4A0A0'];
    }
    
    if (strpos($pkgName, 'creative') !== false || strpos($pkgName, 'classic') !== false 
        || strpos($pkgName, 'premium') !== false) {
        return ['key' => 'creative', 'label' => 'Creative Shoot', 'icon' => '🎨', 'color' => '#D4C4A0'];
    }
    
    if (strpos($pkgName, 'family') !== false || strpos($pkgName, 'pax') !== false) {
        return ['key' => 'family', 'label' => 'Family', 'icon' => '👨‍👩‍👧', 'color' => '#A0C4A0'];
    }
    
    if (strpos($pkgName, 'studio') !== false || strpos($pkgName, 'rental') !== false 
        || strpos($pkgName, 'half') !== false || strpos($pkgName, 'basic') !== false) {
        return ['key' => 'studio-rental', 'label' => 'Studio Rental', 'icon' => '🏢', 'color' => '#B0C4DE'];
    }
    
    return ['key' => 'other', 'label' => 'Other Services', 'icon' => '📷', 'color' => '#8B8177'];
}

// ================================================================
// TOPIC HELPERS
// ================================================================
function getTopicIcon($topic) {
    $icons = ['photographer'=>'📸','delivery'=>'📦','pricing'=>'💰','studio'=>'🏢','staff'=>'👥','quality'=>'🎨','print'=>'🖼️','general'=>'💬','service'=>'🛎️','facility'=>'🏢','other'=>'💬'];
    return $icons[trim($topic)] ?? '💬';
}

function getTopicLabel($topic) {
    $labels = ['photographer'=>'Photographer','delivery'=>'Delivery','pricing'=>'Pricing','studio'=>'Studio','staff'=>'Staff','quality'=>'Quality','print'=>'Print','general'=>'General','service'=>'Service','facility'=>'Facility','other'=>'Other'];
    return $labels[trim($topic)] ?? ucfirst($topic);
}

function getTopicColor($topic) {
    $colors = ['photographer'=>'#8B5CF6','delivery'=>'#F59E0B','pricing'=>'#10B981','studio'=>'#3B82F6','staff'=>'#EC4899','quality'=>'#EF4444','print'=>'#8B5CF6','general'=>'#6B7280','service'=>'#0EA5E9','facility'=>'#6366F1','other'=>'#6B7280'];
    return $colors[trim($topic)] ?? '#6B7280';
}

// ================================================================
// CHECK FEEDBACK TABLE COLUMNS (once, before POST handling)
// ================================================================
$hasAICol = false;
$hasTopics = false;
$hasReplyFeature = false;
$hasBookingIdCol = false;

try {
    $checkAI = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'ai_sentiment'");
    $hasAICol = $checkAI->fetch() ? true : false;
} catch (PDOException $e) { $hasAICol = false; }

try {
    $checkTopics = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'topics'");
    $hasTopics = $checkTopics->fetch() ? true : false;
} catch (PDOException $e) { $hasTopics = false; }

try {
    $checkReply = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'reply_sent_at'");
    $hasReplyFeature = $checkReply->fetch() ? true : false;
} catch (PDOException $e) { $hasReplyFeature = false; }

try {
    $checkBID = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'booking_id'");
    $hasBookingIdCol = $checkBID->fetch() ? true : false;
} catch (PDOException $e) { $hasBookingIdCol = false; }

// ================================================================
// HANDLE FEEDBACK SUBMISSION (with AI)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $rating  = cleanInt($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');
    $booking_id = cleanInt($_POST['booking_id'] ?? 0);

    // ============================================================
    // ✅ VALIDATION 1: rating at comment
    // ============================================================
    $errors = [];

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please provide a valid rating (1-5).';
    }
    if (empty($comment)) {
        $errors[] = 'Please write a comment.';
    } elseif (mb_strlen($comment) < 5) {
        $errors[] = 'Comment must be at least 5 characters.';
    } elseif (mb_strlen($comment) > 2000) {
        $errors[] = 'Comment is too long (max 2000 characters).';
    }

    // ============================================================
    // ✅ VALIDATION 2: booking_id (SECURITY FIX)
    // ============================================================
    if ($hasBookingIdCol) {
        if ($booking_id <= 0) {
            $errors[] = 'Please select a completed session.';
        } else {
            $chk = $pdo->prepare("
                SELECT b.id FROM bookings b
                WHERE b.id = ? AND b.user_id = ? AND b.status = 'Completed'
                LIMIT 1
            ");
            $chk->execute([$booking_id, $uid]);

            if (!$chk->fetch()) {
                $errors[] = 'Invalid session. You can only submit feedback for your completed sessions.';
            } else {
                // ✅ VALIDATION 3: duplicate feedback check
                $dupCheck = $pdo->prepare("
                    SELECT id FROM feedback 
                    WHERE user_id = ? AND booking_id = ? 
                    LIMIT 1
                ");
                $dupCheck->execute([$uid, $booking_id]);

                if ($dupCheck->fetch()) {
                    $errors[] = 'You have already submitted feedback for this session.';
                }
            }
        }
    }

    // ============================================================
    // IF VALID, PROCEED WITH INSERT
    // ============================================================
    if (empty($errors)) {
        // ===== CALL AI =====
        $aiResult = null;
        if (function_exists('analyzeFeedbackWithAI')) {
            try {
                $aiResult = analyzeFeedbackWithAI($comment);
            } catch (Throwable $e) {
                error_log('AI analysis error: ' . $e->getMessage());
                $aiResult = null;
            }
        }
        
        if ($aiResult) {
            $sentiment  = $aiResult['sentiment'] ?? 'neutral';
            $aiCategory = $aiResult['category'] ?? null;
            $aiSummary  = $aiResult['summary'] ?? null;
            $aiReply    = $aiResult['suggested_reply'] ?? null;
        } else {
            $sentiment  = analyzeSentimentLocal($comment);
            $aiCategory = null;
            $aiSummary  = null;
            $aiReply    = null;
        }
        
        $urgent = ($rating <= 2 && $sentiment === 'negative') ? 1 : 0;
        $topicsArray = detectTopicsLocal($comment);
        $topicsString = implode(',', $topicsArray);
        
        $ratingStaff = null;
        $ratingQuality = null;
        $ratingTimeliness = null;
        
        // ============================================================
        // BUILD INSERT DYNAMICALLY BASE SA EXISTING COLUMNS
        // ============================================================
        try {
            if ($hasAICol && $hasBookingIdCol && $hasTopics) {
                $stmt = $pdo->prepare("INSERT INTO feedback (
                    user_id, booking_id, rating_overall, rating_staff, rating_quality, rating_timeliness,
                    comment, sentiment, topics, is_urgent,
                    ai_sentiment, ai_category, ai_summary, ai_suggested_reply, ai_analyzed_at,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $uid, $booking_id, $rating, $ratingStaff, $ratingQuality, $ratingTimeliness,
                    $comment, $sentiment, $topicsString, $urgent,
                    $sentiment, $aiCategory, $aiSummary, $aiReply
                ]);
            } elseif ($hasAICol && $hasBookingIdCol) {
                $stmt = $pdo->prepare("INSERT INTO feedback (
                    user_id, booking_id, rating_overall, rating_staff, rating_quality, rating_timeliness,
                    comment, sentiment, is_urgent,
                    ai_sentiment, ai_category, ai_summary, ai_suggested_reply, ai_analyzed_at,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $uid, $booking_id, $rating, $ratingStaff, $ratingQuality, $ratingTimeliness,
                    $comment, $sentiment, $urgent,
                    $sentiment, $aiCategory, $aiSummary, $aiReply
                ]);
            } elseif ($hasAICol) {
                $stmt = $pdo->prepare("INSERT INTO feedback (
                    user_id, rating_overall, rating_staff, rating_quality, rating_timeliness,
                    comment, sentiment, is_urgent,
                    ai_sentiment, ai_category, ai_summary, ai_suggested_reply, ai_analyzed_at,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $uid, $rating, $ratingStaff, $ratingQuality, $ratingTimeliness,
                    $comment, $sentiment, $urgent,
                    $sentiment, $aiCategory, $aiSummary, $aiReply
                ]);
            } elseif ($hasBookingIdCol) {
                $stmt = $pdo->prepare("INSERT INTO feedback (
                    user_id, booking_id, rating_overall, rating_staff, rating_quality, rating_timeliness,
                    comment, sentiment, topics, is_urgent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $uid, $booking_id, $rating, $ratingStaff, $ratingQuality, $ratingTimeliness,
                    $comment, $sentiment, $topicsString, $urgent
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO feedback (
                    user_id, rating_overall, rating_staff, rating_quality, rating_timeliness,
                    comment, sentiment, topics, is_urgent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $uid, $rating, $ratingStaff, $ratingQuality, $ratingTimeliness,
                    $comment, $sentiment, $topicsString, $urgent
                ]);
            }
            
            if ($urgent) {
                addNotificationByRole('admin', '⚠️ Urgent Feedback', 
                    $user['name'] . ' submitted urgent feedback: "' . mb_substr($comment, 0, 50) . '..."', 
                    'feedback', '⚠️');
                addNotificationByRole('staff', '⚠️ Urgent Feedback', 
                    $user['name'] . ' submitted urgent feedback: "' . mb_substr($comment, 0, 50) . '..."', 
                    'feedback', '⚠️');
            }
            
            setFlash('success', 'Thank you for your feedback! 🤖 AI analyzed your review.');
            
        } catch (Throwable $e) {
            error_log('Feedback insert error: ' . $e->getMessage());
            setFlash('error', 'Something went wrong. Please try again.');
        }
    } else {
        setFlash('error', implode(' ', $errors));
    }
    
    header('Location: index.php?page=feedback');
    exit;
}

// ================================================================
// GET COMPLETED BOOKINGS (excluding those with existing feedback)
// ================================================================
$bookings = $pdo->prepare("
    SELECT b.id, b.booking_ref, p.name as pkg_name, pk.name as main_pkg_name, b.date 
    FROM bookings b 
    JOIN packages p ON b.package_id = p.id
    LEFT JOIN packages pk ON p.parent_id = pk.id
    WHERE b.user_id = ? AND b.status = 'Completed' 
      AND b.id NOT IN (
          SELECT COALESCE(booking_id, 0) FROM feedback WHERE user_id = ?
      )
    ORDER BY b.date DESC
");
$bookings->execute([$uid, $uid]);
$completedBookings = $bookings->fetchAll();

// ================================================================
// GET MY FEEDBACK (with limit para hindi bumagal)
// ================================================================
$myFeedback = $pdo->prepare("
    SELECT f.*, 
           r.name AS replied_by_name,
           p.name AS pkg_name,
           pk.name AS main_pkg_name
    FROM feedback f 
    LEFT JOIN users r ON f.replied_by = r.id
    LEFT JOIN bookings b ON f.booking_id = b.id
    LEFT JOIN packages p ON b.package_id = p.id
    LEFT JOIN packages pk ON p.parent_id = pk.id
    WHERE f.user_id = ? 
    ORDER BY f.created_at DESC
    LIMIT 100
");
$myFeedback->execute([$uid]);
$feedbacks = $myFeedback->fetchAll();

// ================================================================
// GROUP FEEDBACK BY SERVICE
// ================================================================
$feedbackByService = [
    'self-shoot' => [],
    'creative' => [],
    'family' => [],
    'studio-rental' => [],
    'other' => [],
];

foreach ($feedbacks as $f) {
    $service = classifyFeedbackByService($f['main_pkg_name'] ?? $f['pkg_name'] ?? '');
    $feedbackByService[$service['key']][] = array_merge($f, ['_service' => $service]);
}

$serviceCounts = [
    'all' => count($feedbacks),
    'self-shoot' => count($feedbackByService['self-shoot']),
    'creative' => count($feedbackByService['creative']),
    'family' => count($feedbackByService['family']),
    'studio-rental' => count($feedbackByService['studio-rental']),
    'other' => count($feedbackByService['other']),
];

$activeFilter = $_GET['service'] ?? 'all';
if (!array_key_exists($activeFilter, $serviceCounts)) {
    $activeFilter = 'all';
}
?>

<!-- ============================================================ -->
<!-- FULLY RESPONSIVE STYLES                                       -->
<!-- ============================================================ -->
<style>
/* ============================================================
   BASE (MOBILE-FIRST) — 320px pataas
   ============================================================ */

.page-banner {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    background: linear-gradient(135deg, #2C2C2C, #4A4A4A);
    color: #fff;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 14px;
    width: 100%;
    box-sizing: border-box;
}
.page-banner-text { width: 100%; min-width: 0; }
.page-banner .eyebrow {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    opacity: 0.6;
    margin-bottom: 6px;
}
.page-banner h2 {
    font-size: 18px;
    margin: 0 0 6px;
    line-height: 1.25;
    word-break: break-word;
}
.page-banner p {
    font-size: 12px;
    opacity: 0.8;
    margin: 0;
    line-height: 1.4;
}
.page-banner-art {
    font-size: 32px;
    align-self: flex-end;
}

/* AI INFO BADGE */
.ai-info-badge {
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 14px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    box-sizing: border-box;
    min-width: 0;
}
.ai-info-badge .ai-icon { font-size: 22px; flex-shrink: 0; }
.ai-info-badge .ai-body { min-width: 0; flex: 1; }
.ai-info-badge .ai-title {
    color: #3730a3;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.3;
    word-break: break-word;
}
.ai-info-badge .ai-desc {
    font-size: 11px;
    color: #4338ca;
    margin-top: 3px;
    line-height: 1.4;
    word-break: break-word;
}

/* CARD */
.card {
    padding: 14px;
    border-radius: 12px;
    box-sizing: border-box;
    min-width: 0;
}
.card-title {
    font-size: 14px;
    margin-bottom: 12px;
    font-weight: 700;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.section-header h3 {
    font-size: 15px;
    margin: 0;
    word-break: break-word;
}
.section-header .count-text {
    font-size: 12px;
    color: var(--muted);
}

/* FORM GROUP */
.form-group { margin-bottom: 14px; }
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
}
.form-group select,
.form-group textarea,
.form-group input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    font-family: inherit;
    background: #fff;
}
.form-group textarea { resize: vertical; min-height: 90px; }

.form-hint {
    font-size: 11px;
    color: var(--muted);
    margin-top: 4px;
    line-height: 1.4;
}
.form-hint a { color: var(--accent, #6C63FF); }

/* ============================================================
   STAR RATING
   ============================================================ */
.star-rating-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 16px 12px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
    box-sizing: border-box;
}

.star-rating-container {
    display: flex;
    flex-direction: row-reverse;
    gap: 6px;
    justify-content: center;
}

.star-label {
    cursor: pointer;
    color: #d1d5db;
    transition: all 0.2s ease;
    font-size: 36px;
    line-height: 1;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.rating-label-box {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    padding: 8px 14px;
    background: white;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    width: 100%;
    box-sizing: border-box;
    word-break: break-word;
}

/* Hover effect para sa desktop lang */
@media (hover: hover) and (pointer: fine) {
    .star-label:hover {
        color: #F59E0B;
        text-shadow: 0 0 20px rgba(245, 158, 11, 0.3);
    }
}

/* ============================================================
   TOPIC PREVIEW
   ============================================================ */
.topic-preview {
    display: none;
    padding: 12px;
    background: var(--bg-soft);
    border-radius: 8px;
    margin-bottom: 14px;
}
.topic-preview .tp-label {
    font-size: 11px;
    color: var(--muted);
    margin-bottom: 6px;
}
.topic-preview .tp-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.topic-preview .tp-sentiment {
    font-size: 11px;
    color: var(--muted);
    margin-top: 6px;
}

/* ============================================================
   SERVICE FILTER
   ============================================================ */
.service-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}
.service-filter-btn {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    background: #ECECEC;
    color: #6B6359;
    border: 1px solid #E0E0E0;
    white-space: nowrap;
    box-sizing: border-box;
}
.service-filter-btn.active {
    background: #4A453F;
    color: #F5F3F0;
    border-color: #4A453F;
}
.service-filter-btn:hover {
    transform: translateY(-1px);
}

/* ============================================================
   FEEDBACK ITEM CARD
   ============================================================ */
.feedback-item {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 12px;
    box-sizing: border-box;
    min-width: 0;
}

.feedback-item-header {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.feedback-item-header .fih-left { min-width: 0; flex: 1; }
.feedback-item-header .fih-date {
    font-size: 11px;
    color: var(--muted);
    text-align: left;
}

.service-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
    white-space: nowrap;
}

.pkg-name-inline {
    font-size: 11px;
    color: var(--muted);
    margin-left: 6px;
    word-break: break-word;
}

.feedback-stars {
    font-size: 18px;
    color: var(--amber-text);
    line-height: 1;
    margin-bottom: 6px;
}

.feedback-comment {
    font-size: 13px;
    color: var(--dark);
    line-height: 1.5;
    word-break: break-word;
}

.feedback-topics {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.feedback-topic-tag {
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 10px;
    white-space: nowrap;
}

/* ============================================================
   STUDIO REPLY
   ============================================================ */
.studio-reply {
    margin-top: 14px;
    padding: 12px;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border-radius: 10px;
    border-left: 4px solid #2ECC71;
    box-sizing: border-box;
}
.studio-reply .sr-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 6px;
}
.studio-reply .sr-title {
    font-size: 11px;
    font-weight: 700;
    color: #166534;
    letter-spacing: 0.5px;
}
.studio-reply .sr-date {
    font-size: 10px;
    color: #166534;
}
.studio-reply .sr-message {
    font-size: 12px;
    color: #14532d;
    line-height: 1.6;
    word-break: break-word;
}
.studio-reply .sr-author {
    font-size: 10px;
    color: #166534;
    margin-top: 8px;
    font-style: italic;
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    color: var(--muted);
    padding: 24px 12px;
    font-size: 13px;
    line-height: 1.5;
}

/* SUBMIT BUTTON */
.btn-submit-feedback {
    width: 100%;
    padding: 12px;
    background: var(--dark, #0A0A0A);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    box-sizing: border-box;
}
.btn-submit-feedback:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.warn-text {
    font-size: 11px;
    color: var(--red-text);
    margin-top: 8px;
    line-height: 1.4;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (min-width: 380px) {
    .page-banner h2 { font-size: 19px; }
    .card-title { font-size: 15px; }
    .star-label { font-size: 42px; }
    .rating-label-box { font-size: 15px; }
    .feedback-comment { font-size: 14px; }
    .feedback-stars { font-size: 20px; }
}

@media (min-width: 600px) {
    .page-banner {
        flex-direction: row;
        align-items: center;
        padding: 20px 24px;
        border-radius: 14px;
    }
    .page-banner h2 { font-size: 22px; }
    .page-banner-art { font-size: 40px; align-self: center; }

    .ai-info-badge { padding: 14px 18px; }
    .ai-info-badge .ai-icon { font-size: 24px; }
    .ai-info-badge .ai-title { font-size: 14px; }
    .ai-info-badge .ai-desc { font-size: 12px; }

    .card { padding: 18px; }

    .star-rating-wrapper {
        flex-direction: row;
        justify-content: flex-start;
        gap: 20px;
        padding: 16px 20px;
    }
    .star-rating-container { gap: 10px; }
    .star-label { font-size: 44px; }
    .rating-label-box {
        width: auto;
        font-size: 16px;
        min-width: 200px;
    }

    .feedback-item-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }
    .feedback-item-header .fih-date {
        text-align: right;
        flex-shrink: 0;
    }
}

@media (min-width: 768px) {
    .page-banner { padding: 24px 28px; border-radius: 16px; gap: 20px; }
    .page-banner h2 { font-size: 24px; }
    .page-banner p { font-size: 13px; }
    .page-banner-art { font-size: 48px; }

    .card { padding: 20px 22px; border-radius: 14px; }
    .card-title { font-size: 16px; }
    .section-header h3 { font-size: 17px; }

    .star-label { font-size: 48px; }
    .rating-label-box { font-size: 18px; }

    .feedback-item { padding: 16px 18px; }
    .feedback-comment { font-size: 14px; }
    .feedback-stars { font-size: 22px; }
    .studio-reply .sr-message { font-size: 13px; }
}

@media (min-width: 1024px) {
    .page-banner { padding: 28px 32px; }
    .page-banner h2 { font-size: 26px; }
    .page-banner-art { font-size: 52px; }

    .card { padding: 22px 24px; }
    .feedback-item { padding: 18px 20px; }
    .feedback-stars { font-size: 24px; }
}

@media (min-width: 1440px) {
    .page-banner { padding: 32px 40px; border-radius: 18px; }
    .page-banner h2 { font-size: 30px; }
    .page-banner-art { font-size: 64px; }

    .card { padding: 26px 28px; }
    .star-label { font-size: 52px; }
    .feedback-comment { font-size: 15px; }
}

@media (min-width: 1920px) {
    .page-banner { padding: 36px 48px; }
    .page-banner h2 { font-size: 34px; }
    .card { padding: 30px 32px; }
}

@media (max-height: 500px) and (orientation: landscape) {
    .page-banner { padding: 12px 16px; }
    .page-banner h2 { font-size: 18px; }
    .page-banner-art { font-size: 32px; }
    .star-label { font-size: 32px; }
    .star-rating-wrapper { padding: 10px; }
}

@media print {
    .page-banner-art,
    .btn-submit-feedback,
    .service-filter,
    .topic-preview { display: none !important; }
    .card, .feedback-item { break-inside: avoid; border: 1px solid #ccc; }
}
</style>

<!-- ============================================================ -->
<!-- PAGE BANNER                                                   -->
<!-- ============================================================ -->
<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">Share Your Thoughts</div>
    <h2><strong>Feedback</strong></h2>
    <p>Your experience helps us improve our service.</p>
  </div>
  <div class="page-banner-art">⭐</div>
</div>

<!-- ============================================================ -->
<!-- AI INFO BADGE                                                 -->
<!-- ============================================================ -->
<div class="ai-info-badge">
  <span class="ai-icon">🤖</span>
  <div class="ai-body">
    <div class="ai-title">AI-Powered Feedback Analysis</div>
    <p class="ai-desc">Ang iyong feedback ay automatic na analyze ng Google Gemini AI at naka-classify base sa services ng studio.</p>
  </div>
</div>

<!-- ============================================================ -->
<!-- SUBMIT FEEDBACK FORM                                          -->
<!-- ============================================================ -->
<div class="card">
  <div class="card-title">📝 Submit Feedback</div>
  <form method="POST" action="index.php?page=feedback" id="feedback-form">
    <?= csrfField() ?>
    
    <!-- Select Booking -->
    <div class="form-group">
      <label>Select Session</label>
      <select name="booking_id" required <?= empty($completedBookings) ? 'disabled' : '' ?>>
        <option value="">Select a completed session…</option>
        <?php foreach ($completedBookings as $b): 
            $service = classifyFeedbackByService($b['main_pkg_name'] ?? $b['pkg_name'] ?? '');
        ?>
          <option value="<?= $b['id'] ?>">
            <?= $service['icon'] ?> <?= clean($b['pkg_name']) ?> - <?= formatDate($b['date']) ?> (<?= clean($b['booking_ref']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($completedBookings)): ?>
        <div class="form-hint">
          ⚠️ You need a completed session to submit feedback, or you have already submitted feedback for all your completed sessions.
          <a href="index.php?page=bookings">View your bookings</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Overall Rating -->
    <div class="form-group">
        <label style="font-size:14px;margin-bottom:8px;">⭐ Overall Rating</label>
        <div class="star-rating-wrapper">
            <div class="star-rating-container" id="starContainer">
                <input type="radio" name="rating" id="star5" value="5" style="display:none;" checked>
                <label for="star5" class="star-label" data-value="5" onclick="setRating(5)">★</label>
                
                <input type="radio" name="rating" id="star4" value="4" style="display:none;">
                <label for="star4" class="star-label" data-value="4" onclick="setRating(4)">★</label>
                
                <input type="radio" name="rating" id="star3" value="3" style="display:none;">
                <label for="star3" class="star-label" data-value="3" onclick="setRating(3)">★</label>
                
                <input type="radio" name="rating" id="star2" value="2" style="display:none;">
                <label for="star2" class="star-label" data-value="2" onclick="setRating(2)">★</label>
                
                <input type="radio" name="rating" id="star1" value="1" style="display:none;">
                <label for="star1" class="star-label" data-value="1" onclick="setRating(1)">★</label>
            </div>
            <div class="rating-label-box" id="rating-label">
                ⭐ 5 stars - Excellent 🤩
            </div>
        </div>
    </div>

    <!-- Comment -->
    <div class="form-group">
      <label>Your Comment <span style="color:var(--muted);font-weight:400;">(required)</span></label>
      <textarea name="comment" id="feedback-comment" rows="4" 
                maxlength="2000"
                placeholder="Share your experience… What did you like? What can we improve?" required></textarea>
      <div class="form-hint">
        💡 Tip: Be specific! Ang AI ay mag-a-analyze ng iyong feedback pagkatapos i-submit.
      </div>
    </div>

    <!-- Topic Preview (live) -->
    <div id="topic-preview" class="topic-preview">
      <div class="tp-label">🔍 Detected Topics (live preview):</div>
      <div id="topic-tags" class="tp-tags"></div>
      <div class="tp-sentiment" id="sentiment-preview"></div>
    </div>

    <button type="submit" class="btn-submit-feedback" <?= empty($completedBookings) ? 'disabled' : '' ?>>
      📤 Submit Feedback
    </button>
    <?php if (empty($completedBookings)): ?>
      <div class="warn-text">
        ⚠️ You need at least one completed session to submit feedback.
      </div>
    <?php endif; ?>
  </form>
</div>

<!-- ============================================================ -->
<!-- MY FEEDBACK HISTORY                                           -->
<!-- ============================================================ -->
<div class="card" style="margin-top:20px;">
  <div class="section-header">
    <h3><strong>📋 My Reviews</strong></h3>
    <span class="count-text"><?= count($feedbacks) ?> total reviews</span>
  </div>
  
  <?php if (empty($feedbacks)): ?>
    <p class="empty-state">No reviews yet. Submit your first feedback above.</p>
  <?php else: ?>
    
    <!-- SERVICE FILTER -->
    <div class="service-filter">
      <a href="index.php?page=feedback&service=all" 
         class="service-filter-btn <?= $activeFilter === 'all' ? 'active' : '' ?>">
        💬 All Services (<?= $serviceCounts['all'] ?>)
      </a>
      
      <?php foreach ([
        'self-shoot' => ['label' => 'Self-Shoot', 'icon' => '🤳'],
        'creative' => ['label' => 'Creative Shoot', 'icon' => '🎨'],
        'family' => ['label' => 'Family', 'icon' => '👨‍👩‍👧'],
        'studio-rental' => ['label' => 'Studio Rental', 'icon' => '🏢'],
        'other' => ['label' => 'Other', 'icon' => '📷'],
      ] as $key => $info): 
          if ($serviceCounts[$key] === 0) continue;
          $isActive = ($activeFilter === $key);
      ?>
        <a href="index.php?page=feedback&service=<?= $key ?>" 
           class="service-filter-btn <?= $isActive ? 'active' : '' ?>">
          <?= $info['icon'] ?> <?= $info['label'] ?> (<?= $serviceCounts[$key] ?>)
        </a>
      <?php endforeach; ?>
    </div>
    
    <!-- FEEDBACK LIST -->
    <?php 
    $displayFeedback = [];
    if ($activeFilter === 'all') {
        $displayFeedback = $feedbacks;
    } else {
        foreach ($feedbackByService[$activeFilter] ?? [] as $f) {
            $displayFeedback[] = $f;
        }
    }
    ?>
    
    <?php if (empty($displayFeedback)): ?>
      <p class="empty-state">No feedback for this service yet.</p>
    <?php else: ?>
      <?php foreach ($displayFeedback as $f): 
        $topics = [];
        if ($hasTopics && !empty($f['topics'])) {
            $topics = explode(',', $f['topics']);
        }
        $hasReply = $hasReplyFeature && !empty($f['reply_sent_at']);
        $service = $f['_service'] ?? classifyFeedbackByService($f['main_pkg_name'] ?? $f['pkg_name'] ?? '');
      ?>
      <div class="feedback-item">
        
        <!-- Feedback Header -->
        <div class="feedback-item-header">
          <div class="fih-left">
            <!-- SERVICE BADGE -->
            <div>
              <span class="service-badge" style="background:<?= $service['color'] ?>20;color:<?= $service['color'] ?>;border:1px solid <?= $service['color'] ?>40;">
                <?= $service['icon'] ?> <?= $service['label'] ?>
              </span>
              <?php if (!empty($f['pkg_name'])): ?>
                <span class="pkg-name-inline"><?= clean($f['pkg_name']) ?></span>
              <?php endif; ?>
            </div>
            
            <div class="feedback-stars">
              <?= str_repeat('★', (int)$f['rating_overall']) . str_repeat('☆', 5 - (int)$f['rating_overall']) ?>
            </div>
            <div class="feedback-comment"><?= nl2br(clean($f['comment'])) ?></div>
          </div>
          <div class="fih-date">
            <?= formatDate($f['created_at']) ?>
          </div>
        </div>
        
        <!-- Topics -->
        <?php if (!empty($topics)): ?>
        <div class="feedback-topics">
          <?php foreach ($topics as $topic): $topic = trim($topic); if (empty($topic)) continue; ?>
            <span class="feedback-topic-tag" style="background:<?= getTopicColor($topic) ?>20;color:<?= getTopicColor($topic) ?>;border:1px solid <?= getTopicColor($topic) ?>40;">
              <?= getTopicIcon($topic) ?> <?= getTopicLabel($topic) ?>
            </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- STUDIO REPLY -->
        <?php if ($hasReply): ?>
        <div class="studio-reply">
          <div class="sr-header">
            <div class="sr-title">💬 RESPONSE FROM STUDIO 94</div>
            <div class="sr-date"><?= formatDate($f['reply_sent_at']) ?></div>
          </div>
          <div class="sr-message">
            <?= nl2br(clean($f['reply_message'])) ?>
          </div>
          <?php if (!empty($f['replied_by_name'])): ?>
            <div class="sr-author">— <?= clean($f['replied_by_name']) ?>, Studio 94</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT                                                    -->
<!-- ============================================================ -->
<script>
// ================================================================
// STAR RATING SYSTEM (mobile-safe, walang hover JS)
// ================================================================
let selectedRating = 5;

function updateStars(count) {
    document.querySelectorAll('.star-label').forEach(label => {
        const value = parseInt(label.dataset.value);
        if (value <= count) {
            label.style.color = '#F59E0B';
            label.style.textShadow = '0 0 20px rgba(245, 158, 11, 0.3)';
        } else {
            label.style.color = '#d1d5db';
            label.style.textShadow = 'none';
        }
    });
}

function setRating(count) {
    selectedRating = count;
    document.querySelectorAll('input[name="rating"]').forEach(r => r.checked = false);
    const radio = document.getElementById('star' + count);
    if (radio) radio.checked = true;
    updateStars(count);
    const labels = ['', '⭐ 1 star - Terrible 😞', '⭐ 2 stars - Poor 😕', '⭐ 3 stars - Average 😐', '⭐ 4 stars - Good 😊', '⭐ 5 stars - Excellent 🤩'];
    document.getElementById('rating-label').textContent = labels[count] || '';
}

document.addEventListener('DOMContentLoaded', function() {
    updateStars(5);
    document.getElementById('rating-label').textContent = '⭐ 5 stars - Excellent 🤩';
});

// ================================================================
// LIVE TOPIC DETECTION
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('feedback-comment');
    const preview = document.getElementById('topic-preview');
    const tags = document.getElementById('topic-tags');
    const sentPrev = document.getElementById('sentiment-preview');
    
    if (!input) return;
    
    input.addEventListener('input', function() {
        const text = this.value.trim();
        if (text.length < 3) { preview.style.display = 'none'; return; }
        
        const topics = detectTopicsLive(text);
        const sentiment = analyzeSentimentLive(text);
        
        preview.style.display = 'block';
        tags.innerHTML = '';
        
        const colors = { photographer:'#8B5CF6', delivery:'#F59E0B', pricing:'#10B981', studio:'#3B82F6', staff:'#EC4899', quality:'#EF4444', print:'#8B5CF6', general:'#6B7280' };
        const icons = { photographer:'📸', delivery:'📦', pricing:'💰', studio:'🏢', staff:'👥', quality:'🎨', print:'🖼️', general:'💬' };
        const labels = { photographer:'Photographer', delivery:'Delivery', pricing:'Pricing', studio:'Studio', staff:'Staff', quality:'Quality', print:'Print', general:'General' };
        
        topics.forEach(topic => {
            const span = document.createElement('span');
            span.style.cssText = `padding:4px 12px;border-radius:50px;font-size:11px;background:${colors[topic]||'#6B7280'}20;color:${colors[topic]||'#6B7280'};border:1px solid ${colors[topic]||'#6B7280'}40;`;
            span.textContent = (icons[topic]||'💬') + ' ' + (labels[topic]||topic);
            tags.appendChild(span);
        });
        
        const emoji = sentiment === 'positive' ? '😊' : (sentiment === 'negative' ? '😞' : '😐');
        const color = sentiment === 'positive' ? 'var(--green-text)' : (sentiment === 'negative' ? 'var(--red-text)' : 'var(--amber-text)');
        sentPrev.innerHTML = `Sentiment (preview): <strong style="color:${color}">${emoji} ${sentiment.charAt(0).toUpperCase() + sentiment.slice(1)}</strong>`;
    });
});

function detectTopicsLive(text) {
    const t = [];
    const l = text.toLowerCase();
    if (l.includes('photographer')||l.includes('kuha')||l.includes('shot')||l.includes('camera')||l.includes('lens')) t.push('photographer');
    if (l.includes('edit')||l.includes('ganda')||l.includes('pangit')||l.includes('blur')||l.includes('linaw')) t.push('quality');
    if (l.includes('print')||l.includes('photo')||l.includes('picture')||l.includes('larawan')) t.push('print');
    if (l.includes('staff')||l.includes('crew')||l.includes('assistant')||l.includes('maayos')) t.push('staff');
    if (l.includes('delivery')||l.includes('tagal')||l.includes('delay')||l.includes('release')) t.push('delivery');
    if (l.includes('price')||l.includes('mahal')||l.includes('mura')||l.includes('sulit')) t.push('pricing');
    if (l.includes('studio')||l.includes('backdrop')||l.includes('lighting')||l.includes('props')) t.push('studio');
    const unique = [...new Set(t)];
    return unique.length > 0 ? unique : ['general'];
}

function analyzeSentimentLive(text) {
    const pos = ['ganda','satisfied','perfect','magaling','sulit','maayos','maganda','galing','nice','great','excellent','amazing','beautiful','love','thank'];
    const neg = ['bagal','pangit','mahal','delay','problema','tagal','panget','sira','disappoint','bad','poor','terrible','worst'];
    const l = text.toLowerCase();
    let score = 0;
    pos.forEach(w => { if (l.includes(w)) score++; });
    neg.forEach(w => { if (l.includes(w)) score--; });
    return score > 0 ? 'positive' : (score < 0 ? 'negative' : 'neutral');
}
</script>