<?php
if (!isset($current_page)) { $current_page = 'compliance'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance Status - IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pb-2 border-bottom">
                    <div>
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-semibold">Compliance</span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="fas fa-shield-alt text-primary me-2"></i>Chapter Compliance Status
                        </h2>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge badge-success px-3 py-2">
                            <i class="fas fa-check-circle me-1"></i> Chapter in Good Standing
                        </span>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="btn btn-sm btn-outline">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-emerald">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Overall Compliance</div>
                            <div class="stat-value text-success">100%</div>
                            <div class="stat-desc">AY <?= date('Y') ?>–<?= date('Y') + 1 ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-navy">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Member Engagement</div>
                            <div class="stat-value">68.5%</div>
                            <div class="stat-desc">Threshold: 40.0%</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-gold">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Hosted Activities</div>
                            <div class="stat-value">3 Events</div>
                            <div class="stat-desc">Minimum: 1 Event</div>
                        </div>
                    </div>
                </div>

                <div class="card card-navy-top">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fas fa-tasks me-2 text-muted"></i>Accreditation Checklist & Requirements
                        </h5>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Requirement Description</th>
                                    <th>Accreditation Threshold</th>
                                    <th>Current Assessment</th>
                                    <th>Standing</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">Member Participation Rate</div>
                                        <small class="text-muted">Minimum active engagement in regional student conferences and competitions</small>
                                    </td>
                                    <td>40.00%</td>
                                    <td class="fw-bold text-dark">68.50%</td>
                                    <td><span class="badge badge-success"><i class="fas fa-check me-1"></i> Passed</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">Required Chapter Events</div>
                                        <small class="text-muted">Minimum chapter-hosted academic/technical seminars per academic period</small>
                                    </td>
                                    <td>1 Seminar / Event</td>
                                    <td class="fw-bold text-dark">3 Events Hosted</td>
                                    <td><span class="badge badge-success"><i class="fas fa-check me-1"></i> Passed</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">Affiliation Document Endorsement</div>
                                        <small class="text-muted">Dean Endorsement, LOI, Constitution & Bylaws, Officers Directory</small>
                                    </td>
                                    <td>100% Submission</td>
                                    <td class="fw-bold text-dark">100% Complete</td>
                                    <td><span class="badge badge-success"><i class="fas fa-shield-alt me-1"></i> Verified</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
