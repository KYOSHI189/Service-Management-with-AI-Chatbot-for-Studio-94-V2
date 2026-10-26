<?php
// ============================================================
// STUDIO 94 — Landing Page
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

startSession();

// ---- Redirect targets based on login state ----
$loggedIn = isLoggedIn();
$baseUrl  = defined('APP_URL') ? rtrim(APP_URL, '/') : '';

if ($loggedIn) {
    $role = $_SESSION['role'] ?? 'client';
    $bookingUrl = ($role === 'client')
        ? $baseUrl . '/index.php?page=booking'
        : $baseUrl . '/index.php?page=walkin';
    $accountUrl = $baseUrl . '/index.php?page=dashboard';
} else {
    // Public user → login page, then redirect back
    $bookingUrl = $baseUrl . '/login.php?tab=login&redirect=' . urlencode($baseUrl . '/index.php?page=booking');
    $accountUrl = $baseUrl . '/login.php?tab=login&redirect=' . urlencode($baseUrl . '/index.php?page=dashboard');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>STUDIO 94 — Self-Shoot Studio</title>
 
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@1,400;1,500;1,600&family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

  <style>
    :root {
      --font-primary: 'Montserrat', sans-serif;
      --font-body: 'Plus Jakarta Sans', sans-serif;
      --font-serif: 'Cormorant Garamond', Georgia, serif;
      --color-bg: #ffffff;
      --color-text-main: #1a1a1a;
      --color-text-muted: #666666;
      --color-text-light: #888888;
      --color-package-pink: #ffd3d4;
      --color-package-green: #4d7a51;
      --color-package-cream: #fff6e9;
      --color-package-blue: #5990cc;
      --color-package-cream-text: #382315;
      --color-steps-bg: #f6f4f4;
      --color-footer-bg: #b6b6b6;
      --color-border: #e8e8e8;
      --transition-smooth: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font-primary);
      background-color: var(--color-bg);
      color: var(--color-text-main);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
    }
    img { max-width: 100%; height: auto; display: block; }
    a { text-decoration: none; color: inherit; }
    button { background: none; border: none; cursor: pointer; font-family: inherit; }
    .container { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 24px; }

    /* HEADER */
    .site-header {
      position: sticky; top: 0;
      background-color: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(10px);
      z-index: 1000;
      padding: 22px 0 16px;
    }
    .nav-bar { display: flex; align-items: center; justify-content: space-between; }
    .nav-btn {
      display: inline-flex; align-items: center; justify-content: center;
      color: #111; padding: 8px; border-radius: 50%;
      transition: var(--transition-smooth);
    }
    .nav-btn:hover { background-color: rgba(0,0,0,0.05); transform: scale(1.05); }
    .logo-container { display: flex; flex-direction: column; align-items: center; }
    .brand-logo-img { height: 28px; width: auto; object-fit: contain; }
    .brand-logo-text {
      display: flex; align-items: center; gap: 8px;
      font-size: 20px; font-weight: 800; letter-spacing: 2px;
      color: #000; text-transform: uppercase;
    }
    .brand-logo-text .mark { background: #000; color: #fff; padding: 1px 6px; font-size: 15px; font-weight: 900; }
    .brand-sub { font-size: 8.5px; font-weight: 600; letter-spacing: 1.5px; color: #333; margin-top: 2px; }
