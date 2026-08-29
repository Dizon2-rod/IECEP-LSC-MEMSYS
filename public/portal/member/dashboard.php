<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'dashboard';
$pageTitle = 'Student Member Command Center';

$user = get_user_info();
$userId = $user['id'] ?? null;
$userEmail = $user['email'] ?? '';
$displayName = $user['full_name'] ?? $user['name'] ?? $userEmail;

$supabase = getSupabaseClient();

// Fetch Member Record from Database
$member = [];
$schoolName = 'Laguna State Polytechnic University - Santa Cruz Campus';
$schoolAcronym = 'LSPU - SCC';

if ($supabase) {
    try {
        if (!empty($userEmail)) {
            $mRes = $supabase->select('members', ['email' => 'eq.' . $userEmail]);
            if (is_array($mRes) && isset($mRes[0])) {
                $member = $mRes[0];
            }
        }
        if (empty($member) && !empty($userId)) {
            $mRes = $supabase->select('members', ['user_id' => 'eq.' . $userId]);
            if (is_array($mRes) && isset($mRes[0])) {
                $member = $mRes[0];
            }
        }
        if (empty($member) && !empty($userId)) {
            $mRes = $supabase->select('members', ['id' => 'eq.' . $userId]);
            if (is_array($mRes) && isset($mRes[0])) {
                $member = $mRes[0];
            }
        }

        // Resolve School Name
        $instId = $member['institution_id'] ?? ($_SESSION['institution_id'] ?? null);
        if ($instId) {
            $iRes = $supabase->select('institutions', ['id' => 'eq.' . $instId]);
            if (is_array($iRes) && isset($iRes[0]['name'])) {
                $schoolName = $iRes[0]['name'];
                $schoolAcronym = $iRes[0]['acronym'] ?? 'IECEP-SC';
            }
        } elseif (!empty($member['school_affiliate'])) {
            $schoolName = $member['school_affiliate'];
        }
    } catch (Exception $e) {
        error_log("Member dashboard load error: " . $e->getMessage());
    }
}

// Fetch Published Events
$events = [];
try {
    if ($supabase) {
        $rawEvents = $supabase->select('events', [
            'status' => 'eq.published',
            'order' => 'start_date.desc',
            'limit' => '6'
        ]);
        if (is_array($rawEvents)) $events = $rawEvents;
    }
} catch (Exception $e) {
    $events = [];
}

// Fetch Member Attendance Records
$myAttendance = [];
$attendanceCount = 0;
try {
    if ($supabase && !empty($member['id'])) {
        $rawAtt = $supabase->select('event_attendees', [
            'member_id' => 'eq.' . $member['id'],
            'order' => 'check_in_time.desc',
            'limit' => '5'
        ]);
        if (is_array($rawAtt)) {
            $myAttendance = $rawAtt;
            $attendanceCount = count($myAttendance);
        }
    }
} catch (Exception $e) {
    $myAttendance = [];
}

