<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/role-config.php';

// Allow admin and super_admin
require_role(['admin', 'super_admin']);

$current_page = 'dashboard';
$user = $_SESSION['user'] ?? [];
$user_role = $_SESSION['role'] ?? $user['role'] ?? 'admin';
$displayName = $user['user_metadata']['full_name'] ?? $user['name'] ?? $user['email'] ?? ($user_role === 'super_admin' ? 'Super Administrator' : 'Administrator');
$roleDisplay = $user_role === 'super_admin' ? 'Super Administrator' : 'Administrator';

$supabase = getSupabaseClient();

// =========================================================================
// 1. FETCH ONLY REAL DATA FROM DATABASE (NO HARDCODED MOCKS / FALLBACKS)
// =========================================================================

// A. Real Institutions / Chapters
$institutionsList = [];
try {
    if ($supabase) {
        $instRes = $supabase->select('institutions', ['select' => '*', 'order' => 'created_at.desc']);
        if (is_array($instRes)) $institutionsList = $instRes;
    }
} catch (\Throwable $e) {
    error_log("Dashboard institutions query: " . $e->getMessage());
}
$totalSchools = count($institutionsList);
$activeSchools = count(array_filter($institutionsList, fn($i) => ($i['status'] ?? '') === 'active' || ($i['compliance_status'] ?? '') === 'compliant'));
$compliancePercentage = $totalSchools > 0 ? round(($activeSchools / $totalSchools) * 100) : 0;

// B. Real Members
$membersList = [];
try {
    if ($supabase) {
        $memRes = $supabase->select('members', ['select' => '*', 'order' => 'created_at.desc']);
        if (is_array($memRes)) $membersList = $memRes;
    }
} catch (\Throwable $e) {
    error_log("Dashboard members query: " . $e->getMessage());
}
$totalMembers = count($membersList);
$paidMembers = count(array_filter($membersList, fn($m) => in_array(strtolower($m['payment_status'] ?? ''), ['paid', 'active', 'completed', 'verified'])));
$pendingMembers = $totalMembers - $paidMembers;
$issuedDigitalIds = count(array_filter($membersList, fn($m) => !empty($m['membership_id'])));

// C. Real Pending Chapter Affiliations
$pendingAffiliationsList = [];
try {
    if ($supabase) {
        $pendRes = $supabase->select('pending_affiliations', ['status' => 'in.(pending,submitted,pending_review)', 'order' => 'created_at.desc']);
        if (is_array($pendRes)) $pendingAffiliationsList = $pendRes;
    }
} catch (\Throwable $e) {
    error_log("Dashboard pending affiliations query: " . $e->getMessage());
}
$pendingAffiliationsCount = count($pendingAffiliationsList);

// D. Real Financial Transactions & Collections
$transactionsList = [];
$totalCollections = 0.0;
$categoryCollections = [
    'Membership Dues' => 0.0,
    'Chapter Affiliations' => 0.0,
    'Events & Summits' => 0.0,
    'Merchandise' => 0.0,
    'Other Collections' => 0.0
];

try {
    if ($supabase) {
        $txRes = $supabase->select('transactions', ['select' => '*', 'order' => 'created_at.desc']);
        if (is_array($txRes)) {
            $transactionsList = $txRes;
            foreach ($txRes as $tx) {
                $amt = floatval($tx['amount'] ?? 0);
                $st = strtolower($tx['status'] ?? '');
                if (in_array($st, ['paid', 'completed', 'verified', 'settled', 'success', 'approved'])) {
                    $totalCollections += $amt;
                    $type = strtolower($tx['transaction_type'] ?? $tx['type'] ?? $tx['description'] ?? 'other');
                    if (strpos($type, 'member') !== false || strpos($type, 'due') !== false) {
                        $categoryCollections['Membership Dues'] += $amt;
                    } elseif (strpos($type, 'affil') !== false || strpos($type, 'school') !== false || strpos($type, 'charter') !== false) {
                        $categoryCollections['Chapter Affiliations'] += $amt;
                    } elseif (strpos($type, 'event') !== false || strpos($type, 'summit') !== false || strpos($type, 'ticket') !== false) {
                        $categoryCollections['Events & Summits'] += $amt;
                    } elseif (strpos($type, 'merch') !== false || strpos($type, 'item') !== false || strpos($type, 'shirt') !== false) {
                        $categoryCollections['Merchandise'] += $amt;
                    } else {
                        $categoryCollections['Other Collections'] += $amt;
                    }
                }
            }
        }
    }
} catch (\Throwable $e) {
    error_log("Dashboard transactions query: " . $e->getMessage());
}

