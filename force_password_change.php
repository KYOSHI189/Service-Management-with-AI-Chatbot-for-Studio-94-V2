# ============================================================
# STUDIO 94 SNAPTRACK — Temporary password reset (ADMIN ONLY)
# ============================================================
//
// WHY THIS FILE EXISTS:
// change_password.php set EVERY account to the shared password
// "password". That file has been removed. This script does the
// opposite: it forces a password change on first login instead of
// assigning a shared credential nobody would keep.
//
// HOW TO USE:
//   1. Run it ONCE from a trusted machine (or delete after use).
//   2. Every account is flagged must_change_password = 1 and all
//      existing sessions are invalidated.
//   3. Users are forced to set their own password at next login.
//   4. DELETE THIS FILE once every real user has reset.
//
// This script does NOT take a password argument on purpose, so it
// cannot be repurposed to mass-reset accounts to a known value.
//
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// --- Hard guard: only run when explicitly enabled in the environment ---
$resetKey = $_ENV['ADMIN_RESET_KEY'] ?? getenv('ADMIN_RESET_KEY') ?: '';
if ($resetKey === '' || !hash_equals($resetKey, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    die('Forbidden. Set ADMIN_RESET_KEY in the environment and pass ?key=<value>.');
}

$pdo = db();

// Add the column if the schema predates it.
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {
    // Column already exists - fine.
}

// Flag every account and drop all active sessions.
$pdo->exec("UPDATE users SET must_change_password = 1");

try {
    $pdo->exec("DELETE FROM sessions");
} catch (Throwable $e) {
    // No sessions table - fine, sessions are file-based.
}

$count = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

echo "<h2>Password reset required</h2>";
echo "<p>Flagged <strong>{$count}</strong> account(s) as needing a password change.</p>";
echo "<p>All existing sessions were invalidated. The next person to log in at any account "
   . "will be required to set a new password before reaching the app.</p>";
echo "<p style='color:#b00'><strong>Delete this file now.</strong></p>";