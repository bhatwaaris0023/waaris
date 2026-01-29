<?php
/**
 * Simple DB connection test (useful for debugging deployments).
 * WARNING: don't leave this publicly accessible in production.
 */

// Load config (defines DB_* constants via getenv)
require_once __DIR__ . '/config.php';

$driver = defined('DB_DRIVER') ? strtolower(DB_DRIVER) : 'mysql';
$host = defined('DB_HOST') ? DB_HOST : getenv('DB_HOST');
$port = defined('DB_PORT') && DB_PORT !== '' ? DB_PORT : ($driver === 'pgsql' ? 5432 : 3306);
$dbname = defined('DB_NAME') ? DB_NAME : getenv('DB_NAME');
$user = defined('DB_USER') ? DB_USER : getenv('DB_USER');
$pass = defined('DB_PASS') ? DB_PASS : getenv('DB_PASS');

if (!$host || !$dbname || !$user) {
    echo json_encode(['ok' => false, 'error' => 'Missing DB environment variables.']);
    exit;
}

try {
    if ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo json_encode(['ok' => true, 'driver' => $driver, 'message' => 'Connected successfully']);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'driver' => $driver, 'error' => $e->getMessage()]);
}
