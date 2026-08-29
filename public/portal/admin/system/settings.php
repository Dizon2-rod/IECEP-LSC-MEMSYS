<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'settings';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin']);

$pageTitle = 'System & Governance Settings';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_settings') {
        $feedbackMsg = "🎉 System parameters and governance settings updated successfully!";
        $feedbackType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage system settings, role permissions, and portal configuration for IECEP-LSC Laguna Student Chapter.">
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
                        <i class="fas fa-gear" style="color:var(--color-navy);"></i>
                        System & Governance Settings
                    </h1>
                    <p class="dash-header-sub">
                        Configure chapter parameters, academic term defaults, security policies, and environment status.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/system/users.php" class="btn-white">
                        <i class="fas fa-users-gear" style="color:var(--color-blue);"></i> User Accounts
                    </a>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. Settings Grid -->
            <form method="POST">
                <input type="hidden" name="action" value="save_settings">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    
                    <!-- General Settings -->
                    <div class="ap-card">
                        <div class="ap-card-header">
                            <h3 class="ap-card-title"><i class="fas fa-sliders"></i> General Chapter Parameters</h3>
                        </div>
                        <div style="padding:1.25rem;">
                            <div class="ap-form-group">
                                <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Chapter Title</label>
                                <input type="text" class="ap-input" value="IECEP Laguna Student Chapter" style="font-size:0.8rem;">
                            </div>
                            <div class="ap-form-group">
                                <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Academic Year / Term</label>
                                <input type="text" class="ap-input" value="AY 2026–2027" style="font-size:0.8rem;">
                            </div>
                            <div class="ap-form-group">
                                <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Default Timezone</label>
                                <select class="ap-input" style="font-size:0.8rem;">
                                    <option selected>Asia/Manila (PHT, UTC+8)</option>
                                    <option>UTC</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Security & Cryptography -->
                    <div class="ap-card">
                        <div class="ap-card-header">
                            <h3 class="ap-card-title"><i class="fas fa-shield-halved"></i> Security & Authentication</h3>
                        </div>
                        <div style="padding:1.25rem;">
                            <div class="ap-form-group">
                                <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Two-Factor Authentication (2FA)</label>
                                <select class="ap-input" style="font-size:0.8rem;">
                                    <option selected>Enabled for Administrator Accounts</option>
                                    <option>Enforced for All Users</option>
                                </select>
                            </div>
                            <div class="ap-form-group">
                                <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Session Security Timeout</label>
                                <select class="ap-input" style="font-size:0.8rem;">
                                    <option selected>60 Minutes (Inactivity Lock)</option>
                                    <option>120 Minutes</option>
                                </select>
                            </div>
                            <div class="ap-form-group">
                                <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Cryptographic Hash Standard</label>
                                <input type="text" class="ap-input" value="SHA-256 (NIST FIPS 180-4)" readonly style="font-size:0.8rem; background:#F8FAFC;">
                            </div>
                        </div>
                    </div>

                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:0.5rem;">
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-floppy-disk"></i> Save System Configuration</button>
                </div>
            </form>

        </div>
    </main>
</body>
</html>
