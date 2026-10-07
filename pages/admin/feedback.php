<?php
// ============================================================
// ADMIN FEEDBACK - Client Reviews & AI Insights + Send Reply
// ============================================================
requireRole('admin');
$pdo = db();

// ================================================================
// REQUIRE AI HELPER
// ================================================================
require_once __DIR__ . '/../../ai_helper.php';

// ================================================================
// HANDLE ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $fid = cleanInt($_POST['feedback_id'] ?? 0);

    if ($fid > 0) {
        // ===== DELETE =====
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$fid]);
            setFlash('success', 'Feedback deleted.');

        // ===== RESOLVE =====
        } elseif ($action === 'resolve') {
            $pdo->prepare("UPDATE feedback SET is_urgent = 0 WHERE id = ?")->execute([$fid]);
            setFlash('success', 'Marked as resolved.');

        // ===== MARK URGENT =====
        } elseif ($action === 'mark_urgent') {
            $pdo->prepare("UPDATE feedback SET is_urgent = 1 WHERE id = ?")->execute([$fid]);
            setFlash('success', 'Marked as urgent.');

        // ===== RE-ANALYZE WITH AI =====
        } elseif ($action === 'reanalyze') {
            $stmt = $pdo->prepare("SELECT comment FROM feedback WHERE id = ?");
            $stmt->execute([$fid]);
            $fb = $stmt->fetch();
            
            if ($fb && !empty($fb['comment'])) {
                $aiResult = analyzeFeedbackWithAI($fb['comment']);
                if ($aiResult) {
                    $pdo->prepare("
                        UPDATE feedback 
                        SET ai_sentiment=?, ai_category=?, ai_summary=?, ai_suggested_reply=?, 
                            sentiment=?, ai_analyzed_at=NOW()
                        WHERE id=?
                    ")->execute([
                        $aiResult['sentiment'],
                        $aiResult['category'] ?? null,
                        $aiResult['summary'] ?? null,
                        $aiResult['suggested_reply'] ?? null,
                        $aiResult['sentiment'],
                        $fid
                    ]);
                    setFlash('success', '🤖 AI re-analysis complete.');
                } else {
                    setFlash('error', 'AI analysis failed. Try again.');
                }
            }

        // ===== SEND REPLY TO CLIENT =====
        } elseif ($action === 'send_reply') {
            $message = trim($_POST['reply_message'] ?? '');
            
            if ($message !== '') {
                // Get feedback + client info
                $fbStmt = $pdo->prepare("
                    SELECT f.*, u.id AS client_id, u.name AS client_name 
                    FROM feedback f 
                    JOIN users u ON f.user_id = u.id 
                    WHERE f.id = ?
                ");
                $fbStmt->execute([$fid]);
                $feedback = $fbStmt->fetch();
                
                if ($feedback) {
                    // Send notification to client
                    addNotification(
                        $feedback['client_id'],
                        '💬 Response to Your Feedback',
                        $message,
                        'feedback',
                        '💬'
                    );
                    
                    // Mark feedback as replied
                    $pdo->prepare("
                        UPDATE feedback 
                        SET reply_sent_at = NOW(), 
                            reply_message = ?, 
                            replied_by = ?
                        WHERE id = ?
                    ")->execute([$message, $user['id'], $fid]);
                    
                    setFlash('success', '✅ Reply sent to ' . $feedback['client_name'] . '.');
                } else {
                    setFlash('error', 'Feedback not found.');
                }
            } else {
                setFlash('error', 'Reply message cannot be empty.');
            }
        }
    }
    header('Location: index.php?page=feedback');
    exit;
}

// ================================================================
// TOPIC HELPER FUNCTIONS
// ================================================================
function getTopicIcon($topic) {
    $topic = trim($topic);
    $icons = [
        'photographer' => '📸', 'delivery' => '📦', 'pricing' => '💰',
        'studio' => '🏢', 'staff' => '👥', 'quality' => '🎨',
        'print' => '🖼️', 'general' => '💬', 'service' => '🛎️',
        'facility' => '🏢', 'other' => '💬'
    ];
    return $icons[$topic] ?? '💬';
}

