<?php
/**
 * Admin Testimonials Management
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

// Check admin login
AdminAuth::requireLogin();

// Check if user is super admin
if ($_SESSION['admin_role'] !== 'super_admin') {
    Security::logSecurityEvent('UNAUTHORIZED_ACCESS', ['page' => 'testimonials', 'admin_id' => $_SESSION['admin_id']]);
    die('Access denied. Only super administrators can manage testimonials.');
}

// Handle delete
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $delete_stmt = $db->prepare('DELETE FROM testimonials WHERE id = ?');
    if ($delete_stmt->execute([$id])) {
        Security::logSecurityEvent('TESTIMONIAL_DELETED', ['testimonial_id' => $id, 'admin_id' => $_SESSION['admin_id']]);
        $_SESSION['success_message'] = 'Testimonial deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to delete testimonial.';
    }
    header('Location: testimonials.php');
    exit;
}

// Handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'], ['add', 'edit'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_title = trim($_POST['customer_title'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);
    $testimonial_text = trim($_POST['testimonial_text'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    $errors = [];
    if (empty($customer_name) || strlen($customer_name) < 2) {
        $errors[] = 'Customer name is required and must be at least 2 characters.';
    }
    if (empty($testimonial_text) || strlen($testimonial_text) < 10) {
        $errors[] = 'Testimonial text is required and must be at least 10 characters.';
    }
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Rating must be between 1 and 5.';
    }
    
    if (empty($errors)) {
        $customer_image = '';
        
        // Handle image upload
        if (isset($_FILES['customer_image']) && $_FILES['customer_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../assets/images/testimonials/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['customer_image']['name']);
            $filename = 'testimonial_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . strtolower($file_info['extension']);
            $filepath = $upload_dir . $filename;
            
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower($file_info['extension']);
            
            if (in_array($file_ext, $allowed_extensions) && $_FILES['customer_image']['size'] <= 5 * 1024 * 1024) {
                if (move_uploaded_file($_FILES['customer_image']['tmp_name'], $filepath)) {
                    $customer_image = 'assets/images/testimonials/' . $filename;
                }
            } else {
                $errors[] = 'Invalid image file. Please upload JPG, PNG, or GIF (max 5MB).';
            }
        }
        
        if (empty($errors)) {
            if ($_POST['action'] === 'add') {
                $insert_stmt = $db->prepare('
                    INSERT INTO testimonials (customer_name, customer_image, customer_title, rating, testimonial_text, display_order, is_active, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                if ($insert_stmt->execute([
                    $customer_name,
                    $customer_image,
                    $customer_title,
                    $rating,
                    $testimonial_text,
                    $display_order,
                    $is_active,
                    $_SESSION['admin_id']
                ])) {
                    Security::logSecurityEvent('TESTIMONIAL_CREATED', ['admin_id' => $_SESSION['admin_id']]);
                    $_SESSION['success_message'] = 'Testimonial added successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to add testimonial.';
                }
            } else {
                // Edit mode
                $id = (int)($_POST['testimonial_id'] ?? 0);
                if ($customer_image === '') {
                    // Keep existing image
                    $existing = $db->query('SELECT customer_image FROM testimonials WHERE id = ' . $id)->fetch();
                    $customer_image = $existing['customer_image'] ?? '';
                }
                
                $update_stmt = $db->prepare('
                    UPDATE testimonials
                    SET customer_name = ?, customer_image = ?, customer_title = ?, rating = ?, testimonial_text = ?, display_order = ?, is_active = ?
                    WHERE id = ?
                ');
                if ($update_stmt->execute([
                    $customer_name,
                    $customer_image,
                    $customer_title,
                    $rating,
                    $testimonial_text,
                    $display_order,
                    $is_active,
                    $id
                ])) {
                    Security::logSecurityEvent('TESTIMONIAL_UPDATED', ['testimonial_id' => $id, 'admin_id' => $_SESSION['admin_id']]);
                    $_SESSION['success_message'] = 'Testimonial updated successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to update testimonial.';
                }
            }
            
            header('Location: testimonials.php');
            exit;
        }
    }
}

// Get all testimonials
$testimonials = $db->query('
    SELECT t.*, a.username as created_by_name
    FROM testimonials t
    LEFT JOIN admin_users a ON t.created_by = a.id
    ORDER BY t.display_order ASC, t.created_at DESC
')->fetchAll();

// Get testimonial for editing if requested
$edit_testimonial = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_stmt = $db->prepare('SELECT * FROM testimonials WHERE id = ?');
    $edit_stmt->execute([$id]);
    $edit_testimonial = $edit_stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Testimonials - Admin</title>
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
            color: var(--text-dark);
        }

        .page-header p {
            margin: 0;
            font-size: 16px;
            color: var(--text-light);
        }

        .form-section {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-dark);
            transition: var(--transition);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: var(--text-light);
            font-size: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
            border-width: 2px;
        }

        .checkbox-group span {
            color: var(--text-dark);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-light);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }

        .alert-error ul {
            margin: 8px 0 0 20px;
        }

        .alert-error li {
            margin-bottom: 4px;
        }

        .testimonials-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .testimonials-table thead {
            background: var(--bg-tertiary);
        }

        .testimonials-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--border-color);
        }

        .testimonials-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .testimonials-table tbody tr:hover {
            background: var(--bg-tertiary);
        }

        .testimonial-actions {
            display: flex;
            gap: 6px;
        }

        .testimonial-actions a,
        .testimonial-actions button {
            padding: 4px 8px;
            font-size: 12px;
        }

        .rating-display {
            color: var(--warning-color);
            font-weight: 600;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .testimonial-preview {
            max-width: 300px;
            white-space: normal;
        }

        .image-preview {
            max-width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }

        .rating-display {
            color: var(--warning-color);
            font-size: 16px;
        }

        h2 {
            color: var(--text-dark);
            margin-bottom: 24px;
            font-size: 20px;
            font-weight: 700;
        }

        .table-wrapper {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            padding: 25px;
        }

        .table-wrapper p {
            text-align: center;
            padding: 20px;
            color: var(--text-light);
        }

        .overflow-table {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/admin-header.php'; ?>
    
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1><?php echo $edit_testimonial ? 'Edit Testimonial' : 'Manage Testimonials'; ?></h1>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="form-section">
                <h2><?php echo $edit_testimonial ? 'Edit Testimonial' : 'Add New Testimonial'; ?></h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $edit_testimonial ? 'edit' : 'add'; ?>">
                    <?php if ($edit_testimonial): ?>
                        <input type="hidden" name="testimonial_id" value="<?php echo $edit_testimonial['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_name">Customer Name *</label>
                            <input type="text" id="customer_name" name="customer_name" 
                                   value="<?php echo htmlspecialchars($edit_testimonial['customer_name'] ?? $_POST['customer_name'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_title">Customer Title/Designation</label>
                            <input type="text" id="customer_title" name="customer_title" 
                                   placeholder="e.g., Motorcycle Enthusiast"
                                   value="<?php echo htmlspecialchars($edit_testimonial['customer_title'] ?? $_POST['customer_title'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rating">Rating *</label>
                            <select id="rating" name="rating" required>
                                <option value="5" <?php echo (($edit_testimonial['rating'] ?? $_POST['rating'] ?? 5) == 5) ? 'selected' : ''; ?>>5 Stars - Excellent</option>
                                <option value="4" <?php echo (($edit_testimonial['rating'] ?? $_POST['rating'] ?? 5) == 4) ? 'selected' : ''; ?>>4 Stars - Very Good</option>
                                <option value="3" <?php echo (($edit_testimonial['rating'] ?? $_POST['rating'] ?? 5) == 3) ? 'selected' : ''; ?>>3 Stars - Good</option>
                                <option value="2" <?php echo (($edit_testimonial['rating'] ?? $_POST['rating'] ?? 5) == 2) ? 'selected' : ''; ?>>2 Stars - Fair</option>
                                <option value="1" <?php echo (($edit_testimonial['rating'] ?? $_POST['rating'] ?? 5) == 1) ? 'selected' : ''; ?>>1 Star - Poor</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="display_order">Display Order</label>
                            <input type="number" id="display_order" name="display_order" min="0" 
                                   value="<?php echo htmlspecialchars($edit_testimonial['display_order'] ?? $_POST['display_order'] ?? 0); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="testimonial_text">Testimonial Text *</label>
                        <textarea id="testimonial_text" name="testimonial_text" required><?php echo htmlspecialchars($edit_testimonial['testimonial_text'] ?? $_POST['testimonial_text'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_image">Customer Image</label>
                            <input type="file" id="customer_image" name="customer_image" accept="image/*">
                            <small>Upload JPG, PNG, or GIF (max 5MB)</small>
                            <?php if ($edit_testimonial && !empty($edit_testimonial['customer_image'])): ?>
                                <div style="margin-top: 10px;">
                                    <img src="../<?php echo htmlspecialchars($edit_testimonial['customer_image']); ?>" class="image-preview" alt="Current image">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" name="is_active" value="1" 
                                       <?php echo (($edit_testimonial['is_active'] ?? 1) == 1) ? 'checked' : ''; ?>>
                                <span>Display on Homepage</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <?php if ($edit_testimonial): ?>
                            <a href="testimonials.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_testimonial ? 'Update Testimonial' : 'Add Testimonial'; ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php if (!$edit_testimonial): ?>
                <div class="table-wrapper">
                    <h2>All Testimonials <?php echo !empty($testimonials) ? '(' . count($testimonials) . ')' : ''; ?></h2>
                    
                    <?php if (!empty($testimonials)): ?>
                        <div class="overflow-table">
                            <table class="testimonials-table">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Title/Designation</th>
                                        <th>Rating</th>
                                        <th>Testimonial Text</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($testimonials as $testimonial): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($testimonial['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($testimonial['customer_title'] ?? '-'); ?></td>
                                            <td><span class="rating-display">★<?php echo $testimonial['rating']; ?></span></td>
                                            <td>
                                                <div style="max-width: 300px; word-wrap: break-word;">
                                                    "<?php echo htmlspecialchars(substr($testimonial['testimonial_text'], 0, 100)); ?><?php echo strlen($testimonial['testimonial_text']) > 100 ? '...' : ''; ?>"
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $testimonial['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $testimonial['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="testimonial-actions">
                                                    <a href="testimonials.php?edit=<?php echo $testimonial['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this testimonial?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $testimonial['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Del</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No testimonials yet. Add your first testimonial above!</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
