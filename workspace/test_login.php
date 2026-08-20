<?php
require_once 'includes/db.php';

$login = 'admin@electava.com';
$password = 'Electava@2025';

echo "Testing login for: $login\n";
echo "Password: $password\n";

try {
    $stmt = $pdo->prepare('SELECT id, email, username, password, role FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user) {
        echo "User found in database!\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Hash in DB: " . $user['password'] . "\n";
        
        $is_valid = password_verify($password, $user['password']);
        echo "password_verify result: " . ($is_valid ? "SUCCESS" : "FAILURE") . "\n";
        
        // Let's also check if the hash itself is valid
        $info = password_get_info($user['password']);
        echo "Hash info: " . json_encode($info) . "\n";
    } else {
        echo "User NOT found in database.\n";
        
        // List all users to see what's there
        $all = $pdo->query("SELECT username, email FROM users")->fetchAll();
        echo "Available users: " . json_encode($all) . "\n";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
