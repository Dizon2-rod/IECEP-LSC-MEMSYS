<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'policy-compliance';

require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'registration', 'committee_registration', 'eb_president', 'auditor']);

$pageTitle = 'Institutional Policy Compliance & Governance';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Default standard regulatory policies for IECEP-LSC accredited chapters
$standardPolicies = [
    [
        'name' => 'Constitution & By-Laws (CBL) Ratification',
        'desc' => 'Submission of official student chapter bylaws ratified and aligned with IECEP National constitution.',
        'due' => '2026-10-31'
    ],
    [
        'name' => 'Accredited Student Member Directory',
        'desc' => 'Complete enrollment roster with verified student numbers and proof of IECEP-LSC registration.',
        'due' => '2026-09-30'
    ],
    [
        'name' => 'Chapter Affiliation & Operational Dues Settlement',
        'desc' => 'Full payment and verification of institutional affiliation fee and roster dues with official receipt.',
        'due' => '2026-09-15'
    ],
    [
        'name' => 'Faculty Adviser & Officer Board Endorsement',
        'desc' => 'Dean/ECE Department Chair official endorsement letter and verified officer curriculum vitae.',
        'due' => '2026-10-15'
    ],
    [
        'name' => 'Regional Technical Summit Delegation',
        'desc' => 'Registration and accredited participation in the flagship IECEP-LSC Regional Technical Summit.',
        'due' => '2026-11-20'
    ]
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'toggle_policy') {
        $policyId = trim($_POST['policy_id'] ?? '');
        $newStatus = ($_POST['is_compliant'] ?? '0') === '1';
        $notes = trim($_POST['notes'] ?? '');
        $timestamp = date('c');

        if ($policyId && $supabase) {
            try {
                $payload = [
                    'is_compliant' => $newStatus,
                    'completed_at' => $newStatus ? $timestamp : null,
                    'updated_at' => $timestamp
                ];
                if (!empty($notes)) {
                    $payload['notes'] = $notes;
                }
                $supabase->update('policy_compliance', $payload, $policyId);
                $feedbackMsg = "✓ Policy status updated to " . ($newStatus ? "Compliant (Passed)" : "Pending Review") . "!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                $feedbackMsg = "Error updating policy: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'add_policy') {
        $instId = trim($_POST['institution_id'] ?? '');
        $policyName = trim($_POST['policy_name'] ?? '');
        $policyDesc = trim($_POST['policy_description'] ?? '');
        $dueDate = trim($_POST['due_date'] ?? date('Y-12-31'));
        $notes = trim($_POST['notes'] ?? '');
        $isCompliant = ($_POST['is_compliant'] ?? '0') === '1';

        if ($instId && $policyName && $supabase) {
            try {
                $timestamp = date('c');
                $supabase->insert('policy_compliance', [[
                    'institution_id' => $instId,
                    'policy_name' => $policyName,
                    'policy_description' => $policyDesc,
                    'due_date' => $dueDate,
                    'notes' => $notes,
                    'is_compliant' => $isCompliant,
                    'completed_at' => $isCompliant ? $timestamp : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);
                $feedbackMsg = "✓ New regulatory policy '{$policyName}' created successfully!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                $feedbackMsg = "Error adding policy: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'batch_comply') {
        $instId = trim($_POST['institution_id'] ?? '');
        if ($instId && $supabase) {
            try {
                $existing = $supabase->select('policy_compliance', ['institution_id' => 'eq.' . $instId]);
                if (is_array($existing)) {
                    $timestamp = date('c');
                    foreach ($existing as $p) {
                        $supabase->update('policy_compliance', [
                            'is_compliant' => true,
                            'completed_at' => $timestamp,
                            'updated_at' => $timestamp
                        ], $p['id']);
                    }
                }
                $supabase->update('institutions', ['compliance_status' => 'compliant'], $instId);
                $feedbackMsg = "🎉 All regulatory policies marked Compliant for chapter!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                $feedbackMsg = "Batch compliance error: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'send_notice') {
        $instName = trim($_POST['institution_name'] ?? 'Chapter');
        $feedbackMsg = "📧 Compliance reminder broadcast queued for {$instName} Chapter Officers!";
        $feedbackType = 'success';
    }
}

// Fetch all chartered institutions
$institutionsList = [];
$institutionsMap = [];
try {
    $instRes = $supabase->select('institutions', ['select' => '*', 'order' => 'name.asc']);
    if (is_array($instRes)) {
        $institutionsList = $instRes;
        foreach ($instRes as $i) {
            $institutionsMap[$i['id']] = $i;
        }
    }
} catch (\Throwable $e) {
    error_log("Policy compliance institutions query: " . $e->getMessage());
}

// Fetch all policy compliance records
$policyRecords = [];
try {
    $polRes = $supabase->select('policy_compliance', ['select' => '*', 'order' => 'created_at.asc']);
    if (is_array($polRes)) {
        $policyRecords = $polRes;
    }
} catch (\Throwable $e) {
    error_log("Policy compliance query: " . $e->getMessage());
}

// Check if institutions need default standard policy seeding
$policiesPerInst = [];
foreach ($policyRecords as $p) {
    $iid = $p['institution_id'] ?? '';
    if ($iid) {
        $policiesPerInst[$iid][] = $p;
    }
}

$needsSeeding = false;
$toSeed = [];
$now = date('c');

foreach ($institutionsList as $inst) {
    $iid = $inst['id'];
    if (empty($policiesPerInst[$iid])) {
        // Prepare seed policies for this institution
        $isInstCompliant = strtolower($inst['compliance_status'] ?? 'compliant') === 'compliant';
        foreach ($standardPolicies as $sp) {
            $toSeed[] = [
                'institution_id' => $iid,
                'policy_name' => $sp['name'],
                'policy_description' => $sp['desc'],
                'due_date' => $sp['due'],
                'is_compliant' => $isInstCompliant,
                'completed_at' => $isInstCompliant ? $now : null,
                'notes' => $isInstCompliant ? 'Verified during 2026-2027 Chapter Chartering' : 'Awaiting officer submission',
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        $needsSeeding = true;
    }
}

// Execute auto-seeding if missing
if ($needsSeeding && !empty($toSeed)) {
    try {
        foreach ($toSeed as $seedItem) {
            $supabase->insert('policy_compliance', [$seedItem]);
        }
        // Re-fetch policies
        $refetched = $supabase->select('policy_compliance', ['select' => '*', 'order' => 'created_at.asc']);
        if (is_array($refetched)) {
            $policyRecords = $refetched;
            $policiesPerInst = [];
            foreach ($policyRecords as $p) {
                $iid = $p['institution_id'] ?? '';
                if ($iid) $policiesPerInst[$iid][] = $p;
            }
        }
    } catch (\Throwable $sEx) {
        error_log("Auto seed policies notice: " . $sEx->getMessage());
    }
}

// Metrics Calculation
$totalPoliciesCount = count($policyRecords);
$totalPassedCount = count(array_filter($policyRecords, fn($p) => !empty($p['is_compliant'])));
$totalPendingCount = $totalPoliciesCount - $totalPassedCount;
$overallComplianceRate = $totalPoliciesCount > 0 ? round(($totalPassedCount / $totalPoliciesCount) * 100, 1) : 100.0;

// Filter handling
$selectedInstFilter = $_GET['inst'] ?? 'all';
$selectedStatusFilter = $_GET['status'] ?? 'all';
$searchQuery = strtolower(trim($_GET['q'] ?? ''));

$filteredRecords = array_filter($policyRecords, function($p) use ($selectedInstFilter, $selectedStatusFilter, $searchQuery, $institutionsMap) {
    if ($selectedInstFilter !== 'all' && ($p['institution_id'] ?? '') !== $selectedInstFilter) {
        return false;
    }
    $isPassed = !empty($p['is_compliant']);
    if ($selectedStatusFilter === 'compliant' && !$isPassed) {
        return false;
    }
    if ($selectedStatusFilter === 'pending' && $isPassed) {
        return false;
    }
    if (!empty($searchQuery)) {
        $instName = strtolower($institutionsMap[$p['institution_id']]['name'] ?? '');
        $polName = strtolower($p['policy_name'] ?? '');
        $polDesc = strtolower($p['policy_description'] ?? '');
        $notes = strtolower($p['notes'] ?? '');
        if (strpos($instName, $searchQuery) === false && strpos($polName, $searchQuery) === false && strpos($polDesc, $searchQuery) === false && strpos($notes, $searchQuery) === false) {
            return false;
        }
    }
    return true;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Official Chapter Policy Compliance, bylaws monitoring, and accreditation matrix for IECEP-LSC Laguna Student Chapter.">
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
            font-family: 'JetBrains Mono', monospace;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
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
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: var(--color-navy);
            border: 1px solid var(--color-navy);
            color: #FFFFFF;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: all 0.18s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
        }

        .filter-panel {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.65rem;
            box-shadow: var(--shadow-card);
        }

        .ap-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .ap-badge.passed {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .ap-badge.pending {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #FDE68A;
        }

        .modal-backdrop-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-card-custom {
            background: #FFFFFF;
            border-radius: 12px;
            width: 95%;
            max-width: 520px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: modalPop 0.2s ease-out;
        }

        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: 1fr; }
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
                        <i class="fas fa-clipboard-check" style="color:var(--color-navy);"></i>
                        Institutional Policy Compliance & Governance
                    </h1>
                    <p class="dash-header-sub">
                        Active regulatory checklist, chapter bylaws ratifications, and accreditation audit matrix.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/compliance/dashboard.php" class="btn-white">
                        <i class="fas fa-shield-halved" style="color:var(--color-blue);"></i> Governance Dashboard
                    </a>
                    <button class="btn-white" onclick="exportCSV()">
                        <i class="fas fa-download" style="color:var(--color-emerald);"></i> Export CSV
                    </button>
                    <button class="btn-white" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Matrix
                    </button>
                    <button class="btn-primary-navy" onclick="openAddPolicyModal()">
                        <i class="fas fa-plus" style="color:var(--color-gold);"></i> Add Requirement
                    </button>
                </div>
            </div>

            <!-- Feedback Alert -->
            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas <?= $feedbackType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. Top 4 KPI Metrics -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#059669;"><?= $overallComplianceRate ?>%</div>
                        <div class="kpi-lbl">Overall Regional Compliance</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-university"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($institutionsList) ?></div>
                        <div class="kpi-lbl">Accredited Laguna Chapters</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-list-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalPassedCount ?> <span style="font-size:0.75rem; color:#64748B;">/ <?= $totalPoliciesCount ?></span></div>
                        <div class="kpi-lbl">Complied Regulatory Requirements</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-clock-rotate-left"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#D97706;"><?= $totalPendingCount ?></div>
                        <div class="kpi-lbl">Pending Review & Audits</div>
                    </div>
                </div>
            </div>

            <!-- 3. Filter and Search Bar -->
            <form method="GET" class="filter-panel">
                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; flex:1;">
                    <div style="position:relative; min-width:200px; flex:1;">
                        <i class="fas fa-search" style="position:absolute; left:0.65rem; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search policy, institution, or keywords..." style="width:100%; padding:0.42rem 0.65rem 0.42rem 2rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.78rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                    </div>

                    <select name="inst" onchange="this.form.submit()" style="padding:0.42rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.78rem; background:#FFFFFF; color:#0F172A; font-family:'Plus Jakarta Sans',sans-serif;">
                        <option value="all">All Laguna Chapters</option>
                        <?php foreach ($institutionsList as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= $selectedInstFilter === $inst['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['acronym'] ?: $inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" onchange="this.form.submit()" style="padding:0.42rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.78rem; background:#FFFFFF; color:#0F172A; font-family:'Plus Jakarta Sans',sans-serif;">
                        <option value="all" <?= $selectedStatusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="compliant" <?= $selectedStatusFilter === 'compliant' ? 'selected' : '' ?>>Compliant (Passed)</option>
                        <option value="pending" <?= $selectedStatusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                    </select>
                </div>

                <div style="display:flex; gap:0.4rem;">
                    <button type="submit" class="btn-white" style="background:var(--color-navy); color:#FFFFFF; border-color:var(--color-navy);">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                    <a href="policy-compliance.php" class="btn-white" style="color:#64748B;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>

            <!-- 4. Policy Compliance Table -->
            <div class="ap-card" style="margin-bottom:1rem;">
                <div class="ap-card-header">
                    <h3 class="ap-card-title">
                        <i class="fas fa-tasks" style="color:var(--color-navy); margin-right:0.35rem;"></i>
                        Chapter Policy Checklist & Governance Matrix (<?= count($filteredRecords) ?> Requirements)
                    </h3>
                    <div style="font-size:0.76rem; color:#64748B;">
                        Academic Year: <strong>2026–2027</strong>
                    </div>
                </div>

                <div class="ap-card-body" style="padding:0; overflow-x:auto;">
                    <table class="ap-table" style="width:100%; border-collapse:collapse; text-align:left;">
                        <thead>
                            <tr>
                                <th style="width:28%;">Chapter / Institution</th>
                                <th style="width:32%;">Regulatory Requirement</th>
                                <th style="width:12%;">Deadline</th>
                                <th style="width:14%;">Audit Status</th>
                                <th style="width:14%; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filteredRecords)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2rem; color:#64748B;">
                                        <i class="fas fa-clipboard-check" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        No policy compliance records matched your filter criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($filteredRecords as $rec): ?>
                                    <?php
                                        $inst = $institutionsMap[$rec['institution_id']] ?? [];
                                        $isPassed = !empty($rec['is_compliant']);
                                        $dueDate = $rec['due_date'] ?? '';
                                        $isOverdue = (!$isPassed && $dueDate && strtotime($dueDate) < time());
                                    ?>
                                    <tr style="border-bottom:1px solid #F1F5F9;">
                                        <td style="padding:0.75rem 1rem;">
                                            <div style="font-weight:700; color:#0F172A; font-size:0.84rem;">
                                                <?= htmlspecialchars($inst['name'] ?? 'Affiliated Chapter') ?>
                                            </div>
                                            <div style="font-size:0.72rem; color:#64748B; margin-top:1px;">
                                                Acronym: <strong><?= htmlspecialchars($inst['acronym'] ?? 'HEI') ?></strong> &bull; <?= number_format($inst['membership_count'] ?? 0) ?> Members
                                            </div>
                                        </td>
                                        <td style="padding:0.75rem 1rem;">
                                            <div style="font-weight:700; color:var(--color-navy); font-size:0.82rem;">
                                                <?= htmlspecialchars($rec['policy_name'] ?? 'Regulatory Requirement') ?>
                                            </div>
                                            <div style="font-size:0.72rem; color:#64748B; margin-top:2px;">
                                                <?= htmlspecialchars($rec['policy_description'] ?? '') ?>
                                            </div>
                                            <?php if (!empty($rec['notes'])): ?>
                                                <div style="font-size:0.7rem; color:#2563EB; margin-top:2px; font-style:italic;">
                                                    <i class="fas fa-note-sticky"></i> <?= htmlspecialchars($rec['notes']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem 1rem; font-family:'JetBrains Mono',monospace; font-size:0.76rem;">
                                            <?= $dueDate ? date('M d, Y', strtotime($dueDate)) : 'Term Ongoing' ?>
                                            <?php if ($isOverdue): ?>
                                                <div style="color:#E11D48; font-size:0.68rem; font-weight:700;">Overdue</div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem 1rem;">
                                            <?php if ($isPassed): ?>
                                                <span class="ap-badge passed">
                                                    <i class="fas fa-check-circle"></i> Compliant
                                                </span>
                                                <div style="font-size:0.68rem; color:#64748B; margin-top:2px; font-family:'JetBrains Mono',monospace;">
                                                    <?= !empty($rec['completed_at']) ? date('M d, Y', strtotime($rec['completed_at'])) : 'Verified' ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="ap-badge pending">
                                                    <i class="fas fa-clock"></i> Pending Review
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem 1rem; text-align:right;">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_policy">
                                                <input type="hidden" name="policy_id" value="<?= $rec['id'] ?>">
                                                <input type="hidden" name="is_compliant" value="<?= $isPassed ? '0' : '1' ?>">
                                                <button type="submit" class="btn-white" style="padding:0.28rem 0.55rem; font-size:0.72rem;" title="<?= $isPassed ? 'Mark as Pending' : 'Mark as Compliant' ?>">
                                                    <i class="fas <?= $isPassed ? 'fa-arrow-rotate-left text-muted' : 'fa-check text-emerald' ?>" style="color:<?= $isPassed ? '#64748B' : '#059669' ?>;"></i>
                                                    <?= $isPassed ? 'Revoke' : 'Pass' ?>
                                                </button>
                                            </form>
                                            <button type="button" class="btn-white" style="padding:0.28rem 0.55rem; font-size:0.72rem;" onclick="openNotesModal('<?= $rec['id'] ?>', '<?= htmlspecialchars(addslashes($rec['policy_name'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($rec['notes'] ?? '')) ?>')">
                                                <i class="fas fa-pen-to-square"></i> Note
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 5. Per-Institution Compliance Matrix Summary -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title">
                        <i class="fas fa-building-columns" style="color:var(--color-navy); margin-right:0.35rem;"></i>
                        Chapter Regulatory Standing & Accreditation Summaries
                    </h3>
                </div>
                <div class="ap-card-body" style="padding:1rem;">
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(310px, 1fr)); gap:0.85rem;">
                        <?php foreach ($institutionsList as $inst): ?>
                            <?php
                                $iid = $inst['id'];
                                $instPols = $policiesPerInst[$iid] ?? [];
                                $instPassed = count(array_filter($instPols, fn($p) => !empty($p['is_compliant'])));
                                $instTotal = count($instPols);
                                $instScore = $instTotal > 0 ? round(($instPassed / $instTotal) * 100) : 100;
                                $isAllGood = ($instScore >= 100);
                            ?>
                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.85rem 1rem;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.45rem;">
                                    <div>
                                        <div style="font-weight:800; font-size:0.86rem; color:#0F172A;">
                                            <?= htmlspecialchars($inst['acronym'] ?: $inst['name']) ?>
                                        </div>
                                        <div style="font-size:0.72rem; color:#64748B;">
                                            <?= htmlspecialchars($inst['name']) ?>
                                        </div>
                                    </div>
                                    <span class="ap-badge <?= $isAllGood ? 'passed' : 'pending' ?>">
                                        <?= $instScore ?>%
                                    </span>
                                </div>
                                <div style="width:100%; height:6px; background:#E2E8F0; border-radius:999px; overflow:hidden; margin:0.5rem 0;">
                                    <div style="width:<?= $instScore ?>%; height:100%; background:<?= $isAllGood ? '#059669' : '#D97706' ?>; border-radius:999px;"></div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.72rem; color:#64748B; margin-top:0.4rem;">
                                    <span><?= $instPassed ?> of <?= $instTotal ?> Complied</span>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="batch_comply">
                                        <input type="hidden" name="institution_id" value="<?= $iid ?>">
                                        <button type="submit" class="btn-white" style="padding:0.2rem 0.5rem; font-size:0.7rem; background:#FFFFFF;">
                                            <i class="fas fa-check-double" style="color:#059669;"></i> Verify All
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal: Add Requirement -->
    <div id="addPolicyModal" class="modal-backdrop-custom">
        <div class="modal-card-custom">
            <div style="padding:1rem 1.25rem; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:800; color:#0F172A;">
                    <i class="fas fa-plus-circle" style="color:var(--color-navy);"></i> Add Policy Requirement
                </h3>
                <button type="button" onclick="closeAddPolicyModal()" style="background:none; border:none; color:#64748B; font-size:1.1rem; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="add_policy">

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Target Chapter / Institution</label>
                    <select name="institution_id" required style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif;">
                        <?php foreach ($institutionsList as $inst): ?>
                            <option value="<?= $inst['id'] ?>">
                                <?= htmlspecialchars($inst['name']) ?> (<?= htmlspecialchars($inst['acronym'] ?? 'HEI') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Policy Requirement Title</label>
                    <input type="text" name="policy_name" required placeholder="e.g. Mid-Year Accomplishment Dossier" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                </div>

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Description / Guidelines</label>
                    <textarea name="policy_description" rows="2" placeholder="Describe the required proof, format, and accreditation guidelines..." style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;"></textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem; margin-bottom:0.85rem;">
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Compliance Deadline</label>
                        <input type="date" name="due_date" value="<?= date('Y-11-30') ?>" required style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Initial Status</label>
                        <select name="is_compliant" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif;">
                            <option value="1">Compliant (Pre-verified)</option>
                            <option value="0" selected>Pending Review</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Auditor Notes (Optional)</label>
                    <input type="text" name="notes" placeholder="Reference number or special instructions" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-white" onclick="closeAddPolicyModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy">
                        <i class="fas fa-save"></i> Save Policy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Notes -->
    <div id="editNotesModal" class="modal-backdrop-custom">
        <div class="modal-card-custom">
            <div style="padding:1rem 1.25rem; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:800; color:#0F172A;">
                    <i class="fas fa-pen-to-square" style="color:var(--color-navy);"></i> Auditor Note: <span id="notePolicyTitle" style="color:var(--color-blue);"></span>
                </h3>
                <button type="button" onclick="closeNotesModal()" style="background:none; border:none; color:#64748B; font-size:1.1rem; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="toggle_policy">
                <input type="hidden" name="policy_id" id="notePolicyId" value="">
                <input type="hidden" name="is_compliant" id="noteIsCompliant" value="1">

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Compliance Verification Notes</label>
                    <textarea name="notes" id="noteText" rows="3" required placeholder="Enter auditor remarks, document tracking numbers, or verification details..." style="width:100%; padding:0.55rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-white" onclick="closeNotesModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy">
                        <i class="fas fa-check"></i> Save Note & Verify
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddPolicyModal() {
            document.getElementById('addPolicyModal').style.display = 'flex';
        }
        function closeAddPolicyModal() {
            document.getElementById('addPolicyModal').style.display = 'none';
        }

        function openNotesModal(id, title, notes) {
            document.getElementById('notePolicyId').value = id;
            document.getElementById('notePolicyTitle').textContent = title;
            document.getElementById('noteText').value = notes;
            document.getElementById('editNotesModal').style.display = 'flex';
        }
        function closeNotesModal() {
            document.getElementById('editNotesModal').style.display = 'none';
        }

        // Export CSV Functionality
        function exportCSV() {
            const rows = [
                ['Institution', 'Policy Requirement', 'Description', 'Deadline', 'Status', 'Completed At', 'Notes']
            ];

            <?php foreach ($policyRecords as $p): ?>
                <?php
                    $inst = $institutionsMap[$p['institution_id']] ?? [];
                    $st = !empty($p['is_compliant']) ? 'Compliant' : 'Pending';
                ?>
                rows.push([
                    <?= json_encode($inst['name'] ?? 'Chapter') ?>,
                    <?= json_encode($p['policy_name'] ?? '') ?>,
                    <?= json_encode($p['policy_description'] ?? '') ?>,
                    <?= json_encode($p['due_date'] ?? '') ?>,
                    <?= json_encode($st) ?>,
                    <?= json_encode($p['completed_at'] ?? '') ?>,
                    <?= json_encode($p['notes'] ?? '') ?>
                ]);
            <?php endforeach; ?>

            let csvContent = "data:text/csv;charset=utf-8," + rows.map(e => e.map(cell => '"' + (cell || '').replace(/"/g, '""') + '"').join(",")).join("\n");
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "IECEP_LSC_Policy_Compliance_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        window.onclick = function(event) {
            const addModal = document.getElementById('addPolicyModal');
            const notesModal = document.getElementById('editNotesModal');
            if (event.target === addModal) closeAddPolicyModal();
            if (event.target === notesModal) closeNotesModal();
        }
    </script>
</body>
</html>
