<?php
/**
 * Authentication Helper Functions
 */

class Auth {
    /**
     * Register new user
     */
    public static function register($username, $email, $phone, $password, $first_name = '', $last_name = '', $vehicle_number = '') {
        global $db;
        
        // Validate inputs
        if (strlen($username) < 3) {
            return ['success' => false, 'message' => 'Username must be at least 3 characters'];
        }
        
        if (!Security::validateEmail($email)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }
        
        if (empty($phone) || strlen($phone) < 10) {
            return ['success' => false, 'message' => 'Please provide a valid phone number'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        // Check if username or email already exists
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? OR phone = ?');
        $stmt->execute([$username, $email, $phone]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username, email, or phone number already exists'];
        }
        
        // Hash password
        $hashed_password = Security::hashPassword($password);
        
        // Insert user
        try {
            $stmt = $db->prepare('INSERT INTO users (username, email, phone, password, first_name, last_name, vehicle_number) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$username, $email, $phone, $hashed_password, $first_name, $last_name, $vehicle_number]);
            
            Security::logSecurityEvent('USER_REGISTERED', ['username' => $username, 'email' => $email, 'phone' => $phone]);
            
            return ['success' => true, 'message' => 'Registration successful! Please login.'];
        } catch (PDOException $e) {
            error_log('Registration error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Login user
     */
    public static function login($username, $password) {
        global $db;
        
        // Check login attempts
        if (!Security::checkLoginAttempts($username)) {
            return ['success' => false, 'message' => 'Account temporarily locked. Please try again later.'];
        }
        
        // Find user
        $stmt = $db->prepare('SELECT id, username, password, status FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            Security::recordFailedLogin($username);
            Security::logSecurityEvent('FAILED_LOGIN_ATTEMPT', ['username' => $username]);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is inactive'];
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Reset login attempts
        Security::resetLoginAttempts($username);
        
        Security::logSecurityEvent('USER_LOGIN', ['user_id' => $user['id'], 'username' => $username]);
        
        return ['success' => true, 'message' => 'Login successful'];
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        Security::logSecurityEvent('USER_LOGOUT', ['user_id' => $_SESSION['user_id'] ?? null]);
        session_destroy();
        return true;
    }
    
    /**
     * Require login (redirect to login page if not logged in)
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . (SITE_URL ?? '/') . 'login.php');
            exit;
        }
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get user data
     */
    public static function getUserData($user_id = null) {
        global $db;
        
        if ($user_id === null) {
            $user_id = self::getUserId();
        }
        
        if ($user_id === null) {
            return null;
        }
        
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
}

?>
