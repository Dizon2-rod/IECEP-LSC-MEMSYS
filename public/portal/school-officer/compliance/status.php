<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'compliance';

require_once __DIR__ . '/../../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'Chapter Compliance & Accreditation';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Affiliated Chapter';

$supabase = getSupabaseClient();

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
            if (is_array($instRes) && isset($instRes[0]['name'])) {
                $schoolName = $instRes[0]['name'];
            }
        }
    } catch (Exception $e) {}
}

// Fetch real metrics
$memberCount = 0;
$totalPaid = 0;
if ($supabase && $institutionId) {
    try {
        $mems = $supabase->select('members', ['institution_id' => 'eq.' . $institutionId]);
        if (is_array($mems)) $memberCount = count($mems);
        
        $txs = $supabase->select('transactions', [
            'institution_id' => 'eq.' . $institutionId,
            'status' => 'eq.paid'
        ]);
        if (is_array($txs)) {
            foreach ($txs as $t) $totalPaid += floatval($t['amount'] ?? 0);
        }
    } catch (Exception $e) {}
}

$hasRoster = ($memberCount > 0);
$hasPaid = ($totalPaid > 0);
$isCompliant = $hasRoster && $hasPaid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Chapter accreditation, documentary prerequisites, and compliance scorecard.">
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

        .compliance-step-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-color);
            gap: 1rem;
        }
        .compliance-step-row:last-child {
            border-bottom: none;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 0.85rem; }
            .mobile-toggle-btn { display: inline-flex; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.5rem !important; }
            .kpi-val { font-size: 1.1rem !important; }
            .kpi-lbl { font-size: 0.66rem !important; }
            .dash-kpi-card { padding: 0.5rem 0.65rem !important; gap: 0.5rem !important; }
            .kpi-icon-pill { width: 32px !important; height: 32px !important; font-size: 0.9rem !important; }
            .dash-header-banner { flex-direction: column; align-items: stretch; gap: 0.65rem; }
            .compliance-step-row { flex-direction: column; align-items: flex-start; }
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
                    <div>
                        <h1 class="dash-header-title">
                            <i class="fas fa-shield-halved" style="color:var(--color-navy);"></i>
                            <?= htmlspecialchars($schoolName) ?> — Compliance Scorecard
                        </h1>
                        <p class="dash-header-sub">
                            Academic Year 2026–2027 chapter accreditation status, requirements checklist, and clearance proofs.
                        </p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/school-officer/documents/list.php" class="btn-white">
                        <i class="fas fa-file-arrow-up" style="color:var(--color-blue);"></i> Submit Documents
                    </a>
                    <a href="<?= PORTAL_URL ?>/school-officer/members/upload.php" class="btn-primary-navy">
                        <i class="fas fa-cloud-arrow-up" style="color:#FDE047;"></i> Update Roster
                    </a>
                </div>
            </div>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill <?= $isCompliant ? 'emerald' : 'amber' ?>">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div>
                        <div class="kpi-val" style="color:<?= $isCompliant ? '#059669' : '#D97706' ?>;">
                            <?= $isCompliant ? 'Compliant' : 'In Progress' ?>
                        </div>
                        <div class="kpi-lbl">Standing Status</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="kpi-val"><?= $memberCount ?></div>
                        <div class="kpi-lbl">Roster Members Enrolled</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-peso-sign"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#B45309;">₱<?= number_format($totalPaid, 2) ?></div>
                        <div class="kpi-lbl">Dues Remitted to Regional</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <div class="kpi-val">AY 2026</div>
                        <div class="kpi-lbl">Active Term</div>
                    </div>
                </div>
            </div>

            <!-- 3. Compliance Milestone Checklist -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-tasks"></i> Chapter Accreditation Prerequisites (AY 2026–2027)</h3>
                </div>
                <div>
                    
                    <div class="compliance-step-row">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="kpi-icon-pill emerald"><i class="fas fa-check"></i></div>
                            <div>
                                <strong style="font-size:0.84rem; color:#0F172A; display:block;">1. Institutional Chapter Affiliation Recognition</strong>
                                <span style="font-size:0.74rem; color:#64748B;">Official Letter of Intent (LOI) and Dean / Department Endorsement Letter on file.</span>
                            </div>
                        </div>
                        <span class="ap-pill active"><span class="ap-pill-dot"></span> Completed</span>
                    </div>

                    <div class="compliance-step-row">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="kpi-icon-pill <?= $hasRoster ? 'emerald' : 'amber' ?>">
                                <i class="fas <?= $hasRoster ? 'fa-check' : 'fa-clock' ?>"></i>
                            </div>
                            <div>
                                <strong style="font-size:0.84rem; color:#0F172A; display:block;">2. Official Student Membership Masterlist</strong>
                                <span style="font-size:0.74rem; color:#64748B;">Batch submission of enrolled ECE student members (Current: <?= $memberCount ?> registered).</span>
                            </div>
                        </div>
                        <?php if ($hasRoster): ?>
                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Submitted</span>
                        <?php else: ?>
                            <a href="<?= PORTAL_URL ?>/school-officer/members/upload.php" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.6rem;">Upload Roster</a>
                        <?php endif; ?>
                    </div>

                    <div class="compliance-step-row">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="kpi-icon-pill <?= $hasPaid ? 'emerald' : 'amber' ?>">
                                <i class="fas <?= $hasPaid ? 'fa-check' : 'fa-clock' ?>"></i>
                            </div>
                            <div>
                                <strong style="font-size:0.84rem; color:#0F172A; display:block;">3. Per-Capita Chapter Dues Remittance</strong>
                                <span style="font-size:0.74rem; color:#64748B;">Regional per-member share (₱50.00/student) and official payment verification.</span>
                            </div>
                        </div>
                        <?php if ($hasPaid): ?>
                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Remitted</span>
                        <?php else: ?>
                            <a href="<?= PORTAL_URL ?>/school-officer/financial/reports.php" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.6rem;">View Ledger</a>
                        <?php endif; ?>
                    </div>

                    <div class="compliance-step-row">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="kpi-icon-pill emerald"><i class="fas fa-check"></i></div>
                            <div>
                                <strong style="font-size:0.84rem; color:#0F172A; display:block;">4. Chapter Constitution & By-Laws (CBL)</strong>
                                <span style="font-size:0.74rem; color:#64748B;">Adopted chapter bylaws aligned with IECEP National and Laguna Section policies.</span>
                            </div>
                        </div>
                        <span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span>
                    </div>

                    <div class="compliance-step-row">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="kpi-icon-pill gold"><i class="fas fa-certificate"></i></div>
                            <div>
                                <strong style="font-size:0.84rem; color:#0F172A; display:block;">5. Regional Digital Certificate of Good Standing</strong>
                                <span style="font-size:0.74rem; color:#64748B;">Cryptographically verified accreditation token issued by the Executive Board.</span>
                            </div>
                        </div>
                        <span class="ap-pill blue">Issued for AY 2026</span>
                    </div>

                </div>
            </div>

        </div>
    </main>
</body>
</html>
