<?php
/**
 * Customer Alerts Page
 * View and manage all customer alerts
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth.php';

Auth::requireLogin();

$user_id = $_SESSION['user_id'];

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $alert_id = intval($_POST['alert_id'] ?? 0);
    if ($alert_id > 0) {
        $stmt = $db->prepare('UPDATE customer_alerts SET is_read = true WHERE id = ? AND user_id = ?');
        $stmt->execute([$alert_id, $user_id]);
    }
}

// Get all alerts
$alerts_stmt = $db->prepare('SELECT * FROM customer_alerts WHERE user_id = ? ORDER BY created_at DESC');
$alerts_stmt->execute([$user_id]);
$all_alerts = $alerts_stmt->fetchAll();

// Count by type
$type_counts = [
    'payment' => 0,
    'service' => 0,
    'promotion' => 0,
    'update' => 0
];

foreach ($all_alerts as $alert) {
    if (isset($type_counts[$alert['alert_type']])) {
        $type_counts[$alert['alert_type']]++;
    }
}

// Filter by type
$filter_type = $_GET['type'] ?? 'all';
$filtered_alerts = $all_alerts;

if ($filter_type !== 'all') {
    $filtered_alerts = array_filter($all_alerts, function($a) use ($filter_type) {
        return $a['alert_type'] === $filter_type;
    });
}

$unread_count = count(array_filter($all_alerts, fn($a) => !$a['is_read']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Alerts - Ahanger MotoCorp</title>
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
        
        .filter-badge {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: #666;
        }
        
        .alerts-container {
            display: grid;
            gap: 15px;
        }
        
        .alert-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            border-left: 5px solid #667eea;
        }
        
        .alert-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        
        .alert-card.unread {
            background: #f9f9ff;
            border-left-width: 5px;
        }
        
        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        
        .alert-title {
            display: flex;
            gap: 12px;
            align-items: start;
            flex: 1;
        }
        
        .alert-type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .alert-type-badge.payment {
            background: #fee;
            color: #c00;
        }
        
        .alert-type-badge.service {
            background: #efe;
            color: #060;
        }
        
        .alert-type-badge.promotion {
            background: #fef0e0;
            color: #c05000;
        }
        
        .alert-type-badge.update {
            background: #e0f2ff;
            color: #0066cc;
        }
        
        .alert-status {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .unread-indicator {
            width: 12px;
            height: 12px;
            background: #667eea;
            border-radius: 50%;
        }
        
        .read-indicator {
            width: 12px;
            height: 12px;
            background: #ddd;
            border-radius: 50%;
        }
        
        .alert-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 12px;
            font-size: 15px;
        }
        
        .alert-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
        }
        
        .alert-time {
            font-size: 12px;
            color: #999;
        }
        
        .mark-read-btn {
            background: #f0f0f0;
            color: #333;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .mark-read-btn:hover {
            background: #e0e0e0;
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
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat {
            background: white;
            padding: 15px;
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>🔔 My Alerts</h1>
            <p>Stay informed with service updates, promotions, and announcements</p>
            <a href="customer-dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <h4>Total Alerts</h4>
                <div class="number"><?php echo count($all_alerts); ?></div>
            </div>
            <div class="stat">
                <h4>Unread</h4>
                <div class="number"><?php echo $unread_count; ?></div>
            </div>
            <div class="stat">
                <h4>This Month</h4>
                <div class="number">
                    <?php 
                        echo count(array_filter($all_alerts, fn($a) => 
                            date('Y-m', strtotime($a['created_at'])) === date('Y-m')
                        ));
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <a href="customer-alerts.php?type=all" class="filter-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                All Alerts <span class="filter-badge"><?php echo count($all_alerts); ?></span>
            </a>
            <a href="customer-alerts.php?type=payment" class="filter-btn <?php echo $filter_type === 'payment' ? 'active' : ''; ?>">
                💳 Payment <span class="filter-badge"><?php echo $type_counts['payment']; ?></span>
            </a>
            <a href="customer-alerts.php?type=service" class="filter-btn <?php echo $filter_type === 'service' ? 'active' : ''; ?>">
                🔧 Service <span class="filter-badge"><?php echo $type_counts['service']; ?></span>
            </a>
            <a href="customer-alerts.php?type=promotion" class="filter-btn <?php echo $filter_type === 'promotion' ? 'active' : ''; ?>">
                🎉 Promotion <span class="filter-badge"><?php echo $type_counts['promotion']; ?></span>
            </a>
            <a href="customer-alerts.php?type=update" class="filter-btn <?php echo $filter_type === 'update' ? 'active' : ''; ?>">
                📰 Update <span class="filter-badge"><?php echo $type_counts['update']; ?></span>
            </a>
        </div>
        
        <!-- Alerts List -->
        <div class="alerts-container">
            <?php if (empty($filtered_alerts)): ?>
                <div class="empty-state">
                    <h3>No Alerts</h3>
                    <p>You don't have any <?php echo $filter_type !== 'all' ? htmlspecialchars($filter_type) . ' ' : ''; ?>alerts yet.</p>
                    <a href="customer-dashboard.php" class="btn-primary">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <?php foreach ($filtered_alerts as $alert): ?>
                    <div class="alert-card <?php echo $alert['is_read'] ? '' : 'unread'; ?>">
                        <div class="alert-header">
                            <div class="alert-title">
                                <span class="alert-type-badge <?php echo htmlspecialchars($alert['alert_type']); ?>">
                                    <?php echo htmlspecialchars($alert['alert_type']); ?>
                                </span>
                                <div>
                                    <h3 style="color: #333; margin-bottom: 4px; font-size: 16px;">
                                        <?php 
                                            $titles = [
                                                'payment' => '💳 Payment Alert',
                                                'service' => '🔧 Service Update',
                                                'promotion' => '🎉 Special Offer',
                                                'update' => '📰 Announcement'
                                            ];
                                            echo $titles[$alert['alert_type']] ?? 'Alert';
                                        ?>
                                    </h3>
                                </div>
                            </div>
                            <div class="alert-status">
                                <div class="<?php echo $alert['is_read'] ? 'read-indicator' : 'unread-indicator'; ?>"></div>
                            </div>
                        </div>
                        
                        <div class="alert-message">
                            <?php echo nl2br(htmlspecialchars($alert['message'])); ?>
                        </div>
                        
                        <div class="alert-footer">
                            <span class="alert-time">
                                <?php echo date('F j, Y • H:i', strtotime($alert['created_at'])); ?>
                            </span>
                            <?php if (!$alert['is_read']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                    <button type="submit" class="mark-read-btn">Mark as Read</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 12px; color: #999;">✓ Read</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
