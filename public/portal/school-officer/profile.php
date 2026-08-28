<?php
if (!isset($current_page)) { $current_page = 'profile'; }
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

$user = $_SESSION['user'] ?? [];
$userId = $user['id'] ?? $_SESSION['user_id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
$school = [];

if ($institutionId) {
    try {
        $schoolData = $supabase->select('institutions', [
            'id' => 'eq.' . $institutionId,
            'limit' => 1
        ]);
        $school = $schoolData[0] ?? [];
    } catch (Exception $e) {}
}

if (empty($school) && !empty($user['email'])) {
    try {
        $schoolData = $supabase->select('institutions', [
            'email' => 'eq.' . $user['email'],
            'limit' => 1
        ]);
        $school = $schoolData[0] ?? [];
    } catch (Exception $e) {}
}

if (empty($school)) {
    try {
        $schoolData = $supabase->select('institutions', [
            'status' => 'eq.active',
            'limit' => 1
        ]);
        $school = $schoolData[0] ?? [];
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Profile - IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../includes/head-meta.php'; ?>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pb-2 border-bottom">
                    <div>
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-semibold">Profile</span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="fas fa-school text-primary me-2"></i>Institutional School Profile
                        </h2>
                    </div>
                    <div>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="btn btn-sm btn-outline">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <div class="card card-navy-top mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fas fa-info-circle me-2 text-muted"></i>School Accreditation Information
                        </h5>
                        <?php if (!empty($school['status'])): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars(ucfirst($school['status'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($school)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <tbody>
                                    <tr><th style="width: 28%;" class="text-muted">School Name</th><td class="fw-bold text-dark"><?= htmlspecialchars($school['name'] ?? 'N/A') ?></td></tr>
                                    <tr><th class="text-muted">Acronym</th><td><span class="badge bg-secondary"><?= htmlspecialchars($school['acronym'] ?? 'N/A') ?></span></td></tr>
                                    <tr><th class="text-muted">Institution Type</th><td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $school['type'] ?? 'State University'))) ?></td></tr>
                                    <tr><th class="text-muted">Campus Address</th><td><?= htmlspecialchars($school['address'] ?? 'N/A') ?></td></tr>
                                    <tr><th class="text-muted">Municipality / City</th><td><?= htmlspecialchars($school['city'] ?? 'N/A') ?></td></tr>
                                    <tr><th class="text-muted">Province</th><td><?= htmlspecialchars($school['province'] ?? 'Laguna') ?></td></tr>
                                    <tr><th class="text-muted">Official Contact Person</th><td class="fw-semibold text-dark"><?= htmlspecialchars($school['contact_person'] ?? 'School Officer') ?></td></tr>
                                    <tr><th class="text-muted">Official Contact Email</th><td><code><?= htmlspecialchars($school['contact_email'] ?? $school['email'] ?? 'N/A') ?></code></td></tr>
                                    <tr><th class="text-muted">Contact Phone</th><td><?= htmlspecialchars($school['contact_phone'] ?? $school['phone'] ?? '—') ?></td></tr>
                                    <tr><th class="text-muted">Official Website</th><td><?= !empty($school['website']) ? '<a href="' . htmlspecialchars($school['website']) . '" target="_blank" class="text-decoration-none">' . htmlspecialchars($school['website']) . ' <i class="fas fa-external-link-alt small ms-1"></i></a>' : '—' ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-university fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                            <h6 class="fw-bold text-dark mb-1">No Profile Record Found</h6>
                            <p class="small text-muted">Please contact the IECEP-LSC Secretariat to register or link your chapter profile.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
