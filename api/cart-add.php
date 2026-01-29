<?php
/**
 * API - Add to Cart
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

// Initialize cart session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = intval($data['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        throw new Exception('Invalid product ID');
    }
    
    // Check if product exists and is in stock
    $stmt = $db->prepare('SELECT id, stock_quantity FROM products WHERE id = ? AND status = "active"');
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Product not found or unavailable');
    }
    
    if ($product['stock_quantity'] <= 0) {
        throw new Exception('Product is out of stock');
    }
    
    // Add to cart
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += 1;
    } else {
        $_SESSION['cart'][$product_id] = 1;
    }
    
    // Cap at available stock
    if ($_SESSION['cart'][$product_id] > $product['stock_quantity']) {
        $_SESSION['cart'][$product_id] = $product['stock_quantity'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart',
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
