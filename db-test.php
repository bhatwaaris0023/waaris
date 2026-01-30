<?php
/**
 * Simple DB connection test (useful for debugging deployments).
 * WARNING: don't leave this publicly accessible in production.
 */

// Load config (defines DB_* constants via getenv)
// require_once __DIR__ . '/config.php'; // Removed - using env vars directly

$driver = getenv('DB_DRIVER') ?: 'mysql';
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306);
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

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
