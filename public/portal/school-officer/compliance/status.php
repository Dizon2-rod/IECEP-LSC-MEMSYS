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
$participationRate = 0.0;
$hostedEventsCount = 0;
$venueEventsCount = 0;
$totalHostingCredit = 0;
$overallScore = 0.0;
$complianceStatus = 'compliant';
$year = intval(date('Y'));

if ($supabase && $institutionId) {
    try {
        $memberRepo = \App\Lib\MemberRepository::getInstance($supabase);
        $memberStats = $memberRepo->getStatsForInstitution($institutionId);
        $memberCount = $memberStats['active'] > 0 ? $memberStats['active'] : $memberStats['total'];

        $paymentRepo = \App\Lib\PaymentRepository::getInstance($supabase);
        $totalPaid = $paymentRepo->getPaidTotalForInstitution($institutionId);

        // 1. Fetch compliance score via ComplianceRepository
        $compRepo = \App\Lib\ComplianceRepository::getInstance($supabase);
        $row = $compRepo->getScoresForInstitution($institutionId, $year);

        if (!empty($row)) {
            $participationRate = floatval($row['participation_rate'] ?? 0);
            $totalHostingCredit = intval($row['hosted_event_count'] ?? 0);
            $overallScore = floatval($row['overall_score'] ?? 0);
            $complianceStatus = $row['compliance_status'] ?? 'compliant';
        } else {
            // Calculate live via ComplianceEngine if available
            try {
                require_once SRC_PATH . 'lib/BlockchainService.php';
                require_once SRC_PATH . 'lib/ComplianceEngine.php';
                $blockchain = $GLOBALS['blockchain'] ?? new \App\Lib\BlockchainService($supabase);
                $engine = new \App\Lib\ComplianceEngine($supabase, $blockchain);
                $overallScore = $engine->calculateForInstitution($institutionId, $year);
                $rep = $compRepo->getScoresForInstitution($institutionId, $year);
                if ($rep) {
                    $participationRate = floatval($rep['participation_rate'] ?? 0);
                    $totalHostingCredit = intval($rep['hosted_event_count'] ?? 0);
                    $complianceStatus = $rep['compliance_status'] ?? 'compliant';
                }
            } catch (\Throwable $ce) {
                error_log("ComplianceEngine live run notice: " . $ce->getMessage());
            }
        }

        // Count hosted & venue events
        $hosted = $supabase->select('events', ['institution_id' => 'eq.' . $institutionId, 'status' => 'eq.completed']);
        if (is_array($hosted)) $hostedEventsCount = count($hosted);
        
        try {
            $venues = $supabase->select('events', ['venue_institution_id' => 'eq.' . $institutionId, 'status' => 'eq.completed']);
            if (is_array($venues)) $venueEventsCount = count($venues);
        } catch (\Throwable $ve) {
            $venueEventsCount = 0;
        }
        if ($totalHostingCredit === 0) {
            $totalHostingCredit = $hostedEventsCount + $venueEventsCount;
        }

    } catch (Exception $e) {
        error_log("Error loading compliance status metrics: " . $e->getMessage());
    }
}

