<?php
// ============================================================
// STUDIO 94 — Landing Page (Fixed for Existing System)
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
    $bookingUrl = $baseUrl . '/login.php?tab=login&redirect=' . urlencode($baseUrl . '/index.php?page=booking');
    $accountUrl = $baseUrl . '/login.php?tab=login&redirect=' . urlencode($baseUrl . '/index.php?page=dashboard');
}

// ---- Feedback message (optional) ----
$feedbackMsg = '';
$feedbackType = '';
if (isset($_GET['feedback'])) {
    if ($_GET['feedback'] === 'success') {
        $feedbackMsg = 'Thank you! Your feedback has been submitted successfully.';
        $feedbackType = 'success';
    } elseif ($_GET['feedback'] === 'error') {
        $feedbackMsg = 'Sorry, there was an error submitting your feedback. Please try again.';
        $feedbackType = 'error';
    }
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

    /* MOBILE MENU */
    .mobile-menu-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.4);
      z-index: 998; opacity: 0; visibility: hidden; transition: var(--transition-smooth);
    }
    .mobile-menu-overlay.active { opacity: 1; visibility: visible; }
    .mobile-menu-panel {
      position: fixed; top: 0; left: -280px; width: 280px; height: 100%;
      background: #fff; z-index: 999; padding: 40px 24px;
      box-shadow: 4px 0 20px rgba(0,0,0,0.1);
      transition: var(--transition-smooth);
      display: flex; flex-direction: column; gap: 20px;
    }
    .mobile-menu-panel.active { left: 0; }
    .menu-link {
      font-size: 14px; font-weight: 600; text-transform: uppercase;
      letter-spacing: 1.5px; padding: 10px 0;
      border-bottom: 1px solid #f0f0f0; color: #333;
      transition: var(--transition-smooth);
    }
    .menu-link:hover { color: #000; padding-left: 6px; }

    /* HERO */
    .hero-section { padding: 20px 0 50px; text-align: center; }
    .notebook-container { max-width: 620px; margin: 0 auto; perspective: 1000px; }
    .notebook-img {
      width: 100%; height: auto;
      filter: drop-shadow(0 15px 25px rgba(0,0,0,0.12));
      transition: transform 0.4s ease; border-radius: 4px;
    }
    .notebook-img:hover { transform: translateY(-4px) scale(1.01); }
    .hero-cta { margin-top: 36px; }
    .btn-appointment {
      display: inline-block;
      background-color: #000000; color: #ffffff;
      font-size: 13.5px; font-weight: 500; letter-spacing: 0.4px;
      padding: 13px 32px; border-radius: 8px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.15);
      transition: var(--transition-smooth);
    }
    .btn-appointment:hover {
      background-color: #222222;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.22);
    }

    /* CATEGORIES */
    .categories-section { padding: 40px 0 70px; }
    .categories-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px; }
    .category-card {
      display: flex; flex-direction: column; align-items: center;
      text-align: center; transition: transform 0.3s ease;
    }
    .category-card:hover { transform: translateY(-6px); }
    .card-photo-wrapper {
      width: 100%; border-radius: 2px; overflow: hidden;
      box-shadow: 0 8px 24px rgba(0,0,0,0.08);
      transition: box-shadow 0.3s ease; background: #ffffff;
    }
    .category-card:hover .card-photo-wrapper { box-shadow: 0 14px 30px rgba(0,0,0,0.14); }
    .card-photo-wrapper img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; }
    .category-title {
      font-size: 12.5px; font-weight: 500; letter-spacing: 1.8px;
      color: #222222; margin-top: 18px; text-transform: uppercase;
    }

    /* STEPS */
    .steps-section { padding: 30px 0 80px; }
    .steps-title {
      text-align: center; font-size: 34px; font-weight: 300;
      letter-spacing: -0.3px; color: #111111; margin-bottom: 6px;
    }
    .steps-subtitle {
      text-align: center; font-size: 15px; font-weight: 300;
      color: #555555; margin-bottom: 38px;
    }
    .steps-box-outer { background-color: var(--color-steps-bg); border-radius: 12px; padding: 32px 30px; }
    .steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
    .step-card {
      background-color: #ffffff; border-radius: 8px;
      padding: 24px 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);
      display: flex; flex-direction: column; transition: var(--transition-smooth);
    }
    .step-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,0.08); transform: translateY(-2px); }
    .step-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .step-icon {
      flex-shrink: 0; width: 24px; height: 24px;
      display: flex; align-items: center; justify-content: center; color: #222;
    }
    .step-heading { font-size: 13.5px; font-weight: 700; color: #111111; line-height: 1.3; }
    .step-desc { font-family: var(--font-body); font-size: 13px; color: #555555; line-height: 1.55; }

    /* OFFERS */
    .offers-section { padding: 20px 0 90px; }
    .section-headline {
      text-align: center; font-size: 26px; font-weight: 400;
      letter-spacing: 4px; color: #111111; margin-bottom: 40px;
      text-transform: uppercase;
    }
    .offers-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; }
    .offer-block {
      min-height: 380px; border-radius: 2px;
      display: flex; align-items: center; justify-content: center;
      padding: 20px; text-align: center;
      transition: transform 0.35s ease, box-shadow 0.35s ease;
      cursor: pointer; position: relative; overflow: hidden;
    }
    .offer-block:hover { transform: translateY(-8px); box-shadow: 0 16px 32px rgba(0,0,0,0.12); }
    .offer-title {
      font-size: 20px; font-weight: 800;
      letter-spacing: 1.2px; text-transform: uppercase;
      line-height: 1.3; user-select: none;
    }
    .offer-pink { background-color: var(--color-package-pink); color: #ffffff; }
    .offer-green { background-color: var(--color-package-green); color: #ffffff; }
    .offer-cream { background-color: var(--color-package-cream); color: var(--color-package-cream-text); }
    .offer-blue { background-color: var(--color-package-blue); color: #ffffff; }

    /* ====== FEEDBACK SECTION (NEW) ====== */
    .feedback-section {
      padding: 60px 0 80px;
      background: #fafafa;
      border-top: 1px solid var(--color-border);
      border-bottom: 1px solid var(--color-border);
    }
    .feedback-inner {
      max-width: 680px;
      margin: 0 auto;
      text-align: center;
    }
    .feedback-title {
      font-size: 30px;
      font-weight: 400;
      letter-spacing: 1px;
      color: #111;
      margin-bottom: 8px;
    }
    .feedback-subtitle {
      font-family: var(--font-body);
      font-size: 14.5px;
      color: #666;
      margin-bottom: 28px;
      line-height: 1.6;
    }
    .feedback-alert {
      padding: 12px 18px;
      border-radius: 8px;
      font-size: 13.5px;
      font-weight: 500;
      margin-bottom: 20px;
      text-align: left;
    }
    .feedback-alert.success { background: #e6f4ea; color: #2e7d32; border-left: 4px solid #2e7d32; }
    .feedback-alert.error { background: #fdecea; color: #c62828; border-left: 4px solid #c62828; }
    .feedback-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
      text-align: left;
    }
    .feedback-form label {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #333;
      margin-bottom: 4px;
      display: block;
    }
    .feedback-form .form-group {
      display: flex;
      flex-direction: column;
    }
    .feedback-form input,
    .feedback-form textarea {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--color-border);
      border-radius: 8px;
      font-family: var(--font-body);
      font-size: 14px;
      color: #222;
      background: #fff;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .feedback-form input:focus,
    .feedback-form textarea:focus {
      outline: none;
      border-color: #111;
      box-shadow: 0 0 0 3px rgba(0,0,0,0.06);
    }
    .feedback-form textarea { resize: vertical; min-height: 110px; }
    .star-rating {
      display: flex;
      gap: 6px;
      font-size: 28px;
      color: #ccc;
      user-select: none;
    }
    .star-rating span {
      cursor: pointer;
      transition: color 0.15s ease, transform 0.15s ease;
    }
    .star-rating span.active,
    .star-rating span:hover,
    .star-rating span:hover ~ span {
      color: #ffb400;
    }
    .star-rating span:hover { transform: scale(1.1); }
    .feedback-submit {
      margin-top: 6px;
      background: #000;
      color: #fff;
      padding: 13px 28px;
      border-radius: 8px;
      font-size: 13.5px;
      font-weight: 600;
      letter-spacing: 0.5px;
      transition: var(--transition-smooth);
      align-self: flex-start;
    }
    .feedback-submit:hover {
      background: #222;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    /* ====== FOOTER (ENHANCED CONTACT & ICONS) ====== */
    .site-footer {
      background: #111;
      color: #eee;
      padding: 80px 24px 60px;
      text-align: center;
    }
    .footer-content {
      max-width: 720px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .footer-reach {
      font-family: var(--font-serif);
      font-style: italic;
      font-size: 26px;
      font-weight: 500;
      color: #fff;
      margin-bottom: 10px;
      letter-spacing: 0.5px;
    }
    .footer-divider {
      width: 60px;
      height: 2px;
      background: #444;
      margin: 18px auto 26px;
      border-radius: 2px;
    }
    .footer-email {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: #fff;
      text-decoration: none;
      padding: 10px 18px;
      border: 1px solid #333;
      border-radius: 30px;
      margin-bottom: 26px;
      transition: var(--transition-smooth);
    }
    .footer-email:hover {
      background: #fff;
      color: #111;
      border-color: #fff;
      transform: translateY(-2px);
    }
    .footer-email svg { width: 16px; height: 16px; }
    .footer-address {
      font-size: 12.5px;
      font-weight: 500;
      letter-spacing: 1px;
      line-height: 1.8;
      text-transform: uppercase;
      color: #bbb;
      margin-bottom: 34px;
    }
    .footer-socials {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 18px;
      margin-top: 6px;
    }
    .social-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      border-radius: 50%;
      border: 1px solid #333;
      color: #eee;
      background: transparent;
      transition: var(--transition-smooth);
    }
    .social-btn:hover {
      background: #fff;
      color: #111;
      border-color: #fff;
      transform: translateY(-4px) scale(1.05);
      box-shadow: 0 8px 18px rgba(255,255,255,0.12);
    }
    .social-btn svg { width: 20px; height: 20px; }
    .footer-note {
      margin-top: 34px;
      font-size: 11px;
      letter-spacing: 1px;
      color: #777;
      text-transform: uppercase;
    }

    /* RESPONSIVE */
    @media (max-width: 992px) {
      .categories-grid { grid-template-columns: repeat(2, 1fr); gap: 24px; }
      .steps-grid { grid-template-columns: 1fr; gap: 16px; }
      .offers-grid { grid-template-columns: repeat(2, 1fr); gap: 18px; }
      .offer-block { min-height: 280px; }
    }
    @media (max-width: 600px) {
      .categories-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
      .category-title { font-size: 11px; letter-spacing: 1px; }
      .steps-title { font-size: 26px; }
      .steps-subtitle { font-size: 13.5px; margin-bottom: 24px; }
      .steps-box-outer { padding: 20px 16px; }
      .offers-grid { grid-template-columns: 1fr; }
      .offer-block { min-height: 200px; }
      .section-headline { font-size: 22px; letter-spacing: 3px; }
      .footer-email { font-size: 12px; word-break: break-all; padding: 9px 14px; }
      .feedback-title { font-size: 24px; }
      .star-rating { font-size: 24px; }
    }
  </style>
</head>
<body>

  <!-- NAVIGATION -->
  <header class="site-header">
    <div class="container">
      <nav class="nav-bar" aria-label="Main Navigation">
        <button class="nav-btn menu-toggle" id="menuToggle" aria-label="Open Navigation Menu">
          <svg width="22" height="18" viewBox="0 0 22 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <line x1="1" y1="2" x2="21" y2="2"></line>
            <line x1="1" y1="9" x2="21" y2="9"></line>
            <line x1="1" y1="16" x2="21" y2="16"></line>
          </svg>
        </button>

        <a href="landing.php" class="logo-container" aria-label="Studio 94 Home">
          <img src="<?= $baseUrl ?>/assets/logo.png" alt="Studio 94 EST.24" class="brand-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
          <div class="brand-logo-text" style="display: none;">
            <span class="mark">S</span> STUDIO 94®
            <span class="brand-sub">EST.24</span>
          </div>
        </a>

        <a href="<?= htmlspecialchars($accountUrl) ?>" class="nav-btn user-btn" aria-label="User Account">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </a>
      </nav>
    </div>
  </header>

  <!-- MOBILE MENU -->
  <div class="mobile-menu-overlay" id="menuOverlay"></div>
  <aside class="mobile-menu-panel" id="menuPanel" aria-label="Side Navigation">
    <a href="landing.php" class="menu-link">Home</a>
    <a href="#booking" class="menu-link">How It Works</a>
    <a href="#offers" class="menu-link">Packages</a>
    <a href="#feedback" class="menu-link">Feedback</a>
    <a href="#contact" class="menu-link">Contact</a>
  </aside>

  <main>
    <!-- HERO -->
    <section class="hero-section">
      <div class="container">
        <div class="notebook-container">
          <img src="<?= $baseUrl ?>/assets/hero-notebook.jpg" alt="Studio 94 Lookbook Notebook" class="notebook-img" />
        </div>
        <div class="hero-cta">
          <a href="<?= htmlspecialchars($bookingUrl) ?>" class="btn-appointment">Book an appointment</a>
        </div>
      </div>
    </section>

    <!-- CATEGORIES -->
    <section class="categories-section" aria-label="Photo Portfolios">
      <div class="container">
        <div class="categories-grid">
          <article class="category-card">
            <div class="card-photo-wrapper">
              <img src="<?= $baseUrl ?>/assets/card-1.png" alt="Celebrating You Portrait" />
            </div>
            <h3 class="category-title">CELEBRATING YOU</h3>
          </article>
          <article class="category-card">
            <div class="card-photo-wrapper">
              <img src="<?= $baseUrl ?>/assets/card-2.png" alt="Little Life Couple Portrait" />
            </div>
            <h3 class="category-title">LITTLE LIFE</h3>
          </article>
          <article class="category-card">
            <div class="card-photo-wrapper">
              <img src="<?= $baseUrl ?>/assets/card-3.png" alt="Family Portrait" />
            </div>
            <h3 class="category-title">FOR FAMILY</h3>
          </article>
          <article class="category-card">
            <div class="card-photo-wrapper">
              <img src="<?= $baseUrl ?>/assets/card-4.png" alt="Graduation and Group Portrait" />
            </div>
            <h3 class="category-title">FOR EVERYONE</h3>
          </article>
        </div>
      </div>
    </section>

    <!-- STEPS -->
    <section class="steps-section" id="booking">
      <div class="container">
        <h2 class="steps-title">Self-portraits made easy</h2>
        <p class="steps-subtitle">follow these steps to book a session</p>

        <div class="steps-box-outer">
          <div class="steps-grid">
            <div class="step-card">
              <div class="step-header">
                <div class="step-icon">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                </div>
                <h4 class="step-heading">Step 1: Select your schedule</h4>
              </div>
              <p class="step-desc">Choose your schedule and capture your moment—select a date and time for your self-shoot session.</p>
            </div>

            <div class="step-card">
              <div class="step-header">
                <div class="step-icon">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                    <circle cx="12" cy="13" r="4"></circle>
                  </svg>
                </div>
                <h4 class="step-heading">Step 2: Choose your package</h4>
              </div>
              <p class="step-desc">Choose your preferred package and add - ons.</p>
            </div>

            <div class="step-card">
              <div class="step-header">
                <div class="step-icon">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 11 12 14 22 4"></polyline>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                  </svg>
                </div>
                <h4 class="step-heading">Step 3: Secure your spot</h4>
              </div>
              <p class="step-desc">Proceed to checkout and complete your downpayment. you'll receive an email once booking is confirmed</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- OFFERS -->
    <section class="offers-section" id="offers">
      <div class="container">
        <h2 class="section-headline">WHAT WE OFFER</h2>
        <div class="offers-grid">
          <div class="offer-block offer-pink">
            <span class="offer-title">SELF - SHOOT</span>
          </div>
          <div class="offer-block offer-green">
            <span class="offer-title">CREATIVE PACKAGE</span>
          </div>
          <div class="offer-block offer-cream">
            <span class="offer-title">FAMILY PACKAGE</span>
          </div>
          <div class="offer-block offer-blue">
            <span class="offer-title">STUDIO RENTAL</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ========== FEEDBACK SECTION (NEW) ========== -->
    <section class="feedback-section" id="feedback">
      <div class="container">
        <div class="feedback-inner">
          <h2 class="feedback-title">We value your feedback</h2>
          <p class="feedback-subtitle">Tell us about your experience with Studio 94. Your feedback helps us improve our services.</p>

          <?php if (!empty($feedbackMsg)): ?>
            <div class="feedback-alert <?= $feedbackType ?>">
              <?= htmlspecialchars($feedbackMsg) ?>
            </div>
          <?php endif; ?>

          <!-- ⚠️ PALITAN ITO NG EXISTING FEEDBACK HANDLER MO -->
          <form class="feedback-form" action="<?= $baseUrl ?>/index.php?page=feedback" method="POST">
            <input type="hidden" name="source" value="landing" />

            <div class="form-group">
              <label>Your Rating</label>
              <div class="star-rating" id="starRating" role="radiogroup" aria-label="Star rating">
                <span data-value="1" role="radio" aria-checked="false" tabindex="0">★</span>
                <span data-value="2" role="radio" aria-checked="false" tabindex="0">★</span>
                <span data-value="3" role="radio" aria-checked="false" tabindex="0">★</span>
                <span data-value="4" role="radio" aria-checked="false" tabindex="0">★</span>
                <span data-value="5" role="radio" aria-checked="false" tabindex="0">★</span>
              </div>
              <input type="hidden" name="rating" id="ratingValue" required />
            </div>

            <div class="form-group">
              <label for="fbName">Name (optional)</label>
              <input type="text" id="fbName" name="name" placeholder="Leave blank to stay anonymous" maxlength="80" />
            </div>

            <div class="form-group">
              <label for="fbEmail">Email (optional)</label>
              <input type="email" id="fbEmail" name="email" placeholder="you@example.com" maxlength="120" />
            </div>

            <div class="form-group">
              <label for="fbComment">Your Feedback</label>
              <textarea id="fbComment" name="comment" placeholder="Share your experience with us..." required maxlength="1000"></textarea>
            </div>

            <button type="submit" class="feedback-submit">Submit Feedback</button>
          </form>
        </div>
      </div>
    </section>

  </main>

  <!-- FOOTER (ENHANCED CONTACT & ICONS) -->
  <footer class="site-footer" id="contact">
    <div class="footer-content">
      <p class="footer-reach">Reach us here</p>
      <div class="footer-divider"></div>

      <a href="mailto:thisis.studio94official@gmail.com" class="footer-email">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
          <polyline points="22,6 12,13 2,6"></polyline>
        </svg>
        thisis.studio94official@gmail.com
      </a>

      <div class="footer-address">
        2F SBD BUILDING, HIGHWAY 1, SAN ISIDRO<br />
        POBLACION, NABUA, CAMARINES SUR 4434
      </div>

      <div class="footer-socials">
        <a href="https://www.facebook.com/studio94official" target="_blank" rel="noopener noreferrer" class="social-btn" aria-label="Facebook Page">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
          </svg>
        </a>
        <a href="https://www.instagram.com/thisis.studio94" target="_blank" rel="noopener noreferrer" class="social-btn" aria-label="Instagram Profile">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
          </svg>
        </a>
      </div>

      <p class="footer-note">© <?= date('Y') ?> Studio 94 — All rights reserved</p>
    </div>
  </footer>

  <script>
    // ---- Mobile menu ----
    const menuToggle = document.getElementById('menuToggle');
    const menuPanel = document.getElementById('menuPanel');
    const menuOverlay = document.getElementById('menuOverlay');

    function toggleMenu() {
      menuPanel.classList.toggle('active');
      menuOverlay.classList.toggle('active');
      document.body.style.overflow = menuPanel.classList.contains('active') ? 'hidden' : '';
    }

    menuToggle.addEventListener('click', toggleMenu);
    menuOverlay.addEventListener('click', toggleMenu);

    document.querySelectorAll('.menu-link').forEach(link => {
      link.addEventListener('click', () => {
        menuPanel.classList.remove('active');
        menuOverlay.classList.remove('active');
        document.body.style.overflow = '';
      });
    });

    // ---- Star rating ----
    const starContainer = document.getElementById('starRating');
    const ratingInput = document.getElementById('ratingValue');
    const stars = starContainer.querySelectorAll('span');

    function setRating(value) {
      ratingInput.value = value;
      stars.forEach((star, index) => {
        const isActive = index < value;
        star.classList.toggle('active', isActive);
        star.setAttribute('aria-checked', isActive ? 'true' : 'false');
      });
    }

    stars.forEach((star, index) => {
      star.addEventListener('click', () => setRating(index + 1));
      star.addEventListener('mouseenter', () => {
        stars.forEach((s, i) => s.classList.toggle('active', i <= index));
      });
      star.addEventListener('mouseleave', () => {
        const current = parseInt(ratingInput.value || 0, 10);
        stars.forEach((s, i) => s.classList.toggle('active', i < current));
      });
      star.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          setRating(index + 1);
        }
      });
    });
  </script>
</body>
</html>
