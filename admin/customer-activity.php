<?php
/**
 * Admin Customer Activity Dashboard
 * View all customer activities including service bookings, alerts, orders
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireLogin();

$page_title = 'Customer Activity';
$filter_type = isset($_GET['type']) ? Security::sanitizeInput($_GET['type']) : 'all';
$filter_days = isset($_GET['days']) ? intval($_GET['days']) : 7;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Ahanger MotoCorp Admin</title>
    <link rel="stylesheet" href="assets/admin-style.css">
    <style>        .admin-layout {
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

        .activity-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.15);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <p>Monitor all customer activities and service requests</p>
            </div>

                <!-- Activity Filters -->
                <div class="activity-filters">
                    <form method="GET" action="" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div class="filter-group">
                            <label for="type">Activity Type:</label>
                            <select id="type" name="type" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Activities</option>
                                <option value="booking" <?php echo $filter_type === 'booking' ? 'selected' : ''; ?>>Service Bookings</option>
                                <option value="order" <?php echo $filter_type === 'order' ? 'selected' : ''; ?>>Orders</option>
                                <option value="alert" <?php echo $filter_type === 'alert' ? 'selected' : ''; ?>>Alerts</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="days">Time Period:</label>
                            <select id="days" name="days" onchange="this.form.submit()">
                                <option value="1" <?php echo $filter_days === 1 ? 'selected' : ''; ?>>Last 24 Hours</option>
                                <option value="7" <?php echo $filter_days === 7 ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $filter_days === 30 ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90" <?php echo $filter_days === 90 ? 'selected' : ''; ?>>Last 90 Days</option>
                            </select>
                        </div>
                    </form>
                </div>

                <?php
                // Get activity statistics
                $date_from = date('Y-m-d H:i:s', strtotime("-{$filter_days} days"));

                // Count service bookings
                $bookings_count = $db->query("
                    SELECT COUNT(*) as count FROM customer_alerts 
                    WHERE alert_type = 'service' AND created_at >= '$date_from'
                ")->fetch()['count'];

                // Count orders
                $orders_count = $db->query("
                    SELECT COUNT(*) as count FROM orders 
                    WHERE created_at >= '$date_from'
                ")->fetch()['count'];

                // Count alerts
                $alerts_count = $db->query("
                    SELECT COUNT(*) as count FROM customer_alerts 
                    WHERE alert_type IN ('payment', 'promotion', 'update') AND created_at >= '$date_from'
                ")->fetch()['count'];

                // Count active job cards
                $active_jobs = $db->query("
                    SELECT COUNT(*) as count FROM service_job_cards 
                    WHERE status != 'completed' AND created_at >= '$date_from'
                ")->fetch()['count'];
                ?>

                <!-- Activity Statistics -->
                <div class="activity-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $bookings_count; ?></div>
                        <div class="stat-label">Service Bookings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $orders_count; ?></div>
                        <div class="stat-label">Orders Placed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $alerts_count; ?></div>
                        <div class="stat-label">Notifications</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $active_jobs; ?></div>
                        <div class="stat-label">Active Job Cards</div>
                    </div>
                </div>

                <!-- Activity List -->
                <div class="activity-list">
                    <?php
                    // Build query based on filter
                    $activities = [];

                    // Get service bookings
                    if ($filter_type === 'all' || $filter_type === 'booking') {
                        $booking_query = $db->query("
                            SELECT 'booking' as type, ca.id, ca.user_id, ca.message, ca.created_at, u.username, u.first_name, u.email, u.phone
                            FROM customer_alerts ca
                            JOIN users u ON ca.user_id = u.id
                            WHERE ca.alert_type = 'service' AND ca.created_at >= '$date_from'
                            ORDER BY ca.created_at DESC
                        ");
                        $activities = array_merge($activities, $booking_query->fetchAll());
                    }

                    // Get orders
                    if ($filter_type === 'all' || $filter_type === 'order') {
                        $order_query = $db->query("
                            SELECT 'order' as type, o.id, o.user_id, o.total_amount, o.status, o.created_at, u.username, u.first_name, u.email, u.phone, COUNT(oi.id) as items_count
                            FROM orders o
                            JOIN users u ON o.user_id = u.id
                            LEFT JOIN order_items oi ON o.id = oi.order_id
                            WHERE o.created_at >= '$date_from'
                            GROUP BY o.id
                            ORDER BY o.created_at DESC
                        ");
                        $activities = array_merge($activities, $order_query->fetchAll());
                    }

                    // Get other alerts
                    if ($filter_type === 'all' || $filter_type === 'alert') {
                        $alert_query = $db->query("
                            SELECT 'alert' as type, ca.id, ca.user_id, ca.alert_type, ca.message, ca.is_read, ca.created_at, u.username, u.first_name, u.email, u.phone
                            FROM customer_alerts ca
                            JOIN users u ON ca.user_id = u.id
                            WHERE ca.alert_type IN ('payment', 'promotion', 'update') AND ca.created_at >= '$date_from'
                            ORDER BY ca.created_at DESC
                        ");
                        $activities = array_merge($activities, $alert_query->fetchAll());
                    }

                    // Sort all activities by timestamp
                    usort($activities, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });

                    if (empty($activities)):
                    ?>
                        <div class="no-activity">
                            <div class="no-activity-icon">📭</div>
                            <h3>No Activities Found</h3>
                            <p>No customer activities in the selected time period</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item <?php echo $activity['type']; ?>">
                                <div class="activity-header">
                                    <div>
                                        <span class="activity-type-badge badge-<?php echo $activity['type']; ?>">
                                            <?php 
                                            if ($activity['type'] === 'booking') echo '📅 Service Booking';
                                            elseif ($activity['type'] === 'order') echo '🛒 Order';
                                            elseif ($activity['type'] === 'alert') echo '🔔 ' . ucfirst($activity['alert_type']);
                                            ?>
                                        </span>
                                    </div>
                                    <span class="activity-time"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></span>
                                </div>

                                <div class="customer-info">
                                    <span class="customer-name">👤 <?php echo htmlspecialchars($activity['first_name'] ?? $activity['username']); ?></span>
                                    <span class="customer-contact">📧 <?php echo htmlspecialchars($activity['email']); ?> | 📱 <?php echo htmlspecialchars($activity['phone']); ?></span>
                                </div>

                                <?php if ($activity['type'] === 'booking'): ?>
                                    <div class="activity-content">
                                        <strong>Service Request:</strong><br>
                                        <?php echo nl2br(htmlspecialchars(substr($activity['message'], 0, 200))); ?>
                                    </div>
                                    <div class="activity-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Activity ID</span>
                                            <span class="detail-value">#<?php echo str_pad($activity['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        </div>
                                    </div>

                                <?php elseif ($activity['type'] === 'order'): ?>
                                    <div class="activity-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Order ID</span>
                                            <span class="detail-value">#<?php echo str_pad($activity['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Amount</span>
                                            <span class="detail-value">₹<?php echo number_format($activity['total_amount'], 2); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Items</span>
                                            <span class="detail-value"><?php echo $activity['items_count']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Status</span>
                                            <span class="status-badge status-<?php echo $activity['status']; ?>"><?php echo ucfirst($activity['status']); ?></span>
                                        </div>
                                    </div>

                                <?php elseif ($activity['type'] === 'alert'): ?>
                                    <div class="activity-content">
                                        <?php echo nl2br(htmlspecialchars($activity['message'])); ?>
                                    </div>
                                    <div class="activity-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Type</span>
                                            <span class="detail-value"><?php echo ucfirst($activity['alert_type']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Status</span>
                                            <span class="status-badge" style="background: <?php echo $activity['is_read'] ? '#d1ecf1' : '#fff3cd'; ?>; color: <?php echo $activity['is_read'] ? '#0c5460' : '#856404'; ?>;">
                                                <?php echo $activity['is_read'] ? 'Read' : 'Unread'; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
        </div>
    </div>
</body>
</html>
