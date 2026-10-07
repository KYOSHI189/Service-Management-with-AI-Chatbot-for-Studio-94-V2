<?php
require_once __DIR__ . '/mfa-helper.php';

$secret = MFAHelper::generateSecret();
$code   = MFAHelper::generateCode($secret);

echo "<h2>MFA + QR Test</h2>";
echo "<p><strong>Secret:</strong> <code>$secret</code></p>";
echo "<p><strong>Current code:</strong> <code style='font-size:24px;'>$code</code></p>";
echo "<p><strong>Verification:</strong> " . (MFAHelper::verifyCode($secret, $code) ? '✅ Valid' : '❌ Invalid') . "</p>";

// Test QR Code
$qrDataUri = MFAHelper::getQRCodeDataURI($secret, 'test@gmail.com', 'Studio 94');

if (strpos($qrDataUri, 'data:image/png;base64,') === 0) {
    echo "<p style='color:green;'><strong>QR Type:</strong> ✅ Local PNG (base64)</p>";
    echo "<p><img src='$qrDataUri' alt='QR Code' style='border:8px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.1);'></p>";
} else {
    echo "<p style='color:orange;'><strong>QR Type:</strong> ⚠️ Fallback (QuickChart.io)</p>";
    echo "<p><img src='$qrDataUri' alt='QR Code'></p>";
}