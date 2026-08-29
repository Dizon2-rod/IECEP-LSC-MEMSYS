<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'reports';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'registration', 'committee_registration', 'eb_president', 'auditor']);

$supabase = getSupabaseClient();

$institutions = [];
$memberCountMap = [];
$scorecards = [];

try {
    $instData = $supabase->select('institutions', ['select' => 'id, name, acronym, status, compliance_status, created_at', 'order' => 'name.asc']);
    if (is_array($instData)) $institutions = $instData;

    $membersData = $supabase->select('members', ['select' => 'id, institution_id']);
    if (is_array($membersData)) {
        foreach ($membersData as $m) {
            $iid = $m['institution_id'] ?? '';
            if ($iid) $memberCountMap[$iid] = ($memberCountMap[$iid] ?? 0) + 1;
        }
    }
} catch (Exception $e) {
    error_log("Compliance reports query error: " . $e->getMessage());
}

$compliantCount = 0;
$atRiskCount = 0;

foreach ($institutions as $inst) {
    $instId = $inst['id'];
    $mCount = $memberCountMap[$instId] ?? 0;
    $compStatus = strtolower($inst['compliance_status'] ?? 'compliant');
    
    // Compute score based on member roster size vs quota (20 members = 100%)
    $score = min(100, round(($mCount / 20) * 100, 1));
    
    if ($compStatus === 'at_risk' || $score < 80) {
        $statusLabel = 'At Risk';
        $pillClass = 'pending';
        $atRiskCount++;
    } else {
        $statusLabel = 'Compliant';
        $pillClass = 'active';
        $compliantCount++;
    }
    
    $scorecards[] = [
        'id' => $instId,
        'name' => $inst['name'] ?? 'Chapter',
        'acronym' => $inst['acronym'] ?? 'HEI',
        'status_label' => $statusLabel,
        'pill' => $pillClass,
        'member_count' => $mCount,
        'score' => $score
    ];
}

$totalInst = count($institutions);
$avgScore = $totalInst > 0 ? round(array_sum(array_column($scorecards, 'score')) / $totalInst, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Chapter Compliance Scorecard & Audits — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Official annual chapter compliance reports, scorecards, and institutional evaluation audit.">
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
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
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
        .ap-table tr:hover td {
            background: #F8FAFC;
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
                        <i class="fas fa-file-contract" style="color:var(--color-navy);"></i>
                        Institutional Compliance Scorecard & Audits
                    </h1>
                    <p class="dash-header-sub">
                        Annual institutional scorecards, minimum roster threshold evaluations, and governance auditing.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <button class="btn-white" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Scorecard
                    </button>
                    <a href="<?= PORTAL_URL ?>/admin/compliance/dashboard.php" class="btn-white">
                        <i class="fas fa-shield-halved" style="color:var(--color-navy);"></i> Compliance Dashboard
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-school"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalInst ?></div>
                        <div class="kpi-lbl">Evaluated Institutions</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $compliantCount ?></div>
                        <div class="kpi-lbl">Fully Compliant Chapters</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="kpi-val"><?= $atRiskCount ?></div>
                        <div class="kpi-lbl">At Risk Chapters</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-chart-simple"></i></div>
                    <div>
                        <div class="kpi-val"><?= $avgScore ?>%</div>
                        <div class="kpi-lbl">Average Chapter Score</div>
                    </div>
                </div>
            </div>

            <!-- 3. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-ranking-star"></i> Annual Chapter Performance Scorecard</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Institution Name</th>
                                <th>Acronym</th>
                                <th>Active Student Roster</th>
                                <th>Compliance Score</th>
                                <th>Status</th>
                                <th style="text-align:right;">Roster Audit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($scorecards)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-clipboard" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Chapter Audit Records</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Chapter records will be calculated automatically when affiliations are approved.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($scorecards as $row): ?>
                                    <tr>
                                        <td><strong style="color:#0F172A;"><?= htmlspecialchars($row['name']) ?></strong></td>
                                        <td><span style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; font-weight:700; color:var(--color-navy);"><?= htmlspecialchars($row['acronym']) ?></span></td>
                                        <td><strong><?= number_format($row['member_count']) ?></strong> Students (Quota: 20)</td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                                <div style="flex:1; max-width:80px; height:6px; background:#E2E8F0; border-radius:3px; overflow:hidden;">
                                                    <div style="width:<?= $row['score'] ?>%; height:100%; background:<?= $row['score'] >= 80 ? '#059669' : '#D97706' ?>;"></div>
                                                </div>
                                                <span style="font-weight:700; font-size:0.75rem;"><?= $row['score'] ?>%</span>
                                            </div>
                                        </td>
                                        <td><span class="ap-pill <?= $row['pill'] ?>"><?= htmlspecialchars($row['status_label']) ?></span></td>
                                        <td style="text-align:right;">
                                            <a href="<?= PORTAL_URL ?>/admin/members/list.php?school=<?= urlencode($row['id']) ?>" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.55rem;">
                                                <i class="fas fa-users" style="color:var(--color-navy);"></i> View Members
                                            </a>
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
</body>
</html>
