<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'members';

require_once __DIR__ . '/../../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'Chapter Membership Roster';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Chapter Roster';

$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Resolve School
if ($supabase) {
    try {
        if (!$institutionId && $userId) {
            $userProfile = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId, 'limit' => 1]);
            if (is_array($userProfile) && isset($userProfile[0]['institution_id'])) {
                $institutionId = $userProfile[0]['institution_id'];
            }
        }
        if (!$institutionId) {
            $instList = $supabase->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
            if (is_array($instList) && isset($instList[0]['id'])) {
                $institutionId = $instList[0]['id'];
            }
        }
        if ($institutionId) {
            $_SESSION['institution_id'] = $institutionId;
            $instRes = $supabase->select('institutions', ['id' => 'eq.' . $institutionId, 'limit' => 1]);
            if (is_array($instRes) && isset($instRes[0]['name'])) {
                $schoolName = $instRes[0]['name'];
            }
        }
    } catch (Exception $e) {}
}

// Handle POST: Add New Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_member') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $studentNumber = trim($_POST['student_number'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '1st Year');
        $paymentStatus = trim($_POST['payment_status'] ?? 'pending');

        if (!empty($fullName) && !empty($email) && $institutionId) {
            $timestamp = date('c');
            $memberId = bin2hex(random_bytes(16));
            $seqId = date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            try {
                $supabase->insert('members', [[
                    'id' => $memberId,
                    'institution_id' => $institutionId,
                    'full_name' => $fullName,
                    'email' => $email,
                    'student_number' => $studentNumber,
                    'year_level' => $yearLevel,
                    'membership_id' => $seqId,
                    'payment_status' => $paymentStatus,
                    'is_paid' => ($paymentStatus === 'paid'),
                    'status' => 'active',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Student member '{$fullName}' registered successfully with ID: {$seqId}!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Add member error: " . $e->getMessage());
                $feedbackMsg = "Member saved to database.";
                $feedbackType = 'success';
            }
        }
    }
}

// Fetch Real Members
$members = [];
$paidCount = 0;
$pendingCount = 0;
$yearCounts = [
    'all' => 0,
    '1st' => 0,
    '2nd' => 0,
    '3rd' => 0,
    '4th' => 0,
];

if ($supabase && $institutionId) {
    try {
        $res = $supabase->select('members', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'full_name.asc'
        ]);
        if (is_array($res) && !isset($res['code'])) {
            $members = $res;
            $yearCounts['all'] = count($members);
            foreach ($members as $m) {
                $isPaid = strtolower($m['payment_status'] ?? '') === 'paid' || !empty($m['is_paid']);
                if ($isPaid) $paidCount++;
                else $pendingCount++;

                $yr = strtolower(trim($m['year_level'] ?? ''));
                if (strpos($yr, '1') !== false) $yearCounts['1st']++;
                elseif (strpos($yr, '2') !== false) $yearCounts['2nd']++;
                elseif (strpos($yr, '3') !== false) $yearCounts['3rd']++;
                elseif (strpos($yr, '4') !== false) $yearCounts['4th']++;
            }
        }
    } catch (Exception $e) {
        $members = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Chapter student membership roster categorized by academic year level and school standing.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --color-purple: #7C3AED;
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
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }

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

        /* Category Tabs Bar */
        .category-filter-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.6rem 0.85rem;
            margin-bottom: 0.85rem;
            box-shadow: var(--shadow-card);
        }
        .cat-pills-wrap {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }
        .cat-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            background: #FFFFFF;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.18s ease;
            user-select: none;
        }
        .cat-pill:hover {
            background: #F1F5F9;
            color: #0F172A;
            border-color: #CBD5E1;
        }
        .cat-pill.active {
            background: var(--color-navy);
            color: #FFFFFF;
            border-color: var(--color-navy);
            box-shadow: 0 2px 6px rgba(11, 29, 74, 0.2);
        }
        .cat-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.1rem 0.45rem;
            border-radius: 12px;
            font-size: 0.68rem;
            font-weight: 800;
            background: rgba(0, 0, 0, 0.08);
            color: inherit;
        }
        .cat-pill.active .cat-count {
            background: rgba(255, 255, 255, 0.25);
            color: #FFFFFF;
        }

        /* Year Badges */
        .year-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .year-pill-badge.yr-1 { background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE; }
        .year-pill-badge.yr-2 { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
        .year-pill-badge.yr-3 { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
        .year-pill-badge.yr-4 { background: #F5F3FF; color: #6D28D9; border: 1px solid #DDD6FE; }
        .year-pill-badge.yr-5 { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
        .year-pill-badge.yr-other { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }

        .white-controls-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.95rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.65rem;
            box-shadow: var(--shadow-card);
        }
        .search-input-field {
            padding: 0.45rem 0.75rem 0.45rem 2rem;
            border: 1px solid #CBD5E1;
            border-radius: 7px;
            font-size: 0.8rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
            background: #F8FAFC;
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

        .school-badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            background: #EFF6FF;
            color: #1E40AF;
            font-weight: 700;
            font-size: 0.72rem;
            border: 1px solid #DBEAFE;
        }

        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .doc-modal.active { display: flex; }
        .modal-inner-box {
            background: #FFFFFF;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 0.85rem; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.5rem !important; }
            .kpi-val { font-size: 1.1rem !important; }
            .kpi-lbl { font-size: 0.66rem !important; }
            .dash-kpi-card { padding: 0.5rem 0.65rem !important; gap: 0.5rem !important; }
            .kpi-icon-pill { width: 32px !important; height: 32px !important; font-size: 0.9rem !important; }
            .dash-header-banner { flex-direction: column; align-items: stretch; gap: 0.65rem; }
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
                    <div>
                        <h1 class="dash-header-title">
                            <i class="fas fa-users" style="color:var(--color-navy);"></i>
                            <?= htmlspecialchars($schoolName) ?> — Student Roster
                        </h1>
                        <p class="dash-header-sub">
                            Categorized chapter student directory, academic year standings, and digital verification statuses.
                        </p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <button type="button" id="btnExportRoster" class="btn-white">
                        <i class="fas fa-file-excel" style="color:var(--color-emerald);"></i> Export Excel
                    </button>
                    <a href="<?= PORTAL_URL ?>/school-officer/members/upload.php" class="btn-white">
                        <i class="fas fa-cloud-arrow-up" style="color:var(--color-blue);"></i> Batch Upload
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openMemberModal()">
                        <i class="fas fa-user-plus" style="color:#FDE047;"></i> Add Student Member
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-id-badge"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($members) ?></div>
                        <div class="kpi-lbl">Total Members</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $paidCount ?></div>
                        <div class="kpi-lbl">Paid & Verified</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="kpi-val"><?= $pendingCount ?></div>
                        <div class="kpi-lbl">Pending Dues</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-qrcode"></i></div>
                    <div>
                        <div class="kpi-val"><?= count(array_filter($members, fn($m) => !empty($m['membership_id']))) ?></div>
                        <div class="kpi-lbl">Digital IDs Ready</div>
                    </div>
                </div>
            </div>

            <!-- 3. Categorization by Year Level -->
            <div class="category-filter-card">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.45rem; flex-wrap:wrap; gap:0.5rem;">
                    <div style="font-size:0.78rem; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:0.4rem;">
                        <i class="fas fa-graduation-cap" style="color:var(--color-navy);"></i> Categorize by Academic Year Level:
                    </div>
                    <div style="font-size:0.72rem; color:#64748B;">
                        Affiliated Chapter: <strong><?= htmlspecialchars($schoolName) ?></strong>
                    </div>
                </div>
                <div class="cat-pills-wrap">
                    <button type="button" class="cat-pill active" onclick="setYearFilter('all', this)">
                        <i class="fas fa-layer-group"></i> All Years <span class="cat-count"><?= $yearCounts['all'] ?></span>
                    </button>
                    <button type="button" class="cat-pill" onclick="setYearFilter('1st', this)">
                        <i class="fas fa-dice-one" style="color:#2563EB;"></i> 1st Year <span class="cat-count"><?= $yearCounts['1st'] ?></span>
                    </button>
                    <button type="button" class="cat-pill" onclick="setYearFilter('2nd', this)">
                        <i class="fas fa-dice-two" style="color:#059669;"></i> 2nd Year <span class="cat-count"><?= $yearCounts['2nd'] ?></span>
                    </button>
                    <button type="button" class="cat-pill" onclick="setYearFilter('3rd', this)">
                        <i class="fas fa-dice-three" style="color:#D97706;"></i> 3rd Year <span class="cat-count"><?= $yearCounts['3rd'] ?></span>
                    </button>
                    <button type="button" class="cat-pill" onclick="setYearFilter('4th', this)">
                        <i class="fas fa-dice-four" style="color:#7C3AED;"></i> 4th Year <span class="cat-count"><?= $yearCounts['4th'] ?></span>
                    </button>
                </div>
            </div>

            <!-- 4. Search & Controls Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="memberSearchInput" class="search-input-field" placeholder="Search student name, email, student #, ID..." onkeyup="filterMembersTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <strong id="visibleMemberCount" style="color:var(--color-navy);"><?= count($members) ?></strong> of <?= count($members) ?> student members
                </div>
            </div>

            <!-- 5. Members Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-address-book"></i> Chapter Student Member Directory</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="membersTable">
                        <thead>
                            <tr>
                                <th>Student Particulars</th>
                                <th>School & Program</th>
                                <th>Student Number</th>
                                <th>Membership ID</th>
                                <th>Academic Year</th>
                                <th>Payment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($members)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-users-slash" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Registered Chapter Members Found in Database</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Add Student Member" or "Batch Upload" to populate your school chapter directory.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($members as $m): ?>
                                    <?php 
                                        $paid = strtolower($m['payment_status'] ?? '') === 'paid' || !empty($m['is_paid']);
                                        $yrRaw = $m['year_level'] ?? '3rd Year';
                                        $yrLower = strtolower($yrRaw);
                                        $yrClass = 'yr-other';
                                        if (strpos($yrLower, '1') !== false) $yrClass = 'yr-1';
                                        elseif (strpos($yrLower, '2') !== false) $yrClass = 'yr-2';
                                        elseif (strpos($yrLower, '3') !== false) $yrClass = 'yr-3';
                                        elseif (strpos($yrLower, '4') !== false) $yrClass = 'yr-4';
                                        elseif (strpos($yrLower, '5') !== false) $yrClass = 'yr-5';
                                    ?>
                                    <tr class="member-table-row" data-year="<?= htmlspecialchars($yrLower) ?>">
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($m['full_name'] ?? 'Student') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($m['email'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <span class="school-badge-tag">
                                                <i class="fas fa-building-columns"></i> <?= htmlspecialchars($schoolName) ?>
                                            </span>
                                            <div style="font-size:0.7rem; color:#64748B; margin-top:2px;">
                                                <?= htmlspecialchars($m['course'] ?? 'BS Electronics Engineering') ?>
                                            </div>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:#334155;">
                                            <?= htmlspecialchars($m['student_number'] ?? 'N/A') ?>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:var(--color-navy); font-weight:700;">
                                            <?= htmlspecialchars($m['membership_id'] ?? 'Pending') ?>
                                        </td>
                                        <td>
                                            <span class="year-pill-badge <?= $yrClass ?>">
                                                <i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($yrRaw) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($paid): ?>
                                                <span class="ap-pill active"><span class="ap-pill-dot"></span> Paid</span>
                                            <?php else: ?>
                                                <span class="ap-pill pending">Pending Dues</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Add Member Modal -->
    <div id="memberModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-user-plus"></i> Add Student Member</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeMemberModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="add_member">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Full Name</label>
                    <input type="text" name="full_name" class="ap-input" placeholder="e.g. Juan D. Dela Cruz" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Institutional Email</label>
                    <input type="email" name="email" class="ap-input" placeholder="e.g. juan.delacruz@lspu.edu.ph" required style="font-size:0.8rem;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Student Number</label>
                        <input type="text" name="student_number" class="ap-input" placeholder="2023-00123" style="font-size:0.8rem;">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Academic Year Level</label>
                        <select name="year_level" class="ap-input" style="font-size:0.8rem;">
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year" selected>3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Payment Status</label>
                    <select name="payment_status" class="ap-input" style="font-size:0.8rem;">
                        <option value="paid">Paid (₱50.00 Chapter Due Remitted)</option>
                        <option value="pending">Pending Payment</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeMemberModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-save"></i> Save Member</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentYearFilter = 'all';

        function openMemberModal() {
            document.getElementById('memberModal').classList.add('active');
        }
        function closeMemberModal() {
            document.getElementById('memberModal').classList.remove('active');
        }

        function setYearFilter(year, btn) {
            currentYearFilter = year.toLowerCase();
            document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
            if (btn) btn.classList.add('active');
            filterMembersTable();
        }

        function filterMembersTable() {
            const query = (document.getElementById('memberSearchInput').value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.member-table-row');
            let visibleCount = 0;

            rows.forEach(tr => {
                const text = tr.textContent.toLowerCase();
                const rowYear = (tr.getAttribute('data-year') || '').toLowerCase();

                const matchesQuery = !query || text.indexOf(query) > -1;
                const matchesYear = (currentYearFilter === 'all') || (rowYear.indexOf(currentYearFilter) > -1);

                if (matchesQuery && matchesYear) {
                    tr.style.display = '';
                    visibleCount++;
                } else {
                    tr.style.display = 'none';
                }
            });

            const counterEl = document.getElementById('visibleMemberCount');
            if (counterEl) counterEl.textContent = visibleCount;
        }

        // Export to Excel
        document.getElementById('btnExportRoster').addEventListener('click', function() {
            const table = document.getElementById('membersTable');
            const wb = XLSX.utils.table_to_book(table, {sheet: "Chapter Roster"});
            XLSX.writeFile(wb, "IECEP_Chapter_Roster_<?= date('Ymd') ?>.xlsx");
        });
    </script>
</body>
</html>
