<?php
/**
 * API - Checkout
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to checkout'
    ]);
    exit;
}

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Cart is empty'
    ]);
    exit;
}

try {
    $user_id = Auth::getUserId();
    $cart = $_SESSION['cart'];
    $total_amount = 0;
    
    // Calculate total and verify stock
    foreach ($cart as $product_id => $quantity) {
        $stmt = $db->prepare('SELECT id, price, stock_quantity FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product || $product['stock_quantity'] < $quantity) {
            throw new Exception('Some products are out of stock');
        }
        
        $total_amount += ($product['price'] * $quantity);
    }
    
    // Add shipping and tax
    $total_amount = $total_amount * 1.18 + 500; // 18% tax + 500 shipping
    
    // Create order
    $stmt = $db->prepare('INSERT INTO orders (user_id, total_amount, shipping_address, payment_method) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user_id, $total_amount, 'User Address', 'COD']);
    
    $order_id = $db->lastInsertId();
    
    // Add order items
    foreach ($cart as $product_id => $quantity) {
        $stmt = $db->prepare('SELECT price FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        $stmt = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
        $stmt->execute([$order_id, $product_id, $quantity, $product['price']]);
        
        // Update stock
        $stmt = $db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');
        $stmt->execute([$quantity, $product_id]);
    }
    
    // Clear cart
    $_SESSION['cart'] = [];
    
    Security::logSecurityEvent('ORDER_CREATED', ['order_id' => $order_id, 'user_id' => $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>
