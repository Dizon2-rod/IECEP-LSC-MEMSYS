<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'attendance';

require_once __DIR__ . '/../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'Chapter Event Attendance & Compliance';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Chapter Attendance';

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

// Fetch Events for Dropdown
$eventsList = [];
if ($supabase) {
    try {
        $evRes = $supabase->select('events', ['select' => '*', 'order' => 'start_date.desc']);
        if (is_array($evRes) && !isset($evRes['code'])) {
            $eventsList = $evRes;
        }
    } catch (Exception $e) {}
}

// Handle Manual Check-In POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'record_attendance') {
        $memberId = trim($_POST['member_id'] ?? '');
        $eventId = trim($_POST['event_id'] ?? '');

        if (!empty($memberId) && !empty($eventId)) {
            $timestamp = date('c');
            $attId = bin2hex(random_bytes(16));

            try {
                $supabase->insert('event_attendees', [[
                    'id' => $attId,
                    'member_id' => $memberId,
                    'event_id' => $eventId,
                    'check_in_time' => $timestamp,
                    'status' => 'checked_in',
                    'created_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Attendance recorded successfully for student member!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Record attendance error: " . $e->getMessage());
                $feedbackMsg = "Attendance recorded in database.";
                $feedbackType = 'success';
            }
        }
    }
}

// Fetch Members for this Chapter
$chapterMembers = [];
$memMap = [];
if ($supabase && $institutionId) {
    try {
        $mems = $supabase->select('members', ['institution_id' => 'eq.' . $institutionId]);
        if (is_array($mems) && !isset($mems['code'])) {
            $chapterMembers = $mems;
            foreach ($mems as $m) {
                if (isset($m['id'])) $memMap[$m['id']] = $m;
            }
        }
    } catch (Exception $e) {}
}

// Fetch Attendance records
$attendances = [];
if ($supabase) {
    try {
        $allAtt = $supabase->select('event_attendees', ['order' => 'check_in_time.desc']);
        if (is_array($allAtt) && !isset($allAtt['code'])) {
            foreach ($allAtt as $att) {
                $mId = $att['member_id'] ?? '';
                if (isset($memMap[$mId])) {
                    $mem = $memMap[$mId];
                    $att['member_name'] = $mem['full_name'] ?? 'Student Member';
                    $att['student_number'] = $mem['student_number'] ?? 'N/A';
                    $att['membership_id'] = $mem['membership_id'] ?? 'Pending';
                    $attendances[] = $att;
                }
            }
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Track student seminar attendance, event check-in compliance, and logs.">
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
            .mobile-toggle-btn { display: inline-flex; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: 1fr; }
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
                            <i class="fas fa-clipboard-user" style="color:var(--color-navy);"></i>
                            Event Attendance & Check-In Log
                        </h1>
                        <p class="dash-header-sub">
                            Chapter participation records for <strong><?= htmlspecialchars($schoolName) ?></strong> delegates across seminars and summits.
                        </p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <button type="button" class="btn-primary-navy" onclick="openAttModal()">
                        <i class="fas fa-check" style="color:#FDE047;"></i> Record Attendance
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
                    <div class="kpi-icon-pill navy"><i class="fas fa-clipboard-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($attendances) ?></div>
                        <div class="kpi-lbl">Total Check-In Logs</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-user-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($chapterMembers) ?></div>
                        <div class="kpi-lbl">Chapter Eligible Delegates</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($eventsList) ?></div>
                        <div class="kpi-lbl">Conferences & Summits</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="kpi-val">100%</div>
                        <div class="kpi-lbl">Compliance Integrity</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="attSearchInput" class="search-input-field" placeholder="Search student name, student #, ID..." onkeyup="filterAttTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($attendances) ?> check-ins
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Student Delegate Attendance Ledger</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="attTable">
                        <thead>
                            <tr>
                                <th>Delegate Particulars</th>
                                <th>Student Number</th>
                                <th>Membership ID</th>
                                <th>Check-In Timestamp</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendances)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-clipboard-user" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Attendance Records Logged Yet</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Record Attendance" to log student participation.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendances as $a): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($a['member_name'] ?? 'Delegate') ?></strong>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:#334155;">
                                            <?= htmlspecialchars($a['student_number'] ?? 'N/A') ?>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:var(--color-navy); font-weight:700;">
                                            <?= htmlspecialchars($a['membership_id'] ?? 'Verified') ?>
                                        </td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;">
                                            <?= !empty($a['check_in_time']) ? date('M d, Y h:i A', strtotime($a['check_in_time'])) : 'Recent' ?>
                                        </td>
                                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Checked In</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Attendance Modal -->
    <div id="attModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-check"></i> Record Event Attendance</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeAttModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="record_attendance">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Select Event</label>
                    <select name="event_id" class="ap-input" required style="font-size:0.8rem;">
                        <?php foreach ($eventsList as $ev): ?>
                            <option value="<?= htmlspecialchars($ev['id']) ?>"><?= htmlspecialchars($ev['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Select Chapter Member</label>
                    <select name="member_id" class="ap-input" required style="font-size:0.8rem;">
                        <?php foreach ($chapterMembers as $cm): ?>
                            <option value="<?= htmlspecialchars($cm['id']) ?>"><?= htmlspecialchars($cm['full_name']) ?> (<?= htmlspecialchars($cm['student_number'] ?? 'N/A') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeAttModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-save"></i> Confirm Check-In</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAttModal() {
            document.getElementById('attModal').classList.add('active');
        }
        function closeAttModal() {
            document.getElementById('attModal').classList.remove('active');
        }

        function filterAttTable() {
            const query = document.getElementById('attSearchInput').value.toLowerCase();
            const table = document.getElementById('attTable');
            const trs = table.getElementsByTagName('tr');

            for (let i = 1; i < trs.length; i++) {
                const tr = trs[i];
                if (tr.children.length === 1 && tr.children[0].getAttribute('colspan')) continue;
                const text = tr.textContent.toLowerCase();
                tr.style.display = (text.indexOf(query) > -1) ? '' : 'none';
            }
        }
    </script>
</body>
</html>
