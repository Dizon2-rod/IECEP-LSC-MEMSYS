<?php
if (!isset($current_page)) { $current_page = 'memoranda'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';

require_role(['school_officer', 'admin', 'super_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memoranda - IECEP-LSC MEMSYS</title>
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
                            <span class="text-dark fw-semibold">Memoranda</span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="fas fa-file-contract text-primary me-2"></i>Official Memoranda
                        </h2>
                    </div>
                    <div>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="btn btn-sm btn-outline">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <div class="card card-navy-top text-center py-5">
                    <div class="py-4">
                        <div class="stat-icon icon-blue mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.75rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(11,29,74,0.08); color: var(--memsys-navy);">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">Chapter Memoranda Repository</h4>
                        <p class="text-muted mx-auto mb-3" style="max-width: 480px;">
                            Official chapter directives, executive resolutions, and institutional memoranda will be published here.
                        </p>
                        <span class="badge bg-secondary px-3 py-2">No Active Memoranda</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
