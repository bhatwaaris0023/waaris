<?php
/**
 * API - Get Cart Count
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

echo json_encode([
    'success' => true,
    'count' => count($_SESSION['cart'])
]);

?>
