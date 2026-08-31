<?php
// Load environment variables from workspace/.env if present
$envFile = dirname(__DIR__) . '/.env';
if (!isset($_ENV['DB_HOST']) && file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
            putenv(trim($k) . '=' . trim($v));
        }
    }
}

$host    = $_ENV['DB_HOST']    ?? getenv('DB_HOST')    ?: '127.0.0.1';
$db      = $_ENV['DB_NAME']    ?? getenv('DB_NAME')    ?: 'electava_workspace';
$user    = $_ENV['DB_USER']    ?? getenv('DB_USER')    ?: null;
$pass    = $_ENV['DB_PASS']    ?? getenv('DB_PASS')    ?: null;
$charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

if (empty($user) || $pass === null) {
    error_log('[Electava] DB_USER or DB_PASS environment variable is not set.');
    http_response_code(503);
    die('Service temporarily unavailable.');
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log full error server-side, never expose to client
    error_log('[Electava] Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}
?>
