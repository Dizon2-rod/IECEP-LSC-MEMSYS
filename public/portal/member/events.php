<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'events';
$pageTitle = 'Chapter Events & Attendance Hub';

$user = get_user_info();
$userId = $user['id'] ?? null;
$userEmail = $user['email'] ?? '';

$supabase = getSupabaseClient();

// Fetch Member ID
$member = [];
if ($supabase) {
    try {
        if (!empty($userEmail)) {
            $mRes = $supabase->select('members', ['email' => 'eq.' . $userEmail]);
            if (is_array($mRes) && isset($mRes[0])) $member = $mRes[0];
        }
        if (empty($member) && !empty($userId)) {
            $mRes = $supabase->select('members', ['id' => 'eq.' . $userId]);
            if (is_array($mRes) && isset($mRes[0])) $member = $mRes[0];
        }
    } catch (Exception $e) {}
}
$memberDbId = $member['id'] ?? $userId;

$events = [];
$myAttendanceMap = [];

try {
    if ($supabase) {
        $rawEv = $supabase->select('events', [
            'status' => 'eq.published',
            'order' => 'start_date.desc'
        ]);
        if (is_array($rawEv)) $events = $rawEv;

        if (!empty($memberDbId)) {
            $rawAtt = $supabase->select('event_attendees', [
                'member_id' => 'eq.' . $memberDbId
            ]);
            if (is_array($rawAtt)) {
                foreach ($rawAtt as $a) {
                    $myAttendanceMap[$a['event_id']] = $a;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Member events error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="IECEP Laguna Student Chapter Official Events, Technical Seminars, and Attendance Hub.">
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
            .main-content { margin-left: 0; padding: 1rem; }
        }

        .event-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.25rem;
        }

        .event-item-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .event-item-card:hover {
            transform: translateY(-2px);
            border-color: #CBD5E1;
            box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.08);
        }

        .event-item-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0B1D4A, #D4AF37);
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
        .ap-pill.navy { background: #E0E7FF; color: #3730A3; }

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
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
            <div>
                <h1 style="font-size:1.4rem; font-weight:800; color:#0F172A; margin:0 0 0.2rem 0; display:flex; align-items:center; gap:0.5rem;">
                    <i class="fas fa-calendar-star" style="color:var(--color-navy);"></i> Chapter Events & Attendance Hub
                </h1>
                <p style="margin:0; font-size:0.82rem; color:#64748B;">
                    Register for student conventions, workshops, and verify presence through the official dynamic check-in system.
                </p>
            </div>
            <div>
                <a href="/IECEP-LSC-MEMSYS/public/portal/member/scan.php" class="btn-primary-navy">
                    <i class="fas fa-camera"></i> Open Attendance Scanner
                </a>
            </div>
        </div>

        <!-- Events Grid -->
        <?php if (empty($events)): ?>
            <div style="background:#FFFFFF; border:1px solid var(--border-color); border-radius:12px; text-align:center; padding:3rem 1.5rem;">
                <i class="fas fa-calendar-xmark" style="font-size:2.5rem; color:#94A3B8; margin-bottom:0.75rem;"></i>
                <h3 style="color:#0F172A; font-size:1.1rem; margin:0 0 0.4rem 0;">No Upcoming Events Scheduled</h3>
                <p style="color:#64748B; font-size:0.84rem; margin:0;">Check back soon for upcoming IECEP-LSC seminars, student summits, and workshops.</p>
            </div>
        <?php else: ?>
            <div class="event-card-grid">
                <?php foreach ($events as $ev): ?>
                    <?php 
                        $evId = $ev['id'];
                        $isPresent = isset($myAttendanceMap[$evId]);
                        $attInfo = $myAttendanceMap[$evId] ?? null;
                    ?>
                    <div class="event-item-card">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                                <span class="ap-pill gold">
                                    <?= htmlspecialchars($ev['event_type'] ?? 'Regional Event') ?>
                                </span>
                                <?php if ($isPresent): ?>
                                    <span class="ap-pill active"><i class="fas fa-circle-check me-1"></i> Attended</span>
                                <?php else: ?>
                                    <span class="ap-pill navy">Open Registration</span>
                                <?php endif; ?>
                            </div>

                            <h2 style="font-size:1.1rem; font-weight:800; color:#0F172A; margin:0 0 0.4rem 0; line-height:1.3;">
                                <?= htmlspecialchars($ev['title'] ?? 'IECEP Event') ?>
                            </h2>

                            <p style="font-size:0.8rem; color:#475569; line-height:1.45; margin:0 0 1rem 0;">
                                <?= htmlspecialchars($ev['description'] ?? 'Official gathering of electronics engineering delegates across the Laguna region.') ?>
                            </p>

                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.75rem; font-size:0.78rem; color:#334155; margin-bottom:1.15rem;">
                                <div style="margin-bottom:0.35rem; display:flex; align-items:center; gap:0.4rem;">
                                    <i class="fas fa-calendar-day" style="color:#D4AF37; width:16px;"></i>
                                    <strong><?= isset($ev['start_date']) ? date('F d, Y • h:i A', strtotime($ev['start_date'])) : 'TBD' ?></strong>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <i class="fas fa-location-dot" style="color:var(--color-navy); width:16px;"></i>
                                    <span><?= htmlspecialchars($ev['venue'] ?? 'Auditorium / Virtual Venue') ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <?php if ($isPresent): ?>
                                <div style="background:#ECFDF5; border:1px solid #10B981; border-radius:8px; padding:0.6rem; text-align:center; font-size:0.78rem; color:#065F46; font-weight:700;">
                                    <i class="fas fa-badge-check me-1"></i> Attendance Verified • <a href="/IECEP-LSC-MEMSYS/public/portal/member/certificate.php?event_id=<?= urlencode($evId) ?>" style="color:#065F46; text-decoration:underline;">View Certificate</a>
                                </div>
                            <?php else: ?>
                                <a href="/IECEP-LSC-MEMSYS/public/portal/member/scan.php?event_id=<?= urlencode($evId) ?>" class="btn-primary-navy" style="width:100%; justify-content:center;">
                                    <i class="fas fa-camera"></i> Scan Live Event QR
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
