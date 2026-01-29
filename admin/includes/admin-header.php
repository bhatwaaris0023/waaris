<?php
/**
 * Admin Header Include
 */
?>
<header class="admin-navbar">
    <div class="admin-navbar-content">
        <div class="admin-brand">
            <h2><a href="dashboard.php">🏍️ Admin</a></h2>
        </div>
        
        <div class="admin-navbar-right">
            <span class="admin-user">
                Admin: <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
            </span>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
</header>
