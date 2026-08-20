<?php
// Electava Workspace - Environment Check
// This script verifies the PHP extensions and database connection.

header('Content-Type: text/plain');

echo "--- Electava Workspace Environment Check ---\n\n";

// 1. Check PHP Version
echo "PHP Version: " . phpversion() . " - OK\n";

// 2. Check Extensions
$extensions = ['pdo', 'pdo_mysql', 'session'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "Extension '$ext' found - OK\n";
    } else {
        echo "ERROR: Extension '$ext' is NOT loaded. Please check your php.ini.\n";
    }
}

// 3. Check Database Connection
require_once 'includes/db.php';
try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Database Connection ($db) - OK\n";
} catch (PDOException $e) {
    echo "ERROR: Database Connection failed: " . $e->getMessage() . "\n";
}

// 4. Check Filesystem Permissions
$writable = is_writable(__DIR__);
echo "Directory Writable - " . ($writable ? "OK" : "WARNING: May have trouble saving sessions.") . "\n";

echo "\n--- Check Complete ---\n";
?>
