<?php
/**
 * Admin Job Cards Management
 */

$page_title = 'Service Job Cards';
require_once '../config/db.php';
require_once '../config/security.php';
require_once 'auth.php';

// Check admin access
if (!AdminAuth::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get CSRF token
$csrf_token = Security::generateCSRFToken();

// Get all customers
$customers_query = $db->prepare('SELECT id, username, email, phone, vehicle_number, first_name FROM users WHERE status = "active" ORDER BY first_name');
$customers_query->execute();
$customers = $customers_query->fetchAll();

// Get all products
$products_query = $db->prepare('SELECT id, name, price, stock_quantity FROM products WHERE status = "active" ORDER BY name');
$products_query->execute();
$products = $products_query->fetchAll();

// Handle Create/Update Job Card
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = Security::sanitizeInput($_POST['action']);
        
        if ($action === 'create_job') {
            $customer_mode = Security::sanitizeInput($_POST['customer_mode'] ?? 'existing');
            $job_description = Security::sanitizeInput($_POST['job_description']);
            $notes = Security::sanitizeInput($_POST['notes']);
            $admin_id = $_SESSION['admin_id'];
            $user_id = null;
            
            try {
                // Start transaction
                $db->beginTransaction();
                
                // Handle customer assignment
                if ($customer_mode === 'existing') {
                    $user_id = intval($_POST['user_id']);
                    if ($user_id <= 0) {
                        throw new Exception('Please select a valid customer');
                    }
                } else {
                    // For manual entry, just store the job card without user_id
                    // We'll store customer details in notes for now
                    $manual_name = Security::sanitizeInput($_POST['manual_name'] ?? '');
                    $manual_phone = Security::sanitizeInput($_POST['manual_phone'] ?? '');
                    $manual_email = Security::sanitizeInput($_POST['manual_email'] ?? '');
                    $manual_vehicle = Security::sanitizeInput($_POST['manual_vehicle'] ?? '');
                    
                    if (empty($manual_name) || empty($manual_phone)) {
                        throw new Exception('Please enter customer name and phone');
                    }
                    
                    // Append customer details to notes
                    $notes = "Manual Customer Entry:\n" .
                            "Name: " . $manual_name . "\n" .
                            "Phone: " . $manual_phone . "\n" .
                            "Vehicle: " . $manual_vehicle . "\n" .
                            "Email: " . $manual_email . "\n\n" .
                            $notes;
                }
                
                // Create job card
                $insert_query = $db->prepare('
                    INSERT INTO service_job_cards (user_id, job_description, created_by, notes)
                    VALUES (?, ?, ?, ?)
                ');
                $insert_query->execute([$user_id, $job_description, $admin_id, $notes]);
                $job_card_id = $db->lastInsertId();
                
                // Get selected products
                $selected_products = isset($_POST['products']) ? $_POST['products'] : [];
                $total_cost = 0;
                
                if (!empty($selected_products)) {
                    foreach ($selected_products as $product_data) {
                        if (isset($product_data['product_id'], $product_data['quantity'])) {
                            $product_id = intval($product_data['product_id']);
                            $quantity = intval($product_data['quantity']);
                            
                            // Get product price
                            $product_query = $db->prepare('SELECT price FROM products WHERE id = ?');
                            $product_query->execute([$product_id]);
                            $product = $product_query->fetch();
                            
                            if ($product) {
                                $unit_price = $product['price'];
                                $subtotal = $unit_price * $quantity;
                                $total_cost += $subtotal;
                                
                                // Insert job card item
                                $item_query = $db->prepare('
                                    INSERT INTO job_card_items (job_card_id, product_id, quantity, unit_price, subtotal)
                                    VALUES (?, ?, ?, ?, ?)
                                ');
                                $item_query->execute([$job_card_id, $product_id, $quantity, $unit_price, $subtotal]);
                            }
                        }
                    }
                }
                
                // Update job card total cost
                $update_query = $db->prepare('UPDATE service_job_cards SET total_cost = ? WHERE id = ?');
                $update_query->execute([$total_cost, $job_card_id]);
                
                // Send alert to customer (only if user_id exists)
                if ($user_id) {
                    $alert_message = "Service job card #" . str_pad($job_card_id, 6, '0', STR_PAD_LEFT) . " has been created. Description: " . substr($job_description, 0, 100) . "...";
                    $alert_query = $db->prepare('
                        INSERT INTO customer_alerts (user_id, alert_type, message)
                        VALUES (?, "service", ?)
                    ');
                    $alert_query->execute([$user_id, $alert_message]);
                }
                
                $db->commit();
                $message = '<div class="alert alert-success">Job card created successfully! (ID: ' . str_pad($job_card_id, 6, '0', STR_PAD_LEFT) . ')</div>';
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = '<div class="alert alert-error">Error creating job card: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        
        elseif ($action === 'update_job') {
            $job_id = intval($_POST['job_id']);
            $job_description = Security::sanitizeInput($_POST['job_description']);
            $notes = Security::sanitizeInput($_POST['notes']);
            
            try {
                $db->beginTransaction();
                
                // Update job card
                $update_query = $db->prepare('
                    UPDATE service_job_cards 
                    SET job_description = ?, notes = ?
                    WHERE id = ?
                ');
                $update_query->execute([$job_description, $notes, $job_id]);
                
                // Delete old items
                $delete_query = $db->prepare('DELETE FROM job_card_items WHERE job_card_id = ?');
                $delete_query->execute([$job_id]);
                
                // Get selected products and add new items
                $selected_products = isset($_POST['products']) ? $_POST['products'] : [];
                $total_cost = 0;
                
                if (!empty($selected_products)) {
                    foreach ($selected_products as $product_data) {
                        if (isset($product_data['product_id'], $product_data['quantity'])) {
                            $product_id = intval($product_data['product_id']);
                            $quantity = intval($product_data['quantity']);
                            
                            if ($quantity > 0) {
                                // Get product price
                                $product_query = $db->prepare('SELECT price FROM products WHERE id = ?');
                                $product_query->execute([$product_id]);
                                $product = $product_query->fetch();
                                
                                if ($product) {
                                    $unit_price = $product['price'];
                                    $subtotal = $unit_price * $quantity;
                                    $total_cost += $subtotal;
                                    
                                    // Insert new item
                                    $item_query = $db->prepare('
                                        INSERT INTO job_card_items (job_card_id, product_id, quantity, unit_price, subtotal)
                                        VALUES (?, ?, ?, ?, ?)
                                    ');
                                    $item_query->execute([$job_id, $product_id, $quantity, $unit_price, $subtotal]);
                                }
                            }
                        }
                    }
                }
                
                // Update total cost
                $update_total = $db->prepare('UPDATE service_job_cards SET total_cost = ? WHERE id = ?');
                $update_total->execute([$total_cost, $job_id]);
                
                $db->commit();
                $message = '<div class="alert alert-success">Job card updated successfully!</div>';
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = '<div class="alert alert-error">Error updating job card: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        
        elseif ($action === 'update_status') {
            $job_id = intval($_POST['job_id']);
            $status = Security::sanitizeInput($_POST['status']);
            
            $allowed_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (in_array($status, $allowed_statuses)) {
                try {
                    // Get job and user
                    $job_query = $db->prepare('SELECT * FROM service_job_cards WHERE id = ?');
                    $job_query->execute([$job_id]);
                    $job = $job_query->fetch();
                    
                    if ($job) {
                        // Update status
                        $update_query = $db->prepare('UPDATE service_job_cards SET status = ? WHERE id = ?');
                        $update_query->execute([$status, $job_id]);
                        
                        // Send alert to customer
                        $status_text = ucfirst(str_replace('_', ' ', $status));
                        $alert_message = "Service job card #" . str_pad($job_id, 6, '0', STR_PAD_LEFT) . " status updated to: " . $status_text;
                        $alert_query = $db->prepare('
                            INSERT INTO customer_alerts (user_id, alert_type, message)
                            VALUES (?, "service", ?)
                        ');
                        $alert_query->execute([$job['user_id'], $alert_message]);
                        
                        $message = '<div class="alert alert-success">Job card status updated to: ' . htmlspecialchars($status_text) . '</div>';
                    }
                } catch (Exception $e) {
                    $message = '<div class="alert alert-error">Error updating status: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
        
        elseif ($action === 'delete_job') {
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $message = '<div class="alert alert-error">Invalid request. Please try again.</div>';
            } else {
                $job_id = intval($_POST['job_id']);
                
                try {
                    // Get job info before deleting
                    $job_query = $db->prepare('SELECT * FROM service_job_cards WHERE id = ?');
                    $job_query->execute([$job_id]);
                    $job = $job_query->fetch();
                    
                    if ($job) {
                    // Start transaction
                    $db->beginTransaction();
                    
                    // Delete job card items first (foreign key constraint)
                    $delete_items = $db->prepare('DELETE FROM job_card_items WHERE job_card_id = ?');
                    $delete_items->execute([$job_id]);
                    
                    // Delete job card
                    $delete_job = $db->prepare('DELETE FROM service_job_cards WHERE id = ?');
                    $delete_job->execute([$job_id]);
                    
                    $db->commit();
                    
                    Security::logSecurityEvent('JOB_CARD_DELETED', ['job_id' => $job_id, 'admin_id' => $_SESSION['admin_id']]);
                    $message = '<div class="alert alert-success">Job card #' . str_pad($job_id, 6, '0', STR_PAD_LEFT) . ' deleted successfully!</div>';
                } else {
                    $message = '<div class="alert alert-error">Job card not found.</div>';
                }
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = '<div class="alert alert-error">Error deleting job card: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
}

// Get all job cards with customer info
$jobs_query = $db->prepare('
    SELECT sjc.*, u.username, u.first_name, u.phone, u.email
    FROM service_job_cards sjc
    LEFT JOIN users u ON sjc.user_id = u.id
    ORDER BY sjc.created_at DESC
');
$jobs_query->execute();
$job_cards = $jobs_query->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>
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
        
        .job-cards-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 0;
        }
        
        .job-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .job-card:hover {
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.15);
            transform: translateY(-2px);
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .job-id {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .job-status {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .job-status.pending { background: #ffeaa7; color: #d63031; }
        .job-status.in_progress { background: #a29bfe; color: #2d3436; }
        .job-status.completed { background: #55efc4; color: #00b894; }
        .job-status.cancelled { background: #ff7675; color: white; }
        
        .job-customer {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .customer-info {
            background: var(--light-bg);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .customer-info label {
            font-weight: 700;
            color: var(--text-light);
            font-size: 12px;
            display: block;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .customer-info div {
            color: var(--text-dark);
            font-size: 15px;
            font-weight: 500;
        }
        
        .job-description {
            background: var(--light-bg);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
            line-height: 1.6;
        }
        
        .job-items {
            margin: 20px 0;
        }
        
        .items-title {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 12px;
            color: var(--text-dark);
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .items-list {
            background: var(--light-bg);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 10px;
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-row.header {
            background: white;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .job-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid var(--light-bg);
        }
        
        .job-total {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .job-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--light-bg);
            flex-wrap: wrap;
        }
        
        .new-job-form {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .new-job-form h2 {
            color: var(--text-dark);
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .form-section {
            background: var(--light-bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
        }
        
        .form-section h4 {
            color: var(--text-dark);
            font-size: 16px;
            margin-bottom: 15px;
            font-weight: 700;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .customer-selector-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .customer-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-light);
            border-bottom: 3px solid transparent;
            transition: var(--transition);
        }

        .customer-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .customer-tab:hover {
            color: var(--primary-color);
        }

        .customer-section {
            display: none;
        }

        .customer-section.active {
            display: block;
        }

        .product-search {
            position: relative;
            margin-bottom: 15px;
        }

        .product-search-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
        }

        .product-search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .product-search-results {
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

        .product-search-results.active {
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

        .search-result-price {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 2px;
        }

        .customer-search {
            position: relative;
            margin-bottom: 15px;
        }

        .customer-search-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
        }

        .customer-search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .customer-search-results {
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

        .customer-search-results.active {
            display: block;
        }

        .customer-result-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .customer-result-item:hover {
            background: var(--bg-secondary);
        }

        .customer-result-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }

        .customer-result-info {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .product-item {
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 8px;
            background: white;
            display: flex;
            flex-direction: column;
        }

        .product-item input[type="number"] {
            width: 100%;
            padding: 6px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-align: center;
        }
        
        .no-jobs {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                <p>Create and manage customer service job cards</p>
            </div>
            
            <?php if ($message) echo $message; ?>
            
            <!-- Create New Job Card Form -->
            <div class="new-job-form">
                <h2>Create New Service Job Card</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_job">
                    
                    <div class="form-section">
                        <h4>Customer Details</h4>
                        <div class="customer-selector-tabs">
                            <button type="button" class="customer-tab active" onclick="switchCustomerTab('existing')">📋 Existing Customer</button>
                            <button type="button" class="customer-tab" onclick="switchCustomerTab('manual')">✏️ Manual Entry</button>
                        </div>

                        <!-- Existing Customer Tab -->
                        <div id="existing-customer" class="customer-section active">
                            <div class="customer-search">
                                <input type="text" class="customer-search-input" id="customerSearch" placeholder="🔍 Search customers by name, phone or vehicle...">
                                <div class="customer-search-results" id="customerSearchResults"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="user_id">Customer *</label>
                                <select id="user_id" name="user_id" onchange="updateCustomerFields()">
                                    <option value="">-- Select Customer --</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" data-phone="<?php echo htmlspecialchars($customer['phone']); ?>" data-email="<?php echo htmlspecialchars($customer['email']); ?>" data-vehicle="<?php echo htmlspecialchars($customer['vehicle_number'] ?? ''); ?>" data-name="<?php echo htmlspecialchars($customer['first_name'] ?? $customer['username']); ?>">
                                            <?php echo htmlspecialchars($customer['first_name'] ?? $customer['username']); ?> - <?php echo htmlspecialchars($customer['phone']); ?><?php if(!empty($customer['vehicle_number'])) echo ' - ' . htmlspecialchars($customer['vehicle_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Manual Entry Tab -->
                        <div id="manual-customer" class="customer-section">
                            <input type="hidden" id="customer_mode" name="customer_mode" value="existing">
                            <div class="form-group">
                                <label for="manual_name">Customer Name *</label>
                                <input type="text" id="manual_name" name="manual_name" placeholder="Enter customer name...">
                            </div>
                            <div class="form-group">
                                <label for="manual_phone">Phone *</label>
                                <input type="tel" id="manual_phone" name="manual_phone" placeholder="Enter phone number...">
                            </div>
                            <div class="form-group">
                                <label for="manual_email">Email</label>
                                <input type="email" id="manual_email" name="manual_email" placeholder="Enter email...">
                            </div>
                            <div class="form-group">
                                <label for="manual_vehicle">Vehicle Number</label>
                                <input type="text" id="manual_vehicle" name="manual_vehicle" placeholder="Enter vehicle number...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Job Details</h4>
                        <div class="form-group">
                            <label for="job_description">Job Description *</label>
                            <textarea id="job_description" name="job_description" required placeholder="Enter detailed job description..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea id="notes" name="notes" placeholder="Any additional notes for the customer..."></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Add Products Used</h4>
                        <p style="font-size: 12px; color: #666; margin-bottom: 15px;">Select products and quantities used in this job</p>
                        
                        <div class="product-search">
                            <input type="text" class="product-search-input" id="productSearch" placeholder="🔍 Search products by name...">
                            <div class="product-search-results" id="productSearchResults"></div>
                        </div>
                        
                        <div class="products-grid" id="productsGrid">
                            <?php $count = 0; foreach ($products as $product): if ($count >= 6) break; $count++; ?>
                                <div class="product-item" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; flex-direction: column; align-items: flex-start;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px; width: 100%;">
                                        <input type="checkbox" name="products[<?php echo $product['id']; ?>][product_id]" value="<?php echo $product['id']; ?>" style="width: auto;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <small style="display: block; color: #666;">₹<?php echo number_format($product['price'], 2); ?></small>
                                        </div>
                                    </label>
                                    <div style="width: 100%;">
                                        <label style="font-size: 12px; color: #666;">Quantity</label>
                                        <input type="number" name="products[<?php echo $product['id']; ?>][quantity]" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" style="width: 80px; padding: 4px;">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($products) > 6): ?>
                            <p style="font-size: 12px; color: var(--text-light); margin-top: 12px; text-align: center;">
                                Showing 6 of <?php echo count($products); ?> products. Use search above to find more.
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn-submit">Create Job Card</button>
                </form>
            </div>
            
            <!-- Existing Job Cards -->
            <div style="margin-top: 40px;">
                <h2>Service Job Cards</h2>
                
                <?php if (empty($job_cards)): ?>
                    <div class="no-jobs">
                        <p>No job cards created yet.</p>
                    </div>
                <?php else: ?>
                    <div class="job-cards-container">
                        <?php foreach ($job_cards as $job): ?>
                            <div class="job-card">
                                <div class="job-header">
                                    <div>
                                        <div class="job-id">Job Card #<?php echo str_pad($job['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                        <small style="color: #999;">Created: <?php echo date('d M Y H:i', strtotime($job['created_at'])); ?></small>
                                    </div>
                                    <span class="job-status <?php echo $job['status']; ?>"><?php echo str_replace('_', ' ', ucfirst($job['status'])); ?></span>
                                </div>
                                
                                <div class="job-customer">
                                    <div class="customer-info">
                                        <label>Customer Name</label>
                                        <div><?php echo htmlspecialchars($job['first_name']); ?></div>
                                    </div>
                                    <div class="customer-info">
                                        <label>Phone</label>
                                        <div><?php echo htmlspecialchars($job['phone']); ?></div>
                                    </div>
                                    <div class="customer-info">
                                        <label>Email</label>
                                        <div><?php echo htmlspecialchars($job['email']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="job-description">
                                    <strong>Job Description:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($job['job_description'])); ?>
                                </div>
                                
                                <?php 
                                // Get job items
                                $items_query = $db->prepare('
                                    SELECT jci.*, p.name 
                                    FROM job_card_items jci
                                    JOIN products p ON jci.product_id = p.id
                                    WHERE jci.job_card_id = ?
                                ');
                                $items_query->execute([$job['id']]);
                                $job_items = $items_query->fetchAll();
                                ?>
                                
                                <?php if (!empty($job_items)): ?>
                                    <div class="job-items">
                                        <div class="items-title">Products Used</div>
                                        <div class="items-list">
                                            <div class="item-row header">
                                                <div>Product Name</div>
                                                <div>Unit Price</div>
                                                <div>Quantity</div>
                                                <div>Subtotal</div>
                                            </div>
                                            <?php foreach ($job_items as $item): ?>
                                                <div class="item-row">
                                                    <div><?php echo htmlspecialchars($item['name']); ?></div>
                                                    <div>₹<?php echo number_format($item['unit_price'], 2); ?></div>
                                                    <div><?php echo $item['quantity']; ?></div>
                                                    <div>₹<?php echo number_format($item['subtotal'], 2); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="job-footer">
                                    <div>
                                        <?php if (!empty($job['notes'])): ?>
                                            <div><strong>Notes:</strong> <?php echo htmlspecialchars($job['notes']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="job-total">Total Cost: ₹<?php echo number_format($job['total_cost'], 2); ?></div>
                                    </div>
                                </div>
                                
                                <div class="job-actions">
                                    <?php if ($job['status'] !== 'completed' && $job['status'] !== 'cancelled'): ?>
                                        <form method="POST" action="" style="display: flex; gap: 10px; align-items: center; flex: 1;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                            <select name="status" required style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                                                <option value="pending" <?php echo $job['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="in_progress" <?php echo $job['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="completed" <?php echo $job['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $job['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary">Update Status</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($job['status'] !== 'completed' && $job['status'] !== 'cancelled'): ?>
                                        <button class="btn btn-secondary" onclick="toggleEditForm(<?php echo $job['id']; ?>)">✎ Edit Card</button>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this job card? This action cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="delete_job">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="background: #ef4444; color: white;">🗑️ Delete Card</button>
                                    </form>
                                </div>
                                
                                <!-- Edit Form (Hidden by default) -->
                                <div id="edit-form-<?php echo $job['id']; ?>" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                                    <h4 style="color: #333; margin-bottom: 15px; font-size: 16px;">Edit Job Card</h4>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="update_job">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        
                                        <div class="form-group" style="margin-bottom: 15px;">
                                            <label style="display: block; color: #333; font-weight: 600; margin-bottom: 8px;">Job Description</label>
                                            <textarea name="job_description" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; min-height: 100px;"><?php echo htmlspecialchars($job['job_description']); ?></textarea>
                                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 15px;">
                                            <label style="display: block; color: #333; font-weight: 600; margin-bottom: 8px;">Additional Notes</label>
                                            <textarea name="notes" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; min-height: 80px;"><?php echo htmlspecialchars($job['notes'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div style="margin-bottom: 15px;">
                                            <label style="display: block; color: #333; font-weight: 600; margin-bottom: 10px;">Products Used</label>
                                            <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #ddd;">
                                                <?php foreach ($products as $product): ?>
                                                    <?php
                                                    // Check if product is in this job
                                                    $is_in_job = false;
                                                    $qty = 0;
                                                    foreach ($job_items as $item) {
                                                        if ($item['product_id'] == $product['id']) {
                                                            $is_in_job = true;
                                                            $qty = $item['quantity'];
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                    <div style="display: grid; grid-template-columns: 1fr 100px; gap: 15px; margin-bottom: 12px; align-items: center; padding-bottom: 12px; border-bottom: 1px solid #eee;">
                                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                            <input type="checkbox" name="products[<?php echo $product['id']; ?>][product_id]" value="<?php echo $product['id']; ?>" <?php echo $is_in_job ? 'checked' : ''; ?> style="width: auto;">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                                <small style="display: block; color: #666;">₹<?php echo number_format($product['price'], 2); ?></small>
                                                            </div>
                                                        </label>
                                                        <div>
                                                            <input type="number" name="products[<?php echo $product['id']; ?>][quantity]" min="0" value="<?php echo $qty; ?>" placeholder="0" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; text-align: center;">
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 10px;">
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                            <button type="button" class="btn btn-secondary" onclick="toggleEditForm(<?php echo $job['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleEditForm(jobId) {
            const editForm = document.getElementById('edit-form-' + jobId);
            if (editForm) {
                editForm.style.display = editForm.style.display === 'none' ? 'block' : 'none';
            }
        }

        // Customer tab switching
        function switchCustomerTab(tab) {
            const mode = document.getElementById('customer_mode');
            const existingSection = document.getElementById('existing-customer');
            const manualSection = document.getElementById('manual-customer');
            const tabs = document.querySelectorAll('.customer-tab');

            // Update mode
            mode.value = tab;

            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));

            // Update sections
            if (tab === 'existing') {
                existingSection.classList.add('active');
                manualSection.classList.remove('active');
                tabs[0].classList.add('active');
                document.getElementById('user_id').removeAttribute('disabled');
            } else {
                existingSection.classList.remove('active');
                manualSection.classList.add('active');
                tabs[1].classList.add('active');
                document.getElementById('user_id').value = '';
            }
        }

        // Update customer fields when selection changes
        function updateCustomerFields() {
            // Just for consistency, actual values come from select options
        }

        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const mode = document.getElementById('customer_mode').value;
            
            if (mode === 'existing') {
                const userId = document.getElementById('user_id').value;
                if (!userId) {
                    e.preventDefault();
                    alert('Please select a customer');
                    return false;
                }
            } else {
                const name = document.getElementById('manual_name').value.trim();
                const phone = document.getElementById('manual_phone').value.trim();
                if (!name || !phone) {
                    e.preventDefault();
                    alert('Please enter customer name and phone');
                    return false;
                }
            }

            // Check if at least one product is selected
            const selectedProducts = document.querySelectorAll('input[type="checkbox"]:checked');
            if (selectedProducts.length === 0) {
                e.preventDefault();
                alert('Please select at least one product');
                return false;
            }
        });

        // Product search functionality
        const allProducts = [
            <?php foreach ($products as $product): ?>
            { id: <?php echo $product['id']; ?>, name: '<?php echo htmlspecialchars(addslashes($product['name'])); ?>', price: <?php echo $product['price']; ?>, stock: <?php echo $product['stock_quantity']; ?> },
            <?php endforeach; ?>
        ];

        const productSearch = document.getElementById('productSearch');
        const productSearchResults = document.getElementById('productSearchResults');

        if (productSearch) {
            productSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();

                if (query.length === 0) {
                    productSearchResults.classList.remove('active');
                    return;
                }

                const filtered = allProducts.filter(p => p.name.toLowerCase().includes(query));

                if (filtered.length > 0) {
                    productSearchResults.innerHTML = filtered.map(product => `
                        <div class="search-result-item" onclick="selectProduct(${product.id}, '${product.name}')">
                            <div class="search-result-name">${product.name}</div>
                            <div class="search-result-price">₹${product.price.toFixed(2)} (Stock: ${product.stock})</div>
                        </div>
                    `).join('');
                    productSearchResults.classList.add('active');
                } else {
                    productSearchResults.innerHTML = '<div class="search-result-item" style="color: #999;">No products found</div>';
                    productSearchResults.classList.add('active');
                }
            });

            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.product-search')) {
                    productSearchResults.classList.remove('active');
                }
            });
        }

        // Select product from search and scroll to it
        function selectProduct(productId, productName) {
            const checkbox = document.querySelector(`input[type="checkbox"][value="${productId}"]`);
            
            if (!checkbox) {
                // Product not in DOM yet, create it
                const productsGrid = document.getElementById('productsGrid');
                const product = allProducts.find(p => p.id === productId);
                
                if (product) {
                    const productHTML = `
                        <div class="product-item" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; flex-direction: column; align-items: flex-start; background: #f9f9f9;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px; width: 100%;">
                                <input type="checkbox" name="products[${product.id}][product_id]" value="${product.id}" style="width: auto;" checked>
                                <div>
                                    <strong>${product.name}</strong>
                                    <small style="display: block; color: #666;">₹${product.price.toFixed(2)}</small>
                                </div>
                            </label>
                            <div style="width: 100%;">
                                <label style="font-size: 12px; color: #666;">Quantity</label>
                                <input type="number" name="products[${product.id}][quantity]" value="1" min="1" max="${product.stock}" style="width: 80px; padding: 4px;">
                            </div>
                        </div>
                    `;
                    productsGrid.insertAdjacentHTML('beforeend', productHTML);
                }
            } else {
                checkbox.checked = true;
            }
            
            document.getElementById('productSearch').value = '';
            document.getElementById('productSearchResults').classList.remove('active');
            
            // Scroll to the product
            if (checkbox) {
                checkbox.closest('.product-item').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Customer search functionality
        const allCustomers = [
            <?php foreach ($customers as $customer): ?>
            { id: <?php echo $customer['id']; ?>, name: '<?php echo htmlspecialchars(addslashes($customer['first_name'] ?? $customer['username'])); ?>', phone: '<?php echo htmlspecialchars(addslashes($customer['phone'])); ?>', email: '<?php echo htmlspecialchars(addslashes($customer['email'])); ?>', vehicle: '<?php echo htmlspecialchars(addslashes($customer['vehicle_number'] ?? '')); ?>' },
            <?php endforeach; ?>
        ];

        const customerSearch = document.getElementById('customerSearch');
        const customerSearchResults = document.getElementById('customerSearchResults');

        if (customerSearch) {
            customerSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();

                if (query.length === 0) {
                    customerSearchResults.classList.remove('active');
                    return;
                }

                const filtered = allCustomers.filter(customer =>
                    customer.name.toLowerCase().includes(query) ||
                    customer.phone.toLowerCase().includes(query) ||
                    customer.email.toLowerCase().includes(query) ||
                    customer.vehicle.toLowerCase().includes(query)
                );

                if (filtered.length > 0) {
                    customerSearchResults.innerHTML = filtered.map(customer => `
                        <div class="customer-result-item" onclick="selectCustomer(${customer.id}, '${customer.name}')">
                            <div class="customer-result-name">${customer.name}</div>
                            <div class="customer-result-info">${customer.phone} • ${customer.email}${customer.vehicle ? ' • ' + customer.vehicle : ''}</div>
                        </div>
                    `).join('');
                    customerSearchResults.classList.add('active');
                } else {
                    customerSearchResults.innerHTML = '<div class="customer-result-item" style="color: #999;">No customers found</div>';
                    customerSearchResults.classList.add('active');
                }
            });

            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.customer-search')) {
                    customerSearchResults.classList.remove('active');
                }
            });
        }

        // Select customer from search
        function selectCustomer(customerId, customerName) {
            const select = document.getElementById('user_id');
            select.value = customerId;
            document.getElementById('customerSearch').value = '';
            customerSearchResults.classList.remove('active');
            updateCustomerFields();
        }
    </script>
</body>
</html>
