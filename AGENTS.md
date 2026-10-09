# SnapTrack — Agent Notes

Procedural PHP 8.1+ / MySQL studio booking app. **No build step, no test suite, no linter, no CI** (`.github/` absent). Verify changes by loading the page in a browser.

## Setup

```bash
composer install          # vendor/ is gitignored but required at runtime
php -S localhost:8000     # or XAMPP/Laragon
```
- Browser DB installer: `sql/install.php` (raw schema: `sql/install.sql`).
- Required PHP exts: `pdo`, `pdo_mysql`, `mysqli`. Nixpacks adds `gd,curl,mbstring`.
- **`php` is not on PATH on this machine** — you cannot run `php -l` here. State this rather than claiming a file parses.

## Routing — the highest-value thing to understand

`index.php?page=<name>` is the single entrypoint for all authenticated UI.

- `index.php` loads `config.php` + `functions.php`, re-reads the user from DB, validates the page against the `$rolePages` allowlist, then resolves `pages/<role>/<page>.php` → `pages/<page>.php` → other roles' dirs → dashboard.
- **Unlisted pages silently redirect to the dashboard.** No error is shown; the router only `error_log`s. If a new page 404s into the dashboard, the allowlist is the first thing to check.
- **Files in `pages/<role>/` are NOT standalone.** They call `requireRole(...)` at line 2 with no `require_once` — they depend on the router having already defined `$role`, `$page`, `$user`. Opening one directly is a fatal error.
- **Files in `pages/api/` ARE standalone.** Each self-requires `../../config.php` + `../../functions.php` and does its own auth check.

**Adding a page touches 3 files:** the `$rolePages` allowlist and `$pageMeta` in `index.php`, plus the `$navAdmin`/`$navStaff`/`$navClient` array in `sidebar.php`.

`requireRole()` takes a string or array, is case-insensitive, and redirects to the dashboard (not login) on failure.

## Config & env

- `config.php` is the only env reader — a hand-rolled `loadEnv()`, no dotenv. Values populate `$_ENV` and `putenv()`.
- DB config falls back to Railway-style `MYSQLHOST`/`MYSQLPORT`/`MYSQLDATABASE`/`MYSQLUSER`/`MYSQLPASSWORD` when `DB_*` is unset.
- `APP_URL` is auto-detected from request headers when unset. Base-path detection **only** special-cases `/snaptrack` — deploying under any other subfolder breaks every generated URL.
- Several `define()`s are **not** env-driven and `.env` will not change them: `GEMINI_MODEL`, `MAIL_HOST`, `MAIL_PORT`, `UPLOAD_DIR`, `SESSION_LIFETIME`.
- Session cookie `secure` is hardcoded `false` in `startSession()`.

## Schema

- `ensureDatabaseSchema()` runs on the **first `db()` call of every request**: it `ALTER`s missing columns and `MODIFY`s ENUMs on `users`, `bookings`, `payments`, `packages`. Most column additions need no migration file — but it also means the app **mutates the schema at runtime** and needs ALTER privileges. Failures are swallowed to `php-errors.log`.
- Enum changes must be made in **both** `sql/install.sql` and `ensureDatabaseSchema()`, or fresh installs and migrated installs diverge.
- Packages live in the **DB `packages` table** (managed by `pages/admin/packages.php`, read via `getPackageById()`). The `PACKAGES` and `LOYALTY_TIERS` constants in `config.php` are **dead — zero consumers**.
- **Loyalty tiers (2/4/7/10) are duplicated in three places**, all of which must stay in sync: `getLoyaltyTier()` / `getLoyaltyRewards()` / `getLoyaltyProgress()` in `functions.php`; inline thresholds in `pages/admin/loyalty-cards.php` (filters + `getLoyaltyBadge()` / `getProgressToNextTier()`); and duplicated thresholds in `pages/api/check-client-loyalty.php`, which calls neither helper.

## Gotchas

