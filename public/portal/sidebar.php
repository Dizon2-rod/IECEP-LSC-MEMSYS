<?php
// Creatives Sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Creatives Portal</h2>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="features-manager.php" class="sidebar-link <?php echo $currentPage === 'features-manager.php' ? 'active' : ''; ?>">
            <i class="fas fa-star"></i>
            <span>Features</span>
        </a>
        <a href="publications.php" class="sidebar-link <?php echo $currentPage === 'publications.php' ? 'active' : ''; ?>">
            <i class="fas fa-newspaper"></i>
            <span>Publications</span>
        </a>
        <a href="graphics.php" class="sidebar-link <?php echo $currentPage === 'graphics.php' ? 'active' : ''; ?>">
            <i class="fas fa-image"></i>
            <span>Graphics</span>
        </a>
        <a href="announcements.php" class="sidebar-link <?php echo $currentPage === 'announcements.php' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/public/logout.php" class="sidebar-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>
