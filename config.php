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
define('DB_HOST',     'localhost');
define('DB_NAME',     'snaptrack');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// ===== APP SETTINGS =====
define('APP_NAME',    'Studio 94 SnapTrack');
define('APP_URL',     'http://localhost/snaptrack');
define('UPLOAD_DIR',  __DIR__ . '/assets/uploads/');
define('UPLOAD_URL',  APP_URL . '/assets/uploads/');

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
define('MAIL_USERNAME',  'thisis.studio94official@gmail.com');  // ← PALITAN MO
define('MAIL_PASSWORD',  'xxxxxxxxxxxxxxxx');                   // ← Gmail App Password (16 chars, NO spaces)
define('MAIL_FROM_NAME', 'Studio 94');

// ============================================================
// EMAIL VERIFICATION SETTINGS
// ============================================================
define('VERIFICATION_EXPIRY_HOURS', 24);  // Verification link valid for 24 hours

// ============================================================
// ERROR REPORTING — DEBUG MODE (temporary)
// ============================================================
// ⚠️ IMPORTANT: I-set to '0' kapag production na
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-errors.log');