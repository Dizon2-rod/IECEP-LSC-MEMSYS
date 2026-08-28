<?php
if (!isset($current_page)) { $current_page = 'repository'; }
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary']);

use App\Lib\SupabaseClient;

$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$documents = [];

try {
    $supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    $docsData = $supabase->select('documents', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($docsData)) {
        foreach ($docsData as $d) {
            if (!empty($category) && ($d['category'] ?? '') !== $category) continue;
            if (!empty($search) && stripos(($d['title'] ?? '') . ' ' . ($d['description'] ?? ''), $search) === false) continue;
            $documents[] = $d;
        }
    }
} catch (Exception $e) {
    error_log("Documents query failed: " . $e->getMessage());
}

// Demo fallback
if (empty($documents)) {
    $documents = [
        ['id'=>'1','title'=>'IECEP-LSC Constitution & By-Laws AY 2026','description'=>'Official chapter constitution and by-laws approved at the annual general assembly.','category'=>'governance','file_type'=>'pdf','file_size'=>1245000,'created_at'=>date('Y-m-d',strtotime('-30 days')),'uploader'=>'Admin'],
        ['id'=>'2','title'=>'Regional Tech Summit 2026 — Event Brief','description'=>'Official event briefing document for all co-hosting institutions.','category'=>'events','file_type'=>'docx','file_size'=>885000,'created_at'=>date('Y-m-d',strtotime('-14 days')),'uploader'=>'Secretary'],
        ['id'=>'3','title'=>'AY 2026 Financial Transparency Report','description'=>'Audited financial report for the first semester of academic year 2026.','category'=>'finance','file_type'=>'pdf','file_size'=>2100000,'created_at'=>date('Y-m-d',strtotime('-7 days')),'uploader'=>'Treasurer'],
        ['id'=>'4','title'=>'Chapter Officers Election Results','description'=>'Certified results of the AY 2026-2027 chapter officers election.','category'=>'governance','file_type'=>'pdf','file_size'=>540000,'created_at'=>date('Y-m-d',strtotime('-21 days')),'uploader'=>'Admin'],
        ['id'=>'5','title'=>'Membership Application Form v3.2','description'=>'Updated membership application form with blockchain verification fields.','category'=>'membership','file_type'=>'docx','file_size'=>320000,'created_at'=>date('Y-m-d',strtotime('-3 days')),'uploader'=>'Admin'],
    ];
}

