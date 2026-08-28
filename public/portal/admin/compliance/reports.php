<?php
require_once __DIR__ . '/../../auth_check.php';

if (!isset($current_page)) { $current_page = 'reports'; }
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

// Check if user is admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin', 'auditor'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Compliance Audits & Reports — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Official annual chapter compliance reports, participation rates, and hosting evaluations.">
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-file-shield"></i> Chapter Compliance Reports</h1>
                    <p class="ap-page-subtitle">Annual institutional scorecards, event hosting compliance, and participation rate auditing.</p>
                </div>
                <div class="ap-header-actions">
                    <select class="ap-select" id="report-year" onchange="loadReports()">
                        <option value="2024">AY 2024–2025</option>
                        <option value="2025">AY 2025–2026</option>
                        <option value="2026" selected>AY 2026–2027</option>
                    </select>
                    <button class="ap-btn-secondary" onclick="loadReports()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/compliance/dashboard.php" class="ap-btn-primary">
                        <i class="fas fa-clipboard-check"></i> Compliance Dashboard
                    </a>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-university"></i></div>
                        <div><div class="ap-stat-label">Institutions</div><div class="ap-stat-sublabel">Laguna HEIs</div></div>
                    </div>
                    <div class="ap-stat-value">5</div>
                    <div class="ap-stat-footer">Evaluated student chapters</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                        <div><div class="ap-stat-label">Compliant</div><div class="ap-stat-sublabel">Score ≥ 80%</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);">4</div>
                    <div class="ap-stat-footer">Fully qualified chapters</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon amber"><i class="fas fa-triangle-exclamation"></i></div>
                        <div><div class="ap-stat-label">At Risk</div><div class="ap-stat-sublabel">Score &lt; 80%</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-amber);">1</div>
                    <div class="ap-stat-footer">Requires event hosting</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-chart-line"></i></div>
                        <div><div class="ap-stat-label">Average</div><div class="ap-stat-sublabel">Participation Rate</div></div>
                    </div>
                    <div class="ap-stat-value">86.2%</div>
                    <div class="ap-stat-footer">Laguna regional average</div>
                </div>
            </div>

            <!-- Reports List Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-chart-simple"></i> Institutional Scorecard Summary</h3>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Institution</th>
                                <th>Compliance Status</th>
                                <th>Overall Score</th>
                                <th>Event Participation</th>
                                <th>Events Hosted</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reports-table-body">
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <div class="ap-avatar-badge navy">LSPU</div>
                                        <div>
                                            <strong style="color:var(--text-heading);">Laguna State Polytechnic University - Santa Cruz</strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">Santa Cruz, Laguna &bull; 142 Members</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Compliant</span></td>
                                <td><strong style="color:var(--iecep-navy); font-size:0.95rem;">96%</strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <div class="ap-progress-bar" style="width:70px;"><div class="ap-progress-fill emerald" style="width:94%;"></div></div>
                                        <span style="font-weight:700; font-size:0.8rem;">94%</span>
                                    </div>
                                </td>
                                <td><strong>4 events</strong></td>
                                <td style="text-align:right;">
                                    <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="viewReportDetail('LSPU Santa Cruz')">
                                        <i class="fas fa-file-pdf"></i> Scorecard
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <div class="ap-avatar-badge gold">MMCL</div>
                                        <div>
                                            <strong style="color:var(--text-heading);">Mapúa Malayan Colleges Laguna</strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">Cabuyao, Laguna &bull; 98 Members</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Compliant</span></td>
                                <td><strong style="color:var(--iecep-navy); font-size:0.95rem;">92%</strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <div class="ap-progress-bar" style="width:70px;"><div class="ap-progress-fill emerald" style="width:88%;"></div></div>
                                        <span style="font-weight:700; font-size:0.8rem;">88%</span>
                                    </div>
                                </td>
                                <td><strong>3 events</strong></td>
                                <td style="text-align:right;">
                                    <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="viewReportDetail('Mapua Malayan')">
                                        <i class="fas fa-file-pdf"></i> Scorecard
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <div class="ap-avatar-badge emerald">DLSU</div>
                                        <div>
                                            <strong style="color:var(--text-heading);">De La Salle University - Laguna Campus</strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">Biñan, Laguna &bull; 87 Members</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Compliant</span></td>
                                <td><strong style="color:var(--iecep-navy); font-size:0.95rem;">95%</strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <div class="ap-progress-bar" style="width:70px;"><div class="ap-progress-fill emerald" style="width:92%;"></div></div>
                                        <span style="font-weight:700; font-size:0.8rem;">92%</span>
                                    </div>
                                </td>
                                <td><strong>5 events</strong></td>
                                <td style="text-align:right;">
                                    <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="viewReportDetail('DLSU Laguna')">
                                        <i class="fas fa-file-pdf"></i> Scorecard
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <div class="ap-avatar-badge navy">SP</div>
                                        <div>
                                            <strong style="color:var(--text-heading);">Laguna State Polytechnic University - San Pablo</strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">San Pablo, Laguna &bull; 76 Members</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Compliant</span></td>
                                <td><strong style="color:var(--iecep-navy); font-size:0.95rem;">88%</strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <div class="ap-progress-bar" style="width:70px;"><div class="ap-progress-fill emerald" style="width:82%;"></div></div>
                                        <span style="font-weight:700; font-size:0.8rem;">82%</span>
                                    </div>
                                </td>
                                <td><strong>2 events</strong></td>
                                <td style="text-align:right;">
                                    <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="viewReportDetail('LSPU San Pablo')">
                                        <i class="fas fa-file-pdf"></i> Scorecard
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <div class="ap-avatar-badge purple">CSJL</div>
                                        <div>
                                            <strong style="color:var(--text-heading);">Colegio de San Juan de Letran - Calamba</strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">Calamba, Laguna &bull; 52 Members</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="ap-pill pending"><span class="ap-pill-dot"></span> At Risk</span></td>
                                <td><strong style="color:var(--accent-amber); font-size:0.95rem;">72%</strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <div class="ap-progress-bar" style="width:70px;"><div class="ap-progress-fill" style="width:75%; background:var(--accent-amber);"></div></div>
                                        <span style="font-weight:700; font-size:0.8rem;">75%</span>
                                    </div>
                                </td>
                                <td><strong>1 event</strong></td>
                                <td style="text-align:right;">
                                    <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="viewReportDetail('Letran Calamba')">
                                        <i class="fas fa-file-pdf"></i> Scorecard
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-scale-balanced"></i><span><strong>Auditing Standard:</strong> IECEP National Student Chapter Guidelines</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Proof of Compliance:</strong> SHA-256 Ledger Backed</span></div>
            </div>

        </div>
    </main>

    <script>
        function loadReports() {
            alert('Refreshing compliance scorecard evaluation...');
        }

        function viewReportDetail(name) {
            alert('Generating official compliance scorecard for: ' + name);
        }
    </script>
</body>
</html>
