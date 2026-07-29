<?php
require_once __DIR__ . '/../auth_check.php';

require_once __DIR__ . '/../bootstrap.php';
$current_page = 'payments';

require_once __DIR__ . '/../../../includes/config.php';
require_role(['treasurer', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Treasurer Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/portal.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-credit-card"></i> Payments Management</h1>
            <p class="text-muted">View and manage payments</p>
        </div>

        <div class="content-card">
            <h2><i class="fas fa-receipt me-2"></i>Payment Records</h2>
            <p>Payment management features are available in the admin financial dashboard.</p>
            <a href="<?php echo PORTAL_URL; ?>/admin/financial/transactions.php" class="btn btn-primary">
                <i class="fas fa-list"></i> View Transactions
            </a>
            <a href="<?php echo PORTAL_URL; ?>/admin/financial/reports.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> Financial Reports
            </a>
        </div>
    </div>
</body>
</html>
