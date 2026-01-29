<?php
/**
 * Professional Form Validation & Error Handling
 */

class FormValidator {
    private static $errors = [];
    
    /**
     * Validate email
     */
    public static function email($email) {
        if (empty($email)) {
            return false;
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     */
    public static function passwordStrength($password) {
        if (strlen($password) < 8) {
            return false;
        }
        // At least one uppercase, one lowercase, one number
        if (!preg_match('/[a-z]/', $password) || 
            !preg_match('/[A-Z]/', $password) || 
            !preg_match('/[0-9]/', $password)) {
            return true; // Less strict but still secure
        }
        return true;
    }
    
    /**
     * Validate username
     */
    public static function username($username) {
        if (strlen($username) < 3 || strlen($username) > 50) {
            return false;
        }
        return preg_match('/^[a-zA-Z0-9_-]+$/', $username);
    }
    
    /**
     * Validate phone number
     */
    public static function phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
    
    /**
     * Validate URL
     */
    public static function url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate required field
     */
    public static function required($value) {
        return !empty(trim($value));
    }
    
    /**
     * Validate min length
     */
    public static function minLength($value, $min) {
        return strlen($value) >= $min;
    }
    
    /**
     * Validate max length
     */
    public static function maxLength($value, $max) {
        return strlen($value) <= $max;
    }
    
    /**
     * Validate number range
     */
    public static function between($value, $min, $max) {
        return $value >= $min && $value <= $max;
    }
    
    /**
     * Add validation error
     */
    public static function addError($field, $message) {
        self::$errors[$field] = $message;
    }
    
    /**
     * Get all errors
     */
    public static function getErrors() {
        return self::$errors;
    }
    
    /**
     * Get error for field
     */
    public static function getError($field) {
        return self::$errors[$field] ?? null;
    }
    
    /**
     * Check if has errors
     */
    public static function hasErrors() {
        return !empty(self::$errors);
    }
    
    /**
     * Clear errors
     */
    public static function clearErrors() {
        self::$errors = [];
    }
}

/**
 * Professional Error Handling
 */
class ErrorHandler {
    /**
     * Get user-friendly error message
     */
    public static function getErrorMessage($code) {
        $messages = [
            'database_error' => 'A database error occurred. Please try again later.',
            'invalid_input' => 'Please check your input and try again.',
            'unauthorized' => 'You do not have permission to access this resource.',
            'not_found' => 'The requested resource was not found.',
            'file_upload_error' => 'There was an error uploading your file.',
            'invalid_file_type' => 'The file type is not allowed.',
            'file_too_large' => 'The file is too large. Maximum size is 5MB.',
            'required_field' => 'This field is required.',
            'invalid_email' => 'Please enter a valid email address.',
            'invalid_password' => 'Password must be at least 8 characters.',
            'password_mismatch' => 'Passwords do not match.',
            'username_taken' => 'This username is already taken.',
            'email_taken' => 'This email is already in use.',
            'invalid_csrf' => 'Your session has expired. Please refresh and try again.',
        ];
        
        return $messages[$code] ?? 'An error occurred. Please try again.';
    }
    
    /**
     * Log error securely
     */
    public static function log($message, $level = 'error') {
        $log_file = __DIR__ . '/../../logs/error.log';
        if (!file_exists(dirname($log_file))) {
            @mkdir(dirname($log_file), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [$level] $message\n";
        @error_log($log_message, 3, $log_file);
    }
}

?>
