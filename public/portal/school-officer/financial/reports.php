<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'financial-reports';

require_once __DIR__ . '/../../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'Chapter Financial Reports & Collections';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Affiliated Chapter';

$supabase = getSupabaseClient();

// Resolve School
if ($supabase) {
    try {
        if (!$institutionId && $userId) {
            $userProfile = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId, 'limit' => 1]);
            if (is_array($userProfile) && isset($userProfile[0]['institution_id'])) {
                $institutionId = $userProfile[0]['institution_id'];
            }
        }
        if (!$institutionId) {
            $instList = $supabase->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
            if (is_array($instList) && isset($instList[0]['id'])) {
                $institutionId = $instList[0]['id'];
            }
        }
        if ($institutionId) {
            $_SESSION['institution_id'] = $institutionId;
            $instRes = $supabase->select('institutions', ['id' => 'eq.' . $institutionId, 'limit' => 1]);
            if (is_array($instRes) && isset($instRes[0]['name'])) {
                $schoolName = $instRes[0]['name'];
            }
        }
    } catch (Exception $e) {}
}

// Fetch member count
$memberCount = 0;
if ($supabase && $institutionId) {
    try {
        $mems = $supabase->select('members', ['institution_id' => 'eq.' . $institutionId]);
        if (is_array($mems)) $memberCount = count($mems);
    } catch (Exception $e) {}
}

// Calculations based on CBL Policy (₱150/student dues: ₱50 Regional, ₱100 School Chapter)
$duesPerStudent = 150.00;
$regionalSharePerStudent = 50.00;
$localSharePerStudent = 100.00;

$estimatedTotalCollections = $memberCount * $duesPerStudent;
$expectedRegionalRemittance = $memberCount * $regionalSharePerStudent;
$chapterRetainedShare = $memberCount * $localSharePerStudent;

// Fetch Real Transactions
$transactions = [];
$totalPaidToRegional = 0;

if ($supabase && $institutionId) {
    try {
        $res = $supabase->select('transactions', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'created_at.desc'
        ]);
        if (is_array($res) && !isset($res['code'])) {
            $transactions = $res;
            foreach ($transactions as $tx) {
                if (strtolower($tx['status'] ?? '') === 'paid') {
                    $totalPaidToRegional += floatval($tx['amount'] ?? 0);
                }
            }
        }
    } catch (Exception $e) {
        $transactions = [];
    }
}

