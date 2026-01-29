<?php
/**
 * Customer Orders Page
 * View and manage customer orders
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth.php';

Auth::requireLogin();

$user_id = $_SESSION['user_id'];

// Get all orders
$orders_stmt = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$orders_stmt->execute([$user_id]);
$orders = $orders_stmt->fetchAll();

// Get filter
$filter_status = $_GET['status'] ?? 'all';
$filtered_orders = $orders;

if ($filter_status !== 'all') {
    $filtered_orders = array_filter($orders, fn($o) => $o['status'] === $filter_status);
}

// Calculate stats
$status_counts = [
    'pending' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

foreach ($orders as $order) {
    if (isset($status_counts[$order['status']])) {
        $status_counts[$order['status']]++;
    }
}

$total_orders = count($orders);
$total_spent = array_sum(array_map(fn($o) => $o['total_amount'], $orders));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Ahanger MotoCorp</title>
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
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .page-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .back-btn {
            color: white;
            text-decoration: none;
            font-size: 14px;
            margin-top: 15px;
            display: inline-block;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        
        .stat h4 {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .stat .number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .filter-btn {
            padding: 10px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .filter-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .orders-container {
            display: grid;
            gap: 15px;
        }
        
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }
        
        .order-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .order-id {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .order-id span {
            font-size: 12px;
            color: #999;
            font-weight: normal;
            display: block;
            margin-top: 4px;
        }
        
        .order-status {
            display: inline-block;
            padding: 6px 14px;
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
        
        .order-status.cancelled {
            background: #f8d7da;
            color: #842029;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item strong {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .detail-item p {
            color: #333;
            font-size: 14px;
        }
        
        .order-items {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .order-items strong {
            color: #333;
            display: block;
            margin-bottom: 8px;
        }
        
        .item-list {
            color: #666;
            line-height: 1.6;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .order-amount {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>📦 My Orders</h1>
            <p>View and track your orders</p>
            <a href="customer-dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <h4>Total Orders</h4>
                <div class="number"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat">
                <h4>Total Spent</h4>
                <div class="number">₹<?php echo number_format($total_spent, 0); ?></div>
            </div>
            <div class="stat">
                <h4>In Transit</h4>
                <div class="number"><?php echo $status_counts['shipped']; ?></div>
            </div>
            <div class="stat">
                <h4>Delivered</h4>
                <div class="number"><?php echo $status_counts['delivered']; ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <a href="customer-orders.php?status=all" class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                All Orders (<?php echo count($orders); ?>)
            </a>
            <a href="customer-orders.php?status=pending" class="filter-btn <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
                ⏳ Pending (<?php echo $status_counts['pending']; ?>)
            </a>
            <a href="customer-orders.php?status=shipped" class="filter-btn <?php echo $filter_status === 'shipped' ? 'active' : ''; ?>">
                🚚 Shipped (<?php echo $status_counts['shipped']; ?>)
            </a>
            <a href="customer-orders.php?status=delivered" class="filter-btn <?php echo $filter_status === 'delivered' ? 'active' : ''; ?>">
                ✓ Delivered (<?php echo $status_counts['delivered']; ?>)
            </a>
            <a href="customer-orders.php?status=cancelled" class="filter-btn <?php echo $filter_status === 'cancelled' ? 'active' : ''; ?>">
                ✗ Cancelled (<?php echo $status_counts['cancelled']; ?>)
            </a>
        </div>
        
        <!-- Orders List -->
        <div class="orders-container">
            <?php if (empty($filtered_orders)): ?>
                <div class="empty-state">
                    <h3>No Orders</h3>
                    <p>You don't have any <?php echo $filter_status !== 'all' ? htmlspecialchars($filter_status) . ' ' : ''; ?>orders yet.</p>
                    <a href="/waaris/products.php" class="btn-primary">Start Shopping</a>
                </div>
            <?php else: ?>
                <?php foreach ($filtered_orders as $order): 
                    // Get order items
                    $items_stmt = $db->prepare('SELECT oi.*, p.name FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        WHERE oi.order_id = ?');
                    $items_stmt->execute([$order['id']]);
                    $items = $items_stmt->fetchAll();
                ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">
                                    Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                    <span><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                            </div>
                            <span class="order-status <?php echo htmlspecialchars($order['status']); ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-item">
                                <strong>Items</strong>
                                <p><?php echo count($items); ?> product(s)</p>
                            </div>
                            <div class="detail-item">
                                <strong>Payment Method</strong>
                                <p><?php echo htmlspecialchars($order['payment_method'] ?: 'Not specified'); ?></p>
                            </div>
                            <div class="detail-item">
                                <strong>Shipping Address</strong>
                                <p><?php echo htmlspecialchars(substr($order['shipping_address'] ?? 'Not provided', 0, 30)); ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($items)): ?>
                            <div class="order-items">
                                <strong>Items Ordered:</strong>
                                <div class="item-list">
                                    <?php foreach ($items as $item): ?>
                                        <div>• <?php echo htmlspecialchars($item['name']); ?> (Qty: <?php echo $item['quantity']; ?>)</div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="order-footer">
                            <div class="order-amount">
                                ₹<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                            <a href="#" class="btn-primary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
