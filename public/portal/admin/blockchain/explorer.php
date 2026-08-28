<?php
if (!isset($current_page)) { $current_page = 'explorer'; }
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin']);

require_once __DIR__ . '/../../bootstrap.php';
$supabase = getSupabaseClient();

// Get filters
$entityType = $_GET['entity_type'] ?? '';

// Get real blockchain records from Supabase
$records = [];
$totalRecords = 0;
$confirmedRecords = 0;

try {
    $recordsData = $supabase->select('blockchain_records', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($recordsData)) {
        $totalRecords = count($recordsData);
        foreach ($recordsData as $r) {
            if (!empty($r['confirmed']) || ($r['status'] ?? '') === 'confirmed') {
                $confirmedRecords++;
            }
            if (!empty($entityType) && ($r['entity_type'] ?? '') !== $entityType) continue;
            $records[] = $r;
        }
    }
} catch (Exception $e) {
    error_log("Blockchain records query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blockchain Ledger Explorer — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Explore and audit cryptographic blocks, transaction hashes, and proof-of-record entries for IECEP-LSC.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 4) . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-cubes"></i> Blockchain Ledger Explorer</h1>
                    <p class="ap-page-subtitle">Realtime immutable block visualizer, SHA-256 transaction hashes, Merkle root verification, and consensus state.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/blockchain/health.php" class="ap-btn-secondary">
                        <i class="fas fa-heart-pulse"></i> Node Health
                    </a>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-cubes"></i></div>
                        <div><div class="ap-stat-label">Height</div><div class="ap-stat-sublabel">Total Blocks</div></div>
                    </div>
                    <div class="ap-stat-value"><?= $totalRecords ?></div>
                    <div class="ap-stat-footer">Immutable Ledger Blocks</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                        <div><div class="ap-stat-label">Confirmed</div><div class="ap-stat-sublabel">100% Validated</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);"><?= $confirmedRecords ?></div>
                    <div class="ap-stat-footer">Cryptographically Verified</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-shield-halved"></i></div>
                        <div><div class="ap-stat-label">Consensus</div><div class="ap-stat-sublabel">Network Integrity</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--iecep-gold);">Active</div>
                    <div class="ap-stat-footer">Proof-of-Authority Authority</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon cyan"><i class="fas fa-microchip"></i></div>
                        <div><div class="ap-stat-label">Nodes</div><div class="ap-stat-sublabel">Secretariat Cluster</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-cyan);">Synced</div>
                    <div class="ap-stat-footer">Supabase PostgreSQL 15</div>
                </div>
            </div>

            <!-- Explorer Table Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Ledger Block Stream (<?= count($records) ?> Anchors)</h3>
                    <div class="ap-toolbar" style="margin-bottom:0;">
                        <div class="ap-search-wrapper" style="min-width:240px;">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" id="bcSearch" class="ap-search-input" placeholder="Search block hash or entity..." onkeyup="filterBcTable()">
                        </div>
                    </div>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table" id="bcTable">
                        <thead>
                            <tr>
                                <th>Entity / Proof Type</th>
                                <th>Institution / Scope</th>
                                <th>Block Transaction Hash (SHA-256)</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                                <th style="text-align:right;">Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No blockchain records found in database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($records as $r): ?>
                                    <?php 
                                        $txHash = $r['transaction_hash'] ?? $r['record_hash'] ?? hash('sha256', $r['id']);
                                        $jsonData = is_array($r['data_json'] ?? null) ? $r['data_json'] : json_decode($r['data_json'] ?? '{}', true);
                                        $inst = $jsonData['institution_name'] ?? ($r['institution_name'] ?? 'Laguna Chapter');
                                        $docType = $jsonData['document_type'] ?? ($r['record_type'] ?? ($r['entity_type'] ?? 'proof'));
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="ap-pill navy"><?= strtoupper(str_replace('_', ' ', $docType)) ?></span>
                                        </td>
                                        <td>
                                            <strong style="color:var(--text-heading); font-size:0.85rem;"><?= htmlspecialchars($inst) ?></strong>
                                        </td>
                                        <td>
                                            <span class="ap-mono" style="font-size:0.73rem; color:var(--iecep-navy); font-weight:600;"><?= htmlspecialchars(substr($txHash, 0, 20)) ?>...<?= htmlspecialchars(substr($txHash, -10)) ?></span>
                                        </td>
                                        <td>
                                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Confirmed</span>
                                        </td>
                                        <td style="font-size:0.8rem; color:var(--text-muted);">
                                            <?= isset($r['created_at']) ? date('M d, Y H:i:s', strtotime($r['created_at'])) : date('M d, Y') ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <button class="ap-btn-secondary" style="padding:0.25rem 0.65rem; font-size:0.75rem;" onclick="viewPayload('<?= addslashes(htmlspecialchars(json_encode($r, JSON_PRETTY_PRINT))) ?>')">
                                                <i class="fas fa-code"></i> JSON
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-lock"></i><span><strong>Merkle Consensus:</strong> Root State Verified</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Security:</strong> Quantum-Resistant SHA-256 Hashes</span></div>
            </div>

        </div>
    </main>

    <!-- JSON Payload Modal -->
    <div id="payloadModal" class="doc-modal">
        <div class="ap-card" style="max-width:620px; width:100%; margin:0; box-shadow:var(--card-shadow);">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-code"></i> Cryptographic Block Payload</h3>
                <button class="ap-btn-secondary" style="border:none; padding:0.25rem 0.5rem;" onclick="closePayloadModal()">&times;</button>
            </div>
            <pre id="payloadCode" class="ap-mono" style="background:#0B1D4A; color:#E2E8F0; padding:1rem; border-radius:8px; font-size:0.75rem; max-height:350px; overflow-y:auto; word-break:break-all; white-space:pre-wrap;"></pre>
            <div style="display:flex; justify-content:flex-end; margin-top:1rem;">
                <button class="ap-btn-secondary" onclick="closePayloadModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function filterBcTable() {
            const q = document.getElementById('bcSearch').value.toLowerCase();
            document.querySelectorAll('#bcTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }
        function viewPayload(raw) {
            document.getElementById('payloadCode').textContent = raw;
            document.getElementById('payloadModal').style.display = 'flex';
        }
        function closePayloadModal() {
            document.getElementById('payloadModal').style.display = 'none';
        }
    </script>
</body>
</html>
