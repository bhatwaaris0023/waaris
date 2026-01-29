<?php
/**
 * Security Configuration & Functions
 * 
 * IMPORTANT: For production (HTTPS):
 * 1. Set session.cookie_secure to 1 (line below)
 * 2. Get an SSL certificate
 * 3. Update SITE_URL in config.php to use https://
 */

// Start session if not already started (BEFORE ini_set calls)
if (session_status() === PHP_SESSION_NONE) {
    // Session security - set BEFORE starting session
    ini_set('session.cookie_httponly', 1);
    // PRODUCTION: Change 0 to 1 when using HTTPS
    ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    session_start();
}

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:');

class Security {
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_TIME = 900; // 15 minutes
    const CSRF_TOKEN_LENGTH = 32;
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token ?? '');
    }
    
    /**
     * Sanitize user input
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Hash password using bcrypt
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check login attempts and implement brute force protection
     */
    public static function checkLoginAttempts($username) {
        $attempts_key = 'login_attempts_' . md5($username);
        $lockout_key = 'lockout_' . md5($username);
        
        if (isset($_SESSION[$lockout_key]) && time() < $_SESSION[$lockout_key]) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Record failed login attempt
     */
    public static function recordFailedLogin($username) {
        $attempts_key = 'login_attempts_' . md5($username);
        $lockout_key = 'lockout_' . md5($username);
        
        if (!isset($_SESSION[$attempts_key])) {
            $_SESSION[$attempts_key] = 0;
        }
        
        $_SESSION[$attempts_key]++;
        
        if ($_SESSION[$attempts_key] >= self::MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$lockout_key] = time() + self::LOCKOUT_TIME;
        }
    }
    
    /**
     * Reset login attempts
     */
    public static function resetLoginAttempts($username) {
        $attempts_key = 'login_attempts_' . md5($username);
        $lockout_key = 'lockout_' . md5($username);
        
        unset($_SESSION[$attempts_key], $_SESSION[$lockout_key]);
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 5242880) {
        // Check if file exists
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            return false;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate secure filename
     */
    public static function generateSecureFilename($original_filename) {
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $extension = strtolower(preg_replace('/[^a-z0-9]/', '', $extension));
        $filename = md5(uniqid() . time()) . '.' . $extension;
        return $filename;
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = []) {
        $log_file = __DIR__ . '/../logs/security.log';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }
        
        $log_entry = date('Y-m-d H:i:s') . ' | ' . $event . ' | IP: ' . self::getClientIP() . ' | ' . json_encode($details) . "\n";
        error_log($log_entry, 3, $log_file);
    }
    
    /**
     * Get client IP address (safe)
     */
    public static function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'UNKNOWN';
    }
}

?>
