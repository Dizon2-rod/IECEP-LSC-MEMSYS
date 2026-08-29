<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'attendance';

require_once __DIR__ . '/../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'Event Attendance & Live QR Check-In / Check-Out';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Chapter Attendance';

$supabase = getSupabaseClient();

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

$selectedEventId = $_GET['event_id'] ?? ($eventsList[0]['id'] ?? '');

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

// Fetch Attendance records for the selected event
$attendances = [];
$checkedInCount = 0;
$checkedOutCount = 0;

if ($supabase && !empty($selectedEventId)) {
    try {
        $allAtt = $supabase->select('event_attendees', [
            'event_id' => 'eq.' . $selectedEventId,
            'order' => 'check_in_time.desc'
        ]);
        if (is_array($allAtt) && !isset($allAtt['code'])) {
            foreach ($allAtt as $att) {
                $mId = $att['member_id'] ?? '';
                if (isset($memMap[$mId])) {
                    $mem = $memMap[$mId];
                    $att['member_name'] = $mem['full_name'] ?? 'Student Member';
                    $att['student_number'] = $mem['student_number'] ?? 'N/A';
                    $att['membership_id'] = $mem['membership_id'] ?? 'Pending ID';
                    
                    if (!empty($att['check_in_time'])) $checkedInCount++;
                    if (!empty($att['check_out_time'])) $checkedOutCount++;
                    
                    // Compute duration
                    if (!empty($att['check_in_time']) && !empty($att['check_out_time'])) {
                        $diffSecs = max(0, strtotime($att['check_out_time']) - strtotime($att['check_in_time']));
                        $h = floor($diffSecs / 3600);
                        $m = floor(($diffSecs % 3600) / 60);
                        $att['duration_str'] = ($h > 0 ? "{$h}h " : "") . "{$m}m";
                    }
                    
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
    <meta name="description" content="Live QR attendance scanner supporting Check-In (Time-In) and Check-Out (Time-Out) for IECEP-LSC.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --color-rose: #E11D48;
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
            padding: 0.45rem 0.85rem;
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
            padding: 0.45rem 0.95rem;
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
        }

        .btn-emerald {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.95rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--color-emerald);
            border: 1px solid var(--color-emerald);
            color: #FFFFFF !important;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
            transition: all 0.18s ease;
        }
        .btn-emerald:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .btn-rose {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.95rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--color-rose);
            border: 1px solid var(--color-rose);
            color: #FFFFFF !important;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(225, 29, 72, 0.2);
            transition: all 0.18s ease;
        }
        .btn-rose:hover {
            background: #BE123C;
            transform: translateY(-1px);
        }

        /* 4 KPI Grid */
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
        .kpi-icon-pill.rose { background: #FFF1F2; color: #E11D48; border: 1px solid #FECDD3; }
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
            background: rgba(11, 29, 74, 0.65);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .doc-modal.active { display: flex; }
        .modal-inner-box {
            background: #FFFFFF;
            border-radius: 14px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.22);
            border: 1px solid var(--border-color);
            overflow: hidden;
            max-height: 90vh;
            overflow-y: auto;
        }

        .mode-toggle-group {
            display: inline-flex;
            background: #F1F5F9;
            padding: 3px;
            border-radius: 8px;
            gap: 2px;
        }
        .mode-toggle-btn {
            border: none;
            background: transparent;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            cursor: pointer;
            color: #64748B;
            transition: all 0.18s ease;
        }
        .mode-toggle-btn.active-in {
            background: #059669;
            color: #FFFFFF;
            box-shadow: 0 1px 3px rgba(5, 150, 105, 0.3);
        }
        .mode-toggle-btn.active-out {
            background: #E11D48;
            color: #FFFFFF;
            box-shadow: 0 1px 3px rgba(225, 29, 72, 0.3);
        }

        .scan-feedback-box {
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            font-size: 0.82rem;
            display: none;
            animation: fadeIn 0.25s ease;
        }
        .scan-feedback-box.success {
            background: #ECFDF5;
            border: 1px solid #10B981;
            color: #065F46;
            display: block;
        }
        .scan-feedback-box.warning {
            background: #FEF2F2;
            border: 1px solid #F87171;
            color: #991B1B;
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 0.85rem; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.5rem; }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.5rem !important; }
            .dash-header-banner { flex-direction: column; align-items: stretch; gap: 0.65rem; }
            .kpi-val { font-size: 1.1rem !important; }
            .kpi-lbl { font-size: 0.66rem !important; }
            .dash-kpi-card { padding: 0.5rem 0.65rem !important; gap: 0.5rem !important; }
            .kpi-icon-pill { width: 32px !important; height: 32px !important; font-size: 0.9rem !important; }
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
                            <i class="fas fa-qrcode" style="color:var(--color-navy);"></i>
                            Member ID Attendance & Check-In / Check-Out
                        </h1>
                        <p class="dash-header-sub">
                            Scan official IECEP-LSC ID card QR codes for <strong><?= htmlspecialchars($schoolName) ?></strong> delegates.
                        </p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <button type="button" class="btn-emerald" onclick="openScannerWithMode('check_in')">
                        <i class="fas fa-right-to-bracket"></i> Scan Check-In (Time-In)
                    </button>
                    <button type="button" class="btn-rose" onclick="openScannerWithMode('check_out')">
                        <i class="fas fa-right-from-bracket"></i> Scan Check-Out (Time-Out)
                    </button>
                    <button type="button" id="btnExportAtt" class="btn-white">
                        <i class="fas fa-file-excel" style="color:var(--color-emerald);"></i> Export Excel
                    </button>
                </div>
            </div>

            <!-- 2. KPI Grid (2x2 on mobile, 4 columns on desktop) -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-user-check"></i></div>
                    <div>
                        <div class="kpi-val" id="kpiCheckIn"><?= $checkedInCount ?></div>
                        <div class="kpi-lbl">Checked-In (Time-In)</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill rose"><i class="fas fa-clock-rotate-left"></i></div>
                    <div>
                        <div class="kpi-val" id="kpiCheckOut"><?= $checkedOutCount ?></div>
                        <div class="kpi-lbl">Checked-Out (Time-Out)</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-users-viewfinder"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($chapterMembers) ?></div>
                        <div class="kpi-lbl">Total Chapter Delegates</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($eventsList) ?></div>
                        <div class="kpi-lbl">Scheduled Events</div>
                    </div>
                </div>
            </div>

            <!-- 3. Event Selector & Search Bar -->
            <div class="white-controls-card">
                <div style="display:flex; align-items:center; gap:0.65rem; flex:1; flex-wrap:wrap;">
                    <div style="position:relative; flex:1; min-width:220px; max-width:340px;">
                        <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                        <input type="text" id="attSearchInput" class="search-input-field" placeholder="Search student name, ID..." onkeyup="filterAttTable()">
                    </div>
                    <form method="GET" style="display:flex; align-items:center; gap:0.4rem;">
                        <label style="font-size:0.76rem; font-weight:700; color:#64748B; white-space:nowrap;">Active Event:</label>
                        <select name="event_id" id="eventSelectDropdown" class="ap-input" style="font-size:0.78rem; padding:0.35rem 0.65rem;" onchange="this.form.submit()">
                            <?php foreach ($eventsList as $ev): ?>
                                <option value="<?= htmlspecialchars($ev['id']) ?>" <?= ($ev['id'] === $selectedEventId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ev['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;" id="attCountBadge">
                    Showing <?= count($attendances) ?> attendance records
                </div>
            </div>

            <!-- 4. Real Attendance Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Delegate Check-In / Check-Out Ledger</h3>
                    <span class="ap-pill active"><span class="ap-pill-dot"></span> Real Database Records</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="attTable">
                        <thead>
                            <tr>
                                <th>Delegate Student Particulars</th>
                                <th>Student Number</th>
                                <th>Membership ID</th>
                                <th>Time-In (Check-In)</th>
                                <th>Time-Out (Check-Out)</th>
                                <th>Session Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="attTableBody">
                            <?php if (empty($attendances)): ?>
                                <tr id="noRecordsRow">
                                    <td colspan="7" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-id-card" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Attendance Records Scanned Yet for This Event</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "Scan Check-In" or "Scan Check-Out" to scan the student's ID card.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendances as $a): ?>
                                    <tr id="row-<?= htmlspecialchars($a['member_id']) ?>">
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($a['member_name'] ?? 'Delegate') ?></strong>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:#334155;">
                                            <?= htmlspecialchars($a['student_number'] ?? 'N/A') ?>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:var(--color-navy); font-weight:700;">
                                            <?= htmlspecialchars($a['membership_id'] ?? 'Verified') ?>
                                        </td>
                                        <td style="color:#059669; font-weight:600; font-size:0.75rem; white-space:nowrap;">
                                            <?= !empty($a['check_in_time']) ? date('M d, Y h:i A', strtotime($a['check_in_time'])) : '—' ?>
                                        </td>
                                        <td style="color:#E11D48; font-weight:600; font-size:0.75rem; white-space:nowrap;">
                                            <?= !empty($a['check_out_time']) ? date('M d, Y h:i A', strtotime($a['check_out_time'])) : '<span style="color:#94A3B8;">Pending</span>' ?>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; color:#475569;">
                                            <?= htmlspecialchars($a['duration_str'] ?? 'Active') ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['check_out_time'])): ?>
                                                <span class="ap-pill" style="background:#F1F5F9; color:#475569;"><i class="fas fa-flag-checkered me-1"></i> Completed</span>
                                            <?php else: ?>
                                                <span class="ap-pill active"><span class="ap-pill-dot"></span> Present</span>
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

    <!-- Modal 1: Live Officer Camera Scanner (Scan ID QR) -->
    <div id="officerScannerModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header" style="background:var(--color-navy); color:#FFFFFF;">
                <h3 class="ap-card-title" style="color:#FFFFFF;" id="scannerModalTitle">
                    <i class="fas fa-camera"></i> Scan Student Member ID Card
                </h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem; background:transparent; color:#FFFFFF;" onclick="closeOfficerScanner()">&times;</button>
            </div>
            <div style="padding:1.25rem; text-align:center;">
                <!-- Scan Mode Toggle inside Modal -->
                <div style="margin-bottom:1rem; display:flex; justify-content:center;">
                    <div class="mode-toggle-group">
                        <button type="button" id="btnModeCheckIn" class="mode-toggle-btn active-in" onclick="setScanMode('check_in')">
                            <i class="fas fa-right-to-bracket me-1"></i> Check-In (Time-In)
                        </button>
                        <button type="button" id="btnModeCheckOut" class="mode-toggle-btn" onclick="setScanMode('check_out')">
                            <i class="fas fa-right-from-bracket me-1"></i> Check-Out (Time-Out)
                        </button>
                    </div>
                </div>

                <div id="officerReader" style="width:100%; max-width:340px; margin:0 auto; border-radius:10px; overflow:hidden; background:#000;"></div>
                
                <!-- Feedback Notification Alert Box -->
                <div id="scanFeedbackBox" class="scan-feedback-box">
                    <div id="scanFeedbackIcon" style="font-size:1.5rem; margin-bottom:0.35rem;"></div>
                    <strong id="scanFeedbackTitle" style="font-size:0.92rem; display:block;"></strong>
                    <div id="scanFeedbackDetail" style="margin-top:0.25rem; font-size:0.78rem;"></div>
                </div>
            </div>
        </div>
    </div>



    <script>
        let html5Scanner = null;
        let qrTimerInterval = null;
        let qrSecondsRemaining = 30;
        let activeScanMode = 'check_in'; // 'check_in' or 'check_out'
        const currentEventId = <?= json_encode($selectedEventId) ?>;

        function setScanMode(mode) {
            activeScanMode = mode;
            const btnIn = document.getElementById('btnModeCheckIn');
            const btnOut = document.getElementById('btnModeCheckOut');

            if (mode === 'check_in') {
                btnIn.className = 'mode-toggle-btn active-in';
                btnOut.className = 'mode-toggle-btn';
            } else {
                btnIn.className = 'mode-toggle-btn';
                btnOut.className = 'mode-toggle-btn active-out';
            }
        }

        function openScannerWithMode(mode) {
            setScanMode(mode);
            openOfficerScanner();
        }

        function openOfficerScanner() {
            document.getElementById('scanFeedbackBox').className = 'scan-feedback-box';
            document.getElementById('scanFeedbackBox').style.display = 'none';
            document.getElementById('officerScannerModal').classList.add('active');

            if (!html5Scanner) {
                html5Scanner = new Html5QrcodeScanner("officerReader", { fps: 10, qrbox: 240 });
                html5Scanner.render(onOfficerScanSuccess, onOfficerScanError);
            }
        }

        function closeOfficerScanner() {
            document.getElementById('officerScannerModal').classList.remove('active');
            if (html5Scanner) {
                try { html5Scanner.clear(); } catch(e) {}
                html5Scanner = null;
            }
        }

        let isSubmittingScan = false;
        async function onOfficerScanSuccess(decodedText) {
            if (isSubmittingScan) return;
            isSubmittingScan = true;

            const feedback = document.getElementById('scanFeedbackBox');
            const fbTitle = document.getElementById('scanFeedbackTitle');
            const fbDetail = document.getElementById('scanFeedbackDetail');
            const fbIcon = document.getElementById('scanFeedbackIcon');

            try {
                const response = await fetch('/IECEP-LSC-MEMSYS/public/api/events/attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'officer_scan_student',
                        event_id: currentEventId,
                        student_qr: decodedText,
                        scan_mode: activeScanMode
                    })
                });

                const res = await response.json();

                if (res.success && !res.already_recorded) {
                    feedback.className = 'scan-feedback-box success';
                    fbIcon.innerHTML = '<i class="fas fa-circle-check" style="color:#059669;"></i>';
                    fbTitle.textContent = (activeScanMode === 'check_in') ? "Check-In Verified (Time-In)" : "Check-Out Recorded (Time-Out)";
                    fbDetail.textContent = res.message;
                    feedback.style.display = 'block';

                    // Update UI table and counters
                    const st = res.student;
                    if (st) {
                        const noRec = document.getElementById('noRecordsRow');
                        if (noRec) noRec.remove();

                        const tbody = document.getElementById('attTableBody');
                        let existingRow = document.getElementById(`row-${st.id}`);

                        const formattedIn = st.check_in_time ? new Date(st.check_in_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '—';
                        const formattedOut = st.check_out_time ? new Date(st.check_out_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '<span style="color:#94A3B8;">Pending</span>';
                        const duration = st.duration || (st.check_out_time ? 'Completed' : 'Active');
                        const statusPill = st.check_out_time ? '<span class="ap-pill" style="background:#F1F5F9; color:#475569;"><i class="fas fa-flag-checkered me-1"></i> Completed</span>' : '<span class="ap-pill active"><span class="ap-pill-dot"></span> Present</span>';

                        if (existingRow) {
                            existingRow.innerHTML = `
                                <td><strong style="color:#0F172A; font-size:0.84rem;">${st.full_name}</strong></td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:#334155;">${st.student_number}</td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:var(--color-navy); font-weight:700;">${st.membership_id}</td>
                                <td style="color:#059669; font-weight:600; font-size:0.75rem; white-space:nowrap;">${formattedIn}</td>
                                <td style="color:#E11D48; font-weight:600; font-size:0.75rem; white-space:nowrap;">${formattedOut}</td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; color:#475569;">${duration}</td>
                                <td>${statusPill}</td>
                            `;
                        } else {
                            const tr = document.createElement('tr');
                            tr.id = `row-${st.id}`;
                            tr.style.backgroundColor = '#ECFDF5';
                            tr.innerHTML = `
                                <td><strong style="color:#0F172A; font-size:0.84rem;">${st.full_name}</strong></td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:#334155;">${st.student_number}</td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:var(--color-navy); font-weight:700;">${st.membership_id}</td>
                                <td style="color:#059669; font-weight:600; font-size:0.75rem; white-space:nowrap;">${formattedIn}</td>
                                <td style="color:#E11D48; font-weight:600; font-size:0.75rem; white-space:nowrap;">${formattedOut}</td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; color:#475569;">${duration}</td>
                                <td>${statusPill}</td>
                            `;
                            tbody.insertBefore(tr, tbody.firstChild);
                        }

                        if (activeScanMode === 'check_in') {
                            const cinEl = document.getElementById('kpiCheckIn');
                            if (cinEl) cinEl.textContent = parseInt(cinEl.textContent || 0) + 1;
                        } else {
                            const coutEl = document.getElementById('kpiCheckOut');
                            if (coutEl) coutEl.textContent = parseInt(coutEl.textContent || 0) + 1;
                        }
                    }
                } else if (res.already_recorded || res.not_checked_in) {
                    feedback.className = 'scan-feedback-box warning';
                    fbIcon.innerHTML = '<i class="fas fa-triangle-exclamation" style="color:#DC2626;"></i>';
                    fbTitle.textContent = res.not_checked_in ? "Check-In Required First" : "Already Recorded";
                    fbDetail.textContent = res.message;
                    feedback.style.display = 'block';
                } else {
                    feedback.className = 'scan-feedback-box warning';
                    fbIcon.innerHTML = '<i class="fas fa-times-circle" style="color:#DC2626;"></i>';
                    fbTitle.textContent = "Scan Failed";
                    fbDetail.textContent = res.message || "Invalid QR token.";
                    feedback.style.display = 'block';
                }
            } catch (err) {
                feedback.className = 'scan-feedback-box warning';
                fbIcon.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#DC2626;"></i>';
                fbTitle.textContent = "Network Error";
                fbDetail.textContent = err.message;
                feedback.style.display = 'block';
            }

            setTimeout(() => { isSubmittingScan = false; }, 2000);
        }

        function onOfficerScanError(err) {
            // Scanning continuously
        }



        // --- FILTER TABLE ---
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

        // --- EXPORT TO EXCEL ---
        document.getElementById('btnExportAtt').addEventListener('click', function() {
            const table = document.getElementById('attTable');
            const wb = XLSX.utils.table_to_book(table, {sheet: "Attendance Ledger"});
            XLSX.writeFile(wb, "Event_Attendance_Ledger_<?= date('Ymd') ?>.xlsx");
        });
    </script>
</body>
</html>
