<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'scan';
$pageTitle = 'Event QR Attendance Scanner';

$user = get_user_info();
$userId = $user['id'] ?? null;
$userEmail = $user['email'] ?? '';
$displayName = $user['full_name'] ?? $user['name'] ?? $userEmail;

$supabase = getSupabaseClient();

// Fetch Member Record Strictly from Database
$member = [];
$schoolName = 'Affiliated Student Chapter';
$schoolAcronym = 'IECEP-SC';

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
        $instId = $member['institution_id'] ?? null;
        if ($instId) {
            $iRes = $supabase->select('institutions', ['id' => 'eq.' . $instId]);
            if (is_array($iRes) && isset($iRes[0]['name'])) {
                $schoolName = $iRes[0]['name'];
                $schoolAcronym = $iRes[0]['acronym'] ?? 'IECEP-SC';
            }
        }
    } catch (Exception $e) {}
}

$memberDbId = $member['id'] ?? $userId;
$membershipId = $member['membership_id'] ?? 'Pending Assignment';
$memberFullName = $member['full_name'] ?? $displayName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Live camera attendance scanner for verified IECEP-LSC chapter delegates.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
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
            padding: 1.5rem;
            min-height: 100vh;
            box-sizing: border-box;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }

        .scanner-card-white {
            max-width: 500px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-color);
            border-top: 5px solid #0B1D4A;
            overflow: hidden;
        }

        .scanner-header-white {
            background: #FFFFFF;
            color: #0F172A;
            padding: 1.5rem 1.25rem 1rem;
            text-align: center;
            border-bottom: 1px solid #F1F5F9;
        }

        #reader {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #0F172A;
        }

        .result-success-box {
            display: none;
            background: #ECFDF5;
            border: 2px solid #10B981;
            border-radius: 14px;
            padding: 1.25rem;
            text-align: center;
            margin-top: 1rem;
            animation: popIn 0.3s ease;
        }
        @keyframes popIn {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .btn-primary-navy {
            background: var(--color-navy);
            color: #FFFFFF;
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid var(--color-navy);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
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
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
            <div>
                <h1 style="font-size:1.4rem; font-weight:800; color:#0F172A; margin:0 0 0.2rem 0; display:flex; align-items:center; gap:0.5rem;">
                    <i class="fas fa-camera" style="color:var(--color-navy);"></i> Live Event QR Scanner
                </h1>
                <p style="margin:0; font-size:0.82rem; color:#64748B;">
                    Scan the dynamic rotating event QR code presented at the chapter registration desk.
                </p>
            </div>
            <div>
                <a href="/IECEP-LSC-MEMSYS/public/portal/member/events.php" class="btn-white">
                    <i class="fas fa-calendar-check"></i> My Events
                </a>
            </div>
        </div>

        <!-- White Theme Scanner Card -->
        <div class="scanner-card-white">
            <div class="scanner-header-white">
                <div style="width:48px; height:48px; background:#FEF9C3; color:#0B1D4A; border:1px solid #FDE047; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:0.5rem;">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h2 style="font-size:1.2rem; font-weight:800; margin:0 0 0.2rem 0; color:#0B1D4A;">Student Attendance Check-in</h2>
                <div style="font-size:0.8rem; color:#64748B; margin-top:2px;">
                    Delegate: <strong style="color:#0F172A;"><?= htmlspecialchars($memberFullName) ?></strong> • <span style="font-family:'JetBrains Mono', monospace; font-weight:700; color:#0B1D4A;"><?= htmlspecialchars($membershipId) ?></span>
                </div>
            </div>

            <div style="padding: 1.25rem;">
                <!-- Camera Viewport -->
                <div id="reader"></div>

                <div style="display:flex; gap:0.5rem; margin-top:1rem; justify-content:center;">
                    <button id="startScanBtn" class="btn-primary-navy" onclick="startScanner()">
                        <i class="fas fa-camera"></i> Start Camera Scanner
                    </button>
                    <button id="stopScanBtn" class="btn-white" style="display:none;" onclick="stopScanner()">
                        <i class="fas fa-stop"></i> Stop Camera
                    </button>
                </div>

                <!-- Status Notification -->
                <div id="scanStatus" style="display:none; margin-top:1rem; padding:0.75rem; border-radius:8px; font-size:0.82rem; text-align:center;">
                    <i class="fas fa-spinner fa-spin me-1"></i> Validating dynamic QR code...
                </div>

                <!-- Result Celebration Card -->
                <div id="resultSuccess" class="result-success-box">
                    <div style="width:48px; height:48px; background:#10B981; color:#FFFFFF; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; margin-bottom:0.5rem;">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 style="color:#065F46; font-weight:800; font-size:1.15rem; margin:0 0 2px 0;">Attendance Verified!</h3>
                    <div style="font-size:0.84rem; color:#047857; margin-bottom:0.75rem;" id="resEventTitle">IECEP Technical Seminar</div>

                    <div style="background:#FFFFFF; border:1px solid #A7F3D0; border-radius:8px; padding:0.75rem; font-size:0.8rem; text-align:left; color:#064E3B; margin-bottom:1rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <strong>Student Member:</strong>
                            <span id="resStudentName"><?= htmlspecialchars($memberFullName) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <strong>Campus Chapter:</strong>
                            <span style="font-weight:700; color:#0B1D4A;" id="resCampus"><?= htmlspecialchars($schoolAcronym) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <strong>Recorded Time:</strong>
                            <span id="resTime">Just now</span>
                        </div>
                    </div>

                    <button class="btn-primary-navy" onclick="resetScanner()">
                        <i class="fas fa-camera"></i> Scan Another Event
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        let html5QrCode = null;
        let isScanning = false;

        function startScanner() {
            html5QrCode = new Html5Qrcode("reader");
            const config = { fps: 10, qrbox: { width: 220, height: 220 } };

            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanError
            ).then(() => {
                isScanning = true;
                document.getElementById('startScanBtn').style.display = 'none';
                document.getElementById('stopScanBtn').style.display = 'inline-flex';
            }).catch(err => {
                alert('Camera permission needed: ' + err);
            });
        }

        function stopScanner() {
            if (html5QrCode && isScanning) {
                html5QrCode.stop().then(() => {
                    isScanning = false;
                    document.getElementById('startScanBtn').style.display = 'inline-flex';
                    document.getElementById('stopScanBtn').style.display = 'none';
                });
            }
        }

        async function onScanSuccess(decodedText) {
            stopScanner();
            const statusEl = document.getElementById('scanStatus');
            statusEl.style.display = 'block';
            statusEl.style.background = '#EFF6FF';
            statusEl.style.color = '#1D4ED8';
            statusEl.style.border = '1px solid #BFDBFE';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting attendance verification...';

            try {
                let parsed = null;
                try {
                    parsed = JSON.parse(decodedText);
                } catch(e) {
                    parsed = { event_id: decodedText, token: decodedText };
                }

                const response = await fetch('/IECEP-LSC-MEMSYS/public/api/events/attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        event_id: parsed.event_id || '',
                        token: parsed.token || decodedText,
                        member_id: <?= json_encode($memberDbId) ?>,
                        full_name: <?= json_encode($memberFullName) ?>,
                        email: <?= json_encode($userEmail) ?>
                    })
                });

                const result = await response.json();
                statusEl.style.display = 'none';

                if (result.success && !result.already_recorded) {
                    const data = result.data || {};
                    document.getElementById('resEventTitle').textContent = data.event_title || 'IECEP Event';
                    document.getElementById('resStudentName').textContent = data.student_name || <?= json_encode($memberFullName) ?>;
                    document.getElementById('resCampus').textContent = data.institution_acronym || <?= json_encode($schoolAcronym) ?>;
                    document.getElementById('resTime').textContent = new Date().toLocaleTimeString();
                    document.getElementById('resultSuccess').style.display = 'block';
                } else if (result.already_recorded) {
                    statusEl.style.display = 'block';
                    statusEl.style.background = '#FEF9C3';
                    statusEl.style.color = '#A16207';
                    statusEl.style.border = '1px solid #FDE047';
                    statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> ' + (result.message || 'You have already checked in for this event.');
                } else {
                    statusEl.style.display = 'block';
                    statusEl.style.background = '#FEE2E2';
                    statusEl.style.color = '#B91C1C';
                    statusEl.style.border = '1px solid #FECACA';
                    statusEl.innerHTML = '<i class="fas fa-times-circle me-1"></i> ' + (result.message || 'Verification failed. QR code may have expired.');
                }
            } catch (err) {
                statusEl.style.display = 'block';
                statusEl.style.background = '#FEE2E2';
                statusEl.style.color = '#B91C1C';
                statusEl.style.border = '1px solid #FECACA';
                statusEl.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Network error: ' + err.message;
            }
        }

        function onScanError(errorMessage) {}

        function resetScanner() {
            document.getElementById('resultSuccess').style.display = 'none';
            startScanner();
        }
    </script>
</body>
</html>
