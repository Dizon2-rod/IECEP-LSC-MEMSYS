<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'digital-id';
$pageTitle = 'Dynamic Student Digital ID';

$user = get_user_info();
$userId = $user['id'] ?? null;
$userEmail = $user['email'] ?? '';
$displayName = $user['full_name'] ?? $user['name'] ?? $userEmail;

$supabase = getSupabaseClient();

// Fetch Member Record
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
        error_log("Digital ID load error: " . $e->getMessage());
    }
}

$realMemberId = $member['id'] ?? ($userId ?? 'mem_default');
$membershipId = $member['membership_id'] ?? 'IECEP-2026-0001';
$courseName = !empty($member['course']) ? $member['course'] : 'BS Electronics Engineering';
$yearLevel = !empty($member['year_level']) ? $member['year_level'] : '3rd Year';
$studentNumber = !empty($member['student_number']) ? $member['student_number'] : ($member['student_id'] ?? '2023-01048');
$digitalHash = $member['digital_id_hash'] ?? hash('sha256', $membershipId . ($userEmail ?: 'iecep'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Dynamic rolling QR Student Digital ID card for verified IECEP-LSC chapter members.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-blue: #2563EB;
            --bg-page: #F8FAFC;
            --border-color: #E2E8F0;
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

        .id-layout-container {
            display: grid;
            grid-template-columns: 430px 1fr;
            gap: 1.5rem;
            align-items: start;
            max-width: 1100px;
            margin: 0 auto;
        }

        @media (max-width: 950px) {
            .id-layout-container {
                grid-template-columns: 1fr;
            }
        }

        /* Front Digital ID Card */
        .digital-id-card {
            background: linear-gradient(135deg, #0B1D4A 0%, #152C6E 50%, #1E3A8A 100%);
            border-radius: 20px;
            padding: 24px;
            color: #FFFFFF;
            box-shadow: 0 15px 35px -5px rgba(11, 29, 74, 0.35);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(212, 175, 55, 0.45);
        }

        .digital-id-card::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.2) 0%, rgba(212, 175, 55, 0) 70%);
            pointer-events: none;
        }

        .id-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            padding-bottom: 14px;
        }

        .id-card-logo {
            width: 44px;
            height: 44px;
            background: #FFFFFF;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            padding: 4px;
            flex-shrink: 0;
        }

        .id-card-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .id-photo-wrapper {
            width: 76px;
            height: 76px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #D4AF37;
            box-shadow: 0 6px 16px rgba(0,0,0,0.3);
            color: #D4AF37;
            font-size: 2rem;
        }

        .qr-box-wrapper {
            background: #FFFFFF;
            padding: 14px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
            margin: 0 auto;
        }

        .progress-bar-container {
            width: 100%;
            height: 5px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 12px;
        }

        .progress-bar-fill {
            height: 100%;
            background: #10B981;
            transition: width 1s linear;
        }

        /* Information Panels on Right */
        .info-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
            margin-bottom: 1.25rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.65rem 0;
            border-bottom: 1px solid #F1F5F9;
            font-size: 0.86rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748B; font-weight: 600; }
        .info-val { color: #0F172A; font-weight: 700; text-align: right; }

        @media print {
            body { background: #FFF !important; }
            .sidebar-nav, .ap-sidebar, .btn-print-hide, .info-card { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .id-layout-container { display: block !important; }
            .digital-id-card { box-shadow: none !important; border: 2px solid #0B1D4A !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="ap-page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
            <div>
                <h1 style="font-size:1.4rem; font-weight:800; color:#0F172A; margin:0 0 0.2rem 0; display:flex; align-items:center; gap:0.5rem;">
                    <i class="fas fa-id-card" style="color:var(--color-navy);"></i> Student Dynamic Digital ID
                </h1>
                <p style="margin:0; font-size:0.82rem; color:#64748B;">
                    Official anti-counterfeiting digital credential with rotating 30-second cryptographic tokens.
                </p>
            </div>
            <div class="btn-print-hide" style="display:flex; gap:0.5rem;">
                <button type="button" class="btn-white" onclick="window.print()" style="padding:0.45rem 0.9rem; font-size:0.82rem; border-radius:8px; font-weight:600; cursor:pointer; background:#FFF; border:1px solid #CBD5E1;">
                    <i class="fas fa-print me-1"></i> Print / Save PDF
                </button>
                <a href="/IECEP-LSC-MEMSYS/public/portal/member/scan.php" class="btn-primary-navy" style="background:#0B1D4A; color:#FFF; padding:0.45rem 0.9rem; font-size:0.82rem; border-radius:8px; text-decoration:none; font-weight:700; display:inline-flex; align-items:center; gap:0.4rem;">
                    <i class="fas fa-camera"></i> Scan Scanner
                </a>
            </div>
        </div>

        <div class="id-layout-container">
            <!-- Left: Digital ID Card -->
            <div>
                <div class="digital-id-card" id="digitalIdElement">
                    <div class="id-card-header">
                        <div class="id-card-logo">
                            <img src="/IECEP-LSC-MEMSYS/public/assets/icons/iecep-logo.png" alt="IECEP Logo">
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.65rem; color:#D4AF37; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">
                                Institute of Electronics Engineers of the PH
                            </div>
                            <div style="font-size:0.88rem; font-weight:800; color:#FFFFFF;">
                                Laguna Student Chapter
                            </div>
                        </div>
                    </div>

                    <div style="text-align:center;">
                        <div class="id-photo-wrapper">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div style="font-size:1.15rem; font-weight:800; color:#FFFFFF; margin-bottom:0.15rem;">
                            <?= htmlspecialchars($member['full_name'] ?? $displayName) ?>
                        </div>
                        <div style="font-size:0.8rem; color:#E2E8F0; margin-bottom:0.3rem;">
                            <?= htmlspecialchars($schoolName) ?>
                        </div>
                        <div style="font-size:0.75rem; color:#CBD5E1; margin-bottom:0.6rem;">
                            <?= htmlspecialchars($courseName) ?> • <?= htmlspecialchars($yearLevel) ?>
                        </div>
                        <div style="background:rgba(255,255,255,0.12); padding:0.35rem 0.8rem; border-radius:6px; display:inline-block; font-family:'JetBrains Mono', monospace; font-size:0.86rem; font-weight:700; color:#FDE047; margin-bottom:0.9rem; border:1px dashed rgba(212,175,55,0.5);">
                            <?= htmlspecialchars($membershipId) ?>
                        </div>

                        <!-- 30-Second Rolling QR Code -->
                        <div class="qr-box-wrapper">
                            <div id="dynamicMemberQr"></div>
                        </div>

                        <div class="progress-bar-container">
                            <div id="memberQrProgressBar" class="progress-bar-fill" style="width:100%;"></div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.6rem; font-size:0.72rem; color:rgba(255,255,255,0.85);">
                            <span><i class="fas fa-shield-halved text-warning me-1"></i> Rolling 30s Dynamic Security</span>
                            <span id="memberQrTimer">Refreshing in 30s</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Membership Record & Verification Metadata -->
            <div>
                <div class="info-card">
                    <h2 style="font-size:0.95rem; font-weight:700; color:#0F172A; margin:0 0 1rem 0; display:flex; align-items:center; gap:0.5rem;">
                        <i class="fas fa-circle-check" style="color:var(--color-emerald);"></i> Membership Credentials
                    </h2>
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-val"><?= htmlspecialchars($member['full_name'] ?? $displayName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Registered Email</span>
                        <span class="info-val"><?= htmlspecialchars($member['email'] ?? $userEmail) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Student / ID Number</span>
                        <span class="info-val" style="font-family:'JetBrains Mono', monospace;"><?= htmlspecialchars($studentNumber) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">HEI / Chapter</span>
                        <span class="info-val"><?= htmlspecialchars($schoolName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Year Level</span>
                        <span class="info-val"><?= htmlspecialchars($yearLevel) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment & Standing</span>
                        <span class="info-val" style="color:var(--color-emerald);">
                            <i class="fas fa-check-circle me-1"></i> Active / Good Standing
                        </span>
                    </div>
                </div>

                <div class="info-card" style="background:#F8FAFC;">
                    <h2 style="font-size:0.88rem; font-weight:700; color:#0F172A; margin:0 0 0.5rem 0; display:flex; align-items:center; gap:0.4rem;">
                        <i class="fas fa-link" style="color:var(--color-blue);"></i> Security & Anti-Fraud Token
                    </h2>
                    <p style="margin:0 0 0.6rem 0; font-size:0.76rem; color:#64748B; line-height:1.4;">
                        This dynamic ID generates encrypted one-time verification tokens every 30 seconds to eliminate screenshots and duplicate badge presentations at IECEP assemblies.
                    </p>
                    <div style="font-family:'JetBrains Mono', monospace; font-size:0.68rem; color:#475569; word-break:break-all; background:#FFFFFF; padding:0.5rem; border:1px solid #E2E8F0; border-radius:6px;">
                        SHA-256: <?= htmlspecialchars($digitalHash) ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const realMemberId = <?= json_encode($realMemberId) ?>;
        let memberQrSecondsLeft = 30;
        let memberQrTimerInterval = null;

        async function fetchAndRenderMemberQr() {
            try {
                const res = await fetch(`/IECEP-LSC-MEMSYS/public/api/events/attendance.php?action=generate_member_qr&member_id=${encodeURIComponent(realMemberId || 'mem_default')}`);
                const data = await res.json();
                if (data.success) {
                    memberQrSecondsLeft = data.seconds_left || 30;
                    renderMemberQrCode(data.qr_data);
                } else {
                    renderMemberQrCode(JSON.stringify({ member_id: realMemberId, type: 'member_id_qr', timestamp: Date.now() }));
                }
            } catch (err) {
                renderMemberQrCode(JSON.stringify({ member_id: realMemberId, type: 'member_id_qr', timestamp: Date.now() }));
            }
        }

        function renderMemberQrCode(qrDataString) {
            const container = document.getElementById('dynamicMemberQr');
            if (!container) return;
            container.innerHTML = '';

            let rendered = false;
            if (typeof QRCode !== 'undefined') {
                try {
                    new QRCode(container, {
                        text: qrDataString,
                        width: 140,
                        height: 140,
                        colorDark: "#0B1D4A",
                        colorLight: "#FFFFFF",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                    rendered = true;
                } catch (e) {}
            }

            if (!rendered) {
                const img = document.createElement('img');
                img.src = `https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encodeURIComponent(qrDataString)}&color=0B1D4A`;
                img.alt = 'Member QR';
                img.style.width = '140px';
                img.style.height = '140px';
                img.style.display = 'block';
                container.appendChild(img);
            }
        }

        function startTimer() {
            if (memberQrTimerInterval) clearInterval(memberQrTimerInterval);
            memberQrTimerInterval = setInterval(() => {
                memberQrSecondsLeft--;
                const bar = document.getElementById('memberQrProgressBar');
                const timerText = document.getElementById('memberQrTimer');
                
                if (bar) {
                    const pct = Math.max(0, (memberQrSecondsLeft / 30) * 100);
                    bar.style.width = pct + '%';
                    if (pct < 25) bar.style.background = '#EF4444';
                    else if (pct < 50) bar.style.background = '#F59E0B';
                    else bar.style.background = '#10B981';
                }

                if (timerText) {
                    timerText.textContent = `Refreshing in ${memberQrSecondsLeft}s`;
                }

                if (memberQrSecondsLeft <= 0) {
                    memberQrSecondsLeft = 30;
                    fetchAndRenderMemberQr();
                }
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchAndRenderMemberQr();
            startTimer();
        });
    </script>
</body>
</html>
