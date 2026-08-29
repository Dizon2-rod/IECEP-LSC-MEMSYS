<?php
require_once __DIR__ . '/bootstrap.php';

$staticSchools = [
    [
        'name' => 'Colegio de San Juan de Letran - Calamba',
        'org_name' => 'ELECTRONICS ENGINEERING LETRAN STUDENTS SOCIETY',
        'short_name' => 'Letran Calamba',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/LETRAN.png',
        'location' => 'Colegio de San Juan de Letran, Calamba, Philippines, 4027',
        'email' => 'ecelss@letran-calamba.edu.ph',
        'facebook_url' => 'https://www.facebook.com/ECELSSrocks',
        'established' => 'AY 2024-2025',
    ],
    [
        'name' => 'Laguna State Polytechnic University - San Pablo City Campus',
        'org_name' => 'ASSOCIATION OF FUTURE ELECTRONICS ENGINEERS',
        'short_name' => 'LSPU - SPCC',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/LSPU-SPCC.png',
        'location' => 'San Pablo City, Philippines, 4000',
        'email' => 'afece_spc@lspu.edu.ph',
        'facebook_url' => 'https://www.facebook.com/LSPUAFECE',
        'established' => 'AY 2024-2025',
    ],
    [
        'name' => 'Mapúa Malayan Colleges Laguna',
        'org_name' => 'INSTITUTE OF ELECTRONICS ENGINEERS OF THE PHILIPPINES - MCL STUDENT CHAPTER',
        'short_name' => 'MMCL',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/MMCL.webp',
        'location' => 'Pulo, Cabuyao, Philippines, 4025',
        'email' => 'iecepmmcl@gmail.com',
        'facebook_url' => 'https://www.facebook.com/iecepmmcl',
        'established' => 'AY 2024-2025',
    ],
    [
        'name' => 'University of Cabuyao (Pamantasan ng Cabuyao)',
        'org_name' => 'ORGANIZATION OF ELECTRONICS ENGINEERING STUDENTS',
        'short_name' => 'PnC / UC-PNC',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/UC-PNC.png',
        'location' => 'Cabuyao, Philippines, 4025',
        'email' => 'jieceppnc@gmail.com',
        'facebook_url' => 'https://www.facebook.com/jiecep.pnc.official',
        'established' => 'AY 2024-2025',
    ],
    [
        'name' => 'Polytechnic University of the Philippines - Santa Rosa Campus',
        'org_name' => 'ASSOCIATION OF ELECTRONICS AND COMMUNICATIONS ENGINEERING STUDENTS',
        'short_name' => 'PUP - SRC',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/PUP-STA ROSA.png',
        'location' => 'Room 3-4, PUP-Sta. Rosa, Barangay Tagapo, Santa Rosa, Philippines, 4026',
        'email' => 'officialaeces.pupsrc@gmail.com',
        'facebook_url' => 'https://www.facebook.com/OfficialAECES',
        'established' => 'AY 2024-2025',
    ],
    [
        'name' => 'University of Perpetual Help System Laguna – Biñan Campus',
        'org_name' => 'PERPETUAL INSTITUTE OF ELECTRONICS ENGINEERING STUDENTS',
        'short_name' => 'UPHSL Biñan',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/UPHSL-BINAN.png',
        'location' => 'National Hi-way, Brgy. Sto. Niño, Biñan, Philippines, 4024',
        'email' => 'uphsl.pieces@gmail.com',
        'facebook_url' => 'https://www.facebook.com/uphslpieces',
        'established' => 'AY 2024-2025',
    ],
    [
        'name' => 'University of Perpetual Help System DALTA - Calamba Campus',
        'org_name' => 'ELECTRONICS ENGINEERING STUDENTS SOCIETY - UPHSD CALAMBA',
        'short_name' => 'UPHSD Calamba',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/UPHSD.png',
        'location' => 'Calamba, Philippines, 4027',
        'email' => 'pieces.uphsd@gmail.com',
        'facebook_url' => 'https://www.facebook.com/eceperpslp.org',
        'established' => 'AY 2024-2025',
    ],
    [
        'name' => 'Laguna State Polytechnic University - Santa Cruz Campus',
        'org_name' => 'ELECTRONICS ENGINEERING STUDENTS SOCIETY',
        'short_name' => 'LSPU - SCC',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/LSPU-SCC.png',
        'location' => 'Santa Cruz National High-way, Brgy. Bubukal, Santa Cruz, Laguna',
        'email' => 'official.lspusccecess@gmail.com',
        'facebook_url' => 'https://www.facebook.com/LSPUSCCECESS',
        'established' => 'AY 2024-2025',
    ]
];

