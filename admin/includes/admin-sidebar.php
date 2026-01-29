<?php
/**
 * Admin Sidebar Include
 */
?>
<nav class="admin-menu">
    <ul class="admin-menu-list">
        <li>
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                📊 Dashboard
            </a>
        </li>
        
        <li>
            <a href="product-management.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'product-management.php' ? 'active' : ''; ?>">
                📦 Products
            </a>
        </li>
        
        <li>
            <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                👥 Users
            </a>
        </li>
        
        <li>
            <a href="orders.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
                🛒 Orders
            </a>
        </li>
        
        <li>
            <a href="customers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                👤 Customers
            </a>
        </li>
        
        <li>
            <a href="categories.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : ''; ?>">
                🏷️ Categories
            </a>
        </li>
        
        <li>
            <a href="customer-activity.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'customer-activity.php' ? 'active' : ''; ?>">
                📈 Customer Activity
            </a>
        </li>
        
        <li>
            <a href="alerts.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'alerts.php' ? 'active' : ''; ?>">
                🔔 Send Alerts
            </a>
        </li>
        
        <li>
            <a href="job-cards.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'job-cards.php' ? 'active' : ''; ?>">
                🛠️ Service Job Cards
            </a>
        </li>
        

        
        <li>
            <a href="featured-products.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'featured-products.php' ? 'active' : ''; ?>">
                ⭐ Featured Products
            </a>
        </li>
        
        <?php if (AdminAuth::isSuperAdmin()): ?>
            <li>
                <a href="testimonials.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'testimonials.php' ? 'active' : ''; ?>">
                    💬 Testimonials
                </a>
            </li>
            
            <li>
                <a href="admin-users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'admin-users.php' ? 'active' : ''; ?>">
                    🔐 Admin Users & Roles
                </a>
            </li>
            
            <li>
                <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                    ⚙️ Settings
                </a>
            </li>
        <?php endif; ?>
        
        <li class="menu-divider"></li>
        
        <li>
            <a href="logout.php" class="menu-item menu-logout">
                🚪 Logout
            </a>
        </li>
    </ul>
</nav>
