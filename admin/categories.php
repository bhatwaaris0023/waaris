<?php
/**
 * Admin Categories Management
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireSuperAdmin();

$message = '';
$error = '';

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save') {
            $category_id = $_POST['category_id'] ?? 0;
            $name = Security::sanitizeInput($_POST['name'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'active';
            
            if (empty($name)) {
                $error = 'Category name is required.';
            } else {
                try {
                    if ($category_id > 0) {
                        $stmt = $db->prepare('UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?');
                        $stmt->execute([$name, $description, $status, $category_id]);
                        $message = 'Category updated!';
                    } else {
                        $stmt = $db->prepare('INSERT INTO categories (name, description, status) VALUES (?, ?, ?)');
                        $stmt->execute([$name, $description, $status]);
                        $message = 'Category added!';
                    }
                } catch (PDOException $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $category_id = intval($_POST['category_id'] ?? 0);
            if ($category_id > 0) {
                try {
                    $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
                    $stmt->execute([$category_id]);
                    $message = 'Category deleted!';
                } catch (PDOException $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get all categories
$categories = $db->query('SELECT * FROM categories ORDER BY created_at DESC')->fetchAll();

// Get category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_category = $stmt->fetch();
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Admin</title>
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
        
        .categories-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 1000px) {
            .categories-wrapper {
                grid-template-columns: 1fr;
            }
        }
        
        .form-section, .categories-list {
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
        
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            flex: 1;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-dark);
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
            padding: 6px 12px;
            font-size: 12px;
            flex: none;
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
    </style>
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="admin-layout">
        <?php require_once 'includes/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>Category Management</h1>
                <p>Organize and manage product categories</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="categories-wrapper">
                <div class="form-section">
                    <h2 style="margin-top: 0;"><?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?></h2>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="category_id" value="<?php echo $edit_category['id'] ?? 0; ?>">
                        
                        <div class="form-group">
                            <label for="name">Category Name*</label>
                            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo !$edit_category || $edit_category['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $edit_category && $edit_category['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                            </button>
                            <?php if ($edit_category): ?>
                                <a href="categories.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="categories-list">
                    <h2 style="margin-top: 0;">Categories (<?php echo count($categories); ?>)</h2>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td><span class="badge badge-<?php echo $cat['status']; ?>"><?php echo ucfirst($cat['status']); ?></span></td>
                                    <td>
                                        <a href="?edit=<?php echo $cat['id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; flex: none;">Edit</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
