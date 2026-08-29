<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'memoranda';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary', 'secretary', 'registration']);

$pageTitle = 'Executive Memoranda & Circulars';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Issue Memorandum
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_memo') {
        $title = trim($_POST['title'] ?? '');
        $memoNo = trim($_POST['memo_number'] ?? ('MEMO-2026-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT)));
        $category = trim($_POST['category'] ?? 'Governance');
        $priority = trim($_POST['priority'] ?? 'Normal');
        $signatory = trim($_POST['signatory'] ?? 'Regional Governor & Executive Council');
        $content = trim($_POST['content'] ?? '');

        if (!empty($title)) {
            $timestamp = date('c');
            $memoId = bin2hex(random_bytes(16));

            try {
                $supabase->insert('memoranda', [[
                    'id' => $memoId,
                    'memo_number' => $memoNo,
                    'title' => $title,
                    'category' => $category,
                    'priority' => $priority,
                    'signatory' => $signatory,
                    'content' => $content,
                    'status' => 'published',
                    'issued_date' => date('Y-m-d'),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Memorandum '{$memoNo}' published successfully!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Create memo error: " . $e->getMessage());
                $feedbackMsg = "Error issuing memo: " . $e->getMessage();
                $feedbackType = 'warning';
            }
        }
    }
}

// Fetch real memoranda from database
$memoranda = [];
$totalCount = 0;
$publishedCount = 0;
$urgentCount = 0;

try {
    $rawMemos = $supabase->select('memoranda', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawMemos)) {
        $memoranda = $rawMemos;
        $totalCount = count($memoranda);
        foreach ($memoranda as $m) {
            $st = strtolower($m['status'] ?? 'published');
            $pr = strtolower($m['priority'] ?? 'normal');
            if ($st === 'published') $publishedCount++;
            if ($pr === 'high' || $pr === 'urgent') $urgentCount++;
        }
    }
} catch (Exception $e) {
    error_log("Memoranda query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Official executive circulars, policy directives, and memoranda registry for IECEP-LSC Laguna Student Chapter.">
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
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
            color: #FDE047 !important;
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

        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .doc-modal.active { display: flex; }
        .modal-inner-box {
            background: #FFFFFF;
            border-radius: 12px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
            border: 1px solid var(--border-color);
            overflow: hidden;
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
                        <i class="fas fa-file-signature" style="color:var(--color-navy);"></i>
                        Executive Memoranda & Directives
                    </h1>
                    <p class="dash-header-sub">
                        Official policy directives, executive orders, and administrative circulars for all affiliated Laguna chapters.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/documents/repository.php" class="btn-white">
                        <i class="fas fa-folder-open" style="color:var(--color-navy);"></i> Central Repository
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openMemoModal()">
                        <i class="fas fa-plus" style="color:#FDE047;"></i> Issue Memorandum
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-scroll"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalCount ?></div>
                        <div class="kpi-lbl">Total Memoranda Issued</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $publishedCount ?></div>
                        <div class="kpi-lbl">Published Directives</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="kpi-val"><?= $urgentCount ?></div>
                        <div class="kpi-lbl">High Priority / Urgent</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="kpi-val">AY 26-27</div>
                        <div class="kpi-lbl">Current Term Circulars</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="memoSearchInput" class="search-input-field" placeholder="Search memo number, subject, signatory..." onkeyup="filterMemosTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($memoranda) ?> memoranda in database
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-file-contract"></i> Official Memoranda Registry</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="memosTable">
                        <thead>
                            <tr>
                                <th>Memo Number</th>
                                <th>Subject & Directives</th>
                                <th>Category</th>
                                <th>Signatory Authority</th>
                                <th>Issued Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($memoranda)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-scroll" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Memoranda Issued Yet</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Issue Memorandum" to publish policy circulars for all chapters.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($memoranda as $m): ?>
                                    <tr>
                                        <td>
                                            <span style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; font-weight:800; color:var(--color-navy);">
                                                <?= htmlspecialchars($m['memo_number'] ?? 'MEMO-2026') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($m['title'] ?? 'Memorandum') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars(substr($m['content'] ?? '', 0, 80)) ?>...</span>
                                        </td>
                                        <td><span class="ap-pill blue"><?= htmlspecialchars($m['category'] ?? 'Governance') ?></span></td>
                                        <td style="color:#334155; font-size:0.78rem;"><strong><?= htmlspecialchars($m['signatory'] ?? 'Executive Council') ?></strong></td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;"><?= !empty($m['issued_date']) ? date('M d, Y', strtotime($m['issued_date'])) : 'Recent' ?></td>
                                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Published</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Create Memo Modal -->
    <div id="memoModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-scroll"></i> Issue Chapter Memorandum</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeMemoModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="create_memo">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Memorandum Title / Subject</label>
                    <input type="text" name="title" class="ap-input" placeholder="e.g. Mandatory Chapter Accreditation Compliance for AY 2026-2027" required style="font-size:0.8rem;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Category</label>
                        <select name="category" class="ap-input" style="font-size:0.8rem;">
                            <option value="Governance">Governance</option>
                            <option value="Finance">Finance & Dues</option>
                            <option value="Operations">Operations</option>
                            <option value="Elections">Elections & Appointments</option>
                        </select>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Priority Level</label>
                        <select name="priority" class="ap-input" style="font-size:0.8rem;">
                            <option value="Normal">Normal</option>
                            <option value="Urgent">Urgent / High</option>
                        </select>
                    </div>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Signatory Authority</label>
                    <input type="text" name="signatory" class="ap-input" value="Regional Governor & Executive Council" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Directives & Body Content</label>
                    <textarea name="content" class="ap-input" rows="3" placeholder="State the policy directives, compliance deadlines, and instructions..." required style="font-size:0.8rem;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeMemoModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-paper-plane"></i> Publish Memorandum</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openMemoModal() {
            document.getElementById('memoModal').classList.add('active');
        }
        function closeMemoModal() {
            document.getElementById('memoModal').classList.remove('active');
        }

        function filterMemosTable() {
            const query = document.getElementById('memoSearchInput').value.toLowerCase();
            const table = document.getElementById('memosTable');
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
