<?php
// ============================================================
// send-verification.php — Sends verification email via Gmail SMTP
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';   // ← ITO ANG FIX

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($toEmail, $toName, $token) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

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
        return true;
    } catch (Exception $e) {
        error_log('Verification email failed: ' . $mail->ErrorInfo);
        return false;
    }
}