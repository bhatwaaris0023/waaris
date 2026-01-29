<?php
/**
 * API - Remove from Cart
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

try {
    $product_id = intval($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        throw new Exception('Invalid product ID');
    }
    
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Item removed from cart',
        'cart_count' => count($_SESSION['cart'])
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>
