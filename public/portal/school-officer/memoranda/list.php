<?php
if (!isset($current_page)) { $current_page = 'memoranda'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

$memoranda = [
    [
        'id' => 'MEMO-2026-001',
        'title' => 'Executive Directive on Chapter Affiliation Compliance & Per-Capita Dues Remittance',
        'category' => 'Administrative',
        'effective_date' => '2026-08-01',
        'issuer' => 'Regional Executive Board',
        'summary' => 'Prescribing the uniform deadline, documentary prerequisites, and cryptographic validation protocols for all recognized student chapters in the Laguna Section.',
        'status' => 'active'
    ],
    [
        'id' => 'MEMO-2026-002',
        'title' => 'Implementation of Standardized Digital Membership Identification System',
        'category' => 'Operations',
        'effective_date' => '2026-08-10',
        'issuer' => 'IT & Systems Directorate',
        'summary' => 'Authorizing the issuance and cross-campus QR validation of verified digital membership ID credentials for AY 2026–2027.',
        'status' => 'active'
    ],
    [
        'id' => 'MEMO-2026-003',
        'title' => 'Guidelines on Regional Academic Summit Participation & Technical Paper Grants',
        'category' => 'Academic',
        'effective_date' => '2026-08-20',
        'issuer' => 'Academics & Research Council',
        'summary' => 'Framework for student research presentation subsidies, capstone project exhibitions, and institutional chapter delegate accreditations.',
        'status' => 'active'
    ]
];

$totalMemos = count($memoranda);
$activeMemos = count(array_filter($memoranda, fn($m) => strtolower($m['status'] ?? '') === 'active'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Memoranda — IECEP-LSC MEMSYS</title>
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

        .memo-card-clean {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-left: 4px solid #0B1D4A;
            border-radius: 14px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
        }

        .memo-card-clean:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(11, 29, 74, 0.06);
            border-color: rgba(11, 29, 74, 0.25);
        }

        .search-box-wrap {
            position: relative;
            max-width: 320px;
            width: 100%;
        }

        .search-box-wrap input {
            width: 100%;
            padding: 0.45rem 1rem 0.45rem 2.25rem;
            border-radius: 50px;
            border: 1px solid var(--border-light);
            font-size: 0.85rem;
            outline: none;
            background: #FFFFFF;
        }

        .search-box-wrap i {
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
                            <span class="text-dark fw-bold">Memoranda</span>
                        </div>
                        <h1 class="ap-page-title">
                            <i class="fas fa-file-contract text-primary"></i> Official Chapter Memoranda
                        </h1>
                        <p class="ap-page-subtitle">
                            Official policy directives, executive resolutions, and institutional memoranda from the IECEP Laguna Section Chapter.
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
                            <div class="ap-stat-icon navy"><i class="fas fa-file-contract"></i></div>
                            <div class="ap-stat-title">Published Directives</div>
                        </div>
                        <div class="ap-stat-val"><?= $totalMemos ?></div>
                        <div class="small text-muted mt-1">Official circulars indexed</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon emerald"><i class="fas fa-check-circle"></i></div>
                            <div class="ap-stat-title">Active Policies</div>
                        </div>
                        <div class="ap-stat-val text-success"><?= $activeMemos ?></div>
                        <div class="small text-muted mt-1">Currently in effect</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon gold"><i class="fas fa-university"></i></div>
                            <div class="ap-stat-title">Regional Scope</div>
                        </div>
                        <div class="ap-stat-val" style="color: #B8860B;">Laguna Section</div>
                        <div class="small text-muted mt-1">Institutional Chapters</div>
                    </div>
                </div>

                <!-- Toolbar & Search -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div class="search-box-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="memoSearch" placeholder="Search memoranda by title or keyword..." oninput="searchMemos()">
                    </div>

                    <div class="text-muted small">
                        Showing <?= count($memoranda) ?> official document(s)
                    </div>
                </div>

                <!-- Memoranda Cards Container -->
                <div id="memosList">
                    <?php foreach ($memoranda as $m): ?>
                        <div class="memo-card-clean memo-item">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div>
                                    <code class="fw-bold text-dark me-2" style="font-size: 0.85rem;"><?= htmlspecialchars($m['id']) ?></code>
                                    <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($m['category']) ?>
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-calendar-alt me-1"></i> Effective: <?= date('F j, Y', strtotime($m['effective_date'])) ?>
                                </div>
                            </div>

                            <h4 class="fw-bold text-dark mb-2" style="font-size: 1.15rem;">
                                <?= htmlspecialchars($m['title']) ?>
                            </h4>

                            <p class="text-secondary mb-3" style="font-size: 0.9rem; line-height: 1.55;">
                                <?= htmlspecialchars($m['summary']) ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top text-muted small">
                                <div>
                                    <i class="fas fa-stamp me-1 text-primary"></i> Issuing Body: <strong><?= htmlspecialchars($m['issuer']) ?></strong>
                                </div>
                                <span class="badge bg-success" style="font-size: 0.72rem;">
                                    <i class="fas fa-check-circle me-1"></i> Official Policy
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function searchMemos() {
            const query = document.getElementById('memoSearch').value.toLowerCase();
            const items = document.querySelectorAll('.memo-item');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
