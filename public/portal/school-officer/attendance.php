<?php
if (!isset($current_page)) { $current_page = 'attendance'; }
require_once dirname(__DIR__, 3) . '/bootstrap.php';
require_once dirname(__DIR__, 1) . '/auth_check.php';
require_once INCLUDES_PATH . 'config.php';
require_once INCLUDES_PATH . 'role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'School Chapter Event Attendance & Compliance';
$supabase = getSupabaseClient();
$user = $_SESSION['user'] ?? [];
$myInstId = $user['institution_id'] ?? ($_SESSION['institution_id'] ?? null);

if (empty($myInstId) && !empty($user['id'])) {
    try {
        $profRes = $supabase->select('user_profiles', ['user_id' => 'eq.' . $user['id']]);
        if (is_array($profRes) && isset($profRes[0]['institution_id'])) {
            $myInstId = $profRes[0]['institution_id'];
        }
    } catch (Exception $e) {}
}

if (empty($myInstId)) {
    $myInstId = '1fe48809-8ac6-4428-a6f1-3025cc47f5bb';
}

$myInstitution = null;
try {
    $instData = $supabase->select('institutions', ['id' => 'eq.' . $myInstId]);
    if (is_array($instData) && isset($instData[0]) && !isset($instData['code'])) {
        $myInstitution = $instData[0];
    }
} catch (Exception $e) {}

if (!$myInstitution) {
    $myInstitution = [
        'id' => $myInstId,
        'name' => 'Laguna State Polytechnic University - Santa Cruz Campus',
        'acronym' => 'LSPU - SCC',
        'membership_count' => 150
    ];
}

$instName = $myInstitution['name'] ?? 'Laguna State Polytechnic University - Santa Cruz Campus';
$instAcronym = $myInstitution['acronym'] ?? 'LSPU - SCC';
$totalMembers = intval($myInstitution['membership_count'] ?? 150);

// Fetch all events for the modal selector
$eventsList = [];
try {
    $evRes = $supabase->select('events', ['select' => '*', 'order' => 'start_date.desc']);
    if (is_array($evRes) && !isset($evRes['code'])) {
        $eventsList = $evRes;
    }
} catch (Exception $e) {}

if (empty($eventsList)) {
    $eventsList = [
        [
            'id' => '2f2f99ce-98e1-49f6-8949-760687189aa6',
            'title' => 'IECEP-LSC Regional Technical Summit 2026',
            'venue' => 'Main Auditorium / Online'
        ]
    ];
}

// Fetch all attendance for this chapter
$attendances = [];
try {
    $allAtt = $supabase->select('event_attendees', [
        'order' => 'check_in_time.desc'
    ]);
    
    // Map members for full names
    $allMems = $supabase->select('members', ['select' => 'id,full_name,email,institution_id,membership_id']);
    $memMap = [];
    if (is_array($allMems) && !isset($allMems['code'])) {
        foreach ($allMems as $m) {
            if (isset($m['id'])) $memMap[$m['id']] = $m;
        }
    }

    // Map events for titles
    $evMap = [];
    foreach ($eventsList as $ev) {
        if (isset($ev['id'])) $evMap[$ev['id']] = $ev['title'];
    }

    if (is_array($allAtt) && !isset($allAtt['code'])) {
        foreach ($allAtt as $att) {
            if (!isset($att['member_id'])) continue;
            $mId = $att['member_id'];
            $mem = $memMap[$mId] ?? null;
            $memberInst = $mem['institution_id'] ?? '';

            if ($memberInst === $myInstId || empty($memberInst) || stripos($instName, 'Santa Cruz') !== false) {
                $att['member_name'] = $mem['full_name'] ?? 'Student Member';
                $att['member_email'] = $mem['email'] ?? '';
                $att['membership_id'] = $mem['membership_id'] ?? 'IECEP-2026-XXXX';
                $att['event_name'] = $evMap[$att['event_id'] ?? ''] ?? 'IECEP Chapter Event';
                $attendances[] = $att;
            }
        }
    }
} catch (Exception $e) {
    error_log("School officer attendance error: " . $e->getMessage());
}

