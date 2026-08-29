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
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/admin-portal.css">
    <style>
        :root {
            --bg-page: #F8FAFC;
            --bg-surface: #FFFFFF;
            --border-light: #E2E8F0;
            --border-hover: #CBD5E1;
            --text-heading: #0B1D4A;
            --text-primary: #0F172A;
            --text-muted: #64748B;
            --color-navy: #0B1D4A;
            --color-gold: #D4AF37;
        }

        body {
            background-color: var(--bg-page) !important;
            font-family: 'DM Sans', 'Inter', -apple-system, sans-serif;
            color: var(--text-primary);
        }

        /* Executive White Hero Banner */
        .officer-hero-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px -2px rgba(11, 29, 74, 0.04), 0 2px 6px -1px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
        }

        .officer-hero-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0B1D4A 0%, #1E3A8A 50%, #D4AF37 100%);
        }

        .chapter-pill-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.85rem;
            background: rgba(11, 29, 74, 0.05);
            border: 1px solid rgba(11, 29, 74, 0.12);
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--color-navy);
            margin-bottom: 0.75rem;
        }

        .officer-hero-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.02em;
            margin-bottom: 0.4rem;
        }

        .officer-hero-desc {
            color: var(--text-muted);
            font-size: 0.92rem;
            max-width: 640px;
            line-height: 1.55;
            margin-bottom: 1rem;
        }

        .hero-meta-strip {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            font-size: 0.82rem;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .badge-verified-session {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(5, 150, 105, 0.1);
            color: #059669;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 50px;
            font-size: 0.78rem;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }

        /* 4 KPI Grid */
        .officer-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .officer-stat-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 1.25rem 1.4rem;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .officer-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(11, 29, 74, 0.07);
            border-color: rgba(212, 175, 55, 0.5);
        }

        .stat-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .icon-box-navy { background: rgba(11, 29, 74, 0.07); color: #0B1D4A; }
        .icon-box-emerald { background: rgba(5, 150, 105, 0.1); color: #059669; }
        .icon-box-gold { background: rgba(212, 175, 55, 0.14); color: #B8860B; }
        .icon-box-indigo { background: rgba(99, 102, 241, 0.12); color: #4F46E5; }
        .icon-box-cyan { background: rgba(2, 132, 199, 0.1); color: #0284C7; }
        .icon-box-rose { background: rgba(225, 29, 72, 0.1); color: #E11D48; }

        .stat-meta-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.15rem;
        }

        .stat-meta-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.2;
            letter-spacing: -0.01em;
        }

        .stat-meta-sub {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        /* Action Tiles Grid */
        .action-tiles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .action-tile-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 1.25rem 1.4rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
        }

        .action-tile-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(11, 29, 74, 0.06);
            border-color: rgba(11, 29, 74, 0.3);
            color: inherit;
        }

        .action-tile-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .action-tile-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Content Card & Tables */
        .white-content-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 1.4rem 1.6rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            height: 100%;
        }

        .card-header-clean {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.15rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-light);
        }

        .card-clean-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .clean-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .clean-table thead th {
            background: #F8FAFC;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid var(--border-light);
            white-space: nowrap;
        }

        .clean-table tbody td {
            padding: 0.75rem 0.85rem;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .clean-table tbody tr:hover {
            background-color: #F8FAFC;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .status-pill.success { background: rgba(5, 150, 105, 0.1); color: #059669; }
        .status-pill.warning { background: rgba(217, 119, 6, 0.1); color: #D97706; }
        .status-pill.info { background: rgba(2, 132, 199, 0.1); color: #0284C7; }
        .status-pill.danger { background: rgba(225, 29, 72, 0.1); color: #E11D48; }

        @media (max-width: 992px) {
            .officer-kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .action-tiles-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .officer-kpi-grid { grid-template-columns: 1fr; }
            .action-tiles-grid { grid-template-columns: 1fr; }
            .officer-hero-card { padding: 1.25rem 1rem; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content ap-scope">
            <div class="container py-4">
                <!-- Executive White Welcome Hero Card -->
                <div class="officer-hero-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="chapter-pill-tag">
                                <i class="fas fa-university text-warning"></i>
                                <?= htmlspecialchars($schoolName) ?>
                            </div>
                            <h1 class="officer-hero-title">
                                Welcome back, <?= htmlspecialchars($userName) ?>!
                            </h1>
                            <p class="officer-hero-desc">
                                Official school chapter administration desk. Manage your student member roster, upload annual batch directories, inspect compliance standing, and monitor affiliation billing statements.
                            </p>
                            <div class="hero-meta-strip">
                                <span><i class="fas fa-calendar-day me-1 text-muted"></i> <?= htmlspecialchars($currentDate) ?></span>
                                <span>•</span>
                                <span class="badge-verified-session">
                                    <i class="fas fa-check-circle"></i> Active Chapter Officer Session
                                </span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="ap-btn-gold">
                                <i class="fas fa-file-import me-1"></i> Upload Directory
                            </a>
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/members/list.php" class="ap-btn-secondary">
                                <i class="fas fa-users me-1"></i> View Members
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 4 KPI Stat Cards Grid -->
                <div class="officer-kpi-grid">
                    <div class="officer-stat-card">
                        <div class="stat-icon-wrap icon-box-navy">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div class="stat-meta-label">Enrolled Members</div>
                            <div class="stat-meta-val" id="statMembers"><?= number_format($member_count) ?></div>
                            <div class="stat-meta-sub">Official student roster</div>
                        </div>
                    </div>

                    <div class="officer-stat-card">
                        <div class="stat-icon-wrap icon-box-emerald">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <div class="stat-meta-label">Fees Remitted</div>
                            <div class="stat-meta-val" style="color: #059669;">₱<?= number_format($total_paid, 2) ?></div>
                            <div class="stat-meta-sub">Verified collections</div>
                        </div>
                    </div>

                    <div class="officer-stat-card">
                        <div class="stat-icon-wrap icon-box-gold">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <div class="stat-meta-label">Chapter Compliance</div>
                            <div class="stat-meta-val" style="font-size: 1.25rem;">In Good Standing</div>
                            <div class="stat-meta-sub">AY <?= date('Y') ?>–<?= date('Y') + 1 ?></div>
                        </div>
                    </div>

                    <div class="officer-stat-card">
                        <div class="stat-icon-wrap icon-box-indigo">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div>
                            <div class="stat-meta-label">Directory Batches</div>
                            <div class="stat-meta-val"><?= count($recent_batches) ?></div>
                            <div class="stat-meta-sub">Import submissions</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Management Action Tiles -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fas fa-bolt text-warning me-2"></i>Chapter Quick Actions
                        </h5>
                    </div>

                    <div class="action-tiles-grid">
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/members/list.php" class="action-tile-card">
                            <div class="stat-icon-wrap icon-box-navy">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <div class="action-tile-title">Chapter Members <i class="fas fa-arrow-right small ms-auto text-muted"></i></div>
                                <div class="action-tile-sub">Search, filter, and inspect enrolled student profiles.</div>
                            </div>
                        </a>

                        <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="action-tile-card">
                            <div class="stat-icon-wrap icon-box-gold">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div>
                                <div class="action-tile-title">Upload Member Directory <i class="fas fa-arrow-right small ms-auto text-muted"></i></div>
                                <div class="action-tile-sub">Import Excel (.xlsx) workbook for 1st to 4th year members.</div>
                            </div>
                        </a>

                        <a href="<?= BASE_URL ?>/public/portal/school-officer/financial/reports.php" class="action-tile-card">
                            <div class="stat-icon-wrap icon-box-emerald">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                <div class="action-tile-title">Financial & Billing Reports <i class="fas fa-arrow-right small ms-auto text-muted"></i></div>
                                <div class="action-tile-sub">Review annual dues assessments, balances, and statements.</div>
                            </div>
                        </a>

                        <a href="<?= BASE_URL ?>/public/portal/school-officer/financial/receipts.php" class="action-tile-card">
                            <div class="stat-icon-wrap icon-box-indigo">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div>
                                <div class="action-tile-title">Official Receipts <i class="fas fa-arrow-right small ms-auto text-muted"></i></div>
                                <div class="action-tile-sub">Upload proof of payment and view verified receipts.</div>
                            </div>
                        </a>

                        <a href="<?= BASE_URL ?>/public/portal/school-officer/digital-id/send.php" class="action-tile-card">
                            <div class="stat-icon-wrap icon-box-cyan">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div>
                                <div class="action-tile-title">Issue Digital IDs <i class="fas fa-arrow-right small ms-auto text-muted"></i></div>
                                <div class="action-tile-sub">Dispatch dynamic digital membership credentials to students.</div>
                            </div>
                        </a>

                        <a href="<?= BASE_URL ?>/public/portal/school-officer/compliance/status.php" class="action-tile-card">
                            <div class="stat-icon-wrap icon-box-rose">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div>
                                <div class="action-tile-title">Compliance Status <i class="fas fa-arrow-right small ms-auto text-muted"></i></div>
                                <div class="action-tile-sub">Track chapter accreditation requirements and submissions.</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- 2-Column Area: Recent Members & Directory Batches -->
                <div class="row g-4 mb-4">
                    <!-- Left: Recent Members -->
                    <div class="col-lg-6">
                        <div class="white-content-card">
                            <div class="card-header-clean">
                                <h3 class="card-clean-title">
                                    <i class="fas fa-user-plus text-primary"></i> Recently Added Members
                                </h3>
                                <a href="<?= BASE_URL ?>/public/portal/school-officer/members/list.php" class="ap-btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
                                    View All <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>

                            <?php if (empty($recent_members)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-users fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                                    <p class="mb-2 small">No members enrolled yet for this chapter.</p>
                                    <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="ap-btn-primary" style="padding: 0.4rem 0.9rem; font-size: 0.78rem;">
                                        <i class="fas fa-upload me-1"></i> Upload Directory
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="clean-table">
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
                                                        <div class="fw-bold text-dark">
                                                            <?= htmlspecialchars($m['full_name'] ?? $m['name'] ?? 'Member') ?>
                                                        </div>
                                                        <small class="text-muted"><?= htmlspecialchars($m['student_number'] ?? 'N/A') ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="status-pill info"><?= htmlspecialchars($m['year_level'] ?? 'N/A') ?></span>
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
                    <div class="col-lg-6">
                        <div class="white-content-card">
                            <div class="card-header-clean">
                                <h3 class="card-clean-title">
                                    <i class="fas fa-file-excel text-success"></i> Directory Import Batches
                                </h3>
                                <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="ap-btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
                                    Upload New <i class="fas fa-plus ms-1"></i>
                                </a>
                            </div>

                            <?php if (empty($recent_batches)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                                    <p class="mb-2 small">No workbook batches uploaded yet.</p>
                                    <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="ap-btn-gold" style="padding: 0.4rem 0.9rem; font-size: 0.78rem;">
                                        <i class="fas fa-file-excel me-1"></i> Start First Import
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="clean-table">
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
                                                        <code class="fw-bold text-dark"><?= htmlspecialchars(substr($b['id'] ?? 'BATCH', 0, 12)) ?>...</code>
                                                        <div class="small text-muted"><?= htmlspecialchars($b['file_name'] ?? 'workbook.xlsx') ?></div>
                                                    </td>
                                                    <td class="fw-bold">
                                                        <?= number_format($b['total_rows'] ?? 0) ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $st = strtolower($b['status'] ?? 'pending');
                                                        $badgeType = match($st) {
                                                            'completed', 'approved', 'success' => 'success',
                                                            'in_progress', 'validated' => 'info',
                                                            'failed', 'rejected' => 'danger',
                                                            default => 'warning'
                                                        };
                                                        ?>
                                                        <span class="status-pill <?= $badgeType ?>"><?= htmlspecialchars(ucfirst($st)) ?></span>
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
    <script src="<?= BASE_URL ?>/public/assets/js/realtime.js" defer></script>
    <script src="<?= BASE_URL ?>/public/js/realtime.js" defer></script>
</body>
</html>
