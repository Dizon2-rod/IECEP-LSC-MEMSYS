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
                <h1>Documentation Management</h1>
                <p class="welcome-message">Manage committee documentation and records</p>
            </div>
        </div>
    </div>

    <div class="content-card">
        <h2>Documentation Overview</h2>
        <p>Content will be available soon for documentation management.</p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>24</h3>
                <p>Documents</p>
            </div>
            <div class="stat-card">
                <h3>8</h3>
                <p>Categories</p>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