$presentCount = count($attendances);
$participationRate = round(($presentCount / max(1, $totalMembers)) * 100, 1);
$complianceStatus = ($participationRate >= 40) ? 'compliant' : (($participationRate >= 20) ? 'at_risk' : 'non_compliant');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($instAcronym) ?></title>
    <meta name="description" content="Campus-level event attendance tracking, modal 15-second dynamic QR code generator, and school officer scanner.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.7);
            backdrop-filter: blur(6px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal-inner-box {
            background: #FFFFFF;
            border-radius: 20px;
            max-width: 580px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid var(--border-light);
            animation: popModal 0.25s ease;
        }
        @keyframes popModal {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-navy-header {
            background: linear-gradient(135deg, #0B1D4A 0%, #17306b 100%);
            color: #FFFFFF;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #D4AF37;
        }
        .qr-canvas-holder {
            background: #FFFFFF;
            padding: 1rem;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(11,29,74,0.1);
            display: inline-block;
            margin: 1rem 0;
            border: 1px solid var(--border-light);
        }
        #modalQrcode canvas, #modalQrcode img {
            width: 220px !important;
            height: 220px !important;
            display: block;
            margin: 0 auto;
        }
        .timer-badge-sm {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(212,175,55,0.15);
            border: 1px solid #D4AF37;
            color: #B8860B;
            padding: 0.3rem 0.85rem;
            border-radius: 99px;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .progress-track-sm {
            width: 240px;
            height: 6px;
            background: #E2E8F0;
            border-radius: 99px;
            overflow: hidden;
            margin: 0.5rem auto 0 auto;
        }
        .progress-fill-sm {
            height: 100%;
            background: linear-gradient(90deg, #D4AF37, #F59E0B);
            width: 100%;
            transition: width 0.1s linear;
        }
        #officerReader {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
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
                    <h1 class="ap-page-title"><i class="fas fa-calendar-check"></i> Chapter Event Attendance Ledger</h1>
                    <p class="ap-page-subtitle">Institutional Chapter: <strong><?= htmlspecialchars($instName) ?> (<?= htmlspecialchars($instAcronym) ?>)</strong></p>
                </div>
                <div class="ap-header-actions">
                    <!-- Action Button 1: Open 15s Rotating Dynamic QR Modal -->
                    <button class="ap-btn-primary" onclick="openLiveQrModal()">
                        <i class="fas fa-qrcode"></i> Generate 15s Dynamic QR
                    </button>
                    <!-- Action Button 2: Open School Officer Camera Scanner Modal -->
                    <button class="ap-btn-secondary" onclick="openScannerModal()">
                        <i class="fas fa-camera"></i> Scan Student QR Code
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-users"></i></div>
                        <div><div class="ap-stat-label">Roster</div><div class="ap-stat-sublabel">Total Enrolled</div></div>
                    </div>
                    <div class="ap-stat-value"><?= $totalMembers ?></div>
                    <div class="ap-stat-footer">Chapter Registered Members</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                        <div><div class="ap-stat-label">Present</div><div class="ap-stat-sublabel">Verified Attendees</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);" id="kpiPresentCount"><?= $presentCount ?></div>
                    <div class="ap-stat-footer">Dynamic 15s & Officer Scans</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-percent"></i></div>
                        <div><div class="ap-stat-label">Rate</div><div class="ap-stat-sublabel">Participation</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--iecep-gold);" id="kpiRate"><?= $participationRate ?>%</div>
                    <div class="ap-stat-footer">Chapter Target: ≥ 40%</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon cyan"><i class="fas fa-scale-balanced"></i></div>
                        <div><div class="ap-stat-label">Standing</div><div class="ap-stat-sublabel">Compliance Rating</div></div>
                    </div>
                    <div class="ap-stat-value" style="font-size:1.15rem; color:<?= $complianceStatus === 'compliant' ? 'var(--accent-emerald)' : 'var(--accent-amber)' ?>;">
                        <?= ucfirst($complianceStatus) ?>
                    </div>
                    <div class="ap-stat-footer">Constitution Art. V Sec. 3</div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> <?= htmlspecialchars($instAcronym) ?> Attended Students Roster</h3>
                    <div class="ap-toolbar" style="margin-bottom:0;">
                        <div class="ap-search-wrapper" style="min-width:220px;">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" id="attSearch" class="ap-search-input" placeholder="Search student name..." onkeyup="filterRoster()">
                        </div>
                    </div>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table" id="rosterTable">
                        <thead>
                            <tr>
                                <th>Student Member</th>
                                <th>Membership ID</th>
                                <th>Event Title</th>
                                <th>Check-in Timestamp</th>
                                <th>Status</th>
                                <th>Cryptographic Hash</th>
                            </tr>
                        </thead>
                        <tbody id="rosterTbody">
                            <?php if (empty($attendances)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:2.5rem; color:var(--text-muted);">No attendance records recorded yet for <?= htmlspecialchars($instAcronym) ?> students. Click "Generate 15s Dynamic QR" or "Scan Student QR Code" above.</td></tr>
                            <?php else: ?>
                                <?php foreach ($attendances as $att): ?>
                                    <?php 
                                        $hash = $att['verification_hash'] ?? hash('sha256', ($att['id'] ?? '') . ($att['member_name'] ?? ''));
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                                <div class="ap-avatar-badge navy"><?= strtoupper(substr($att['member_name'] ?? 'S', 0, 2)) ?></div>
                                                <div>
                                                    <strong style="color:var(--text-heading); font-size:0.92rem;"><?= htmlspecialchars($att['member_name'] ?? 'Student') ?></strong><br>
                                                    <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($att['member_email'] ?? '') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ap-pill gold" style="font-family:'JetBrains Mono'; font-size:0.75rem;"><?= htmlspecialchars($att['membership_id'] ?? 'IECEP-2026-0001') ?></span>
                                        </td>
                                        <td>
                                            <strong style="color:var(--text-heading); font-size:0.85rem;"><?= htmlspecialchars($att['event_name'] ?: 'IECEP Event') ?></strong>
                                        </td>
                                        <td style="font-size:0.82rem; color:var(--text-heading); font-weight:600;">
                                            <i class="fas fa-clock" style="color:var(--iecep-gold); margin-right:4px;"></i>
                                            <?= isset($att['check_in_time']) ? date('M d, Y &bull; h:i A', strtotime($att['check_in_time'])) : date('M d, Y') ?>
                                        </td>
                                        <td>
                                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Present</span>
                                        </td>
                                        <td>
                                            <span class="ap-mono" style="font-size:0.72rem; color:var(--iecep-navy);"><?= substr($hash, 0, 16) ?>...</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-qrcode"></i><span><strong>Modal QR Engine:</strong> 15-Second Dynamic Rotating TOTP</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-camera"></i><span><strong>Officer Scanner:</strong> Direct Student Digital ID Verification</span></div>
            </div>

        </div>
    </main>

    <!-- ========================================================================= -->
    <!-- MODAL 1: Live 15-Second Rotating Dynamic QR Attendance Modal              -->
    <!-- ========================================================================= -->
    <div id="liveQrModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="modal-navy-header">
                <div>
                    <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#FFFFFF;"><i class="fas fa-qrcode" style="color:#D4AF37;"></i> Live 15s Dynamic Attendance QR</h3>
                    <div style="font-size:0.75rem; color:rgba(255,255,255,0.75);">Project or show on laptop for students to scan</div>
                </div>
                <button type="button" onclick="closeLiveQrModal()" style="background:transparent; border:none; color:#FFFFFF; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>

            <div style="padding:1.5rem; text-align:center;">
                <!-- Select Event -->
                <div style="text-align:left; margin-bottom:1rem;">
                    <label class="ap-form-label" style="font-size:0.78rem;">Target Event:</label>
                    <select id="modalEventSelect" class="ap-form-select" onchange="onModalEventChange()">
                        <?php foreach ($eventsList as $ev): ?>
                            <option value="<?= htmlspecialchars($ev['id']) ?>"><?= htmlspecialchars($ev['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 15s Rotating Timer Badge -->
                <div class="timer-badge-sm">
                    <i class="fas fa-rotate fa-spin"></i>
                    <span>Rotating Dynamic QR &bull; Changes in <strong id="modalSecondsLeft" style="font-family:'JetBrains Mono'; font-size:0.95rem;">15</strong>s</span>
                </div>

                <!-- QR Canvas Box -->
                <div class="qr-canvas-holder">
                    <div id="modalQrcode"></div>
                </div>

                <!-- 15s Progress Bar -->
                <div class="progress-track-sm">
                    <div class="progress-fill-sm" id="modalProgressBar"></div>
                </div>

                <div style="font-size:0.78rem; color:var(--text-muted); margin-top:1rem;">
                    <i class="fas fa-shield" style="color:var(--accent-emerald);"></i> Anti-proxy enabled &bull; Automatically tags scanned students under <strong><?= htmlspecialchars($instAcronym) ?></strong>.
                </div>

                <div style="display:flex; justify-content:center; gap:0.75rem; margin-top:1.25rem;">
                    <a id="modalFullscreenLink" href="/IECEP-LSC-MEMSYS/public/portal/admin/events/live-qr.php?id=<?= urlencode($eventsList[0]['id'] ?? '') ?>" target="_blank" class="ap-btn-secondary" style="font-size:0.8rem; padding:0.45rem 1rem;">
                        <i class="fas fa-expand"></i> Fullscreen Screen Mode
                    </a>
                    <button type="button" class="ap-btn-primary" style="font-size:0.8rem; padding:0.45rem 1rem;" onclick="closeLiveQrModal()">
                        Done / Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: School Officer Camera Scanner Modal (Scan Student Digital ID)    -->
    <!-- ========================================================================= -->
    <div id="officerScannerModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="modal-navy-header">
                <div>
                    <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#FFFFFF;"><i class="fas fa-camera" style="color:#D4AF37;"></i> Scan Student QR Code</h3>
                    <div style="font-size:0.75rem; color:rgba(255,255,255,0.75);">Point camera at student's Digital ID or attendance code</div>
                </div>
                <button type="button" onclick="closeScannerModal()" style="background:transparent; border:none; color:#FFFFFF; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>

            <div style="padding:1.5rem;">
                <!-- Select Event -->
                <div style="margin-bottom:1rem;">
                    <label class="ap-form-label" style="font-size:0.78rem;">Check-in For Event:</label>
                    <select id="scannerEventSelect" class="ap-form-select">
                        <?php foreach ($eventsList as $ev): ?>
                            <option value="<?= htmlspecialchars($ev['id']) ?>"><?= htmlspecialchars($ev['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Camera Stream Viewport -->
                <div id="officerReader"></div>

                <div style="display:flex; gap:0.5rem; margin-top:1rem; justify-content:center;">
                    <button id="officerStartBtn" class="ap-btn-primary" onclick="startOfficerScanner()">
                        <i class="fas fa-video"></i> Start Camera
                    </button>
                    <button id="officerStopBtn" class="ap-btn-secondary" style="display:none;" onclick="stopOfficerScanner()">
                        <i class="fas fa-stop"></i> Stop Camera
                    </button>
                </div>

                <!-- Verification Result Card -->
                <div id="officerScanResult" style="display:none; margin-top:1rem; padding:1rem; border-radius:12px; text-align:center;"></div>

                <!-- Manual Input Fallback -->
                <div style="margin-top:1.25rem; padding-top:1rem; border-top:1px dashed var(--border-light);">
                    <label class="ap-form-label" style="font-size:0.78rem;">Manual Student ID / Email Entry:</label>
                    <div style="display:flex; gap:0.5rem;">
                        <input type="text" id="manualStudentInput" class="ap-input" placeholder="e.g. IECEP-2026-0001 or student email">
                        <button type="button" class="ap-btn-primary" onclick="submitManualStudent()">Check-in</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audio Chime -->
    <audio id="beepSound" src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU"></audio>

    <script>
        const secretKey = 'IECEP_LSC_ROTATING_QR_SECRET_2026';
        let modalQrInstance = null;
        let qrTimerInterval = null;
        let lastModalWindow = -1;

        // SHA-256 for browser client
        async function sha256(str) {
            const buffer = new TextEncoder("utf-8").encode(str);
            const digest = await crypto.subtle.digest("SHA-256", buffer);
            return Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');
        }

        // ==========================================
        // 1. Live 15s Dynamic QR Modal Controller
        // ==========================================
        function openLiveQrModal() {
            document.getElementById('liveQrModal').style.display = 'flex';
            lastModalWindow = -1;
            refreshModalQR();
            if (qrTimerInterval) clearInterval(qrTimerInterval);
            qrTimerInterval = setInterval(refreshModalQR, 500);
        }

        function closeLiveQrModal() {
            document.getElementById('liveQrModal').style.display = 'none';
            if (qrTimerInterval) clearInterval(qrTimerInterval);
        }

        function onModalEventChange() {
            const evtId = document.getElementById('modalEventSelect').value;
            document.getElementById('modalFullscreenLink').href = '/IECEP-LSC-MEMSYS/public/portal/admin/events/live-qr.php?id=' + encodeURIComponent(evtId);
            lastModalWindow = -1;
            refreshModalQR();
        }

        async function refreshModalQR() {
            const eventId = document.getElementById('modalEventSelect').value;
            const now = Math.floor(Date.now() / 1000);
            const currentWindow = Math.floor(now / 15);
            const secondsRemaining = 15 - (now % 15);

            document.getElementById('modalSecondsLeft').textContent = secondsRemaining;
            document.getElementById('modalProgressBar').style.width = ((secondsRemaining / 15) * 100) + '%';

            if (currentWindow !== lastModalWindow) {
                lastModalWindow = currentWindow;
                const token = await sha256(eventId + ':' + currentWindow + ':' + secretKey);

                const payload = JSON.stringify({
                    event_id: eventId,
                    token: token,
                    window: currentWindow,
                    expires_in: 15
                });

                const container = document.getElementById('modalQrcode');
                container.innerHTML = '';
                modalQrInstance = new QRCode(container, {
                    text: payload,
                    width: 220,
                    height: 220,
                    colorDark: "#0B1D4A",
                    colorLight: "#FFFFFF",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        }

        // ==========================================
        // 2. School Officer Camera Scanner Controller
        // ==========================================
        let officerScanner = null;
        let isOfficerScanning = false;

        function openScannerModal() {
            document.getElementById('officerScannerModal').style.display = 'flex';
            document.getElementById('officerScanResult').style.display = 'none';
            startOfficerScanner();
        }

        function closeScannerModal() {
            stopOfficerScanner();
            document.getElementById('officerScannerModal').style.display = 'none';
        }

        function startOfficerScanner() {
            officerScanner = new Html5Qrcode("officerReader");
            const config = { fps: 10, qrbox: { width: 220, height: 220 } };

            officerScanner.start(
                { facingMode: "environment" },
                config,
                onOfficerScanSuccess,
                onOfficerScanError
            ).then(() => {
                isOfficerScanning = true;
                document.getElementById('officerStartBtn').style.display = 'none';
                document.getElementById('officerStopBtn').style.display = 'inline-flex';
            }).catch(err => {
                console.warn('Camera error:', err);
                document.getElementById('officerStartBtn').style.display = 'inline-flex';
                document.getElementById('officerStopBtn').style.display = 'none';
            });
        }

        function stopOfficerScanner() {
            if (officerScanner && isOfficerScanning) {
                officerScanner.stop().then(() => {
                    isOfficerScanning = false;
                    document.getElementById('officerStartBtn').style.display = 'inline-flex';
                    document.getElementById('officerStopBtn').style.display = 'none';
                }).catch(() => {});
            }
        }

        async function onOfficerScanSuccess(decodedText) {
            stopOfficerScanner();
            submitStudentCheckin(decodedText);
        }

        function onOfficerScanError(err) {
            // ignore continuous scanning errors
        }

        function submitManualStudent() {
            const val = document.getElementById('manualStudentInput').value.trim();
            if (!val) { alert('Please enter a Membership ID or email.'); return; }
            submitStudentCheckin(val);
        }

        async function submitStudentCheckin(studentQrData) {
            const eventId = document.getElementById('scannerEventSelect').value;
            const resBox = document.getElementById('officerScanResult');
            resBox.style.display = 'block';
            resBox.style.background = '#F0F9FF';
            resBox.style.border = '1px solid #BAE6FD';
            resBox.style.color = '#0369A1';
            resBox.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking in student...';

            try {
                const resp = await fetch('/IECEP-LSC-MEMSYS/public/api/events/attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'officer_scan_student',
                        event_id: eventId,
                        student_qr: studentQrData
                    })
                });

                const result = await resp.json();

                if (result.success) {
                    const data = result.data || {};
                    resBox.style.background = '#ECFDF5';
                    resBox.style.border = '2px solid #10B981';
                    resBox.style.color = '#065F46';
                    resBox.innerHTML = `
                        <div style="font-size:1.25rem; font-weight:800; margin-bottom:4px;"><i class="fas fa-check-circle"></i> ${esc(result.message)}</div>
                        <div style="font-size:0.85rem;">Student: <strong>${esc(data.student_name || 'Member')}</strong> (${esc(data.membership_id || '')})</div>
                        <div style="font-size:0.8rem; color:#047857; margin-top:2px;">Campus: <strong>${esc(data.institution_acronym || 'LSPU SCC')}</strong></div>
                        <button class="ap-btn-primary" style="margin-top:0.75rem; font-size:0.78rem; padding:0.35rem 0.85rem;" onclick="startOfficerScanner()">
                            <i class="fas fa-camera"></i> Scan Next Student
                        </button>
                    `;

                    // Increment KPI count on page dynamically
                    const kpi = document.getElementById('kpiPresentCount');
                    if (kpi) kpi.textContent = parseInt(kpi.textContent || '0') + 1;

                    // Append to table
                    const tbody = document.getElementById('rosterTbody');
                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `
                        <td>
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <div class="ap-avatar-badge navy">${esc((data.student_name||'S').substring(0,2).toUpperCase())}</div>
                                <div>
                                    <strong style="color:var(--text-heading); font-size:0.92rem;">${esc(data.student_name||'Student')}</strong>
                                </div>
                            </div>
                        </td>
                        <td><span class="ap-pill gold" style="font-family:'JetBrains Mono'; font-size:0.75rem;">${esc(data.membership_id||'')}</span></td>
                        <td><strong style="color:var(--text-heading); font-size:0.85rem;">${esc(data.event_title||'IECEP Event')}</strong></td>
                        <td style="font-size:0.82rem; color:var(--text-heading); font-weight:600;"><i class="fas fa-clock" style="color:var(--iecep-gold); margin-right:4px;"></i> Just now</td>
                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Present</span></td>
                        <td><span class="ap-mono" style="font-size:0.72rem; color:var(--iecep-navy);">${(data.hash||'').substring(0,16)}...</span></td>
                    `;
                    tbody.insertBefore(newRow, tbody.firstChild);
                } else {
                    resBox.style.background = '#FEF2F2';
                    resBox.style.border = '2px solid #EF4444';
                    resBox.style.color = '#991B1B';
                    resBox.innerHTML = `
                        <div style="font-weight:700;"><i class="fas fa-circle-xmark"></i> ${esc(result.message || 'Check-in failed')}</div>
                        <button class="ap-btn-secondary" style="margin-top:0.5rem; font-size:0.78rem; padding:0.3rem 0.75rem;" onclick="startOfficerScanner()">Try Again</button>
                    `;
                }
            } catch (err) {
                resBox.style.background = '#FEF2F2';
                resBox.style.border = '2px solid #EF4444';
                resBox.style.color = '#991B1B';
                resBox.innerHTML = `<div><i class="fas fa-circle-xmark"></i> Network error: ${esc(err.message)}</div>`;
            }
        }

        function esc(t) {
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }

        function filterRoster() {
            const q = document.getElementById('attSearch').value.toLowerCase();
            document.querySelectorAll('#rosterTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
