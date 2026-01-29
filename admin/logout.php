<?php
/**
 * Admin Logout
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::logout();
header('Location: login.php');
exit;

?>
