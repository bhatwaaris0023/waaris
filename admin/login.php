<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/auth.php';

// If already logged in, redirect to dashboard
if (AdminAuth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $result = AdminAuth::login($username, $password);
            
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Ahanger MotoCorp</title>
    <link rel="stylesheet" href="assets/admin-style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-container {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 50px;
            max-width: 450px;
            width: 100%;
            box-shadow: var(--shadow-xl);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 32px;
            margin: 0 0 10px 0;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            transition: var(--transition);
            box-sizing: border-box;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .password-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-input-group input {
            flex: 1;
            padding-right: 50px;
        }
        
        .btn-toggle-password {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 18px;
            color: var(--primary-color);
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-toggle-password:hover {
            color: var(--primary-dark);
        }
        
        .toggle-icon {
            display: inline-block;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-lg);
        }
        
        .alert-error {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .security-note {
            background: var(--primary-light);
            border: 1px solid var(--primary-color);
            padding: 15px;
            border-radius: var(--radius-md);
            margin-top: 20px;
            font-size: 12px;
            color: var(--text-dark);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <h1>🏍️ Admin Panel</h1>
                <p>Ahanger MotoCorp Administration</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-group">
                    <label for="username">Admin Username</label>
                    <input type="text" id="username" name="username" required autofocus autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-group">
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <button type="button" class="btn-toggle-password" data-target="password">
                            <span class="toggle-icon">👁️</span>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">Login to Dashboard</button>
            </form>
            
            <div class="security-note">
                <strong>⚠️ Security Notice:</strong> This is a restricted area. Unauthorized access attempts are logged and monitored. Only authorized administrators should access this panel.
            </div>
        </div>
    </div>
    
    <script>
        // Show/Hide Password Toggle
        document.querySelectorAll('.btn-toggle-password').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('.toggle-icon');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = '🙈';
                } else {
                    input.type = 'password';
                    icon.textContent = '👁️';
                }
            });
        });
    </script>
</body>
</html>
