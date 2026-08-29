<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'announcements';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary', 'secretary', 'registration']);

$pageTitle = 'Chapter Announcements & Broadcasts';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_announcement') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $priority = trim($_POST['priority'] ?? 'Normal');
        $status = trim($_POST['status'] ?? 'published');

        if (!empty($title) && !empty($body)) {
            $timestamp = date('c');
            $annId = bin2hex(random_bytes(16));
            $imageUrl = '';

            // Handle Banner / Photo Upload
            if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/public/storage/announcements/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
                
                $orig = basename($_FILES['announcement_image']['name']);
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $safeName = 'ANN_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $uploadDir . $safeName;
                    if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $dest)) {
                        $imageUrl = '/IECEP-LSC-MEMSYS/public/storage/announcements/' . $safeName;
                    }
                }
            }

            try {
                $supabase->insert('announcements', [[
                    'id' => $annId,
                    'title' => $title,
                    'content' => $body,
                    'body' => $body,
                    'priority' => $priority,
                    'status' => $status,
                    'image_url' => $imageUrl,
                    'banner_url' => $imageUrl,
                    'is_public' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $supabase->insert('notifications', [[
                    'id' => bin2hex(random_bytes(16)),
                    'title' => $title,
                    'message' => substr($body, 0, 200),
                    'type' => 'announcement',
                    'created_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Announcement '{$title}' published successfully!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Announcement create error: " . $e->getMessage());
                $feedbackMsg = "Error publishing announcement: " . $e->getMessage();
                $feedbackType = 'warning';
            }
        }
    }
}

// Fetch real announcements from database
$announcements = [];
$publishedCount = 0;
$draftCount = 0;
$urgentCount = 0;

try {
    $rawAnn = $supabase->select('announcements', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawAnn)) {
        $announcements = $rawAnn;
        foreach ($announcements as $a) {
            $st = strtolower($a['status'] ?? 'published');
            if ($st === 'published' || $st === 'active') $publishedCount++;
            else $draftCount++;
            if (strtolower($a['priority'] ?? '') === 'urgent') $urgentCount++;
        }
    }
} catch (Exception $e) {
    error_log("Announcements query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage and publish chapter announcements for IECEP-LSC Laguna Student Chapter.">
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
                        <i class="fas fa-bullhorn" style="color:var(--color-navy);"></i>
                        Chapter Announcements & Broadcasts
                    </h1>
                    <p class="dash-header-sub">
                        Publish news, regional updates, and push notifications to all registered student chapter members.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/communication/newsletter.php" class="btn-white">
                        <i class="fas fa-newspaper" style="color:var(--color-blue);"></i> Newsletters
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openAnnModal()">
                        <i class="fas fa-plus" style="color:#FDE047;"></i> Compose Announcement
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
                    <div class="kpi-icon-pill navy"><i class="fas fa-message"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($announcements) ?></div>
                        <div class="kpi-lbl">Total Announcements</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $publishedCount ?></div>
                        <div class="kpi-lbl">Active Broadcasts</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="kpi-val"><?= $urgentCount ?></div>
                        <div class="kpi-lbl">Urgent Bulletins</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="kpi-val"><?= $draftCount ?></div>
                        <div class="kpi-lbl">Draft Messages</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="annSearchInput" class="search-input-field" placeholder="Search announcement title, content..." onkeyup="filterAnnouncementsTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($announcements) ?> announcements in database
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list"></i> Published Announcements</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="annTable">
                        <thead>
                            <tr>
                                <th>Announcement Particulars & Photo</th>
                                <th>Priority</th>
                                <th>Target Audience</th>
                                <th>Status</th>
                                <th>Date Published</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($announcements)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-bullhorn" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Announcements Published Yet</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Compose Announcement" to broadcast news to all chapters.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $a): ?>
                                    <?php 
                                        $st = strtolower($a['status'] ?? 'published');
                                        $pr = strtolower($a['priority'] ?? 'normal');
                                        $img = $a['image_url'] ?? ($a['banner_url'] ?? '');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                                <?php if (!empty($img)): ?>
                                                    <img src="<?= htmlspecialchars($img) ?>" alt="Banner" style="width:48px; height:36px; object-fit:cover; border-radius:6px; border:1px solid #E2E8F0; flex-shrink:0;">
                                                <?php else: ?>
                                                    <div style="width:48px; height:36px; border-radius:6px; background:#F1F5F9; border:1px solid #E2E8F0; display:flex; align-items:center; justify-content:center; color:#94A3B8; font-size:1rem; flex-shrink:0;">
                                                        <i class="fas fa-bullhorn"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($a['title'] ?? 'Announcement') ?></strong><br>
                                                    <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars(substr($a['content'] ?? ($a['body'] ?? ''), 0, 90)) ?>...</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ap-pill <?= ($pr === 'urgent' || $pr === 'high') ? 'danger' : 'blue' ?>">
                                                <?= ucfirst($pr) ?>
                                            </span>
                                        </td>
                                        <td><span style="color:#64748B; font-size:0.75rem;">All Laguna Chapters</span></td>
                                        <td>
                                            <span class="ap-pill <?= ($st === 'published' || $st === 'active') ? 'active' : 'pending' ?>">
                                                <?= ucfirst($st) ?>
                                            </span>
                                        </td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;"><?= !empty($a['created_at']) ? date('M d, Y', strtotime($a['created_at'])) : 'Recent' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Create Announcement Modal -->
    <div id="annModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-bullhorn"></i> Compose Announcement</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeAnnModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" style="padding:1.25rem;">
                <input type="hidden" name="action" value="create_announcement">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Announcement Title</label>
                    <input type="text" name="title" class="ap-input" placeholder="e.g. Schedule for General Assembly 2026" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Photo / Banner Image</label>
                    <input type="file" name="announcement_image" class="ap-input" accept="image/*" style="font-size:0.8rem;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Priority</label>
                        <select name="priority" class="ap-input" style="font-size:0.8rem;">
                            <option value="Normal">Normal</option>
                            <option value="Urgent">Urgent / High</option>
                        </select>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Publish Status</label>
                        <select name="status" class="ap-input" style="font-size:0.8rem;">
                            <option value="published">Publish Immediately</option>
                            <option value="draft">Save as Draft</option>
                        </select>
                    </div>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Announcement Body Content</label>
                    <textarea name="body" class="ap-input" rows="4" placeholder="Type your broadcast message to all chapter members..." required style="font-size:0.8rem;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeAnnModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-paper-plane"></i> Broadcast Message</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAnnModal() {
            document.getElementById('annModal').classList.add('active');
        }
        function closeAnnModal() {
            document.getElementById('annModal').classList.remove('active');
        }

        function filterAnnouncementsTable() {
            const query = document.getElementById('annSearchInput').value.toLowerCase();
            const table = document.getElementById('annTable');
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
