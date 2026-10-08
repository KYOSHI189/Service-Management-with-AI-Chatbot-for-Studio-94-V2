<?php
// ============================================================
// send-verification.php — Sends emails via Brevo/Resend (HTTPS 443) or Gmail SMTP
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Returns true if any email service is configured.
 */
function isMailConfigured(): bool {
    if (defined('BREVO_API_KEY') && !empty(BREVO_API_KEY)) {
        return true;
    }
    if (defined('RESEND_API_KEY') && !empty(RESEND_API_KEY)) {
        return true;
    }
    return defined('MAIL_USERNAME') && defined('MAIL_PASSWORD')
        && !empty(MAIL_USERNAME) && !empty(MAIL_PASSWORD)
        && MAIL_USERNAME !== '' && MAIL_PASSWORD !== '';
}

/**
 * Sends an email via Brevo REST API (HTTPS port 443).
 * Allows sending to ANY recipient email address on free plan.
 */
function sendViaBrevo(string $toEmail, string $toName, string $subject, string $htmlContent): bool {
    if (!defined('BREVO_API_KEY') || empty(BREVO_API_KEY)) {
        return false;
    }

    $senderEmail = defined('BREVO_SENDER_EMAIL') && !empty(BREVO_SENDER_EMAIL)
        ? BREVO_SENDER_EMAIL
        : (defined('MAIL_USERNAME') && !empty(MAIL_USERNAME) ? MAIL_USERNAME : 'hello@studio94.com');

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    $payload = json_encode([
        'sender'      => ['name' => 'Studio 94', 'email' => $senderEmail],
        'to'          => [['email' => $toEmail, 'name' => $toName ?: 'Client']],
        'subject'     => $subject,
        'htmlContent' => $htmlContent,
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . BREVO_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log('[STUDIO94] Email sent successfully via Brevo API to: ' . $toEmail);
        return true;
    }

    error_log('[STUDIO94] Brevo API error (' . $httpCode . '): ' . $response . ' ' . $curlErr);
    return false;
}

/**
 * Sends an email via Resend REST API (HTTPS port 443).
 * Works on Railway where outbound SMTP ports are blocked.
 */
function sendViaResend(string $toEmail, string $subject, string $htmlContent): bool {
    if (!defined('RESEND_API_KEY') || empty(RESEND_API_KEY)) {
        return false;
    }

    $fromAddress = defined('RESEND_FROM') && !empty(RESEND_FROM)
        ? RESEND_FROM
        : 'Studio 94 <onboarding@resend.dev>';

    $ch = curl_init('https://api.resend.com/emails');
    $payload = json_encode([
        'from'    => $fromAddress,
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
        // Fallback: try port 465 (SMTPS)
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

    // Priority 1: Brevo API (HTTPS Port 443 — sends to any recipient)
    if (defined('BREVO_API_KEY') && !empty(BREVO_API_KEY)) {
        if (sendViaBrevo($toEmail, $toName, $subject, $htmlBody)) {
            return 'sent';
        }
    }

    // Priority 2: Resend API (HTTPS Port 443)
    if (defined('RESEND_API_KEY') && !empty(RESEND_API_KEY)) {
        if (sendViaResend($toEmail, $subject, $htmlBody)) {
            return 'sent';
        }
    }

    // Priority 3: PHPMailer SMTP
    if (sendViaPhpMailer($toEmail, $toName, $subject, $htmlBody, $altBody)) {
        return 'sent';
    }

    return 'failed';
}

/**
 * Sends a password reset email via Brevo, Resend, or Gmail SMTP.
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

    // Priority 1: Brevo API (HTTPS Port 443 — sends to any recipient)
    if (defined('BREVO_API_KEY') && !empty(BREVO_API_KEY)) {
        if (sendViaBrevo($toEmail, $toName, $subject, $htmlBody)) {
            return true;
        }
    }

    // Priority 2: Resend API (HTTPS Port 443)
    if (defined('RESEND_API_KEY') && !empty(RESEND_API_KEY)) {
        if (sendViaResend($toEmail, $subject, $htmlBody)) {
            return true;
        }
    }

    // Priority 3: PHPMailer SMTP (ports 587 then 465)
    return sendViaPhpMailer($toEmail, $toName, $subject, $htmlBody, $altBody);
}