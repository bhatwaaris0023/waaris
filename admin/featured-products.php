<?php
/**
 * Admin Featured Products Management
 */

$page_title = 'Featured Products';
require_once '../config/db.php';
require_once '../config/security.php';
require_once 'auth.php';

// Check admin access
if (!AdminAuth::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get all products
$products_query = $db->prepare('SELECT * FROM products WHERE status = "active" ORDER BY name');
$products_query->execute();
$all_products = $products_query->fetchAll();

// Get featured products
$featured_query = $db->prepare('
    SELECT fp.*, p.name, p.price, p.image_url, p.description
    FROM featured_products fp
    JOIN products p ON fp.product_id = p.id
    WHERE fp.is_featured = TRUE
    ORDER BY fp.display_order ASC
');
$featured_query->execute();
$featured_products = $featured_query->fetchAll();

$message = '';

// Handle Add Featured Product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = Security::sanitizeInput($_POST['action']);
        
        if ($action === 'add_featured') {
            $product_id = intval($_POST['product_id']);
            $display_order = intval($_POST['display_order'] ?? count($featured_products));
            
            // Check if already featured
            $check_query = $db->prepare('SELECT id FROM featured_products WHERE product_id = ? AND is_featured = TRUE');
            $check_query->execute([$product_id]);
            
            if ($check_query->fetch()) {
                $message = '<div class="alert alert-error">This product is already in featured products</div>';
            } else {
                try {
                    $admin_id = $_SESSION['admin_id'];
                    $insert_query = $db->prepare('
                        INSERT INTO featured_products (product_id, display_order, is_featured, set_by)
                        VALUES (?, ?, TRUE, ?)
                    ');
                    $insert_query->execute([$product_id, $display_order, $admin_id]);
                    $message = '<div class="alert alert-success">Product added to featured products</div>';
                    
                    // Refresh featured products
                    $featured_query->execute();
                    $featured_products = $featured_query->fetchAll();
                } catch (Exception $e) {
                    $message = '<div class="alert alert-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
        
        elseif ($action === 'remove_featured') {
            $featured_id = intval($_POST['featured_id']);
            
            try {
                $delete_query = $db->prepare('UPDATE featured_products SET is_featured = FALSE WHERE id = ?');
                $delete_query->execute([$featured_id]);
                $message = '<div class="alert alert-success">Product removed from featured products</div>';
                
                // Refresh featured products
                $featured_query->execute();
                $featured_products = $featured_query->fetchAll();
            } catch (Exception $e) {
                $message = '<div class="alert alert-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        
        elseif ($action === 'update_order') {
            try {
                $db->beginTransaction();
                
                // Get all featured items with new order
                if (isset($_POST['product_order'])) {
                    foreach ($_POST['product_order'] as $featured_id => $new_order) {
                        $order_update = $db->prepare('UPDATE featured_products SET display_order = ? WHERE id = ? AND is_featured = TRUE');
                        $order_update->execute([intval($new_order), intval($featured_id)]);
                    }
                }
                
                $db->commit();
                $message = '<div class="alert alert-success">Display order updated successfully</div>';
                
                // Refresh featured products
                $featured_query->execute();
                $featured_products = $featured_query->fetchAll();
            } catch (Exception $e) {
                $db->rollBack();
                $message = '<div class="alert alert-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// Get products that are not featured
$unfeatured_query = $db->prepare('
    SELECT p.* FROM products p
    WHERE p.status = "active"
    AND p.id NOT IN (SELECT product_id FROM featured_products WHERE is_featured = TRUE)
    ORDER BY p.name
');
$unfeatured_query->execute();
$unfeatured_products = $unfeatured_query->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>
    <link rel="stylesheet" href="assets/admin-style.css">
    <style>
        .admin-layout {
            display: flex;
            min-height: calc(100vh - 80px);
            width: 100%;
            gap: 0;
        }

        .admin-sidebar {
            width: 260px;
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            position: sticky;
            top: 80px;
            height: fit-content;
            flex-shrink: 0;
        }

        .admin-content {
            flex: 1;
            padding: 24px;
            background: var(--bg-secondary);
            overflow-x: auto;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }

        .page-header p {
            margin: 0;
            font-size: 16px;
            color: #64748b;
        }
        
        .featured-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-top: 0;
        }
        
        .add-featured-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            height: fit-content;
        }
        
        .add-featured-section h3 {
            margin-bottom: 20px;
            font-size: 16px;
            color: var(--text-dark);
            font-weight: 700;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-size: 14px;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.3s;
        }

        .form-group select:hover,
        .form-group input:hover {
            border-color: var(--primary-color);
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-add {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }
        
        .featured-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid var(--border-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .featured-section h3 {
            color: var(--text-dark);
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .featured-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        
        .featured-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            align-items: center;
            padding: 20px;
            background: var(--light-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .featured-item:hover {
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.15);
            transform: translateY(-2px);
            border-color: var(--primary-color);
        }
        
        .featured-image {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
        }
        
        .featured-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .featured-content {
            display: grid;
            gap: 8px;
        }
        
        .featured-name {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
        }
        
        .featured-price {
            color: #6f42c1;
            font-weight: bold;
            font-size: 14px;
        }
        
        .featured-desc {
            color: #666;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .featured-controls {
            display: grid;
            gap: 8px;
            grid-template-columns: 60px 1fr;
            align-items: center;
        }
        
        .order-input {
            width: 60px !important;
            padding: 5px !important;
            text-align: center;
            font-size: 13px;
        }
        
        .btn-remove {
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        .btn-update-order {
            padding: 6px 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-update-order:hover {
            background: #218838;
        }
        
        .order-update-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        .no-featured {
            text-align: center;
            padding: 40px;
            color: #999;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .preview-section {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .preview-section h4 {
            margin-bottom: 15px;
            font-size: 14px;
            color: #333;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .preview-card {
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .preview-image {
            width: 100%;
            height: 120px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .preview-info {
            padding: 8px;
            font-size: 12px;
        }
        
        .preview-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .preview-price {
            color: #6f42c1;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="admin-layout">
        <?php require_once 'includes/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <p>Manage products displayed on the home page</p>
            </div>
            
            <?php if ($message) echo $message; ?>
                
                <div class="featured-container">
                    <!-- Add Featured Section -->
                    <div class="add-featured-section">
                        <h3>📌 Add Featured Product</h3>
                        
                        <?php if (empty($unfeatured_products)): ?>
                            <div style="padding: 20px; text-align: center; color: #999;">
                                <p>All products are already featured!</p>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="add_featured">
                                
                                <div class="form-group">
                                    <label for="product_id">Select Product *</label>
                                    <select id="product_id" name="product_id" required>
                                        <option value="">-- Select Product --</option>
                                        <?php foreach ($unfeatured_products as $product): ?>
                                            <option value="<?php echo $product['id']; ?>">
                                                <?php echo htmlspecialchars($product['name']); ?> (₹<?php echo number_format($product['price'], 2); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="display_order">Display Order</label>
                                    <input type="number" id="display_order" name="display_order" value="<?php echo count($featured_products) + 1; ?>" min="1">
                                    <small style="color: #666; margin-top: 3px; display: block;">Position on home page (1 = first)</small>
                                </div>
                                
                                <button type="submit" class="btn-add">Add to Featured</button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="preview-section">
                            <h4>📱 Home Page Preview</h4>
                            <p style="color: #666; font-size: 12px; margin-bottom: 10px;">Current featured products will appear like this:</p>
                            <div class="preview-grid">
                                <?php $preview_count = 0; foreach ($featured_products as $fp): 
                                    if ($preview_count >= 3) break;
                                    $preview_count++;
                                ?>
                                    <div class="preview-card">
                                        <div class="preview-image">
                                            <img src="<?php echo htmlspecialchars($fp['image_url'] ?? 'assets/images/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($fp['name']); ?>">
                                        </div>
                                        <div class="preview-info">
                                            <div class="preview-name"><?php echo htmlspecialchars(substr($fp['name'], 0, 20)); ?></div>
                                            <div class="preview-price">₹<?php echo number_format($fp['price'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Featured Products List -->
                    <div class="featured-section">
                        <h3>⭐ Featured Products (<?php echo count($featured_products); ?>)</h3>
                        
                        <?php if (empty($featured_products)): ?>
                            <div class="no-featured">
                                <p>No featured products yet. Add one from the left panel!</p>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" id="featured-form">
                                <div class="featured-list">
                                    <?php foreach ($featured_products as $featured): ?>
                                        <div class="featured-item">
                                            <div class="featured-image">
                                                <img src="<?php echo htmlspecialchars($featured['image_url'] ?? 'assets/images/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($featured['name']); ?>">
                                            </div>
                                            
                                            <div class="featured-content">
                                                <div class="featured-name"><?php echo htmlspecialchars($featured['name']); ?></div>
                                                <div class="featured-price">₹<?php echo number_format($featured['price'], 2); ?></div>
                                                <div class="featured-desc"><?php echo htmlspecialchars(substr($featured['description'], 0, 80)); ?>...</div>
                                            </div>
                                            
                                            <div class="featured-controls">
                                                <input type="number" name="product_order[<?php echo $featured['id']; ?>]" value="<?php echo $featured['display_order']; ?>" class="order-input" min="1">
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="action" value="remove_featured">
                                                    <input type="hidden" name="featured_id" value="<?php echo $featured['id']; ?>">
                                                    <button type="submit" class="btn-remove" onclick="return confirm('Remove from featured products?')">Remove</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="order-update-section">
                                    <p style="font-size: 12px; color: #666; margin-bottom: 10px;">Edit order numbers above and click to save new display order</p>
                                    <input type="hidden" name="action" value="update_order">
                                    <button type="submit" class="btn-update-order">💾 Update Display Order</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
    </div>
</body>
</html>
