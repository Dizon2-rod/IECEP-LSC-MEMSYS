<?php
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

$current_page = 'scan';
$pageTitle = 'Event QR Attendance Scanner';

$user = $_SESSION['user'] ?? [];
$userName = $user['user_metadata']['full_name'] ?? $user['name'] ?? $user['full_name'] ?? 'Rashed Dizon';
$userEmail = $user['email'] ?? 'rasheddizon7@gmail.com';
$userId = $user['id'] ?? 'mem_rashed_dizon';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Scan live event QR codes from your mobile phone camera to verify your chapter attendance.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        .scanner-card {
            max-width: 520px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(11,29,74,0.1);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }
        .scanner-header {
            background: linear-gradient(135deg, #0B1D4A 0%, #17306b 100%);
            color: #FFFFFF;
            padding: 1.5rem;
            text-align: center;
        }
        #reader {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }
        .result-success-box {
            display: none;
            background: #ECFDF5;
            border: 2px solid #10B981;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            margin-top: 1.25rem;
            animation: popIn 0.3s ease;
        }
        @keyframes popIn {
            0% { transform: scale(0.9); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-camera"></i> Live Event QR Scanner</h1>
                    <p class="ap-page-subtitle">Scan the dynamic 15-second rotating QR code on the organizer's laptop screen.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/member/events.php" class="ap-btn-secondary">
                        <i class="fas fa-calendar"></i> My Events
                    </a>
                </div>
            </div>

            <!-- Scanner Card -->
            <div class="scanner-card">
                <div class="scanner-header">
                    <div style="width:48px; height:48px; background:var(--iecep-gold); color:var(--iecep-navy); border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:0.5rem;">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h2 style="font-size:1.25rem; font-weight:800; margin:0; color:#FFFFFF;">Student Attendance Check-in</h2>
                    <div style="font-size:0.8rem; color:rgba(255,255,255,0.8); margin-top:2px;">
                        Logged in as: <strong><?= htmlspecialchars($userName) ?></strong>
                    </div>
                </div>

                <div style="padding: 1.5rem;">
                    <!-- Camera Viewport -->
                    <div id="reader"></div>

                    <div style="display:flex; gap:0.5rem; margin-top:1rem; justify-content:center;">
                        <button id="startScanBtn" class="ap-btn-primary" onclick="startScanner()">
                            <i class="fas fa-camera"></i> Start Phone Camera
                        </button>
                        <button id="stopScanBtn" class="ap-btn-secondary" style="display:none;" onclick="stopScanner()">
                            <i class="fas fa-stop"></i> Stop Camera
                        </button>
                    </div>

                    <!-- Status Notification -->
                    <div id="scanStatus" style="display:none; margin-top:1rem;" class="ap-alert info">
                        <i class="fas fa-spinner fa-spin"></i> Validating dynamic QR code...
                    </div>

                    <!-- Result Celebration Card -->
                    <div id="resultSuccess" class="result-success-box">
                        <div style="width:54px; height:54px; background:#10B981; color:#FFFFFF; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:0.75rem;">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3 style="color:#065F46; font-weight:800; font-size:1.25rem; margin:0 0 4px 0;">Attendance Verified!</h3>
                        <div style="font-size:0.9rem; color:#047857; margin-bottom:0.75rem;" id="resEventTitle">IECEP Technical Seminar</div>

                        <div style="background:#FFFFFF; border:1px solid #A7F3D0; border-radius:10px; padding:0.75rem; font-size:0.82rem; text-align:left; color:#064E3B; margin-bottom:1rem;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <strong>Student Member:</strong>
                                <span id="resStudentName"><?= htmlspecialchars($userName) ?></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <strong>Attributed Campus:</strong>
                                <span class="ap-pill gold" id="resCampus">LSPU SCC</span>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <strong>Timestamp:</strong>
                                <span id="resTime">Just now</span>
                            </div>
                        </div>

                        <button class="ap-btn-primary" onclick="resetScanner()">
                            <i class="fas fa-camera"></i> Scan Another Event
                        </button>
                    </div>

                    <!-- Quick Testing Panel (Simulate scan on laptop) -->
                    <div style="margin-top:1.5rem; padding-top:1rem; border-top:1px dashed var(--border-light); text-align:center;">
                        <button class="ap-btn-secondary" style="font-size:0.75rem; padding:0.3rem 0.75rem;" onclick="simulateQuickScan()">
                            <i class="fas fa-bolt"></i> Test Quick Check-in Simulation
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip" style="max-width:520px; margin:1.5rem auto 0 auto;">
                <div class="ap-sentinel-item"><i class="fas fa-link"></i><span><strong>Proof-of-Attendance:</strong> SHA-256 Ledger Backed</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield"></i><span><strong>15s Anti-Proxy:</strong> Dynamic TOTP Validation</span></div>
            </div>

        </div>
    </main>

    <script>
        let html5QrCode = null;
        let isScanning = false;

        function startScanner() {
            html5QrCode = new Html5Qrcode("reader");
            const config = { fps: 10, qrbox: { width: 250, height: 250 } };

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
                alert('Camera permission denied or camera not found: ' + err);
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
            statusEl.className = 'ap-alert info';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting attendance verification...';

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
                        event_id: parsed.event_id || '2f2f99ce-98e1-49f6-8949-760687189aa6',
                        token: parsed.token || decodedText,
                        member_id: <?= json_encode($userId) ?>,
                        full_name: <?= json_encode($userName) ?>,
                        email: <?= json_encode($userEmail) ?>
                    })
                });

                const result = await response.json();
                statusEl.style.display = 'none';

                if (result.success) {
                    const data = result.data || {};
                    document.getElementById('resEventTitle').textContent = data.event_title || 'IECEP Event';
                    document.getElementById('resStudentName').textContent = data.student_name || <?= json_encode($userName) ?>;
                    document.getElementById('resCampus').textContent = data.institution_acronym || 'LSPU SCC';
                    document.getElementById('resTime').textContent = new Date().toLocaleTimeString();
                    document.getElementById('resultSuccess').style.display = 'block';
                } else {
                    alert(result.message || 'Verification failed. QR code may have expired.');
                }
            } catch (err) {
                statusEl.className = 'ap-alert danger';
                statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error: ' + err.message;
            }
        }

        function onScanError(errorMessage) {
            // parse error, ignore continuously
        }

        function resetScanner() {
            document.getElementById('resultSuccess').style.display = 'none';
            startScanner();
        }

        // Quick simulation for local testing on laptop
        async function simulateQuickScan() {
            const currentWindow = Math.floor(Date.now() / 1000 / 15);
            // Simulate token
            const buffer = new TextEncoder("utf-8").encode('2f2f99ce-98e1-49f6-8949-760687189aa6:' + currentWindow + ':IECEP_LSC_ROTATING_QR_SECRET_2026');
            const digest = await crypto.subtle.digest("SHA-256", buffer);
            const token = Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');

            onScanSuccess(JSON.stringify({
                event_id: '2f2f99ce-98e1-49f6-8949-760687189aa6',
                token: token
            }));
        }
    </script>
</body>
</html>
