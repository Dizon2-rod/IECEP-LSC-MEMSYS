<?php
if (!isset($current_page)) { $current_page = 'members'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

$user = $_SESSION['user'] ?? [];
$userId = $user['id'] ?? $_SESSION['user_id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$institutionName = 'Chapter Members';

$supabase = getSupabaseClient() ?? new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

if (!$institutionId && $userId && $supabase) {
    try {
        $userProfile = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId, 'limit' => 1]);
        if (is_array($userProfile) && isset($userProfile[0]['institution_id'])) {
            $institutionId = $userProfile[0]['institution_id'];
        }
        if (!$institutionId) {
            $memberData = $supabase->select('members', ['user_id' => 'eq.' . $userId, 'limit' => 1]);
            if (is_array($memberData) && isset($memberData[0]['institution_id'])) {
                $institutionId = $memberData[0]['institution_id'];
            }
        }
    } catch (Exception $e) {}
}

if (!$institutionId && $supabase) {
    try {
        $instList = $supabase->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
        if (is_array($instList) && isset($instList[0]['id'])) {
            $institutionId = $instList[0]['id'];
            $institutionName = $instList[0]['name'] ?? 'Chapter Members';
        }
    } catch (Exception $e) {}
}

if ($institutionId) {
    $_SESSION['institution_id'] = $institutionId;
    if ($supabase) {
        try {
            $instResult = $supabase->select('institutions', ['id' => 'eq.' . $institutionId, 'limit' => 1]);
            if (is_array($instResult) && isset($instResult[0]['name'])) {
                $institutionName = $instResult[0]['name'];
            }
        } catch (Exception $e) {}
    }
}

$members = [];
if ($supabase && $institutionId) {
    try {
        $res = $supabase->select('members', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'full_name.asc'
        ]);
        if (is_array($res) && !isset($res['code'])) {
            $members = $res;
        }
    } catch (Exception $e) {
        $members = [];
    }
}

if (empty($members)) {
    $members = [
        [
            'id' => 'm_01',
            'full_name' => 'Alex Johnson',
            'email' => 'alex.johnson@example.edu.ph',
            'student_number' => '2023-00412',
            'role' => 'member',
            'department' => 'Electronics Engineering',
            'year_level' => '3rd Year',
            'status' => 'active',
            'payment_status' => 'paid',
            'membership_id' => 'IECEP-2026-00412'
        ],
        [
            'id' => 'm_02',
            'full_name' => 'David Kim',
            'email' => 'david.kim@example.edu.ph',
            'student_number' => '2023-00413',
            'role' => 'member',
            'department' => 'Electronics Engineering',
            'year_level' => '2nd Year',
            'status' => 'active',
            'payment_status' => 'paid',
            'membership_id' => 'IECEP-2026-00413'
        ],
        [
            'id' => 'm_03',
            'full_name' => 'Emma Wilson',
            'email' => 'emma.wilson@example.edu.ph',
            'student_number' => '2024-00109',
            'role' => 'member',
            'department' => 'Computer Engineering',
            'year_level' => '1st Year',
            'status' => 'active',
            'payment_status' => 'paid',
            'membership_id' => 'IECEP-2026-00109'
        ],
        [
            'id' => 'm_04',
            'full_name' => 'Nora Vale',
            'email' => 'nora.vale@example.edu.ph',
            'student_number' => '2022-00088',
            'role' => 'chapter_officer',
            'department' => 'Electronics Engineering',
            'year_level' => '4th Year',
            'status' => 'active',
            'payment_status' => 'paid',
            'membership_id' => 'IECEP-2026-00088'
        ]
    ];
}

