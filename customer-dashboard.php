<?php
/**
 * Customer Dashboard
 * Central hub for customers to manage alerts, bookings, orders, and profile
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in
Auth::requireLogin();

$user_id = $_SESSION['user_id'];
$user_username = $_SESSION['username'];

// Get user details
$user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Get unread alerts count
$alerts_count = $db->query("SELECT COUNT(*) as count FROM customer_alerts WHERE user_id = $user_id AND is_read = false")->fetch()['count'];

// Get recent alerts
$alerts_stmt = $db->prepare('SELECT * FROM customer_alerts WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$alerts_stmt->execute([$user_id]);
$recent_alerts = $alerts_stmt->fetchAll();

// Get order count
$orders_count = $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id = $user_id")->fetch()['count'];

// Get recent orders
$orders_stmt = $db->prepare('SELECT o.*, COUNT(oi.id) as items FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC LIMIT 5');
$orders_stmt->execute([$user_id]);
$recent_orders = $orders_stmt->fetchAll();

// Get total spent
$total_spent = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE user_id = $user_id")->fetch()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Ahanger MotoCorp</title>
    <link rel="stylesheet" href="/waaris/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .dashboard-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .dashboard-header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .dashboard-nav {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .nav-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .nav-btn:hover {
            background: white;
            color: #667eea;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-item {
            padding: 15px;
            border-left: 4px solid #667eea;
            background: #f9f9ff;
            margin-bottom: 12px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .alert-item:hover {
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.2);
        }
        
        .alert-item.unread {
            background: #f0f3ff;
            font-weight: 500;
        }
        
        .alert-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .alert-type.payment {
            background: #fee;
            color: #c00;
        }
        
        .alert-type.service {
            background: #efe;
            color: #060;
        }
        
        .alert-type.promotion {
            background: #fef0e0;
            color: #c05000;
        }
        
        .alert-type.update {
            background: #e0f2ff;
            color: #0066cc;
        }
        
        .alert-message {
            color: #666;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .alert-time {
            font-size: 12px;
            color: #999;
        }
        
        .order-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            background: #fafafa;
            margin-bottom: 12px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-info h4 {
            color: #333;
            margin-bottom: 4px;
        }
        
        .order-info p {
            font-size: 12px;
            color: #999;
        }
        
        .order-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .order-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .order-status.shipped {
            background: #cfe2ff;
            color: #084298;
        }
        
        .order-status.delivered {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        
        .empty-state p {
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .view-all-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
            margin-top: 15px;
            transition: all 0.3s;
        }
        
        .view-all-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .profile-section {
            background: #f9f9ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .profile-section h3 {
            color: #333;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .profile-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .profile-info p {
            font-size: 13px;
            color: #666;
        }
        
        .profile-info strong {
            color: #333;
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 3px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-buttons a {
            flex: 1;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1>🏍️ Welcome, <?php echo htmlspecialchars($user['first_name'] ?: $user['username']); ?>!</h1>
            <p>Manage your orders, alerts, and service bookings</p>
            <div class="dashboard-nav">
                <a href="#" class="nav-btn">📊 Dashboard</a>
                <a href="customer-orders.php" class="nav-btn">📦 My Orders</a>
                <a href="customer-alerts.php" class="nav-btn">🔔 Alerts (<?php echo $alerts_count; ?>)</a>
                <a href="customer-booking.php" class="nav-btn">🔧 Book Service</a>
                <a href="customer-profile.php" class="nav-btn">👤 Profile</a>
                <a href="/waaris/logout.php" class="nav-btn">🚪 Logout</a>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="number"><?php echo $orders_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Unread Alerts</h3>
                <div class="number"><?php echo $alerts_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Spent</h3>
                <div class="number">₹<?php echo number_format($total_spent, 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Member Since</h3>
                <div class="number" style="font-size: 16px;"><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="content-grid">
            <!-- Alerts Section -->
            <div class="card">
                <h2>🔔 Recent Alerts</h2>
                
                <?php if (empty($recent_alerts)): ?>
                    <div class="empty-state">
                        <p>No alerts yet. Stay tuned for service updates and promotions!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_alerts as $alert): ?>
                        <div class="alert-item <?php echo $alert['is_read'] ? '' : 'unread'; ?>">
                            <span class="alert-type <?php echo htmlspecialchars($alert['alert_type']); ?>">
                                <?php echo htmlspecialchars($alert['alert_type']); ?>
                            </span>
                            <div class="alert-message"><?php echo htmlspecialchars(substr($alert['message'], 0, 80)) . (strlen($alert['message']) > 80 ? '...' : ''); ?></div>
                            <div class="alert-time">
                                <?php echo $alert['is_read'] ? '✓ Read' : '● New'; ?> • 
                                <?php echo date('M d, Y', strtotime($alert['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="customer-alerts.php" class="view-all-link">View All Alerts →</a>
                <?php endif; ?>
            </div>
            
            <!-- Recent Orders Section -->
            <div class="card">
                <h2>📦 Recent Orders</h2>
                
                <?php if (empty($recent_orders)): ?>
                    <div class="empty-state">
                        <p>No orders yet. Start shopping!</p>
                        <a href="/waaris/products.php" class="btn-primary">Browse Products</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_orders as $order): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <h4>Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h4>
                                <p>
                                    <?php echo $order['items']; ?> item(s) • 
                                    ₹<?php echo number_format($order['total_amount'], 2); ?>
                                </p>
                                <p><?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                            </div>
                            <span class="order-status <?php echo htmlspecialchars($order['status']); ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <a href="customer-orders.php" class="view-all-link">View All Orders →</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Profile & Quick Actions -->
        <div class="card">
            <h2>👤 My Profile & Quick Actions</h2>
            
            <div class="profile-section">
                <h3>Contact Information</h3>
                <div class="profile-info">
                    <div>
                        <strong>Email</strong>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div>
                        <strong>Phone</strong>
                        <p><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></p>
                    </div>
                    <div>
                        <strong>Username</strong>
                        <p><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    <div>
                        <strong>Status</strong>
                        <p><?php echo $user['status'] === 'active' ? '✓ Active' : '● Blocked'; ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($user['address']) || !empty($user['city'])): ?>
            <div class="profile-section">
                <h3>Address</h3>
                <div class="profile-info">
                    <?php if (!empty($user['address'])): ?>
                    <div style="grid-column: 1 / -1;">
                        <strong>Address</strong>
                        <p><?php echo htmlspecialchars($user['address']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['city'])): ?>
                    <div>
                        <strong>City</strong>
                        <p><?php echo htmlspecialchars($user['city']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['state'])): ?>
                    <div>
                        <strong>State</strong>
                        <p><?php echo htmlspecialchars($user['state']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['postal_code'])): ?>
                    <div>
                        <strong>Postal Code</strong>
                        <p><?php echo htmlspecialchars($user['postal_code']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="customer-profile.php" class="btn-primary">Edit Profile</a>
                <a href="customer-booking.php" class="btn-primary">Book Service</a>
                <a href="/waaris/products.php" class="btn-secondary">Continue Shopping</a>
            </div>
        </div>
    </div>
</body>
</html>
