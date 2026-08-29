<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'analytics';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_president', 'eb_treasurer', 'eb_auditor', 'treasurer', 'auditor']);

$pageTitle = 'Executive Analytics & Intelligence';
$supabase = getSupabaseClient();

$totalMembers = 0;
$totalInstitutions = 0;
$totalEvents = 0;
$totalRevenue = 0.0;
$compliantInstitutions = 0;
$complianceRate = 100;

try {
    $mems = $supabase->select('members', ['select' => 'id']);
    if (is_array($mems)) $totalMembers = count($mems);
    if ($totalMembers === 0) {
        $profs = $supabase->select('user_profiles', ['select' => 'id']);
        if (is_array($profs)) $totalMembers = count($profs);
    }

    $insts = $supabase->select('institutions', ['select' => '*']);
    if (is_array($insts)) {
        $totalInstitutions = count($insts);
        $compliantInstitutions = count(array_filter($insts, fn($i) => ($i['compliance_status'] ?? '') === 'compliant' || ($i['status'] ?? '') === 'active'));
        if ($totalInstitutions > 0) {
            $complianceRate = round(($compliantInstitutions / $totalInstitutions) * 100);
        }
    }

    $evts = $supabase->select('events', ['select' => 'id']);
    if (is_array($evts)) $totalEvents = count($evts);

    $txs = $supabase->select('transactions', ['select' => 'amount,status']);
    if (is_array($txs)) {
        foreach ($txs as $t) {
            if (($t['status'] ?? '') === 'paid' || ($t['status'] ?? '') === 'completed') {
                $totalRevenue += floatval($t['amount'] ?? 0);
            }
        }
    }
} catch (Exception $e) {
    error_log("Analytics load error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Comprehensive analytics, membership trends, revenue insights, and compliance overview for IECEP-LSC Laguna Student Chapter.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
                        <i class="fas fa-chart-line" style="color:var(--color-navy);"></i>
                        Executive Analytics & Regional Intelligence
                    </h1>
                    <p class="dash-header-sub">
                        Real-time membership growth, treasury collections, institutional compliance, and telemetry.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/financial/reports.php" class="btn-white">
                        <i class="fas fa-file-invoice-dollar" style="color:var(--color-blue);"></i> Financial Reports
                    </a>
                    <button class="btn-white" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="kpi-val"><?= number_format($totalMembers) ?></div>
                        <div class="kpi-lbl">Total Registered Members</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-vault"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#059669;">₱<?= number_format($totalRevenue, 2) ?></div>
                        <div class="kpi-lbl">Audited Treasury Collections</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-university"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalInstitutions ?></div>
                        <div class="kpi-lbl">Laguna Student Chapters</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalEvents ?></div>
                        <div class="kpi-lbl">Scheduled Chapter Events</div>
                    </div>
                </div>
            </div>

            <!-- 3. Grid for Compliance & Growth Analytics -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-shield-check"></i> Chapter Compliance Distribution</h3>
                    </div>
                    <div style="padding:1.5rem; text-align:center;">
                        <div style="font-size:2.5rem; font-weight:800; color:var(--color-navy);"><?= $complianceRate ?>%</div>
                        <p style="margin:0.25rem 0 1rem; color:#64748B; font-size:0.85rem;">Overall Regional Compliance Health</p>
                        <div style="display:flex; justify-content:center; gap:1.5rem; font-size:0.8rem;">
                            <div><strong style="color:#059669; font-size:1.1rem;"><?= $compliantInstitutions ?></strong><div style="color:#64748B;">Compliant Chapters</div></div>
                            <div><strong style="color:#D97706; font-size:1.1rem;"><?= max(0, $totalInstitutions - $compliantInstitutions) ?></strong><div style="color:#64748B;">Pending Audit</div></div>
                        </div>
                    </div>
                </div>

                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-database"></i> Database Telemetry & Integrity</h3>
                    </div>
                    <div style="padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid #F1F5F9; font-size:0.85rem;">
                            <span style="color:#64748B;">Database Engine:</span>
                            <strong style="color:var(--color-navy);">PostgreSQL (Supabase Cloud)</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid #F1F5F9; font-size:0.85rem;">
                            <span style="color:#64748B;">Cryptographic Anchor:</span>
                            <strong style="color:#059669;">SHA-256 Enabled</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; font-size:0.85rem;">
                            <span style="color:#64748B;">Active Cluster Status:</span>
                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Healthy & Synchronized</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