- **`display_errors` is off** (`config.php` tail). Fatals render as a blank page — read `php-errors.log` at the repo root. `error_reporting(E_ALL)` is left on despite the adjacent "set to 0 in production" comment.
- **Push notifications silently no-op.** `functions.php` uses `Minishlink\WebPush\*`, but `minishlink/web-push` is absent from both `composer.json` and `vendor/`. The `class_exists()` guard swallows it — no error, no notifications.
- **PDF libs are hand-vendored** in `includes/tcpdf` and `includes/fpdf`, not via composer. Don't assume composer knows about them.
- **Uploads are scattered** across `uploads/`, `assets/uploads/`, `assets/uploads/avatars/`, `pages/assets/uploads/avatars/`, `pages/upload/payments/`, `pages/uploads/payment/`. Only `UPLOAD_DIR` (`assets/uploads/`) is a defined constant. Check which directory a feature actually writes to before assuming `UPLOAD_DIR`.
- **Root `styles.css` is dead.** Only `assets/css/styles.css` is loaded (by `header.php`, `login.php`, `mfa-setup.php`); `assets/js/app.js` is loaded by `footer.php`. Edit those, not the root copy.
- **`sql/` is NOT tracked in git** (removed in `40cefcf3`), so the installer is not deployed. If you re-add it, note `sql/install.php` requires `ADMIN_RESET_KEY` in the environment plus `?key=<value>` — do not strip that guard.
- `pages/api/get-day-bookings.php` is a 0-byte empty file.

### Dead and broken code (verified)

- **`includes/deduct_inventory.php` is dead** — nothing requires it. The live path is `deductInventoryOnComplete()` in `functions.php`. It also calls `addNotificationByRole()` without requiring `functions.php`.
- **`includes/booking.php` is broken** — `saveBooking()`, `getBookings()` and `getBookingStats()` call `getDBConnection()`, which is **defined nowhere in the project** (the only DB accessor is `db()`). It also never requires `functions.php` despite calling `addNotificationByRole()`. The `catch` only handles `PDOException`, so the PHP `Error` is unhandled.
- **`pages/client/booking.php` redefines `checkBookingConflict()`** at line 45, shadowing `functions.php:1232`. Its copy is dead on the normal router path.
- **CSRF is missing** on three admin POST handlers: `pages/admin/profile.php`, `pages/admin/reports.php`, `pages/admin/sales.php` — no `csrfField()` / `verifyCsrf()` pair.
- **Dead duplicates:** root `styles.css` and `assets/images/css/styles.css` are byte-identical to each other and differ from the live `assets/css/styles.css`; root `app.js` (1893 lines) is dead because only `assets/js/app.js` loads; `pages/admin/DFD.HTML` is a design mockup that the router can never serve anyway (it always appends `.php`).

### Security issues found

**Incident (2026-10-09):** `change_password.php` was deployed and reset every account to the shared password `password` on a plain unauthenticated GET. It was removed in the cleanup commit. If you see this file in history, the password reset already happened — use `force_password_change.php` (needs `ADMIN_RESET_KEY`) to force users to set their own, then delete that script too.

- **`pages/api/chatbot-ai.php`** used to set `Access-Control-Allow-Origin: *` on an unauthenticated endpoint and disable TLS verification while sending the Gemini API key. Now requires a logged-in user and verifies TLS. **Treat the Gemini key as exposed — rotate it.**
- **`pages/api/check-client-loyalty.php`** returns raw exception text to the client.
- **`login.php` auto-verifies accounts** when SMTP is unconfigured or blocked, which defeats the email-verification gate.

## Conventions

- **Prepared statements are mandatory.** `db()` sets `ATTR_EMULATE_PREPARES => false`, so you cannot bind an identifier or interpolate a value into `LIMIT`/`IN`.
- Escape on output with `clean()`; sanitize input with `cleanInt()` / `cleanFloat()`.
- POST handlers use `csrfField()` + `verifyCsrf()` — follow this for any new form.
- DB columns are `snake_case`; PHP functions are `camelCase`.
- Statements are formatted very airy (one argument per line, operators leading). Match it when editing surrounding code.

## Conventions from history

Single `main` branch, direct commits. Messages use imperative prefixes: `Fix ...`, `Add ...`, `Implement ...`, `Support ...`.