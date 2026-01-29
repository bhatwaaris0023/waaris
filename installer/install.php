<?php
/**
 * Website Installation Script
 * Setup: Database, Admin User, and Initial Configuration
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load database schema
require_once __DIR__ . '/database.sql.php';

// Check if already installed
$config_file = __DIR__ . '/../config.php';
$is_installed = file_exists($config_file);

$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($current_step) {
        case 1:
            // Database configuration
            $db_host = isset($_POST['db_host']) ? trim($_POST['db_host']) : '';
            $db_name = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
            $db_user = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
            $db_pass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';

            if (empty($db_host) || empty($db_name) || empty($db_user)) {
                $error = 'Please fill in all database fields.';
            } else {
                try {
                    // Test connection
                    $test_dsn = 'mysql:host=' . $db_host . ';charset=utf8mb4';
                    $test_pdo = new PDO($test_dsn, $db_user, $db_pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);

                    // Create database
                    $test_pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $db_name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

                    // Select database
                    $test_pdo->exec('USE `' . $db_name . '`');

                    // Execute schema
                    $statements = explode(';', DATABASE_SCHEMA);
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $test_pdo->exec($statement);
                        }
                    }

                    // Store in session
                    $_SESSION['db_config'] = [
                        'host' => $db_host,
                        'name' => $db_name,
                        'user' => $db_user,
                        'pass' => $db_pass,
                    ];

                    $success = 'Database created and schema installed successfully!';
                    $current_step = 2;

                } catch (PDOException $e) {
                    $error = 'Database Error: ' . $e->getMessage();
                }
            }
            break;

        case 2:
            // Create Admin User
            if (!isset($_SESSION['db_config'])) {
                $error = 'Database configuration missing. Start over.';
            } else {
                $admin_username = isset($_POST['admin_username']) ? trim($_POST['admin_username']) : '';
                $admin_email = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : '';
                $admin_password = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
                $admin_password_confirm = isset($_POST['admin_password_confirm']) ? $_POST['admin_password_confirm'] : '';

                if (empty($admin_username) || empty($admin_email) || empty($admin_password)) {
                    $error = 'Please fill in all admin fields.';
                } elseif ($admin_password !== $admin_password_confirm) {
                    $error = 'Passwords do not match.';
                } elseif (strlen($admin_password) < 8) {
                    $error = 'Password must be at least 8 characters long.';
                } else {
                    try {
                        $db = $_SESSION['db_config'];
                        $pdo = new PDO(
                            'mysql:host=' . $db['host'] . ';dbname=' . $db['name'],
                            $db['user'],
                            $db['pass'],
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                        );

                        // Hash password
                        $hashed_pass = password_hash($admin_password, PASSWORD_BCRYPT);

                        // Insert admin user
                        $stmt = $pdo->prepare('
                            INSERT INTO admin_users (username, email, password, role, status) 
                            VALUES (?, ?, ?, ?, ?)
                        ');
                        $stmt->execute([
                            $admin_username,
                            $admin_email,
                            $hashed_pass,
                            'super_admin',
                            'active'
                        ]);

                        $_SESSION['admin_created'] = true;
                        $success = 'Admin user created successfully!';
                        $current_step = 3;

                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate') !== false) {
                            $error = 'Username or email already exists.';
                        } else {
                            $error = 'Error creating admin: ' . $e->getMessage();
                        }
                    }
                }
            }
            break;

        case 3:
            // Create config.php
            if (!isset($_SESSION['db_config'])) {
                $error = 'Database configuration missing.';
            } else {
                $db = $_SESSION['db_config'];
                
                $config_content = "<?php\n";
                $config_content .= "// Database Configuration\n";
                $config_content .= "define('DB_HOST', '" . addslashes($db['host']) . "');\n";
                $config_content .= "define('DB_NAME', '" . addslashes($db['name']) . "');\n";
                $config_content .= "define('DB_USER', '" . addslashes($db['user']) . "');\n";
                $config_content .= "define('DB_PASS', '" . addslashes($db['pass']) . "');\n";
                $config_content .= "define('CONFIG_LOADED', true);\n";
                $config_content .= "?>\n";

                if (file_put_contents($config_file, $config_content)) {
                    $_SESSION['installation_complete'] = true;
                    $success = 'Configuration file created successfully!';
                    $current_step = 4;
                } else {
                    $error = 'Could not write config.php file. Check permissions.';
                }
            }
            break;
    }
}

// Pre-fill form with defaults
$db_host = $_SESSION['db_config']['host'] ?? 'localhost';
$db_name = $_SESSION['db_config']['name'] ?? 'waaris_db';
$db_user = $_SESSION['db_config']['user'] ?? 'root';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waaris - Installation Wizard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 600px;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 10px;
        }

        .progress-step {
            flex: 1;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            position: relative;
        }

        .progress-step.active,
        .progress-step.completed {
            background: #667eea;
        }

        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-hint {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .alert-info {
            background: #eef;
            color: #33c;
            border: 1px solid #ccf;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #ddd;
            color: #333;
        }

        .btn-secondary:hover {
            background: #ccc;
        }

        .success-content {
            text-align: center;
        }

        .success-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .success-content h2 {
            color: #3c3;
            margin-bottom: 10px;
        }

        .success-content p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .next-steps {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-size: 14px;
        }

        .next-steps ol {
            margin-left: 20px;
        }

        .next-steps li {
            margin-bottom: 10px;
            color: #666;
        }

        a {
            color: #667eea;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Waaris Installation</h1>
            <p>Setup your e-commerce platform in a few steps</p>
        </div>

        <div class="progress-bar">
            <div class="progress-step <?php echo $current_step >= 1 ? 'completed' : ''; ?> <?php echo $current_step === 1 ? 'active' : ''; ?>"></div>
            <div class="progress-step <?php echo $current_step >= 2 ? 'completed' : ''; ?> <?php echo $current_step === 2 ? 'active' : ''; ?>"></div>
            <div class="progress-step <?php echo $current_step >= 3 ? 'completed' : ''; ?> <?php echo $current_step === 3 ? 'active' : ''; ?>"></div>
            <div class="progress-step <?php echo $current_step >= 4 ? 'completed' : ''; ?> <?php echo $current_step === 4 ? 'active' : ''; ?>"></div>
        </div>

        <!-- Step 1: Database Configuration -->
        <div class="step-content <?php echo $current_step === 1 ? 'active' : ''; ?>">
            <h2>Step 1: Database Configuration</h2>
            <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Enter your database credentials to set up the database and tables.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="<?php echo htmlspecialchars($db_host); ?>" required>
                    <div class="form-hint">Usually: localhost or 127.0.0.1</div>
                </div>

                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" value="<?php echo htmlspecialchars($db_name); ?>" required>
                    <div class="form-hint">New or existing database (will be created if doesn't exist)</div>
                </div>

                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" value="<?php echo htmlspecialchars($db_user); ?>" required>
                    <div class="form-hint">Usually: root for local development</div>
                </div>

                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" value="">
                    <div class="form-hint">Leave empty if no password</div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">Continue to Step 2 →</button>
                </div>
            </form>
        </div>

        <!-- Step 2: Create Admin User -->
        <div class="step-content <?php echo $current_step === 2 ? 'active' : ''; ?>">
            <h2>Step 2: Create Admin Account</h2>
            <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Create your administrator account to access the admin panel.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Admin Username</label>
                    <input type="text" name="admin_username" required>
                    <div class="form-hint">Username for admin login</div>
                </div>

                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="admin_email" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="admin_password" required>
                    <div class="form-hint">At least 8 characters</div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="admin_password_confirm" required>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">Create Admin & Continue →</button>
                </div>
            </form>
        </div>

        <!-- Step 3: Setup Complete -->
        <div class="step-content <?php echo $current_step === 3 ? 'active' : ''; ?>">
            <h2>Step 3: Finalizing Setup</h2>
            <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Creating configuration file...</p>

            <form method="POST">
                <div class="alert alert-info">The config.php file is being created with your database settings.</div>
                <div class="button-group">
                    <button type="submit" class="btn-primary">Complete Installation →</button>
                </div>
            </form>
        </div>

        <!-- Step 4: Success -->
        <div class="step-content <?php echo $current_step === 4 ? 'active' : ''; ?>">
            <div class="success-content">
                <div class="success-icon">✅</div>
                <h2>Installation Complete!</h2>
                <p>Your Waaris e-commerce platform is now ready to use.</p>

                <div class="next-steps">
                    <strong>What's Next?</strong>
                    <ol>
                        <li><a href="../admin/login.php">Login to Admin Panel</a></li>
                        <li>Add your motorcycle products and categories</li>
                        <li>Configure site settings</li>
                        <li>Test the customer interface</li>
                    </ol>
                </div>

                <p style="color: #999; font-size: 12px; margin-top: 20px;">
                    <strong>Important:</strong> Delete this installer file (installer/install.php) from your server for security.
                </p>

                <div class="button-group">
                    <a href="../admin/login.php" style="flex: 1;">
                        <button class="btn-primary" style="width: 100%; cursor: pointer;">Go to Admin Panel</button>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
