<?php
// ============================================================
// GOOGLE CALLBACK — Auto-verify Google users
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/google-config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// SAFE SESSION START
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEBUG LOG
$debug  = "=== " . date('Y-m-d H:i:s') . " ===\n";
$debug .= "GET params: " . print_r($_GET, true) . "\n";
$debug .= "Session BEFORE: " . print_r($_SESSION, true) . "\n";

// NO CODE → ERROR
if (!isset($_GET['code'])) {
    $debug .= "ERROR: Walang code na natanggap.\n\n";
    @file_put_contents(__DIR__ . '/google-debug.log', $debug, FILE_APPEND);

    if (isset($_GET['error'])) {
        die("Google Error: " . htmlspecialchars($_GET['error']) .
            "<br>Description: " . htmlspecialchars($_GET['error_description'] ?? 'N/A'));
    }

    header('Location: login.php');
    exit;
}

try {
    // SETUP GOOGLE CLIENT
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);

    // EXCHANGE CODE FOR TOKEN
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $debug .= "Token response: " . print_r($token, true) . "\n";

    if (isset($token['error'])) {
        $debug .= "TOKEN ERROR: " . $token['error'] . "\n\n";
        @file_put_contents(__DIR__ . '/google-debug.log', $debug, FILE_APPEND);
        die("Error fetching token: " . htmlspecialchars($token['error']) .
            "<br>Description: " . htmlspecialchars($token['error_description'] ?? 'N/A'));
    }

    $client->setAccessToken($token);

    // GET USER INFO FROM GOOGLE
    $google_oauth = new Google\Service\Oauth2($client);
    $user_info    = $google_oauth->userinfo->get();

    $email    = $user_info->email;
    $name     = $user_info->name;
    $googleId = $user_info->id;

    $debug .= "Google User: email=$email, name=$name, google_id=$googleId\n";

    // MUST BE GMAIL ONLY
    if (!preg_match('/@gmail\.com$/i', $email)) {
        $debug .= "REJECTED: Not a Gmail address.\n\n";
        @file_put_contents(__DIR__ . '/google-debug.log', $debug, FILE_APPEND);

        $_SESSION['oauth_error'] = 'Only Gmail accounts are allowed.';
        header('Location: login.php');
        exit;
    }

    // CHECK IF USER EXISTS
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $debug .= "User in DB: " . ($user ? "YES (id=" . $user['id'] . ")" : "NO") . "\n";

    if (!$user) {
        // ============================================================
        // NEW USER — auto-verified (Google na ang nag-verify)
        // ============================================================
        $randomHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $ins = db()->prepare('INSERT INTO users (name, email, password, google_id, role, is_active, email_verified, created_at) VALUES (?, ?, ?, ?, "client", 1, 1, NOW())');
        $ins->execute([$name, $email, $randomHash, $googleId]);

        // Get new user
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $debug .= "Created new user via Google: id=" . $user['id'] . " (auto-verified)\n";
    } else {
        // ============================================================
        // EXISTING USER — update google_id + mark as verified
        // ============================================================
        $upd = db()->prepare('UPDATE users SET google_id = ?, email_verified = 1 WHERE id = ?');
        $upd->execute([$googleId, $user['id']]);

        $debug .= "Updated existing user: google_id set, marked verified\n";
    }

    // SET SESSION
    session_regenerate_id(true);

    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['role']        = strtolower($user['role']);
    $_SESSION['user_phone']  = $user['phone'] ?? '';
    $_SESSION['user_avatar'] = $user['avatar'] ?? null;

    $debug .= "Session AFTER: " . print_r($_SESSION, true) . "\n";
    $debug .= "=== SUCCESS — redirecting to dashboard ===\n\n";

    @file_put_contents(__DIR__ . '/google-debug.log', $debug, FILE_APPEND);

    header('Location: index.php?page=dashboard&role=' . urlencode($_SESSION['role']));
    exit;

} catch (Exception $e) {
    $debug .= "EXCEPTION: " . $e->getMessage() . "\n";
    $debug .= "Stack: " . $e->getTraceAsString() . "\n\n";
    @file_put_contents(__DIR__ . '/google-debug.log', $debug, FILE_APPEND);

    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>