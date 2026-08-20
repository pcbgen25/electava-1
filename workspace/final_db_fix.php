<?php
require_once 'includes/db.php';
try {
    $password = 'Electava@2025';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    echo "Generated Hash: $hash\n";
    echo "Length: " . strlen($hash) . "\n";
    
    $stmt = $pdo->prepare("UPDATE users SET password = ?");
    $stmt->execute([$hash]);
    
    echo "SUCCESS: All users updated.\n";
    
    // Immediate verification
    $stmt = $pdo->query("SELECT password FROM users LIMIT 1");
    $dbHash = $stmt->fetchColumn();
    echo "DB Stored Hash: $dbHash\n";
    echo "DB Hash Length: " . strlen($dbHash) . "\n";
    echo "Match Check: " . (password_verify($password, $dbHash) ? "PASS" : "FAIL") . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
