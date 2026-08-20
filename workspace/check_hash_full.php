<?php
require_once 'includes/db.php';
$stmt = $pdo->prepare('SELECT id, password FROM users WHERE id = 1');
$stmt->execute();
$user = $stmt->fetch();
if ($user) {
    echo "ID: " . $user['id'] . "\n";
    echo "Hash: " . $user['password'] . "\n";
    echo "Length: " . strlen($user['password']) . "\n";
    var_dump(password_get_info($user['password']));
    
    $password = 'Electava@2025';
    echo "Result: " . (password_verify($password, $user['password']) ? "OK" : "FAIL") . "\n";
}
?>
