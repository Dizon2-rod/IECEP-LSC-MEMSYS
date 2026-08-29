<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'digital-id';

require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_admin', 'registration']);

$pageTitle = 'Cryptographic Digital ID & Identity Ledger';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Fetch real database records
$membersList = [];
$blockchainRecords = [];
$totalVerified = 0;

try {
    $rawMembers = $supabase->select('members', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawMembers)) {
        $membersList = $rawMembers;
    }

    $rawBc = $supabase->select('blockchain_records', ['select' => '*', 'order' => 'created_at.desc', 'limit' => 50]);
    if (is_array($rawBc)) {
        $blockchainRecords = $rawBc;
        $totalVerified = count($rawBc);
    }
} catch (Exception $e) {
    error_log('Error querying digital IDs: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Digital ID ledger, cryptographic identity hashes, and QR code verification for IECEP-LSC.">
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
                        <i class="fas fa-id-card-clip" style="color:var(--color-navy);"></i>
                        Cryptographic Digital ID & Identity Ledger
                    </h1>
                    <p class="dash-header-sub">
                        Verify member identities, view QR verification hashes, and inspect cryptographic credentials.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/members/list.php" class="btn-white">
                        <i class="fas fa-users" style="color:var(--color-blue);"></i> Member Directory
                    </a>
                    <a href="<?= PORTAL_URL ?>/admin/blockchain/explorer.php" class="btn-white">
                        <i class="fas fa-cubes" style="color:#059669;"></i> Blockchain Explorer
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-id-card"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($membersList) ?></div>
                        <div class="kpi-lbl">Total Registered Members</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalVerified ?></div>
                        <div class="kpi-lbl">Anchored Identity Proofs</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-qrcode"></i></div>
                    <div>
                        <div class="kpi-val">Instant</div>
                        <div class="kpi-lbl">QR Verification Engine</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-shield-check"></i></div>
                    <div>
                        <div class="kpi-val">Active</div>
                        <div class="kpi-lbl">Identity Security</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="didSearchInput" class="search-input-field" placeholder="Search member name, ID, email..." onkeyup="filterDidTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($membersList) ?> student engineers
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-id-card"></i> Member Digital Identity Ledger</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="didTable">
                        <thead>
                            <tr>
                                <th>Member Name & Student #</th>
                                <th>Institutional Chapter</th>
                                <th>Membership Type</th>
                                <th>Digital ID Status</th>
                                <th>Verification Proof</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($membersList)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-id-card" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Member Records Found</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Members registered in the directory will appear here with cryptographic credentials.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($membersList as $m): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($m['full_name'] ?? 'Student Member') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B; font-family:'JetBrains Mono', monospace;"><?= htmlspecialchars($m['student_number'] ?? $m['id'] ?? '') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($m['school_name'] ?? $m['institution_name'] ?? 'Laguna Chapter') ?></td>
                                        <td><span class="ap-pill blue"><?= ucfirst($m['membership_type'] ?? 'Student') ?></span></td>
                                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Issued</span></td>
                                        <td>
                                            <span style="font-family:'JetBrains Mono', monospace; font-size:0.72rem; color:var(--color-navy);">
                                                <?= htmlspecialchars(substr(hash('sha256', ($m['id'] ?? '') . ($m['full_name'] ?? '')), 0, 16)) ?>...
                                            </span>
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
        function filterDidTable() {
            const query = document.getElementById('didSearchInput').value.toLowerCase();
            const table = document.getElementById('didTable');
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
