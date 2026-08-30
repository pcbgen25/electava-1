<?php
require 'includes/db.php';

$newHash = password_hash('Electava@2025', PASSWORD_BCRYPT);

// Update admin
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->execute([$newHash, 'admin@electava.com']);
echo "admin@electava.com updated: " . $stmt->rowCount() . " row(s)\n";

// Update service team
$stmt->execute([$newHash, 'service.team@electava.com']);
echo "service.team@electava.com updated: " . $stmt->rowCount() . " row(s)\n";

// Update vendor
$stmt->execute([$newHash, 'vendor1@electava.com']);
echo "vendor1@electava.com updated: " . $stmt->rowCount() . " row(s)\n";

// Verify
$check = $pdo->query("SELECT email, password_hash FROM users");
while ($row = $check->fetch()) {
    $ok = password_verify('Electava@2025', $row['password_hash']) ? 'MATCH' : 'NO MATCH';
    echo $row['email'] . " => " . $ok . "\n";
}
