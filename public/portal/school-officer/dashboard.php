<?php
if (!isset($current_page)) { $current_page = 'dashboard'; }
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

$user = $_SESSION['user'] ?? [];
$userName = $user['user_metadata']['full_name'] ?? $user['name'] ?? $user['email'] ?? 'School Officer';
$userEmail = $user['email'] ?? '';
$currentDate = date('F j, Y');

// Get user's institution
$db = $GLOBALS['supabaseClient'] ?? new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Affiliated School Chapter';

if ($db) {
    try {
        if (!$institutionId && isset($user['id'])) {
            $profiles = $db->select('user_profiles', ['user_id' => 'eq.' . $user['id']]);
            if (is_array($profiles) && isset($profiles[0]['institution_id'])) {
                $institutionId = $profiles[0]['institution_id'];
            }
            if (!$institutionId) {
                $members = $db->select('members', ['user_id' => 'eq.' . $user['id']]);
                if (is_array($members) && isset($members[0]['institution_id'])) {
                    $institutionId = $members[0]['institution_id'];
                }
            }
        }

        if ($institutionId) {
            $institutions = $db->select('institutions', ['id' => 'eq.' . $institutionId]);
            if (is_array($institutions) && isset($institutions[0]['name'])) {
                $schoolName = $institutions[0]['name'];
            }
        } else {
            $institutions = $db->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
            if (is_array($institutions) && isset($institutions[0]['id'])) {
                $institutionId = $institutions[0]['id'];
                $schoolName = $institutions[0]['name'] ?? $schoolName;
            }
        }
    } catch (Exception $e) {}
}

if ($institutionId) {
    $_SESSION['institution_id'] = $institutionId;
}

// Fetch dashboard data
$member_count = 0;
$total_paid = 0;
$recent_members = [];
$recent_batches = [];

