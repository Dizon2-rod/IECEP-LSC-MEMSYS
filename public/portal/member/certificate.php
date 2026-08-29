<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'certificate';
$pageTitle = 'My Certificates & Blockchain Badges';

$user = get_user_info();
$userId = $user['id'] ?? null;
$userEmail = $user['email'] ?? '';
$displayName = $user['full_name'] ?? $user['name'] ?? $userEmail;

$supabase = getSupabaseClient();

// Fetch Member Record
$member = [];
$schoolName = 'Laguna State Polytechnic University - Santa Cruz Campus';
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
            }
        }
    } catch (Exception $e) {}
}

$memberDbId = $member['id'] ?? $userId;

// Fetch Attended Events
$attendedEvents = [];
$allEventsMap = [];

try {
    if ($supabase) {
        $rawEv = $supabase->select('events', ['select' => '*']);
        if (is_array($rawEv)) {
            foreach ($rawEv as $ev) {
                $allEventsMap[$ev['id']] = $ev;
            }
        }

        if (!empty($memberDbId)) {
            $rawAtt = $supabase->select('event_attendees', [
                'member_id' => 'eq.' . $memberDbId
            ]);
            if (is_array($rawAtt)) {
                foreach ($rawAtt as $a) {
                    $evId = $a['event_id'] ?? '';
                    if (isset($allEventsMap[$evId])) {
                        $attendedEvents[] = [
                            'event' => $allEventsMap[$evId],
                            'attendance' => $a,
                            'cert_num' => 'IECEP-CERT-' . strtoupper(substr(md5($memberDbId . $evId), 0, 8)),
                            'hash' => hash('sha256', $memberDbId . $evId . ($a['check_in_time'] ?? 'now'))
                        ];
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Certificates fetch error: " . $e->getMessage());
}

$selectedEventId = $_GET['event_id'] ?? null;
$selectedCert = null;
if ($selectedEventId && !empty($attendedEvents)) {
    foreach ($attendedEvents as $c) {
        if ($c['event']['id'] === $selectedEventId) {
            $selectedCert = $c;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Verified Certificates of Participation and Blockchain Credentials for IECEP Laguna delegates.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cinzel:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-blue: #2563EB;
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

        .cert-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.25rem;
        }

        .cert-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.08);
            border-color: #CBD5E1;
        }

        .cert-preview-banner {
            background: linear-gradient(135deg, #0B1D4A 0%, #152C6E 100%);
            padding: 1.5rem 1.25rem;
            color: #FFFFFF;
            text-align: center;
            position: relative;
            border-bottom: 3px solid #D4AF37;
        }

        /* Certificate Modal / Print Canvas */
        .cert-paper {
            background: #FFFFFF;
            border: 10px solid #0B1D4A;
            padding: 2.5rem 2rem;
            border-radius: 4px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }
        .cert-paper::before {
            content: '';
            position: absolute;
            top: 8px; left: 8px; right: 8px; bottom: 8px;
            border: 2px solid #D4AF37;
            pointer-events: none;
        }

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

        @media print {
            body * { visibility: hidden; }
            .cert-paper, .cert-paper * { visibility: visible; }
            .cert-paper { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none !important; border: 8px solid #0B1D4A !important; -webkit-print-color-adjust: exact !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
            <div>
                <h1 style="font-size:1.4rem; font-weight:800; color:#0F172A; margin:0 0 0.2rem 0; display:flex; align-items:center; gap:0.5rem;">
                    <i class="fas fa-certificate" style="color:#D97706;"></i> My Certificates & Blockchain Credentials
                </h1>
                <p style="margin:0; font-size:0.82rem; color:#64748B;">
                    Official digital credentials for verified participation in IECEP Laguna technical workshops and assemblies.
                </p>
            </div>
            <div>
                <a href="/IECEP-LSC-MEMSYS/public/portal/member/events.php" class="btn-white">
                    <i class="fas fa-calendar-alt"></i> Browse Events
                </a>
            </div>
        </div>

        <?php if ($selectedCert): ?>
            <!-- Single Certificate View & Print Card -->
            <div class="no-print" style="margin-bottom:1rem;">
                <a href="/IECEP-LSC-MEMSYS/public/portal/member/certificate.php" class="btn-white" style="font-size:0.8rem;">
                    <i class="fas fa-arrow-left me-1"></i> Back to All Certificates
                </a>
                <button type="button" class="btn-primary-navy ms-2" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print Certificate
                </button>
            </div>

            <div class="cert-paper">
                <div style="font-size:0.75rem; font-weight:800; color:#D4AF37; text-transform:uppercase; letter-spacing:0.15em; margin-bottom:0.25rem;">
                    Institute of Electronics Engineers of the Philippines
                </div>
                <div style="font-size:0.9rem; font-weight:800; color:#0B1D4A; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1.5rem;">
                    Laguna Student Chapter
                </div>

                <div style="font-family:'Cinzel', serif; font-size:1.8rem; font-weight:800; color:#0B1D4A; margin-bottom:1rem;">
                    Certificate of Participation
                </div>

                <div style="font-size:0.85rem; color:#64748B; margin-bottom:0.5rem;">
                    This official certificate is proudly conferred to
                </div>

                <div style="font-size:1.6rem; font-weight:800; color:#0B1D4A; margin-bottom:0.25rem; border-bottom:2px solid #D4AF37; display:inline-block; padding:0 2rem 0.25rem;">
                    <?= htmlspecialchars($member['full_name'] ?? $displayName) ?>
                </div>

                <div style="font-size:0.85rem; color:#475569; margin-top:0.35rem; margin-bottom:1.25rem;">
                    <?= htmlspecialchars($schoolName) ?>
                </div>

                <div style="max-width:580px; margin:0 auto 1.5rem; font-size:0.88rem; color:#334155; line-height:1.5;">
                    for active participation and successful completion of the chapter event entitled 
                    <strong style="color:#0B1D4A;"><?= htmlspecialchars($selectedCert['event']['title']) ?></strong>, 
                    held on <?= date('F d, Y', strtotime($selectedCert['event']['start_date'] ?? 'now')) ?>.
                </div>

                <div style="display:flex; justify-content:space-around; align-items:center; margin-top:2rem; padding-top:1.5rem; border-top:1px solid #E2E8F0;">
                    <div>
                        <div style="border-bottom:1px solid #334155; width:160px; margin:0 auto 0.3rem;"></div>
                        <div style="font-size:0.75rem; font-weight:700; color:#0B1D4A;">Chapter President</div>
                        <div style="font-size:0.68rem; color:#64748B;">IECEP-LSC</div>
                    </div>
                    <div>
                        <div style="border-bottom:1px solid #334155; width:160px; margin:0 auto 0.3rem;"></div>
                        <div style="font-size:0.75rem; font-weight:700; color:#0B1D4A;">Event Chairperson</div>
                        <div style="font-size:0.68rem; color:#64748B;">IECEP-LSC Committee</div>
                    </div>
                </div>

                <div style="margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center; font-size:0.68rem; color:#64748B; border-top:1px dashed #CBD5E1; padding-top:0.75rem;">
                    <div>
                        <strong>Certificate No:</strong> <span style="font-family:'JetBrains Mono', monospace;"><?= htmlspecialchars($selectedCert['cert_num']) ?></span>
                    </div>
                    <div style="color:var(--color-emerald); font-weight:700;">
                        <i class="fas fa-shield-halved me-1"></i> Blockchain Cryptographically Verified
                    </div>
                </div>
            </div>

        <?php else: ?>

            <!-- All Certificates Grid -->
            <?php if (empty($attendedEvents)): ?>
                <div style="background:#FFFFFF; border:1px solid var(--border-color); border-radius:12px; text-align:center; padding:3rem 1.5rem;">
                    <i class="fas fa-award" style="font-size:2.5rem; color:#94A3B8; margin-bottom:0.75rem;"></i>
                    <h3 style="color:#0F172A; font-size:1.1rem; margin:0 0 0.4rem 0;">No Certificates Earned Yet</h3>
                    <p style="color:#64748B; font-size:0.84rem; margin:0 0 1rem 0;">Certificates are automatically unlocked when your attendance is verified at official IECEP-LSC events.</p>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/member/events.php" class="btn-primary-navy">
                        <i class="fas fa-calendar-check me-1"></i> Browse Upcoming Events
                    </a>
                </div>
            <?php else: ?>
                <div class="cert-grid">
                    <?php foreach ($attendedEvents as $c): ?>
                        <div class="cert-card">
                            <div>
                                <div class="cert-preview-banner">
                                    <i class="fas fa-award" style="font-size:2rem; color:#D4AF37; margin-bottom:0.4rem;"></i>
                                    <div style="font-size:0.65rem; color:#FDE047; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">
                                        Official Credential
                                    </div>
                                    <h3 style="font-size:0.95rem; font-weight:800; color:#FFFFFF; margin:0.2rem 0 0;">
                                        <?= htmlspecialchars($c['event']['title']) ?>
                                    </h3>
                                </div>
                                <div style="padding:1rem;">
                                    <div style="font-size:0.78rem; color:#64748B; margin-bottom:0.5rem;">
                                        <i class="fas fa-calendar-day me-1"></i> <?= date('F d, Y', strtotime($c['event']['start_date'] ?? 'now')) ?>
                                    </div>
                                    <div style="font-family:'JetBrains Mono', monospace; font-size:0.72rem; color:#334155; background:#F8FAFC; padding:0.4rem 0.6rem; border-radius:6px; border:1px solid #E2E8F0; margin-bottom:0.75rem;">
                                        ID: <?= htmlspecialchars($c['cert_num']) ?>
                                    </div>
                                </div>
                            </div>
                            <div style="padding:0 1rem 1rem;">
                                <a href="/IECEP-LSC-MEMSYS/public/portal/member/certificate.php?event_id=<?= urlencode($c['event']['id']) ?>" class="btn-primary-navy" style="width:100%; justify-content:center;">
                                    <i class="fas fa-eye"></i> View &amp; Print Certificate
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</body>
</html>