// Stats
$membershipId = $member['membership_id'] ?? 'IECEP-2026-0001';
$courseName = !empty($member['course']) ? $member['course'] : 'BS Electronics Engineering';
$yearLevel = !empty($member['year_level']) ? $member['year_level'] : '3rd Year';
$isPaid = strtolower($member['payment_status'] ?? 'paid') === 'paid';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Student Member Portal Dashboard for IECEP Laguna Student Chapter delegates.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-blue: #2563EB;
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

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .dash-hero {
            background: linear-gradient(135deg, #0B1D4A 0%, #152C6E 60%, #1E3A8A 100%);
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            color: #FFFFFF;
            margin-bottom: 1.25rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(212, 175, 55, 0.35);
            box-shadow: 0 10px 25px -5px rgba(11, 29, 74, 0.15);
        }

        .dash-hero::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.18) 0%, rgba(212, 175, 55, 0) 70%);
            pointer-events: none;
        }

        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 1100px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: 1fr; }
        }

        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.15rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
        }

        .dash-kpi-card:hover {
            transform: translateY(-2px);
            border-color: #CBD5E1;
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.08);
        }

        .kpi-icon-pill {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .kpi-icon-pill.gold { background: #FEF9C3; color: #CA8A04; }
        .kpi-icon-pill.blue { background: #EFF6FF; color: #2563EB; }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; }
        .kpi-icon-pill.purple { background: #F3E8FF; color: #7C3AED; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.2;
        }

        .kpi-lbl {
            font-size: 0.76rem;
            color: #64748B;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-top: 0.2rem;
        }

        .dash-grid-2col {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 1024px) {
            .dash-grid-2col { grid-template-columns: 1fr; }
        }

        .dash-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }

        .dash-card-header {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FAFAFA;
        }

        .dash-card-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .dash-card-body {
            padding: 1.25rem;
        }

        .btn-primary-navy {
            background: var(--color-navy);
            color: #FFFFFF;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            color: #FFFFFF;
        }

        .btn-emerald {
            background: var(--color-emerald);
            color: #FFFFFF;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-emerald:hover {
            background: #047857;
            color: #FFFFFF;
        }

        .btn-white {
            background: #FFFFFF;
            color: #334155;
            border: 1px solid #CBD5E1;
            padding: 0.5rem 0.9rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
        }

        .ap-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .ap-pill.active { background: #ECFDF5; color: #059669; }
        .ap-pill.gold { background: #FEF9C3; color: #A16207; }
        .ap-pill.blue { background: #EFF6FF; color: #1D4ED8; }

        .event-feed-item {
            padding: 0.9rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            background: #FFFFFF;
            transition: all 0.15s ease;
        }
        .event-feed-item:hover {
            border-color: #94A3B8;
            background: #F8FAFC;
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <!-- Hero Welcome Header -->
        <div class="dash-hero">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; position:relative; z-index:1;">
                <div>
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                        <span class="ap-pill" style="background:rgba(212,175,55,0.2); color:#FDE047; border:1px solid rgba(212,175,55,0.5);">
                            <i class="fas fa-shield-halved me-1"></i> Official Student Member
                        </span>
                        <span style="color:rgba(255,255,255,0.8); font-size:0.78rem;">
                            • <?= htmlspecialchars($schoolAcronym) ?> Chapter
                        </span>
                    </div>
                    <h1 style="font-size:1.6rem; font-weight:800; margin:0 0 0.35rem 0; letter-spacing:-0.02em;">
                        Welcome back, <?= htmlspecialchars($member['full_name'] ?? $displayName) ?>!
                    </h1>
                    <p style="margin:0; font-size:0.84rem; color:rgba(255,255,255,0.85); max-width:600px; line-height:1.4;">
                        <?= htmlspecialchars($schoolName) ?> — Stay updated with registered summits, dynamic ID check-ins, and accredited blockchain records.
                    </p>
                </div>
                <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/member/digital-id.php" class="btn-emerald">
                        <i class="fas fa-id-card"></i> View Digital ID
                    </a>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/member/scan.php" class="btn-primary-navy" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3);">
                        <i class="fas fa-camera"></i> Scan Attendance
                    </a>
                </div>
            </div>
        </div>

        <!-- 4 KPI Summary Cards -->
        <div class="dash-kpi-grid">
            <div class="dash-kpi-card">
                <div class="kpi-icon-pill gold"><i class="fas fa-id-badge"></i></div>
                <div style="overflow:hidden;">
                    <div class="kpi-val" style="font-family:'JetBrains Mono', monospace; font-size:1.05rem;" id="memIdText">
                        <?= htmlspecialchars($membershipId) ?>
                    </div>
                    <div class="kpi-lbl">Membership ID</div>
                </div>
            </div>

            <div class="dash-kpi-card">
                <div class="kpi-icon-pill blue"><i class="fas fa-building-columns"></i></div>
                <div style="overflow:hidden;">
                    <div class="kpi-val" style="font-size:1.05rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($schoolName) ?>">
                        <?= htmlspecialchars($schoolAcronym) ?>
                    </div>
                    <div class="kpi-lbl">Affiliated Chapter</div>
                </div>
            </div>

            <div class="dash-kpi-card">
                <div class="kpi-icon-pill emerald"><i class="fas fa-graduation-cap"></i></div>
                <div style="overflow:hidden;">
                    <div class="kpi-val" style="font-size:1.05rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= htmlspecialchars($yearLevel) ?>
                    </div>
                    <div class="kpi-lbl">Academic Year Level</div>
                </div>
            </div>

            <div class="dash-kpi-card">
                <div class="kpi-icon-pill purple"><i class="fas fa-circle-check"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--color-emerald); font-size:1.05rem;">
                        <?= $isPaid ? 'Active / Paid' : 'Pending Verification' ?>
                    </div>
                    <div class="kpi-lbl">Membership Status</div>
                </div>
            </div>
        </div>

        <!-- Main 2-Column Grid -->
        <div class="dash-grid-2col">
            <!-- Left Column: Upcoming Regional Events -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <h2 class="dash-card-title">
                        <i class="fas fa-calendar-check" style="color:var(--color-blue);"></i>
                        Official Chapter & Regional Events
                    </h2>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/member/events.php" class="btn-white" style="font-size:0.75rem; padding:0.3rem 0.6rem;">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="dash-card-body">
                    <?php if (empty($events)): ?>
                        <div style="text-align:center; padding:2rem 1rem; color:#64748B;">
                            <i class="fas fa-calendar-xmark" style="font-size:2rem; margin-bottom:0.5rem; color:#94A3B8;"></i>
                            <p style="margin:0; font-size:0.88rem; font-weight:600;">No scheduled events at this moment.</p>
                            <span style="font-size:0.76rem;">Check back soon for chapter assemblies and regional summit schedules.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                            <div class="event-feed-item">
                                <div style="flex:1;">
                                    <div style="display:flex; align-items:center; gap:0.4rem; margin-bottom:0.25rem;">
                                        <span class="ap-pill blue"><?= strtoupper(htmlspecialchars($ev['event_type'] ?? 'Seminar')) ?></span>
                                        <span style="font-size:0.74rem; color:#64748B; font-family:'JetBrains Mono', monospace;">
                                            <i class="fas fa-clock me-1"></i><?= date('M d, Y • h:i A', strtotime($ev['start_date'] ?? 'now')) ?>
                                        </span>
                                    </div>
                                    <h3 style="margin:0 0 0.2rem 0; font-size:0.92rem; font-weight:700; color:#0F172A;">
                                        <?= htmlspecialchars($ev['title'] ?? 'IECEP Chapter Event') ?>
                                    </h3>
                                    <div style="font-size:0.76rem; color:#475569;">
                                        <i class="fas fa-location-dot me-1" style="color:var(--color-rose);"></i>
                                        <?= htmlspecialchars($ev['venue'] ?? 'Main Hall / Online Platform') ?>
                                    </div>
                                </div>
                                <div>
                                    <a href="/IECEP-LSC-MEMSYS/public/portal/member/events.php" class="btn-primary-navy" style="font-size:0.76rem; padding:0.4rem 0.75rem;">
                                        Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Quick Digital ID Card & Quick Actions -->
            <div>
                <!-- Digital ID Preview Widget -->
                <div class="dash-card" style="margin-bottom:1.25rem; background:linear-gradient(135deg, #0B1D4A 0%, #152C6E 100%); color:#FFFFFF; border-color:rgba(212,175,55,0.4);">
                    <div style="padding:1.25rem; text-align:center;">
                        <div style="width:54px; height:54px; border-radius:50%; background:rgba(255,255,255,0.1); border:2px solid #D4AF37; display:inline-flex; align-items:center; justify-content:center; font-size:1.5rem; color:#D4AF37; margin-bottom:0.6rem;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 style="margin:0 0 0.2rem 0; font-size:1.05rem; font-weight:800; color:#FFFFFF;">
                            <?= htmlspecialchars($member['full_name'] ?? $displayName) ?>
                        </h3>
                        <p style="margin:0 0 0.5rem 0; font-size:0.78rem; color:rgba(255,255,255,0.85);">
                            <?= htmlspecialchars($courseName) ?>
                        </p>
                        <div style="background:rgba(255,255,255,0.12); padding:0.4rem 0.75rem; border-radius:8px; display:inline-block; font-family:'JetBrains Mono', monospace; font-size:0.82rem; font-weight:700; color:#FDE047; margin-bottom:0.75rem; border:1px dashed rgba(212,175,55,0.6);">
                            <?= htmlspecialchars($membershipId) ?>
                        </div>
                        <div>
                            <a href="/IECEP-LSC-MEMSYS/public/portal/member/digital-id.php" class="btn-emerald" style="width:100%; justify-content:center;">
                                <i class="fas fa-qrcode"></i> Open Dynamic 30s ID Card
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Navigation Grid -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <i class="fas fa-compass" style="color:var(--color-gold);"></i>
                            Member Quick Services
                        </h2>
                    </div>
                    <div class="dash-card-body" style="padding:0.75rem;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                            <a href="/IECEP-LSC-MEMSYS/public/portal/member/certificate.php" class="btn-white" style="justify-content:center; padding:0.65rem 0.5rem; text-align:center; font-size:0.78rem;">
                                <i class="fas fa-certificate" style="color:#D97706;"></i> Certificates
                            </a>
                            <a href="/IECEP-LSC-MEMSYS/public/portal/member/payments.php" class="btn-white" style="justify-content:center; padding:0.65rem 0.5rem; text-align:center; font-size:0.78rem;">
                                <i class="fas fa-receipt" style="color:#059669;"></i> Payments
                            </a>
                            <a href="/IECEP-LSC-MEMSYS/public/portal/member/surveys.php" class="btn-white" style="justify-content:center; padding:0.65rem 0.5rem; text-align:center; font-size:0.78rem;">
                                <i class="fas fa-square-poll-vertical" style="color:#2563EB;"></i> Surveys
                            </a>
                            <a href="/IECEP-LSC-MEMSYS/public/portal/member/profile.php" class="btn-white" style="justify-content:center; padding:0.65rem 0.5rem; text-align:center; font-size:0.78rem;">
                                <i class="fas fa-user-gear" style="color:#7C3AED;"></i> My Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
