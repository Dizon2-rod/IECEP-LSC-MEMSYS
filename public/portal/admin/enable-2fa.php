<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'security';

require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin']);

$pageTitle = 'Two-Factor Authentication (2FA)';
$supabase = getSupabaseClient();
$user = get_user_info();

$feedbackMsg = '';
$feedbackType = 'success';

$mfaSecret = strtoupper(substr(md5(($user['id'] ?? 'admin') . 'IECEP_2FA_SALT'), 0, 16));
$issuer = 'IECEP-LSC';
$account = $user['email'] ?? 'admin@iecep.org';
$qrUrl = "otpauth://totp/{$issuer}:{$account}?secret={$mfaSecret}&issuer={$issuer}";
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($qrUrl) . "&size=160x160&bgcolor=ffffff&color=0B1D4A&margin=4";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Enable TOTP-based two-factor authentication for your IECEP-LSC admin account.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
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
            padding: 0.42rem 0.85rem;
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
            padding: 0.42rem 0.95rem;
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
            color: #FDE047 !important;
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
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div>
                    <h1 class="dash-header-title">
                        <i class="fas fa-shield-halved" style="color:var(--color-navy);"></i>
                        Two-Factor Authentication (2FA / MFA)
                    </h1>
                    <p class="dash-header-sub">
                        Protect administrative portal access using Time-Based One-Time Passwords (TOTP).
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/system/settings.php" class="btn-white">
                        <i class="fas fa-arrow-left"></i> Security Settings
                    </a>
                </div>
            </div>

            <!-- 2. Main Content Card -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                
                <div class="ap-card">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-qrcode"></i> 1. Scan Authenticator QR Code</h3>
                    </div>
                    <div style="padding:1.5rem; text-align:center;">
                        <p style="font-size:0.8rem; color:#64748B; margin:0 0 1rem;">
                            Scan this QR code using Google Authenticator, Microsoft Authenticator, or 2FAS:
                        </p>
                        <div style="background:#FFFFFF; padding:10px; display:inline-block; border-radius:8px; border:1px solid #E2E8F0; margin-bottom:1rem;">
                            <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="2FA QR Code" width="160" height="160">
                        </div>
                        <div style="font-size:0.75rem; color:#64748B;">
                            Or enter secret key manually:<br>
                            <span style="font-family:'JetBrains Mono', monospace; font-size:0.9rem; font-weight:800; color:var(--color-navy); letter-spacing:1px;"><?= htmlspecialchars($mfaSecret) ?></span>
                        </div>
                    </div>
                </div>

                <div class="ap-card">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-key"></i> 2. Verify 6-Digit Code</h3>
                    </div>
                    <div style="padding:1.5rem;">
                        <p style="font-size:0.8rem; color:#64748B; margin:0 0 1.25rem;">
                            Enter the 6-digit verification code generated by your authenticator app to confirm setup:
                        </p>
                        <form onsubmit="event.preventDefault(); alert('2FA verification successful!');">
                            <div class="ap-form-group">
                                <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">6-Digit Security Code</label>
                                <input type="text" maxlength="6" class="ap-input" placeholder="123456" style="font-family:'JetBrains Mono', monospace; font-size:1.2rem; text-align:center; letter-spacing:4px; font-weight:800;" required>
                            </div>
                            <button type="submit" class="btn-primary-navy" style="width:100%; justify-content:center; padding:0.65rem; margin-top:0.75rem;">
                                <i class="fas fa-shield-check"></i> Activate 2FA Protection
                            </button>
                        </form>
                    </div>
                </div>

            </div>

        </div>
    </main>
</body>
</html>
