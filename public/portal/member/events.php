<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'events';
$pageTitle = 'Chapter Events & Attendance Hub';

$user = $_SESSION['user'] ?? [];
$userId = $user['id'] ?? null;
$supabase = getSupabaseClient();

$events = [];
$myAttendanceMap = [];

try {
    // Fetch upcoming and ongoing events
    $rawEv = $supabase->select('events', [
        'select' => '*',
        'order' => 'start_date.desc'
    ]);
    if (is_array($rawEv)) $events = $rawEv;

    // Fetch member's attendance
    if ($userId) {
        $rawAtt = $supabase->select('event_attendees', [
            'member_id' => 'eq.' . $userId
        ]);
        if (is_array($rawAtt)) {
            foreach ($rawAtt as $a) {
                $myAttendanceMap[$a['event_id']] = $a;
            }
        }
    }
} catch (Exception $e) {
    error_log("Member events query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="View upcoming IECEP-LSC chapter events and scan the live 15-second dynamic QR code to record attendance.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        .event-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.25rem;
        }
        .event-item-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .event-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11,29,74,0.08);
        }
        .event-item-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0B1D4A, #D4AF37);
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-calendar-star"></i> Chapter Events & Attendance Hub</h1>
                    <p class="ap-page-subtitle">Register for regional summits, seminars, and scan the organizer's 15-second dynamic QR code to mark attendance.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/member/scan.php" class="ap-btn-primary">
                        <i class="fas fa-camera"></i> Open Phone Camera Scanner
                    </a>
                </div>
            </div>

            <!-- Events Grid -->
            <?php if (empty($events)): ?>
                <div class="ap-card" style="text-align:center; padding:3rem 1rem;">
                    <i class="fas fa-calendar-xmark fa-3x" style="color:var(--text-muted); opacity:0.5; margin-bottom:1rem;"></i>
                    <h3 style="color:var(--text-heading); font-size:1.15rem; margin:0 0 0.5rem 0;">No Upcoming Events Scheduled</h3>
                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0;">Check back soon for upcoming IECEP-LSC summits and seminars.</p>
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
                                    <span class="ap-pill gold" style="font-weight:700; font-size:0.75rem;">
                                        <?= htmlspecialchars($ev['event_type'] ?: 'Regional Event') ?>
                                    </span>
                                    <?php if ($isPresent): ?>
                                        <span class="ap-pill active"><i class="fas fa-circle-check"></i> Present (Scanned)</span>
                                    <?php else: ?>
                                        <span class="ap-pill navy">Open for Check-in</span>
                                    <?php endif; ?>
                                </div>

                                <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading); margin:0 0 0.5rem 0;">
                                    <?= htmlspecialchars($ev['title']) ?>
                                </h3>

                                <p style="font-size:0.83rem; color:var(--text-muted); line-height:1.5; margin:0 0 1rem 0;">
                                    <?= htmlspecialchars($ev['description'] ?? 'Join your fellow electronics engineering peers in this chapter activity.') ?>
                                </p>

                                <div style="background:#F8FAFC; border:1px solid var(--border-light); border-radius:10px; padding:0.75rem; font-size:0.8rem; color:var(--text-heading); margin-bottom:1.25rem;">
                                    <div style="margin-bottom:4px;">
                                        <i class="fas fa-calendar" style="color:var(--iecep-gold); width:18px;"></i>
                                        <?= isset($ev['start_date']) ? date('F d, Y &bull; h:i A', strtotime($ev['start_date'])) : 'TBD' ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-location-dot" style="color:var(--iecep-navy); width:18px;"></i>
                                        <?= htmlspecialchars($ev['venue'] ?: 'Laguna Campus Auditorium') ?>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <?php if ($isPresent): ?>
                                    <div style="background:#ECFDF5; border:1px solid #10B981; border-radius:10px; padding:0.65rem; text-align:center; font-size:0.8rem; color:#065F46; font-weight:700;">
                                        <i class="fas fa-badge-check"></i> Attendance Verified on Ledger &bull; <?= date('h:i A', strtotime($attInfo['attended_at'] ?? 'now')) ?>
                                    </div>
                                <?php else: ?>
                                    <a href="/IECEP-LSC-MEMSYS/public/portal/member/scan.php?event_id=<?= urlencode($evId) ?>" class="ap-btn-primary" style="width:100%; justify-content:center;">
                                        <i class="fas fa-camera"></i> Scan Live 15s QR Code
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip" style="margin-top:1.5rem;">
                <div class="ap-sentinel-item"><i class="fas fa-qrcode"></i><span><strong>Check-in:</strong> Dynamic QR Camera Reader</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Proof-of-Presence:</strong> Cryptographically Recorded</span></div>
            </div>

        </div>
    </main>
</body>
</html>
