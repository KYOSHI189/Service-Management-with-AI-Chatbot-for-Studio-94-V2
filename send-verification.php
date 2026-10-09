<?php
// ============================================================
// send-verification.php — Sends emails via Resend (HTTPS 443) or Gmail SMTP
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Returns true if an email service is configured (either Resend API or Gmail SMTP).
 */
function isMailConfigured(): bool {
    if (defined('RESEND_API_KEY') && !empty(RESEND_API_KEY)) {
        return true;
    }
    if (defined('BREVO_API_KEY') && !empty(BREVO_API_KEY)) {
        return true;
    }
    return defined('MAIL_USERNAME') && defined('MAIL_PASSWORD')
        && !empty(MAIL_USERNAME) && !empty(MAIL_PASSWORD)
        && MAIL_USERNAME !== '' && MAIL_PASSWORD !== '';
}

/**
 * Asks Brevo which sender address is verified for this account, so a fresh
 * install works without any extra configuration. Returns '' on any failure.
 */
function brevoDefaultSender(string $apiKey): string {
    $ch = curl_init('https://api.brevo.com/v3/account');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . $apiKey,
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $data = json_decode($response, true);
        $email = $data['email'] ?? '';
        if (!empty($email)) {
            error_log('[STUDIO94] Using Brevo account sender: ' . $email);
            return (string) $email;
        }
    }

    error_log('[STUDIO94] Could not read Brevo account sender (' . $httpCode . '): '
        . substr((string) $response, 0, 300) . ' ' . $curlErr);
    return '';
}

/**
 * Sends an email via Brevo REST API v3 (HTTPS port 443).
 * 300 emails/day free, no domain required, and the default sender is
 * verified automatically. Works on Railway where SMTP ports are blocked.
 */
function sendViaBrevo(string $toEmail, string $subject, string $htmlContent, string $textContent = ''): bool {
    if (!defined('BREVO_API_KEY') || empty(BREVO_API_KEY)) {
        return false;
    }

    // Sender resolution: explicit config -> MAIL_USERNAME -> the verified
    // sender Brevo reports for this account. Without a sender Brevo rejects
    // the request, so fall back rather than giving up.
    $senderEmail = defined('BREVO_FROM_EMAIL') && !empty(BREVO_FROM_EMAIL)
        ? BREVO_FROM_EMAIL
        : (MAIL_USERNAME !== '' ? MAIL_USERNAME : '');

    if ($senderEmail === '') {
        $senderEmail = brevoDefaultSender(BREVO_API_KEY);
    }

    if ($senderEmail === '') {
        error_log('[STUDIO94] Brevo skipped: could not resolve a sender address. '
            . 'Set BREVO_FROM_EMAIL in Railway to a verified sender.');
        return false;
    }

    $senderName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Studio 94';

    $payload = [
        'sender'      => ['name' => $senderName, 'email' => $senderEmail],
        'to'          => [['email' => $toEmail]],
        'subject'     => $subject,
        'htmlContent' => $htmlContent,
    ];
    if ($textContent !== '') {
        $payload['textContent'] = $textContent;
    }

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . BREVO_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log('[STUDIO94] Email sent via Brevo to: ' . $toEmail);
        return true;
    }

    error_log('[STUDIO94] Brevo API error (' . $httpCode . '): ' . $response . ' ' . $curlErr);
    return false;
}

/**
 * Sends an email via Resend REST API (HTTPS port 443).
 * Works reliably on Railway where outbound SMTP ports are blocked.
 */