function safeSelect($supabase, $table, $filters = []) {
    try {
        $result = $supabase->select($table, $filters);
        if (!is_array($result)) return [];
        if (isset($result['message']) && isset($result['details'])) return [];
        if (array_keys($result) !== range(0, count($result) - 1)) return [];
        return $result;
    } catch (\Exception $e) {
        error_log("safeSelect error for table '$table': " . $e->getMessage());
        return [];
    }
}

$supabase = supabase();
$affiliatedSchools = [];

if ($supabase) {
    $supabaseConfig = require __DIR__ . '/includes/supabase.php';
    if (!empty($supabaseConfig['service_role_key'])) {
        $supabase->setServiceRoleKey($supabaseConfig['service_role_key']);
    }

    $dbInstitutions = safeSelect($supabase, 'institutions');
    if (!empty($dbInstitutions)) {
        foreach ($dbInstitutions as $inst) {
            $instName = $inst['name'] ?? '';
            $matchingStatic = null;
            foreach ($staticSchools as $staticSchool) {
                if (
                    strcasecmp($staticSchool['name'], $instName) === 0 || 
                    strpos($instName, $staticSchool['short_name'] ?? '~~~') !== false || 
                    (!empty($inst['acronym']) && strcasecmp($staticSchool['short_name'], $inst['acronym']) === 0)
                ) {
                    $matchingStatic = $staticSchool;
                    break;
                }
            }
            $affiliatedSchools[] = [
                'name'          => $instName,
                'org_name'      => $matchingStatic['org_name'] ?? 'Official IECEP-LSC Student Chapter',
                'short_name'    => $inst['acronym'] ?? ($matchingStatic['short_name'] ?? ''),
                'logo'          => $inst['logo_url'] ?? ($matchingStatic['logo'] ?? '/IECEP-LSC-MEMSYS/public/assets/icons/default-school.png'),
                'location'      => $inst['address'] ?? ($matchingStatic['location'] ?? (($inst['city'] ?? 'Laguna') . ', Philippines')),
                'email'         => $inst['contact_email'] ?? $inst['email'] ?? ($matchingStatic['email'] ?? ''),
                'facebook_url'  => $inst['facebook_url'] ?? ($matchingStatic['facebook_url'] ?? ''),
                'status'        => $inst['status'] ?? 'active',
                'compliance'    => $inst['compliance_status'] ?? 'compliant',
                'source'        => 'institutions',
            ];
        }
    }

    $pendingAffiliations = safeSelect($supabase, 'pending_affiliations');
    foreach ($pendingAffiliations as $app) {
        if (($app['status'] ?? 'pending') === 'pending') {
            $affiliatedSchools[] = [
                'name'          => $app['institution_name'] ?? $app['name'] ?? '',
                'org_name'      => 'Pending Affiliation Applicant',
                'short_name'    => '',
                'logo'          => $app['logo'] ?? '/IECEP-LSC-MEMSYS/public/assets/icons/default-school.png',
                'location'      => $app['institution_address'] ?? $app['location'] ?? 'Laguna, Philippines',
                'email'         => $app['email'] ?? '',
                'facebook_url'  => $app['facebook_url'] ?? '',
                'status'        => 'pending',
                'source'        => 'pending_affiliations',
                'receipt_number'=> $app['receipt_number'] ?? null,
                'submitted_at'  => $app['submitted_at'] ?? null,
            ];
        }
    }
}

// Fallback to static schools if database yields no active rows
if (empty($affiliatedSchools)) {
    foreach ($staticSchools as $s) {
        $affiliatedSchools[] = [
            'name'          => $s['name'],
            'org_name'      => $s['org_name'] ?? 'Official IECEP-LSC Student Chapter',
            'short_name'    => $s['short_name'],
            'logo'          => $s['logo'],
            'location'      => $s['location'],
            'email'         => $s['email'] ?? '',
            'facebook_url'  => $s['facebook_url'],
            'status'        => 'active',
            'source'        => 'static',
        ];
    }
}

