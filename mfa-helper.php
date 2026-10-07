<?php
// ============================================================
// MFA Helper — TOTP (Time-based One-Time Password)
// Compatible with Google Authenticator, Authy, Microsoft Auth
// Requires: endroid/qr-code ^6.0
// ============================================================

class MFAHelper {

    /**
     * Generate a random base32 secret
     */
    public static function generateSecret($length = 32) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    /**
     * Base32 decode
     */
    private static function base32Decode($secret) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $secret = str_replace('=', '', $secret);

        $binary = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($secret); $i++) {
            $val = strpos($chars, $secret[$i]);
            if ($val === false) continue;

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Generate TOTP code for a given time slice
     */
    public static function generateCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $code = $value % 1000000;
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a user-supplied code against the secret
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::generateCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate otpauth:// URI
     */
    public static function getOTPAuthURI($secret, $email, $issuer = 'Studio 94') {
        $label = rawurlencode($issuer . ':' . $email);
        $issuerEncoded = rawurlencode($issuer);
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerEncoded}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate QR code as base64 data URI (endroid/qr-code v6)
     */
    public static function getQRCodeDataURI($secret, $email, $issuer = 'Studio 94') {
        // Load composer autoloader
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        $otpauth = self::getOTPAuthURI($secret, $email, $issuer);

        // Fallback kung hindi available yung library
        if (!class_exists('Endroid\\QrCode\\QrCode')) {
            error_log('[STUDIO94] endroid/qr-code not found, using QuickChart fallback');
            return 'https://quickchart.io/qr?text=' . rawurlencode($otpauth) . '&size=200&margin=2';
        }

        try {
            // endroid/qr-code v6 API
            $qrCode = new \Endroid\QrCode\QrCode($otpauth);
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);

            return 'data:image/png;base64,' . base64_encode($result->getString());

        } catch (\Throwable $e) {
            // Fallback kung may error sa library
            error_log('[STUDIO94] QR generation failed: ' . $e->getMessage());
            return 'https://quickchart.io/qr?text=' . rawurlencode($otpauth) . '&size=200&margin=2';
        }
    }

    /**
     * Generate backup codes (8 codes, 8 chars each)
     */
    public static function generateBackupCodes($count = 8) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    /**
     * Hash backup codes for storage
     */
    public static function hashBackupCodes($codes) {
        $hashed = [];
        foreach ($codes as $code) {
            $hashed[] = password_hash($code, PASSWORD_DEFAULT);
        }
        return json_encode($hashed);
    }

    /**
     * Verify a backup code and remove it if valid
     */
    public static function verifyBackupCode($code, $hashedCodesJson) {
        $code = strtoupper(preg_replace('/\s+/', '', $code));
        $hashedCodes = json_decode($hashedCodesJson, true);
        if (!is_array($hashedCodes)) return false;

        foreach ($hashedCodes as $i => $hash) {
            if (password_verify($code, $hash)) {
                unset($hashedCodes[$i]);
                return [
                    'valid' => true,
                    'remaining' => json_encode(array_values($hashedCodes))
                ];
            }
        }
        return false;
    }
}