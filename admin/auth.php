<?php
/**
 * Admin Authentication & Authorization
 */

// Load configuration
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

class AdminAuth {
    /**
     * Admin Login
     */
    public static function login($username, $password) {
        global $db;
        
        // Check login attempts
        if (!Security::checkLoginAttempts($username)) {
            return ['success' => false, 'message' => 'Account temporarily locked. Please try again later.'];
        }
        
        // Find admin user
        $stmt = $db->prepare('SELECT id, username, password, role, status FROM admin_users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        
        if (!$admin || !Security::verifyPassword($password, $admin['password'])) {
            Security::recordFailedLogin($username);
            Security::logSecurityEvent('ADMIN_FAILED_LOGIN', ['username' => $username]);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if ($admin['status'] !== 'active') {
            return ['success' => false, 'message' => 'Admin account is inactive'];
        }
        
        // Set session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        
        // Reset login attempts
        Security::resetLoginAttempts($username);
        
        // Update last login
        $update_stmt = $db->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?');
        $update_stmt->execute([$admin['id']]);
        
        Security::logSecurityEvent('ADMIN_LOGIN', ['admin_id' => $admin['id'], 'username' => $username]);
        
        return ['success' => true, 'message' => 'Login successful'];
    }
    
    /**
     * Check if admin is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in']);
    }
    
    /**
     * Check if admin is super admin
     */
    public static function isSuperAdmin() {
        return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
    }
    
    /**
     * Require admin login
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . 'admin/login.php');
            exit;
        }
    }
    
    /**
     * Require super admin
     */
    public static function requireSuperAdmin() {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            header('Location: ' . SITE_URL . 'admin/dashboard.php');
            exit;
        }
    }
    
    /**
     * Logout admin
     */
    public static function logout() {
        Security::logSecurityEvent('ADMIN_LOGOUT', ['admin_id' => $_SESSION['admin_id'] ?? null]);
        session_destroy();
        return true;
    }
    
    /**
     * Get current admin ID
     */
    public static function getAdminId() {
        return $_SESSION['admin_id'] ?? null;
    }
}

?>
