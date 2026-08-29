<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'repository';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary', 'secretary', 'registration']);

$pageTitle = 'Document Repository';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Upload Document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_doc') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'governance');
        $description = trim($_POST['description'] ?? '');

        if (!empty($title)) {
            $timestamp = date('c');
            $docId = bin2hex(random_bytes(16));
            $fileUrl = '';
            $fileType = 'pdf';

            // Handle file upload if present
            if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 3) . '/storage/documents/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
                
                $orig = basename($_FILES['doc_file']['name']);
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $fileType = $ext;
                $safeName = 'DOC_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . $safeName;
                if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $dest)) {
                    $fileUrl = PUBLIC_URL . '/storage/documents/' . $safeName;
                }
            }

            try {
                $supabase->insert('documents', [[
                    'id' => $docId,
                    'title' => $title,
                    'description' => $description,
                    'category' => $category,
                    'file_type' => $fileType,
                    'file_url' => $fileUrl,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Document '{$title}' uploaded and recorded successfully!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Upload doc error: " . $e->getMessage());
                $feedbackMsg = "Error saving document: " . $e->getMessage();
                $feedbackType = 'warning';
            }
        }
    }
}

// Fetch real documents from database
$documents = [];
try {
    $rawDocs = $supabase->select('documents', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawDocs)) {
        $documents = $rawDocs;
    }
} catch (Exception $e) {
    error_log("Documents load error: " . $e->getMessage());
}

$govCount = 0;
$finCount = 0;
$evtCount = 0;
foreach ($documents as $d) {
    $cat = strtolower($d['category'] ?? 'governance');
    if ($cat === 'finance') $finCount++;
    elseif ($cat === 'events') $evtCount++;
    else $govCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Central document repository for IECEP-LSC Laguna Student Chapter.">
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
            max-width: 500px;
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
                        <i class="fas fa-folder-open" style="color:var(--color-navy);"></i>
                        Central Document Repository & Legal Archives
                    </h1>
                    <p class="dash-header-sub">
                        Constitutions, bylaws, board resolutions, chapter memoranda, and operational circulars.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/documents/memoranda.php" class="btn-white">
                        <i class="fas fa-scroll" style="color:var(--color-blue);"></i> Memoranda & Circulars
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openUploadModal()">
                        <i class="fas fa-cloud-arrow-up" style="color:#FDE047;"></i> Upload Document
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
                    <div class="kpi-icon-pill navy"><i class="fas fa-file-lines"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($documents) ?></div>
                        <div class="kpi-lbl">Total Archived Files</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-scale-balanced"></i></div>
                    <div>
                        <div class="kpi-val"><?= $govCount ?></div>
                        <div class="kpi-lbl">Governance & Bylaws</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div>
                        <div class="kpi-val"><?= $finCount ?></div>
                        <div class="kpi-lbl">Financial Reports</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-calendar-days"></i></div>
                    <div>
                        <div class="kpi-val"><?= $evtCount ?></div>
                        <div class="kpi-lbl">Event Briefs & Circulars</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="docSearchInput" class="search-input-field" placeholder="Search document title, description, category..." onkeyup="filterDocsTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($documents) ?> documents in database
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-folder-closed"></i> Archived Documents</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="docsTable">
                        <thead>
                            <tr>
                                <th>Document Title</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Date Archived</th>
                                <th style="text-align:right;">Access</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-folder-open" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Documents in Repository</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Upload Document" to add official constitutions, briefs, or circulars.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documents as $d): ?>
                                    <?php 
                                        $type = strtolower($d['file_type'] ?? 'pdf');
                                        $url = $d['file_url'] ?? '';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                                <i class="fas <?= ($type === 'pdf') ? 'fa-file-pdf' : 'fa-file-lines' ?>" style="color:<?= ($type === 'pdf') ? '#E11D48' : '#2563EB' ?>; font-size:1.1rem;"></i>
                                                <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($d['title'] ?? 'Document') ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ap-pill blue"><?= ucfirst($d['category'] ?? 'Governance') ?></span>
                                        </td>
                                        <td style="color:#64748B; font-size:0.78rem;">
                                            <?= htmlspecialchars($d['description'] ?? 'Official document') ?>
                                        </td>
                                        <td style="color:#64748B; font-size:0.75rem;">
                                            <?= !empty($d['created_at']) ? date('M d, Y', strtotime($d['created_at'])) : 'Recent' ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <?php if (!empty($url)): ?>
                                                <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.55rem;">
                                                    <i class="fas fa-download"></i> View / Download
                                                </a>
                                            <?php else: ?>
                                                <span style="font-size:0.72rem; color:#94A3B8;">On File</span>
                                            <?php endif; ?>
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

    <!-- Upload Modal -->
    <div id="uploadModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-cloud-arrow-up"></i> Upload Chapter Document</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeUploadModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" style="padding:1.25rem;">
                <input type="hidden" name="action" value="upload_doc">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Document Title</label>
                    <input type="text" name="title" class="ap-input" placeholder="e.g. IECEP-LSC Constitution & By-Laws AY 2026" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Category</label>
                    <select name="category" class="ap-input" style="font-size:0.8rem;">
                        <option value="governance">Governance & Bylaws</option>
                        <option value="finance">Financial Reports</option>
                        <option value="events">Event Briefs & Circulars</option>
                        <option value="membership">Membership Guidelines</option>
                    </select>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">File Attachment (.pdf, .docx, .xlsx)</label>
                    <input type="file" name="doc_file" class="ap-input" style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Description</label>
                    <textarea name="description" class="ap-input" rows="2" placeholder="Brief summary of this legal document..." style="font-size:0.8rem;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-save"></i> Save & Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('active');
        }
        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
        }

        function filterDocsTable() {
            const query = document.getElementById('docSearchInput').value.toLowerCase();
            const table = document.getElementById('docsTable');
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
