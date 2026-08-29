<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'profile';
$pageTitle = 'Student Member Profile';

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
    } catch (Exception $e) {
        error_log("Profile load error: " . $e->getMessage());
    }
}

$membershipId = $member['membership_id'] ?? '20260001';
$courseName = !empty($member['course']) ? $member['course'] : 'BS Electronics Engineering';
$yearLevel = !empty($member['year_level']) ? $member['year_level'] : '3rd Year';
$studentNumber = !empty($member['student_number']) ? $member['student_number'] : ($member['student_id'] ?? '2023-01048');
$phone = $member['phone'] ?? '+63 912 345 6789';
$address = $member['address'] ?? 'Santa Cruz, Laguna';
$birthday = !empty($member['birthday']) ? date('F d, Y', strtotime($member['birthday'])) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage personal profile, student records, and chapter credentials.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            padding: 1.25rem;
            min-height: 100vh;
            box-sizing: border-box;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 1.25rem;
        }

        @media (max-width: 900px) {
            .profile-grid { grid-template-columns: 1fr; }
        }

        .prof-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #F1F5F9;
            font-size: 0.86rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748B; font-weight: 600; }
        .info-val { color: #0F172A; font-weight: 700; text-align: right; }

        .btn-primary-navy {
            background: var(--color-navy);
            color: #FFFFFF;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            color: #FFFFFF;
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
                    <i class="fas fa-user-circle" style="color:var(--color-navy);"></i> My Member Profile
                </h1>
                <p style="margin:0; font-size:0.82rem; color:#64748B;">
                    Personal directory records and regional membership verification data.
                </p>
            </div>
            <div>
                <a href="/IECEP-LSC-MEMSYS/change-password.php" class="btn-primary-navy">
                    <i class="fas fa-key"></i> Change Password
                </a>
            </div>
        </div>

        <div class="profile-grid">
            <!-- Left Card: Avatar & Primary Identity -->
            <div class="prof-card" style="text-align:center;">
                <div style="width:90px; height:90px; border-radius:50%; background:linear-gradient(135deg, #0B1D4A 0%, #152C6E 100%); color:#D4AF37; display:inline-flex; align-items:center; justify-content:center; font-size:2.5rem; border:4px solid #D4AF37; box-shadow:0 8px 20px rgba(11,29,74,0.15); margin-bottom:1rem;">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h2 style="font-size:1.2rem; font-weight:800; color:#0F172A; margin:0 0 0.2rem 0;">
                    <?= htmlspecialchars($member['full_name'] ?? $displayName) ?>
                </h2>
                <div style="font-size:0.82rem; color:#64748B; margin-bottom:0.6rem;">
                    <?= htmlspecialchars($schoolName) ?>
                </div>
                <div style="font-family:'JetBrains Mono', monospace; font-size:0.85rem; font-weight:700; color:#0B1D4A; background:#FEF9C3; padding:0.35rem 0.75rem; border-radius:6px; display:inline-block; border:1px solid #FDE047; margin-bottom:1.25rem;">
                    <?= htmlspecialchars($membershipId) ?>
                </div>

                <div style="border-top:1px solid #E2E8F0; padding-top:1.25rem; text-align:left;">
                    <div style="font-size:0.75rem; font-weight:700; color:#64748B; text-transform:uppercase; margin-bottom:0.5rem;">Quick Details</div>
                    <div style="font-size:0.82rem; color:#334155; margin-bottom:0.4rem;">
                        <i class="fas fa-graduation-cap me-2" style="color:#D4AF37; width:16px;"></i> <?= htmlspecialchars($courseName) ?>
                    </div>
                    <div style="font-size:0.82rem; color:#334155; margin-bottom:0.4rem;">
                        <i class="fas fa-calendar-check me-2" style="color:#059669; width:16px;"></i> <?= htmlspecialchars($yearLevel) ?>
                    </div>
                    <div style="font-size:0.82rem; color:#334155;">
                        <i class="fas fa-shield-check me-2" style="color:#2563EB; width:16px;"></i> Verified Student Chapter Member
                    </div>
                </div>
            </div>

            <!-- Right Card: Detailed Records -->
            <div>
                <div class="prof-card" style="margin-bottom:1.25rem;">
                    <h3 style="font-size:0.95rem; font-weight:700; color:#0F172A; margin:0 0 1rem 0; display:flex; align-items:center; gap:0.5rem;">
                        <i class="fas fa-id-card-clip" style="color:var(--color-blue);"></i> Institutional Membership Information
                    </h3>
                    <div class="info-row">
                        <span class="info-label">Full Legal Name</span>
                        <span class="info-val"><?= htmlspecialchars($member['full_name'] ?? $displayName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Student ID / Serial No.</span>
                        <span class="info-val" style="font-family:'JetBrains Mono', monospace;"><?= htmlspecialchars($studentNumber) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Academic Program</span>
                        <span class="info-val"><?= htmlspecialchars($courseName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Year Level</span>
                        <span class="info-val"><?= htmlspecialchars($yearLevel) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Institutional Chapter</span>
                        <span class="info-val"><?= htmlspecialchars($schoolName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Membership Standing</span>
                        <span class="info-val" style="color:var(--color-emerald);">
                            <i class="fas fa-circle-check me-1"></i> Active / Good Standing
                        </span>
                    </div>
                </div>

                <div class="prof-card">
                    <h3 style="font-size:0.95rem; font-weight:700; color:#0F172A; margin:0 0 1rem 0; display:flex; align-items:center; gap:0.5rem;">
                        <i class="fas fa-address-book" style="color:var(--color-gold);"></i> Contact &amp; Verification Details
                    </h3>
                    <div class="info-row">
                        <span class="info-label">Email Address</span>
                        <span class="info-val"><?= htmlspecialchars($member['email'] ?? $userEmail) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Mobile Phone</span>
                        <span class="info-val"><?= htmlspecialchars($phone) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-val"><?= htmlspecialchars($address) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date Registered</span>
                        <span class="info-val"><?= date('F d, Y', strtotime($member['created_at'] ?? 'now')) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
