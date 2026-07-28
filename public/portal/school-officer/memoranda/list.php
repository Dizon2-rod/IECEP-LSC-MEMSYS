<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../../auth_check.php';
require_role('school_officer');

require_once __DIR__ . '/../../../includes/head-meta.php';
?>
<div class="container mt-4">
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="card">
            <div class="card-body">
                <h1 class="h4">Memoranda</h1>
                <p class="text-muted">This section will list memoranda relevant to your school chapter. You can view, download, and acknowledge memoranda here.</p>
                <span class="badge" style="background:#D4AF37;color:#0B1D4A;font-weight:700;padding:0.5rem 0.75rem;border-radius:8px;">Coming Soon</span>
                <div class="mt-3">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/dashboard.php" class="btn btn-outline-secondary mt-2">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../../../includes/footer-new.php'; ?>
