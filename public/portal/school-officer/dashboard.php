<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'dashboard';

require_once __DIR__ . '/../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'School Officer Command Center';
$user = get_user_info();
$userName = $user['full_name'] ?? $user['name'] ?? $user['email'] ?? 'School Officer';
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Affiliated School Chapter';
$schoolAcronym = 'IECEP-SC';

$supabase = getSupabaseClient();

// Resolve Institution ID & Details from Database
if ($supabase) {
    try {
        if (!$institutionId && $userId) {
            $profiles = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
            if (is_array($profiles) && isset($profiles[0]['institution_id'])) {
                $institutionId = $profiles[0]['institution_id'];
            }
            if (!$institutionId) {
                $members = $supabase->select('members', ['user_id' => 'eq.' . $userId]);
                if (is_array($members) && isset($members[0]['institution_id'])) {
                    $institutionId = $members[0]['institution_id'];
                }
            }
        }

        if ($institutionId) {
            $institutions = $supabase->select('institutions', ['id' => 'eq.' . $institutionId]);
            if (is_array($institutions) && isset($institutions[0]['name'])) {
                $schoolName = $institutions[0]['name'];
                $schoolAcronym = $institutions[0]['acronym'] ?? 'IECEP-SC';
            }
        } else {
            $institutions = $supabase->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
            if (is_array($institutions) && isset($institutions[0]['id'])) {
                $institutionId = $institutions[0]['id'];
                $schoolName = $institutions[0]['name'] ?? $schoolName;
                $schoolAcronym = $institutions[0]['acronym'] ?? 'IECEP-SC';
            }
        }
    } catch (Exception $e) {
        error_log("School resolution error: " . $e->getMessage());
    }
}

if ($institutionId) {
    $_SESSION['institution_id'] = $institutionId;
}

// Fetch 100% Real Database Metrics
$membersList = [];
$totalPaid = 0;
$recentMembers = [];
$recentBatches = [];
$upcomingEvents = [];

if ($institutionId && $supabase) {
    // 1. Members
    try {
        $mems = $supabase->select('members', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'created_at.desc'
        ]);
        if (is_array($mems) && !isset($mems['code'])) {
            $membersList = $mems;
            $recentMembers = array_slice($mems, 0, 5);
        }
    } catch (Exception $e) {}

    // 2. Transactions / Collections
    try {
        $txs = $supabase->select('transactions', [
            'institution_id' => 'eq.' . $institutionId
        ]);
        if (is_array($txs) && !isset($txs['code'])) {
            foreach ($txs as $t) {
                if (strtolower($t['status'] ?? '') === 'paid') {
                    $totalPaid += floatval($t['amount'] ?? 0);
                }
            }
        }
    } catch (Exception $e) {}

    // 3. Upload Batches
    try {
        $batches = $supabase->select('upload_batches', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'uploaded_at.desc',
            'limit' => 4
        ]);
        if (is_array($batches) && !isset($batches['code'])) {
            $recentBatches = $batches;
        }
    } catch (Exception $e) {}

    // 4. Events
    try {
        $evs = $supabase->select('events', [
            'order' => 'start_date.desc',
            'limit' => 3
        ]);
        if (is_array($evs) && !isset($evs['code'])) {
            $upcomingEvents = $evs;
        }
    } catch (Exception $e) {}
}