function getTopicLabel($topic) {
    $topic = trim($topic);
    $labels = [
        'photographer' => 'Photographer', 'delivery' => 'Delivery', 'pricing' => 'Pricing',
        'studio' => 'Studio', 'staff' => 'Staff', 'quality' => 'Quality',
        'print' => 'Print', 'general' => 'General', 'service' => 'Service',
        'facility' => 'Facility', 'other' => 'Other'
    ];
    return $labels[$topic] ?? ucfirst($topic);
}

function getTopicColor($topic) {
    $topic = trim($topic);
    $colors = [
        'photographer' => '#8B5CF6', 'delivery' => '#F59E0B', 'pricing' => '#10B981',
        'studio' => '#3B82F6', 'staff' => '#EC4899', 'quality' => '#EF4444',
        'print' => '#8B5CF6', 'general' => '#6B7280', 'service' => '#0EA5E9',
        'facility' => '#6366F1', 'other' => '#6B7280'
    ];
    return $colors[$topic] ?? '#6B7280';
}

// ================================================================
// FALLBACK SENTIMENT (local)
// ================================================================
function analyzeSentimentLocal($comment, $rating = 0) {
    $text = strtolower(trim((string)$comment));
    if ($text === '') {
        if ($rating >= 4) return 'positive';
        if ($rating <= 2) return 'negative';
        return 'neutral';
    }
    $pos = ['maganda','magaling','maayos','salamat','perfect','excellent','great','love','happy','best','beautiful','professional','friendly','helpful','recommend','thank','nice','good'];
    $neg = ['pangit','masama','mabagal','malabo','mahal','bad','terrible','worst','poor','disappointed','hate','rude','slow','late','dirty','broken','waste'];
    
    $p = 0; $n = 0;
    foreach ($pos as $w) if (strpos($text, $w) !== false) $p++;
    foreach ($neg as $w) if (strpos($text, $w) !== false) $n++;
    if ($rating >= 4) $p += 2;
    elseif ($rating <= 2) $n += 2;
    
    if ($p > $n) return 'positive';
    if ($n > $p) return 'negative';
    return 'neutral';
}

function getSentimentBadge($s) {
    return match($s) {
        'positive' => 'badge-green',
        'negative' => 'badge-red',
        'mixed' => 'badge-purple',
        default => 'badge-amber'
    };
}

function getSentimentIcon($s) {
    return match($s) {
        'positive' => '😊',
        'negative' => '😞',
        'mixed' => '🤔',
        default => '😐'
    };
}

function getSentimentLabel($s) {
    return match($s) {
        'positive' => 'Positive',
        'negative' => 'Negative',
        'mixed' => 'Mixed',
        default => 'Neutral'
    };
}

function getSentimentInlineStyle($s) {
    if ($s === 'mixed') return 'background:#F3E8FF;color:#7C3AED;border:1px solid #C4B5FD;';
    return '';
}

function isUrgentFeedback($f) {
    if (!empty($f['is_urgent'])) return true;
    $sent = $f['sentiment'] ?? 'neutral';
    $rating = (int)($f['rating_overall'] ?? 0);
    if ($sent === 'negative') return true;
    if ($sent === 'mixed' && $rating <= 3) return true;
    return false;
}

// ================================================================
// CHECK FOR AI COLUMNS
// ================================================================
try {
    $checkAI = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'ai_sentiment'");
    $hasAI = $checkAI->fetch() ? true : false;
} catch (PDOException $e) { $hasAI = false; }

try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'topics'");
    $hasTopics = $checkCol->fetch() ? true : false;
} catch (PDOException $e) { $hasTopics = false; }

try {
    $checkReply = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'reply_sent_at'");
    $hasReplyFeature = $checkReply->fetch() ? true : false;
} catch (PDOException $e) { $hasReplyFeature = false; }

