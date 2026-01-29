<?php
/**
 * Admin Users & Roles Management - Manage admin accounts
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireLogin();
AdminAuth::requireSuperAdmin(); // Only super admins can manage admin users

$error = '';
$success = '';

// Handle add admin user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = Security::sanitizeInput($_POST['role'] ?? 'admin');
        
        if (empty($username) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!Security::validateEmail($email)) {
            $error = 'Invalid email address.';
        } elseif ($role !== 'admin' && $role !== 'super_admin') {
            $error = 'Invalid role selected.';
        } else {
            // Check if username or email already exists
            $stmt = $db->prepare('SELECT id FROM admin_users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                try {
                    $hashed_password = Security::hashPassword($password);
                    $insert_stmt = $db->prepare('INSERT INTO admin_users (username, email, password, role, status) VALUES (?, ?, ?, ?, "active")');
                    $insert_stmt->execute([$username, $email, $hashed_password, $role]);
                    
                    Security::logSecurityEvent('ADMIN_USER_CREATED', [
                        'created_by' => $_SESSION['admin_id'],
                        'username' => $username,
                        'role' => $role
                    ]);
                    
                    $success = "Admin user '$username' created successfully!";
                } catch (PDOException $e) {
                    error_log('Admin creation error: ' . $e->getMessage());
                    $error = 'Failed to create admin user. Please try again.';
                }
            }
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $admin_id = intval($_POST['admin_id'] ?? 0);
        $status = Security::sanitizeInput($_POST['status'] ?? '');
        
        if ($admin_id <= 0 || ($status !== 'active' && $status !== 'inactive')) {
            $error = 'Invalid request.';
        } else {
            try {
                $update_stmt = $db->prepare('UPDATE admin_users SET status = ? WHERE id = ?');
                $update_stmt->execute([$status, $admin_id]);
                
                $success = 'Admin status updated successfully!';
                
                Security::logSecurityEvent('ADMIN_STATUS_UPDATED', [
                    'updated_by' => $_SESSION['admin_id'],
                    'admin_id' => $admin_id,
                    'new_status' => $status
                ]);
            } catch (PDOException $e) {
                error_log('Status update error: ' . $e->getMessage());
                $error = 'Failed to update status. Please try again.';
            }
        }
    }
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $admin_id = intval($_POST['admin_id'] ?? 0);
        $role = Security::sanitizeInput($_POST['role'] ?? '');
        
        if ($admin_id <= 0 || ($role !== 'admin' && $role !== 'super_admin')) {
            $error = 'Invalid request.';
        } else if ($admin_id == $_SESSION['admin_id']) {
            $error = 'You cannot change your own role.';
        } else {
            try {
                $update_stmt = $db->prepare('UPDATE admin_users SET role = ? WHERE id = ?');
                $update_stmt->execute([$role, $admin_id]);
                
                $success = 'Admin role updated successfully!';
                
                Security::logSecurityEvent('ADMIN_ROLE_UPDATED', [
                    'updated_by' => $_SESSION['admin_id'],
                    'admin_id' => $admin_id,
                    'new_role' => $role
                ]);
            } catch (PDOException $e) {
                error_log('Role update error: ' . $e->getMessage());
                $error = 'Failed to update role. Please try again.';
            }
        }
    }
}

// Get all admin users
$admins_query = $db->query('SELECT * FROM admin_users ORDER BY created_at DESC');
$all_admins = $admins_query->fetchAll();

// Get statistics
$total_admins = count($all_admins);
$active_admins = count(array_filter($all_admins, fn($a) => $a['status'] === 'active'));
$super_admins = count(array_filter($all_admins, fn($a) => $a['role'] === 'super_admin'));

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users Management - Admin Panel</title>
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
        
        .page-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            grid-column: 1 / -1;
        }
        
        .admin-stat {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .admin-stat h3 {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        
        .admin-stat .number {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .add-admin-form {
            background: var(--bg-primary);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-add-admin {
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
        }
        
        .btn-add-admin:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-lg);
        }
        
        .admins-list {
            background: var(--bg-primary);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .admins-list h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .admin-item {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 20px;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 10px;
        }
        
        .admin-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .admin-info {
            flex: 1;
        }
        
        .admin-username {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .admin-email {
            font-size: 12px;
            color: #999;
            margin-top: 3px;
        }
        
        .admin-joined {
            font-size: 11px;
            color: #ccc;
            margin-top: 3px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-super-admin {
            background: #fef0e0;
            color: #c05000;
        }
        
        .badge-admin {
            background: #e0f2ff;
            color: #0066cc;
        }
        
        .badge-active {
            background: #efe;
            color: #060;
        }
        
        .badge-inactive {
            background: #fee;
            color: #c00;
        }
        
        .admin-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-small {
            padding: 6px 12px;
            background: #f0f0f0;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-small:hover {
            background: #e0e0e0;
        }
        
        .btn-small.btn-danger {
            background: #fee;
            border-color: #fcc;
            color: #c00;
        }
        
        .btn-small.btn-danger:hover {
            background: #fdd;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            color: #333;
            margin: 0;
        }
        
        .modal-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-primary {
            padding: 10px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            padding: 10px;
            background: var(--bg-secondary);
            color: var(--text-dark);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #060;
            border: 1px solid #cfc;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="admin-layout">
        <?php require_once 'includes/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>Admin Users & Roles Management</h1>
                <p>Manage admin accounts, roles, and permissions</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="admin-stat">
                    <h3>Total Admins</h3>
                    <div class="number"><?php echo $total_admins; ?></div>
                </div>
                <div class="admin-stat">
                    <h3>Active Admins</h3>
                    <div class="number"><?php echo $active_admins; ?></div>
                </div>
                <div class="admin-stat">
                    <h3>Super Admins</h3>
                    <div class="number"><?php echo $super_admins; ?></div>
                </div>
            </div>
            
            <div class="page-content">
                <div class="add-admin-form">
                    <h2 style="margin-bottom: 25px; color: #333;">Add New Admin</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required placeholder="Enter username">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required placeholder="Enter email">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required placeholder="Minimum 8 characters">
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-add-admin">Add Admin User</button>
                    </form>
                </div>
                
                <div class="admins-list">
                    <h2>Admin Users</h2>
                    <?php if (empty($all_admins)): ?>
                        <p style="color: #999; text-align: center; padding: 30px 0;">No admin users found.</p>
                    <?php else: ?>
                        <?php foreach ($all_admins as $admin): ?>
                            <div class="admin-item">
                                <div class="admin-info">
                                    <div class="admin-username"><?php echo htmlspecialchars($admin['username']); ?></div>
                                    <div class="admin-email"><?php echo htmlspecialchars($admin['email']); ?></div>
                                    <div class="admin-joined">Joined: <?php echo date('M d, Y', strtotime($admin['created_at'])); ?></div>
                                </div>
                                
                                <div>
                                    <span class="badge <?php echo $admin['role'] === 'super_admin' ? 'badge-super-admin' : 'badge-admin'; ?>">
                                        <?php echo htmlspecialchars($admin['role']); ?>
                                    </span>
                                </div>
                                
                                <div>
                                    <span class="badge <?php echo $admin['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo htmlspecialchars($admin['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="admin-actions">
                                    <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                        <button class="btn-small" onclick="openUpdateRoleModal(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['role']); ?>')">
                                            Change Role
                                        </button>
                                        <button class="btn-small <?php echo $admin['status'] === 'active' ? 'btn-danger' : ''; ?>" 
                                                onclick="updateStatus(<?php echo $admin['id']; ?>, '<?php echo $admin['status'] === 'active' ? 'inactive' : 'active'; ?>')">
                                            <?php echo $admin['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #ccc; font-size: 12px;">Your Account</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Update Role Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Admin Role</h2>
            </div>
            <form method="POST" action="" id="roleForm">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="admin_id" id="roleAdminId">
                
                <div class="form-group">
                    <label for="roleSelect">Select New Role</label>
                    <select id="roleSelect" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn-primary">Update Role</button>
                    <button type="button" class="btn-secondary" onclick="closeRoleModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openUpdateRoleModal(adminId, currentRole) {
            document.getElementById('roleAdminId').value = adminId;
            document.getElementById('roleSelect').value = currentRole;
            document.getElementById('roleModal').classList.add('show');
        }
        
        function closeRoleModal() {
            document.getElementById('roleModal').classList.remove('show');
        }
        
        function updateStatus(adminId, newStatus) {
            if (confirm('Are you sure you want to ' + (newStatus === 'active' ? 'activate' : 'deactivate') + ' this admin?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="admin_id" value="${adminId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('roleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    </script>
        </div>
    </div>
</body>
</html>