// Filter out categories with 0 for doughnut chart
$nonZeroCategories = array_filter($categoryCollections, fn($v) => $v > 0);
$hasTreasuryData = !empty($nonZeroCategories);

// E. Real Blockchain Records
$blockchainList = [];
try {
    if ($supabase) {
        $bcRes = $supabase->select('blockchain_records', ['select' => '*', 'order' => 'created_at.desc', 'limit' => 6]);
        if (is_array($bcRes)) $blockchainList = $bcRes;
    }
} catch (\Throwable $e) {
    error_log("Dashboard blockchain query: " . $e->getMessage());
}
$totalBlocksCount = count($blockchainList);

// F. Chart Data: Real Members Distribution per Chartered School Chapter
$schoolMemberDistribution = [];
foreach ($institutionsList as $inst) {
    $acronym = $inst['acronym'] ?? '';
    if (empty($acronym)) {
        $words = explode(' ', $inst['name'] ?? 'HEI');
        $acronym = count($words) > 1 ? implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), array_slice($words, 0, 4))) : substr($inst['name'] ?? 'HEI', 0, 8);
    }
    $schoolMemberDistribution[$acronym] = 0;
}

foreach ($membersList as $mem) {
    $mInstId = $mem['institution_id'] ?? '';
    foreach ($institutionsList as $inst) {
        if ($inst['id'] === $mInstId) {
            $acronym = $inst['acronym'] ?? '';
            if (empty($acronym)) {
                $words = explode(' ', $inst['name'] ?? 'HEI');
                $acronym = count($words) > 1 ? implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), array_slice($words, 0, 4))) : substr($inst['name'] ?? 'HEI', 0, 8);
            }
            $schoolMemberDistribution[$acronym] = ($schoolMemberDistribution[$acronym] ?? 0) + 1;
            break;
        }
    }
}