$memberCount = count($membersList);
$activePaidMembers = count(array_filter($membersList, fn($m) => strtolower($m['payment_status'] ?? '') === 'paid' || !empty($m['is_paid'])));
$complianceRate = ($memberCount > 0) ? round(($activePaidMembers / $memberCount) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="School Officer Command Desk for Chapter Members, Compliance, and Dues.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --bg-page: #F8FAFC;
            --border-color: #E2E8F0;
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

        .main-content {
            margin-left: 260px;
            padding: 1.25rem;
            min-height: 100vh;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dash-header-banner {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
        }
        .dash-header-title {
            margin: 0 0 0.15rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dash-header-sub {
            margin: 0;
            font-size: 0.8rem;
            color: #64748B;
        }

        .mobile-toggle-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #F1F5F9;
            border: 1px solid var(--border-color);
            color: var(--color-navy);
            font-size: 1rem;
            cursor: pointer;
            flex-shrink: 0;
        }

        .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            color: #0F172A;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.18s ease;
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            transform: translateY(-1px);
        }

        .btn-primary-navy {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.95rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--color-navy);
            border: 1px solid var(--color-navy);
            color: #FFFFFF !important;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(11, 29, 74, 0.15);
            transition: all 0.18s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
            color: #FDE047 !important;
        }

        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            min-width: 0;
        }
        .kpi-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
        }

        /* 3-Column Quick Actions Grid */
        .officer-actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        .officer-action-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.85rem 1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            transition: all 0.18s ease;
            color: inherit;
        }
        .officer-action-card:hover {
            transform: translateY(-2px);
            border-color: var(--color-navy);
            box-shadow: 0 6px 16px rgba(11, 29, 74, 0.08);
        }

        .ap-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem;
        }
        .ap-card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFFFFF;
        }
        .ap-card-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 800;
            color: #0F172A;
        }

        .ap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            text-align: left;
        }
        .ap-table th {
            background: #F8FAFC;
            color: #64748B;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
        }
        .ap-table td {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: #334155;
            vertical-align: middle;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 0.85rem; }
            .mobile-toggle-btn { display: inline-flex; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .officer-actions-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: 1fr; }
            .officer-actions-grid { grid-template-columns: 1fr; }
            .dash-header-banner { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                    <button type="button" id="sidebarToggle" class="mobile-toggle-btn" aria-label="Toggle Navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="dash-header-title">
                            <i class="fas fa-university" style="color:var(--color-navy);"></i>
                            <?= htmlspecialchars($schoolName) ?>
                        </h1>
                        <p class="dash-header-sub">
                            School Chapter Command Desk &bull; Official Officer Session: <strong><?= htmlspecialchars($userName) ?></strong>
                        </p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/school-officer/compliance/status.php" class="btn-white">
                        <i class="fas fa-shield-halved" style="color:var(--color-blue);"></i> Compliance Status
                    </a>
                    <a href="<?= PORTAL_URL ?>/school-officer/members/upload.php" class="btn-primary-navy">
                        <i class="fas fa-cloud-arrow-up" style="color:#FDE047;"></i> Upload Directory
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="kpi-val"><?= number_format($memberCount) ?></div>
                        <div class="kpi-lbl">Total Enrolled Members</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= number_format($activePaidMembers) ?></div>
                        <div class="kpi-lbl">Verified Paid Members</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-peso-sign"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#B45309;">₱<?= number_format($totalPaid, 2) ?></div>
                        <div class="kpi-lbl">Chapter Collections Remitted</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-shield-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $complianceRate ?>%</div>
                        <div class="kpi-lbl">Accreditation Score</div>
                    </div>
                </div>
            </div>

            <!-- 3. Quick Action Hub -->
            <div class="officer-actions-grid">
                <a href="<?= PORTAL_URL ?>/school-officer/members/list.php" class="officer-action-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-address-book"></i></div>
                    <div>
                        <strong style="font-size:0.84rem; color:#0F172A; display:block;">Chapter Roster</strong>
                        <span style="font-size:0.72rem; color:#64748B;">Manage student profiles and statuses</span>
                    </div>
                </a>

                <a href="<?= PORTAL_URL ?>/school-officer/attendance.php" class="officer-action-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-clipboard-user"></i></div>
                    <div>
                        <strong style="font-size:0.84rem; color:#0F172A; display:block;">Event Attendance</strong>
                        <span style="font-size:0.72rem; color:#64748B;">Track seminar & workshop attendance</span>
                    </div>
                </a>

                <a href="<?= PORTAL_URL ?>/school-officer/digital-id/send.php" class="officer-action-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-id-card"></i></div>
                    <div>
                        <strong style="font-size:0.84rem; color:#0F172A; display:block;">Digital ID Dispatch</strong>
                        <span style="font-size:0.72rem; color:#64748B;">Send verified QR IDs to members</span>
                    </div>
                </a>

                <a href="<?= PORTAL_URL ?>/school-officer/financial/reports.php" class="officer-action-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-receipt"></i></div>
                    <div>
                        <strong style="font-size:0.84rem; color:#0F172A; display:block;">Financial Reports</strong>
                        <span style="font-size:0.72rem; color:#64748B;">Ledger and payment breakdown</span>
                    </div>
                </a>

                <a href="<?= PORTAL_URL ?>/school-officer/documents/list.php" class="officer-action-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-folder-open"></i></div>
                    <div>
                        <strong style="font-size:0.84rem; color:#0F172A; display:block;">Document Archive</strong>
                        <span style="font-size:0.72rem; color:#64748B;">Upload CBL and chapter endorsements</span>
                    </div>
                </a>

                <a href="<?= PORTAL_URL ?>/school-officer/announcements/list.php" class="officer-action-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-bullhorn"></i></div>
                    <div>
                        <strong style="font-size:0.84rem; color:#0F172A; display:block;">Announcements</strong>
                        <span style="font-size:0.72rem; color:#64748B;">View regional bulletins and memos</span>
                    </div>
                </a>
            </div>

            <!-- 4. Tables Grid: Recent Members & Upload History -->
            <div style="display:grid; grid-template-columns: 1.2fr 0.8fr; gap:0.85rem;">
                
                <!-- Recent Members -->
                <div class="ap-card">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-users"></i> Recently Registered Members</h3>
                        <a href="<?= PORTAL_URL ?>/school-officer/members/list.php" style="font-size:0.74rem; color:var(--color-navy); font-weight:700; text-decoration:none;">View All &rarr;</a>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="ap-table">
                            <thead>
                                <tr>
                                    <th>Member Name</th>
                                    <th>Student Number</th>
                                    <th>Payment Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentMembers)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding:1.75rem; color:#64748B;">
                                            <i class="fas fa-users-slash" style="font-size:1.5rem; color:#CBD5E1; margin-bottom:0.35rem; display:block;"></i>
                                            <strong style="color:#0F172A; font-size:0.82rem;">No Members Recorded Yet</strong>
                                            <p style="margin:0.15rem 0 0; font-size:0.72rem;">Upload the student masterlist to populate chapter records.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentMembers as $m): ?>
                                        <?php $paid = strtolower($m['payment_status'] ?? '') === 'paid' || !empty($m['is_paid']); ?>
                                        <tr>
                                            <td>
                                                <strong style="color:#0F172A; font-size:0.82rem;"><?= htmlspecialchars($m['full_name'] ?? 'Student Member') ?></strong><br>
                                                <span style="font-size:0.7rem; color:#64748B;"><?= htmlspecialchars($m['email'] ?? '') ?></span>
                                            </td>
                                            <td style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; color:var(--color-navy); font-weight:700;">
                                                <?= htmlspecialchars($m['student_number'] ?? $m['membership_id'] ?? 'Pending') ?>
                                            </td>
                                            <td>
                                                <?php if ($paid): ?>
                                                    <span class="ap-pill active"><span class="ap-pill-dot"></span> Paid</span>
                                                <?php else: ?>
                                                    <span class="ap-pill pending">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Upload Batches -->
                <div class="ap-card">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-file-import"></i> Roster Upload History</h3>
                        <a href="<?= PORTAL_URL ?>/school-officer/members/upload.php" style="font-size:0.74rem; color:var(--color-navy); font-weight:700; text-decoration:none;">Upload &rarr;</a>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="ap-table">
                            <thead>
                                <tr>
                                    <th>Batch / File</th>
                                    <th>Uploaded</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentBatches)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding:1.75rem; color:#64748B;">
                                            <i class="fas fa-cloud-arrow-up" style="font-size:1.5rem; color:#CBD5E1; margin-bottom:0.35rem; display:block;"></i>
                                            <strong style="color:#0F172A; font-size:0.82rem;">No Batch Uploads Yet</strong>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBatches as $b): ?>
                                        <tr>
                                            <td>
                                                <strong style="color:#0F172A; font-size:0.8rem;"><?= htmlspecialchars($b['filename'] ?? 'Masterlist.xlsx') ?></strong><br>
                                                <span style="font-size:0.7rem; color:#64748B;"><?= intval($b['records_count'] ?? $b['total_rows'] ?? 0) ?> rows</span>
                                            </td>
                                            <td style="color:#64748B; font-size:0.72rem; white-space:nowrap;"><?= !empty($b['uploaded_at']) ? date('M d, Y', strtotime($b['uploaded_at'])) : 'Recent' ?></td>
                                            <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Processed</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>
</body>
</html>
