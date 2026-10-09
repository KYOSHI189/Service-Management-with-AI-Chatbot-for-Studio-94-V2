<?php
// ============================================================
// STUDIO 94 SNAPTRACK — Configuration
// ============================================================

function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (strpos($value, '"') === 0 || strpos($value, "'") === 0) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

loadEnv();

// ===== DATABASE CONNECTION =====
define('DB_HOST',    $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'localhost');
define('DB_PORT',    $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: '3306');
define('DB_NAME',    $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'snaptrack');
define('DB_USER',    $_ENV['DB_USER'] ?? getenv('DB_USER') ?? $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root');
define('DB_PASS',    $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ===== APP SETTINGS & URL CONFIGURATION =====
define('APP_NAME', 'Studio 94 SnapTrack');

// Detect APP_URL: environment variable takes precedence, otherwise auto-detect from host & protocol
$envAppUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: null;
if (!empty($envAppUrl)) {
    $detectedUrl = rtrim($envAppUrl, '/');
} else {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] !== 'off') ||
        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = '';
    if (strpos($scriptName, '/snaptrack/') === 0 || $scriptName === '/snaptrack') {
        $basePath = '/snaptrack';
    }

    $detectedUrl = $protocol . $host . $basePath;
}

define('APP_URL',    $detectedUrl);
define('UPLOAD_DIR', __DIR__ . '/assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');

define('SESSION_LIFETIME', 7200);
define('ROLES', ['admin', 'staff', 'client']);

// ============================================================
// GEMINI API KEY
// ============================================================
define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: '');

// ===== GEMINI MODEL =====
define('GEMINI_MODEL', 'gemini-3.6-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/');

// ===== PACKAGE PRICES =====
define('PACKAGES', [
    'Self-Shoot'    => ['price' => 1500, 'duration' => '2 hours',  'description' => 'DIY photoshoot with professional lighting.'],
    'Studio Rental' => ['price' => 2500, 'duration' => '1 hour',   'description' => 'Professional studio space with lighting.'],
    'Family'        => ['price' => 3500, 'duration' => '3 hours',  'description' => 'Inclusive session for families.'],
    'Creative'      => ['price' => 5000, 'duration' => '4 hours',  'description' => 'High-concept shoot with artistic direction.'],
]);

// ===== LOYALTY TIERS =====
define('LOYALTY_TIERS', [
    1 => '+5 MINUTES',
    2 => '+1 PRINT OUT',
    3 => '+1 BACKDROP',
    4 => '50% OFF',
]);

// ============================================================
// GMAIL SMTP — para sa verification at notification emails
// ============================================================
// ⚠️ PALITAN MO ITO ng totoong Gmail account mo + App Password
// 
// PAANO MAKAKUHA NG APP PASSWORD:
// 1. Enable 2-Step Verification: https://myaccount.google.com/security
// 2. Generate App Password: https://myaccount.google.com/apppasswords
// 3. Copy yung 16-character password (alisin yung spaces)
// ============================================================
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define(
    'MAIL_USERNAME',
    $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: ''
);

define(
    'MAIL_PASSWORD',
    $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: ''
);
define('MAIL_FROM_NAME', 'Studio 94');

// ===== RESEND API (HTTPS Port 443 — works on Railway without SMTP blocking) =====
define(
    'RESEND_API_KEY',
    $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?: ''
);

// Sender address for outbound mail. Resend's onboarding@resend.dev test domain
// only allows sending to your own address — to email real clients you must verify
// a domain in Resend and set RESEND_FROM to e.g. "Studio 94 <no-reply@yourdomain>".
define(
    'RESEND_FROM',
    $_ENV['RESEND_FROM'] ?? getenv('RESEND_FROM') ?: ''
);

// ===== BREVO API (HTTPS 443 — 300 emails/day free, no domain required) =====
// Preferred over Gmail SMTP on Railway, which blocks ports 465/587.
// The sender defaults to MAIL_USERNAME; override with BREVO_FROM_EMAIL.
define(
    'BREVO_API_KEY',
    $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?: ''
);
define(
    'BREVO_FROM_EMAIL',
    $_ENV['BREVO_FROM_EMAIL'] ?? getenv('BREVO_FROM_EMAIL') ?: ''
);

// ============================================================
// EMAIL VERIFICATION SETTINGS
// ============================================================
define('VERIFICATION_EXPIRY_HOURS', 24);  // Verification link valid for 24 hours

// ============================================================
// ERROR REPORTING — DEBUG MODE (temporary)
// ============================================================
// ⚠️ IMPORTANT: I-set to '0' kapag production na
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');

// Writing errors to a file makes them invisible on Railway, where the container
// filesystem is discarded and the log stream is the only place output survives.
// Detect Railway (it sets RAILWAY_ENVIRONMENT automatically) and let PHP log to
// the SAPI logger, which reaches the Railway dashboard.
$isRailway = !empty($_ENV['RAILWAY_ENVIRONMENT'] ?? getenv('RAILWAY_ENVIRONMENT'));
if (!$isRailway) {
    ini_set('error_log', __DIR__ . '/php-errors.log');
}

// Mirror fatal errors to stderr so a blank page can be diagnosed even if the
// ini above was left at its default.
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    $line = sprintf(
        "[UA-FATAL] %s in %s:%d\n%s\n",
        $err['message'],
        $err['file'],
        $err['error_line'],
        $err['message']
    );
    if (defined('STDERR')) {
        fwrite(STDERR, $line);
    } else {
        error_log(rtrim($line));
    }
});