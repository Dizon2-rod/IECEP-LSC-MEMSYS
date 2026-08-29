<?php
if (!isset($current_page)) { $current_page = 'compliance'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

$user = $_SESSION['user'] ?? [];
$userId = $user['id'] ?? $_SESSION['user_id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Affiliated School Chapter';

$db = $GLOBALS['supabaseClient'] ?? new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
if ($db && $institutionId) {
    try {
        $institutions = $db->select('institutions', ['id' => 'eq.' . $institutionId]);
        if (is_array($institutions) && isset($institutions[0]['name'])) {
            $schoolName = $institutions[0]['name'];
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Compliance Status — IECEP-LSC MEMSYS</title>
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

        .compliance-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 1rem;
            border-radius: 50px;
            background: rgba(5, 150, 105, 0.1);
            color: #059669;
            font-weight: 700;
            font-size: 0.85rem;
            border: 1px solid rgba(5, 150, 105, 0.25);
        }

        .checklist-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .milestone-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .milestone-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .milestone-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .milestone-icon.completed {
            background: rgba(5, 150, 105, 0.12);
            color: #059669;
        }

        .milestone-icon.in-progress {
            background: rgba(217, 119, 6, 0.12);
            color: #D97706;
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
                            <span class="text-dark fw-bold">Compliance</span>
                        </div>
                        <h1 class="ap-page-title">
                            <i class="fas fa-shield-alt text-success"></i> Chapter Accreditation & Compliance
                        </h1>
                        <p class="ap-page-subtitle">
                            Institutional Standing: <strong><?= htmlspecialchars($schoolName) ?></strong> • AY <?= date('Y') ?>–<?= date('Y') + 1 ?>
                        </p>
                    </div>
                    <div class="ap-header-actions">
                        <span class="compliance-hero-badge">
                            <i class="fas fa-check-circle"></i> Chapter in Good Standing
                        </span>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="ap-btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- 3 KPI Stat Cards -->
                <div class="ap-kpi-grid-3 mb-4">
                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon emerald"><i class="fas fa-chart-pie"></i></div>
                            <div class="ap-stat-title">Overall Compliance</div>
                        </div>
                        <div class="ap-stat-val text-success">100%</div>
                        <div class="small text-muted mt-1">Full chapter accreditation</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon navy"><i class="fas fa-user-check"></i></div>
                            <div class="ap-stat-title">Member Engagement</div>
                        </div>
                        <div class="ap-stat-val">68.5%</div>
                        <div class="small text-muted mt-1">Accreditation threshold: 40.0%</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon gold"><i class="fas fa-calendar-check"></i></div>
                            <div class="ap-stat-title">Hosted Activities</div>
                        </div>
                        <div class="ap-stat-val" style="color: #B8860B;">3 Events</div>
                        <div class="small text-muted mt-1">Minimum requirement: 1 event</div>
                    </div>
                </div>

                <!-- Accreditation Checklist Table Card -->
                <div class="checklist-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold text-dark mb-0" style="font-size: 1.1rem;">
                            <i class="fas fa-tasks text-primary me-2"></i>Accreditation Matrix & Requirements
                        </h4>
                        <span class="badge bg-light text-muted border">IECEP National CBL Art. IV</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr style="background: #F8FAFC; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <th class="py-3">Requirement & Metric</th>
                                    <th class="py-3">Threshold</th>
                                    <th class="py-3">Current Assessment</th>
                                    <th class="py-3 text-center">Standing</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">Member Participation Rate</div>
                                        <small class="text-muted">Minimum active engagement in regional conferences, seminars, and technical summits</small>
                                    </td>
                                    <td class="fw-semibold">40.00%</td>
                                    <td class="fw-bold text-success">68.50%</td>
                                    <td class="text-center">
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="fas fa-check me-1"></i> Passed
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">Required Chapter-Hosted Events</div>
                                        <small class="text-muted">Minimum campus-level academic, career orientation, or technical workshops per AY</small>
                                    </td>
                                    <td class="fw-semibold">1 Event</td>
                                    <td class="fw-bold text-dark">3 Events Hosted</td>
                                    <td class="text-center">
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="fas fa-check me-1"></i> Passed
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">Affiliation Document Endorsement Kit</div>
                                        <small class="text-muted">Dean Endorsement, Letter of Intent, Constitution & Bylaws, Officers Directory with CVs</small>
                                    </td>
                                    <td class="fw-semibold">100% Submission</td>
                                    <td class="fw-bold text-dark">100% Verified</td>
                                    <td class="text-center">
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="fas fa-shield-alt me-1"></i> Verified
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">Annual Institutional Dues Remittance</div>
                                        <small class="text-muted">Chapter affiliation and student member operational per-capita dues settlement</small>
                                    </td>
                                    <td class="fw-semibold">Full Settlement</td>
                                    <td class="fw-bold text-success">Settled & Audited</td>
                                    <td class="text-center">
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="fas fa-receipt me-1"></i> Remitted
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Accreditation Milestones Card -->
                <div class="checklist-card">
                    <h4 class="fw-bold text-dark mb-3" style="font-size: 1.1rem;">
                        <i class="fas fa-flag-checkered text-warning me-2"></i>Accreditation Timeline & Milestones
                    </h4>

                    <div class="milestone-item">
                        <div class="milestone-icon completed"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="fw-bold text-dark">Official Endorsement & Constitution Approved</div>
                            <div class="text-muted small">Dean endorsement letter and approved chapter bylaws indexed in regional archives.</div>
                        </div>
                    </div>

                    <div class="milestone-item">
                        <div class="milestone-icon completed"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="fw-bold text-dark">Student Member Directory Synchronized</div>
                            <div class="text-muted small">1st to 4th year student roster validated and active in the central cryptographic registry.</div>
                        </div>
                    </div>

                    <div class="milestone-item">
                        <div class="milestone-icon completed"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="fw-bold text-dark">Digital IDs Generated & Distributed</div>
                            <div class="text-muted small">Real-time dynamic membership credentials generated with QR code verification.</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
