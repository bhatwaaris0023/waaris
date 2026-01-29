<?php
/**
 * Admin Alerts Management - Send alerts to customers
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

AdminAuth::requireLogin();

$error = '';
$success = '';
$alert_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Handle alert sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $message = Security::sanitizeInput($_POST['message'] ?? '');
        $alert_type = Security::sanitizeInput($_POST['alert_type'] ?? 'update');
        $recipient_type = Security::sanitizeInput($_POST['recipient_type'] ?? 'all');
        
        if (empty($message)) {
            $error = 'Alert message cannot be empty.';
        } else {
            try {
                if ($recipient_type === 'all') {
                    $users = $db->query('SELECT id FROM users WHERE status = "active"')->fetchAll();
                } elseif ($recipient_type === 'selected') {
                    $user_ids = $_POST['user_ids'] ?? [];
                    if (empty($user_ids)) {
                        $error = 'Please select at least one user.';
                    } else {
                        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
                        $stmt = $db->prepare('SELECT id FROM users WHERE id IN (' . $placeholders . ')');
                        $stmt->execute($user_ids);
                        $users = $stmt->fetchAll();
                    }
                }
                
                if (isset($users) && !empty($users)) {
                    $insert_stmt = $db->prepare('INSERT INTO customer_alerts (user_id, alert_type, message, is_read) VALUES (?, ?, ?, false)');
                    $count = 0;
                    foreach ($users as $user) {
                        $insert_stmt->execute([$user['id'], $alert_type, $message]);
                        $count++;
                    }
                    
                    Security::logSecurityEvent('ADMIN_ALERT_SENT', [
                        'admin_id' => $_SESSION['admin_id'],
                        'count' => $count,
                        'type' => $alert_type
                    ]);
                    
                    $success = "Alert sent to $count customer(s) successfully!";
                }
            } catch (PDOException $e) {
                error_log('Alert sending error: ' . $e->getMessage());
                $error = 'Failed to send alert. Please try again.';
            }
        }
    }
}

// Get statistics
$total_alerts = $db->query('SELECT COUNT(*) as count FROM customer_alerts')->fetch()['count'];
$unread_alerts = $db->query('SELECT COUNT(*) as count FROM customer_alerts WHERE is_read = false')->fetch()['count'];
$sent_today = $db->query('SELECT COUNT(*) as count FROM customer_alerts WHERE DATE(created_at) = CURDATE()')->fetch()['count'];

// Get recent alerts
$alerts_query = $db->query('SELECT ca.*, u.username, u.phone FROM customer_alerts ca 
    JOIN users u ON ca.user_id = u.id 
    ORDER BY ca.created_at DESC LIMIT 20');
$recent_alerts = $alerts_query->fetchAll();

// Get all active users for selection
$users_query = $db->query('SELECT id, username, email, phone FROM users WHERE status = "active" ORDER BY username');
$all_users = $users_query->fetchAll();

// Get CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Alerts - Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="assets/admin-style.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: rgba(99, 102, 241, 0.1);
            --bg-primary: #fff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-md: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 12px rgba(0, 0, 0, 0.15);
            --transition: all 0.2s ease;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .alert-stat {
            padding: 24px 20px;
            border-radius: 12px;
            text-align: center;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .alert-stat:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-1px);
        }

        .alert-stat h3 {
            margin: 0 0 12px 0;
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .alert-stat .number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
            margin: 0;
        }

        .alerts-container {
            display: grid;
            gap: 24px;
        }

        .alert-form, .alert-history {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .alert-form {
            padding: 32px;
        }

        .alert-history {
            padding: 32px;
        }

        .alert-form h2, .alert-history h2 {
            margin: 0 0 24px 0;
            color: #1e293b;
            font-size: 22px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            transition: var(--transition);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .recipient-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .recipient-option {
            padding: 20px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            background: transparent;
        }

        .recipient-option input[type="radio"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
        }

        .recipient-option:hover {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }

        .recipient-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }

        .search-box {
            margin-bottom: 16px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .search-results {
            display: none;
            position: absolute;
            top: 50px;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .search-results.active {
            display: block;
        }

        .search-result-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .search-result-item:hover {
            background: var(--bg-secondary);
        }

        .search-result-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }

        .search-result-info {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }

        .user-selector {
            max-height: 280px;
            overflow-y: auto;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
            display: none;
            background: #fafbfc;
        }

        .user-selector.show {
            display: block;
        }

        .user-checkbox {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-checkbox:hover {
            background: var(--primary-light);
        }

        .user-checkbox input[type="checkbox"] {
            margin-top: 2px;
            margin-right: 12px;
            width: 16px;
            height: 16px;
        }

        .user-checkbox label {
            margin: 0;
            flex: 1;
            cursor: pointer;
        }

        .user-info {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        .btn-send {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 8px;
        }

        .btn-send:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .alert-item {
            padding: 20px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 12px;
        }

        .alert-user {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .alert-type {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .alert-type.payment { background: #fee2e2; color: #dc2626; }
        .alert-type.service { background: #dcfce7; color: #16a34a; }
        .alert-type.promotion { background: #fed7aa; color: #ea580c; }
        .alert-type.update { background: #dbeafe; color: #2563eb; }

        .alert-message {
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .alert-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #94a3b8;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            border: 1px solid;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        @media (max-width: 768px) {
            .admin-content {
                padding: 16px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 16px;
            }
            
            .recipient-options {
                grid-template-columns: 1fr;
            }
            
            .alert-header {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
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
                <h1>Customer Alerts Management</h1>
                <p>Send service, payment, and promotional alerts to your customers</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="alert-stat">
                    <h3>Total Alerts</h3>
                    <div class="number"><?php echo number_format($total_alerts); ?></div>
                </div>
                <div class="alert-stat">
                    <h3>Unread</h3>
                    <div class="number"><?php echo number_format($unread_alerts); ?></div>
                </div>
                <div class="alert-stat">
                    <h3>Sent Today</h3>
                    <div class="number"><?php echo number_format($sent_today); ?></div>
                </div>
            </div>
            
            <div class="alerts-container">
                <div class="alert-form">
                    <h2>Send New Alert</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <div class="form-group">
                            <label>Alert Type</label>
                            <select name="alert_type" required>
                                <option value="update">Service Update</option>
                                <option value="service">Service Alert</option>
                                <option value="payment">Payment Alert</option>
                                <option value="promotion">Promotion</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Recipients</label>
                            <div class="recipient-options">
                                <div class="recipient-option selected" data-option="all">
                                    <label style="margin: 0; width: 100%; display: flex; align-items: center; cursor: pointer;">
                                        <input type="radio" name="recipient_type" value="all" checked>
                                        <span style="font-weight: 500;">All Active Customers</span>
                                    </label>
                                </div>
                                <div class="recipient-option" data-option="selected">
                                    <label style="margin: 0; width: 100%; display: flex; align-items: center; cursor: pointer;">
                                        <input type="radio" name="recipient_type" value="selected">
                                        <span style="font-weight: 500;">Select Customers</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="user-selector" id="userSelector">
                            <div style="margin-bottom: 16px; font-size: 13px; color: #64748b; font-weight: 600;">Select customers to receive this alert:</div>
                            
                            <div class="search-box">
                                <input type="text" id="customerSearch" placeholder="🔍 Search customers by name, email or phone...">
                                <div class="search-results" id="searchResults"></div>
                            </div>
                            
                            <?php foreach ($all_users as $user): ?>
                                <div class="user-checkbox">
                                    <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" id="user_<?php echo $user['id']; ?>">
                                    <label for="user_<?php echo $user['id']; ?>">
                                        <div style="font-weight: 600; color: #1e293b; margin-bottom: 4px;"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <div class="user-info"><?php echo htmlspecialchars($user['phone']); ?> • <?php echo htmlspecialchars($user['email']); ?></div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Alert Message</label>
                            <textarea id="message" name="message" required placeholder="Enter your alert message here... (max 500 characters)" maxlength="500"></textarea>
                        </div>
                        
                        <button type="submit" class="btn-send">🚀 Send Alert Now</button>
                    </form>
                </div>
                
                <div class="alert-history">
                    <h2>Recent Alerts</h2>
                    <?php if (empty($recent_alerts)): ?>
                        <div style="text-align: center; padding: 48px 24px; color: #94a3b8;">
                            <div style="font-size: 16px; margin-bottom: 8px;">📭 No alerts yet</div>
                            <p style="font-size: 14px; margin: 0;">Send your first customer alert using the form above!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_alerts as $alert): ?>
                            <div class="alert-item">
                                <div class="alert-header">
                                    <div>
                                        <div class="alert-user"><?php echo htmlspecialchars($alert['username']); ?></div>
                                        <div style="font-size: 13px; color: #94a3b8;"><?php echo htmlspecialchars($alert['phone']); ?></div>
                                    </div>
                                    <span class="alert-type <?php echo htmlspecialchars($alert['alert_type']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($alert['alert_type'])); ?>
                                    </span>
                                </div>
                                <div class="alert-message">
                                    <?php echo htmlspecialchars(substr($alert['message'], 0, 120)) . (strlen($alert['message']) > 120 ? '...' : ''); ?>
                                </div>
                                <div class="alert-meta">
                                    <span><?php echo $alert['is_read'] ? '✓ Read' : '○ Unread'; ?></span>
                                    <span><?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle radio button changes
        const radios = document.querySelectorAll('input[name="recipient_type"]');
        const options = document.querySelectorAll('.recipient-option');
        const userSelector = document.getElementById('userSelector');

        // Update UI when radio changes
        function updateRecipientUI(value) {
            options.forEach(o => o.classList.remove('selected'));
            options.forEach(o => {
                if (o.dataset.option === value) {
                    o.classList.add('selected');
                }
            });
            
            if (value === 'selected') {
                userSelector.classList.add('show');
            } else {
                userSelector.classList.remove('show');
            }
        }

        // Handle radio input change
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                updateRecipientUI(this.value);
            });
        });

        // Handle option div clicks - delegate to radio
        options.forEach(option => {
            option.addEventListener('click', function(e) {
                // Don't prevent default if clicking the radio directly
                if (e.target.type !== 'radio') {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
        });

        // Handle textarea auto-resize
        const textarea = document.getElementById('message');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 200) + 'px';
            });
        }

        // Customer search functionality
        const searchInput = document.getElementById('customerSearch');
        const searchResults = document.getElementById('searchResults');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const allUsers = [
            <?php foreach ($all_users as $user): ?>
            { id: <?php echo $user['id']; ?>, username: '<?php echo htmlspecialchars(addslashes($user['username'])); ?>', email: '<?php echo htmlspecialchars(addslashes($user['email'])); ?>', phone: '<?php echo htmlspecialchars(addslashes($user['phone'])); ?>' },
            <?php endforeach; ?>
        ];

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                
                if (query.length === 0) {
                    searchResults.classList.remove('active');
                    userCheckboxes.forEach(box => box.style.display = 'block');
                    return;
                }
                
                const filtered = allUsers.filter(user => 
                    user.username.toLowerCase().includes(query) ||
                    user.email.toLowerCase().includes(query) ||
                    user.phone.toLowerCase().includes(query)
                );
                
                // Show/hide checkboxes based on search
                userCheckboxes.forEach(box => {
                    const userId = box.querySelector('input[type="checkbox"]').value;
                    const found = filtered.some(u => u.id == userId);
                    box.style.display = found ? 'block' : 'none';
                });
                
                // Show search results dropdown
                if (filtered.length > 0) {
                    searchResults.innerHTML = filtered.map(user => `
                        <div class="search-result-item" onclick="selectCustomer(${user.id}, '${user.username}')">
                            <div class="search-result-name">${user.username}</div>
                            <div class="search-result-info">${user.phone} • ${user.email}</div>
                        </div>
                    `).join('');
                    searchResults.classList.add('active');
                } else {
                    searchResults.classList.remove('active');
                }
            });
        }

        // Select customer from search results
        function selectCustomer(userId, userName) {
            const checkbox = document.querySelector(`input[type="checkbox"][value="${userId}"]`);
            if (checkbox) {
                checkbox.checked = true;
                document.getElementById('customerSearch').value = '';
                searchResults.classList.remove('active');
                userCheckboxes.forEach(box => box.style.display = 'block');
                checkbox.closest('.user-checkbox').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-box')) {
                searchResults.classList.remove('active');
            }
        });
    </script>
</body>
</html>
