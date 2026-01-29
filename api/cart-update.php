<?php
/**
 * API - Update Cart
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

try {
    $product_id = intval($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? $_GET['quantity'] ?? 0);
    
    if ($product_id <= 0) {
        throw new Exception('Invalid product ID');
    }
    
    if ($quantity < 0) {
        throw new Exception('Invalid quantity');
    }
    
    if ($quantity === 0) {
        // Remove item
        unset($_SESSION['cart'][$product_id]);
    } else {
        // Check stock
        $stmt = $db->prepare('SELECT stock_quantity FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        if ($quantity > $product['stock_quantity']) {
            throw new Exception('Quantity exceeds available stock');
        }
        
        $_SESSION['cart'][$product_id] = $quantity;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart updated',
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
