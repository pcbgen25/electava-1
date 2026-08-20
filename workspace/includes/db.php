<?php
$host = '127.0.0.1';
$db   = 'electava_workspace';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$dsn_nodb = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if ($e->getCode() == 1049) {
        try {
            $pdo = new PDO($dsn_nodb, $user, $pass, $options);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
            $pdo->exec("USE `$db`");
            $sql_file = __DIR__ . '/../install.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                $pdo->exec($sql);
            }
        } catch (\PDOException $e2) {
            die("Database initialization failed: " . $e2->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
