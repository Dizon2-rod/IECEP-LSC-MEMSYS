<?php
$current_page = basename(__FILE__, '.php');
session_start();
define('BASE_PUBLIC_URL', '/IECEP-LSC-MEMSYS/public');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_PUBLIC_URL . '/login.php');
    exit;
}

// Role check
$allowed_roles = ['admin', 'super_admin'];
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
                <h1>Payment Management</h1>
                <p class="welcome-message">Manage member payments and transactions</p>
            </div>
        </div>
    </div>

    <div class="content-card">
        <h2>Payments Overview</h2>
        <p>Content will be available soon for payment management.</p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>₱125,000</h3>
                <p>Total Collections</p>
            </div>
            <div class="stat-card">
                <h3>48</h3>
                <p>Transactions This Month</p>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

