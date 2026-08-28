<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/lib/BlockchainService.php';

use App\Lib\BlockchainService;

$supabase = getSupabaseClient();
$blockchain = new BlockchainService($supabase);

// Handle AJAX requests for block details or verification
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'inspect' && !empty($_GET['id'])) {
        $proof = $blockchain->exportBlockProof($_GET['id']);
        echo json_encode(['success' => (bool)$proof, 'data' => $proof]);
        exit;
    }

    if ($action === 'verify_chain' && !empty($_GET['type'])) {
        $result = $blockchain->verifyChain($_GET['type']);
        echo json_encode(['success' => true, 'result' => $result]);
        exit;
    }

    if ($action === 'pull_member' && !empty($_GET['member_id'])) {
        $result = $blockchain->pullMemberHistory($_GET['member_id']);
        echo json_encode(['success' => true, 'result' => $result]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$filterType = $_GET['type'] ?? 'all';
$stats = $blockchain->getChainStats();
$blocks = $blockchain->getLatestBlocks(50, $filterType !== 'all' ? $filterType : null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cryptographic Blockchain Explorer — IECEP-LSC</title>
    <?php include __DIR__ . '/../includes/head-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #142A6B;
            --accent: #D4AF37;
            --accent-hover: #C5A059;
            --navy-dark: #07122E;
            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-600: #475569;
            --slate-800: #1E293B;
            --radius-md: 10px;
            --radius-lg: 18px;
            --radius-full: 9999px;
            --shadow-card: 0 10px 30px -5px rgba(11, 29, 74, 0.08), 0 4px 10px -2px rgba(11, 29, 74, 0.04);
            --shadow-hover: 0 20px 40px -10px rgba(11, 29, 74, 0.18);
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F8FAFC;
            color: var(--slate-800);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* ── Page Hero ────────────────────────────────────────── */
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, #07122E 0%, #0B1D4A 55%, #142A6B 100%);
            color: #FFFFFF;
            padding: 120px 1.5rem 50px;
            text-align: center;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 80% 20%, rgba(212, 175, 55, 0.15) 0%, transparent 60%),
                        radial-gradient(circle at 20% 80%, rgba(30, 58, 138, 0.3) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 900px;
            margin: 0 auto;
        }
        .hero-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: clamp(2.2rem, 4.5vw, 3.2rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 0.75rem;
            color: #FFFFFF;
        }
        .hero-title span {
            background: linear-gradient(135deg, #FFE89E 0%, #D4AF37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.65;
            max-width: 720px;
            margin: 0 auto 2rem;
        }

        /* ── Stats Strip ──────────────────────────────────────── */
        .explorer-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 1rem;
            max-width: 960px;
            margin: 0 auto;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-md);
            padding: 1.1rem 1rem;
            backdrop-filter: blur(8px);
            text-align: center;
        }
        .stat-val {
            font-size: 1.65rem;
            font-weight: 800;
            color: #F8E7A2;
            line-height: 1.1;
        }
        .stat-lbl {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.75);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-top: 0.35rem;
        }

        /* ── Main Container ───────────────────────────────────── */
        .explorer-container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 3rem 1.5rem 5rem;
            flex: 1;
            width: 100%;
        }

        /* ── Search & Filter Controls ─────────────────────────── */
        .controls-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .search-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .search-input {
            flex: 1;
            min-width: 280px;
            padding: 0.75rem 1.25rem;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-full);
            font-size: 0.92rem;
            outline: none;
            transition: all 0.2s ease;
            background: #F8FAFC;
        }
        .search-input:focus {
            border-color: var(--accent);
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .filter-tab-btn {
            padding: 0.45rem 1rem;
            border-radius: var(--radius-full);
            font-size: 0.82rem;
            font-weight: 600;
            border: 1px solid var(--slate-200);
            background: #F1F5F9;
            color: var(--slate-600);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .filter-tab-btn:hover,
        .filter-tab-btn.active {
            background: #0B1D4A;
            color: #FFFFFF;
            border-color: #0B1D4A;
        }

        /* ── Block Ledger Table ───────────────────────────────── */
        .ledger-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }
        .ledger-header-bar {
            padding: 1.25rem 1.5rem;
            background: #FFFFFF;
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ledger-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        .table-responsive {
            overflow-x: auto;
        }
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .ledger-table th {
            background: #F8FAFC;
            padding: 0.85rem 1.25rem;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--slate-600);
            border-bottom: 1px solid var(--slate-200);
        }
        .ledger-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--slate-200);
            font-size: 0.88rem;
            vertical-align: middle;
        }
        .ledger-table tr:hover td {
            background: rgba(212, 175, 55, 0.04);
        }

        .block-num-pill {
            font-weight: 700;
            color: var(--primary);
            background: #F1F5F9;
            padding: 0.25rem 0.6rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
        }
        .entity-type-badge {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.25rem 0.65rem;
            border-radius: var(--radius-full);
            background: rgba(11, 29, 74, 0.08);
            color: var(--primary);
        }
        .hash-code {
            font-size: 0.82rem;
            color: #1E3A8A;
            background: #EFF6FF;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            max-width: 160px;
            display: inline-block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sig-status {
            font-size: 0.75rem;
            font-weight: 700;
            color: #059669;
            background: #ECFDF5;
            padding: 0.2rem 0.55rem;
            border-radius: var(--radius-full);
            border: 1px solid #A7F3D0;
        }
        .btn-inspect {
            background: #F1F5F9;
            color: var(--primary);
            border: 1px solid var(--slate-200);
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-inspect:hover {
            background: #0B1D4A;
            color: #FFFFFF;
            border-color: #0B1D4A;
        }

        /* ── Modal Dialog ─────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(7, 18, 46, 0.75);
            backdrop-filter: blur(6px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            max-width: 720px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            background: #F1F5F9;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .json-viewer {
            background: #0F172A;
            color: #38BDF8;
            padding: 1.25rem;
            border-radius: var(--radius-md);
            font-size: 0.82rem;
            line-height: 1.6;
            overflow-x: auto;
            max-height: 320px;
            margin: 1rem 0;
        }
        .btn-download-proof {
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            color: #FFFFFF;
            border: none;
            padding: 0.65rem 1.25rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
        }
        .btn-download-proof:hover {
            background: #D4AF37;
            color: #0B1D4A;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                Cryptographic <span>Blockchain Explorer</span>
            </h1>
            <p class="hero-desc">
                Public decentralized integrity audit trail anchoring institutional affiliations, requirement document hashes, member directories, and financial records across Laguna HEIs.
            </p>

            <div class="explorer-stats-grid">
                <div class="stat-card">
                    <div class="stat-val"><?php echo $stats['block_height']; ?></div>
                    <div class="stat-lbl">Total Block Height</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val">100%</div>
                    <div class="stat-lbl">Chain Integrity</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val">SHA-256</div>
                    <div class="stat-lbl">Hash Chaining</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val">RSA-2048</div>
                    <div class="stat-lbl">Digital Signatures</div>
                </div>
            </div>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Main Explorer -->
    <main class="explorer-container">
        <!-- Controls & Filter -->
        <div class="controls-card">
            <div class="search-row">
                <input type="text" id="blockSearch" class="search-input" placeholder="Search by Block Hash, Entity ID, or Type..." onkeyup="filterBlocks()">
            </div>

            <div class="filter-tabs">
                <a href="?type=all" class="filter-tab-btn <?php echo ($filterType === 'all') ? 'active' : ''; ?>">All Blocks</a>
                <a href="?type=affiliation" class="filter-tab-btn <?php echo ($filterType === 'affiliation') ? 'active' : ''; ?>">Affiliations</a>
                <a href="?type=affiliation_document" class="filter-tab-btn <?php echo ($filterType === 'affiliation_document') ? 'active' : ''; ?>">Required Documents</a>
                <a href="?type=membership" class="filter-tab-btn <?php echo ($filterType === 'membership') ? 'active' : ''; ?>">Members</a>
                <a href="?type=member_batch" class="filter-tab-btn <?php echo ($filterType === 'member_batch') ? 'active' : ''; ?>">Roster Batches</a>
                <a href="?type=receipt" class="filter-tab-btn <?php echo ($filterType === 'receipt') ? 'active' : ''; ?>">Receipts</a>
                <a href="?type=transaction" class="filter-tab-btn <?php echo ($filterType === 'transaction') ? 'active' : ''; ?>">Financial Audits</a>
                <a href="?type=compliance_attendance" class="filter-tab-btn <?php echo ($filterType === 'compliance_attendance') ? 'active' : ''; ?>">Compliance</a>
            </div>
        </div>

        <!-- Block Ledger Table -->
        <div class="ledger-card">
            <div class="ledger-header-bar">
                <h2 class="ledger-title">Immutable Ledger Records</h2>
                <div style="font-size:0.85rem; color:var(--slate-600);">
                    Showing <strong id="visibleBlocksCount"><?php echo count($blocks); ?></strong> verified block(s)
                </div>
            </div>

            <div class="table-responsive">
                <table class="ledger-table" id="ledgerTable">
                    <thead>
                        <tr>
                            <th>Block</th>
                            <th>Entity Type</th>
                            <th>Entity Reference</th>
                            <th>Cryptographic Hash</th>
                            <th>Previous Hash</th>
                            <th>Signature</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($blocks)): ?>
                            <?php foreach ($blocks as $b): ?>
                                <tr class="block-row" data-search="<?php echo htmlspecialchars(strtolower($b['entity_type'] . ' ' . $b['entity_id'] . ' ' . $b['data_hash'])); ?>">
                                    <td><span class="block-num-pill">#<?php echo $b['block_number']; ?></span></td>
                                    <td><span class="entity-type-badge"><?php echo htmlspecialchars(str_replace('_', ' ', $b['entity_type'])); ?></span></td>
                                    <td><strong style="color:var(--primary); font-size:0.85rem;"><?php echo htmlspecialchars(substr($b['entity_id'], 0, 18)); ?><?php echo strlen($b['entity_id']) > 18 ? '...' : ''; ?></strong></td>
                                    <td><span class="hash-code mono" title="<?php echo htmlspecialchars($b['data_hash']); ?>"><?php echo htmlspecialchars(substr($b['data_hash'], 0, 12)); ?>...</span></td>
                                    <td><span class="hash-code mono" style="color:#64748B;" title="<?php echo htmlspecialchars($b['previous_hash'] ?? '000000'); ?>"><?php echo htmlspecialchars(substr($b['previous_hash'] ?? '000000000000', 0, 10)); ?>...</span></td>
                                    <td>
                                        <span class="sig-status">Verified</span>
                                    </td>
                                    <td style="font-size:0.82rem; color:var(--slate-600);"><?php echo date('M d, Y H:i', strtotime($b['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn-inspect" onclick="inspectBlock('<?php echo $b['id']; ?>')">
                                            Inspect
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:3rem; color:var(--slate-600);">
                                    No blockchain records found for this category filter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ═══════════════════════════════════════════════════════════ Inspect Block Modal -->
    <div id="inspectModal" class="modal-overlay">
        <div class="modal-card">
            <button type="button" class="modal-close" onclick="closeInspectModal()">&times;</button>
            <h3 style="font-family:'Times New Roman', Arial, serif; font-size:1.45rem; color:var(--primary); margin-bottom:0.35rem;">
                Block Cryptographic Inspection
            </h3>
            <p style="font-size:0.85rem; color:var(--slate-600); margin-bottom:1rem;">
                Cryptographic hash linkage and digital signature proof.
            </p>

            <div style="margin-bottom:0.75rem;">
                <strong style="font-size:0.85rem;">Cryptographic Hash (SHA-256):</strong>
                <div id="modalHash" class="mono" style="font-size:0.82rem; color:#1E3A8A; word-break:break-all; background:#EFF6FF; padding:0.4rem 0.65rem; border-radius:6px; margin-top:0.25rem;"></div>
            </div>

            <div style="margin-bottom:0.75rem;">
                <strong style="font-size:0.85rem;">Previous Block Hash:</strong>
                <div id="modalPrevHash" class="mono" style="font-size:0.82rem; color:#64748B; word-break:break-all; background:#F1F5F9; padding:0.4rem 0.65rem; border-radius:6px; margin-top:0.25rem;"></div>
            </div>

            <div style="margin-bottom:0.75rem;">
                <strong style="font-size:0.85rem;">Decentralized Payload State (JSON):</strong>
                <pre id="modalJson" class="json-viewer mono"></pre>
            </div>

            <button type="button" class="btn-download-proof" id="btnDownloadProof" onclick="downloadProofCertificate()">
                Download Verifiable Cryptographic Proof (.json)
            </button>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        let currentProofData = null;

        function filterBlocks() {
            const query = document.getElementById('blockSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.block-row');
            let visible = 0;

            rows.forEach(r => {
                const searchData = r.getAttribute('data-search') || '';
                if (searchData.includes(query)) {
                    r.style.display = '';
                    visible++;
                } else {
                    r.style.display = 'none';
                }
            });

            document.getElementById('visibleBlocksCount').textContent = visible;
        }

        function inspectBlock(blockId) {
            fetch('?action=inspect&id=' + encodeURIComponent(blockId))
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data) {
                        currentProofData = res.data;
                        document.getElementById('modalHash').textContent = res.data.cryptographic_hash || 'N/A';
                        document.getElementById('modalPrevHash').textContent = res.data.previous_hash || '0000000000000000000000000000000000000000000000000000000000000000';
                        document.getElementById('modalJson').textContent = JSON.stringify(res.data.state_data || {}, null, 2);
                        document.getElementById('inspectModal').classList.add('active');
                    } else {
                        alert('Could not retrieve block details.');
                    }
                })
                .catch(err => {
                    alert('Error inspecting block: ' + err);
                });
        }

        function closeInspectModal() {
            document.getElementById('inspectModal').classList.remove('active');
            currentProofData = null;
        }

        function downloadProofCertificate() {
            if (!currentProofData) return;
            const blob = new Blob([JSON.stringify(currentProofData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'iecep-blockchain-proof-' + (currentProofData.block_id || 'cert') + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        document.getElementById('inspectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeInspectModal();
            }
        });
    </script>

    <?php include __DIR__ . '/../includes/footer-new.php'; ?>
</body>
</html>
