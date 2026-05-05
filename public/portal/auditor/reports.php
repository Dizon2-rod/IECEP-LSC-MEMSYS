<?php
session_start();
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
                <h1>Audit Reports</h1>
                <p class="welcome-message">Generate and manage audit reports</p>
            </div>
        </div>
    </div>

    <div class="content-card">
        <h2>Reports Overview</h2>
        <p>Content will be available soon for audit reports management.</p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>24</h3>
                <p>Reports Generated</p>
            </div>
            <div class="stat-card">
                <h3>8</h3>
                <p>This Quarter</p>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