$balanceDue = max(0, $expectedRegionalRemittance - $totalPaidToRegional);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Chapter dues collection ledger, remittances to regional section, and balance sheets.">
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

        .main-content {
            margin-left: 260px;
            padding: 1.25rem;
            min-height: 100vh;
            box-sizing: border-box;
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

        .mobile-toggle-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #F1F5F9;
            border: 1px solid var(--border-color);
            color: var(--color-navy);
            font-size: 1rem;
            cursor: pointer;
            flex-shrink: 0;
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
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }

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

        .white-controls-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.95rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.65rem;
            box-shadow: var(--shadow-card);
        }
        .search-input-field {
            padding: 0.45rem 0.75rem 0.45rem 2rem;
            border: 1px solid #CBD5E1;
            border-radius: 7px;
            font-size: 0.8rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
            background: #F8FAFC;
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
            .main-content { margin-left: 0; padding: 0.85rem; }
            .mobile-toggle-btn { display: inline-flex; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.5rem !important; }
            .kpi-val { font-size: 1.1rem !important; }
            .kpi-lbl { font-size: 0.66rem !important; }
            .dash-kpi-card { padding: 0.5rem 0.65rem !important; gap: 0.5rem !important; }
            .kpi-icon-pill { width: 32px !important; height: 32px !important; font-size: 0.9rem !important; }
            .dash-header-banner { flex-direction: column; align-items: stretch; gap: 0.65rem; }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                    <button type="button" id="sidebarToggle" class="mobile-toggle-btn" aria-label="Toggle Navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="dash-header-title">
                            <i class="fas fa-chart-line" style="color:var(--color-navy);"></i>
                            <?= htmlspecialchars($schoolName) ?> — Financial Reports
                        </h1>
                        <p class="dash-header-sub">
                            Chapter collections breakdown, regional remittances ledger, and financial clearance proofs.
                        </p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/school-officer/financial/receipts.php" class="btn-white">
                        <i class="fas fa-receipt" style="color:var(--color-blue);"></i> Official Receipts
                    </a>
                    <a href="<?= PORTAL_URL ?>/school-officer/financial/fee-waiver.php" class="btn-white">
                        <i class="fas fa-hand-holding-dollar"></i> Fee Waivers
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-wallet"></i></div>
                    <div>
                        <div class="kpi-val">₱<?= number_format($estimatedTotalCollections, 2) ?></div>
                        <div class="kpi-lbl">Est. Chapter Collections</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#059669;">₱<?= number_format($totalPaidToRegional, 2) ?></div>
                        <div class="kpi-lbl">Remitted to Regional Section</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-building-columns"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#B45309;">₱<?= number_format($chapterRetainedShare, 2) ?></div>
                        <div class="kpi-lbl">Local Chapter Retained Fund</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill <?= ($balanceDue > 0) ? 'amber' : 'emerald' ?>"><i class="fas fa-scale-balanced"></i></div>
                    <div>
                        <div class="kpi-val" style="color:<?= ($balanceDue > 0) ? '#D97706' : '#059669' ?>;">
                            ₱<?= number_format($balanceDue, 2) ?>
                        </div>
                        <div class="kpi-lbl">Outstanding Balance Due</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="txSearchInput" class="search-input-field" placeholder="Search reference #, description, amount..." onkeyup="filterTxTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($transactions) ?> remittance transactions
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-file-invoice-dollar"></i> Remittance & Payment Ledger</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="txTable">
                        <thead>
                            <tr>
                                <th>Transaction Ref & Purpose</th>
                                <th>Remittance Amount</th>
                                <th>Payment Method</th>
                                <th>Date Remitted</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-receipt" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Remittance Transactions Found in Database</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Payments and fee remittances recorded by the Regional Treasurer will display here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                    <?php $paid = strtolower($t['status'] ?? '') === 'paid'; ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($t['description'] ?? 'Chapter Dues Remittance') ?></strong><br>
                                            <span style="font-family:'JetBrains Mono', monospace; font-size:0.7rem; color:#64748B;"><?= htmlspecialchars($t['reference_number'] ?? $t['id'] ?? '') ?></span>
                                        </td>
                                        <td><strong style="color:#059669; font-size:0.84rem;">₱<?= number_format(floatval($t['amount'] ?? 0), 2) ?></strong></td>
                                        <td><span style="color:#64748B; font-size:0.75rem;"><?= htmlspecialchars(ucfirst($t['payment_method'] ?? 'Bank / GCash')) ?></span></td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;"><?= !empty($t['created_at']) ? date('M d, Y', strtotime($t['created_at'])) : 'Recent' ?></td>
                                        <td>
                                            <?php if ($paid): ?>
                                                <span class="ap-pill active"><span class="ap-pill-dot"></span> Paid</span>
                                            <?php else: ?>
                                                <span class="ap-pill pending">Pending Clearance</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        function filterTxTable() {
            const query = document.getElementById('txSearchInput').value.toLowerCase();
            const table = document.getElementById('txTable');
            const trs = table.getElementsByTagName('tr');

            for (let i = 1; i < trs.length; i++) {
                const tr = trs[i];
                if (tr.children.length === 1 && tr.children[0].getAttribute('colspan')) continue;
                const text = tr.textContent.toLowerCase();
                tr.style.display = (text.indexOf(query) > -1) ? '' : 'none';
            }
        }
    </script>
</body>
</html>
