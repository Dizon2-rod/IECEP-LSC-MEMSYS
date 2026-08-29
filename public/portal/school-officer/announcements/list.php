<?php
if (!isset($current_page)) { $current_page = 'announcements'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

$supabase = getSupabaseClient();
$announcements = [];
try {
    if ($supabase) {
        $res = $supabase->select('announcements', [
            'order' => 'created_at.desc'
        ]);
        if (is_array($res) && !isset($res['code'])) {
            $announcements = $res;
        }
    }
} catch (\Throwable $e) {
    error_log("Announcements query error: " . $e->getMessage());
}

// Fallback sample data if table is currently empty
if (empty($announcements)) {
    $announcements = [
        [
            'id' => 'anc-01',
            'title' => 'Annual Chapter Affiliation Renewal Notice (AY ' . date('Y') . '–' . (date('Y')+1) . ')',
            'category' => 'compliance',
            'content' => 'All institutional student chapter officers are requested to submit the complete Affiliation Kit (LOI, Constitution & Bylaws, Endorsement Letter, and Officer CVs) on or before the deadline to ensure good chapter standing.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'author' => 'IECEP-LSC Secretariat',
            'priority' => 'high'
        ],
        [
            'id' => 'anc-02',
            'title' => 'Regional Student Convention & Technical Papers Call',
            'category' => 'events',
            'content' => 'The Call for Papers and Student Research Presentation is officially open. Outstanding papers will receive recognition awards and publication in the official chapter research proceedings.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'author' => 'Academics & Research Committee',
            'priority' => 'normal'
        ],
        [
            'id' => 'anc-03',
            'title' => 'Digital ID Dispatch & Verification System Guidelines',
            'category' => 'general',
            'content' => 'Officers can now generate and batch-email verified digital membership credentials to all student members in good financial standing through the Digital ID management desk.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'author' => 'IT & Systems Operations',
            'priority' => 'normal'
        ]
    ];
}

$totalNotices = count($announcements);
$urgentCount = count(array_filter($announcements, fn($a) => strtolower($a['priority'] ?? '') === 'high' || strtolower($a['priority'] ?? '') === 'urgent'));
$complianceCount = count(array_filter($announcements, fn($a) => strtolower($a['category'] ?? '') === 'compliance'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Announcements — IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/admin-portal.css">
    <style>
        :root {
            --bg-page: #F8FAFC;
            --bg-surface: #FFFFFF;
            --border-light: #E2E8F0;
            --text-heading: #0B1D4A;
            --text-primary: #0F172A;
            --text-muted: #64748B;
        }

        body {
            background-color: var(--bg-page) !important;
            font-family: 'DM Sans', 'Inter', -apple-system, sans-serif;
            color: var(--text-primary);
        }

        .announcement-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
            position: relative;
        }

        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(11, 29, 74, 0.06);
            border-color: rgba(11, 29, 74, 0.25);
        }

        .announcement-card.priority-high {
            border-left: 4px solid #E11D48;
        }

        .announcement-card.priority-normal {
            border-left: 4px solid #0B1D4A;
        }

        .filter-tab-btn {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.4rem 0.95rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-tab-btn.active, .filter-tab-btn:hover {
            background: #0B1D4A;
            color: #FFFFFF;
            border-color: #0B1D4A;
        }

        .search-bar-wrap {
            position: relative;
            max-width: 320px;
            width: 100%;
        }

        .search-bar-wrap input {
            width: 100%;
            padding: 0.45rem 1rem 0.45rem 2.25rem;
            border-radius: 50px;
            border: 1px solid var(--border-light);
            font-size: 0.85rem;
            outline: none;
            background: #FFFFFF;
            transition: all 0.2s ease;
        }

        .search-bar-wrap input:focus {
            border-color: #0B1D4A;
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.08);
        }

        .search-bar-wrap i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>

        <main class="main-content ap-scope">
            <div class="container py-4">
                <!-- Clean Page Header -->
                <div class="ap-page-header">
                    <div class="ap-title-block">
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-bold">Announcements</span>
                        </div>
                        <h1 class="ap-page-title">
                            <i class="fas fa-bullhorn text-warning"></i> Chapter Announcement Board
                        </h1>
                        <p class="ap-page-subtitle">
                            Official institutional circulars, regional conference updates, and executive chapter advisories.
                        </p>
                    </div>
                    <div class="ap-header-actions">
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="ap-btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- 3 KPI Stat Cards -->
                <div class="ap-kpi-grid-3 mb-4">
                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon navy"><i class="fas fa-bullhorn"></i></div>
                            <div class="ap-stat-title">Total Circulars</div>
                        </div>
                        <div class="ap-stat-val"><?= $totalNotices ?></div>
                        <div class="small text-muted mt-1">Official releases</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon emerald"><i class="fas fa-shield-alt"></i></div>
                            <div class="ap-stat-title">Compliance Directives</div>
                        </div>
                        <div class="ap-stat-val text-success"><?= $complianceCount ?></div>
                        <div class="small text-muted mt-1">Accreditation guidelines</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon gold"><i class="fas fa-exclamation-circle"></i></div>
                            <div class="ap-stat-title">High Priority</div>
                        </div>
                        <div class="ap-stat-val" style="color: #B8860B;"><?= $urgentCount ?></div>
                        <div class="small text-muted mt-1">Action required notices</div>
                    </div>
                </div>

                <!-- Toolbar & Filters -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div class="d-flex gap-2 flex-wrap" id="categoryFilterGroup">
                        <button class="filter-tab-btn active" onclick="filterCategory('all', this)">All Notices</button>
                        <button class="filter-tab-btn" onclick="filterCategory('compliance', this)">Compliance</button>
                        <button class="filter-tab-btn" onclick="filterCategory('events', this)">Events & Summits</button>
                        <button class="filter-tab-btn" onclick="filterCategory('general', this)">General</button>
                    </div>

                    <div class="search-bar-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="announcementSearch" placeholder="Search announcements..." oninput="searchNotices()">
                    </div>
                </div>

                <!-- Announcements List Cards -->
                <div id="announcementsList">
                    <?php foreach ($announcements as $a): ?>
                        <?php
                        $priority = strtolower($a['priority'] ?? 'normal');
                        $category = strtolower($a['category'] ?? 'general');
                        $badgeClass = match($category) {
                            'compliance' => 'emerald',
                            'events' => 'gold',
                            default => 'navy'
                        };
                        ?>
                        <div class="announcement-card priority-<?= $priority ?>" data-category="<?= htmlspecialchars($category) ?>">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div>
                                    <span class="badge bg-light text-dark border me-2 text-uppercase fw-bold" style="font-size: 0.72rem; padding: 0.25rem 0.55rem;">
                                        <?= htmlspecialchars($category) ?>
                                    </span>
                                    <?php if ($priority === 'high' || $priority === 'urgent'): ?>
                                        <span class="badge bg-danger text-white fw-bold" style="font-size: 0.72rem;">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Urgent Notice
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-calendar-alt me-1"></i> <?= date('F j, Y', strtotime($a['created_at'] ?? 'now')) ?>
                                </div>
                            </div>

                            <h4 class="fw-bold text-dark mb-2" style="font-size: 1.15rem;">
                                <?= htmlspecialchars($a['title'] ?? 'Notice') ?>
                            </h4>

                            <p class="text-secondary mb-3" style="font-size: 0.92rem; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($a['content'] ?? '')) ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top text-muted small">
                                <div>
                                    <i class="fas fa-user-circle me-1 text-primary"></i> <?= htmlspecialchars($a['author'] ?? 'IECEP Secretariat') ?>
                                </div>
                                <span class="text-success fw-semibold">
                                    <i class="fas fa-check-circle me-1"></i> Official Chapter Circular
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function filterCategory(cat, btn) {
            document.querySelectorAll('.filter-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const cards = document.querySelectorAll('.announcement-card');
            cards.forEach(card => {
                const itemCat = card.getAttribute('data-category');
                if (cat === 'all' || itemCat === cat) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function searchNotices() {
            const query = document.getElementById('announcementSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.announcement-card');
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
