<?php
/**
 * Products Page
 */

$page_title = 'Products';
require_once 'includes/header.php';
require_once 'includes/auth.php';

// Get categories for filter
$categories_query = $db->prepare('SELECT * FROM categories WHERE status = "active"');
$categories_query->execute();
$categories = $categories_query->fetchAll();

// Get products with filters
$search = isset($_GET['search']) ? Security::sanitizeInput($_GET['search']) : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Build query
$query = 'SELECT * FROM products WHERE status = "active"';
$params = [];

if (!empty($search)) {
    $query .= ' AND (MATCH(name, description) AGAINST(? IN BOOLEAN MODE) OR name LIKE ?)';
    $search_param = '%' . $search . '%';
    $params[] = $search;
    $params[] = $search_param;
}

if ($category_id > 0) {
    $query .= ' AND category_id = ?';
    $params[] = $category_id;
}

$query .= ' ORDER BY created_at DESC';

$products_query = $db->prepare($query);
$products_query->execute($params);
$products = $products_query->fetchAll();

// Fetch tags for each product
foreach ($products as &$product) {
    $tags_query = $db->prepare('SELECT tag_name, tag_color FROM product_tags WHERE product_id = ? ORDER BY position ASC');
    $tags_query->execute([$product['id']]);
    $product['tags'] = $tags_query->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Ahanger MotoCorp</title>
    <link rel="stylesheet" href="/waaris/assets/css/professional-theme.css">
    <link rel="stylesheet" href="/waaris/assets/css/style.css">
    <style>
        .products-page {
            padding: 25px 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .page-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 700;
        }
        
        .products-wrapper {
            display: block;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .filters-sidebar {
            background: white;
            padding: 18px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .filter-title {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
        }
        
        .filter-form {
            display: flex;
            flex-direction: row;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 160px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #333;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }
        
        .products-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .products-count {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .product-content {
            padding: 14px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        
        .product-name {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
            line-height: 1.4;
        }
        
        .product-description {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.5;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
            margin-top: auto;
        }
        
        .product-price {
            font-size: 16px;
            font-weight: 700;
            color: #667eea;
        }
        
        .product-stock {
            font-size: 11px;
            color: #999;
        }
        
        .btn-add-cart {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-add-cart:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-add-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .no-products {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-size: 14px;
        }
        
        @media (max-width: 1200px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .page-title {
                font-size: 32px;
            }
        }
        
        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/header.php'; ?>
    
<div class="container products-page">
    <h1 class="page-title">Our Products</h1>
    
    <!-- Filters at Top -->
    <div class="filters-sidebar">
        <h3 class="filter-title">🔍 Filters</h3>
        
        <form method="GET" action="products.php" id="filter-form" class="filter-form">
            <!-- Search -->
            <div class="filter-group">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" 
                       placeholder="Search products..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <!-- Category Filter -->
            <div class="filter-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" 
                                <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="products.php" class="btn btn-secondary">Clear Filters</a>
            </div>
        </form>
    </div>
    
    <div class="products-wrapper">
        <!-- Products Grid -->
        <section class="products-section">
            <?php if (empty($products)): ?>
                <div class="no-products">
                    <p>No products found. Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="products-count">
                    Showing <?php echo count($products); ?> product<?php echo count($products) !== 1 ? 's' : ''; ?>
                </div>
                
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
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
                                <?php elseif ($product['stock_quantity'] === 0): ?>
                                    <span class="stock-badge out-of-stock">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-content">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?></p>
                                
                                <div class="product-meta">
                                    <span class="product-price">₹<?php echo number_format($product['price'], 2); ?></span>
                                    <span class="product-stock">Stock: <?php echo $product['stock_quantity']; ?></span>
                                </div>
                                
                                <button class="btn btn-secondary btn-add-cart" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        <?php echo $product['stock_quantity'] === 0 ? 'disabled' : ''; ?>>
                                    <?php echo $product['stock_quantity'] === 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script src="assets/js/cart.js"></script>

<?php require_once 'includes/footer.php'; ?>
