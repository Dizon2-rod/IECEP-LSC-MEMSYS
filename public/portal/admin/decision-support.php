<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'decision-support';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_officer', 'eb_president']);

$pageTitle = 'Executive Decision Support & Strategy';
$supabase = getSupabaseClient();

$totalCollected = 0.0;
$totalPending = 0.0;
$collectionRate = 100.0;
$totalInstitutions = 0;
$atRiskCount = 0;

try {
    $txs = $supabase->select('transactions', ['select' => '*']);
    if (is_array($txs) && !empty($txs)) {
        foreach ($txs as $t) {
            $amt = floatval($t['amount'] ?? 0);
            $st = strtolower($t['status'] ?? 'pending');
            if ($st === 'completed' || $st === 'paid') {
                $totalCollected += $amt;
            } else {
                $totalPending += $amt;
            }
        }
        if ($totalCollected + $totalPending > 0) {
            $collectionRate = round(($totalCollected / ($totalCollected + $totalPending)) * 100, 1);
        }
    }

    $insts = $supabase->select('institutions', ['select' => '*']);
    if (is_array($insts)) {
        $totalInstitutions = count($insts);
        $atRiskCount = count(array_filter($insts, fn($i) => ($i['compliance_status'] ?? '') === 'at_risk'));
    }
} catch (Exception $e) {
    error_log("Decision support query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Predictive chapter metrics, compliance risk forecasting, and strategic decision support for IECEP-LSC executive officers.">
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
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
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
                        <i class="fas fa-brain" style="color:var(--color-navy);"></i>
                        Executive Decision Support & Strategic Forecasts
                    </h1>
                    <p class="dash-header-sub">
                        Algorithmic risk forecasting, institutional compliance radar, and membership growth models.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/compliance/dashboard.php" class="btn-white">
                        <i class="fas fa-clipboard-check" style="color:var(--color-blue);"></i> Compliance Monitor
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-vault"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#059669;">₱<?= number_format($totalCollected, 2) ?></div>
                        <div class="kpi-lbl">Collected Treasury Remittance</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <div class="kpi-val"><?= $collectionRate ?>%</div>
                        <div class="kpi-lbl">Collection Efficiency</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-university"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalInstitutions ?></div>
                        <div class="kpi-lbl">Active Chapter Institutions</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="kpi-val" style="color:<?= $atRiskCount > 0 ? '#D97706' : '#059669' ?>;"><?= $atRiskCount ?></div>
                        <div class="kpi-lbl">Chapters Requiring Audit</div>
                    </div>
                </div>
            </div>

            <!-- 3. Intelligence Briefing Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-compass"></i> Strategic Chapter Recommendations</h3>
                </div>
                <div style="padding:1.25rem;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:1rem;">
                            <strong style="color:var(--color-navy); font-size:0.88rem;"><i class="fas fa-lightbulb" style="color:#D4AF37;"></i> Membership Expansion</strong>
                            <p style="font-size:0.8rem; color:#64748B; margin:0.35rem 0 0;">Prioritize outreach for non-compliant school chapters to submit their rosters before midterm accreditation deadlines.</p>
                        </div>
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:1rem;">
                            <strong style="color:var(--color-navy); font-size:0.88rem;"><i class="fas fa-shield-halved" style="color:#059669;"></i> Treasury Assurance</strong>
                            <p style="font-size:0.8rem; color:#64748B; margin:0.35rem 0 0;">Maintain 100% cryptographic SHA-256 block receipts for every chapter remittance to ensure seamless year-end auditing.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
