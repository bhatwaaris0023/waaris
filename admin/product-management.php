<?php
/**
 * Admin Unified Product Management
 * Combines Products, Images, and Tags management in a single integrated form
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireLogin();

$message = '';
$error = '';
$upload_dir = '../assets/images/products/';

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ============ HANDLE PRODUCT SAVE (with image and tags) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_product') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else if (AdminAuth::isSuperAdmin()) {
        $product_id = $_POST['product_id'] ?? 0;
        $name = Security::sanitizeInput($_POST['name'] ?? '');
        $description = Security::sanitizeInput($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $alt_text = Security::sanitizeInput($_POST['alt_text'] ?? '');
        
        if (empty($name) || $price <= 0 || $category_id <= 0) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $db->beginTransaction();
                
                // Insert or Update Product
                if ($product_id > 0) {
                    $stmt = $db->prepare('UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, stock_quantity = ?, status = ? WHERE id = ?');
                    $stmt->execute([$name, $description, $price, $category_id, $stock_quantity, $status, $product_id]);
                    $created_product_id = $product_id;
                } else {
                    $stmt = $db->prepare('INSERT INTO products (name, description, price, category_id, stock_quantity, status) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $description, $price, $category_id, $stock_quantity, $status]);
                    $created_product_id = $db->lastInsertId();
                }
                
                // Handle Image Upload
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['product_image'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 5 * 1024 * 1024;
                    
                    if (!in_array($file['type'], $allowed_types)) {
                        throw new Exception('Invalid image type. Allowed: JPG, PNG, GIF, WebP');
                    } elseif ($file['size'] > $max_size) {
                        throw new Exception('Image too large. Max size: 5MB');
                    } else {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = 'product_' . $created_product_id . '_' . time() . '.' . $ext;
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            $image_url = 'assets/images/products/' . $filename;
                            $admin_id = $_SESSION['admin_id'];
                            
                            // Check if first image
                            $check_images = $db->prepare('SELECT COUNT(*) as count FROM product_images WHERE product_id = ?');
                            $check_images->execute([$created_product_id]);
                            $result = $check_images->fetch();
                            $is_first_image = ($result['count'] == 0);
                            
                            $insert_query = $db->prepare('
                                INSERT INTO product_images (product_id, image_url, alt_text, uploaded_by, is_primary)
                                VALUES (?, ?, ?, ?, ?)
                            ');
                            $insert_query->execute([$created_product_id, $image_url, $alt_text, $admin_id, $is_first_image ? 1 : 0]);
                            
                            if ($is_first_image) {
                                $update_product = $db->prepare('UPDATE products SET image_url = ? WHERE id = ?');
                                $update_product->execute([$image_url, $created_product_id]);
                            }
                        } else {
                            throw new Exception('Failed to move uploaded file');
                        }
                    }
                }
                
                // Handle Tags
                if (!empty($_POST['tags'])) {
                    $tags = $_POST['tags']; // Array of tag objects
                    foreach ($tags as $tag) {
                        if (!empty($tag['tag_name'])) {
                            $tag_name = Security::sanitizeInput($tag['tag_name']);
                            $tag_color = Security::sanitizeInput($tag['tag_color'] ?? '#FF5733');
                            $position = Security::sanitizeInput($tag['position'] ?? 'top-right');
                            
                            $insert_tag = $db->prepare('
                                INSERT INTO product_tags (product_id, tag_name, tag_color, position)
                                VALUES (?, ?, ?, ?)
                            ');
                            $insert_tag->execute([$created_product_id, $tag_name, $tag_color, $position]);
                        }
                    }
                }
                
                $db->commit();
                $message = $product_id > 0 ? 'Product updated successfully!' : 'Product created successfully!';
                
                // Redirect to clear form
                header("Location: product-management.php?success=1");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error saving product: ' . $e->getMessage();
            }
        }
    }
}

// ============ HANDLE PRODUCT DELETE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else if (AdminAuth::isSuperAdmin()) {
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            try {
                $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
                $stmt->execute([$product_id]);
                $message = 'Product deleted successfully!';
                Security::logSecurityEvent('PRODUCT_DELETED', ['product_id' => $product_id]);
            } catch (PDOException $e) {
                $error = 'Error deleting product: ' . $e->getMessage();
            }
        }
    }
}

// ============ HANDLE EXISTING TAG DELETION ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_tag') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $tag_id = intval($_POST['tag_id'] ?? 0);
        try {
            $delete_query = $db->prepare('DELETE FROM product_tags WHERE id = ?');
            $delete_query->execute([$tag_id]);
            $message = 'Tag deleted successfully';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ============ HANDLE IMAGE DELETION ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $image_id = intval($_POST['image_id'] ?? 0);
        try {
            // Get image details
            $get_image = $db->prepare('SELECT image_url, product_id, is_primary FROM product_images WHERE id = ?');
            $get_image->execute([$image_id]);
            $image = $get_image->fetch();
            
            if ($image) {
                // Delete file
                $filepath = $upload_dir . basename($image['image_url']);
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                // Delete record
                $delete_query = $db->prepare('DELETE FROM product_images WHERE id = ?');
                $delete_query->execute([$image_id]);
                
                // If this was primary, set another as primary
                if ($image['is_primary']) {
                    $next_image = $db->prepare('SELECT id, image_url FROM product_images WHERE product_id = ? LIMIT 1');
                    $next_image->execute([$image['product_id']]);
                    $next = $next_image->fetch();
                    
                    if ($next) {
                        $set_new_primary = $db->prepare('UPDATE product_images SET is_primary = TRUE WHERE id = ?');
                        $set_new_primary->execute([$next['id']]);
                        
                        $update_product = $db->prepare('UPDATE products SET image_url = ? WHERE id = ?');
                        $update_product->execute([$next['image_url'], $image['product_id']]);
                    }
                }
                
                $message = 'Image deleted successfully';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ============ HANDLE SET PRIMARY IMAGE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_primary') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $image_id = intval($_POST['image_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        try {
            $db->beginTransaction();
            
            // Remove primary from all other images for this product
            $update_all = $db->prepare('UPDATE product_images SET is_primary = FALSE WHERE product_id = ?');
            $update_all->execute([$product_id]);
            
            // Set this image as primary
            $set_primary = $db->prepare('UPDATE product_images SET is_primary = TRUE WHERE id = ?');
            $set_primary->execute([$image_id]);
            
            // Update product's main image
            $get_image = $db->prepare('SELECT image_url FROM product_images WHERE id = ?');
            $get_image->execute([$image_id]);
            $image = $get_image->fetch();
            
            $update_product = $db->prepare('UPDATE products SET image_url = ? WHERE id = ?');
            $update_product->execute([$image['image_url'], $product_id]);
            
            $db->commit();
            $message = 'Image set as primary successfully';
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ============ HANDLE EXISTING TAG UPDATE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tag') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $tag_id = intval($_POST['tag_id'] ?? 0);
        $tag_name = Security::sanitizeInput($_POST['tag_name'] ?? '');
        $tag_color = Security::sanitizeInput($_POST['tag_color'] ?? '');
        $position = Security::sanitizeInput($_POST['position'] ?? '');
        
        if (empty($tag_name)) {
            $error = 'Tag name cannot be empty';
        } else {
            try {
                $update_query = $db->prepare('
                    UPDATE product_tags SET tag_name = ?, tag_color = ?, position = ? WHERE id = ?
                ');
                $update_query->execute([$tag_name, $tag_color, $position, $tag_id]);
                $message = 'Tag updated successfully';
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// ============ LOAD DATA ============
$search = '';
if (isset($_GET['search'])) {
    $search = Security::sanitizeInput($_GET['search']);
}

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $products_query = $db->prepare('SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? ORDER BY p.created_at DESC');
    $products_query->execute([$search_term, $search_term, $search_term]);
    $products = $products_query->fetchAll();
} else {
    $products_query = $db->query('SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC');
    $products = $products_query->fetchAll();
}

$categories_query = $db->query('SELECT * FROM categories WHERE status = "active"');
$categories = $categories_query->fetchAll();

$edit_product = null;
$product_images = [];
$product_tags = [];

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_product = $stmt->fetch();
    
    if ($edit_product) {
        // Load product images
        $images_query = $db->prepare('
            SELECT pi.* FROM product_images pi
            WHERE pi.product_id = ?
            ORDER BY pi.is_primary DESC, pi.created_at DESC
        ');
        $images_query->execute([$edit_id]);
        $product_images = $images_query->fetchAll();
        
        // Load product tags
        $tags_query = $db->prepare('SELECT * FROM product_tags WHERE product_id = ? ORDER BY position ASC, id DESC');
        $tags_query->execute([$edit_id]);
        $product_tags = $tags_query->fetchAll();
    }
}

$tag_colors = [
    '#FF5733' => 'Red',
    '#FF8C00' => 'Orange',
    '#FFD700' => 'Gold',
    '#28a745' => 'Green',
    '#00CED1' => 'Cyan',
    '#4169E1' => 'Blue',
    '#9370DB' => 'Purple',
    '#FF1493' => 'Pink',
];

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Admin</title>
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

        .products-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 1200px) {
            .products-wrapper {
                grid-template-columns: 1fr;
            }
        }
        
        .form-section {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-md);
        }
        
        .products-list {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-md);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="text"], input[type="number"], input[type="email"], textarea, select, input[type="file"], input[type="color"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            flex: 1;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-edit {
            background: #0d6efd;
            color: white;
        }

        .btn-edit:hover {
            background: #0b5ed7;
        }

        .btn-add {
            background: var(--primary-color);
            color: white;
            padding: 8px 12px;
            font-size: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-add:hover {
            background: var(--primary-dark);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th {
            background: #f5f7fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .badge-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .badge-inactive {
            background: #f8d7da;
            color: #842029;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-bar input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .search-bar button {
            padding: 12px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-bar button:hover {
            background: var(--primary-dark);
        }

        .search-clear {
            padding: 12px 20px;
            background: #f0f0f0;
            color: #333;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .search-clear:hover {
            background: #e0e0e0;
        }

        .search-results {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            padding: 10px 0;
        }

        /* Image Upload Section */
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-label {
            display: block;
            padding: 30px;
            background: var(--bg-secondary);
            border: 2px dashed var(--primary-color);
            border-radius: var(--radius-lg);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .file-input-label:hover {
            background: #f0f7ff;
            border-color: var(--primary-dark);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-group input[type="file"] {
            position: absolute;
            left: -9999px;
        }

        #product-image-preview-container {
            margin-top: 20px;
            padding: 15px;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            display: none;
        }

        #product-image-name {
            font-size: 13px;
            color: var(--text-dark);
            font-weight: 500;
            word-break: break-all;
        }

        .existing-images {
            margin-top: 25px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }

        .existing-images h4 {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: var(--text-dark);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
        }

        .image-item {
            position: relative;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
            height: 150px;
            background: white;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .image-item:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            gap: 5px;
            background: rgba(0, 0, 0, 0.7);
            padding: 8px;
            opacity: 0;
            transition: var(--transition);
        }

        .image-item:hover .image-actions {
            opacity: 1;
        }

        .image-actions form {
            flex: 1;
        }

        .image-actions button {
            width: 100%;
            padding: 6px;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            color: white;
        }

        .btn-set-primary {
            background: var(--primary-color);
        }

        .btn-set-primary:hover {
            background: var(--primary-dark);
        }

        .btn-delete-image {
            background: var(--danger-color);
        }

        .btn-delete-image:hover {
            background: #dc2626;
        }

        /* Tags Section */
        .tags-section {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .tags-section h3 {
            margin-top: 0;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .tags-input-group {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 8px;
            margin-bottom: 10px;
        }

        .tags-input-group input[type="text"] {
            padding: 8px;
            font-size: 13px;
        }

        .tags-input-group input[type="color"] {
            width: 50px;
            height: 36px;
            padding: 2px;
            cursor: pointer;
        }

        .tags-input-group select {
            padding: 8px;
            font-size: 13px;
        }

        .color-presets {
            display: flex;
            gap: 5px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .color-preset {
            width: 28px;
            height: 28px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .color-preset:hover {
            transform: scale(1.1);
            border-color: #333;
        }

        .tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .tag-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .tag-badge .badge-label {
            flex: 1;
        }

        .tag-badge .badge-actions {
            display: flex;
            gap: 4px;
        }

        .tag-badge button {
            background: rgba(0,0,0,0.2);
            border: none;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tag-badge button:hover {
            background: rgba(0,0,0,0.4);
        }

        .existing-tags {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .existing-tags h4 {
            margin-top: 0;
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .existing-tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .existing-tag-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 12px;
        }

        .existing-tag-color {
            width: 20px;
            height: 20px;
            border-radius: 3px;
        }

        .existing-tag-actions {
            display: flex;
            gap: 4px;
        }

        .existing-tag-actions button {
            padding: 2px 6px;
            font-size: 11px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .existing-tag-actions .btn-edit {
            background: #0d6efd;
            color: white;
        }

        .existing-tag-actions .btn-delete {
            background: #dc3545;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        .btn-close {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-save {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="admin-layout">
        <?php require_once 'includes/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>Product Management</h1>
                <p>Create and manage products with images and tags all in one form</p>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Product saved successfully!</div>
            <?php endif; ?>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="products-wrapper">
                <div class="form-section">
                    <h2 style="margin-top: 0;"><?php echo $edit_product ? 'Edit Product' : 'Create New Product'; ?></h2>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="save_product">
                        <input type="hidden" name="product_id" value="<?php echo $edit_product['id'] ?? 0; ?>">
                        
                        <!-- Basic Product Info -->
                        <div class="form-group">
                            <label for="name">Product Name*</label>
                            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($edit_product['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price (₹)*</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo $edit_product['price'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Category*</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $edit_product && $edit_product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock_quantity">Stock Quantity*</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" min="0" required value="<?php echo $edit_product['stock_quantity'] ?? '0'; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo !$edit_product || $edit_product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $edit_product && $edit_product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <!-- Product Image Upload -->
                        <div class="form-group">
                            <label for="product_image">Product Image</label>
                            <div class="file-input-wrapper">
                                <label for="product_image" class="file-input-label">
                                    📁 Click to select image or drag and drop
                                </label>
                                <input type="file" id="product_image" name="product_image" accept="image/*" onchange="previewProductImage(event)">
                            </div>
                            <small style="color: var(--text-light); margin-top: 8px; display: block; font-size: 12px;">
                                Supported: JPG, PNG, GIF, WebP (Max 5MB)
                            </small>

                            <!-- Image Name Display -->
                            <div id="product-image-preview-container">
                                <label style="font-size: 12px; color: var(--text-light); display: block; margin-bottom: 8px;">Selected File:</label>
                                <div id="product-image-name"></div>
                            </div>

                            <?php if ($edit_product && !empty($product_images)): ?>
                                <div class="existing-images">
                                    <h4>📸 Existing Images (<?php echo count($product_images); ?>)</h4>
                                    <div class="image-grid">
                                        <?php foreach ($product_images as $img): ?>
                                            <div class="image-item">
                                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; padding: 10px; background: var(--bg-primary); word-break: break-word; text-align: center;">
                                                    <span style="font-size: 12px; color: var(--text-dark); font-weight: 500;"><?php echo htmlspecialchars(basename($img['image_url'])); ?></span>
                                                </div>
                                                <div class="image-actions">
                                                    <form method="POST" style="flex: 1;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                        <input type="hidden" name="action" value="set_primary">
                                                        <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                                        <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                                                        <button type="submit" class="btn-set-primary" <?php echo $img['is_primary'] ? 'disabled' : ''; ?>>
                                                            <?php echo $img['is_primary'] ? '✓ Primary' : '☆ Set Primary'; ?>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="flex: 1;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                        <input type="hidden" name="action" value="delete_image">
                                                        <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                                        <button type="submit" class="btn-delete-image" onclick="return confirm('Delete this image?');">🗑️ Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="alt_text">Image Alt Text (for SEO)</label>
                            <input type="text" id="alt_text" name="alt_text" placeholder="Describe the image...">
                        </div>

                        <!-- Product Tags -->
                        <div class="tags-section">
                            <h3>🏷️ Add Tags to Product</h3>
                            
                            <div class="tags-input-group">
                                <input type="text" id="new_tag_name" placeholder="Tag name (e.g., Sale, New, Hot)" maxlength="15">
                                <input type="color" id="new_tag_color" value="#FF5733">
                                <select id="new_tag_position">
                                    <option value="top-left">Top Left</option>
                                    <option value="top-center">Top Center</option>
                                    <option value="top-right" selected>Top Right</option>
                                </select>
                                <button type="button" class="btn-add" onclick="addTagToForm()">Add Tag</button>
                            </div>

                            <div class="color-presets">
                                <?php foreach ($tag_colors as $color => $color_name): ?>
                                    <div class="color-preset" style="background: <?php echo $color; ?>;" title="<?php echo htmlspecialchars($color_name); ?>" onclick="document.getElementById('new_tag_color').value = '<?php echo $color; ?>';"></div>
                                <?php endforeach; ?>
                            </div>

                            <div class="tags-list" id="tags-list">
                                <!-- Tags will be added here by JavaScript -->
                            </div>

                            <?php if ($edit_product && !empty($product_tags)): ?>
                                <div class="existing-tags">
                                    <h4>📌 Existing Tags (<?php echo count($product_tags); ?>)</h4>
                                    <div class="existing-tags-list">
                                        <?php foreach ($product_tags as $tag): ?>
                                            <div class="existing-tag-item">
                                                <div class="existing-tag-color" style="background: <?php echo htmlspecialchars($tag['tag_color']); ?>;"></div>
                                                <span><?php echo htmlspecialchars($tag['tag_name']); ?></span>
                                                <small style="color: #999;">(<?php echo str_replace('-', ' ', ucfirst($tag['position'])); ?>)</small>
                                                <div class="existing-tag-actions">
                                                    <button type="button" class="btn-edit btn-small" onclick="editExistingTag(<?php echo htmlspecialchars(json_encode($tag)); ?>)">Edit</button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                        <input type="hidden" name="action" value="delete_tag">
                                                        <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                                        <button type="submit" class="btn-delete btn-small" onclick="return confirm('Delete this tag?')">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $edit_product ? '✏️ Update Product' : '➕ Create Product'; ?>
                            </button>
                            <?php if ($edit_product): ?>
                                <a href="product-management.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="products-list">
                    <h2 style="margin-top: 0;">📋 All Products (<?php echo count($products); ?>)</h2>
                    
                    <div class="search-bar">
                        <form method="GET" style="display: flex; gap: 10px; flex: 1;">
                            <input type="text" name="search" placeholder="🔍 Search by name, description, or category..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                            <button type="submit">Search</button>
                            <?php if (!empty($search)): ?>
                                <a href="product-management.php" class="search-clear">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if (!empty($search)): ?>
                        <div class="search-results">
                            Found <?php echo count($products); ?> result<?php echo count($products) !== 1 ? 's' : ''; ?> for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        </div>
                    <?php endif; ?>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #999; padding: 30px;">No products yet. Create one on the left!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                        <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                        <td><?php echo $product['stock_quantity']; ?></td>
                                        <td><span class="badge badge-<?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></td>
                                        <td style="white-space: nowrap;">
                                            <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-primary btn-small">Edit</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this product?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Tag Modal -->
    <div class="modal" id="editTagModal">
        <div class="modal-content">
            <div class="modal-header">Edit Tag</div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="update_tag">
                <input type="hidden" name="tag_id" id="edit_tag_id">
                
                <div class="form-group">
                    <label for="edit_tag_name">Tag Name *</label>
                    <input type="text" id="edit_tag_name" name="tag_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_tag_color">Tag Color</label>
                    <input type="color" id="edit_tag_color" name="tag_color">
                </div>
                
                <div class="form-group">
                    <label for="edit_tag_position">Tag Position</label>
                    <select id="edit_tag_position" name="position">
                        <option value="top-left">Top Left</option>
                        <option value="top-center">Top Center</option>
                        <option value="top-right">Top Right</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-close" onclick="closeEditTagModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let tagCount = 0;
        const tagsData = {};

        function addTagToForm() {
            const tagName = document.getElementById('new_tag_name').value.trim();
            const tagColor = document.getElementById('new_tag_color').value;
            const tagPosition = document.getElementById('new_tag_position').value;

            if (!tagName) {
                alert('Please enter a tag name');
                return;
            }

            tagCount++;
            const tagId = 'tag_' + tagCount;
            tagsData[tagId] = {
                tag_name: tagName,
                tag_color: tagColor,
                position: tagPosition
            };

            const tagsList = document.getElementById('tags-list');
            const tagBadge = document.createElement('div');
            tagBadge.className = 'tag-badge';
            tagBadge.style.backgroundColor = tagColor;
            tagBadge.id = tagId;
            tagBadge.innerHTML = `
                <span class="badge-label">${escapeHtml(tagName)} <small>(${tagPosition.replace('-', ' ')})</small></span>
                <div class="badge-actions">
                    <button type="button" onclick="removeTag('${tagId}')" title="Remove">×</button>
                </div>
            `;
            
            tagsList.appendChild(tagBadge);

            // Clear inputs
            document.getElementById('new_tag_name').value = '';
            document.getElementById('new_tag_color').value = '#FF5733';
            document.getElementById('new_tag_position').value = 'top-right';
        }

        function removeTag(tagId) {
            document.getElementById(tagId).remove();
            delete tagsData[tagId];
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Before form submission, add hidden inputs for tags
        document.querySelector('form').addEventListener('submit', function(e) {
            const tagsList = document.getElementById('tags-list');
            const tags = Object.keys(tagsData);
            
            tags.forEach((tagId, index) => {
                const tag = tagsData[tagId];
                
                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = `tags[${index}][tag_name]`;
                nameInput.value = tag.tag_name;
                this.appendChild(nameInput);

                const colorInput = document.createElement('input');
                colorInput.type = 'hidden';
                colorInput.name = `tags[${index}][tag_color]`;
                colorInput.value = tag.tag_color;
                this.appendChild(colorInput);

                const positionInput = document.createElement('input');
                positionInput.type = 'hidden';
                positionInput.name = `tags[${index}][position]`;
                positionInput.value = tag.position;
                this.appendChild(positionInput);
            });
        });

        function editExistingTag(tag) {
            document.getElementById('edit_tag_id').value = tag.id;
            document.getElementById('edit_tag_name').value = tag.tag_name;
            document.getElementById('edit_tag_color').value = tag.tag_color;
            document.getElementById('edit_tag_position').value = tag.position;
            document.getElementById('editTagModal').classList.add('active');
        }

        function closeEditTagModal() {
            document.getElementById('editTagModal').classList.remove('active');
        }

        // Drag and drop for file input
        const fileInput = document.getElementById('product_image');
        const fileLabel = document.querySelector('.file-input-label');
        
        if (fileInput && fileLabel) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileLabel.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                fileLabel.addEventListener(eventName, () => {
                    fileLabel.style.background = '#e9ecef';
                });
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                fileLabel.addEventListener(eventName, () => {
                    fileLabel.style.background = 'white';
                });
            });
            
            fileLabel.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
            });
        }

        // Show selected image filename
        function previewProductImage(event) {
            const file = event.target.files[0];
            const container = document.getElementById('product-image-preview-container');
            const nameDisplay = document.getElementById('product-image-name');
            
            if (file) {
                nameDisplay.textContent = file.name;
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
    </script>
</body>
</html>
