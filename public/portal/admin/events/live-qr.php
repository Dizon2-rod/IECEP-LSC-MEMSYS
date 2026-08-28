<?php
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

require_role(['admin', 'super_admin', 'school_officer', 'committee_registration']);

$eventId = $_GET['id'] ?? '';
$supabase = getSupabaseClient();
$secretKey = 'IECEP_LSC_ROTATING_QR_SECRET_2026';

$event = null;
if (!empty($eventId)) {
    try {
        $res = $supabase->select('events', ['id' => 'eq.' . $eventId]);
        if (is_array($res) && !empty($res)) {
            $event = $res[0];
        }
    } catch (Exception $e) {
        error_log("Event query error: " . $e->getMessage());
    }
}

if (!$event) {
    // Pick first event or default
    try {
        $allEv = $supabase->select('events', ['select' => '*', 'order' => 'created_at.desc', 'limit' => 1]);
        if (is_array($allEv) && !empty($allEv)) {
            $event = $allEv[0];
            $eventId = $event['id'];
        }
    } catch (Exception $e) {}
}

if (!$event) {
    $event = [
        'id' => 'evt_tech_summit_2026',
        'title' => 'IECEP-LSC Regional Technical Summit 2026',
        'venue' => 'Main Auditorium / Online Stream',
        'start_date' => date('Y-m-d H:i:s')
    ];
    $eventId = $event['id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live 15s Dynamic QR Check-in — <?= htmlspecialchars($event['title']) ?></title>
    <meta name="description" content="Live rotating dynamic QR attendance screen with 15-second auto-refresh and campus attribution for IECEP-LSC.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --iecep-navy: #0B1D4A;
            --iecep-gold: #D4AF37;
            --bg-dark: #070F26;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg-dark);
            color: #FFFFFF;
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        .live-top-bar {
            background: rgba(11, 29, 74, 0.85);
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .live-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            padding: 2rem;
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        @media (max-width: 1000px) {
            .live-grid { grid-template-columns: 1fr; }
        }
        .qr-stage {
            background: linear-gradient(145deg, rgba(11,29,74,0.6) 0%, rgba(7,15,38,0.9) 100%);
            border: 2px solid rgba(212,175,55,0.4);
            border-radius: 24px;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .qr-box-wrapper {
            background: #FFFFFF;
            padding: 1.25rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(212,175,55,0.35);
            margin: 1.5rem 0;
            display: inline-block;
            position: relative;
        }
        #qrcode canvas, #qrcode img {
            display: block;
            width: 260px !important;
            height: 260px !important;
        }
        .timer-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(212,175,55,0.15);
            border: 1px solid var(--iecep-gold);
            color: var(--iecep-gold);
            padding: 0.4rem 1.1rem;
            border-radius: 99px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        .progress-track {
            width: 280px;
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 99px;
            overflow: hidden;
            margin-top: 1rem;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #D4AF37, #F59E0B);
            width: 100%;
            transition: width 0.1s linear;
        }
        .feed-stage {
            background: rgba(11,29,74,0.45);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
        }
        .feed-item {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .campus-pill {
            background: rgba(212,175,55,0.2);
            color: #FCD34D;
            border: 1px solid rgba(212,175,55,0.4);
            font-size: 0.72rem;
            padding: 0.2rem 0.6rem;
            border-radius: 99px;
            font-weight: 700;
        }
        .ap-btn-gold {
            background: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%);
            color: #0B1D4A;
            font-weight: 800;
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .ap-btn-outline {
            background: rgba(255,255,255,0.08);
            color: #FFFFFF;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="live-top-bar">
        <div style="display:flex; align-items:center; gap:1rem;">
            <div style="width:42px; height:42px; background:var(--iecep-gold); color:var(--iecep-navy); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                <i class="fas fa-qrcode"></i>
            </div>
            <div>
                <h1 style="font-size:1.15rem; font-weight:800; color:#FFFFFF; margin:0;"><?= htmlspecialchars($event['title']) ?></h1>
                <div style="font-size:0.8rem; color:rgba(255,255,255,0.7);">
                    <i class="fas fa-location-dot" style="color:var(--iecep-gold);"></i> <?= htmlspecialchars($event['venue'] ?: 'Laguna Campus') ?> &bull; 
                    <i class="fas fa-shield" style="color:var(--accent-emerald);"></i> 15-Second Dynamic Cryptographic QR Check-in
                </div>
            </div>
        </div>
        <div style="display:flex; gap:0.75rem; align-items:center;">
            <a href="/IECEP-LSC-MEMSYS/public/portal/admin/events/attendance.php?id=<?= urlencode($eventId) ?>" class="ap-btn-gold">
                <i class="fas fa-list-check"></i> View Attendance Roster
            </a>
            <a href="/IECEP-LSC-MEMSYS/public/portal/admin/events/list.php" class="ap-btn-outline">
                <i class="fas fa-arrow-left"></i> Events
            </a>
        </div>
    </header>

    <!-- Main Live Screen Area -->
    <div class="live-grid">
        
        <!-- Left: Big Rotating Dynamic QR Stage -->
        <div class="qr-stage">
            <div class="timer-badge">
                <i class="fas fa-rotate fa-spin" style="--fa-animation-duration: 3s;"></i>
                <span>Rotating Dynamic QR &bull; Auto-Refreshes in <strong id="secondsLeft" style="font-family:'JetBrains Mono'; font-size:1rem;">15</strong>s</span>
            </div>

            <h2 style="font-size:1.6rem; font-weight:800; color:#FFFFFF; margin:0.5rem 0 0.2rem 0;">Scan to Mark Attendance</h2>
            <p style="font-size:0.9rem; color:rgba(255,255,255,0.7); max-width:480px; margin:0;">
                Students: Open your phone, log into your IECEP Member Account, and scan the QR code below. Your attendance will automatically register under your campus!
            </p>

            <div class="qr-box-wrapper">
                <div id="qrcode"></div>
            </div>

            <!-- 15s Progress Bar -->
            <div class="progress-track">
                <div class="progress-bar-fill" id="progressBar"></div>
            </div>

            <div style="display:flex; gap:1.5rem; margin-top:1.5rem; font-size:0.82rem; color:rgba(255,255,255,0.8);">
                <div><i class="fas fa-lock" style="color:var(--iecep-gold);"></i> <strong>Anti-Proxy:</strong> Screenshot Sharing Expired in 15s</div>
                <div><i class="fas fa-university" style="color:#60A5FA;"></i> <strong>Auto-Campus:</strong> Tagged to Student's Chapter</div>
            </div>
        </div>

        <!-- Right: Live Scanned Attendees Stream -->
        <div class="feed-stage">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.75rem;">
                <div>
                    <h3 style="font-size:1.1rem; font-weight:800; margin:0;">Live Attendee Feed</h3>
                    <div style="font-size:0.75rem; color:rgba(255,255,255,0.6);">Real-time Incoming Check-ins</div>
                </div>
                <div style="background:rgba(16,185,129,0.2); border:1px solid #10B981; color:#34D399; font-weight:800; font-size:1.1rem; padding:0.3rem 0.8rem; border-radius:10px;" id="totalPresentCounter">
                    0 Present
                </div>
            </div>

            <!-- Campus Counters -->
            <div id="campusStats" style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-bottom:1rem;">
                <div style="background:rgba(255,255,255,0.04); padding:0.6rem; border-radius:8px; font-size:0.75rem;">
                    <span style="color:rgba(255,255,255,0.6);">LSPU SCC</span>
                    <div style="font-weight:700; font-size:1rem; color:#FCD34D;" id="countLspuScc">0</div>
                </div>
                <div style="background:rgba(255,255,255,0.04); padding:0.6rem; border-radius:8px; font-size:0.75rem;">
                    <span style="color:rgba(255,255,255,0.6);">LSPU San Pablo</span>
                    <div style="font-weight:700; font-size:1rem; color:#60A5FA;" id="countLspuSanPablo">0</div>
                </div>
            </div>

            <!-- Stream List -->
            <div id="feedList" style="flex:1; overflow-y:auto; max-height:420px; padding-right:4px;">
                <div style="text-align:center; padding:3rem 1rem; color:rgba(255,255,255,0.4); font-size:0.85rem;">
                    <i class="fas fa-satellite-dish fa-2x" style="margin-bottom:0.75rem; opacity:0.5;"></i><br>
                    Waiting for students to scan...
                </div>
            </div>
        </div>

    </div>

    <!-- Audio Chime on Check-in -->
    <audio id="scanChime" src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU"></audio>

    <script>
        const eventId = <?= json_encode($eventId) ?>;
        const secretKey = <?= json_encode($secretKey) ?>;
        
        let qrcodeInstance = null;
        let lastWindowIndex = -1;
        let lastAttendeeCount = 0;

        // Simple HMAC-SHA256 simulation in JS or lightweight SHA-256 for browser client
        async function sha256(str) {
            const buffer = new TextEncoder("utf-8").encode(str);
            const digest = await crypto.subtle.digest("SHA-256", buffer);
            return Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');
        }

        async function compute15sToken(evtId, windowIdx) {
            return await sha256(evtId + ':' + windowIdx + ':' + secretKey);
        }

        async function refreshDynamicQRCode() {
            const now = Math.floor(Date.now() / 1000);
            const currentWindow = Math.floor(now / 15);
            const secondsRemaining = 15 - (now % 15);

            document.getElementById('secondsLeft').textContent = secondsRemaining;
            document.getElementById('progressBar').style.width = ((secondsRemaining / 15) * 100) + '%';

            if (currentWindow !== lastWindowIndex) {
                lastWindowIndex = currentWindow;
                const token = await compute15sToken(eventId, currentWindow);

                // Build QR payload JSON string
                const qrPayload = JSON.stringify({
                    event_id: eventId,
                    token: token,
                    window: currentWindow,
                    expires_in: 15
                });

                const qrContainer = document.getElementById('qrcode');
                qrContainer.innerHTML = '';
                qrcodeInstance = new QRCode(qrContainer, {
                    text: qrPayload,
                    width: 260,
                    height: 260,
                    colorDark: "#0B1D4A",
                    colorLight: "#FFFFFF",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        }

        // Poll live attendee stream
        async function pollLiveAttendees() {
            try {
                const response = await fetch(`/IECEP-LSC-MEMSYS/public/api/events/attendance.php?action=live_stream&event_id=${encodeURIComponent(eventId)}`);
                const res = await response.json();
                if (res.success) {
                    const attendees = res.attendees || [];
                    document.getElementById('totalPresentCounter').textContent = attendees.length + ' Present';

                    // Update campus stats
                    let scc = 0, sp = 0;
                    attendees.forEach(a => {
                        const c = (a.institution_name || '').toLowerCase();
                        if (c.includes('santa cruz') || (a.institution_acronym || '').includes('SCC')) scc++;
                        else if (c.includes('san pablo')) sp++;
                    });
                    document.getElementById('countLspuScc').textContent = scc;
                    document.getElementById('countLspuSanPablo').textContent = sp;

                    // Update feed
                    if (attendees.length > 0) {
                        const feed = document.getElementById('feedList');
                        feed.innerHTML = attendees.slice(0, 10).map(a => {
                            const timeStr = a.attended_at ? new Date(a.attended_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'}) : 'Just now';
                            const acronym = a.institution_acronym || 'LSPU SCC';
                            return `
                                <div class="feed-item">
                                    <div>
                                        <strong style="color:#FFFFFF; font-size:0.88rem;">${esc(a.member_name || 'Student')}</strong>
                                        <div style="font-size:0.75rem; color:rgba(255,255,255,0.6); margin-top:2px;">
                                            <i class="fas fa-clock" style="color:var(--iecep-gold);"></i> ${timeStr}
                                        </div>
                                    </div>
                                    <span class="campus-pill">${esc(acronym)}</span>
                                </div>
                            `;
                        }).join('');
                    }
                }
            } catch (err) {
                console.warn('Live poll error:', err);
            }
        }

        function esc(t) {
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }

        // Initialize timers
        setInterval(refreshDynamicQRCode, 500);
        refreshDynamicQRCode();
        setInterval(pollLiveAttendees, 3000);
        pollLiveAttendees();
    </script>
</body>
</html>
