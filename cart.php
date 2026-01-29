<?php
/**
 * Cart Page
 */

$page_title = 'Shopping Cart';
require_once 'includes/header.php';
require_once 'includes/auth.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = $_SESSION['cart'];
$total_price = 0;
$cart_items = [];

// Get product details for cart items
if (!empty($cart)) {
    $placeholders = implode(',', array_keys($cart));
    $products_query = $db->prepare('SELECT * FROM products WHERE id IN (' . $placeholders . ')');
    $products_query->execute();
    $products = $products_query->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cart as $product_id => $quantity) {
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            $item_total = $product['price'] * $quantity;
            $total_price += $item_total;
            $cart_items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'item_total' => $item_total
            ];
        }
    }
}
?>

<div class="container cart-page">
    <h1 class="page-title">Shopping Cart</h1>
    
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <p class="empty-cart-message">Your cart is empty</p>
            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-wrapper">
            <section class="cart-items-section">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr class="cart-item" data-product-id="<?php echo $item['product']['id']; ?>">
                                <td class="cart-product-info">
                                    <img src="<?php echo htmlspecialchars($item['product']['image_url'] ?? 'assets/images/placeholder.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product']['name']); ?>" class="cart-product-image">
                                    <div>
                                        <h4><?php echo htmlspecialchars($item['product']['name']); ?></h4>
                                        <p class="product-sku">SKU: #<?php echo $item['product']['id']; ?></p>
                                    </div>
                                </td>
                                <td class="cart-price">₹<?php echo number_format($item['product']['price'], 2); ?></td>
                                <td class="cart-quantity">
                                    <input type="number" class="quantity-input" 
                                           value="<?php echo $item['quantity']; ?>" 
                                           min="1" max="<?php echo $item['product']['stock_quantity']; ?>"
                                           data-product-id="<?php echo $item['product']['id']; ?>">
                                </td>
                                <td class="cart-item-total">₹<?php echo number_format($item['item_total'], 2); ?></td>
                                <td class="cart-action">
                                    <button class="btn-remove-item" data-product-id="<?php echo $item['product']['id']; ?>">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            
            <aside class="cart-summary-section">
                <div class="cart-summary">
                    <h3>Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">₹<?php echo number_format($total_price, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span id="shipping">₹500.00</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Tax (18%):</span>
                        <span id="tax">₹<?php echo number_format($total_price * 0.18, 2); ?></span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="total">₹<?php echo number_format($total_price * 1.18 + 500, 2); ?></span>
                    </div>
                    
                    <?php if ($is_logged_in): ?>
                        <button class="btn btn-primary btn-checkout" style="margin-top: 20px; width: 100%;">
                            Proceed to Checkout
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary" style="margin-top: 20px; width: 100%; text-align: center; display: block;">
                            Login to Checkout
                        </a>
                    <?php endif; ?>
                    
                    <a href="products.php" class="btn btn-secondary" style="margin-top: 10px; width: 100%; text-align: center; display: block;">
                        Continue Shopping
                    </a>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<script src="assets/js/cart.js"></script>

<?php require_once 'includes/footer.php'; ?>