$schoolsToShow = $affiliatedSchools;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliated Schools &amp; Chapters — IECEP-LSC</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #142A6B;
            --accent: #D4AF37;
            --accent-hover: #C5A059;
            --navy-dark: #07122E;
            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-600: #475569;
            --slate-800: #1E293B;
            --radius-md: 12px;
            --radius-lg: 18px;
            --radius-full: 9999px;
            --shadow-card: 0 10px 30px -5px rgba(11, 29, 74, 0.08), 0 4px 10px -2px rgba(11, 29, 74, 0.04);
            --shadow-hover: 0 20px 40px -10px rgba(11, 29, 74, 0.18), 0 8px 16px -4px rgba(212, 175, 55, 0.15);
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F8FAFC;
            color: var(--slate-800);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Page Hero ────────────────────────────────────────── */
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, #07122E 0%, #0B1D4A 55%, #142A6B 100%);
            color: #FFFFFF;
            padding: 120px 1.5rem 60px;
            text-align: center;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 80% 20%, rgba(212, 175, 55, 0.15) 0%, transparent 60%),
                        radial-gradient(circle at 20% 80%, rgba(30, 58, 138, 0.3) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 820px;
            margin: 0 auto;
        }
        .hero-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: clamp(2.2rem, 4.5vw, 3.2rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            color: #FFFFFF;
        }
        .hero-title span {
            background: linear-gradient(135deg, #FFE89E 0%, #D4AF37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.65;
            max-width: 680px;
            margin: 0 auto;
        }

        /* ── Main Container ───────────────────────────────────── */
        .schools-container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
            flex: 1;
        }

        /* ── Search & Filter Bar ──────────────────────────────── */
        .filter-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2.5rem;
            background: #FFFFFF;
            padding: 0.85rem 1.25rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .search-box {
            position: relative;
            flex: 1;
            min-width: 260px;
        }
        .search-input {
            width: 100%;
            padding: 0.65rem 1.25rem;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-full);
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
            background: #F8FAFC;
        }
        .search-input:focus {
            border-color: var(--accent);
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }
        .filter-counter {
            font-size: 0.88rem;
            color: var(--slate-600);
            font-weight: 500;
        }
        .filter-counter strong {
            color: var(--primary);
        }

        /* ── Schools Grid ─────────────────────────────────────── */
        .schools-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1.5rem;
        }
        @media (min-width: 640px) {
            .schools-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .schools-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (min-width: 1280px) {
            .schools-grid { grid-template-columns: repeat(4, 1fr); }
        }

        /* ── School Card ──────────────────────────────────────── */
        .school-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2rem 1.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .school-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .school-card:hover {
            transform: translateY(-6px);
            border-color: rgba(212, 175, 55, 0.4);
            box-shadow: var(--shadow-hover);
        }
        .school-card:hover::before {
            opacity: 1;
        }

        /* Logo Disc */
        .school-logo-disc {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: #F8FAFC;
            border: 2px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.06);
            transition: all 0.3s ease;
            position: relative;
        }
        .school-card:hover .school-logo-disc {
            border-color: var(--accent);
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.25);
        }
        .school-logo-disc img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        /* School Info */
        .school-card-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.35;
            margin-bottom: 0.4rem;
            min-height: 2.7rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .society-title-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(11, 29, 74, 0.05);
            color: var(--primary);
            border: 1px solid rgba(11, 29, 74, 0.12);
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 0.65rem;
            line-height: 1.3;
        }

        .school-card-location {
            font-size: 0.8rem;
            color: var(--slate-600);
            margin-bottom: 0.5rem;
            line-height: 1.3;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 0.35rem;
        }

        .school-card-email {
            font-size: 0.75rem;
            color: var(--slate-600);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            word-break: break-all;
        }
        .school-card-email a {
            color: #2563EB;
            text-decoration: none;
            font-weight: 600;
        }
        .school-card-email a:hover {
            text-decoration: underline;
        }

        /* School Button */
        .school-action-btn {
            margin-top: auto;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            background: #F1F5F9;
            color: var(--primary);
            border: 1px solid var(--slate-200);
            padding: 0.65rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .school-action-btn:hover {
            background: #1877F2;
            color: #FFFFFF;
            border-color: #1877F2;
            box-shadow: 0 4px 12px rgba(24, 119, 242, 0.25);
        }

        /* ── Call To Action Box ───────────────────────────────── */
        .affiliation-cta-banner {
            margin-top: 4rem;
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
            box-shadow: 0 15px 35px rgba(11, 29, 74, 0.15);
            position: relative;
            overflow: hidden;
        }
        .affiliation-cta-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .cta-text-wrap h3 {
            font-size: 1.45rem;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 0.35rem;
        }
        .cta-text-wrap p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.92rem;
            margin: 0;
            max-width: 580px;
        }
        .cta-btn-affiliate {
            background: linear-gradient(135deg, #FFE89E 0%, #D4AF37 100%);
            color: #07122E;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.85rem 1.65rem;
            border-radius: var(--radius-full);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
            white-space: nowrap;
        }
        .cta-btn-affiliate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.45);
            filter: brightness(1.05);
        }

        /* ── Empty State ──────────────────────────────────────── */
        .empty-search-state {
            grid-column: 1 / -1;
            padding: 4rem 1.5rem;
            text-align: center;
            display: none;
        }
        .empty-search-state h3 {
            color: var(--primary);
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .empty-search-state p {
            color: var(--slate-600);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                Affiliated <span>Higher Education Institutions</span>
            </h1>
            <p class="hero-desc">
                Uniting Electronics Engineering student chapters across Laguna through academic excellence, regional synergy, and shared technological purpose.
            </p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Schools Section -->
    <main class="schools-container">
        <!-- Search & Counter -->
        <div class="filter-wrapper">
            <div class="search-box">
                <input type="text" id="schoolSearch" class="search-input" placeholder="Search by institution, student society, or city..." onkeyup="filterSchools()">
            </div>
            <div class="filter-counter">
                Showing <strong id="schoolCountDisplay"><?php echo count($schoolsToShow); ?></strong> institutions
            </div>
        </div>

        <!-- Grid of School Cards -->
        <div class="schools-grid" id="schoolsGrid">
            <?php foreach ($schoolsToShow as $school): 
                $logo = !empty($school['logo']) ? htmlspecialchars($school['logo']) : '/IECEP-LSC-MEMSYS/public/assets/icons/default-school.png';
                $facebook = !empty($school['facebook_url']) ? htmlspecialchars($school['facebook_url']) : '';
                $orgName = !empty($school['org_name']) ? htmlspecialchars($school['org_name']) : 'Official IECEP-LSC Student Chapter';
                $email = !empty($school['email']) ? htmlspecialchars($school['email']) : '';
            ?>
                <div class="school-card" data-name="<?php echo htmlspecialchars(strtolower($school['name'] . ' ' . $orgName . ' ' . ($school['location'] ?? ''))); ?>">
                    <div class="school-logo-disc">
                        <img src="<?php echo $logo; ?>" 
                             alt="<?php echo htmlspecialchars($school['name']); ?>" 
                             loading="lazy"
                             onerror="this.src='/IECEP-LSC-MEMSYS/public/assets/icons/iecep-logo.png';">
                    </div>

                    <h3 class="school-card-name" title="<?php echo htmlspecialchars($school['name']); ?>">
                        <?php echo htmlspecialchars($school['name']); ?>
                    </h3>

                    <div class="society-title-badge" title="<?php echo $orgName; ?>">
                        <i class="fas fa-certificate" style="color:#D4AF37;"></i> <?php echo $orgName; ?>
                    </div>

                    <div class="school-card-location">
                        <i class="fas fa-map-marker-alt" style="color:#EF4444; margin-top:2px; flex-shrink:0;"></i>
                        <span><?php echo htmlspecialchars($school['location'] ?? 'Laguna, Philippines'); ?></span>
                    </div>

                    <?php if ($email): ?>
                        <div class="school-card-email">
                            <i class="fas fa-envelope" style="color:#64748B;"></i>
                            <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a>
                        </div>
                    <?php endif; ?>

                    <?php if ($facebook): ?>
                        <a href="<?php echo $facebook; ?>" target="_blank" rel="noopener noreferrer" class="school-action-btn">
                            <i class="fab fa-facebook" style="color:#1877F2;"></i> Visit Student Chapter
                        </a>
                    <?php else: ?>
                        <span class="school-action-btn" style="opacity:0.6; cursor:default;">
                            Official Chapter
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Empty Search State -->
            <div id="noResultsState" class="empty-search-state">
                <h3>No Matching Institutions Found</h3>
                <p>Try searching with another keyword or city name.</p>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ CTA Banner -->
        <div class="affiliation-cta-banner">
            <div class="cta-text-wrap">
                <h3>Is your School Chapter not yet affiliated?</h3>
                <p>Accreditation grants your engineering student body digital member IDs, access to conventions, and official regional chapter status.</p>
            </div>
            <a href="/IECEP-LSC-MEMSYS/index.php#how-to-affiliate" class="cta-btn-affiliate">
                Apply for Affiliation
            </a>
        </div>
    </main>

    <!-- Client-side filter script -->
    <script>
        function filterSchools() {
            const query = document.getElementById('schoolSearch').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.school-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const data = card.getAttribute('data-name') || '';
                if (data.includes(query)) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            document.getElementById('schoolCountDisplay').textContent = visibleCount;
            const noResults = document.getElementById('noResultsState');
            if (noResults) {
                noResults.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
        }
    </script>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>