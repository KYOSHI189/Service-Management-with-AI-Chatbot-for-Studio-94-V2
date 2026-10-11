<?php
// ============================================================
// AI HELPER — Google Gemini Integration (FIXED)
// Model: gemini-2.5-flash (stable, fast)
// ============================================================

require_once __DIR__ . '/config.php';

// ============================================================
// SHARED FALLBACK: SENTIMENT ANALYSIS (LOCAL)
// ============================================================
if (!function_exists('analyzeSentimentLocal')) {
    function analyzeSentimentLocal(string $comment, int $rating = 0): string {
        $text = strtolower(trim($comment));
        
        // If empty comment, use rating
        if ($text === '') {
            if ($rating >= 4) return 'positive';
            if ($rating <= 2) return 'negative';
            return 'neutral';
        }
        
        // Positive keywords (English + Tagalog)
        $positiveWords = [
            'ganda', 'maganda', 'magaling', 'maayos', 'salamat', 'sulit',
            'perfect', 'excellent', 'great', 'amazing', 'awesome',
            'love', 'happy', 'satisfied', 'beautiful', 'nice',
            'wonderful', 'best', 'professional', 'friendly',
            'helpful', 'recommend', 'thank', 'good', 'okay',
            'galing', 'bilib', 'wow', 'solid', 'panalo', 'astig',
        ];
        
        // Negative keywords (English + Tagalog)
        $negativeWords = [
            'pangit', 'panget', 'masama', 'mabagal', 'bagal', 'malabo',
            'mahal', 'sira', 'basura', 'problema', 'tagal', 'nawala',
            'bad', 'terrible', 'worst', 'poor', 'disappointed',
            'hate', 'rude', 'slow', 'late', 'dirty', 'broken',
            'waste', 'delay', 'awful', 'horrible', 'sad',
        ];
        
        // Negation words
        $negations = ['hindi', 'di', 'not', 'no', 'never', 'wala', 'walang'];
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        // Detect positive words (with negation check)
        foreach ($positiveWords as $word) {
            $pos = strpos($text, $word);
            if ($pos !== false) {
                // Check kung may negation bago ang word (10 chars before)
                $before = substr($text, max(0, $pos - 15), 15);
                $isNegated = false;
                foreach ($negations as $neg) {
                    if (strpos($before, $neg) !== false) {
                        $isNegated = true;
                        break;
                    }
                }
                if ($isNegated) $negativeCount++;
                else $positiveCount++;
            }
        }
        
        // Detect negative words
        foreach ($negativeWords as $word) {
            if (strpos($text, $word) !== false) {
                $negativeCount++;
            }
        }
        
        // Apply rating weight (2x)
        if ($rating >= 4) $positiveCount += 2;
        elseif ($rating <= 2) $negativeCount += 2;
        
        // MIXED detection: if both have significant count
        if ($positiveCount > 0 && $negativeCount > 0) {
            $diff = abs($positiveCount - $negativeCount);
            if ($diff <= 1) return 'mixed';
            return $positiveCount > $negativeCount ? 'positive' : 'negative';
        }
        
        if ($positiveCount > 0) return 'positive';
        if ($negativeCount > 0) return 'negative';
        return 'neutral';
    }
}

// ============================================================
// SHARED: TOPIC DETECTION (LOCAL)
// ============================================================
if (!function_exists('detectTopicsLocal')) {
    function detectTopicsLocal(string $comment): array {
        $topics = [];
        $text = strtolower($comment);
        
        if (preg_match('/\b(kuha|shot|shots|photographer|camera|lens|pose|angle|photography|shoot)\b/', $text)) 
            $topics[] = 'photographer';
        if (preg_match('/\b(edit|editing|retouch|ganda|maganda|pangit|panget|sira|blur|linaw|clear|sharp|quality)\b/', $text)) 
            $topics[] = 'quality';
        if (preg_match('/\b(print|printed|picture|photo|larawan|litrato|canvas|frame|album)\b/', $text)) 
            $topics[] = 'print';
        if (preg_match('/\b(staff|crew|assistant|reception|employee|team|maayos|mabait|magalang|friendly|helpful)\b/', $text)) 
            $topics[] = 'staff';
        if (preg_match('/\b(delivery|tagal|delay|late|mabilis|agad|release|send|receive|waiting|hintay)\b/', $text)) 
            $topics[] = 'delivery';
        if (preg_match('/\b(price|mahal|mura|sulit|worth|budget|cost|presyo|bayad|payment)\b/', $text)) 
            $topics[] = 'pricing';
        if (preg_match('/\b(studio|setup|props|backdrop|lighting|place|location|space|room|facility)\b/', $text)) 
            $topics[] = 'studio';
        
        $topics = array_unique($topics);
        if (empty($topics)) $topics[] = 'general';
        return $topics;
    }
}

// ============================================================
// SHARED: TOPIC HELPERS
// ============================================================
if (!function_exists('getTopicIcon')) {
    function getTopicIcon(string $topic): string {
        $icons = [
            'photographer' => '📸', 'delivery' => '📦', 'pricing' => '💰',
            'studio' => '🏢', 'staff' => '👥', 'quality' => '🎨',
            'print' => '🖼️', 'general' => '💬', 'service' => '🛎️',
            'facility' => '🏢', 'other' => '💬',
        ];
        return $icons[trim($topic)] ?? '💬';
    }
}

