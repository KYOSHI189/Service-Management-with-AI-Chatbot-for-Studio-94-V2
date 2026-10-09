<?php
// ============================================================
// mail_diagnose.php — diagnose outbound email delivery
// ============================================================
// Reports which mail transport is configured and, if a Brevo send was
// recently attempted, the exact API response. Never prints key values.
//
// TEMPORARY. Delete this file once mail is working — it only reveals
// configuration state, but it should not ship to production.
//
// Usage: mail_diagnose.php?key=<ADMIN_RESET_KEY>
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/send-verification.php';

$key = $_ENV['ADMIN_RESET_KEY'] ?? getenv('ADMIN_RESET_KEY') ?: '';
if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    die('Forbidden. Pass ?key=<ADMIN_RESET_KEY>.');
}

header('Content-Type: text/plain; charset=utf-8');

function state(bool $ok): string {
    return $ok ? 'SET' : 'not set';
}

echo "=== Mail configuration ===\n";
echo 'RESEND_API_KEY    : ' . state(!empty(RESEND_API_KEY)) . "\n";
echo 'RESEND_FROM       : ' . state(!empty(RESEND_FROM)) . "\n";
echo 'BREVO_API_KEY     : ' . state(!empty(BREVO_API_KEY)) . "\n";
echo 'BREVO_FROM_EMAIL  : ' . state(!empty(BREVO_FROM_EMAIL)) . "\n";
echo 'MAIL_USERNAME     : ' . state(MAIL_USERNAME !== '') . "\n";
echo 'MAIL_PASSWORD     : ' . state(MAIL_PASSWORD !== '') . "\n";
echo 'APP_URL           : ' . APP_URL . "\n";
echo 'isMailConfigured(): ' . (isMailConfigured() ? 'YES' : 'NO') . "\n";

echo "\n=== Sender resolution ===\n";
$sender = !empty(BREVO_FROM_EMAIL)
    ? BREVO_FROM_EMAIL
    : (MAIL_USERNAME !== '' ? MAIL_USERNAME : '');
echo 'configured sender : ' . ($sender !== '' ? $sender : '(none)') . "\n";

if (!empty(BREVO_API_KEY)) {
    $resolved = brevoDefaultSender(BREVO_API_KEY);
    echo 'Brevo account     : ' . ($resolved !== '' ? $resolved : 'could not read') . "\n";

    echo "\n=== Brevo /v3/account response ===\n";
    $ch = curl_init('https://api.brevo.com/v3/account');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . BREVO_API_KEY,
        'Accept: application/json',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    echo 'HTTP ' . $code . "\n";
    if ($err) {
        echo 'curl: ' . $err . "\n";
    }
    echo substr((string) $body, 0, 800) . "\n";
}

$errFile = sys_get_temp_dir() . '/ua-brevo-error.json';
echo "\n=== Last recorded Brevo send error ===\n";
if (file_exists($errFile)) {
    echo file_get_contents($errFile) . "\n";
} else {
    echo "(no send attempt recorded yet)\n";
}

echo "\n=== Recent app log lines ===\n";
$logFile = __DIR__ . '/php-errors.log';
if (file_exists($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
    foreach (array_slice($lines, -25) as $l) {
        if (stripos($l, 'STUDIO94') !== false || stripos($l, 'Brevo') !== false) {
            echo $l . "\n";
        }
    }
} else {
    echo "(no local php-errors.log on this host)\n";
}