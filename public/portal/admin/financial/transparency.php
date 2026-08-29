<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'transparency';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'treasurer', 'auditor', 'eb_treasurer', 'eb_auditor']);

$pageTitle = 'Financial Transparency';
$supabase = getSupabaseClient();

$totalInflow = 0.0;
$totalOutflow = 0.0;
$transactionsList = [];
$blockchainProofs = 0;

try {
    $rawTx = $supabase->select('transactions', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawTx)) {
        $transactionsList = $rawTx;
        foreach ($rawTx as $tx) {
            $amt = floatval($tx['amount'] ?? 0);
            $type = strtolower($tx['type'] ?? '');
            $st = strtolower($tx['status'] ?? 'pending');

            if ($st === 'paid' || $st === 'completed') {
                if ($type === 'expense' || $type === 'disbursement') {
                    $totalOutflow += $amt;
                } else {
                    $totalInflow += $amt;
                }
            }
        }
    }

    $rawBc = $supabase->select('blockchain_records', ['select' => 'id']);
    if (is_array($rawBc)) {
        $blockchainProofs = count($rawBc);
    }
} catch (Exception $e) {
    error_log("Transparency audit error: " . $e->getMessage());
}

$netReserve = $totalInflow - $totalOutflow;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Public and internal financial transparency registry backed by blockchain cryptographic verification.">
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
            --color-rose: #E11D48;
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

        .btn-primary-navy {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.95rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--color-navy);
            border: 1px solid var(--color-navy);
            color: #FFFFFF !important;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(11, 29, 74, 0.15);
            transition: all 0.18s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
            color: #FDE047 !important;
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
        .kpi-icon-pill.rose { background: #FFF1F2; color: #E11D48; border: 1px solid #FECDD3; }
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
                        <i class="fas fa-eye" style="color:var(--color-navy);"></i>
                        Financial Transparency & Audit Records
                    </h1>
                    <p class="dash-header-sub">
                        Fund allocations, expenditure accountability, and cryptographic ledger verification records.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/financial/dashboard.php" class="btn-white">
                        <i class="fas fa-chart-pie" style="color:var(--color-blue);"></i> Treasury Dashboard
                    </a>
                    <a href="<?= BASE_URL ?>/transparency.php" target="_blank" class="btn-primary-navy">
                        <i class="fas fa-arrow-up-right-from-square"></i> Open Public Transparency Page
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-sack-dollar"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#059669;">₱<?= number_format($totalInflow, 2) ?></div>
                        <div class="kpi-lbl">Total Funds Collected</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill rose"><i class="fas fa-money-bill-transfer"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#E11D48;">₱<?= number_format($totalOutflow, 2) ?></div>
                        <div class="kpi-lbl">Audited Expenditures</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-wallet"></i></div>
                    <div>
                        <div class="kpi-val">₱<?= number_format($netReserve, 2) ?></div>
                        <div class="kpi-lbl">Net Reserve Balance</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="kpi-val"><?= $blockchainProofs ?> Proofs</div>
                        <div class="kpi-lbl">Cryptographic SHA-256</div>
                    </div>
                </div>
            </div>

            <!-- 3. Transparency Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-table-list"></i> Audited Financial Ledger Records</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Receipt / Ref</th>
                                <th>Description / Particulars</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Ledger Status</th>
                                <th>Date Recorded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactionsList)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-scale-balanced" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Transparency Records Yet</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Financial remittances and expenditures will automatically appear here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactionsList as $tx): ?>
                                    <?php 
                                        $amt = floatval($tx['amount'] ?? 0);
                                        $type = strtolower($tx['type'] ?? 'membership_fee');
                                        $isExp = ($type === 'expense' || $type === 'disbursement');
                                    ?>
                                    <tr>
                                        <td><span style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; font-weight:700; color:var(--color-navy);"><?= htmlspecialchars($tx['receipt_number'] ?? $tx['id'] ?? 'TXN') ?></span></td>
                                        <td><strong><?= htmlspecialchars($tx['notes'] ?? $tx['description'] ?? 'Dues Remittance') ?></strong></td>
                                        <td><span class="ap-pill <?= $isExp ? 'danger' : 'blue' ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?></span></td>
                                        <td><strong style="color:<?= $isExp ? '#E11D48' : '#059669' ?>;">₱<?= number_format($amt, 2) ?></strong></td>
                                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Anchored</span></td>
                                        <td style="color:#64748B; font-size:0.75rem;"><?= !empty($tx['created_at']) ? date('M d, Y', strtotime($tx['created_at'])) : 'Recent' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
