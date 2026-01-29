<?php
/**
 * Database Configuration & Connection
 * Secure PDO connection with prepared statements
 */

// Load configuration if not already loaded
if (!defined('CONFIG_LOADED')) {
    // Check if config file exists
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    } else {
        // Default development configuration
        define('DB_HOST', 'dpg-d5rn7effte5s73cdunb0-a');
        define('DB_NAME', 'waaris_db');
        define('DB_USER', 'waaris_db_user');
        define('DB_PASS', '592y7fahxY2iAHDiKbeTHL27n8qxHmER');
        define('CONFIG_LOADED', true);
    }
}

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $driver = defined('DB_DRIVER') ? strtolower(DB_DRIVER) : 'mysql';
            $port = defined('DB_PORT') && DB_PORT !== '' ? DB_PORT : ($driver === 'pgsql' ? 5432 : 3306);

            if ($driver === 'pgsql') {
                $dsn = 'pgsql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME;
            } else {
                // default to mysql
                $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            }
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database Connection Error (' . ($driver ?? 'unknown') . '): ' . $e->getMessage());
            die('Database connection failed. Please contact administrator.');
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialize
    public function __wakeup() {}
}

// Get database connection
$db = Database::getInstance()->getConnection();

?>
