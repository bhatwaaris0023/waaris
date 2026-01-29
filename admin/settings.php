<?php
/**
 * Admin Settings
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireSuperAdmin();

$message = '';
$error = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Verify current password
            $stmt = $db->prepare('SELECT password FROM admin_users WHERE id = ?');
            $stmt->execute([AdminAuth::getAdminId()]);
            $admin = $stmt->fetch();
            
            if (!Security::verifyPassword($current_password, $admin['password'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new_password) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                $hashed = Security::hashPassword($new_password);
                $update = $db->prepare('UPDATE admin_users SET password = ? WHERE id = ?');
                $update->execute([$hashed, AdminAuth::getAdminId()]);
                $message = 'Password changed successfully!';
                Security::logSecurityEvent('ADMIN_PASSWORD_CHANGED', ['admin_id' => AdminAuth::getAdminId()]);
            }
        }
    }
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin</title>
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
        
        .settings-section {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 25px;
            max-width: 500px;
            box-shadow: var(--shadow-md);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 14px;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-lg);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--radius-md);
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
                <h1>Admin Settings</h1>
                <p>Manage your account and security settings</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="settings-section">
                <h2 style="margin-top: 0;">Change Password</h2>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" minlength="8" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                    </div>
                    
                    <button type="submit" class="btn-submit">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