if (!function_exists('getTopicLabel')) {
    function getTopicLabel(string $topic): string {
        $labels = [
            'photographer' => 'Photographer', 'delivery' => 'Delivery', 'pricing' => 'Pricing',
            'studio' => 'Studio', 'staff' => 'Staff', 'quality' => 'Quality',
            'print' => 'Print', 'general' => 'General', 'service' => 'Service',
            'facility' => 'Facility', 'other' => 'Other',
        ];
        return $labels[trim($topic)] ?? ucfirst($topic);
    }
}

if (!function_exists('getTopicColor')) {
    function getTopicColor(string $topic): string {
        $colors = [
            'photographer' => '#8B5CF6', 'delivery' => '#F59E0B', 'pricing' => '#10B981',
            'studio' => '#3B82F6', 'staff' => '#EC4899', 'quality' => '#EF4444',
            'print' => '#8B5CF6', 'general' => '#6B7280', 'service' => '#0EA5E9',
            'facility' => '#6366F1', 'other' => '#6B7280',
        ];
        return $colors[trim($topic)] ?? '#6B7280';
    }
}

// ============================================================
// SHARED: SENTIMENT HELPERS
// ============================================================
if (!function_exists('getSentimentBadge')) {
    function getSentimentBadge(string $s): string {
        return match($s) {
            'positive' => 'badge-green',
            'negative' => 'badge-red',
            'mixed' => 'badge-purple',
            default => 'badge-amber',
        };
    }
}

if (!function_exists('getSentimentIcon')) {
    function getSentimentIcon(string $s): string {
        return match($s) {
            'positive' => '😊',
            'negative' => '😞',
            'mixed' => '🤔',
            default => '😐',
        };
    }
}

if (!function_exists('getSentimentLabel')) {
    function getSentimentLabel(string $s): string {
        return match($s) {
            'positive' => 'Positive',
            'negative' => 'Negative',
            'mixed' => 'Mixed',
            default => 'Neutral',
        };
    }
}

if (!function_exists('getSentimentInlineStyle')) {
    function getSentimentInlineStyle(string $s): string {
        if ($s === 'mixed') return 'background:#F3E8FF;color:#7C3AED;border:1px solid #C4B5FD;';
        return '';
    }
}

if (!function_exists('isUrgentFeedback')) {
    function isUrgentFeedback(array $f): bool {
        if (!empty($f['is_urgent'])) return true;
        $sent = $f['sentiment'] ?? 'neutral';
        $rating = (int)($f['rating_overall'] ?? 0);
        if ($sent === 'negative') return true;
        if ($sent === 'mixed' && $rating <= 3) return true;
        return false;
    }
}

// ============================================================
// GEMINI AI ANALYSIS
// ============================================================
if (!function_exists('analyzeFeedbackWithAI')) {
    function analyzeFeedbackWithAI(string $feedbackText): ?array {
        $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        if (empty($apiKey)) {
            error_log("AI Helper: Empty API key");
            return null;
        }

        $feedbackText = trim($feedbackText);
        if ($feedbackText === '') return null;

        if (mb_strlen($feedbackText) > 2000) {
            $feedbackText = mb_substr($feedbackText, 0, 2000) . '...';
        }

        // ✅ IMPROVED: Better prompt with Tagalog support + mixed detection
        $prompt = "You are analyzing customer feedback for a Filipino photography studio called Studio 94.
The feedback may be written in English, Tagalog, or Taglish (mixed English-Tagalog).

Instructions:
1. Consider BOTH English and Filipino/Tagalog words
2. If the feedback contains BOTH positive and negative opinions, use 'mixed'
3. Be culturally aware of Filipino expressions (e.g., 'sulit' = worth it, 'pangit' = ugly/bad)
4. Return ONLY valid JSON (no markdown, no code fences, no explanation)

Return this exact JSON format:
{\"sentiment\": \"positive\"|\"negative\"|\"neutral\"|\"mixed\", \"score\": 1-5, \"category\": \"service\"|\"quality\"|\"staff\"|\"pricing\"|\"facility\"|\"delivery\"|\"print\"|\"general\", \"summary\": \"one short sentence in English\", \"suggested_reply\": \"professional reply in Taglish\"}

Customer feedback: \"$feedbackText\"";

        $models = [
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-flash-latest',
            'gemini-2.5-pro',
            'gemini-flash-lite-latest',
        ];

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

            $body = json_encode([
                'contents' => [[
                    'parts' => [['text' => $prompt]]
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 500,
                    'topP' => 0.8,
                ]
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("AI Helper: [$model] HTTP $httpCode");

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                    $parsed = json_decode($matches[0], true);
                    if ($parsed && isset($parsed['sentiment'])) {
                        // ✅ VALIDATE: Ensure valid sentiment
                        $validSentiments = ['positive', 'negative', 'neutral', 'mixed'];
                        $sentiment = strtolower(trim($parsed['sentiment']));
                        if (!in_array($sentiment, $validSentiments)) {
                            $sentiment = 'neutral';
                        }
                        
                        error_log("AI Helper: ✅ SUCCESS with $model");
                        return [
                            'sentiment' => $sentiment,
                            'score' => max(1, min(5, (int)($parsed['score'] ?? 3))),
                            'category' => strtolower(trim($parsed['category'] ?? 'general')),
                            'summary' => trim($parsed['summary'] ?? ''),
                            'suggested_reply' => trim($parsed['suggested_reply'] ?? ''),
                        ];
                    }
                }
            }
        }

        error_log("AI Helper: ❌ All models failed");
        return null;
    }
}
