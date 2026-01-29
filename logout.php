<?php
/**
 * Logout
 */

require_once 'config/db.php';
require_once 'config/security.php';
require_once 'includes/auth.php';

Auth::logout();
header('Location: index.php');
exit;

?>
