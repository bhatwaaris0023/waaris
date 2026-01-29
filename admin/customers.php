<?php
/**
 * Admin Customer Management
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireLogin();

$message = '';
$error = '';
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$customer = null;
$orders = [];

// Handle customer update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        if ($_POST['action'] === 'update' && $customer_id > 0) {
            $first_name = Security::sanitizeInput($_POST['first_name'] ?? '');
            $last_name = Security::sanitizeInput($_POST['last_name'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $phone = Security::sanitizeInput($_POST['phone'] ?? '');
            $vehicle_number = Security::sanitizeInput($_POST['vehicle_number'] ?? '');
            
            if (!empty($first_name) && !empty($email)) {
                $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, vehicle_number = ? WHERE id = ?');
                $stmt->execute([$first_name, $last_name, $email, $phone, $vehicle_number, $customer_id]);
                $message = 'Customer updated successfully!';
            } else {
                $error = 'Please fill in all required fields.';
            }
        } elseif ($_POST['action'] === 'send_alert' && $customer_id > 0) {
            $alert_type = $_POST['alert_type'] ?? '';
            $message_text = Security::sanitizeInput($_POST['message'] ?? '');
            
            if (!empty($alert_type) && !empty($message_text)) {
                $stmt = $db->prepare('INSERT INTO customer_alerts (user_id, alert_type, message, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$customer_id, $alert_type, $message_text]);
                $message = 'Alert sent successfully!';
            } else {
                $error = 'Please fill in all alert fields.';
            }
        }
    }
}

// Get customer details
if ($customer_id > 0) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    // Get customer orders
    $stmt = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll();
}

// Get all customers for listing
$search = '';
if (isset($_GET['search'])) {
    $search = Security::sanitizeInput($_GET['search']);
}

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $customers_stmt = $db->prepare('SELECT u.*, COUNT(o.id) as order_count FROM users u LEFT JOIN orders o ON u.id = o.user_id WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.phone LIKE ? GROUP BY u.id ORDER BY u.created_at DESC');
    $customers_stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term]);
} else {
    $customers_stmt = $db->prepare('SELECT u.*, COUNT(o.id) as order_count FROM users u LEFT JOIN orders o ON u.id = o.user_id GROUP BY u.id ORDER BY u.created_at DESC');
    $customers_stmt->execute();
}
$customers = $customers_stmt->fetchAll();

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Admin</title>
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
        
        .customers-container {
            display: block;
        }
        
        .customers-list {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
        }
        
        .customers-list h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--text-dark);
            font-weight: 700;
            font-size: 20px;
        }
        
        .customer-list-item {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            display: inline-block;
            background: var(--bg-secondary);
        }
        
        .customer-list-item:hover {
            background: var(--border-light);
            transform: translateX(3px);
        }
        
        .customer-list-item.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .customer-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .customer-email {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 3px;
        }
        
        .customer-orders {
            font-size: 11px;
            opacity: 0.6;
        }
        
        .customer-details {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-md);
        }
        
        .customer-details h2,
        .customer-details h3 {
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .detail-card {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
        }
        
        .detail-label {
            font-size: 12px;
            color: var(--text-light);
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--text-dark);
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-update {
            flex: 1;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-update:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-send-alert {
            flex: 1;
            padding: 12px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-send-alert:hover {
            background: #dc2626;
            box-shadow: var(--shadow-lg);
        }
        
        .orders-section {
            margin-top: 30px;
            background: var(--bg-primary);
            padding: 20px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }
        
        .order-item {
            background: var(--bg-secondary);
            padding: 12px;
            margin-bottom: 10px;
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-id {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .order-amount {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 3px;
        }
        
        .order-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-shipped {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 15px;
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

        .search-bar {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }

        .search-bar button {
            padding: 10px 15px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            transition: var(--transition);
        }

        .search-bar button:hover {
            background: var(--primary-dark);
        }

        .search-bar input {
            flex: 1;
            padding: 10px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 13px;
            transition: var(--transition);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .search-results {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
            padding: 8px 0;
        }
        
        .no-selection {
            text-align: center;
            color: #999;
            padding: 40px 20px;
        }
        
        /* Responsive Layout */
        @media (max-width: 1024px) {
            .customer-list-item {
                display: inline-block;
                margin-right: 8px;
            }
        }
        
        @media (max-width: 768px) {
            .customer-list-item {
                display: block;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="admin-layout">
        <?php require_once 'includes/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>Customer Management</h1>
                <p>View and manage customer accounts and activity</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="customers-container">
                <!-- Unified Customers Section -->
                <div class="customers-list">
                    <h3>All Customers (<?php echo count($customers); ?>)</h3>
                    
                    <!-- Search Bar -->
                    <div class="search-bar">
                        <form method="GET" style="display: flex; gap: 8px; width: 100%;">
                            <input type="text" name="search" placeholder="🔍 Search customers..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
                            <button type="submit" style="padding: 10px 15px; background: var(--primary-color); color: white; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; font-size: 13px;">Search</button>
                        </form>
                    </div>

                    <?php if (!empty($search)): ?>
                        <div class="search-results">
                            Found <?php echo count($customers); ?> customer<?php echo count($customers) !== 1 ? 's' : ''; ?> for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($customers)): ?>
                        <div style="text-align: center; padding: 20px; color: var(--text-light);">
                            No customers found
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php foreach ($customers as $cust): ?>
                            <div class="customer-list-item <?php echo ($customer_id === $cust['id']) ? 'active' : ''; ?>" 
                                 onclick="window.location.href='customers.php?id=<?php echo $cust['id']; ?>'">
                                <div class="customer-name">
                                    👤 <?php echo htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']); ?>
                                </div>
                                <div class="customer-email" style="font-size: 11px; margin-top: 4px;"><?php echo htmlspecialchars($cust['email']); ?></div>
                                <?php if(!empty($cust['vehicle_number'])): ?>
                                    <div style="font-size: 11px; margin-top: 2px; color: #666;">🚗 <?php echo htmlspecialchars($cust['vehicle_number']); ?></div>
                                <?php endif; ?>
                                <div class="customer-orders" style="font-size: 11px; margin-top: 3px;">Orders: <?php echo $cust['order_count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Customer Details Section -->
                <div class="customer-details">
                    <?php if ($customer): ?>
                        <h2 style="margin-top: 0;">
                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                        </h2>
                        
                        <div class="detail-card">
                            <div class="detail-label">Email</div>
                            <div class="detail-value"><?php echo htmlspecialchars($customer['email']); ?></div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value"><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-label">Vehicle Number</div>
                            <div class="detail-value"><?php echo htmlspecialchars($customer['vehicle_number'] ?? '-'); ?></div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-label">Username</div>
                            <div class="detail-value"><?php echo htmlspecialchars($customer['username']); ?></div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-label">Joined</div>
                            <div class="detail-value"><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></div>
                        </div>
                        
                        <!-- Edit Form -->
                        <form method="POST" style="margin-top: 25px;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="update">
                            
                            <h3>Edit Customer</h3>
                            
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Vehicle Number</label>
                                <input type="text" name="vehicle_number" value="<?php echo htmlspecialchars($customer['vehicle_number'] ?? ''); ?>">
                            </div>
                            
                            <button type="submit" class="btn-update">✓ Update Customer</button>
                        </form>
                        
                        <!-- Send Alert Form -->
                        <form method="POST" style="margin-top: 25px;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="send_alert">
                            
                            <h3>Send Alert</h3>
                            
                            <div class="form-group">
                                <label>Alert Type</label>
                                <select name="alert_type" required>
                                    <option value="">-- Select Alert Type --</option>
                                    <option value="payment">Payment Due</option>
                                    <option value="service">Service Reminder</option>
                                    <option value="promotion">Promotion</option>
                                    <option value="update">Account Update</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Message</label>
                                <textarea name="message" rows="4" placeholder="Enter message for customer..." required></textarea>
                            </div>
                            
                            <button type="submit" class="btn-send-alert">📢 Send Alert</button>
                        </form>
                        
                        <!-- Orders Section -->
                        <?php if (!empty($orders)): ?>
                            <div class="orders-section">
                                <h3>Recent Orders</h3>
                                
                                <?php foreach ($orders as $order): ?>
                                    <div class="order-item">
                                        <div class="order-info">
                                            <div class="order-id">Order #<?php echo $order['id']; ?></div>
                                            <div class="order-amount">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                                        </div>
                                        <span class="order-status status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-selection">
                            <h2>👤 Select a Customer</h2>
                            <p>Choose a customer from the list to view and manage their details</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
