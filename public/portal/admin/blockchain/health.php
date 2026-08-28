<?php
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin']);
require_once __DIR__ . '/../../../../includes/role-config.php';
require_once __DIR__ . '/../../../../bootstrap.php';

$current_page = 'health';

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

$recordTypes = [
    'membership', 'affiliation', 'document_hash', 'payment',
    'digital_id', 'event_attendance', 'transaction', 'compliance_attendance', 'certificate',
];

$blockchainStats = [];
foreach ($recordTypes as $type) {
    try {
        $records = $supabase->select('blockchain_records', [
            'entity_type' => 'eq.' . $type,
            'order' => 'created_at.desc',
            'limit' => 1,
        ]);
        $totalResult = $supabase->select('blockchain_records', ['entity_type' => 'eq.' . $type]);
        $total = count($totalResult);
        $lastRecord = !empty($records) ? $records[0] : null;
        $lastDate = $lastRecord['created_at'] ?? null;
        $blockchainStats[$type] = ['total' => $total, 'last_date' => $lastDate, 'last_record' => $lastRecord];
    } catch (Exception $e) {
        $blockchainStats[$type] = ['total' => 0, 'last_date' => null, 'error' => $e->getMessage()];
    }
}

$totalAllBlocks = array_sum(array_column($blockchainStats, 'total'));
$typesWithData = count(array_filter($blockchainStats, fn($s) => $s['total'] > 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blockchain Health — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Monitor and verify blockchain integrity across all record types for IECEP-LSC Laguna Student Chapter.">
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <style>
        .health-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.15rem;
            margin-bottom: 1.5rem;
        }
        .health-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 1.35rem 1.25rem;
            position: relative;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }
        .health-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3.5px;
        }
        .health-card.status-pending::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
        .health-card.status-valid::before { background: linear-gradient(90deg, #059669, #10B981); }
        .health-card.status-invalid::before { background: linear-gradient(90deg, #E11D48, #F43F5E); }
        .health-card.status-empty::before { background: linear-gradient(90deg, #94A3B8, #CBD5E1); }
        .health-card:hover { transform: translateY(-2px); box-shadow: var(--card-shadow-hover); }

        .health-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .health-type-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            background: var(--iecep-gold-bg);
            color: var(--iecep-gold);
        }
        .health-type-name {
            font-family: 'Times New Roman', Georgia, serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0.75rem 0 0.2rem;
            text-transform: capitalize;
        }
        .health-type-sub {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .health-meta-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid var(--border-light);
            font-size: 0.82rem;
        }
        .health-meta-row:last-of-type { border-bottom: none; }
        .health-meta-label { color: var(--text-muted); font-weight: 600; }
        .health-meta-value { color: var(--text-heading); font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 0.78rem; }
        .verify-result {
            display: none;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-top: 0.85rem;
        }
        .verify-result.success { background: var(--accent-emerald-bg); color: var(--accent-emerald); border: 1px solid rgba(5,150,105,0.3); }
        .verify-result.error { background: var(--accent-rose-bg); color: var(--accent-rose); border: 1px solid rgba(225,29,72,0.3); }
        @media (max-width: 1024px) { .health-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .health-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-link"></i> Blockchain Health Monitor</h1>
                    <p class="ap-page-subtitle">Verify SHA-256 chain integrity across all <?= count($recordTypes) ?> cryptographic record types. Total: <strong><?= $totalAllBlocks ?> blocks</strong>.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/blockchain/explorer.php" class="ap-btn-secondary">
                        <i class="fas fa-cube"></i> Open Explorer
                    </a>
                    <button class="ap-btn-primary" onclick="verifyAllChains()">
                        <i class="fas fa-shield-halved"></i> Verify All Chains
                    </button>
                </div>
            </div>

            <!-- Summary KPIs -->
            <div class="ap-kpi-grid-3" style="grid-template-columns: repeat(3, 1fr);">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon cyan"><i class="fas fa-database"></i></div>
                        <div><div class="ap-stat-label">Total</div><div class="ap-stat-sublabel">All Blocks</div></div>
                    </div>
                    <div class="ap-stat-value"><?= $totalAllBlocks ?></div>
                    <div class="ap-stat-footer">Across <?= count($recordTypes) ?> Record Types</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-layer-group"></i></div>
                        <div><div class="ap-stat-label">Active</div><div class="ap-stat-sublabel">Record Types</div></div>
                    </div>
                    <div class="ap-stat-value"><?= $typesWithData ?></div>
                    <div class="ap-stat-footer">Of <?= count($recordTypes) ?> Monitored Types</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-shield-check"></i></div>
                        <div><div class="ap-stat-label">Status</div><div class="ap-stat-sublabel">Chain Integrity</div></div>
                    </div>
                    <div class="ap-stat-value" id="globalIntegrityStatus">—</div>
                    <div class="ap-stat-footer">Run Audit to Verify</div>
                </div>
            </div>

            <!-- Health Cards Grid -->
            <div class="health-grid">
                <?php foreach ($blockchainStats as $type => $stats): ?>
                    <?php
                        $isEmpty = $stats['total'] === 0;
                        $statusClass = $isEmpty ? 'status-empty' : 'status-pending';
                        $typeLabel = ucwords(str_replace('_', ' ', $type));
                        $iconMap = [
                            'membership' => 'fa-id-card', 'affiliation' => 'fa-building-columns',
                            'document_hash' => 'fa-file-shield', 'payment' => 'fa-receipt',
                            'digital_id' => 'fa-id-badge', 'event_attendance' => 'fa-calendar-check',
                            'transaction' => 'fa-money-bill-transfer', 'compliance_attendance' => 'fa-clipboard-check',
                            'certificate' => 'fa-award'
                        ];
                        $icon = $iconMap[$type] ?? 'fa-link';
                    ?>
                    <div class="health-card <?= $statusClass ?>" id="card-<?= $type ?>">
                        <div class="health-card-top">
                            <div class="health-type-icon"><i class="fas <?= $icon ?>"></i></div>
                            <span class="ap-pill <?= $isEmpty ? 'inactive' : 'pending' ?>" id="badge-<?= $type ?>">
                                <span class="ap-pill-dot"></span>
                                <?= $isEmpty ? 'No Records' : 'Pending Audit' ?>
                            </span>
                        </div>
                        <div class="health-type-name"><?= $typeLabel ?></div>
                        <div class="health-type-sub">SHA-256 Hash Chain</div>

                        <div class="health-meta-row">
                            <span class="health-meta-label">Total Blocks</span>
                            <span class="health-meta-value"><?= $stats['total'] ?></span>
                        </div>
                        <div class="health-meta-row">
                            <span class="health-meta-label">Last Record</span>
                            <span class="health-meta-value"><?= $stats['last_date'] ? date('M d, Y H:i', strtotime($stats['last_date'])) : 'None' ?></span>
                        </div>

                        <button class="ap-btn-primary" style="width:100%; margin-top:1rem; justify-content:center;" onclick="verifyChain('<?= $type ?>')" <?= $isEmpty ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?> id="btn-<?= $type ?>">
                            <i class="fas fa-shield-alt"></i> Verify Chain
                        </button>

                        <div class="verify-result" id="result-<?= $type ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- About Section -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-info-circle"></i> How Blockchain Verification Works</h3>
                </div>
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:1rem;">
                    <div>
                        <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.75rem;">
                            The verification engine recomputes SHA-256 hashes across all chained records to detect any unauthorized modifications.
                        </p>
                        <ul style="font-size:0.82rem; color:var(--text-secondary); padding-left:1.25rem; line-height:1.85;">
                            <li>Recomputes SHA-256 hashes for all records of each type</li>
                            <li>Verifies stored hash vs recomputed hash matches exactly</li>
                            <li>Checks <code style="font-family:'JetBrains Mono'; font-size:0.78rem;">previous_hash</code> chain linking is intact</li>
                            <li>Identifies tampered or broken links in the cryptographic chain</li>
                        </ul>
                    </div>
                    <div>
                        <div class="ap-alert info">
                            <i class="fas fa-info-circle"></i>
                            <span>All records are protected with RSA-2048 signing and SHA-256 cryptographic chaining. Any single-byte modification to a record will immediately break the chain and be detected.</span>
                        </div>
                        <div class="ap-alert success">
                            <i class="fas fa-shield-halved"></i>
                            <span>Run a full audit periodically — especially after large batch imports or system upgrades — to confirm chain integrity.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-link"></i><span><strong>Algorithm:</strong> SHA-256 + RSA-2048</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-database"></i><span><strong>Total Blocks:</strong> <?= $totalAllBlocks ?></span></div>
                <div class="ap-sentinel-item"><i class="fas fa-clock"></i><span><strong>Checked:</strong> <?= date('M d, Y H:i') ?> UTC+8</span></div>
            </div>

        </div>
    </main>

    <script>
        async function verifyChain(recordType) {
            const btn = document.getElementById(`btn-${recordType}`);
            const resultDiv = document.getElementById(`result-${recordType}`);
            const badge = document.getElementById(`badge-${recordType}`);
            const card = document.getElementById(`card-${recordType}`);

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            resultDiv.style.display = 'none';

            try {
                const response = await fetch('/api/blockchain/verify-chain.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ entity_type: recordType })
                });
                const data = await response.json();

                if (data.valid) {
                    badge.className = 'ap-pill active';
                    badge.innerHTML = '<span class="ap-pill-dot"></span> Verified';
                    card.className = 'health-card status-valid';
                    resultDiv.className = 'verify-result success';
                    resultDiv.innerHTML = `<i class="fas fa-check-circle"></i> <strong>Chain Integrity Confirmed.</strong> ${data.total_records || 0} blocks verified successfully.`;
                } else {
                    badge.className = 'ap-pill danger';
                    badge.innerHTML = '<span class="ap-pill-dot"></span> Tampered';
                    card.className = 'health-card status-invalid';
                    resultDiv.className = 'verify-result error';
                    let tamperedHtml = `<i class="fas fa-exclamation-triangle"></i> <strong>Chain Compromised.</strong> ${data.tampered?.length || 0} tampered record(s) detected.<br><br>`;
                    if (data.tampered) {
                        data.tampered.forEach(r => {
                            tamperedHtml += `<div style="font-family:'JetBrains Mono',monospace; font-size:0.75rem; margin-top:4px;">ID: ${r.id} — Expected: ${r.expected_hash?.substring(0,12)}... vs Stored: ${r.stored_hash?.substring(0,12)}...</div>`;
                        });
                    }
                    resultDiv.innerHTML = tamperedHtml;
                }
            } catch (error) {
                resultDiv.className = 'verify-result error';
                resultDiv.innerHTML = `<i class="fas fa-times-circle"></i> <strong>Verification Error:</strong> ${error.message}`;
            } finally {
                resultDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shield-alt"></i> Re-Verify';
            }
        }

        async function verifyAllChains() {
            document.getElementById('globalIntegrityStatus').textContent = '...';
            const types = <?= json_encode(array_keys(array_filter($blockchainStats, fn($s) => $s['total'] > 0))) ?>;
            for (const type of types) {
                await verifyChain(type);
                await new Promise(r => setTimeout(r, 200));
            }
            document.getElementById('globalIntegrityStatus').textContent = '✓ Audited';
        }
    </script>
</body>
</html>