$chartSchoolLabels = array_keys($schoolMemberDistribution);
$chartSchoolData = array_values($schoolMemberDistribution);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Executive Command Desk — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Central administrative dashboard for IECEP-LSC institutional chapters, student memberships, financial auditing, and cryptographic ledger verification.">
    
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg-page: #F8FAFC;
            --bg-card: #FFFFFF;
            --border-subtle: #E2E8F0;
            --border-hover: #CBD5E1;
            
            --text-heading: #0F172A;
            --text-body: #334155;
            --text-muted: #64748B;
            --text-light: #94A3B8;

            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-gold-dark: #B45309;
            --color-emerald: #059669;
            --color-purple: #7C3AED;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
            --shadow-elevated: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            margin: 0;
            padding: 0;
        }

        .dashboard-main-wrap {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* 1. Header Banner */
        .dash-header-banner {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            box-sizing: border-box;
            width: 100%;
        }

        .dash-header-title {
            margin: 0 0 0.15rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dash-header-sub {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .dash-header-btn-group {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
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
            border: 1px solid var(--border-hover);
            color: var(--text-heading);
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            color: #0F172A;
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
            white-space: nowrap;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            border-color: var(--color-navy-hover);
            color: #FDE047 !important;
            transform: translateY(-1px);
        }

        /* 2. Top 4 Real Data KPI Cards */
        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
            width: 100%;
            box-sizing: border-box;
        }

        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
            box-sizing: border-box;
            min-width: 0;
        }
        .dash-kpi-card:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow-elevated);
            transform: translateY(-1px);
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
        .kpi-icon-pill.blue { background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 3. Strategic Shortcuts Grid */
        .dash-shortcuts-bar {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.55rem;
            margin-bottom: 0.85rem;
            width: 100%;
            box-sizing: border-box;
        }

        .shortcut-btn {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 0.55rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            text-decoration: none;
            color: var(--text-heading);
            font-weight: 700;
            font-size: 0.78rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.18s ease;
            box-sizing: border-box;
        }
        .shortcut-btn:hover {
            border-color: var(--color-navy);
            background: #F8FAFC;
            transform: translateY(-1px);
        }
        .shortcut-icon {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        /* 4. Charts 2-Column Grid */
        .dash-charts-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
            width: 100%;
            box-sizing: border-box;
        }

        .chart-card-box {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 0.85rem 1.15rem;
            box-shadow: var(--shadow-card);
            box-sizing: border-box;
        }

        .chart-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.45rem;
            border-bottom: 1px solid var(--border-subtle);
        }
        .chart-title-text {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .chart-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            background: #F1F5F9;
            color: var(--text-muted);
        }

        /* 5. Tables 2-Column Grid */
        .dash-tables-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
            width: 100%;
            box-sizing: border-box;
        }

        .table-card-box {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            box-sizing: border-box;
        }

        .table-topbar {
            padding: 0.75rem 1rem;
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-title {
            font-size: 0.86rem;
            font-weight: 800;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .dash-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            text-align: left;
        }
        .dash-data-table th {
            background: #F8FAFC;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.72rem;
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid var(--border-subtle);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .dash-data-table td {
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: var(--text-body);
            vertical-align: middle;
        }
        .dash-data-table tr:hover td {
            background: #F8FAFC;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .status-badge.active, .status-badge.paid {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .status-badge.pending {
            background: #FEF9C3;
            color: #B45309;
            border: 1px solid #FDE68A;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .dash-shortcuts-bar { grid-template-columns: repeat(3, 1fr); }
            .dash-charts-grid { grid-template-columns: 1fr; }
            .dash-tables-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 0.4rem; }
            .dash-shortcuts-bar { grid-template-columns: repeat(2, 1fr); gap: 0.4rem; }
            .dash-header-banner { padding: 0.65rem 0.85rem; }
        }
    </style>
</head>
<body>

    <!-- Unified Dynamic Sidebar -->
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="dashboard-main-wrap">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div>
                    <h1 class="dash-header-title">
                        <i class="fas fa-gauge-high" style="color:var(--color-navy);"></i>
                        Welcome back, <?= htmlspecialchars($displayName) ?>
                    </h1>
                </div>
                <div class="dash-header-btn-group">
                    <a href="<?= PORTAL_URL ?>/admin/members/list.php" class="btn-white">
                        <i class="fas fa-users" style="color:var(--color-blue);"></i> Member Directory
                    </a>
                    <a href="<?= PORTAL_URL ?>/admin/institutions/list.php" class="btn-white">
                        <i class="fas fa-university" style="color:var(--color-navy);"></i> Chapter Affiliations
                    </a>
                    <a href="<?= PORTAL_URL ?>/admin/members/batch-process.php" class="btn-white">
                        <i class="fas fa-file-excel" style="color:#107C41;"></i> Chapter Submissions
                    </a>
                </div>
            </div>

            <!-- 2. Top 4 Real Data KPI Cards -->
            <div class="dash-kpi-grid">
                <!-- Card 1: Institutions -->
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy">
                        <i class="fas fa-building-columns"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="kpi-val"><?= $totalSchools ?></div>
                        <div class="kpi-lbl">Chartered Institutions</div>
                    </div>
                </div>

                <!-- Card 2: Student Members -->
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="kpi-val"><?= number_format($totalMembers) ?></div>
                        <div class="kpi-lbl"><?= $paidMembers ?> Paid / Dues Cleared</div>
                    </div>
                </div>

                <!-- Card 3: Pending Affiliations -->
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="kpi-val"><?= $pendingAffiliationsCount ?></div>
                        <div class="kpi-lbl">Pending Affiliations</div>
                    </div>
                </div>

                <!-- Card 4: Collections & Treasury -->
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="kpi-val">₱<?= number_format($totalCollections, 2) ?></div>
                        <div class="kpi-lbl">Audited Total Collections</div>
                    </div>
                </div>
            </div>

            <!-- 3. Strategic Shortcuts Bar -->
            <div class="dash-shortcuts-bar">
                <a href="<?= PORTAL_URL ?>/admin/members/list.php" class="shortcut-btn">
                    <div class="shortcut-icon" style="background:#EFF6FF; color:#2563EB;"><i class="fas fa-users"></i></div>
                    <span>Members</span>
                </a>
                <a href="<?= PORTAL_URL ?>/admin/institutions/list.php" class="shortcut-btn">
                    <div class="shortcut-icon" style="background:rgba(11,29,74,0.08); color:#0B1D4A;"><i class="fas fa-university"></i></div>
                    <span>Institutions</span>
                </a>
                <a href="<?= PORTAL_URL ?>/admin/financial/transactions.php" class="shortcut-btn">
                    <div class="shortcut-icon" style="background:#ECFDF5; color:#059669;"><i class="fas fa-receipt"></i></div>
                    <span>Financials</span>
                </a>
                <a href="<?= PORTAL_URL ?>/admin/compliance/dashboard.php" class="shortcut-btn">
                    <div class="shortcut-icon" style="background:#FEF9C3; color:#B45309;"><i class="fas fa-shield-halved"></i></div>
                    <span>Compliance</span>
                </a>
                <a href="<?= PORTAL_URL ?>/admin/blockchain/explorer.php" class="shortcut-btn">
                    <div class="shortcut-icon" style="background:#F5F3FF; color:#7C3AED;"><i class="fas fa-link"></i></div>
                    <span>Blockchain</span>
                </a>
                <a href="<?= PORTAL_URL ?>/admin/system/users.php" class="shortcut-btn">
                    <div class="shortcut-icon" style="background:#FFF1F2; color:#E11D48;"><i class="fas fa-users-gear"></i></div>
                    <span>Users</span>
                </a>
            </div>

            <!-- 4. Dynamic Real Data Charts -->
            <div class="dash-charts-grid">
                <!-- Chart 1: Members Distribution Per School -->
                <div class="chart-card-box">
                    <div class="chart-header-row">
                        <div class="chart-title-text">
                            <i class="fas fa-chart-column" style="color:var(--color-navy);"></i>
                            Student Member Count by Chapter
                        </div>
                        <span class="chart-badge">Live DB Query</span>
                    </div>
                    <div style="position:relative; height:240px; width:100%;">
                        <?php if ($totalSchools > 0): ?>
                            <canvas id="chapterMembersBarChart"></canvas>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); font-size:0.8rem;">
                                <i class="fas fa-chart-bar" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.4rem;"></i>
                                No chapter membership records found in database.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chart 2: Treasury Collections Breakdown -->
                <div class="chart-card-box">
                    <div class="chart-header-row">
                        <div class="chart-title-text">
                            <i class="fas fa-chart-pie" style="color:var(--color-emerald);"></i>
                            Verified Revenue Distribution
                        </div>
                        <span class="chart-badge">Audited Collections</span>
                    </div>
                    <div style="position:relative; height:240px; width:100%; display:flex; align-items:center; justify-content:center;">
                        <?php if ($hasTreasuryData): ?>
                            <canvas id="treasuryDistributionChart"></canvas>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); font-size:0.8rem; text-align:center;">
                                <i class="fas fa-receipt" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.4rem;"></i>
                                <span>No paid transactions recorded in database yet.<br>Total Collections: <strong>₱0.00</strong></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 5. Real Data Tables Grid -->
            <div class="dash-tables-grid">
                
                <!-- Table 1: Chartered Institutions -->
                <div class="table-card-box">
                    <div class="table-topbar">
                        <div class="table-title">
                            <i class="fas fa-building-columns" style="color:var(--color-navy);"></i>
                            Chartered Institutions & Chapters
                        </div>
                        <a href="<?= PORTAL_URL ?>/admin/institutions/list.php" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.6rem;">
                            View All (<?= $totalSchools ?>)
                        </a>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="dash-data-table">
                            <thead>
                                <tr>
                                    <th>Institution</th>
                                    <th>Officer / Advisor</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($institutionsList)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                            <i class="fas fa-school" style="font-size:1.8rem; color:#CBD5E1; margin-bottom:0.4rem; display:block;"></i>
                                            No chartered institutions found in database.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($institutionsList, 0, 5) as $inst): ?>
                                        <tr>
                                            <td>
                                                <strong style="color:var(--text-heading);"><?= htmlspecialchars($inst['name'] ?? 'HEI') ?></strong>
                                                <div style="font-size:0.7rem; color:var(--text-muted);"><?= htmlspecialchars($inst['acronym'] ?? '') ?></div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($inst['contact_person'] ?: 'Faculty Advisor') ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($inst['city'] ?: 'Laguna') ?>
                                            </td>
                                            <td>
                                                <span class="status-badge active"><i class="fas fa-circle" style="font-size:0.35rem;"></i> Active</span>
                                            </td>
                                            <td style="text-align:right;">
                                                <a href="<?= PORTAL_URL ?>/admin/members/list.php?school=<?= urlencode($inst['id']) ?>" class="btn-white" style="font-size:0.7rem; padding:0.25rem 0.55rem;">
                                                    <i class="fas fa-users"></i> Members
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Table 2: Cryptographic Ledger Records -->
                <div class="table-card-box">
                    <div class="table-topbar">
                        <div class="table-title">
                            <i class="fas fa-link" style="color:var(--color-gold-dark);"></i>
                            Recent Blockchain Audit Trail
                        </div>
                        <a href="<?= PORTAL_URL ?>/admin/blockchain/explorer.php" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.6rem;">
                            Explorer
                        </a>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="dash-data-table">
                            <thead>
                                <tr>
                                    <th>Event / Type</th>
                                    <th>SHA-256 Hash</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($blockchainList)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                            <i class="fas fa-cube" style="font-size:1.8rem; color:#CBD5E1; margin-bottom:0.4rem; display:block;"></i>
                                            No blockchain ledger records in database yet.<br>
                                            <span style="font-size:0.72rem;">Hashes are anchored upon member registration & affiliation approval.</span>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($blockchainList as $block): ?>
                                        <?php 
                                            $bType = strtoupper($block['record_type'] ?? $block['entity_type'] ?? 'LEDGER_ENTRY');
                                            $rawHash = $block['transaction_hash'] ?? $block['data_hash'] ?? $block['id'] ?? 'hash';
                                            $shortHash = substr($rawHash, 0, 14) . '...';
                                            $timeStr = !empty($block['created_at']) ? date('M d, H:i', strtotime($block['created_at'])) : 'Recent';
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="status-badge paid" style="font-size:0.68rem;"><?= htmlspecialchars($bType) ?></span>
                                            </td>
                                            <td>
                                                <code style="font-family:'JetBrains Mono',monospace; font-size:0.72rem; color:var(--color-navy); font-weight:700;">
                                                    <?= htmlspecialchars($shortHash) ?>
                                                </code>
                                            </td>
                                            <td style="font-size:0.72rem; color:var(--text-muted); white-space:nowrap;">
                                                <?= $timeStr ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <!-- Chart.js Scripts (Strictly Real Data) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart 1: Real Member Count Per School
        const ctxBar = document.getElementById('chapterMembersBarChart');
        if (ctxBar) {
            const labels = <?= json_encode($chartSchoolLabels) ?>;
            const data = <?= json_encode($chartSchoolData) ?>;

            new Chart(ctxBar.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Registered Students',
                        data: data,
                        backgroundColor: '#0B1D4A',
                        borderRadius: 6,
                        barThickness: 20
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0B1D4A',
                            padding: 8,
                            titleFont: { family: 'Plus Jakarta Sans', weight: '700' },
                            bodyFont: { family: 'Plus Jakarta Sans' }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Plus Jakarta Sans', size: 10, weight: '600' }, color: '#64748B' }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#F1F5F9' },
                            ticks: { stepSize: 1, font: { family: 'Plus Jakarta Sans', size: 10 }, color: '#64748B' }
                        }
                    }
                }
            });
        }

        // Chart 2: Real Treasury Distribution
        const ctxDoughnut = document.getElementById('treasuryDistributionChart');
        if (ctxDoughnut) {
            const treasuryLabels = <?= json_encode(array_keys($nonZeroCategories)) ?>;
            const treasuryData = <?= json_encode(array_values($nonZeroCategories)) ?>;

            new Chart(ctxDoughnut.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: treasuryLabels,
                    datasets: [{
                        data: treasuryData,
                        backgroundColor: ['#0B1D4A', '#D4AF37', '#059669', '#2563EB', '#7C3AED'],
                        borderWidth: 2,
                        borderColor: '#FFFFFF',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { family: 'Plus Jakarta Sans', size: 10, weight: '600' },
                                color: '#475569',
                                boxWidth: 10,
                                boxHeight: 10,
                                useBorderRadius: true,
                                borderRadius: 3,
                                padding: 8
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }
    });
    </script>
</body>
</html>