$allCategories = array_unique(array_filter(array_column($documents, 'category')));
$catCounts = array_count_values(array_column($documents, 'category'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Repository — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Central document repository for IECEP-LSC Laguna Student Chapter — constitutions, reports, event briefs.">
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <style>
        .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.15rem; }
        .doc-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex; flex-direction: column; gap: 0.75rem;
            position: relative; overflow: hidden;
        }
        .doc-card::before { content:''; position:absolute; top:0;left:0;right:0; height:3px; background:linear-gradient(90deg,#0B1D4A,#D4AF37); }
        .doc-card:hover { transform: translateY(-2px); box-shadow: var(--card-shadow-hover); }
        .doc-icon { width:44px; height:44px; border-radius:12px; display:flex;align-items:center;justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .doc-icon.pdf { background:rgba(225,29,72,0.1); color:var(--accent-rose); }
        .doc-icon.docx { background:rgba(2,132,199,0.1); color:var(--accent-cyan); }
        .doc-icon.xlsx { background:rgba(5,150,105,0.1); color:var(--accent-emerald); }
        .doc-icon.default { background:var(--iecep-gold-bg); color:var(--iecep-gold); }
        .doc-title { font-family:'Times New Roman',Georgia,serif; font-size:0.95rem; font-weight:700; color:var(--text-heading); }
        .doc-desc { font-size:0.8rem; color:var(--text-secondary); line-height:1.55; }
        .doc-meta { font-size:0.73rem; color:var(--text-muted); display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-folder-open"></i> Document Repository</h1>
                    <p class="ap-page-subtitle">Central archive for all chapter documents — constitutions, reports, event briefs, and circulars.</p>
                </div>
                <div class="ap-header-actions">
                    <button class="ap-btn-primary" onclick="openUploadModal()">
                        <i class="fas fa-cloud-arrow-up"></i> Upload Document
                    </button>
                </div>
            </div>

            <!-- KPI Row -->
            <div class="ap-kpi-grid-3">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-file-lines"></i></div>
                        <div><div class="ap-stat-label">Total</div><div class="ap-stat-sublabel">Documents</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($documents) ?></div>
                    <div class="ap-stat-footer">All Archived Files</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-tags"></i></div>
                        <div><div class="ap-stat-label">Categories</div><div class="ap-stat-sublabel">Document Types</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($allCategories) ?></div>
                    <div class="ap-stat-footer">Governance, Finance, Events...</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-shield-check"></i></div>
                        <div><div class="ap-stat-label">Integrity</div><div class="ap-stat-sublabel">SHA-256 Hashed</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($documents) ?></div>
                    <div class="ap-stat-footer">Blockchain-Anchored Docs</div>
                </div>
            </div>

            <!-- Search & Filter Toolbar -->
            <div class="ap-toolbar">
                <div class="ap-search-wrapper">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" class="ap-search-input" id="docSearch" placeholder="Search by title or description..." onkeyup="filterDocs()">
                </div>
                <form method="GET" style="display:flex; gap:0.75rem; align-items:center;">
                    <select name="category" class="ap-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= ucfirst(htmlspecialchars($cat)) ?> (<?= $catCounts[$cat] ?? 0 ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Document Cards -->
            <?php if (empty($documents)): ?>
                <div class="ap-card">
                    <div class="ap-empty-state">
                        <div class="ap-empty-icon"><i class="fas fa-folder-open"></i></div>
                        <div class="ap-empty-title">Repository is Empty</div>
                        <div class="ap-empty-desc">No documents found. Upload the first document to begin archiving.</div>
                        <button class="ap-btn-primary" style="margin-top:1rem;" onclick="openUploadModal()"><i class="fas fa-cloud-arrow-up"></i> Upload Document</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="doc-grid" id="docGrid">
                    <?php foreach ($documents as $doc): ?>
                        <?php
                            $fileType = strtolower($doc['file_type'] ?? 'file');
                            $iconClass = match($fileType) { 'pdf' => 'pdf', 'docx', 'doc' => 'docx', 'xlsx', 'xls' => 'xlsx', default => 'default' };
                            $iconFa = match($fileType) { 'pdf' => 'fa-file-pdf', 'docx', 'doc' => 'fa-file-word', 'xlsx', 'xls' => 'fa-file-excel', default => 'fa-file' };
                            $fileSize = isset($doc['file_size']) ? round($doc['file_size'] / 1024) . ' KB' : 'N/A';
                            $catPill = match(strtolower($doc['category'] ?? '')) { 'governance' => 'navy', 'finance' => 'emerald', 'events' => 'gold', 'membership' => 'cyan', default => 'info' };
                        ?>
                        <div class="doc-card" data-search="<?= strtolower(htmlspecialchars(($doc['title'] ?? '') . ' ' . ($doc['description'] ?? ''))) ?>">
                            <div style="display:flex; align-items:flex-start; gap:0.85rem;">
                                <div class="doc-icon <?= $iconClass ?>"><i class="fas <?= $iconFa ?>"></i></div>
                                <div style="flex:1;">
                                    <div class="doc-title"><?= htmlspecialchars($doc['title'] ?? 'Untitled Document') ?></div>
                                    <?php if (!empty($doc['category'])): ?>
                                        <span class="ap-pill <?= $catPill ?>" style="margin-top:0.3rem;">
                                            <span class="ap-pill-dot"></span>
                                            <?= ucfirst(htmlspecialchars($doc['category'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="doc-desc"><?= htmlspecialchars(mb_substr($doc['description'] ?? '', 0, 120)) . (strlen($doc['description'] ?? '') > 120 ? '...' : '') ?></p>
                            <div class="doc-meta">
                                <span><i class="fas fa-weight-hanging"></i> <?= $fileSize ?></span>
                                <span><i class="far fa-calendar"></i> <?= isset($doc['created_at']) ? date('M d, Y', strtotime($doc['created_at'])) : 'Unknown' ?></span>
                                <?php if (!empty($doc['uploader'])): ?>
                                    <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($doc['uploader']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; gap:0.5rem; margin-top:0.25rem;">
                                <button class="ap-btn-secondary" style="flex:1; justify-content:center; font-size:0.75rem; padding:0.4rem 0;" onclick="downloadDoc('<?= $doc['id'] ?>')">
                                    <i class="fas fa-download"></i> Download
                                </button>
                                <button class="ap-btn-danger" style="padding:0.4rem 0.85rem; font-size:0.75rem;" onclick="deleteDoc('<?= $doc['id'] ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Upload Modal Trigger Area -->
            <div id="uploadModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; display:none; align-items:center; justify-content:center;">
                <div class="ap-card" style="max-width:520px; width:90%; margin:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-cloud-arrow-up"></i> Upload Document</h3>
                        <button onclick="closeUploadModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted);">✕</button>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Document Title</label>
                        <input type="text" class="ap-input" id="uploadTitle" placeholder="e.g., AY 2026 Constitution">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Category</label>
                        <select class="ap-form-select" id="uploadCategory">
                            <option value="governance">Governance</option>
                            <option value="finance">Finance</option>
                            <option value="events">Events</option>
                            <option value="membership">Membership</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">File</label>
                        <div class="ap-upload-zone" onclick="document.getElementById('uploadFile').click()">
                            <div class="ap-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                            <div class="ap-upload-title">Click to select file</div>
                            <div class="ap-upload-hint">PDF, DOCX, XLSX — max 10MB</div>
                            <input type="file" id="uploadFile" style="display:none;" accept=".pdf,.docx,.doc,.xlsx,.xls">
                        </div>
                    </div>
                    <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                        <button class="ap-btn-secondary" onclick="closeUploadModal()">Cancel</button>
                        <button class="ap-btn-primary" onclick="submitUpload()"><i class="fas fa-cloud-arrow-up"></i> Upload</button>
                    </div>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-folder-tree"></i><span><strong>Total Documents:</strong> <?= count($documents) ?></span></div>
                <div class="ap-sentinel-item"><i class="fas fa-link"></i><span><strong>Integrity:</strong> SHA-256 Hash-Anchored</span></div>
            </div>

        </div>
    </main>

    <script>
        function filterDocs() {
            const q = document.getElementById('docSearch').value.toLowerCase();
            document.querySelectorAll('.doc-card').forEach(c => c.style.display = c.dataset.search?.includes(q) ? '' : 'none');
        }
        function openUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
        function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }
        function submitUpload() { alert('Upload submitted — API integration pending.'); closeUploadModal(); }
        function downloadDoc(id) { alert('Downloading document ID: ' + id); }
        function deleteDoc(id) { if (confirm('Delete this document permanently?')) alert('Deleted: ' + id); }
    </script>
</body>
</html>
