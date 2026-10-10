<?php
require_once 'config.php';

echo "<h2>Timezone Verification</h2>";

// PHP timezone
echo "<strong>PHP Timezone:</strong> " . date_default_timezone_get() . "<br>";
echo "<strong>PHP Current Time:</strong> " . date('Y-m-d H:i:s') . "<br>";
echo "<strong>PHP Current Hour:</strong> " . date('H') . "<br><br>";

// MySQL timezone
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT NOW() as db_time, @@session.time_zone as tz, @@global.time_zone as gtz");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<strong>MySQL Session Timezone:</strong> " . $row['tz'] . "<br>";
    echo "<strong>MySQL Global Timezone:</strong> " . $row['gtz'] . "<br>";
    echo "<strong>MySQL Current Time:</strong> " . $row['db_time'] . "<br><br>";
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}

// Comparison
echo "<h3>Comparison</h3>";
$phpTime = date('Y-m-d H:i');
$dbTime = isset($row) ? date('Y-m-d H:i', strtotime($row['db_time'])) : 'N/A';

echo "PHP:   $phpTime<br>";
echo "MySQL: $dbTime<br>";

if ($phpTime === $dbTime) {
    echo "<br>✅ <strong style='color:green;'>TAMA! Pareho ang PHP at MySQL time.</strong>";
} else {
    echo "<br>❌ <strong style='color:red;'>MALI! May difference ang PHP at MySQL time.</strong>";
}

// Delete this file after testing!
