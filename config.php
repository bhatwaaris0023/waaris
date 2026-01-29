<?php
/**
 * PRODUCTION CONFIGURATION FILE
 * Update the values below for your live environment
 */

// ========================================
// DATABASE CONFIGURATION
// ========================================
// CHANGE THESE VALUES FOR YOUR PRODUCTION DATABASE
// For Render: These values come from Environment Variables
define('DB_HOST', getenv('DB_HOST') ?: 'dpg-d5rn7effte5s73cdunb0-a');           // Production database host (e.g., your-server.com or database IP)
define('DB_NAME', getenv('DB_NAME') ?: 'waaris_db');          // Production database name
define('DB_USER', getenv('DB_USER') ?: 'waaris_db_user');                // Production database username (use strong username)
define('DB_PASS', getenv('DB_PASS') ?: '592y7fahxY2iAHDiKbeTHL27n8qxHmER');                    // Production database password (use strong password)
// Optional: DB driver and port (set DB_DRIVER=pgsql for PostgreSQL on Render)
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
define('DB_PORT', getenv('DB_PORT') ?: '');
define('CONFIG_LOADED', true);

// ========================================
// APPLICATION SETTINGS
// ========================================
// CHANGE THIS TO YOUR LIVE DOMAIN
// For Render: Will auto-detect from environment
define('SITE_URL', getenv('SITE_URL') ?: 'https://waaris.onrender.com'); // Change to: https://yourdomain.com
define('SITE_NAME', 'Ahanger MotoCorp');       // Your business name
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'development'); // 'production' or 'development'

// ========================================
// FILE UPLOAD SETTINGS
// ========================================
define('UPLOAD_DIR', __DIR__ . '/assets/images/');  // Upload directory path
define('MAX_UPLOAD_SIZE', 5242880);                 // Max file size in bytes (5MB)
