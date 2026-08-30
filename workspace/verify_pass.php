<?php
require 'includes/db.php';
$s = $pdo->query("SELECT email, password_hash FROM users WHERE email='admin@electava.com'");
$r = $s->fetch();
if ($r) {
    echo "Email: " . $r['email'] . "\n";
    echo "Hash: " . $r['password_hash'] . "\n";
    echo "Verify: " . (password_verify('Electava@2025', $r['password_hash']) ? 'MATCH' : 'NO MATCH') . "\n";
} else {
    echo "User not found!\n";
}
