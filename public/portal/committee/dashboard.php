<?php
require_once __DIR__ . '/../bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('BASE_PUBLIC_URL', '/IECEP-LSC-MEMSYS/public');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_PUBLIC_URL . '/login.php');
    exit;
}

// Role check
$allowed_roles = ['committee', 'committee_creatives', 'committee_logistics', 'committee_marketing', 'committee_technical', 'committee_documentation', 'admin', 'super_admin'];
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
                <h1>Committee Dashboard</h1>
                <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($_SESSION['user']['user_metadata']['full_name'] ?? $_SESSION['user']['email'] ?? 'User'); ?> - Committee Member</p>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>8</h3>
            <p>Active Projects</p>
        </div>
        <div class="stat-card">
            <h3>24</h3>
            <p>Upcoming Events</p>
        </div>
        <div class="stat-card">
            <h3>156</h3>
            <p>Members Engaged</p>
        </div>
        <div class="stat-card">
            <h3>92%</h3>
            <p>Task Completion</p>
        </div>
    </div>

    <div class="content-card">
        <h2>Committee Management</h2>
        <p>Coordinate committee activities, manage documentation, and oversee organizational initiatives.</p>
        
        <div class="quick-actions">
            <a href="<?php echo BASE_PUBLIC_URL; ?>/portal/committee/documentation.php" class="btn">
                <i class="fas fa-folder"></i> Documentation
            </a>
            <a href="<?php echo BASE_PUBLIC_URL; ?>/portal/committee/logistics.php" class="btn">
                <i class="fas fa-truck"></i> Logistics
            </a>
            <a href="<?php echo BASE_PUBLIC_URL; ?>/portal/committee/marketing.php" class="btn">
                <i class="fas fa-chart-line"></i> Marketing
            </a>
            <a href="<?php echo BASE_PUBLIC_URL; ?>/portal/committee/technical.php" class="btn">
                <i class="fas fa-cogs"></i> Technical
            </a>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
