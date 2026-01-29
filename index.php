<?php
/**
 * Home Page
 */

$page_title = 'Home';
require_once 'includes/header.php';
require_once 'includes/auth.php';

// Get featured products from featured_products table, or fallback to latest 3
$featured_query = $db->prepare('
    SELECT p.* FROM products p
    LEFT JOIN featured_products fp ON p.id = fp.product_id
    WHERE p.status = "active"
    ORDER BY fp.is_featured DESC, fp.display_order ASC, p.created_at DESC
    LIMIT 3
');
$featured_query->execute();
$featured_products = $featured_query->fetchAll();

// Fetch tags for each featured product
foreach ($featured_products as &$product) {
    $tags_query = $db->prepare('SELECT tag_name, tag_color FROM product_tags WHERE product_id = ? ORDER BY position ASC');
    $tags_query->execute([$product['id']]);
    $product['tags'] = $tags_query->fetchAll();
}

// Get active testimonials
$testimonials_query = $db->prepare('
    SELECT id, customer_name, customer_image, customer_title, rating, testimonial_text
    FROM testimonials
    WHERE is_active = TRUE
    ORDER BY display_order ASC, created_at DESC
');
$testimonials_query->execute();
$testimonials = $testimonials_query->fetchAll();
?>

<section class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">Welcome to Ahanger MotoCorp</h1>
        <p class="hero-subtitle">Experience Premium Motorcycles & Accessories</p>
        <p class="hero-description">Discover our exclusive collection of high-performance motorcycles and premium accessories designed for passionate riders.</p>
        <a href="products.php" class="btn btn-primary btn-lg">Shop Now</a>
    </div>
</section>

<section class="featured-section">
    <div class="container">
        <h2 class="section-title">Featured Products</h2>
        
        <div class="products-grid">
            <?php foreach ($featured_products as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'assets/images/placeholder.png'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php if (!empty($product['tags'])): ?>
                            <div class="product-tags">
                                <?php foreach ($product['tags'] as $tag): ?>
                                    <span class="product-tag" style="background-color: <?php echo htmlspecialchars($tag['tag_color']); ?>">
                                        <?php echo htmlspecialchars($tag['tag_name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['stock_quantity'] < 5): ?>
                            <span class="stock-badge">Low Stock</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-content">
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100) . '...'); ?></p>
                        
                        <div class="product-meta">
                            <span class="product-price">₹<?php echo number_format($product['price'], 2); ?></span>
                            <span class="product-stock">Stock: <?php echo $product['stock_quantity']; ?></span>
                        </div>
                        
                        <button class="btn btn-secondary btn-add-cart" data-product-id="<?php echo $product['id']; ?>">
                            Add to Cart
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="features-section">
    <div class="container">
        <h2 class="section-title">Why Choose Us?</h2>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🚚</div>
                <h3>Fast Shipping</h3>
                <p>Quick and secure delivery to your doorstep</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Payments</h3>
                <p>Safe and encrypted transactions</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">✅</div>
                <h3>Quality Assured</h3>
                <p>100% authentic and genuine products</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3>24/7 Support</h3>
                <p>Dedicated customer support team</p>
            </div>
        </div>
    </div>
</section>

<section class="testimonials-section">
    <div class="container">
        <h2 class="section-title">Customer Testimonials</h2>
        <p class="testimonials-subtitle">Hear what our satisfied customers have to say about us</p>
        
        <?php if (!empty($testimonials)): ?>
            <div class="testimonials-grid">
                <?php $count = 0; foreach ($testimonials as $testimonial): if ($count >= 6) break; $count++; ?>
                    <div class="testimonial-card">
                        <div class="testimonial-header">
                            <?php if (!empty($testimonial['customer_image'])): ?>
                                <img src="<?php echo htmlspecialchars($testimonial['customer_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($testimonial['customer_name']); ?>" 
                                     class="testimonial-image">
                            <?php else: ?>
                                <div class="testimonial-image-placeholder">
                                    <?php echo htmlspecialchars(substr($testimonial['customer_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="testimonial-info">
                                <h4 class="testimonial-name"><?php echo htmlspecialchars($testimonial['customer_name']); ?></h4>
                                <?php if (!empty($testimonial['customer_title'])): ?>
                                    <p class="testimonial-title"><?php echo htmlspecialchars($testimonial['customer_title']); ?></p>
                                <?php endif; ?>
                                
                                <div class="testimonial-rating">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <span class="star <?php echo $i < $testimonial['rating'] ? 'filled' : 'empty'; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        
                        <p class="testimonial-text"><?php echo htmlspecialchars($testimonial['testimonial_text']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-testimonials">No testimonials available at this moment.</p>
        <?php endif; ?>
    </div>
</section>

<script src="assets/js/cart.js"></script>

<?php require_once 'includes/footer.php'; ?>