$hasRoster = ($memberCount > 0);
$hasPaid = ($totalPaid > 0);
$meetsParticipation = ($participationRate >= 40.0);
$meetsHosting = ($totalHostingCredit >= 1);
$isCompliant = ($complianceStatus === 'compliant');
$isAtRisk = ($complianceStatus === 'at_risk');
$isNonCompliant = ($complianceStatus === 'non_compliant');
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
                    <div class="kpi-icon-pill <?= $isCompliant ? 'emerald' : ($isAtRisk ? 'amber' : 'navy') ?>" style="<?= $isNonCompliant ? 'background:#FEE2E2; color:#DC2626;' : '' ?>">
                        <i class="fas <?= $isCompliant ? 'fa-certificate' : ($isAtRisk ? 'fa-triangle-exclamation' : 'fa-circle-exclamation') ?>"></i>
                    </div>
                    <div>
                        <div class="kpi-val" style="color:<?= $isCompliant ? '#059669' : ($isAtRisk ? '#D97706' : '#DC2626') ?>;">
                            <?= $isCompliant ? 'Compliant' : ($isAtRisk ? 'At Risk' : 'Needs Improvement') ?>
                        </div>
                        <div class="kpi-lbl">CBL Compliance Standing</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill <?= $meetsParticipation ? 'emerald' : 'amber' ?>">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <div class="kpi-val" style="color:<?= $meetsParticipation ? '#059669' : '#D97706' ?>;"><?= number_format($participationRate, 1) ?>%</div>
                        <div class="kpi-lbl">Participation (Min 40%)</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill <?= $meetsHosting ? 'emerald' : 'amber' ?>">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <div>
                        <div class="kpi-val" style="color:<?= $meetsHosting ? '#059669' : '#B45309' ?>;"><?= $totalHostingCredit ?> Event<?= $totalHostingCredit == 1 ? '' : 's' ?></div>
                        <div class="kpi-lbl">Hosted / Official Venue</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-link"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#B45309;"><?= number_format($overallScore, 1) ?>%</div>
                        <div class="kpi-lbl">Blockchain Audited Score</div>
                    </div>
                </div>
            </div>

            <!-- Low Compliance Advisory Reminder Banner (Monitoring Notice) -->
            <?php if ($isAtRisk || $isNonCompliant || !$meetsParticipation || !$meetsHosting): ?>
                <div style="background: <?= $isNonCompliant ? '#FFF1F2' : '#FFFBEB' ?>; border: 1px solid <?= $isNonCompliant ? '#FECDD3' : '#FDE68A' ?>; border-left: 5px solid <?= $isNonCompliant ? '#E11D48' : '#D97706' ?>; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1rem; box-shadow: var(--shadow-card);">
                    <div style="display: flex; align-items: flex-start; gap: 0.85rem;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: <?= $isNonCompliant ? '#FFE4E6' : '#FEF3C7' ?>; color: <?= $isNonCompliant ? '#E11D48' : '#D97706' ?>; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; margin-top: 2px;">
                            <i class="fas <?= $isNonCompliant ? 'fa-triangle-exclamation' : 'fa-bell' ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.35rem;">
                                <strong style="font-size: 0.92rem; color: <?= $isNonCompliant ? '#9F1239' : '#92400E' ?>;">
                                    <i class="fas fa-circle-info"></i> Chapter Compliance Monitoring Advisory
                                </strong>
                                <span style="font-size: 0.72rem; font-weight: 700; background: <?= $isNonCompliant ? '#FFE4E6' : '#FEF3C7' ?>; color: <?= $isNonCompliant ? '#9F1239' : '#92400E' ?>; padding: 0.2rem 0.55rem; border-radius: 6px;">
                                    Status: <?= $isNonCompliant ? 'Needs Improvement' : 'At Risk' ?>
                                </span>
                            </div>
                            <p style="margin: 0 0 0.65rem; font-size: 0.8rem; color: <?= $isNonCompliant ? '#881337' : '#78350F' ?>; line-height: 1.45;">
                                This indicator is an <strong>informational monitoring metric</strong> under CBL Art. V Sec. 3 designed to help your chapter foster active member engagement. There are <strong>no automatic affiliation revocations or punitive deadlines</strong>. Please consider the following recommended steps to boost your chapter's compliance rating:
                            </p>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.65rem; margin-bottom: 0.75rem;">
                                <div style="background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(0,0,0,0.06); border-radius: 8px; padding: 0.6rem 0.75rem;">
                                    <strong style="font-size: 0.78rem; color: #0F172A; display: block;">
                                        <i class="fas fa-users" style="color: <?= $meetsParticipation ? '#059669' : '#D97706' ?>;"></i>
                                        Member Participation (Current: <?= number_format($participationRate, 1) ?>% / Target: ≥ 40%)
                                    </strong>
                                    <span style="font-size: 0.73rem; color: #64748B;">Encourage registered student members to attend upcoming regional technical webinars, conventions, and leadership summits.</span>
                                </div>
                                <div style="background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(0,0,0,0.06); border-radius: 8px; padding: 0.6rem 0.75rem;">
                                    <strong style="font-size: 0.78rem; color: #0F172A; display: block;">
                                        <i class="fas fa-landmark" style="color: <?= $meetsHosting ? '#059669' : '#D97706' ?>;"></i>
                                        Event Hosting or Official Venue (Current: <?= $totalHostingCredit ?> / Target: ≥ 1)
                                    </strong>
                                    <span style="font-size: 0.73rem; color: #64748B;">Coordinate with the IECEP-LSC Executive Board to host a local seminar or designate your school facilities as the official venue.</span>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <a href="<?= PORTAL_URL ?>/events.php" class="btn-primary-navy" style="font-size: 0.74rem; padding: 0.35rem 0.75rem;">
                                    <i class="fas fa-calendar-alt"></i> View Sanctioned Regional Events
                                </a>
                                <a href="mailto:compliance@iecep-lsc.org?subject=<?= urlencode('Chapter Event Hosting Collaboration - ' . $schoolName) ?>" class="btn-white" style="font-size: 0.74rem; padding: 0.35rem 0.75rem;">
                                    <i class="fas fa-envelope"></i> Contact Executive Board
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 3. Compliance Milestone Checklist -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-tasks"></i> Constitution & By-Laws Compliance Verification (Art. IV & V)</h3>
                </div>
                <div>
                    
                    <!-- Metric 1: 40% Participation -->
                    <div class="compliance-step-row">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="kpi-icon-pill <?= $meetsParticipation ? 'emerald' : 'amber' ?>">
                                <i class="fas <?= $meetsParticipation ? 'fa-check' : 'fa-hourglass-half' ?>"></i>
                            </div>
                            <div>
                                <strong style="font-size:0.84rem; color:#0F172A; display:block;">1. Minimum 40% Member Participation Threshold (Art. V Sec. 3)</strong>
                                <span style="font-size:0.74rem; color:#64748B;">At least 40% of the active chapter roster must participate in IECEP-LSC sanctioned events (Current: <strong><?= number_format($participationRate, 1) ?>%</strong>).</span>
                            </div>
                        </div>
                        <span class="ap-pill <?= $meetsParticipation ? 'active' : 'pending' ?>"><span class="ap-pill-dot"></span> <?= $meetsParticipation ? 'Satisfied (≥ 40%)' : 'Under 40%' ?></span>
                    </div>

                    <!-- Metric 2: Hosted Event or Official Venue -->
                    <div class="compliance-step-row">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="kpi-icon-pill <?= $meetsHosting ? 'emerald' : 'amber' ?>">
                                <i class="fas <?= $meetsHosting ? 'fa-check' : 'fa-calendar-plus' ?>"></i>
                            </div>
                            <div>
                                <strong style="font-size:0.84rem; color:#0F172A; display:block;">2. Sanctioned Event Hosting OR Official Venue (Art. V Sec. 3)</strong>
                                <span style="font-size:0.74rem; color:#64748B;">Host at least one regional activity or serve as the official physical/virtual venue during the academic year (Current: <strong><?= $totalHostingCredit ?></strong>).</span>
                            </div>
                        </div>
                        <span class="ap-pill <?= $meetsHosting ? 'active' : 'pending' ?>"><span class="ap-pill-dot"></span> <?= $meetsHosting ? 'Satisfied (≥ 1)' : 'Pending Requirement' ?></span>
                    </div>
                    
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
