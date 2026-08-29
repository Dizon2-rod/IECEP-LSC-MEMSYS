<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'profile';

require_once __DIR__ . '/../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'School Officer & Chapter Profile';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$userName = $user['full_name'] ?? $user['name'] ?? $user['email'] ?? 'School Officer';
$userEmail = $user['email'] ?? '';

$supabase = getSupabaseClient();
$school = [];

// Resolve School
if ($supabase) {
    try {
        if (!$institutionId && $userId) {
            $userProfile = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId, 'limit' => 1]);
            if (is_array($userProfile) && isset($userProfile[0]['institution_id'])) {
                $institutionId = $userProfile[0]['institution_id'];
            }
        }
        if (!$institutionId) {
            $instList = $supabase->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
            if (is_array($instList) && isset($instList[0]['id'])) {
                $institutionId = $instList[0]['id'];
            }
        }
        if ($institutionId) {
            $_SESSION['institution_id'] = $institutionId;
            $instRes = $supabase->select('institutions', ['id' => 'eq.' . $institutionId, 'limit' => 1]);
            if (is_array($instRes) && isset($instRes[0])) {
                $school = $instRes[0];
            }
        }
    } catch (Exception $e) {}
}

$schoolName = $school['name'] ?? 'Affiliated Chapter';
$schoolAcronym = $school['acronym'] ?? 'IECEP-SC';
$schoolAddress = $school['address'] ?? ($school['city'] ?? 'Laguna, Philippines');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="School officer profile, institutional credentials, and affiliation details.">
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

        .main-content {
            margin-left: 260px;
            padding: 1.25rem;
            min-height: 100vh;
            box-sizing: border-box;
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

        .mobile-toggle-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #F1F5F9;
            border: 1px solid var(--border-color);
            color: var(--color-navy);
            font-size: 1rem;
            cursor: pointer;
            flex-shrink: 0;
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

        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            min-width: 0;
        }
        .kpi-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
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

        .profile-field-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .profile-field-row:last-child {
            border-bottom: none;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 0.85rem; }
            .mobile-toggle-btn { display: inline-flex; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: 1fr; }
            .dash-header-banner { flex-direction: column; align-items: flex-start; }
            .profile-field-row { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                    <button type="button" id="sidebarToggle" class="mobile-toggle-btn" aria-label="Toggle Navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="dash-header-title">
                            <i class="fas fa-user-gear" style="color:var(--color-navy);"></i>
                            School Officer & Chapter Profile
                        </h1>
                        <p class="dash-header-sub">
                            Manage your officer credentials, chapter affiliation records, and security details.
                        </p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/school-officer/dashboard.php" class="btn-white">
                        <i class="fas fa-gauge-high" style="color:var(--color-navy);"></i> Dashboard
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-user-tie"></i></div>
                    <div>
                        <div class="kpi-val">Officer</div>
                        <div class="kpi-lbl">Account Privileges</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val">Active</div>
                        <div class="kpi-lbl">Standing Status</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-university"></i></div>
                    <div>
                        <div class="kpi-val"><?= htmlspecialchars($schoolAcronym) ?></div>
                        <div class="kpi-lbl">Institutional Chapter</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="kpi-val">RBAC</div>
                        <div class="kpi-lbl">Security Tier</div>
                    </div>
                </div>
            </div>

            <!-- 3. Profile Information Cards -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                
                <!-- Officer Info -->
                <div class="ap-card">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-id-badge"></i> Officer Account Details</h3>
                    </div>
                    <div>
                        <div class="profile-field-row">
                            <span style="font-size:0.78rem; font-weight:700; color:#64748B;">Full Name</span>
                            <strong style="font-size:0.84rem; color:#0F172A;"><?= htmlspecialchars($userName) ?></strong>
                        </div>
                        <div class="profile-field-row">
                            <span style="font-size:0.78rem; font-weight:700; color:#64748B;">Email Address</span>
                            <span style="font-size:0.8rem; color:#0F172A;"><?= htmlspecialchars($userEmail) ?></span>
                        </div>
                        <div class="profile-field-row">
                            <span style="font-size:0.78rem; font-weight:700; color:#64748B;">Designated Role</span>
                            <span class="ap-pill blue">School Chapter Officer</span>
                        </div>
                        <div class="profile-field-row">
                            <span style="font-size:0.78rem; font-weight:700; color:#64748B;">Access Level</span>
                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Authorized</span>
                        </div>
                    </div>
                </div>

                <!-- Institution Info -->
                <div class="ap-card">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-school"></i> Institutional Chapter Profile</h3>
                    </div>
                    <div>
                        <div class="profile-field-row">
                            <span style="font-size:0.78rem; font-weight:700; color:#64748B;">Institution Name</span>
                            <strong style="font-size:0.84rem; color:#0F172A;"><?= htmlspecialchars($schoolName) ?></strong>
                        </div>
                        <div class="profile-field-row">
                            <span style="font-size:0.78rem; font-weight:700; color:#64748B;">Chapter Acronym</span>
                            <span style="font-family:'JetBrains Mono', monospace; font-size:0.8rem; font-weight:700; color:var(--color-navy);"><?= htmlspecialchars($schoolAcronym) ?></span>
                        </div>
                        <div class="profile-field-row">
                            <span style="font-size:0.78rem; font-weight:700; color:#64748B;">Location / Address</span>
                            <span style="font-size:0.8rem; color:#0F172A;"><?= htmlspecialchars($schoolAddress) ?></span>
                        </div>
                        <div class="profile-field-row">
                            <span style="font-size:0.78rem; font-weight:700; color:#64748B;">Chapter Status</span>
                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Chartered Chapter</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </main>
</body>
</html>