// ================================================================
// FILTERS
// ================================================================
$sentimentFilter = $_GET['sentiment'] ?? 'all';
$ratingFilter = $_GET['rating'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// ================================================================
// GET FEEDBACK
// ================================================================
$sql = "
    SELECT f.*, u.name AS client_name, u.email AS client_email
    FROM feedback f 
    JOIN users u ON f.user_id = u.id 
    WHERE 1=1
";
$params = [];

if ($ratingFilter !== 'all') {
    $sql .= " AND f.rating_overall = ?";
    $params[] = (int)$ratingFilter;
}

if (!empty($search)) {
    $sql .= " AND (f.comment LIKE ? OR u.name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// ===== NORMALIZE SENTIMENT =====
foreach ($feedbacks as &$fb) {
    if ($hasAI && !empty($fb['ai_sentiment'])) {
        $fb['sentiment'] = $fb['ai_sentiment'];
    } else {
        $fb['sentiment'] = analyzeSentimentLocal(
            $fb['comment'] ?? '',
            (int)($fb['rating_overall'] ?? 0)
        );
    }
}
unset($fb);

// ===== SENTIMENT FILTER =====
if ($sentimentFilter !== 'all') {
    $feedbacks = array_values(array_filter($feedbacks, function($f) use ($sentimentFilter) {
        return $f['sentiment'] === $sentimentFilter;
    }));
}

// ===== STATS =====
$totalFeedback = count($feedbacks);
$sentimentCounts = ['positive' => 0, 'negative' => 0, 'neutral' => 0, 'mixed' => 0];
$ratingSum = 0; $ratingCount = 0; $aiAnalyzedCount = 0; $repliedCount = 0;

foreach ($feedbacks as $f) {
    if (isset($sentimentCounts[$f['sentiment']])) $sentimentCounts[$f['sentiment']]++;
    if (!empty($f['rating_overall'])) { $ratingSum += (int)$f['rating_overall']; $ratingCount++; }
    if ($hasAI && !empty($f['ai_summary'])) $aiAnalyzedCount++;
    if ($hasReplyFeature && !empty($f['reply_sent_at'])) $repliedCount++;
}

$avgRating = $ratingCount > 0 ? round($ratingSum / $ratingCount, 1) : 0;

// ===== URGENT LIST =====
$urgentList = array_filter($feedbacks, 'isUrgentFeedback');
usort($urgentList, function($a, $b) {
    $priority = ['negative' => 0, 'mixed' => 1, 'neutral' => 2, 'positive' => 3];
    $pa = $priority[$a['sentiment']] ?? 4;
    $pb = $priority[$b['sentiment']] ?? 4;
    if ($pa !== $pb) return $pa - $pb;
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
?>

<!-- ============================================================ -->
<!-- PAGE BANNER -->
<!-- ============================================================ -->
<div class="page-banner">
    <div class="page-banner-text">
        <div class="eyebrow">Analytics</div>
        <h2>Client Feedback</h2>
        <p>AI-powered reviews, sentiment analysis, and urgent concerns.</p>
    </div>
    <div class="page-banner-art">💬</div>
</div>

<!-- ============================================================ -->
<!-- AI STATUS BANNER -->
<!-- ============================================================ -->
<?php if ($hasAI): ?>
<div style="background:linear-gradient(135deg,#eef2ff,#e0e7ff);border:1px solid #c7d2fe;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
  <span style="font-size:24px;">🤖</span>
  <div style="flex:1;">
    <strong style="color:#3730a3;">AI-Powered Analysis Active</strong>
    <p style="font-size:12px;color:#4338ca;margin-top:2px;">
      <?= $aiAnalyzedCount ?> out of <?= $totalFeedback ?> reviews analyzed by Google Gemini AI.
      <?php if ($hasReplyFeature): ?>
        · <strong><?= $repliedCount ?> replied</strong>
      <?php endif; ?>
    </p>
  </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- STATS SUMMARY -->
<!-- ============================================================ -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
    <div class="card" style="padding:16px;margin:0;">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Total Reviews</div>
        <div style="font-size:24px;font-weight:700;color:var(--dark);margin-top:4px;"><?= $totalFeedback ?></div>
    </div>
    <div class="card" style="padding:16px;margin:0;">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Avg Rating</div>
        <div style="font-size:24px;font-weight:700;color:var(--amber-text);margin-top:4px;">
            <?= $avgRating ?> <span style="font-size:14px;">★</span>
        </div>
    </div>
    <div class="card" style="padding:16px;margin:0;border-left:4px solid var(--green);">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Positive</div>
        <div style="font-size:24px;font-weight:700;color:var(--green-text);margin-top:4px;"><?= $sentimentCounts['positive'] ?></div>
    </div>
    <div class="card" style="padding:16px;margin:0;border-left:4px solid var(--red);">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Negative</div>
        <div style="font-size:24px;font-weight:700;color:var(--red-text);margin-top:4px;"><?= $sentimentCounts['negative'] ?></div>
    </div>
    <div class="card" style="padding:16px;margin:0;border-left:4px solid #8B5CF6;">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Mixed</div>
        <div style="font-size:24px;font-weight:700;color:#7C3AED;margin-top:4px;"><?= $sentimentCounts['mixed'] ?></div>
    </div>
    <div class="card" style="padding:16px;margin:0;border-left:4px solid var(--amber);">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Urgent</div>
        <div style="font-size:24px;font-weight:700;color:var(--amber-text);margin-top:4px;"><?= count($urgentList) ?></div>
    </div>
</div>

<!-- ============================================================ -->
<!-- URGENT FEEDBACK -->
<!-- ============================================================ -->
<?php if (!empty($urgentList)): ?>
<div class="card" style="border-color:var(--red);background:#FFF5F5;margin-bottom:20px;">
  <div class="section-header">
    <div>
      <h3 style="color:var(--red-text);margin:0;">⚠️ URGENT CLIENT CONCERNS</h3>
      <p style="font-size:12px;color:var(--muted);margin:4px 0 0;">Auto-flagged from negative feedback and low-rated mixed reviews.</p>
    </div>
    <span class="badge badge-red" style="font-size:11px;">
      <?= count($urgentList) ?> concern<?= count($urgentList) > 1 ? 's' : '' ?>
    </span>
  </div>
  <?php foreach ($urgentList as $f): ?>
    <div style="padding:12px;border-left:3px solid var(--red);background:white;border-radius:0 8px 8px 0;margin-bottom:10px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <div style="font-weight:600;color:var(--red-text);">
          <?= clean($f['client_name']) ?> — <?= str_repeat('★', $f['rating_overall']) ?><?= str_repeat('☆', 5 - $f['rating_overall']) ?>
        </div>
        <div style="display:flex;gap:6px;align-items:center;">
          <?php if (!empty($f['is_urgent'])): ?>
            <span style="padding:2px 8px;border-radius:50px;font-size:10px;background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;">📌 Manually Flagged</span>
          <?php endif; ?>
          <span class="badge <?= getSentimentBadge($f['sentiment']) ?>" style="<?= getSentimentInlineStyle($f['sentiment']) ?>">
            <?= getSentimentIcon($f['sentiment']) ?> <?= getSentimentLabel($f['sentiment']) ?>
          </span>
        </div>
      </div>
      <div style="font-size:13px;margin-top:4px;">"<?= clean($f['comment']) ?>"</div>

      <!-- AI ANALYSIS -->
      <?php if ($hasAI && !empty($f['ai_summary'])): ?>
      <div style="margin-top:10px;padding:10px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);border-radius:8px;border-left:3px solid #6366f1;">
        <div style="font-size:10px;font-weight:700;color:#3730a3;margin-bottom:4px;">🤖 AI ANALYSIS</div>
        <div style="font-size:12px;color:#312e81;line-height:1.5;">
          <div><strong>Summary:</strong> <?= clean($f['ai_summary']) ?></div>
        </div>
        <?php if (!empty($f['ai_suggested_reply'])): ?>
          <div style="margin-top:6px;padding-top:6px;border-top:1px dashed #a5b4fc;">
            <div style="font-size:10px;font-weight:600;color:#3730a3;margin-bottom:4px;">💬 Suggested Reply:</div>
            <div style="background:white;padding:6px 10px;border-radius:6px;font-size:11px;color:#1e293b;font-style:italic;">
              <?= clean($f['ai_suggested_reply']) ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- TOPICS -->
      <?php if ($hasTopics && !empty($f['topics'])):
        $topics = explode(',', $f['topics']);
      ?>
      <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px;">
        <?php foreach ($topics as $topic): $topic = trim($topic); ?>
          <span style="padding:2px 10px;border-radius:50px;font-size:10px;background:<?= getTopicColor($topic) ?>20;color:<?= getTopicColor($topic) ?>;border:1px solid <?= getTopicColor($topic) ?>40;">
            <?= getTopicIcon($topic) ?> <?= getTopicLabel($topic) ?>
          </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;gap:8px;flex-wrap:wrap;">
        <div style="font-size:11px;color:var(--muted);"><?= formatDate($f['created_at']) ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php if ($hasReplyFeature && !empty($f['ai_suggested_reply']) && empty($f['reply_sent_at'])): ?>
            <button type="button" class="btn-green btn-sm" style="font-size:10px;padding:3px 10px;"
                    onclick="openReplyModal(<?= $f['id'] ?>, <?= htmlspecialchars(json_encode($f['ai_suggested_reply']), ENT_QUOTES) ?>, '<?= addslashes(clean($f['client_name'])) ?>')">
              📤 Send Reply
            </button>
          <?php elseif ($hasReplyFeature && !empty($f['reply_sent_at'])): ?>
            <span style="font-size:10px;color:var(--green-text);">✅ Replied <?= formatDate($f['reply_sent_at']) ?></span>
          <?php endif; ?>
          <form method="POST" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="resolve">
            <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
            <button type="submit" class="btn-ghost btn-sm" style="font-size:10px;padding:3px 10px;">✓ Resolve</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- ALL FEEDBACK TABLE -->
<!-- ============================================================ -->
<div class="card">
  <div class="section-header">
    <h3>Recent Client Reviews</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:11px;">
      <span class="badge badge-green">😊 Positive (<?= $sentimentCounts['positive'] ?>)</span>
      <span class="badge badge-red">😞 Negative (<?= $sentimentCounts['negative'] ?>)</span>
      <span class="badge badge-amber">😐 Neutral (<?= $sentimentCounts['neutral'] ?>)</span>
      <span class="badge badge-purple" style="background:#F3E8FF;color:#7C3AED;border:1px solid #C4B5FD;">🤔 Mixed (<?= $sentimentCounts['mixed'] ?>)</span>
    </div>
  </div>

  <!-- FILTERS -->
  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;padding:12px;background:var(--bg-soft);border-radius:8px;align-items:center;">
    <div style="font-size:13px;font-weight:500;color:var(--muted);">Filter:</div>
    <a href="index.php?page=feedback&sentiment=all" class="badge <?= $sentimentFilter === 'all' ? 'badge-primary' : 'badge-gray' ?>" style="text-decoration:none;padding:4px 14px;">All</a>
    <a href="index.php?page=feedback&sentiment=positive" class="badge <?= $sentimentFilter === 'positive' ? 'badge-green' : 'badge-gray' ?>" style="text-decoration:none;padding:4px 14px;">😊 Positive</a>
    <a href="index.php?page=feedback&sentiment=negative" class="badge <?= $sentimentFilter === 'negative' ? 'badge-red' : 'badge-gray' ?>" style="text-decoration:none;padding:4px 14px;">😞 Negative</a>
    <a href="index.php?page=feedback&sentiment=neutral" class="badge <?= $sentimentFilter === 'neutral' ? 'badge-amber' : 'badge-gray' ?>" style="text-decoration:none;padding:4px 14px;">😐 Neutral</a>
    <a href="index.php?page=feedback&sentiment=mixed" class="badge <?= $sentimentFilter === 'mixed' ? 'badge-purple' : 'badge-gray' ?>" style="text-decoration:none;padding:4px 14px;">🤔 Mixed</a>
    <div style="margin-left:auto;">
      <form method="GET" style="display:flex;gap:6px;">
        <input type="hidden" name="page" value="feedback">
        <input type="text" name="search" placeholder="Search..." value="<?= clean($search) ?>" style="padding:6px 12px;border-radius:6px;border:1px solid var(--border);font-size:13px;background:white;">
        <button type="submit" class="btn-ghost btn-sm">🔍</button>
      </form>
    </div>
  </div>

  <!-- TABLE -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Client</th>
          <th>Rating</th>
          <th>Comment</th>
          <th>Topics</th>
          <th>Sentiment</th>
          <th>AI Analysis</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($feedbacks as $f):
          $sentBadge = getSentimentBadge($f['sentiment']);
          $sentIcon  = getSentimentIcon($f['sentiment']);
          $sentLabel = getSentimentLabel($f['sentiment']);
          $sentStyle = getSentimentInlineStyle($f['sentiment']);
          $topics = ($hasTopics && !empty($f['topics'])) ? explode(',', $f['topics']) : [];
          $isUrgent = isUrgentFeedback($f);
          $hasAIAnalysis = $hasAI && !empty($f['ai_summary']);
        ?>
        <tr style="<?= $isUrgent ? 'background:#FEF2F2;' : '' ?>">
          <td>
            <strong><?= clean($f['client_name']) ?></strong>
            <?php if (!empty($f['client_email'])): ?>
              <div style="font-size:11px;color:var(--muted);"><?= clean($f['client_email']) ?></div>
            <?php endif; ?>
            <?php if ($isUrgent): ?>
              <div style="margin-top:3px;">
                <span style="font-size:9px;font-weight:700;color:#991B1B;background:#FEE2E2;padding:2px 6px;border-radius:50px;border:1px solid #FCA5A5;">⚠️ URGENT</span>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <span style="color:var(--amber-text);font-size:14px;">
              <?= str_repeat('★', $f['rating_overall']) ?><?= str_repeat('☆', 5 - $f['rating_overall']) ?>
            </span>
          </td>
          <td style="max-width:250px;"><?= clean($f['comment'] ?? '—') ?></td>
          <td>
            <?php if (!empty($topics)): ?>
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                <?php foreach ($topics as $topic): $topic = trim($topic); ?>
                  <span style="padding:2px 10px;border-radius:50px;font-size:10px;background:<?= getTopicColor($topic) ?>20;color:<?= getTopicColor($topic) ?>;border:1px solid <?= getTopicColor($topic) ?>40;white-space:nowrap;">
                    <?= getTopicIcon($topic) ?> <?= getTopicLabel($topic) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <span style="color:var(--muted);font-size:12px;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $sentBadge ?>" style="<?= $sentStyle ?>">
              <?= $sentIcon ?> <?= $sentLabel ?>
            </span>
          </td>
          <td style="max-width:280px;">
            <?php if ($hasAIAnalysis): ?>
              <div style="font-size:11px;line-height:1.5;">
                <div style="background:#eef2ff;padding:6px 8px;border-radius:6px;border-left:2px solid #6366f1;margin-bottom:6px;">
                  <strong style="color:#3730a3;">🤖 Summary:</strong><br>
                  <?= clean($f['ai_summary']) ?>
                </div>
                <?php if (!empty($f['ai_suggested_reply'])): ?>
                  <details style="font-size:11px;">
                    <summary style="cursor:pointer;color:#4338ca;font-weight:600;">💬 Suggested Reply</summary>
                    <div style="margin-top:4px;background:white;padding:6px 8px;border-radius:6px;font-style:italic;color:#1e293b;border:1px solid #e0e7ff;">
                      <?= clean($f['ai_suggested_reply']) ?>
                    </div>
                  </details>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reanalyze">
                <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                <button type="submit" class="btn-ghost btn-sm" style="font-size:10px;padding:3px 10px;">🤖 Analyze</button>
              </form>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--muted);"><?= formatDate($f['created_at']) ?></td>
          <td>
            <div style="display:flex;flex-direction:column;gap:4px;min-width:90px;">
              <!-- SEND REPLY -->
              <?php if ($hasReplyFeature): ?>
                <?php if (!empty($f['reply_sent_at'])): ?>
                  <span style="font-size:10px;color:var(--green-text);font-weight:600;">✅ Replied</span>
                <?php elseif (!empty($f['ai_suggested_reply'])): ?>
                  <button type="button" class="btn-green btn-sm" style="font-size:10px;padding:3px 10px;width:100%;"
                          onclick="openReplyModal(<?= $f['id'] ?>, <?= htmlspecialchars(json_encode($f['ai_suggested_reply']), ENT_QUOTES) ?>, '<?= addslashes(clean($f['client_name'])) ?>')">
                    📤 Reply
                  </button>
                <?php endif; ?>
              <?php endif; ?>

              <?php if ($isUrgent): ?>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="resolve">
                  <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                  <button type="submit" class="btn-ghost btn-sm" style="font-size:10px;padding:3px 10px;width:100%;">✓ Resolve</button>
                </form>
              <?php else: ?>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="mark_urgent">
                  <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                  <button type="submit" class="btn-ghost btn-sm" style="font-size:10px;padding:3px 10px;width:100%;">⚠️ Flag</button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this feedback permanently?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                <button type="submit" class="btn-red btn-sm" style="font-size:10px;padding:3px 10px;width:100%;">🗑️ Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($feedbacks)): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px;">No feedback found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================ -->
<!-- REPLY MODAL -->
<!-- ============================================================ -->
<?php if ($hasReplyFeature): ?>
<div id="reply-modal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this) closeReplyModal()">
    <div class="modal-content" style="background:white;max-width:600px;width:90%;border-radius:12px;padding:24px;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 style="color:var(--green-text);margin:0;">📤 Send Reply to Client</h2>
            <button onclick="closeReplyModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--muted);">✕</button>
        </div>
        
        <div style="background:#f0f9ff;border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;color:#0369a1;border-left:3px solid #0ea5e9;">
            <strong>📝 Replying to:</strong> <span id="reply-client-name">Client</span>
        </div>
        
        <p style="font-size:13px;color:var(--muted);margin-bottom:8px;">
            🤖 AI-generated reply below. Pwede mong i-edit bago i-send.
        </p>
        
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Reply Message (editable):</label>
        <textarea id="reply-message" rows="6" style="width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;margin-bottom:16px;" required></textarea>
        
        <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">
            ⚠️ Ang reply na ito ay ipapadala sa client as notification.
        </p>
        
        <div style="display:flex;gap:10px;">
            <button type="button" onclick="closeReplyModal()" style="flex:1;padding:10px;background:#f1f5f9;border:1px solid var(--border);border-radius:8px;cursor:pointer;font-weight:600;">Cancel</button>
            <button type="button" id="send-reply-btn" onclick="confirmSendReply()" style="flex:2;padding:10px;background:var(--green,#2ECC71);color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;">📤 Send to Client</button>
        </div>
        
        <input type="hidden" id="reply-feedback-id" value="">
    </div>
</div>

<script>
function openReplyModal(feedbackId, suggestedReply, clientName) {
    document.getElementById('reply-feedback-id').value = feedbackId;
    document.getElementById('reply-message').value = suggestedReply || '';
    document.getElementById('reply-client-name').textContent = clientName || 'Client';
    document.getElementById('reply-modal').style.display = 'flex';
}

function closeReplyModal() {
    document.getElementById('reply-modal').style.display = 'none';
}

function confirmSendReply() {
    const feedbackId = document.getElementById('reply-feedback-id').value;
    const message = document.getElementById('reply-message').value.trim();
    
    if (!feedbackId) { alert('No feedback selected.'); return; }
    if (!message) { alert('Please enter a reply message.'); return; }
    if (!confirm('Send this reply to the client?\n\n' + message)) return;
    
    const btn = document.getElementById('send-reply-btn');
    btn.disabled = true;
    btn.textContent = '⏳ Sending...';
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'index.php?page=feedback';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?= csrfToken() ?>';
    form.appendChild(csrfInput);
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'send_reply';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'feedback_id';
    idInput.value = feedbackId;
    form.appendChild(idInput);
    
    const msgInput = document.createElement('input');
    msgInput.type = 'hidden';
    msgInput.name = 'reply_message';
    msgInput.value = message;
    form.appendChild(msgInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php endif; ?>

<!-- ===== FALLBACK STYLES ===== -->
<style>
.badge-primary { background: var(--dark); color: white; border: 1px solid var(--dark); }
.badge-gray { background: var(--bg-soft); color: var(--muted); border: 1px solid var(--border); }
.badge-gray:hover { background: var(--border); color: var(--dark); }
.badge-purple { background: #F3E8FF; color: #7C3AED; border: 1px solid #C4B5FD; }
.btn-green { background: var(--green, #2ECC71); color: white; border: none; border-radius: 6px; cursor: pointer; transition: 0.2s; font-weight: 500; }
.btn-green:hover { filter: brightness(0.95); transform: scale(1.02); }
.btn-red { background: var(--red, #E74C3C); color: white; border: none; border-radius: 6px; cursor: pointer; transition: 0.2s; font-weight: 500; }
.btn-red:hover { filter: brightness(0.95); transform: scale(1.02); }
details summary::-webkit-details-marker { display: none; }
details summary::marker { display: none; }
</style>