function sendViaResend(string $toEmail, string $subject, string $htmlContent): bool {
    if (!defined('RESEND_API_KEY') || empty(RESEND_API_KEY)) {
        return false;
    }

    // Resend's onboarding@resend.dev test domain can only send to the account
    // owner's own address. Sending to real clients requires a verified domain,
    // so the sender is configurable via RESEND_FROM.
    $from = defined('RESEND_FROM') && !empty(RESEND_FROM)
        ? RESEND_FROM
        : 'Studio 94 <onboarding@resend.dev>';

    $ch = curl_init('https://api.resend.com/emails');
    $payload = json_encode([
        'from'    => $from,
        'to'      => [$toEmail],
        'subject' => $subject,
        'html'    => $htmlContent,
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . RESEND_API_KEY,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log('[STUDIO94] Email sent successfully via Resend API to: ' . $toEmail);
        return true;
    }

    error_log('[STUDIO94] Resend API error (' . $httpCode . '): ' . $response . ' ' . $curlErr);
    return false;
}

/**
 * Sends an email via PHPMailer SMTP.
 */
function sendViaPhpMailer(string $toEmail, string $toName, string $subject, string $htmlContent, string $altBody): bool {
    if (!defined('MAIL_USERNAME') || !defined('MAIL_PASSWORD') || empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->Timeout    = 8;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;
        $mail->AltBody = $altBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Fallback: try port 465 (SMTPS) if port 587 was blocked
        try {
            $mail2 = new PHPMailer(true);
            $mail2->isSMTP();
            $mail2->Host       = MAIL_HOST;
            $mail2->SMTPAuth   = true;
            $mail2->Username   = MAIL_USERNAME;
            $mail2->Password   = MAIL_PASSWORD;
            $mail2->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail2->Port       = 465;
            $mail2->Timeout    = 8;
            $mail2->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail2->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
            $mail2->addAddress($toEmail, $toName);
            $mail2->isHTML(true);
            $mail2->Subject = $subject;
            $mail2->Body    = $htmlContent;
            $mail2->AltBody = $altBody;
            $mail2->send();
            return true;
        } catch (Exception $e2) {
            error_log('[STUDIO94] SMTP send failed for ' . $toEmail . ': ' . $mail->ErrorInfo . ' | SMTPS: ' . $mail2->ErrorInfo);
            return false;
        }
    }
}

/**
 * Sends the verification email.
 * Returns 'sent' on success, 'skipped' if mail is not configured (auto-verify flow),
 * or 'failed' if mail is configured but sending failed.
 */
function sendVerificationEmail($toEmail, $toName, $token): string {
    if (!isMailConfigured()) {
        error_log('[STUDIO94] Mail not configured — skipping email for: ' . $toEmail);
        return 'skipped';
    }

    $verifyUrl = APP_URL . '/verify.php?token=' . urlencode($token);
    $subject   = 'Verify your Studio 94 account';
    $htmlBody  = "
        <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;'>
          <h2 style='color:#111;'>Welcome to Studio 94, {$toName}!</h2>
          <p style='color:#444;line-height:1.6;'>
            Thanks for signing up. Please click the button below to verify your Gmail account:
          </p>
          <p style='text-align:center;margin:32px 0;'>
            <a href='{$verifyUrl}' style='background:#111;color:#fff;padding:14px 28px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold;'>Verify My Account</a>
          </p>
          <p style='color:#888;font-size:12px;'>
            This link expires in 24 hours. If you didn't create this account, you can ignore this email.
          </p>
          <hr style='border:0;border-top:1px solid #eee;margin:24px 0;'>
          <p style='color:#999;font-size:11px;'>Studio 94 — Every frame tells a story.</p>
        </div>
    ";
    $altBody   = "Welcome to Studio 94! Verify your account: {$verifyUrl}";

    // Priority 1: Resend API (HTTPS Port 443 — works everywhere on Railway)
    if (defined('RESEND_API_KEY') && !empty(RESEND_API_KEY)) {
        if (sendViaResend($toEmail, $subject, $htmlBody)) {
            return 'sent';
        }
    }

    // Priority 2: Brevo API (HTTPS Port 443 — 300/day free, no domain needed)
    if (sendViaBrevo($toEmail, $subject, $htmlBody, $altBody)) {
        return 'sent';
    }

    // Priority 3: PHPMailer SMTP (blocked by Railway on non-Pro plans)
    if (sendViaPhpMailer($toEmail, $toName, $subject, $htmlBody, $altBody)) {
        return 'sent';
    }

    return 'failed';
}

/**
 * Sends a 6-digit verification code to $toEmail.
 *
 * Used at registration: the account row is NOT created until the user
 * proves they can read mail at that address. Returns the same
 * 'sent' / 'skipped' / 'failed' contract as sendVerificationEmail().
 */
function sendVerificationCode(string $toEmail, string $toName, string $code): string {
    if (!isMailConfigured()) {
        error_log('[STUDIO94] Mail not configured — cannot send code to: ' . $toEmail);
        return 'skipped';
    }

    $subject  = 'Your Studio 94 verification code';
    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $htmlBody = "
        <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;'>
          <h2 style='color:#111;'>Welcome to Studio 94, {$safeName}!</h2>
          <p style='color:#444;line-height:1.6;'>
            Use this verification code to finish creating your account:
          </p>
          <p style='text-align:center;margin:32px 0;'>
            <span style='display:inline-block;font-size:34px;font-weight:bold;letter-spacing:10px;
                         background:#111;color:#fff;padding:18px 34px;border-radius:10px;'>
              {$safeCode}
            </span>
          </p>
          <p style='color:#888;font-size:12px;'>
            This code expires in 24 hours. If you didn't try to register, you can ignore this email.
          </p>
          <hr style='border:0;border-top:1px solid #eee;margin:24px 0;'>
          <p style='color:#999;font-size:11px;'>Studio 94 — Every frame tells a story.</p>
        </div>
    ";
    $altBody = "Your Studio 94 verification code is: {$code}";

    if (defined('RESEND_API_KEY') && !empty(RESEND_API_KEY)) {
        if (sendViaResend($toEmail, $subject, $htmlBody)) {
            return 'sent';
        }
    }

    // Brevo — 300 emails/day free, works on Railway over HTTPS.
    if (sendViaBrevo($toEmail, $subject, $htmlBody, $altBody)) {
        return 'sent';
    }

    if (sendViaPhpMailer($toEmail, $toName, $subject, $htmlBody, $altBody)) {
        return 'sent';
    }

    return 'failed';
}

/**
 * Sends a password reset email via Resend API or Gmail SMTP.
 * Returns true on success, false on error/unconfigured.
 */
function sendPasswordResetEmail($toEmail, $toName, $token): bool {
    if (!isMailConfigured()) {
        error_log('[STUDIO94] Mail not configured — cannot send password reset email to: ' . $toEmail);
        return false;
    }

    $resetUrl = APP_URL . '/login.php?token=' . urlencode($token);
    $subject  = 'Reset your Studio 94 password';
    $htmlBody = "
        <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;'>
          <h2 style='color:#111;'>Password Reset Request</h2>
          <p style='color:#444;line-height:1.6;'>
            Hello {$toName}, we received a request to reset the password for your Studio 94 account.
          </p>
          <p style='color:#444;line-height:1.6;'>
            Click the button below to choose a new password:
          </p>
          <p style='text-align:center;margin:32px 0;'>
            <a href='{$resetUrl}' style='background:#111;color:#fff;padding:14px 28px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold;'>Reset My Password</a>
          </p>
          <p style='color:#888;font-size:12px;'>
            This link will expire in 1 hour. If you didn't request a password reset, you can safely ignore this email.
          </p>
          <hr style='border:0;border-top:1px solid #eee;margin:24px 0;'>
          <p style='color:#999;font-size:11px;'>Studio 94 — Every frame tells a story.</p>
        </div>
    ";
    $altBody  = "Reset your Studio 94 password: {$resetUrl}";

    // Priority 1: Resend API (HTTPS Port 443 — works everywhere on Railway)
    if (defined('RESEND_API_KEY') && !empty(RESEND_API_KEY)) {
        if (sendViaResend($toEmail, $subject, $htmlBody)) {
            return true;
        }
    }

    // Priority 2: Brevo API (HTTPS Port 443 — 300/day free, no domain needed)
    if (sendViaBrevo($toEmail, $subject, $htmlBody, $altBody)) {
        return true;
    }

    // Priority 3: PHPMailer SMTP (ports 587 then 465)
    return sendViaPhpMailer($toEmail, $toName, $subject, $htmlBody, $altBody);
}