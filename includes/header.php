<?php
/**
 * Include Files - Header
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

// Check user authentication status
$is_logged_in = isset($_SESSION['user_id']);
$user_name = '';
if ($is_logged_in) {
    $user_query = $db->prepare('SELECT first_name FROM users WHERE id = ?');
    $user_query->execute([$_SESSION['user_id']]);
    $user = $user_query->fetch();
    $user_name = $user ? $user['first_name'] : 'User';
}

// Get CSRF token for forms
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Premium motorcycles, accessories, and services from Ahanger MotoCorp">
    <meta name="theme-color" content="#667eea">
    <title><?php echo htmlspecialchars($page_title ?? 'Ahanger MotoCorp'); ?> - Premium Motorcycles</title>
    <link rel="stylesheet" href="/waaris/assets/css/professional-theme.css">
    <link rel="stylesheet" href="/waaris/assets/css/style.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --light-bg: #f5f7fa;
            --text-dark: #2d3748;
            --text-light: #718096;
            --border-color: #e2e8f0;
        }
        
        header.navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }
        
        .navbar-brand h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .navbar-brand a {
            color: white;
            text-decoration: none;
        }
        
        .navbar-menu ul {
            list-style: none;
            display: flex;
            gap: 25px;
            margin: 0;
            padding: 0;
        }
        
        .navbar-menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        .navbar-menu a:hover {
            opacity: 0.8;
        }
        
        .cart-link {
            position: relative;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -12px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
        }
        
        .user-greeting {
            color: #fff;
            font-size: 13px;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
            text-decoration: none;
        }
        
        .btn-login,
        .btn-register {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-login {
            background: transparent;
            color: white;
            border: 1px solid white;
        }
        
        .btn-login:hover {
            background: white;
            color: #667eea;
            text-decoration: none;
        }
        
        .btn-register {
            background: white;
            color: #667eea;
            font-weight: 600;
        }
        
        .btn-register:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .navbar-menu {
                order: 3;
                width: 100%;
            }
            
            .navbar-menu ul {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container navbar-content">
            <div class="navbar-brand">
                <h1><a href="/waaris/">🏍️ Ahanger MotoCorp</a></h1>
            </div>
            
            <nav class="navbar-menu">
                <ul>
                    <li><a href="/waaris/">Home</a></li>
                    <li><a href="/waaris/products.php">Products</a></li>
                    <li><a href="/waaris/cart.php" class="cart-link">
                        Cart <span class="cart-count" id="cart-count">0</span>
                    </a></li>
                </ul>
            </nav>
            
            <div class="navbar-right">
                <?php if ($is_logged_in): ?>
                    <span class="user-greeting">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                    <a href="/waaris/logout.php" class="btn-logout">Logout</a>
                <?php else: ?>
                    <a href="/waaris/login.php" class="btn-login">Login</a>
                    <a href="/waaris/register.php" class="btn-register">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="main-content">