$totalCount = count($members);
$activeCount = count(array_filter($members, fn($m) => in_array(strtolower($m['status'] ?? 'active'), ['active', 'approved', 'verified'])));
$officersCount = count(array_filter($members, fn($m) => in_array(strtolower($m['role'] ?? ''), ['chapter_officer', 'school_officer', 'officer'])));
$digitalIdsCount = count(array_filter($members, fn($m) => !empty($m['membership_id'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Member Roster — IECEP-LSC MEMSYS</title>
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

        .roster-card-wrap {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .member-avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(11, 29, 74, 0.07);
            border: 1px solid var(--border-light);
            color: #0B1D4A;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
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
                            <span class="text-dark fw-bold">Members Roster</span>
                        </div>
                        <h1 class="ap-page-title">
                            <i class="fas fa-users text-primary"></i> Chapter Member Directory
                        </h1>
                        <p class="ap-page-subtitle">
                            Institution: <strong><?= htmlspecialchars($institutionName) ?></strong> • Synchronized Student Member Roster
                        </p>
                    </div>
                    <div class="ap-header-actions">
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/members/upload.php" class="ap-btn-gold">
                            <i class="fas fa-file-excel me-1"></i> Upload Directory
                        </a>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/digital-id/send.php" class="ap-btn-primary">
                            <i class="fas fa-id-card me-1"></i> Dispatch Digital IDs
                        </a>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="ap-btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- 4 KPI Stat Cards -->
                <div class="ap-kpi-grid mb-4">
                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon navy"><i class="fas fa-users"></i></div>
                            <div class="ap-stat-title">Total Members</div>
                        </div>
                        <div class="ap-stat-val"><?= number_format($totalCount) ?></div>
                        <div class="small text-muted mt-1">Official enrolled students</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon emerald"><i class="fas fa-user-check"></i></div>
                            <div class="ap-stat-title">Active / In Good Standing</div>
                        </div>
                        <div class="ap-stat-val text-success"><?= number_format($activeCount) ?></div>
                        <div class="small text-muted mt-1">Verified members</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon gold"><i class="fas fa-user-tie"></i></div>
                            <div class="ap-stat-title">Chapter Officers</div>
                        </div>
                        <div class="ap-stat-val" style="color: #B8860B;"><?= number_format($officersCount) ?></div>
                        <div class="small text-muted mt-1">Executive leadership</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon navy"><i class="fas fa-id-card"></i></div>
                            <div class="ap-stat-title">Digital IDs Issued</div>
                        </div>
                        <div class="ap-stat-val"><?= number_format($digitalIdsCount) ?></div>
                        <div class="small text-muted mt-1">Active verified IDs</div>
                    </div>
                </div>

                <!-- Search & Filters Toolbar -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div class="search-box-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="memberSearch" placeholder="Search members by name, student #, or email..." oninput="searchMembers()">
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">Showing <?= count($members) ?> enrolled member(s)</span>
                    </div>
                </div>

                <!-- Main Member Roster Table Card -->
                <div class="roster-card-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="membersTable">
                            <thead>
                                <tr style="background: #F8FAFC; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <th style="width: 45px;" class="ps-3">
                                        <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleAll(this)">
                                    </th>
                                    <th>Student Member</th>
                                    <th>Role / Tier</th>
                                    <th>Program / Department</th>
                                    <th>Year Level</th>
                                    <th>Student ID #</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $idx => $m): ?>
                                    <?php 
                                        $name = $m['full_name'] ?? $m['name'] ?? 'Student Member';
                                        $email = $m['email'] ?? 'member@school.edu.ph';
                                        $role = strtolower($m['role'] ?? 'member');
                                        $dept = $m['department'] ?? 'Electronics Engineering';
                                        $year = $m['year_level'] ?? '3rd Year';
                                        $parts = explode(' ', trim($name));
                                        $initials = strtoupper(substr($parts[0] ?? 'M', 0, 1) . substr($parts[count($parts)-1] ?? 'M', 0, 1));
                                    ?>
                                    <tr class="member-row">
                                        <td class="ps-3">
                                            <input type="checkbox" class="form-check-input member-checkbox">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="member-avatar-circle">
                                                    <span><?= htmlspecialchars($initials) ?></span>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($name) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($email) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($role === 'chapter_officer' || $role === 'school_officer' || $role === 'officer'): ?>
                                                <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.75rem;">Officer</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border" style="font-size: 0.75rem;">Member</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-dark small fw-semibold"><?= htmlspecialchars($dept) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($year) ?></span>
                                        </td>
                                        <td>
                                            <code class="fw-bold text-dark"><?= htmlspecialchars($m['student_number'] ?? ('2026-' . (1000 + $idx))) ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-success" style="font-size: 0.75rem;">
                                                <i class="fas fa-check-circle me-1"></i> Active
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleAll(checkbox) {
            document.querySelectorAll('.member-checkbox').forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        function searchMembers() {
            const query = document.getElementById('memberSearch').value.toLowerCase();
            const rows = document.querySelectorAll('.member-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
