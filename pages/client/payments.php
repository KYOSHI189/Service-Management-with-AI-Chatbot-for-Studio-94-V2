<?php
requireRole('client');
// ================================================================
// My Payments — Client View (Black & White Theme)
// ================================================================
?>

<!-- ============================================================ -->
<!-- FULLY RESPONSIVE STYLES                                       -->
<!-- ============================================================ -->
<style>
/* ============================================================
   BASE (MOBILE-FIRST) — 320px pataas
   ============================================================ */

.page-banner {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    background: linear-gradient(135deg, #2C2C2C, #4A4A4A);
    color: #fff;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 14px;
    width: 100%;
    box-sizing: border-box;
}
.page-banner-text { width: 100%; min-width: 0; }
.page-banner .eyebrow {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    opacity: 0.6;
    margin-bottom: 6px;
}
.page-banner h2 {
    font-size: 18px;
    margin: 0 0 6px;
    line-height: 1.25;
    word-break: break-word;
}
.page-banner p {
    font-size: 12px;
    opacity: 0.8;
    margin: 0;
    line-height: 1.4;
}
.page-banner-art {
    font-size: 32px;
    align-self: flex-end;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-bottom: 14px;
}
.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    min-width: 0;
    box-sizing: border-box;
}
.stat-card .stat-eyebrow {
    font-size: 9px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 4px;
}
.stat-card .stat-value {
    font-family: serif;
    font-size: 20px;
    line-height: 1.1;
    color: var(--dark);
    margin-bottom: 2px;
    word-break: break-word;
}
.stat-card .stat-label {
    font-size: 10px;
    color: var(--muted);
}

.payment-methods-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    margin-bottom: 20px;
}

.payment-method-card {
    background: #FFFFFF;
    border: 2px solid #0A0A0A;
    border-radius: 14px;
    padding: 16px;
    box-sizing: border-box;
    min-width: 0;
}

.payment-method-card .pm-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #E0E0E0;
}
.payment-method-card .pm-header .pm-icon { font-size: 22px; }
.payment-method-card .pm-header .pm-title {
    font-size: 15px;
    color: #0A0A0A;
    font-weight: 700;
    word-break: break-word;
}

.payment-method-card .pm-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
}

.payment-method-card .qr-wrap {
    text-align: center;
    flex-shrink: 0;
    width: 100%;
}
.payment-method-card .qr-wrap img,
.payment-method-card .qr-wrap .qr-fallback {
    width: 140px;
    height: 140px;
    object-fit: contain;
    border-radius: 12px;
    border: 1px solid #E0E0E0;
    background: white;
    padding: 8px;
    display: block;
    margin: 0 auto;
    box-sizing: border-box;
}
.payment-method-card .qr-wrap .qr-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
}
.payment-method-card .qr-wrap .qr-label {
    font-size: 11px;
    color: #8B8177;
    margin-top: 8px;
}

.payment-method-card .pm-details {
    flex: 1;
    min-width: 0;
    width: 100%;
}
.payment-method-card .pm-field-label {
    font-size: 10px;
    color: #8B8177;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.payment-method-card .pm-field-value {
    font-weight: 700;
    font-size: 14px;
    color: #0A0A0A;
    margin-bottom: 14px;
    word-break: break-word;
}
.payment-method-card .pm-field-value.mono {
    font-family: monospace;
    font-size: 15px;
    letter-spacing: 0.5px;
}
.payment-method-card .pm-copy-btn {
    width: 100%;
    padding: 10px;
    background: #0A0A0A;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
    box-sizing: border-box;
}
.payment-method-card .pm-copy-btn:hover { opacity: 0.8; }

.card {
    padding: 14px;
    border-radius: 12px;
    box-sizing: border-box;
    min-width: 0;
}
.card-title {
    font-size: 14px;
    margin-bottom: 12px;
    font-weight: 700;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.section-header h3 {
    font-size: 15px;
    margin: 0;
    word-break: break-word;
}

#paymentsList {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-card {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: white;
    box-sizing: border-box;
    min-width: 0;
    transition: box-shadow 0.4s ease, border-color 0.4s ease, transform 0.4s ease;
}

.payment-card:target {
    border-color: #EAB308;
    box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.3), 0 8px 24px rgba(234, 179, 8, 0.15);
    transform: scale(1.01);
    animation: highlightPulse 1.5s ease-in-out;
}

@keyframes highlightPulse {
    0%, 100% {
        box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.3), 0 8px 24px rgba(234, 179, 8, 0.15);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(234, 179, 8, 0.5), 0 8px 30px rgba(234, 179, 8, 0.3);
    }
}

.payment-card .pc-header {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 12px;
}
.payment-card .pc-header-left { min-width: 0; }
.payment-card .pc-pkg-name {
    font-weight: 700;
    font-size: 15px;
    word-break: break-word;
    margin-bottom: 4px;
}
.payment-card .pc-session {
    font-size: 12px;
    color: var(--muted);
}
.payment-card .pc-header-right {
    text-align: left;
}
.payment-card .pc-price-main {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}
.payment-card .pc-price-strike {
    font-size: 11px;
    color: var(--green-text);
    text-decoration: line-through;
}
.payment-card .pc-badge-row { margin-top: 4px; }

.loyalty-pill {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 50px;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
}

