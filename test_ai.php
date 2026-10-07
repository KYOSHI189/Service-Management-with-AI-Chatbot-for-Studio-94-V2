<?php
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Simple Gemini Test</h2>";

$key = trim(GEMINI_API_KEY);
$model = 'gemini-3.6-flash';  // ← I-check kung tama

echo "<p><strong>Testing model:</strong> $model</p>";

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

$payload = [
    'contents' => [['parts' => [['text' => 'Say hi.']]]]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $key,
    ],
    CURLOPT_TIMEOUT => 60,          // ← Extended timeout
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$start = microtime(true);
$response = curl_exec($ch);
$duration = round((microtime(true) - $start) * 1000);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p><strong>Duration:</strong> {$duration} ms</p>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>cURL Error:</strong> " . ($curlError ?: 'None') . "</p>";

echo "<hr><h3>Response:</h3>";
echo "<pre style='background:#f5f5f5;padding:15px;border-radius:8px;overflow:auto;'>";
echo htmlspecialchars($response);
echo "</pre>";