<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=electava_workspace", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $hash = '$2y$10$bmmNEGU6TrfrwfDevARLTRfnyJBxq/u6ILo3t/VDR25R';
    $pdo->prepare("UPDATE users SET password = ?")->execute([$hash]);
    echo "SUCCESS: Updated passwords.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
