<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'digital-id';

$user = get_user_info();
$member_id = $_SESSION['member_id'] ?? $user['member_id'] ?? ($user['id'] ?? null);

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch member details
$member = [];
try {
    if ($member_id) {
        $memberData = $supabase->select('members', [
            'id' => 'eq.' . $member_id
        ]);
        if (!empty($memberData) && isset($memberData[0])) {
            $member = $memberData[0];
        }
    }
    if (empty($member) && !empty($user['email'])) {
        $memberData = $supabase->select('members', [
            'email' => 'eq.' . $user['email']
        ]);
        if (!empty($memberData) && isset($memberData[0])) {
            $member = $memberData[0];
            $member_id = $member['id'];
        }
    }
} catch (Exception $e) {
    $member = [];
}

$realMemberId = $member['id'] ?? $member_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../../includes/head-meta.php'; ?>
    <title>My Dynamic Digital ID - Member Portal</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .digital-id-card {
            background: linear-gradient(135deg, #0B1D4A 0%, #152C6E 100%);
            border-radius: 20px;
            padding: 28px;
            color: white;
            max-width: 420px;
            margin: 0 auto;
            box-shadow: 0 20px 45px rgba(11,29,74,0.3);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(212,175,55,0.4);
        }
        .digital-id-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        .digital-id-logo {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .digital-id-logo img {
            width: 36px;
            height: 36px;
        }
        .digital-id-title {
            flex: 1;
        }
        .digital-id-title h3 {
            margin: 0;
            font-size: 13px;
            color: #D4AF37;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }
        .digital-id-title h2 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            color: #FFFFFF;
        }
        .member-photo {
            width: 84px;
            height: 84px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #D4AF37;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
        }
        .member-name {
            text-align: center;
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #FFFFFF;
        }
        .member-details {
            text-align: center;
            font-size: 13px;
            margin-bottom: 18px;
            color: rgba(255,255,255,0.85);
        }
        .qr-box-wrapper {
            background: #FFFFFF;
            padding: 16px;
            border-radius: 14px;
            display: inline-block;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            margin: 0 auto;
        }
        .progress-bar-container {
            width: 100%;
            height: 5px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 14px;
        }
        .progress-bar-fill {
            height: 100%;
            background: #10B981;
            transition: width 1s linear;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h1 class="h2 mb-1" style="font-weight:800; color:#0B1D4A;"><i class="fas fa-id-card" style="color:#D4AF37;"></i> My Dynamic Digital ID</h1>
                        <p class="text-muted mb-0" style="font-size:0.88rem;">Official verified student member identification with 30-second rolling QR code.</p>
                    </div>
                    <button class="btn btn-primary" onclick="window.print()" style="background:#0B1D4A; border-color:#0B1D4A; font-weight:700;">
                        <i class="fas fa-print me-1"></i> Print ID Card
                    </button>
                </div>

                <div class="digital-id-card" id="digitalIdCard">
                    <div class="digital-id-header">
                        <div class="digital-id-logo">
                            <img src="<?= PUBLIC_URL ?>/assets/icons/iecep-logo.png" alt="IECEP-LSC">
                        </div>
                        <div class="digital-id-title">
                            <h3>IECEP Laguna Student Chapter</h3>
                            <h2>Official Member Identification</h2>
                        </div>
                    </div>

                    <div style="text-align:center;">
                        <div class="member-photo">
                            <i class="fas fa-user-graduate" style="font-size:36px; color:#D4AF37;"></i>
                        </div>
                        <div class="member-name">
                            <?= htmlspecialchars($member['full_name'] ?? ($user['name'] ?? 'Student Member')) ?>
                        </div>
                        <div class="member-details">
                            <p style="margin:2px 0;"><strong><?= htmlspecialchars($member['school_affiliate'] ?? 'Affiliated Chapter') ?></strong></p>
                            <p style="margin:2px 0; font-family:monospace; font-weight:700; color:#D4AF37; font-size:14px;"><?= htmlspecialchars($member['membership_id'] ?? 'IECEP-2026-MEM') ?></p>
                            <span class="badge bg-success" style="font-size:11px; padding:4px 10px; margin-top:4px;">
                                <i class="fas fa-check-circle me-1"></i> Verified & Active
                            </span>
                        </div>

                        <!-- 30-Second Rolling Dynamic QR Code -->
                        <div class="qr-box-wrapper">
                            <div id="dynamicMemberQr"></div>
                        </div>

                        <div class="progress-bar-container">
                            <div id="memberQrProgressBar" class="progress-bar-fill" style="width:100%;"></div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; font-size:11px; color:rgba(255,255,255,0.75);">
                            <span><i class="fas fa-shield-alt text-warning me-1"></i> Rolling 30s Dynamic Security</span>
                            <span id="memberQrTimer">Refreshing in 30s</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

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
                console.error("Error generating dynamic member QR:", err);
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
                } catch(e) {
                    console.warn("QRCodeJS instance failed, falling back to image renderer:", e);
                }
            }

            if (!rendered) {
                const encoded = encodeURIComponent(qrDataString);
                const img = document.createElement('img');
                img.src = `https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encoded}&color=0B1D4A`;
                img.alt = "Member Digital ID QR";
                img.style.width = "140px";
                img.style.height = "140px";
                img.style.display = "block";
                img.style.borderRadius = "8px";
                container.appendChild(img);
            }
        }

        function updateMemberQrTimer() {
            memberQrSecondsLeft--;
            if (memberQrSecondsLeft <= 0) {
                fetchAndRenderMemberQr();
            }
            const pct = Math.max(0, (memberQrSecondsLeft / 30) * 100);
            const bar = document.getElementById('memberQrProgressBar');
            if (bar) bar.style.width = pct + '%';
            const txt = document.getElementById('memberQrTimer');
            if (txt) txt.textContent = `Refreshing in ${memberQrSecondsLeft}s`;
        }

        // Initialize dynamic rolling QR code
        fetchAndRenderMemberQr();
        memberQrTimerInterval = setInterval(updateMemberQrTimer, 1000);
    </script>
</body>
</html>
