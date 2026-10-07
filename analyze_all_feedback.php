<?php
// ============================================================
// BATCH ANALYZER — Process 5 at a time
// Location: snaptrack/analyze_all_feedback.php
// ⚠️ DELETE AFTER USE!
// ============================================================

set_time_limit(600);
ini_set('max_execution_time', 600);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ai_helper.php';

requireRole('admin');
$pdo = db();

// ===== GET BATCH NUMBER =====
$batch = (int)($_GET['batch'] ?? 1);
$perBatch = 3; // 3 feedback per batch (para safe sa timeout)
$offset = ($batch - 1) * $perBatch;

// ===== GET TOTAL COUNT =====
$totalStmt = $pdo->query("
    SELECT COUNT(*) FROM feedback 
    WHERE (ai_sentiment IS NULL OR ai_summary IS NULL)
    AND comment IS NOT NULL AND comment != ''
");
$total = (int)$totalStmt->fetchColumn();

// ===== GET CURRENT BATCH =====
$stmt = $pdo->prepare("
    SELECT id, comment FROM feedback 
    WHERE (ai_sentiment IS NULL OR ai_summary IS NULL)
    AND comment IS NOT NULL AND comment != ''
    ORDER BY id ASC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perBatch, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$feedbacks = $stmt->fetchAll();

$totalBatches = ceil($total / $perBatch);

?>
<!DOCTYPE html>
<html>
<head>
    <title>AI Batch Analysis</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; line-height: 1.6; }
        .box { background: white; padding: 15px; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .success { color: green; } .error { color: red; } .info { color: #3b82f6; }
        .item { border-bottom: 1px solid #eee; padding: 8px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #6C63FF; color: white; text-decoration: none; border-radius: 8px; margin: 5px 5px 5px 0; font-weight: bold; }
        .btn:hover { background: #4A42CC; }
        .progress { background: #e5e7eb; border-radius: 8px; overflow: hidden; height: 20px; margin: 10px 0; }
        .progress-bar { background: #10b981; height: 100%; transition: width 0.3s; }
    </style>
</head>
<body>

<h1>🤖 AI Batch Analysis</h1>

<div class="box">
    <strong>Batch:</strong> <?= $batch ?> of <?= $totalBatches ?><br>
    <strong>Remaining:</strong> <?= $total ?> feedback to analyze<br>
    <strong>Processing:</strong> <?= count($feedbacks) ?> items in this batch<br>
    <div class="progress">
        <div class="progress-bar" style="width: <?= $total > 0 ? (($total - count($feedbacks)) / $total * 100) : 100 ?>%"></div>
    </div>
</div>

<?php if (empty($feedbacks)): ?>
    <div class="box success">
        ✅ <strong>All feedback analyzed!</strong> No more pending items.
    </div>
    <div class="box" style="background:#fff3cd;border-left:4px solid #ffc107;">
        <strong>⚠️ IMPORTANT:</strong> Delete <code>analyze_all_feedback.php</code> now!
    </div>
    <a href="index.php?page=feedback" class="btn">← Back to Feedback</a>
<?php else: ?>
    <div class="box">
    <?php
    $success = 0; $failed = 0;
    foreach ($feedbacks as $fb):
        $id = $fb['id'];
        $comment = $fb['comment'];
        
        echo "<div class='item'>";
        echo "<strong>#{$id}:</strong> \"" . htmlspecialchars(substr($comment, 0, 60)) . "...\"<br>";
        echo "<span class='info'>⏳ Analyzing...</span><br>";
        
        if (ob_get_level() > 0) ob_flush();
        flush();
        
        $result = analyzeFeedbackWithAI($comment);
        
        if ($result) {
            try {
                $upd = $pdo->prepare("
                    UPDATE feedback 
                    SET ai_sentiment = ?, ai_category = ?, ai_summary = ?, 
                        ai_suggested_reply = ?, sentiment = ?, ai_analyzed_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([
                    $result['sentiment'], $result['category'],
                    $result['summary'], $result['suggested_reply'],
                    $result['sentiment'], $id
                ]);
                echo "<span class='success'>✅ " . strtoupper($result['sentiment']) . " ({$result['score']}/5)</span><br>";
                echo "<span class='info'>📝 " . htmlspecialchars($result['summary']) . "</span>";
                $success++;
            } catch (Exception $e) {
                echo "<span class='error'>❌ DB error: " . htmlspecialchars($e->getMessage()) . "</span>";
                $failed++;
            }
        } else {
            echo "<span class='error'>❌ Failed</span>";
            $failed++;
        }
        
        echo "</div>";
    endforeach;
    ?>
    </div>

    <div class="box">
        <strong>✅ Success:</strong> <span class="success"><?= $success ?></span><br>
        <strong>❌ Failed:</strong> <span class="error"><?= $failed ?></span>
    </div>

    <?php if ($batch < $totalBatches): ?>
        <a href="?batch=<?= $batch + 1 ?>" class="btn">➡️ Next Batch</a>
    <?php else: ?>
        <div class="box success">
            🎉 <strong>ALL DONE!</strong> All feedback analyzed!
        </div>
    <?php endif; ?>
    
    <a href="index.php?page=feedback" class="btn" style="background:#666;">← Feedback Page</a>
<?php endif; ?>

</body>
</html>