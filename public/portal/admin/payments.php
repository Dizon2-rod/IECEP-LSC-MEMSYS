<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'payments';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /IECEP-LSC-MEMSYS/public/login.php');
    exit;
}

$allowed_roles = ['admin', 'super_admin'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header('Location: /IECEP-LSC-MEMSYS/public/portal/member/dashboard.php');
    exit;
}

// Demo data
$recentPayments = [
    ['id'=>'PAY-001','member'=>'Alex Johnson','institution'=>'LSPU Santa Cruz','amount'=>950,'type'=>'Membership Fee','status'=>'paid','date'=>date('Y-m-d',strtotime('-2 days')),'ref'=>'20260041'],
    ['id'=>'PAY-002','member'=>'David Kim','institution'=>'Mapúa Malayan Colleges','amount'=>950,'type'=>'Membership Fee','status'=>'paid','date'=>date('Y-m-d',strtotime('-3 days')),'ref'=>'20260042'],
    ['id'=>'PAY-003','member'=>'Emma Wilson','institution'=>'Colegio de San Juan de Letran','amount'=>950,'type'=>'Membership Fee','status'=>'pending','date'=>date('Y-m-d',strtotime('-1 day')),'ref'=>'20260043'],
    ['id'=>'PAY-004','member'=>'Kenji Tan','institution'=>'UPH - Dr. Jose G. Tamayo','amount'=>500,'type'=>'Event Fee','status'=>'paid','date'=>date('Y-m-d',strtotime('-5 days')),'ref'=>'20260044'],
    ['id'=>'PAY-005','member'=>'Mira Stone','institution'=>'Regional Executive Council','amount'=>950,'type'=>'Membership Fee','status'=>'paid','date'=>date('Y-m-d',strtotime('-1 day')),'ref'=>'20260001'],
];
$totalCollections = 125000;
$pendingAmount = 12400;
$txnThisMonth = 48;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage member payments, dues collection, and transaction records for IECEP-LSC Laguna Student Chapter.">
    <?php include_once __DIR__ . '/../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
</head>
<body>
    <?php include_once __DIR__ . '/../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-credit-card"></i> Payment Management</h1>
                    <p class="ap-page-subtitle">Manage membership dues, event fees, and all chapter financial collections. Blockchain-anchored ledger.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/financial/transactions.php" class="ap-btn-secondary">
                        <i class="fas fa-list"></i> All Transactions
                    </a>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/financial/dashboard.php" class="ap-btn-primary">
                        <i class="fas fa-chart-line"></i> Financial Dashboard
                    </a>
                </div>
            </div>

            <!-- KPI Strip -->
            <div class="ap-kpi-grid-3">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-sack-dollar"></i></div>
                        <div><div class="ap-stat-label">Total</div><div class="ap-stat-sublabel">Collections</div></div>
                    </div>
                    <div class="ap-stat-value">₱<?= number_format($totalCollections) ?></div>
                    <div class="ap-stat-footer">All-Time Verified Collections</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon amber"><i class="fas fa-clock"></i></div>
                        <div><div class="ap-stat-label">Pending</div><div class="ap-stat-sublabel">Awaiting Clearance</div></div>
                    </div>
                    <div class="ap-stat-value">₱<?= number_format($pendingAmount) ?></div>
                    <div class="ap-stat-footer">Outstanding Dues</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-receipt"></i></div>
                        <div><div class="ap-stat-label">Volume</div><div class="ap-stat-sublabel">This Month</div></div>
                    </div>
                    <div class="ap-stat-value"><?= $txnThisMonth ?></div>
                    <div class="ap-stat-footer">Transactions Processed</div>
                </div>
            </div>

            <!-- Recent Payments Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-table-list"></i> Recent Payments</h3>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/financial/transactions.php" class="ap-btn-secondary" style="font-size:0.78rem; padding:0.45rem 1rem;">
                        View All Transactions <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Member / Institution</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $pay): ?>
                                <tr>
                                    <td>
                                        <span class="ap-mono"><?= htmlspecialchars($pay['id']) ?></span><br>
                                        <span style="font-size:0.72rem; color:var(--text-muted);"><?= htmlspecialchars($pay['ref']) ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:0.75rem;">
                                            <div class="ap-avatar-badge"><?= htmlspecialchars(substr($pay['member'], 0, 2)) ?></div>
                                            <div>
                                                <strong style="color:var(--text-heading);"><?= htmlspecialchars($pay['member']) ?></strong><br>
                                                <span style="font-size:0.76rem; color:var(--text-muted);"><?= htmlspecialchars($pay['institution']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="ap-pill navy"><span class="ap-pill-dot"></span><?= htmlspecialchars($pay['type']) ?></span></td>
                                    <td><strong>₱<?= number_format($pay['amount']) ?></strong></td>
                                    <td style="font-size:0.82rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($pay['date'])) ?></td>
                                    <td>
                                        <?php if ($pay['status'] === 'paid'): ?>
                                            <span class="ap-pill active"><span class="ap-pill-dot"></span>Paid</span>
                                        <?php elseif ($pay['status'] === 'pending'): ?>
                                            <span class="ap-pill pending"><span class="ap-pill-dot"></span>Pending</span>
                                        <?php else: ?>
                                            <span class="ap-pill danger"><span class="ap-pill-dot"></span>Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="/IECEP-LSC-MEMSYS/public/portal/admin/financial/receipt.php?id=<?= urlencode($pay['id']) ?>" class="ap-btn-secondary" style="padding:0.3rem 0.85rem; font-size:0.75rem;">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="ap-grid-2">
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <a href="/IECEP-LSC-MEMSYS/public/portal/admin/financial/transactions.php" class="ap-btn-secondary" style="justify-content:flex-start;">
                            <i class="fas fa-list"></i> View All Transactions
                        </a>
                        <a href="/IECEP-LSC-MEMSYS/public/portal/admin/financial/reports.php" class="ap-btn-secondary" style="justify-content:flex-start;">
                            <i class="fas fa-chart-bar"></i> Generate Financial Report
                        </a>
                        <a href="/IECEP-LSC-MEMSYS/public/portal/admin/financial/transparency.php" class="ap-btn-secondary" style="justify-content:flex-start;">
                            <i class="fas fa-eye"></i> Transparency Report
                        </a>
                    </div>
                </div>
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-circle-info"></i> Payment Methods Accepted</h3>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <?php foreach (['GCash (Chapter QR)','Bank Transfer (BPI/BDO)','Cash (at Chapter Office)','PayMaya / Maya App'] as $method): ?>
                            <div style="display:flex; align-items:center; gap:0.65rem; padding:0.5rem 0; border-bottom:1px solid var(--border-light);">
                                <div style="width:8px; height:8px; border-radius:50%; background:var(--accent-emerald); flex-shrink:0;"></div>
                                <span style="font-size:0.84rem; color:var(--text-primary);"><?= $method ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-link"></i><span><strong>Ledger:</strong> Blockchain-Anchored SHA-256</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-sack-dollar"></i><span><strong>Collections:</strong> ₱<?= number_format($totalCollections) ?> Verified</span></div>
            </div>

        </div>
    </main>
</body>
</html>
