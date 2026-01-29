<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireLogin();

// Get statistics
$users_count = $db->query('SELECT COUNT(*) as count FROM users')->fetch()['count'];
$products_count = $db->query('SELECT COUNT(*) as count FROM products')->fetch()['count'];
$orders_count = $db->query('SELECT COUNT(*) as count FROM orders')->fetch()['count'];
$total_revenue = $db->query('SELECT SUM(total_amount) as total FROM orders WHERE status != "cancelled"')->fetch()['total'] ?? 0;

// Get recent orders
$recent_orders = $db->query('SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5')->fetchAll();

// Get recent products
$recent_products = $db->query('SELECT * FROM products ORDER BY created_at DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ahanger MotoCorp</title>
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
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 28px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 14px 14px 0 0;
        }

        .stat-card:hover {
            box-shadow: 0 12px 24px rgba(99, 102, 241, 0.12);
            transform: translateY(-4px);
            border-color: var(--primary-color);
        }

        .stat-icon {
            font-size: 42px;
            margin-bottom: 12px;
            display: inline-block;
        }

        .stat-label {
            color: #64748b;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), #5b5fc7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .content-section {
            background: white;
            padding: 28px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 28px;
        }

        .section-title {
            margin: 0 0 24px 0;
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead {
            background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 100%);
            border-bottom: 2px solid #e2e8f0;
        }

        .table th {
            padding: 16px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 14px;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-completed {
            background: #dbeafe;
            color: #0c4a6e;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .activity-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .filter-group label {
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
        }

        .filter-group select {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: white;
            color: var(--text-dark);
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .filter-group select:hover {
            border-color: var(--primary-color);
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .activity-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .activity-item:hover {
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.15);
            transform: translateX(5px);
        }

        .activity-item.service {
            border-left-color: #28a745;
        }

        .activity-item.order {
            border-left-color: #17a2b8;
        }

        .activity-item.alert {
            border-left-color: #ffc107;
        }

        .activity-item.booking {
            border-left-color: #6f42c1;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .activity-type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-service {
            background: #d4edda;
            color: #155724;
        }

        .badge-order {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-alert {
            background: #fff3cd;
            color: #856404;
        }

        .badge-booking {
            background: #e2d0f5;
            color: #4a235a;
        }

        .activity-time {
            color: var(--text-light);
            font-size: 12px;
        }

        .customer-info {
            background: var(--light-bg);
            padding: 12px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 14px;
        }

        .customer-name {
            font-weight: 600;
            color: var(--text-dark);
            display: block;
        }

        .customer-contact {
            color: var(--text-light);
            font-size: 12px;
            margin-top: 4px;
        }

        .activity-content {
            color: var(--text-dark);
            line-height: 1.6;
            margin: 10px 0;
        }

        .activity-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 12px 0;
            padding: 12px;
            background: var(--light-bg);
            border-radius: 8px;
        }

        .detail-item {
            font-size: 13px;
        }

        .detail-label {
            color: var(--text-light);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }

        .detail-value {
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #ffeaa7;
            color: #d63031;
        }

        .status-completed {
            background: #55efc4;
            color: #00b894;
        }

        .status-in-progress {
            background: #a29bfe;
            color: #2d3436;
        }

        .status-cancelled {
            background: #ff7675;
            color: white;
        }

        .no-activity {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .no-activity-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="admin-layout">
        <?php require_once 'includes/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Welcome to the admin dashboard</p>
            </div>
            
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo $users_count; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value"><?php echo $products_count; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?php echo $orders_count; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">₹<?php echo number_format($total_revenue, 0); ?></div>
                </div>
            </div>
            
            <div class="content-section">
                <h2 class="section-title">Recent Orders</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['username']); ?></td>
                                <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <a href="orders.php?id=<?php echo $order['id']; ?>" class="btn btn-small btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="content-section">
                <h2 class="section-title">Recent Products</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>
                                    <?php
                                    $cat = $db->prepare('SELECT name FROM categories WHERE id = ?');
                                    $cat->execute([$product['category_id']]);
                                    $category = $cat->fetch();
                                    echo htmlspecialchars($category['name'] ?? 'N/A');
                                    ?>
                                </td>
                                <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $product['stock_quantity']; ?></td>
                                <td><span class="badge badge-<?php echo strtolower($product['status']); ?>"><?php echo ucfirst($product['status']); ?></span></td>
                                <td>
                                    <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-small btn-primary">Edit</a>
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
