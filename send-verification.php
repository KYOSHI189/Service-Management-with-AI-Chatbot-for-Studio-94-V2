<?php
// ============================================================
// send-verification.php — Sends verification email via Gmail SMTP
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Returns true if SMTP is properly configured (both username and password present).
 * If not configured, email cannot be sent from this server.
 */
function isMailConfigured(): bool {
    return defined('MAIL_USERNAME') && defined('MAIL_PASSWORD')
        && !empty(MAIL_USERNAME) && !empty(MAIL_PASSWORD)
        && MAIL_USERNAME !== '' && MAIL_PASSWORD !== '';
}

/**
 * Sends the verification email.
 * Returns 'sent' on success, 'skipped' if mail is not configured (auto-verify flow),
 * or 'failed' if mail is configured but sending failed.
 */
function sendVerificationEmail($toEmail, $toName, $token): string {
    // If no SMTP credentials are configured, skip email sending
    if (!isMailConfigured()) {
        error_log('[STUDIO94] Mail not configured (empty MAIL_USERNAME/MAIL_PASSWORD) — skipping email for: ' . $toEmail);
        return 'skipped';
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
        $mail->Timeout    = 5;

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $verifyUrl = APP_URL . '/verify.php?token=' . urlencode($token);

        $mail->isHTML(true);
        $mail->Subject = 'Verify your Studio 94 account';
        $mail->Body = "
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
        $mail->AltBody = "Welcome to Studio 94! Verify your account: {$verifyUrl}";

        $mail->send();
        return 'sent';
    } catch (Exception $e) {
        error_log('[STUDIO94] Verification email FAILED for ' . $toEmail . ': ' . $mail->ErrorInfo);
        return 'failed';
    }
}

/**
 * Sends a password reset email via Gmail SMTP.
 * Returns true on success, false on error/unconfigured.
 */
function sendPasswordResetEmail($toEmail, $toName, $token): bool {
    if (!isMailConfigured()) {
        error_log('[STUDIO94] Mail not configured (empty MAIL_USERNAME/MAIL_PASSWORD) — cannot send password reset email to: ' . $toEmail);
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
        $mail->Timeout    = 10;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $resetUrl = APP_URL . '/login.php?token=' . urlencode($token);

        $mail->isHTML(true);
        $mail->Subject = 'Reset your Studio 94 password';
        $mail->Body = "
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
        $mail->AltBody = "Reset your Studio 94 password: {$resetUrl}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[STUDIO94] Password reset email FAILED for ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    }
}