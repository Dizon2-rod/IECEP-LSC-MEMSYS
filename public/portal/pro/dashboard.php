<?php
session_start();
define('BASE_PUBLIC_URL', '/IECEP-LSC-MEMSYS/public');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_PUBLIC_URL . '/login.php');
    exit;
}

// Role check
$allowed_roles = ['pro', 'admin', 'super_admin'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header('Location: ' . BASE_PUBLIC_URL . '/portal/member/dashboard.php');
    exit;
}

include_once __DIR__ . '/../../../includes/head-meta.php';
include_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="main-content">
    <div class="dashboard-header">
        <div class="header-content">
            <div>
                <h1>PRO Dashboard</h1>
                <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($_SESSION['user']['user_metadata']['full_name'] ?? $_SESSION['user']['email'] ?? 'User'); ?> - Public Relations Officer</p>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>12</h3>
            <p>Active Campaigns</p>
        </div>
        <div class="stat-card">
            <h3>48</h3>
            <p>Media Mentions</p>
        </div>
        <div class="stat-card">
            <h3>156</h3>
            <p>Social Media Posts</p>
        </div>
        <div class="stat-card">
            <h3>89%</h3>
            <p>Engagement Rate</p>
        </div>
    </div>

    <div class="content-card">
        <h2>Public Relations Overview</h2>
        <p>Manage public relations campaigns, media outreach, and promotional activities for IECEP-LSC.</p>
        
        <div class="quick-actions">
            <a href="<?php echo BASE_PUBLIC_URL; ?>/portal/pro/media.php" class="btn">
                <i class="fas fa-photo-video"></i> Manage Media
            </a>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
