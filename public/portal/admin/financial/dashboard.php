<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'financial';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'treasurer', 'auditor']);

$supabase = getSupabaseClient();

$totalCollections = 0.0;
$pendingCollections = 0.0;
$totalMembers = 0;
$totalInstitutions = 0;
$transactionsList = [];
$schoolRevenue = [];

try {
    $rawPayments = $supabase->select('payments', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawPayments)) {
        $transactionsList = $rawPayments;
        foreach ($rawPayments as $p) {
            $amt = floatval($p['amount'] ?? 0);
            $st = strtolower($p['status'] ?? ($p['payment_status'] ?? 'pending'));
            if ($st === 'completed' || $st === 'paid') {
                $totalCollections += $amt;
            } else {
                $pendingCollections += $amt;
            }
        }
    }

    $rawMembers = $supabase->select('members', ['select' => 'id, payment_status']);
    if (is_array($rawMembers)) {
        $totalMembers = count($rawMembers);
    }

    $rawInst = $supabase->select('institutions', ['select' => 'id, name, acronym']);
    if (is_array($rawInst)) {
        $totalInstitutions = count($rawInst);
    }
} catch (Exception $e) {
    error_log("Financial dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Financial & Treasury Dashboard — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Audited collections tracking, dues ledger, and treasury management.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --bg-page: #F8FAFC;
            --border-color: #E2E8F0;
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

        .dash-header-banner {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
        }
        .dash-header-title {
            margin: 0 0 0.15rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dash-header-sub {
            margin: 0;
            font-size: 0.8rem;
            color: #64748B;
        }

        .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            color: #0F172A;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.18s ease;
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            transform: translateY(-1px);
        }

        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            min-width: 0;
        }
        .kpi-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
        }

        .ap-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem;
        }
        .ap-card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFFFFF;
        }
        .ap-card-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 800;
            color: #0F172A;
        }

        .ap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            text-align: left;
        }
        .ap-table th {
            background: #F8FAFC;
            color: #64748B;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
        }
        .ap-table td {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: #334155;
            vertical-align: middle;
        }

        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div>
                    <h1 class="dash-header-title">
                        <i class="fas fa-sack-dollar" style="color:var(--color-navy);"></i>
                        Financial & Treasury Operations
                    </h1>
                    <p class="dash-header-sub">
                        Audited treasury ledger, membership collections, official receipts, and chapter dues management.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/financial/transactions.php" class="btn-white">
                        <i class="fas fa-receipt" style="color:var(--color-blue);"></i> Transactions Ledger
                    </a>
                    <a href="<?= PORTAL_URL ?>/admin/financial/reports.php" class="btn-white">
                        <i class="fas fa-file-invoice-dollar" style="color:#059669;"></i> Financial Reports
                    </a>
                    <a href="<?= PORTAL_URL ?>/admin/financial/transparency.php" class="btn-white">
                        <i class="fas fa-scale-balanced" style="color:#D97706;"></i> Transparency Hub
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-vault"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#059669;">₱<?= number_format($totalCollections, 2) ?></div>
                        <div class="kpi-lbl">Total Audited Collections</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#D97706;">₱<?= number_format($pendingCollections, 2) ?></div>
                        <div class="kpi-lbl">Pending Dues Clearance</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="kpi-val"><?= number_format($totalMembers) ?></div>
                        <div class="kpi-lbl">Enrolled Members</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-school"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalInstitutions ?></div>
                        <div class="kpi-lbl">Chartered Institutions</div>
                    </div>
                </div>
            </div>

            <!-- 3. Recent Transactions Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Recent Payment & Dues Transactions</h3>
                    <a href="<?= PORTAL_URL ?>/admin/financial/transactions.php" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.55rem;">
                        View All (<?= count($transactionsList) ?>)
                    </a>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Transaction Ref / ID</th>
                                <th>Payer / Student</th>
                                <th>Description / Purpose</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactionsList)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-receipt" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Transactions in Database</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Paid membership dues and event fees will immediately display here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($transactionsList, 0, 10) as $t): ?>
                                    <?php 
                                        $amt = floatval($t['amount'] ?? 0);
                                        $st = strtolower($t['status'] ?? ($t['payment_status'] ?? 'pending'));
                                    ?>
                                    <tr>
                                        <td><span style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; font-weight:700; color:var(--color-navy);"><?= htmlspecialchars($t['reference_number'] ?? $t['id'] ?? 'TXN') ?></span></td>
                                        <td><strong><?= htmlspecialchars($t['payer_name'] ?? $t['student_name'] ?? 'Member') ?></strong></td>
                                        <td><?= htmlspecialchars($t['description'] ?? 'Membership Dues (AY 2026-2027)') ?></td>
                                        <td><strong style="color:#059669;">₱<?= number_format($amt, 2) ?></strong></td>
                                        <td><span class="ap-pill <?= ($st === 'completed' || $st === 'paid') ? 'active' : 'pending' ?>"><?= ucfirst($st) ?></span></td>
                                        <td style="color:#64748B; font-size:0.75rem;"><?= !empty($t['created_at']) ? date('M d, Y', strtotime($t['created_at'])) : 'Recent' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip" style="margin-top:1.5rem;">
                <div class="ap-sentinel-item"><i class="fas fa-shield-check"></i><span><strong>Audited Ledger:</strong> Cryptographically Anchored Treasury Proof</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-file-invoice"></i><span><strong>Anti-Fraud:</strong> QR-Verifiable Official Receipts</span></div>
            </div>

        </div>
    </main>
</body>
</html>
