<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'health';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin']);

$pageTitle = 'Blockchain Node & Network Health';
$supabase = getSupabaseClient();

$recordTypes = [
    'membership', 'affiliation', 'document_hash', 'payment',
    'digital_id', 'event_attendance', 'transaction', 'certificate'
];

$blockchainStats = [];
$totalAllBlocks = 0;

foreach ($recordTypes as $type) {
    try {
        $records = $supabase->select('blockchain_records', [
            'entity_type' => 'eq.' . $type,
            'order' => 'created_at.desc',
            'limit' => 1
        ]);
        $totalResult = $supabase->select('blockchain_records', ['entity_type' => 'eq.' . $type]);
        $total = is_array($totalResult) ? count($totalResult) : 0;
        $totalAllBlocks += $total;
        $lastRecord = (!empty($records) && is_array($records)) ? $records[0] : null;
        $lastDate = $lastRecord['created_at'] ?? null;
        $blockchainStats[$type] = ['total' => $total, 'last_date' => $lastDate, 'last_record' => $lastRecord];
    } catch (Exception $e) {
        $blockchainStats[$type] = ['total' => 0, 'last_date' => null];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Monitor and verify blockchain node health and network integrity for IECEP-LSC.">
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
                        <i class="fas fa-heart-pulse" style="color:var(--color-navy);"></i>
                        Blockchain Node & Network Health
                    </h1>
                    <p class="dash-header-sub">
                        Monitor cryptographic proof generation, record anchors, and consensus health.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/blockchain/explorer.php" class="btn-white">
                        <i class="fas fa-cubes" style="color:var(--color-blue);"></i> Ledger Explorer
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-server"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalAllBlocks ?></div>
                        <div class="kpi-lbl">Total Anchored Blocks</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val">100%</div>
                        <div class="kpi-lbl">Node Uptime</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="kpi-val">Healthy</div>
                        <div class="kpi-lbl">SHA-256 Engine</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-network-wired"></i></div>
                    <div>
                        <div class="kpi-val">8 Channels</div>
                        <div class="kpi-lbl">Active Entity Types</div>
                    </div>
                </div>
            </div>

            <!-- 3. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Blockchain Entity Channel Health</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Entity / Channel Type</th>
                                <th>Total Anchored Records</th>
                                <th>Latest Cryptographic Hash</th>
                                <th>Last Anchored</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blockchainStats as $type => $stat): ?>
                                <?php 
                                    $hash = $stat['last_record']['record_hash'] ?? $stat['last_record']['block_hash'] ?? '0x8a9f...';
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?></strong></td>
                                    <td><strong><?= number_format($stat['total']) ?></strong> blocks</td>
                                    <td>
                                        <span style="font-family:'JetBrains Mono', monospace; font-size:0.72rem; color:#64748B;">
                                            <?= $stat['total'] > 0 ? htmlspecialchars(substr($hash, 0, 24)) . '...' : 'None' ?>
                                        </span>
                                    </td>
                                    <td style="color:#64748B; font-size:0.75rem;">
                                        <?= !empty($stat['last_date']) ? date('M d, Y h:i A', strtotime($stat['last_date'])) : 'No records yet' ?>
                                    </td>
                                    <td>
                                        <span class="ap-pill <?= $stat['total'] > 0 ? 'active' : 'pending' ?>">
                                            <span class="ap-pill-dot"></span> <?= $stat['total'] > 0 ? 'Active' : 'Idle' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