if ($institutionId && $db) {
    try {
        $members = $db->select('members', ['institution_id' => 'eq.' . $institutionId]);
        $member_count = is_array($members) ? count($members) : 0;
    } catch (Exception $e) {}

    try {
        $txs = $db->select('transactions', [
            'institution_id' => 'eq.' . $institutionId,
            'status' => 'eq.paid'
        ]);
        if (is_array($txs)) {
            foreach ($txs as $t) {
                $total_paid += (float)($t['amount'] ?? 0);
            }
        }
    } catch (Exception $e) {}

    try {
        $recent_members = $db->select('members', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'created_at.desc',
            'limit' => 5
        ]);
        if (!is_array($recent_members)) { $recent_members = []; }
    } catch (Exception $e) {}

    try {
        $recent_batches = $db->select('upload_batches', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'uploaded_at.desc',
            'limit' => 4
        ]);
        if (!is_array($recent_batches)) { $recent_batches = []; }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Officer Dashboard - IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../includes/head-meta.php'; ?>
    <style>
        .hero-banner {
            background: linear-gradient(135deg, #071330 0%, #0B1D4A 50%, #1E3A6E 100%) !important;
            border-radius: var(--radius-lg);
            padding: 2.25rem 2.5rem;
            color: #ffffff !important;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(11, 29, 74, 0.25);
        }

        .hero-banner h1,
        .hero-banner h2,
        .hero-banner h3 {
            color: #ffffff !important;
        }

        .hero-banner p {
            color: #e2e8f0 !important;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.3) 0%, rgba(212, 175, 55, 0) 70%);
            pointer-events: none;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.95rem;
            background: rgba(255, 255, 255, 0.15) !important;
            border: 1px solid rgba(255, 255, 255, 0.28) !important;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #F5D76E !important;
            margin-bottom: 0.85rem;
            backdrop-filter: blur(6px);
        }

        .hero-banner .text-white-50 {
            color: rgba(255, 255, 255, 0.85) !important;
        }

        .hero-banner .btn-gold {
            background: linear-gradient(135deg, #F5D76E 0%, #E5B82A 50%, #D4AF37 100%) !important;
            color: #071330 !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
            border: none !important;
            padding: 0.7rem 1.4rem !important;
            font-size: 0.92rem !important;
            border-radius: var(--radius-sm) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            text-decoration: none !important;
        }

        .hero-banner .btn-gold:hover {
            background: #ffffff !important;
            color: #0B1D4A !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4) !important;
        }

        .hero-banner .btn-gold i {
            color: inherit !important;
        }

        .hero-banner .btn-outline {
            background: rgba(255, 255, 255, 0.12) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.45) !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            backdrop-filter: blur(6px);
            padding: 0.7rem 1.4rem !important;
            font-size: 0.92rem !important;
            border-radius: var(--radius-sm) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            text-decoration: none !important;
        }

        .hero-banner .btn-outline:hover {
            background: rgba(255, 255, 255, 0.25) !important;
            border-color: #ffffff !important;
            color: #ffffff !important;
            transform: translateY(-2px);
        }

        .hero-banner .btn-outline i {
            color: #ffffff !important;
        }

        .quick-action-tile {
            background: var(--memsys-card-bg);
            border: 1px solid var(--memsys-border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            transition: var(--transition);
            height: 100%;
        }

        .quick-action-tile:hover {
            transform: translateY(-3px);
            border-color: rgba(212, 175, 55, 0.6);
            box-shadow: var(--shadow-md);
            color: inherit;
        }

        .quick-action-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .quick-action-tile:hover .quick-action-icon {
            transform: scale(1.08);
        }

        .icon-navy { background: rgba(11, 29, 74, 0.08); color: var(--memsys-navy); }
        .icon-gold { background: rgba(212, 175, 55, 0.15); color: #b8960c; }
        .icon-emerald { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .icon-indigo { background: rgba(99, 102, 241, 0.15); color: #4f46e5; }
        .icon-cyan { background: rgba(6, 182, 212, 0.15); color: #0891b2; }
        .icon-rose { background: rgba(244, 63, 94, 0.15); color: #e11d48; }

        body.dark-mode .hero-banner {
            background: linear-gradient(135deg, #071228 0%, #0d1e3d 60%, #14284f 100%);
            border: 1px solid #233554;
        }

        body.dark-mode .quick-action-tile {
            background: #152238;
            border-color: #233554;
            color: #f1f5f9;
        }

        body.dark-mode .quick-action-tile:hover {
            border-color: var(--memsys-gold);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container py-4">
                <!-- Hero Welcome Banner -->
                <div class="hero-banner">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="hero-tag">
                                <i class="fas fa-university"></i>
                                <?= htmlspecialchars($schoolName) ?>
                            </div>
                            <h1 class="fw-bold text-white mb-2" style="font-size: 2rem;">
                                Welcome back, <?= htmlspecialchars($userName) ?>!
                            </h1>
                            <p class="text-light opacity-90 mb-3" style="max-width: 580px; font-size: 0.95rem;">
                                Manage your student member roster, upload annual batch directories, monitor chapter compliance, and view real-time affiliation billing.
                            </p>
                            <div class="d-flex align-items-center gap-2 text-white-50 small">
                                <i class="fas fa-calendar-day"></i>
                                <span><?= htmlspecialchars($currentDate) ?></span>
                                <span>•</span>
                                <span class="badge bg-success text-white"><i class="fas fa-check-circle me-1"></i> Active Officer Session</span>
                            </div>
                        </div>

                        <div class="d-flex flex-column gap-2">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="btn btn-gold">
                                <i class="fas fa-file-import me-1"></i> Upload Directory
                            </a>
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/members/list.php" class="btn btn-outline text-white border-white">
                                <i class="fas fa-users me-1"></i> View Members
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 4 KPI Stat Cards -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-navy">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Enrolled Members</div>
                            <div class="stat-value" id="statMembers"><?= number_format($member_count) ?></div>
                            <div class="stat-desc">Official student roster</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-emerald">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Fees Remitted</div>
                            <div class="stat-value text-success">₱<?= number_format($total_paid, 2) ?></div>
                            <div class="stat-desc">Verified transactions</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-gold">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Chapter Compliance</div>
                            <div class="stat-value" style="font-size: 1.35rem;">In Good Standing</div>
                            <div class="stat-desc">AY <?= date('Y') ?>–<?= date('Y') + 1 ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-indigo">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Directory Batches</div>
                            <div class="stat-value"><?= count($recent_batches) ?></div>
                            <div class="stat-desc">Import submissions</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Action Grid -->
                <div class="mb-4">
                    <h5 class="fw-bold text-dark mb-3">
                        <i class="fas fa-bolt text-gold me-2"></i>Quick Management Actions
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/members/list.php" class="quick-action-tile">
                                <div class="quick-action-icon icon-navy">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">My Chapter Members</h6>
                                    <small class="text-muted">Browse, filter, and inspect enrolled student profiles.</small>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="quick-action-tile">
                                <div class="quick-action-icon icon-gold">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">Upload Member Directory</h6>
                                    <small class="text-muted">Import Excel (.xlsx) workbook for 1st to 4th year members.</small>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/financial/reports.php" class="quick-action-tile">
                                <div class="quick-action-icon icon-emerald">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">Financial & Billing Reports</h6>
                                    <small class="text-muted">Review annual dues assessments, balances, and statements.</small>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/financial/receipts.php" class="quick-action-tile">
                                <div class="quick-action-icon icon-indigo">
                                    <i class="fas fa-receipt"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">Official Receipts</h6>
                                    <small class="text-muted">Upload proof of payment and view verified receipts.</small>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/digital-id/send.php" class="quick-action-tile">
                                <div class="quick-action-icon icon-cyan">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">Issue Digital IDs</h6>
                                    <small class="text-muted">Dispatch dynamic digital membership credentials to students.</small>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/compliance/status.php" class="quick-action-tile">
                                <div class="quick-action-icon icon-rose">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">Compliance Status</h6>
                                    <small class="text-muted">Track chapter accreditation requirements and submissions.</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 2-Column Activity & Batches Area -->
                <div class="row">
                    <!-- Left: Recent Members -->
                    <div class="col-lg-6 mb-4">
                        <div class="card card-navy-top h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0">
                                    <i class="fas fa-user-plus text-primary me-2"></i>Recently Added Members
                                </h5>
                                <a href="<?= BASE_URL ?>/public/portal/school-officer/members/list.php" class="small fw-semibold text-decoration-none">
                                    View All <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>

                            <?php if (empty($recent_members)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-users fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                                    <p class="mb-2">No members enrolled yet for this chapter.</p>
                                    <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-upload me-1"></i> Upload Directory
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Year</th>
                                                <th>Email</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_members as $m): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold text-dark">
                                                            <?= htmlspecialchars($m['full_name'] ?? $m['name'] ?? 'Member') ?>
                                                        </div>
                                                        <small class="text-muted"><?= htmlspecialchars($m['student_number'] ?? 'N/A') ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($m['year_level'] ?? 'N/A') ?></span>
                                                    </td>
                                                    <td class="small text-muted">
                                                        <?= htmlspecialchars($m['email'] ?? '—') ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right: Directory Upload Batches -->
                    <div class="col-lg-6 mb-4">
                        <div class="card card-gold-top h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0">
                                    <i class="fas fa-file-excel text-success me-2"></i>Directory Import Batches
                                </h5>
                                <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="small fw-semibold text-decoration-none">
                                    Upload New <i class="fas fa-plus ms-1"></i>
                                </a>
                            </div>

                            <?php if (empty($recent_batches)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                                    <p class="mb-2">No workbook batches uploaded yet.</p>
                                    <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="btn btn-sm btn-gold">
                                        <i class="fas fa-file-excel me-1"></i> Start First Import
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Batch Reference</th>
                                                <th>Rows</th>
                                                <th>Status</th>
                                                <th>Uploaded</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_batches as $b): ?>
                                                <tr>
                                                    <td>
                                                        <code class="fw-bold"><?= htmlspecialchars($b['id'] ?? 'N/A') ?></code>
                                                        <div class="small text-muted"><?= htmlspecialchars($b['file_name'] ?? 'workbook.xlsx') ?></div>
                                                    </td>
                                                    <td class="fw-semibold">
                                                        <?= number_format($b['total_rows'] ?? 0) ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $st = $b['status'] ?? 'pending';
                                                        $badgeClass = match($st) {
                                                            'completed', 'approved' => 'badge-success',
                                                            'in_progress', 'validated' => 'badge-info',
                                                            'failed', 'rejected' => 'badge-danger',
                                                            default => 'badge-warning'
                                                        };
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($st)) ?></span>
                                                    </td>
                                                    <td class="small text-muted">
                                                        <?= date('M d, Y', strtotime($b['uploaded_at'] ?? 'now')) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Realtime engine -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script>
        window.IECEP_CONFIG = window.IECEP_CONFIG || {
            SUPABASE_URL: <?php echo json_encode(SUPABASE_URL); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(SUPABASE_ANON_KEY); ?>
        };
        window.IECEP_SCHOOL_OFFICER = {
            institutionId: '<?php echo htmlspecialchars($_SESSION['institution_id'] ?? '', ENT_QUOTES); ?>'
        };
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/assets/js/realtime.js" defer></script>
    <script src="/IECEP-LSC-MEMSYS/public/js/realtime.js" defer></script>
</body>
</html>
