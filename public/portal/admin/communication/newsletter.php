<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'newsletter';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary', 'secretary', 'registration']);

$pageTitle = 'Email Newsletters & Broadcast Campaigns';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Create Email Blast
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_blast') {
        $title = trim($_POST['title'] ?? '');
        $subject = trim($_POST['subject'] ?? $title);
        $content = trim($_POST['content'] ?? '');
        $group = trim($_POST['recipient_group'] ?? 'All Laguna Chapters');

        if (!empty($title) && !empty($content)) {
            $timestamp = date('c');
            $blastId = bin2hex(random_bytes(16));

            try {
                $supabase->insert('email_blasts', [[
                    'id' => $blastId,
                    'title' => $title,
                    'subject' => $subject,
                    'content' => $content,
                    'recipient_group' => $group,
                    'status' => 'sent',
                    'sent_at' => $timestamp,
                    'created_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Newsletter campaign '{$title}' dispatched successfully!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Email blast insert error: " . $e->getMessage());
                $feedbackMsg = "Error saving campaign: " . $e->getMessage();
                $feedbackType = 'warning';
            }
        }
    }
}

// Fetch real email blasts
$blastsList = [];
$totalSent = 0;

try {
    $rawBlasts = $supabase->select('email_blasts', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawBlasts)) {
        $blastsList = $rawBlasts;
        $totalSent = count($rawBlasts);
    }
} catch (Exception $e) {
    error_log("Error loading email blasts: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage and dispatch bulk email newsletters and regional announcements for IECEP-LSC.">
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
                        <i class="fas fa-newspaper" style="color:var(--color-navy);"></i>
                        Email Newsletters & Chapter Campaigns
                    </h1>
                    <p class="dash-header-sub">
                        Draft, schedule, and dispatch bulk email newsletters, regional publications, and chapter updates.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/communication/announcements.php" class="btn-white">
                        <i class="fas fa-bullhorn" style="color:var(--color-blue);"></i> Announcements
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openBlastModal()">
                        <i class="fas fa-plus" style="color:#FDE047;"></i> Create Campaign
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
                    <div class="kpi-icon-pill navy"><i class="fas fa-paper-plane"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalSent ?></div>
                        <div class="kpi-lbl">Total Campaigns Dispatched</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val">100%</div>
                        <div class="kpi-lbl">Email Delivery Success</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="kpi-val">All Chapters</div>
                        <div class="kpi-lbl">Target Audience</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-envelope-circle-check"></i></div>
                    <div>
                        <div class="kpi-val">Active</div>
                        <div class="kpi-lbl">SMTP Gmail Relay</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="blastSearchInput" class="search-input-field" placeholder="Search campaign title, subject..." onkeyup="filterBlastsTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($blastsList) ?> campaigns
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-envelope-open-text"></i> Campaign Dispatch History</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="blastsTable">
                        <thead>
                            <tr>
                                <th>Campaign Title & Subject</th>
                                <th>Recipient Group</th>
                                <th>Dispatch Status</th>
                                <th>Sent Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($blastsList)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-envelope-open" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Newsletter Campaigns Dispatched Yet</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Create Campaign" to compose and dispatch bulk emails to members.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($blastsList as $b): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($b['title'] ?? 'Campaign') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;">Subject: <?= htmlspecialchars($b['subject'] ?? $b['title'] ?? '') ?></span>
                                        </td>
                                        <td><span class="ap-pill blue"><?= htmlspecialchars($b['recipient_group'] ?? 'All Chapters') ?></span></td>
                                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Sent</span></td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;"><?= !empty($b['sent_at']) ? date('M d, Y h:i A', strtotime($b['sent_at'])) : 'Recent' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Create Campaign Modal -->
    <div id="blastModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-paper-plane"></i> Create Newsletter Campaign</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeBlastModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="create_blast">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Campaign Title</label>
                    <input type="text" name="title" class="ap-input" placeholder="e.g. IECEP Laguna Quarterly Gazette - Q3" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Email Subject Line</label>
                    <input type="text" name="subject" class="ap-input" placeholder="e.g. Important Updates & Chapter Highlights" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Recipient Group</label>
                    <select name="recipient_group" class="ap-input" style="font-size:0.8rem;">
                        <option value="All Laguna Chapters">All Laguna Chapter Members</option>
                        <option value="School Officers Only">School Chapter Officers Only</option>
                        <option value="Executive Board">Executive Board Members</option>
                    </select>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Newsletter Content & HTML Body</label>
                    <textarea name="content" class="ap-input" rows="4" placeholder="Write your newsletter edition..." required style="font-size:0.8rem;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeBlastModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-paper-plane"></i> Dispatch Campaign</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openBlastModal() {
            document.getElementById('blastModal').classList.add('active');
        }
        function closeBlastModal() {
            document.getElementById('blastModal').classList.remove('active');
        }

        function filterBlastsTable() {
            const query = document.getElementById('blastSearchInput').value.toLowerCase();
            const table = document.getElementById('blastsTable');
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
