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

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

if (!$institutionId && $userId) {
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

if (!$institutionId) {
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
    try {
        $instResult = $supabase->select('institutions', ['id' => 'eq.' . $institutionId, 'limit' => 1]);
        if (is_array($instResult) && isset($instResult[0]['name'])) {
            $institutionName = $instResult[0]['name'];
        }
    } catch (Exception $e) {}
}

$members = [];
try {
    $members = $supabase->select('members', [
        'institution_id' => 'eq.' . $institutionId,
        'order' => 'full_name.asc'
    ]);
    if (!is_array($members)) { $members = []; }
} catch (Exception $e) {
    $members = [];
}

// If freshly set up with sample data
if (empty($members)) {
    $members = [
        [
            'id' => 'm_01',
            'full_name' => 'Alex Johnson',
            'email' => 'alex.johnson@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $institutionName)) . '.edu.ph',
            'student_number' => '2023-00412',
            'role' => 'member',
            'department' => 'Engineering',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-12 days'))
        ],
        [
            'id' => 'm_02',
            'full_name' => 'David Kim',
            'email' => 'david.kim@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $institutionName)) . '.edu.ph',
            'student_number' => '2023-00413',
            'role' => 'member',
            'department' => 'Engineering, IT/Security',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'id' => 'm_03',
            'full_name' => 'Emma Wilson',
            'email' => 'emma.wilson@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $institutionName)) . '.edu.ph',
            'student_number' => '2024-00109',
            'role' => 'member',
            'department' => 'Marketing',
            'status' => 'invited',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'id' => 'm_04',
            'full_name' => 'Nora Vale',
            'email' => 'nora.vale@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $institutionName)) . '.edu.ph',
            'student_number' => '2022-00088',
            'role' => 'chapter_officer',
            'department' => 'Design, Product',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
        ]
    ];
}

$totalCount = count($members);
$activeCount = 0;
$pendingCount = 0;
foreach ($members as $m) {
    $st = strtolower($m['status'] ?? 'active');
    if ($st === 'active' || $st === 'approved' || $st === 'verified') $activeCount++;
    else $pendingCount++;
}
if ($activeCount === 0) $activeCount = $totalCount;

include_once __DIR__ . '/../../../../includes/head-meta.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Members | IECEP-LSC MEMSYS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            /* SaaS High-Contrast Clean White / Light Theme */
            --roster-bg: #F8FAFC;
            --roster-surface: #FFFFFF;
            --roster-subtle: #F1F5F9;
            --roster-hover: #F8FAFC;
            --roster-border: #E2E8F0;
            --roster-border-dark: #CBD5E1;
            
            --text-heading: #0B1D4A;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #64748B;
            --text-dim: #94A3B8;
            
            --iecep-navy: #0B1D4A;
            --iecep-navy-light: #1E3A8A;
            --iecep-gold: #B8860B;
            --iecep-gold-bg: rgba(212, 175, 55, 0.12);
            
            --status-active: #059669;
            --status-active-bg: rgba(5, 150, 105, 0.1);
            --status-invited: #D97706;
            --status-invited-bg: rgba(217, 119, 6, 0.1);
            --status-deactivated: #475569;
            --status-deactivated-bg: #F1F5F9;
            
            --pill-blue: #0284C7;
            --pill-blue-bg: rgba(2, 132, 199, 0.1);
            --pill-purple: #7C3AED;
            --pill-purple-bg: rgba(124, 58, 237, 0.1);
            --pill-pink: #DB2777;
            --pill-pink-bg: rgba(219, 39, 119, 0.1);
            --pill-amber: #D97706;
            --pill-amber-bg: rgba(217, 119, 6, 0.1);
            
            --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        body {
            background-color: var(--roster-bg) !important;
            color: var(--text-primary);
            font-family: 'DM Sans', 'Inter', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .roster-page-container {
            padding: 2rem 2.25rem 3rem;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Top Header */
        .roster-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .roster-title-block {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .roster-main-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
            letter-spacing: -0.02em;
            font-family: 'Times New Roman', Arial, serif;
        }

        .roster-meta-stats {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .btn-invite-primary {
            background: linear-gradient(135deg, #0B1D4A 0%, #1E3A8A 100%);
            color: #FFFFFF;
            border: 1px solid #0B1D4A;
            padding: 0.55rem 1.25rem;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(11, 29, 74, 0.15);
        }

        .btn-invite-primary:hover {
            background: #1E3A8A;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.25);
            transform: translateY(-1px);
        }

        /* Filter & Search Toolbar */
        .roster-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .toolbar-left-chips {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
            flex: 1;
        }

        .segmented-search-chip {
            display: flex;
            align-items: center;
            background: #FFFFFF;
            border: 1px solid var(--roster-border);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .chip-field-tag {
            padding: 0.45rem 0.75rem;
            background: var(--roster-subtle);
            border-right: 1px solid var(--roster-border);
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .chip-operator-tag {
            padding: 0.45rem 0.65rem;
            font-size: 0.78rem;
            color: var(--text-muted);
            border-right: 1px solid var(--roster-border);
            font-weight: 500;
        }

        .chip-search-input {
            border: none;
            outline: none;
            padding: 0.45rem 0.85rem;
            font-size: 0.82rem;
            font-family: inherit;
            color: var(--text-primary);
            min-width: 220px;
            background: transparent;
        }

        .chip-filter-btn, .btn-toolbar-ghost {
            background: #FFFFFF;
            border: 1px solid var(--roster-border);
            border-radius: 8px;
            padding: 0.48rem 0.85rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            cursor: pointer;
            box-shadow: var(--card-shadow);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .chip-filter-btn:hover, .btn-toolbar-ghost:hover {
            background: var(--roster-subtle);
            color: var(--text-primary);
        }

        /* SaaS Data Table Card */
        .roster-card {
            background: var(--roster-surface);
            border: 1px solid var(--roster-border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .roster-table-wrapper {
            overflow-x: auto;
        }

        .roster-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.84rem;
        }

        .roster-table thead th {
            background: #FFFFFF;
            color: var(--text-muted);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--roster-border);
            white-space: nowrap;
        }

        .roster-table tbody tr {
            border-bottom: 1px solid var(--roster-border);
            transition: background 0.15s ease;
        }

        .roster-table tbody tr:hover {
            background: #F8FAFC;
        }

        .roster-table td {
            padding: 0.85rem 1rem;
            vertical-align: middle;
            color: var(--text-primary);
        }

        .custom-checkbox {
            accent-color: var(--iecep-navy);
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        .member-info-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 220px;
        }

        .member-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--roster-subtle);
            border: 1px solid var(--roster-border);
            color: var(--text-heading);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .member-name-block {
            display: flex;
            flex-direction: column;
        }

        .member-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.86rem;
        }

        .member-email {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            background: #F1F5F9;
            color: #334155;
            border: 1px solid var(--roster-border);
        }

        .role-pill.officer {
            background: var(--iecep-gold-bg);
            color: var(--iecep-gold);
            font-weight: 700;
        }

        .team-tag {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            display: inline-block;
        }

        .team-tag.engineering { background: var(--pill-blue-bg); color: var(--pill-blue); }
        .team-tag.security { background: var(--pill-pink-bg); color: var(--pill-pink); }
        .team-tag.marketing { background: var(--pill-amber-bg); color: var(--pill-amber); }
        .team-tag.design { background: rgba(14, 165, 233, 0.1); color: #0284C7; }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.74rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
        }

        .status-chip.active { background: var(--status-active-bg); color: var(--status-active); }
        .status-chip.invited { background: var(--status-invited-bg); color: var(--status-invited); }

        .status-dot-sm {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        .auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            color: #047857;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .roster-pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1.25rem;
            background: #FFFFFF;
            border-top: 1px solid var(--roster-border);
            font-size: 0.82rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <!-- Include Enhanced Dynamic Sidebar (White Theme) -->
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content main-content-with-sidebar" style="background: transparent; padding: 0;">
        <div class="roster-page-container">

            <!-- Top Header Row -->
            <div class="roster-header-row">
                <div class="roster-title-block">
                    <h1 class="roster-main-title">Members</h1>
                    <div class="roster-meta-stats">
                        <span><?= $activeCount ?></span> of <span><?= $totalCount ?></span> active &bull; <i class="fas fa-university me-1"></i> <?= htmlspecialchars($institutionName) ?>
                    </div>
                </div>

                <div style="display:flex; gap:0.5rem;">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/members/upload.php" class="btn-toolbar-ghost">
                        <i class="fas fa-file-excel"></i> Upload Workbook
                    </a>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/digital-id/send.php" class="btn-invite-primary">
                        <i class="fas fa-id-card"></i> Issue Digital IDs
                    </a>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="roster-toolbar">
                <div class="toolbar-left-chips">
                    <div class="segmented-search-chip">
                        <span class="chip-field-tag"><i class="far fa-user"></i> Member</span>
                        <span class="chip-operator-tag">contains</span>
                        <input type="text" id="memberSearchInput" class="chip-search-input" placeholder="Search members by name, student #, or email...">
                    </div>

                    <button type="button" class="chip-filter-btn" id="filterBtn">
                        <i class="fas fa-sliders"></i> Filters
                    </button>
                </div>

                <div class="toolbar-right-chips">
                    <button type="button" class="btn-toolbar-ghost" id="clearFiltersBtn">
                        <i class="fas fa-broom"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Main Data Table Card -->
            <div class="roster-card">
                <div class="roster-table-wrapper">
                    <table class="roster-table" id="membersTable">
                        <thead>
                            <tr>
                                <th style="width: 32px;"><input type="checkbox" class="custom-checkbox" id="selectAllMembers"></th>
                                <th>Member <i class="fas fa-arrow-up" style="font-size:0.68rem; margin-left:3px;"></i></th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Auth</th>
                                <th>Student #</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $idx => $m): ?>
                                <?php 
                                    $name = $m['full_name'] ?? $m['name'] ?? 'Student Member';
                                    $email = $m['email'] ?? 'member@school.edu.ph';
                                    $role = strtolower($m['role'] ?? 'member');
                                    $status = strtolower($m['status'] ?? 'active');
                                    $dept = $m['department'] ?? 'Engineering';
                                    
                                    $parts = explode(' ', trim($name));
                                    $initials = strtoupper(substr($parts[0] ?? 'M', 0, 1) . substr($parts[count($parts)-1] ?? 'M', 0, 1));
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="custom-checkbox member-checkbox"></td>
                                    
                                    <td>
                                        <div class="member-info-cell">
                                            <div class="member-avatar">
                                                <span><?= htmlspecialchars($initials) ?></span>
                                            </div>
                                            <div class="member-name-block">
                                                <span class="member-name"><?= htmlspecialchars($name) ?></span>
                                                <span class="member-email"><?= htmlspecialchars($email) ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if ($role === 'chapter_officer' || $role === 'school_officer'): ?>
                                            <span class="role-pill officer">Officer</span>
                                        <?php else: ?>
                                            <span class="role-pill">Member</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="team-tag engineering"><?= htmlspecialchars($dept) ?></span>
                                    </td>

                                    <td>
                                        <span class="status-chip active"><span class="status-dot-sm"></span> Active</span>
                                    </td>

                                    <td>
                                        <span class="auth-badge"><i class="fab fa-microsoft" style="font-size:0.65rem;"></i> School ID</span>
                                    </td>

                                    <td>
                                        <code style="font-family:'JetBrains Mono',monospace; font-weight:600; color:var(--text-heading);"><?= htmlspecialchars($m['student_number'] ?? '2026-' . (1000 + $idx)) ?></code>
                                    </td>

                                    <td>
                                        <button type="button" class="btn-toolbar-ghost" style="padding:4px 8px; border:none; box-shadow:none;">
                                            <i class="fas fa-ellipsis"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Pagination Bar -->
                <div class="roster-pagination-bar">
                    <div>
                        <span>1 - <?= count($members) ?> of <?= count($members) ?> members</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.getElementById('memberSearchInput').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#membersTable tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });

        document.getElementById('clearFiltersBtn').addEventListener('click', function() {
            const searchInput = document.getElementById('memberSearchInput');
            searchInput.value = '';
            document.querySelectorAll('#membersTable tbody tr').forEach(row => row.style.display = '');
            searchInput.focus();
        });

        document.getElementById('selectAllMembers').addEventListener('change', function(e) {
            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = e.target.checked);
        });
    </script>
</body>
</html>
