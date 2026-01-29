<?php
/**
 * Admin Users Management
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireLogin();

$message = '';
$error = '';

// Handle user operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && AdminAuth::isSuperAdmin()) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($action === 'block' && $user_id > 0) {
            $stmt = $db->prepare('UPDATE users SET status = "blocked" WHERE id = ?');
            $stmt->execute([$user_id]);
            $message = 'User blocked successfully!';
        } elseif ($action === 'unblock' && $user_id > 0) {
            $stmt = $db->prepare('UPDATE users SET status = "active" WHERE id = ?');
            $stmt->execute([$user_id]);
            $message = 'User unblocked successfully!';
        } elseif ($action === 'delete' && $user_id > 0) {
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $message = 'User deleted successfully!';
        }
    }
}

// Handle search
$search = '';
if (isset($_GET['search'])) {
    $search = Security::sanitizeInput($_GET['search']);
}

// Get all users with search filter
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $users_query = $db->prepare('SELECT * FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ? OR phone LIKE ? ORDER BY created_at DESC');
    $users_query->execute([$search_term, $search_term, $search_term, $search_term, $search_term]);
    $users = $users_query->fetchAll();
} else {
    $users_query = $db->query('SELECT * FROM users ORDER BY created_at DESC');
    $users = $users_query->fetchAll();
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
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
        
        .users-section {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-md);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th {
            background: var(--bg-secondary);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table tr:hover {
            background: var(--bg-secondary);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            font-size: 12px;
        }
        
        .badge-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .badge-blocked {
            background: #f8d7da;
            color: #842029;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin: 0 2px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .user-details h4 {
            margin: 0;
            color: #333;
        }
        
        .user-details p {
            margin: 0;
            font-size: 12px;
            color: #666;
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
    </style>
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="admin-layout">
        <?php require_once 'includes/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>User Management</h1>
                <p>Manage customer user accounts</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="users-section">
                <h2 style="margin-top: 0;">All Users (<?php echo count($users); ?>)</h2>
                
                <!-- Search Bar -->
                <div class="search-bar">
                    <form method="GET" style="display: flex; gap: 10px; flex: 1;">
                        <input type="text" name="search" placeholder="🔍 Search by name, email, username, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="users.php" class="search-clear">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (!empty($search)): ?>
                    <div class="search-results">
                        Found <?php echo count($users); ?> result<?php echo count($users) !== 1 ? 's' : ''; ?> for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    </div>
                <?php endif; ?>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                            <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        
                                        <?php if ($user['status'] === 'active'): ?>
                                            <button type="submit" name="action" value="block" class="btn btn-warning" onclick="return confirm('Block this user?');">Block</button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="unblock" class="btn btn-primary">Unblock</button>
                                        <?php endif; ?>
                                        
                                        <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Delete this user?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
