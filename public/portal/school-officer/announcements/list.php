<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'announcements';

require_once __DIR__ . '/../../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'Chapter & Regional Announcements';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Chapter Announcements';

$supabase = getSupabaseClient();

// Fetch Real Announcements from Database
$announcements = [];
$urgentCount = 0;
$activeCount = 0;

if ($supabase) {
    try {
        $rawAnn = $supabase->select('announcements', ['select' => '*', 'order' => 'created_at.desc']);
        if (is_array($rawAnn) && !isset($rawAnn['code'])) {
            $announcements = $rawAnn;
            foreach ($announcements as $a) {
                $st = strtolower($a['status'] ?? 'published');
                if ($st === 'published' || $st === 'active') $activeCount++;
                if (strtolower($a['priority'] ?? '') === 'urgent' || strtolower($a['priority'] ?? '') === 'high') $urgentCount++;
            }
        }
    } catch (Exception $e) {
        $announcements = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Official chapter announcements, regional circulars, and event bulletins.">
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

        .main-content {
            margin-left: 260px;
            padding: 1.25rem;
            min-height: 100vh;
            box-sizing: border-box;
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

        .mobile-toggle-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #F1F5F9;
            border: 1px solid var(--border-color);
            color: var(--color-navy);
            font-size: 1rem;
            cursor: pointer;
            flex-shrink: 0;
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

        .ann-feed-item {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem 1.4rem;
            margin-bottom: 0.85rem;
            box-shadow: var(--shadow-card);
            transition: all 0.18s ease;
        }
        .ann-feed-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11, 29, 74, 0.08);
            border-color: rgba(11, 29, 74, 0.3);
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 0.85rem; }
            .mobile-toggle-btn { display: inline-flex; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.5rem !important; }
            .kpi-val { font-size: 1.1rem !important; }
            .kpi-lbl { font-size: 0.66rem !important; }
            .dash-kpi-card { padding: 0.5rem 0.65rem !important; gap: 0.5rem !important; }
            .kpi-icon-pill { width: 32px !important; height: 32px !important; font-size: 0.9rem !important; }
            .dash-header-banner { flex-direction: column; align-items: stretch; gap: 0.65rem; }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                    <button type="button" id="sidebarToggle" class="mobile-toggle-btn" aria-label="Toggle Navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="dash-header-title">
                            <i class="fas fa-bullhorn" style="color:var(--color-navy);"></i>
                            Official Chapter Announcements & Bulletins
                        </h1>
                        <p class="dash-header-sub">
                            Regional circulars, event guidelines, and technical competition advisories for student officers.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-newspaper"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($announcements) ?></div>
                        <div class="kpi-lbl">Total Announcements</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $activeCount ?></div>
                        <div class="kpi-lbl">Active Broadcasts</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="kpi-val"><?= $urgentCount ?></div>
                        <div class="kpi-lbl">Urgent Circulars</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-globe"></i></div>
                    <div>
                        <div class="kpi-val">Laguna</div>
                        <div class="kpi-lbl">Section Scope</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="annSearchInput" class="search-input-field" placeholder="Search announcement title, content..." onkeyup="filterAnnFeed()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($announcements) ?> announcements
                </div>
            </div>

            <!-- 4. Announcements Feed -->
            <div id="annFeedContainer">
                <?php if (empty($announcements)): ?>
                    <div class="ann-feed-item" style="text-align:center; padding:3rem 1.5rem;">
                        <i class="fas fa-bullhorn" style="font-size:2.25rem; color:#CBD5E1; margin-bottom:0.75rem; display:block;"></i>
                        <strong style="color:#0F172A; font-size:0.95rem;">No Announcements Published Yet in Database</strong>
                        <p style="margin:0.25rem 0 0; font-size:0.8rem; color:#64748B;">Regional notices published by the secretariat will appear here in real-time.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $a): ?>
                        <?php 
                            $pr = strtolower($a['priority'] ?? 'normal');
                            $img = $a['image_url'] ?? ($a['banner_url'] ?? '');
                        ?>
                        <div class="ann-feed-item" data-search="<?= htmlspecialchars(strtolower(($a['title'] ?? '') . ' ' . ($a['content'] ?? ($a['body'] ?? '')))) ?>">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.65rem;">
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <span class="ap-pill <?= ($pr === 'urgent' || $pr === 'high') ? 'danger' : 'blue' ?>">
                                        <?= ucfirst($pr) ?> Priority
                                    </span>
                                    <span style="font-size:0.75rem; color:#64748B;">
                                        <i class="fas fa-clock"></i> <?= !empty($a['created_at']) ? date('M d, Y h:i A', strtotime($a['created_at'])) : 'Recent' ?>
                                    </span>
                                </div>
                                <span class="ap-pill active"><span class="ap-pill-dot"></span> Published</span>
                            </div>

                            <h3 style="margin:0 0 0.5rem; font-size:1.05rem; font-weight:800; color:#0F172A;">
                                <?= htmlspecialchars($a['title'] ?? 'Announcement') ?>
                            </h3>

                            <?php if (!empty($img)): ?>
                                <div style="margin-bottom:0.85rem; border-radius:8px; overflow:hidden; border:1px solid #E2E8F0; max-height:260px;">
                                    <img src="<?= htmlspecialchars($img) ?>" alt="Banner" style="width:100%; height:auto; object-fit:cover; display:block;">
                                </div>
                            <?php endif; ?>

                            <div style="font-size:0.84rem; line-height:1.6; color:#334155;">
                                <?= nl2br(htmlspecialchars($a['content'] ?? ($a['body'] ?? ''))) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        function filterAnnFeed() {
            const query = document.getElementById('annSearchInput').value.toLowerCase();
            const items = document.querySelectorAll('.ann-feed-item[data-search]');

            items.forEach(item => {
                const text = item.getAttribute('data-search') || '';
                item.style.display = (text.indexOf(query) > -1) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
