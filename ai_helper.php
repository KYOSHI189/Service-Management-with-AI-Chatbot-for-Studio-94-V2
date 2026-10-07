<?php
// ============================================================
// AI HELPER — Google Gemini Integration (UPDATED)
// Model: gemini-2.5-flash (stable, fast)
// ============================================================

require_once __DIR__ . '/config.php';

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

    $prompt = "Analyze this customer feedback for a photo studio. Return ONLY valid JSON (no markdown, no code fences) in this exact format:
{\"sentiment\": \"positive\" or \"negative\" or \"neutral\", \"score\": 1-5, \"category\": \"service\" or \"quality\" or \"staff\" or \"pricing\" or \"facility\" or \"delivery\" or \"print\" or \"general\", \"summary\": \"one short sentence\", \"suggested_reply\": \"professional reply\"}

Customer feedback: \"$feedbackText\"";

    // Try available models — in order of preference
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
                'temperature' => 0.3,
                'maxOutputTokens' => 500,
                'topP' => 0.8,
            ]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
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
                    error_log("AI Helper: ✅ SUCCESS with $model");
                    return [
                        'sentiment' => strtolower(trim($parsed['sentiment'])),
                        'score' => max(1, min(5, (int)($parsed['score'] ?? 3))),
                        'category' => strtolower(trim($parsed['category'] ?? 'general')),
                        'summary' => trim($parsed['summary'] ?? ''),
                        'suggested_reply' => trim($parsed['suggested_reply'] ?? '')
                    ];
                }
            } else {
                error_log("AI Helper: No JSON found. Text: " . substr($text, 0, 200));
            }
        } else {
            error_log("AI Helper: Response: " . substr($response, 0, 200));
        }
    }

    error_log("AI Helper: ❌ All models failed");
    return null;
}