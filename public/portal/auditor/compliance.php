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
$allowed_roles = ['auditor', 'admin', 'super_admin'];
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
                <h1>Compliance Management</h1>
                <p class="welcome-message">Monitor and manage organizational compliance</p>
            </div>
        </div>
    </div>

    <div class="content-card">
        <h2>Compliance Overview</h2>
        <p>Content will be available soon for compliance management.</p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>98%</h3>
                <p>Compliance Rate</p>
            </div>
            <div class="stat-card">
                <h3>12</h3>
                <p>Active Audits</p>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
