<?php
/**
 * Customer Profile Page
 * Manage personal information and account settings
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth.php';

Auth::requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user details
$user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $first_name = Security::sanitizeInput($_POST['first_name'] ?? '');
        $last_name = Security::sanitizeInput($_POST['last_name'] ?? '');
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $phone = Security::sanitizeInput($_POST['phone'] ?? '');
        $vehicle_number = Security::sanitizeInput($_POST['vehicle_number'] ?? '');
        $address = Security::sanitizeInput($_POST['address'] ?? '');
        $city = Security::sanitizeInput($_POST['city'] ?? '');
        $state = Security::sanitizeInput($_POST['state'] ?? '');
        $postal_code = Security::sanitizeInput($_POST['postal_code'] ?? '');
        
        if (empty($email)) {
            $error = 'Email is required.';
        } elseif (!Security::validateEmail($email)) {
            $error = 'Invalid email address.';
        } else {
            try {
                $update_stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, vehicle_number = ?, address = ?, city = ?, state = ?, postal_code = ? WHERE id = ?');
                $update_stmt->execute([$first_name, $last_name, $email, $phone, $vehicle_number, $address, $city, $state, $postal_code, $user_id]);
                
                // Refresh user data
                $user_stmt->execute([$user_id]);
                $user = $user_stmt->fetch();
                
                $success = 'Profile updated successfully!';
            } catch (PDOException $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Verify current password
            if (!Security::verifyPassword($current_password, $user['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                try {
                    $hashed_password = Security::hashPassword($new_password);
                    $pwd_stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $pwd_stmt->execute([$hashed_password, $user_id]);
                    
                    $success = 'Password changed successfully!';
                    Security::logSecurityEvent('PASSWORD_CHANGED', ['user_id' => $user_id]);
                } catch (PDOException $e) {
                    error_log('Password change error: ' . $e->getMessage());
                    $error = 'Failed to change password. Please try again.';
                }
            }
        }
    }
}

$csrf_token = Security::generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Ahanger MotoCorp</title>
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
            max-width: 900px;
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
        
        .alert {
            padding: 15px;
            border-radius: 10px;
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
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 22px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-row.full {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group .hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background: #f9f9ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-item strong {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .info-item p {
            color: #333;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>👤 My Profile</h1>
            <a href="customer-dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <!-- Alerts -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Account Information -->
        <div class="card">
            <h2>Account Information</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <strong>Username</strong>
                    <p><?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                <div class="info-item">
                    <strong>Member Since</strong>
                    <p><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
                <div class="info-item">
                    <strong>Account Status</strong>
                    <p><?php echo $user['status'] === 'active' ? '✓ Active' : '● Blocked'; ?></p>
                </div>
                <div class="info-item">
                    <strong>Last Updated</strong>
                    <p><?php echo date('F j, Y', strtotime($user['updated_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Edit Profile -->
        <div class="card">
            <h2>Edit Profile</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address <span style="color: red;">*</span></label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="vehicle_number">Vehicle Number</label>
                        <input type="text" id="vehicle_number" name="vehicle_number" value="<?php echo htmlspecialchars($user['vehicle_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <a href="customer-dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <h2>Change Password</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-row full">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <span class="hint">Minimum 8 characters</span>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-primary">Change Password</button>
                    <button type="reset" class="btn-secondary">Clear</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
