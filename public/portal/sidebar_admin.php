<?php
// Admin Sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Admin Portal</h2>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="affiliations.php" class="sidebar-link <?php echo $currentPage === 'affiliations.php' ? 'active' : ''; ?>">
            <i class="fas fa-university"></i>
            <span>Affiliations</span>
        </a>
        <a href="contact-messages.php" class="sidebar-link <?php echo $currentPage === 'contact-messages.php' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>Messages</span>
        </a>
        <a href="events.php" class="sidebar-link <?php echo $currentPage === 'events.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar"></i>
            <span>Events</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/public/logout.php" class="sidebar-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>