.payment-card .pc-amounts {
    padding: 12px;
    background: var(--bg-soft);
    border-radius: 8px;
    margin-top: 12px;
}
.payment-card .pc-amount-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 13px;
    flex-wrap: wrap;
}
.payment-card .pc-amount-row + .pc-amount-row { margin-top: 4px; }
.payment-card .pc-amount-row .pc-label { color: var(--muted); }
.payment-card .pc-amount-row .pc-value { font-weight: 600; word-break: break-word; }
.payment-card .pc-amount-divider {
    border-top: 1px solid var(--border);
    margin: 8px 0;
}
.payment-card .pc-amount-total {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 14px;
    font-weight: 700;
    flex-wrap: wrap;
}
.payment-card .pc-amount-note {
    font-size: 11px;
    color: var(--muted);
    text-align: right;
    margin-top: 2px;
}

.payment-card .pc-history {
    margin-top: 12px;
    padding: 10px;
    background: #F8F9FA;
    border-radius: 8px;
}
.payment-card .pc-history-title {
    font-size: 10px;
    font-weight: 700;
    color: var(--muted);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.payment-card .pc-history-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
    padding: 4px 0;
    flex-wrap: wrap;
}

.payment-card .pc-form-wrap {
    margin-top: 12px;
    padding: 12px;
    background: #F5F5F5;
    border-radius: 8px;
    box-sizing: border-box;
}
.payment-card .pc-form-title {
    font-weight: 700;
    margin-bottom: 10px;
    color: #0A0A0A;
    font-size: 13px;
}
.payment-card .pc-form-info {
    margin-bottom: 12px;
    padding: 12px;
    background: white;
    border-radius: 8px;
    border: 1px solid #0A0A0A;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.payment-card .pc-form-info .pcfi-icon { font-size: 24px; flex-shrink: 0; }
.payment-card .pc-form-info .pcfi-body { min-width: 0; flex: 1; }
.payment-card .pc-form-info .pcfi-label {
    font-weight: 700;
    font-size: 13px;
    color: #0A0A0A;
    word-break: break-word;
}
.payment-card .pc-form-info .pcfi-value {
    font-size: 18px;
    color: #0A0A0A;
    font-weight: 800;
    word-break: break-word;
}
.payment-card .pc-form-info .pcfi-note {
    font-size: 11px;
    color: #666;
    line-height: 1.4;
}

.method-selector {
    margin-bottom: 12px;
}
.method-selector .method-label {
    font-weight: 600;
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
    color: #0A0A0A;
}
.method-selector .method-options {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.method-selector .method-option {
    flex: 1 1 120px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    border: 2px solid #E0E0E0;
    border-radius: 8px;
    cursor: pointer;
    background: transparent;
    transition: .2s;
    box-sizing: border-box;
    min-width: 0;
}
.method-selector .method-option.active {
    border-color: #0A0A0A;
    background: #F5F5F5;
}
.method-selector .method-option .mo-icon { font-size: 18px; flex-shrink: 0; }
.method-selector .method-option .mo-text {
    font-weight: 600;
    font-size: 13px;
    color: #666;
    word-break: break-word;
}
.method-selector .method-option.active .mo-text { color: #0A0A0A; }

.form-group { margin-bottom: 8px; }
.form-group label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #0A0A0A;
}
.form-group input[type="text"],
.form-group input[type="file"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #E0E0E0;
    border-radius: 8px;
    font-size: 13px;
    background: white;
    box-sizing: border-box;
    font-family: inherit;
}
.form-group input[type="file"] {
    padding: 8px;
    font-size: 12px;
}

.pc-submit-btn {
    background: #0A0A0A;
    color: #fff;
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    box-sizing: border-box;
}
.pc-submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.pc-status {
    margin-top: 12px;
    padding: 12px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
}
.pc-status.pending {
    background: #F5F5F5;
    border: 1px solid #0A0A0A;
    color: #0A0A0A;
}
.pc-status.rejected {
    background: #FFEEEE;
    border: 1px solid #E74C3C;
    color: #991B1B;
}
.pc-status.success {
    background: #F0FFF4;
    border: 1px solid #2E7D32;
    color: #1B5E20;
}

.policy-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.policy-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #F5F5F5;
    border-radius: 12px;
    border: 1px solid #0A0A0A;
    box-sizing: border-box;
    min-width: 0;
}
.policy-item .policy-icon {
    width: 44px;
    height: 44px;
    background: #0A0A0A;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}
.policy-item .policy-body { min-width: 0; flex: 1; }
.policy-item .policy-title {
    font-weight: 600;
    color: #0A0A0A;
    font-size: 13px;
    word-break: break-word;
}
.policy-item .policy-desc {
    font-size: 12px;
    color: #666;
    line-height: 1.4;
    word-break: break-word;
}

@media (min-width: 380px) {
    .page-banner h2 { font-size: 19px; }
    .stat-card .stat-value { font-size: 22px; }
    .payment-card .pc-pkg-name { font-size: 16px; }
    .payment-card .pc-price-main { font-size: 22px; }
    .payment-method-card .qr-wrap img,
    .payment-method-card .qr-wrap .qr-fallback {
        width: 150px;
        height: 150px;
    }
}

@media (min-width: 600px) {
    .page-banner {
        flex-direction: row;
        align-items: center;
        padding: 20px 24px;
        border-radius: 14px;
    }
    .page-banner h2 { font-size: 22px; }
    .page-banner-art { font-size: 40px; align-self: center; }

    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 16px;
    }
    .stat-card { padding: 14px 16px; border-radius: 12px; }
    .stat-card .stat-value { font-size: 24px; }

    .payment-methods-grid {
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .payment-method-card .pm-content {
        flex-direction: row;
        align-items: flex-start;
    }
    .payment-method-card .qr-wrap { width: auto; }
    .payment-method-card .qr-wrap img,
    .payment-method-card .qr-wrap .qr-fallback { margin: 0; }
    .payment-method-card .pm-details { width: auto; }

    .payment-card .pc-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: flex-start;
    }
    .payment-card .pc-header-right {
        text-align: right;
        flex-shrink: 0;
    }

    .card { padding: 16px; }
    .card-title { font-size: 15px; }
}

@media (min-width: 768px) {
    .page-banner { padding: 24px 28px; border-radius: 16px; gap: 20px; }
    .page-banner h2 { font-size: 24px; }
    .page-banner p { font-size: 13px; }
    .page-banner-art { font-size: 48px; }

    .stats-grid { gap: 12px; }
    .stat-card { padding: 16px 18px; }
    .stat-card .stat-value { font-size: 28px; }
    .stat-card .stat-eyebrow { font-size: 10px; }
    .stat-card .stat-label { font-size: 11px; }

    .payment-methods-grid { gap: 16px; margin-bottom: 20px; }
    .payment-method-card { padding: 20px; border-radius: 16px; }
    .payment-method-card .pm-header { margin-bottom: 20px; padding-bottom: 14px; }
    .payment-method-card .pm-header .pm-title { font-size: 16px; }
    .payment-method-card .qr-wrap img,
    .payment-method-card .qr-wrap .qr-fallback {
        width: 160px;
        height: 160px;
    }

    .card { padding: 20px; border-radius: 14px; }
    .card-title { font-size: 16px; }
    .section-header h3 { font-size: 17px; }

    #paymentsList { gap: 14px; }
    .payment-card { padding: 16px 18px; border-radius: 14px; }
    .payment-card .pc-pkg-name { font-size: 17px; }
    .payment-card .pc-price-main { font-size: 24px; }
    .payment-card .pc-amounts { padding: 14px; }
    .payment-card .pc-amount-row { font-size: 13px; }
    .payment-card .pc-form-wrap { padding: 14px; }
    .payment-card .pc-form-info { padding: 14px; }
    .payment-card .pc-form-info .pcfi-value { font-size: 20px; }

    .policy-item { padding: 14px 16px; }
    .policy-item .policy-icon { width: 48px; height: 48px; font-size: 15px; }
    .policy-item .policy-title { font-size: 14px; }
    .policy-item .policy-desc { font-size: 12px; }
}

@media (min-width: 1024px) {
    .page-banner { padding: 28px 32px; }
    .page-banner h2 { font-size: 26px; }
    .page-banner-art { font-size: 52px; }

    .stats-grid { gap: 14px; margin-bottom: 20px; }
    .stat-card { padding: 18px 20px; }
    .stat-card .stat-value { font-size: 30px; }

    .payment-methods-grid { gap: 18px; }
    .payment-method-card { padding: 24px; }
    .payment-method-card .qr-wrap img,
    .payment-method-card .qr-wrap .qr-fallback {
        width: 170px;
        height: 170px;
    }
    .payment-method-card .pm-field-value { font-size: 15px; }
    .payment-method-card .pm-field-value.mono { font-size: 16px; }

    .card { padding: 22px; }
    .payment-card { padding: 18px 20px; }
    .payment-card .pc-price-main { font-size: 26px; }
    .payment-card .pc-header-right { min-width: 200px; }
}

@media (min-width: 1440px) {
    .page-banner { padding: 32px 40px; border-radius: 18px; }
    .page-banner h2 { font-size: 30px; }
    .page-banner p { font-size: 14px; }
    .page-banner-art { font-size: 64px; }

    .stats-grid { gap: 16px; }
    .stat-card { padding: 22px 24px; border-radius: 14px; }
    .stat-card .stat-value { font-size: 34px; }

    .payment-method-card { padding: 28px; }
    .payment-method-card .qr-wrap img,
    .payment-method-card .qr-wrap .qr-fallback {
        width: 190px;
        height: 190px;
    }

    .card { padding: 26px; }
    .payment-card { padding: 20px 24px; }
    .payment-card .pc-price-main { font-size: 28px; }
}

@media (min-width: 1920px) {
    .page-banner { padding: 36px 48px; }
    .page-banner h2 { font-size: 34px; }

    .stat-card { padding: 26px 28px; }
    .stat-card .stat-value { font-size: 38px; }

    .payment-card { padding: 24px 28px; }
}

@media (max-height: 500px) and (orientation: landscape) {
    .page-banner { padding: 12px 16px; }
    .page-banner h2 { font-size: 18px; }
    .page-banner-art { font-size: 32px; }
    .stats-grid { grid-template-columns: repeat(4, 1fr); }
}

@media print {
    .page-banner-art,
    .pc-submit-btn,
    .pm-copy-btn,
    .btn-primary,
    .btn-ghost { display: none !important; }
    .payment-card { break-inside: avoid; border: 1px solid #ccc; }
}
</style>

<!-- ============================================================ -->
<!-- PAGE BANNER                                                   -->
<!-- ============================================================ -->
<div class="page-banner">
  <div class="page-banner-text">
    <div class="eyebrow">Financial Overview</div>
    <h2><strong>My Payments</strong></h2>
    <p>Manage your reservation and payment history.</p>
  </div>
  <div class="page-banner-art">💳</div>
</div>

<!-- ============================================================ -->
<!-- STATS                                                         -->
<!-- ============================================================ -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-eyebrow">Total Paid</div>
    <div class="stat-value" id="statPaid">—</div>
    <div class="stat-label">All time payments</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Pending</div>
    <div class="stat-value" id="statPending">—</div>
    <div class="stat-label">Awaiting verification</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Unpaid</div>
    <div class="stat-value" id="statUnpaid">—</div>
    <div class="stat-label">Reservation required</div>
  </div>
  <div class="stat-card">
    <div class="stat-eyebrow">Active Bookings</div>
    <div class="stat-value" id="statActive">—</div>
    <div class="stat-label">Pending settlement</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- PAYMENT METHODS (Black & White)                               -->
<!-- ============================================================ -->
<div class="payment-methods-grid">

  <!-- GCash -->
  <div class="payment-method-card">
    <div class="pm-header">
      <span class="pm-icon">💚</span>
      <strong class="pm-title">Pay via GCash</strong>
    </div>

    <div class="pm-content">
      <div class="qr-wrap">
        <img id="gcashQrImg" alt="GCash QR Code" src="<?= APP_URL ?>/assets/qr-code/gcash-qr.jpg">
        <div class="qr-label">Scan to pay</div>
      </div>

      <div class="pm-details">
        <div class="pm-field-label">Account Name</div>
        <div class="pm-field-value" id="gcashName">—</div>

        <div class="pm-field-label">Account Number</div>
        <div class="pm-field-value mono" id="gcashNo">—</div>

        <button id="copyGcashBtn" class="pm-copy-btn" type="button">
          📋 Copy GCash Number
        </button>
      </div>
    </div>
  </div>

  <!-- Maribank -->
  <div class="payment-method-card">
    <div class="pm-header">
      <span class="pm-icon">🏦</span>
      <strong class="pm-title">Pay via Maribank</strong>
    </div>

    <div class="pm-content">
      <div class="qr-wrap">
        <img id="maribankQrImg" alt="Maribank QR Code" src="<?= APP_URL ?>/assets/qr-code/maribank-qr.jpg">
        <div class="qr-label">Scan to transfer</div>
      </div>

      <div class="pm-details">
        <div class="pm-field-label">Account Name</div>
        <div class="pm-field-value" id="maribankName">—</div>

        <div class="pm-field-label">Account Number</div>
        <div class="pm-field-value mono" id="maribankNo">—</div>

        <button id="copyMaribankBtn" class="pm-copy-btn" type="button">
          📋 Copy Maribank Number
        </button>
      </div>
    </div>
  </div>

</div>

<!-- ============================================================ -->
<!-- PAYMENTS LIST                                                 -->
<!-- ============================================================ -->
<div class="card">
  <div class="section-header">
    <h3><strong>My Payments</strong></h3>
  </div>
  <div id="paymentsList">
    <p style="text-align:center;color:var(--muted);padding:30px;">Loading…</p>
  </div>
</div>

<!-- ============================================================ -->
<!-- RESERVATION & CANCELLATION POLICY                             -->
<!-- ============================================================ -->
<div class="card" style="margin-top:20px;">
  <div class="card-title"><strong>📋 Reservation &amp; Cancellation Policy</strong></div>
  <div class="policy-list">

    <div class="policy-item">
      <div class="policy-icon">₱100</div>
      <div class="policy-body">
        <div class="policy-title">₱100 Reservation Fee</div>
        <div class="policy-desc">A flat ₱100 reservation fee is required to confirm your booking.</div>
      </div>
    </div>

    <div class="policy-item">
      <div class="policy-icon">💰</div>
      <div class="policy-body">
        <div class="policy-title">Remaining Balance</div>
        <div class="policy-desc">The remaining balance is to be paid on your shoot day.</div>
      </div>
    </div>

    <div class="policy-item">
      <div class="policy-icon">🚫</div>
      <div class="policy-body">
        <div class="policy-title">No Refund Policy</div>
        <div class="policy-desc">The ₱100 reservation fee is non-refundable. Please make sure of your schedule before booking.</div>
      </div>
    </div>

  </div>
</div>

<meta name="csrf-token" content="<?= csrfToken() ?>">

<script>
// ============================================================
// CONFIG — FIXED: dynamic APP_BASE from APP_URL (Railway-compatible)
// ============================================================
const API_BASE        = 'pages/api/client-payments.php';
const CSRF_TOKEN      = document.querySelector('meta[name="csrf-token"]').content;
const APP_BASE        = '<?= rtrim(APP_URL, "/") ?>/';
const RESERVATION_FEE = 100;
let   CLIENT_LOYALTY_COUNT = 0;

// ============================================================
// HELPERS
// ============================================================
function fmtMoney(n) {
    return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function statusBadgeJS(status) {
    if (!status) return '';
    const map = {
        PAID:      'badge badge-green',
        PENDING:   'badge badge-amber',
        REJECTED:  'badge badge-red',
        UNPAID:    'badge badge-red',
        REFUNDED:  'badge badge-blue',
        CANCELLED: 'badge badge-gray',
    };
    return `<span class="${map[status] || 'badge'}">${esc(status)}</span>`;
}
function formatDateJS(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d)) return esc(dateStr);
    return d.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
}

// ============================================================
// TOAST
// ============================================================
function showToast(message, type = 'success') {
    document.getElementById('toast')?.remove();
    const colors = { success: '#0A0A0A', error: '#E74C3C', warning: '#F59E0B' };
    const toast = document.createElement('div');
    toast.id = 'toast';
    toast.style.cssText = `
        position: fixed; bottom: 24px; left: 16px; right: 16px; z-index: 9999;
        background: ${colors[type] || colors.success}; color: white;
        padding: 14px 20px; border-radius: 10px; font-size: 14px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        font-family: 'Inter', sans-serif;
        text-align: center;
        box-sizing: border-box;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

// ============================================================
// COPY TO CLIPBOARD
// ============================================================
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('✅ Copied: ' + text, 'success');
    }).catch(() => {
        const temp = document.createElement('input');
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        showToast('✅ Copied: ' + text, 'success');
    });
}

// ============================================================
// SET QR IMAGE (FIXED — dynamic change, cache-bust, no DOM break)
// ============================================================
// The bundled assets/qr-code/*.jpg files are the source of truth.
// A settings-table value is only honoured when it is non-empty, so
// clearing the field falls back to the bundled image rather than a
// blank box.
const BUNDLED_QR = {
    gcashQrImg:    '<?= APP_URL ?>/assets/qr-code/gcash-qr.jpg',
    maribankQrImg: '<?= APP_URL ?>/assets/qr-code/maribank-qr.jpg',
};
function setQrImage(imgId, qrValue, fallbackEmoji) {
    const img = document.getElementById(imgId);
    if (!img) return;

    const parent = img.parentElement;
    const bundled = BUNDLED_QR[imgId] || '';

    // Alisin ang lumang fallback (kung meron) bago mag-set ng bagong state
    const oldFallback = parent.querySelector('.qr-fallback');
    if (oldFallback) oldFallback.remove();

    const qr = (qrValue || '').trim();

    // Reset handlers before touching src, so a stale handler can't fire.
    img.onerror = null;
    img.onload = null;

    const showFallback = function () {
        img.style.display = 'none';
        if (!parent.querySelector('.qr-fallback')) {
            const fb = document.createElement('div');
            fb.className = 'qr-fallback';
            fb.innerHTML = fallbackEmoji;
            parent.insertBefore(fb, img);
        }
    };

    const showImage = function () {
        img.style.display = 'block';
        const fb = parent.querySelector('.qr-fallback');
        if (fb) fb.remove();
    };

    if (!qr) {
        // No override configured — always use the bundled file.
        img.style.display = 'block';
        if (bundled) img.src = bundled;
        return;
    }

    // Build full URL — support relative path AND full URL
    const baseUrl = qr.startsWith('http') ? qr : (APP_BASE + qr.replace(/^\/+/, ''));
    const finalUrl = baseUrl + (baseUrl.includes('?') ? '&' : '?') + 'v=' + Date.now();

    img.onload = showImage;

    img.onerror = function () {
        // A configured override can point at a file that no longer exists.
        // Fall back to the bundled QR rather than degrading to an emoji.
        console.warn('QR override failed, using bundled image:', finalUrl);
        if (bundled && img.getAttribute('src') !== bundled) {
            img.onerror = function () {
                console.warn('Bundled QR also failed:', bundled);
                showFallback();
            };
            img.src = bundled;
            return;
        }
        showFallback();
    };

    img.src = finalUrl;
}

// ============================================================
// LOAD SETTINGS
// ============================================================
async function loadSettings() {
    try {
        const res = await fetch(`${API_BASE}?action=settings&_t=${Date.now()}`, { cache: 'no-store' });
        if (!res.ok) {
            console.error('Settings HTTP error:', res.status);
            return;
        }
        const r = await res.json();
        if (!r.success) {
            console.error('Settings error:', r.error);
            return;
        }

        // GCash account details
        document.getElementById('gcashName').textContent = r.data.gcash_account_name || '—';
        document.getElementById('gcashNo').textContent   = r.data.gcash_account_number || '—';

        // GCash QR image
        setQrImage('gcashQrImg', r.data.gcash_qr || '', '💚');

        // GCash copy button
        document.getElementById('copyGcashBtn').onclick = () => {
            copyToClipboard(r.data.gcash_account_number || '');
        };

        // Maribank account details
        document.getElementById('maribankName').textContent = r.data.maribank_account_name || '—';
        document.getElementById('maribankNo').textContent   = r.data.maribank_account_number || '—';

        // Maribank QR image
        setQrImage('maribankQrImg', r.data.maribank_qr || '', '🏦');

        // Maribank copy button
        document.getElementById('copyMaribankBtn').onclick = () => {
            copyToClipboard(r.data.maribank_account_number || '');
        };

    } catch (err) {
        console.error('Settings load failed:', err);
    }
}

// ============================================================
// LOAD PAYMENTS + STATS
// ============================================================
async function loadPayments() {
    const list = document.getElementById('paymentsList');
    try {
        const res = await fetch(`${API_BASE}?action=list&_t=${Date.now()}`, {
            cache: 'no-store'
        });
        if (!res.ok) {
            list.innerHTML = `<p style="text-align:center;color:var(--red-text);padding:30px;">Server error (HTTP ${res.status}).</p>`;
            return;
        }

        const r = await res.json();
        if (!r.success) {
            list.innerHTML = `<p style="text-align:center;color:var(--red-text);padding:30px;">${esc(r.error || 'Failed to load.')}</p>`;
            return;
        }

        if (r.data.loyalty_count !== undefined) {
            CLIENT_LOYALTY_COUNT = parseInt(r.data.loyalty_count) || 0;
        }

        const s = r.data.stats || {};
        document.getElementById('statPaid').textContent    = fmtMoney(s.total_paid);
        document.getElementById('statPending').textContent = fmtMoney(s.pending_amt);
        document.getElementById('statUnpaid').textContent  = fmtMoney(s.unpaid_amt);
        document.getElementById('statActive').textContent  = s.active_cnt ?? 0;

        const payments = r.data.payments || [];
        if (!payments.length) {
            list.innerHTML = `<p style="text-align:center;color:var(--muted);padding:30px;">No payments yet.</p>`;
            return;
        }

        // GROUP payments by booking_id
        const grouped = {};
        payments.forEach(p => {
            const bid = p.booking_id;
            if (!grouped[bid]) {
                grouped[bid] = {
                    booking_id: bid,
                    booking_ref: p.booking_ref,
                    pkg_name: p.pkg_name,
                    booking_date: p.booking_date,
                    booking_time: p.booking_time,
                    package_price: parseFloat(p.package_price) || 0,
                    deposit_paid: parseInt(p.deposit_paid) || 0,
                    fully_paid: parseInt(p.fully_paid) || 0,
                    remaining_amount: parseFloat(p.remaining_balance) || 0,
                    status: p.booking_status,
                    loyalty_count: parseInt(p.loyalty_count) || 0,
                    all_payments: [],
                    id: p.id,
                    payment_id: p.id,
                };
            }
            grouped[bid].all_payments.push({
                id: p.id,
                type: p.payment_type,
                amount: parseFloat(p.amount) || 0,
                method: p.method,
                status: p.status,
                ref_number: p.ref_number,
                rejection_reason: p.rejection_reason,
                proof_image: p.proof_image,
                created_at: p.payment_created,
            });
        });

        const groupedPayments = Object.values(grouped);
        list.innerHTML = groupedPayments.map(renderPaymentCard).join('');

        scrollToTargetBooking();

    } catch (err) {
        console.error('Payments load failed:', err);
        list.innerHTML = `<p style="text-align:center;color:var(--red-text);padding:30px;">Network error.</p>`;
    }
}

// ============================================================
// AUTO-SCROLL SA TARGET BOOKING
// ============================================================
function scrollToTargetBooking() {
    const hash = window.location.hash;
    if (!hash || !hash.startsWith('#booking-')) return;

    setTimeout(() => {
        const targetId = hash.substring(1);
        const target = document.getElementById(targetId);

        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });

            target.style.transition = 'box-shadow 0.3s ease';
            target.style.boxShadow = '0 0 0 4px rgba(234, 179, 8, 0.5)';

            setTimeout(() => {
                target.style.boxShadow = '';
            }, 3000);

            const refInput = target.querySelector('input[name="ref_number"]');
            if (refInput) {
                setTimeout(() => refInput.focus(), 500);
            }
        }
    }, 500);
}

// ============================================================
// RENDER PAYMENT METHOD SELECTOR
// ============================================================
function paymentMethodSelector(paymentType) {
    const prefix = paymentType === 'RESERVATION' ? 'res' : 'bal';
    return `
      <div class="method-selector">
        <label class="method-label">Payment Method</label>
        <div class="method-options">
          <label class="method-option active" id="${prefix}-label-gcash" onclick="selectMethod_${prefix}('GCash')">
            <input type="radio" name="method_${prefix}" value="GCash" checked style="display:none;">
            <span class="mo-icon">💚</span>
            <span class="mo-text">GCash</span>
          </label>
          <label class="method-option" id="${prefix}-label-maribank" onclick="selectMethod_${prefix}('Maribank')">
            <input type="radio" name="method_${prefix}" value="Maribank" style="display:none;">
            <span class="mo-icon">🏦</span>
            <span class="mo-text">Maribank</span>
          </label>
        </div>
        <input type="hidden" name="method" value="GCash" id="${prefix}-method-value">
      </div>`;
}

function updateMethodUI(prefix, method) {
    document.querySelectorAll(`input[name="method_${prefix}"]`).forEach(r => r.checked = false);
    const radio = document.querySelector(`input[name="method_${prefix}"][value="${method}"]`);
    if (radio) radio.checked = true;
    const hidden = document.getElementById(`${prefix}-method-value`);
    if (hidden) hidden.value = method;

    const gcash = document.getElementById(`${prefix}-label-gcash`);
    const mb = document.getElementById(`${prefix}-label-maribank`);
    if (!gcash || !mb) return;

    if (method === 'GCash') {
        gcash.classList.add('active');
        mb.classList.remove('active');
    } else {
        mb.classList.add('active');
        gcash.classList.remove('active');
    }
}

window.selectMethod_res = function(method) { updateMethodUI('res', method); };
window.selectMethod_bal = function(method) { updateMethodUI('bal', method); };

// ============================================================
// RENDER ONE PAYMENT CARD
// ============================================================
function renderPaymentCard(p) {
    const depositPaid  = p.deposit_paid;
    const fullyPaid    = p.fully_paid;
    const loyaltyCount = parseInt(p.loyalty_count) || CLIENT_LOYALTY_COUNT || 0;
    const hasLoyalty   = loyaltyCount >= 10;
    const originalPrice  = parseFloat(p.original_price) || 0;
    const effectivePrice = parseFloat(p.package_price) || 0;
    const reservationFee  = RESERVATION_FEE;
    const remainingBalance = parseFloat(p.remaining_amount) || 0;

    const reservationPaid = Boolean(depositPaid)
        || ['Deposit Paid', 'Confirmed', 'Completed'].includes(p.status);

    let headerBadge = '';
    if (fullyPaid) {
        headerBadge = '<span class="badge badge-green">✅ Fully Paid</span>';
    } else if (reservationPaid) {
        headerBadge = '<span class="badge badge-amber">💰 Balance Pending</span>';
    } else {
        headerBadge = '<span class="badge badge-red">⚠️ Reservation Required</span>';
    }

    const loyaltyPillBg = hasLoyalty
        ? 'linear-gradient(135deg,#EAB308,#CA8A04)'
        : 'linear-gradient(135deg,#6C63FF,#4A42CC)';

    return `
    <div class="payment-card" id="booking-${p.booking_id}">
      <div class="pc-header">
        <div class="pc-header-left">
          <div class="pc-pkg-name">${esc(p.pkg_name)}</div>
          <div class="pc-session">Session: ${formatDateJS(p.booking_date)}</div>
          ${loyaltyCount > 0 ? `
            <div style="margin-top:6px;">
              <span class="loyalty-pill" style="background:${loyaltyPillBg};">
                🎫 ${loyaltyCount} bookings${hasLoyalty ? ' 🎉 50% OFF' : ''}
              </span>
            </div>
          ` : ''}
        </div>
        <div class="pc-header-right">
          <div class="pc-price-main">${fmtMoney(effectivePrice)}</div>
          ${hasLoyalty ? `<div class="pc-price-strike">${fmtMoney(originalPrice)}</div>` : ''}
          <div class="pc-badge-row">${headerBadge}</div>
        </div>
      </div>

      <div class="pc-amounts">
        <div class="pc-amount-row">
          <span class="pc-label">Total Package:</span>
          <span class="pc-value">${fmtMoney(effectivePrice)}</span>
        </div>

        <div class="pc-amount-row" style="color:${reservationPaid ? 'var(--green-text)' : 'var(--amber-text)'};">
          <span>${reservationPaid ? '✅' : '⚠️'} Reservation Fee:</span>
          <span class="pc-value">${fmtMoney(reservationFee)}</span>
        </div>

        ${reservationPaid ? `
          ${!fullyPaid ? `
            <div class="pc-amount-divider"></div>
            <div class="pc-amount-total">
              <span>Remaining Balance:</span>
              <span style="color:var(--amber-text);">${fmtMoney(remainingBalance)} ⏳</span>
            </div>
            <div class="pc-amount-note">
              Due on shoot day (${formatDateJS(p.booking_date)})
            </div>
          ` : `
            <div class="pc-amount-divider"></div>
            <div class="pc-amount-total" style="color:var(--green-text);">
              <span>🎉 Fully Paid:</span>
              <span>${fmtMoney(effectivePrice)}</span>
            </div>
          `}
        ` : `
          <div class="pc-amount-divider"></div>
          <div class="pc-amount-total">
            <span>Remaining Balance:</span>
            <span style="color:var(--muted);font-style:italic;">🔒 Pay reservation first</span>
          </div>
        `}
      </div>

      ${p.all_payments && p.all_payments.length > 0 ? `
        <div class="pc-history">
          <div class="pc-history-title">Payment History</div>
          ${p.all_payments.map(ap => `
            <div class="pc-history-row">
              <span>${ap.type === 'RESERVATION' ? '🎫' : ap.type === 'BALANCE' ? '💰' : '💳'} ${esc(ap.type || 'Payment')} ${ap.method ? '· ' + esc(ap.method) : ''}</span>
              <span>${fmtMoney(ap.amount)} ${statusBadgeJS(ap.status)}</span>
            </div>
          `).join('')}
        </div>
      ` : ''}

      ${renderActions(p, reservationFee, remainingBalance, reservationPaid)}
    </div>`;
}

// ============================================================
// RENDER ACTION SECTION
// ============================================================
function renderActions(p, reservationFee, remainingBalance, reservationPaid) {
    const fullyPaid = p.fully_paid;
    const allPayments = p.all_payments || [];

    const reservationPayment = allPayments.find(x => x.type === 'RESERVATION');
    const balancePayment     = allPayments.find(x => x.type === 'BALANCE');

    const reservationPaymentId = reservationPayment
        ? reservationPayment.id
        : (p.payment_id || p.id || '');
    const balancePaymentId = balancePayment
        ? balancePayment.id
        : (p.payment_id || p.id || '');

    const reservationAmount = reservationPayment
        ? parseFloat(reservationPayment.amount) || 0
        : reservationFee;
    const balanceAmount = balancePayment
        ? parseFloat(balancePayment.amount) || 0
        : remainingBalance;

    const reservationStatus = reservationPayment ? reservationPayment.status : null;
    const balanceStatus     = balancePayment     ? balancePayment.status     : null;

    if (fullyPaid) {
        return `
          <div class="pc-status success">
            ✅ No payments due. Your booking is fully paid.
          </div>`;
    }

    if (reservationPaid) {
        if (balanceStatus === 'PENDING') {
            return `
              <div class="pc-status pending">
                <strong>⏳ Balance payment submitted!</strong><br>
                <small>We're verifying your proof of ${fmtMoney(balanceAmount)}. You'll be notified once confirmed.</small>
              </div>`;
        }

        if (balanceStatus === 'REJECTED') {
            return `
              <div class="pc-status rejected">
                <strong>❌ Balance payment rejected:</strong> ${esc(balancePayment.rejection_reason || 'Please re-submit.')}
              </div>
              <div class="pc-form-wrap">
                <form class="payment-form" data-bid="${p.booking_id}" enctype="multipart/form-data">
                  <div class="pc-form-title">💰 Resubmit Remaining Balance</div>
                  <div class="pc-form-info">
                    <span class="pcfi-icon">💰</span>
                    <div class="pcfi-body">
                      <div class="pcfi-label">Remaining Balance</div>
                      <div class="pcfi-value">${fmtMoney(balanceAmount)}</div>
                      <div class="pcfi-note">Due on shoot day (${formatDateJS(p.booking_date)})</div>
                    </div>
                    <input type="hidden" name="payment_id" value="${balancePaymentId}">
                    <input type="hidden" name="payment_type" value="BALANCE">
                    <input type="hidden" name="booking_id" value="${p.booking_id}">
                  </div>
                  ${paymentMethodSelector('BALANCE')}
                  <div class="form-group">
                    <label>Reference Number</label>
                    <input type="text" name="ref_number" placeholder="Transaction ref…" required>
                  </div>
                  <div class="form-group">
                    <label>Upload Balance Proof (screenshot)</label>
                    <input type="file" name="proof" accept="image/*" required>
                  </div>
                  <button type="submit" class="pc-submit-btn">📤 Resubmit Balance Proof</button>
                </form>
              </div>`;
        }

        return `
          <div class="pc-form-wrap">
            <form class="payment-form" data-bid="${p.booking_id}" enctype="multipart/form-data">
              <div class="pc-form-title">💰 Pay Remaining Balance</div>
              <div class="pc-form-info">
                <span class="pcfi-icon">💰</span>
                <div class="pcfi-body">
                  <div class="pcfi-label">Remaining Balance</div>
                  <div class="pcfi-value">${fmtMoney(balanceAmount)}</div>
                  <div class="pcfi-note">Due on shoot day (${formatDateJS(p.booking_date)})</div>
                </div>
                <input type="hidden" name="payment_id" value="${balancePaymentId}">
                <input type="hidden" name="payment_type" value="BALANCE">
                <input type="hidden" name="booking_id" value="${p.booking_id}">
              </div>
              ${paymentMethodSelector('BALANCE')}
              <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="ref_number" placeholder="Transaction ref…" required>
              </div>
              <div class="form-group">
                <label>Upload Balance Proof (screenshot)</label>
                <input type="file" name="proof" accept="image/*" required>
              </div>
              <button type="submit" class="pc-submit-btn">📤 Submit Balance Proof</button>
            </form>
          </div>`;
    }

    if (reservationStatus === 'PENDING') {
        return `
          <div class="pc-status pending">
            <strong>⏳ Reservation payment submitted!</strong><br>
            <small>We're verifying your proof of ${fmtMoney(reservationAmount)}. You'll be notified once confirmed.</small>
          </div>`;
    }

    if (reservationStatus === 'REJECTED') {
        return `
          <div class="pc-status rejected">
            <strong>❌ Reservation rejected:</strong> ${esc(reservationPayment.rejection_reason || 'Invalid proof. Please re-submit.')}
          </div>
          <div class="pc-form-wrap">
            <form class="payment-form" data-bid="${p.booking_id}" enctype="multipart/form-data">
              <div class="pc-form-title">🎫 Resubmit Reservation Fee</div>
              <div class="pc-form-info">
                <span class="pcfi-icon">🎫</span>
                <div class="pcfi-body">
                  <div class="pcfi-label">₱100 Reservation Fee</div>
                  <div class="pcfi-value">${fmtMoney(reservationAmount)}</div>
                  <div class="pcfi-note">Remaining (${fmtMoney(remainingBalance)}) payable on shoot day</div>
                </div>
                <input type="hidden" name="payment_id" value="${reservationPaymentId}">
                <input type="hidden" name="payment_type" value="RESERVATION">
                <input type="hidden" name="booking_id" value="${p.booking_id}">
              </div>
              ${paymentMethodSelector('RESERVATION')}
              <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="ref_number" placeholder="Transaction ref…" required>
              </div>
              <div class="form-group">
                <label>Upload Reservation Proof (screenshot)</label>
                <input type="file" name="proof" accept="image/*" required>
              </div>
              <button type="submit" class="pc-submit-btn">📤 Resubmit Reservation Proof</button>
            </form>
          </div>`;
    }

    return `
      <div class="pc-form-wrap">
        <form class="payment-form" data-bid="${p.booking_id}" enctype="multipart/form-data">
          <div class="pc-form-title">🎫 Pay Reservation Fee</div>
          <div class="pc-form-info">
            <span class="pcfi-icon">🎫</span>
            <div class="pcfi-body">
              <div class="pcfi-label">₱100 Reservation Fee</div>
              <div class="pcfi-value">${fmtMoney(reservationAmount)}</div>
              <div class="pcfi-note">Remaining (${fmtMoney(remainingBalance)}) payable on shoot day</div>
            </div>
            <input type="hidden" name="payment_id" value="${reservationPaymentId}">
            <input type="hidden" name="payment_type" value="RESERVATION">
            <input type="hidden" name="booking_id" value="${p.booking_id}">
          </div>
          ${paymentMethodSelector('RESERVATION')}
          <div class="form-group">
            <label>Reference Number</label>
            <input type="text" name="ref_number" placeholder="Transaction ref…" required>
          </div>
          <div class="form-group">
            <label>Upload Reservation Proof (screenshot)</label>
            <input type="file" name="proof" accept="image/*" required>
          </div>
          <button type="submit" class="pc-submit-btn">📤 Submit Reservation Proof</button>
        </form>
      </div>`;
}

// ============================================================
// AJAX FORM SUBMIT
// ============================================================
document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.payment-form');
    if (!form) return;
    e.preventDefault();

    const bookingId = form.dataset.bid;
    const fd = new FormData(form);
    fd.append('csrf_token', CSRF_TOKEN);

    const methodHidden = form.querySelector('input[name="method"]');
    if (methodHidden) {
        fd.set('method', methodHidden.value);
    }

    const paymentId = fd.get('payment_id');
    const refNumber = fd.get('ref_number');

    if (!paymentId || paymentId === '0' || paymentId === '') {
        showToast('❌ Payment ID is missing. Please refresh the page.', 'error');
        return;
    }
    if (!refNumber || refNumber.trim() === '') {
        showToast('❌ Please enter a reference number.', 'error');
        return;
    }

    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Submitting…';

    try {
        const res = await fetch(`${API_BASE}?action=submit`, {
            method: 'POST',
            body: fd
        });

        const rawText = await res.text();

        let r;
        try {
            r = JSON.parse(rawText);
        } catch (parseErr) {
            console.error('Invalid JSON:', rawText);
            showToast('❌ Server error: invalid response', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }

        if (r.success) {
            showToast('✅ ' + (r.message || 'Submitted!'), 'success');

            setTimeout(() => {
                window.location.href = 'index.php?page=payments#booking-' + bookingId;
                window.location.reload();
            }, 800);
        } else {
            showToast('❌ ' + (r.error || 'Failed.'), 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        console.error('Submit error:', err);
        showToast('❌ Network error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
});

// ============================================================
// INIT
// ============================================================
(async function init() {
    await loadSettings();
    await loadPayments();
})();
</script>
