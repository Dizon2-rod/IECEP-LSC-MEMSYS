<?php
require_once __DIR__ . '/bootstrap.php';

// Static list of affiliated schools
$staticSchools = [
    [
        'name' => 'Colegio de San Juan de Letrán',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/LETRAN.png',
        'location' => 'Calamba, Laguna',
        'facebook_url' => 'https://www.facebook.com/LetranCalamba',
    ],
    [
        'name' => 'Laguna State Polytechnic University - Santa Cruz Campus',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/LSPU-SCC.png',
        'location' => 'Santa Cruz, Laguna',
        'facebook_url' => 'https://www.facebook.com/LSPUSantaCruz',
    ],
    [
        'name' => 'Laguna State Polytechnic University - San Pablo City Campus',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/LSPU-SPCC.png',
        'location' => 'San Pablo City, Laguna',
        'facebook_url' => 'https://www.facebook.com/LSPUSanPablo',
    ],
    [
        'name' => 'Mapua Malayan Colleges Laguna',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/MMCL.webp',
        'location' => 'Cabuyao, Laguna',
        'facebook_url' => 'https://www.facebook.com/MMCLaguna',
    ],
    [
        'name' => 'Polytechnic University of the Philippines - Santa Rosa Campus',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/PUP-STA ROSA.png',
        'location' => 'Santa Rosa, Laguna',
        'facebook_url' => 'https://www.facebook.com/PUPSantaRosa',
    ],
    [
        'name' => 'Pamantasan ng Cabuyao',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/UC-PNC.png',
        'location' => 'Cabuyao, Laguna',
        'facebook_url' => 'https://www.facebook.com/PamantasanNgCabuyao',
    ],
    [
        'name' => 'University of Perpetual Help System Dalta - Calamba Campus',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/UPHSD.png',
        'location' => 'Calamba, Laguna',
        'facebook_url' => 'https://www.facebook.com/UPHSDCalamba',
    ],
    [
        'name' => 'University of Perpetual Help System Jonelta - Biñán Campus',
        'logo' => '/IECEP-LSC-MEMSYS/public/assets/icons/UPHSL-BINAN.png',
        'location' => 'Biñán, Laguna',
        'facebook_url' => 'https://www.facebook.com/UPHSLBinan',
    ]
];

// Get Supabase client
$supabase = supabase();
$affiliatedSchools = [];

if ($supabase) {
    try {
        $result = $supabase->select('affiliated_schools', null, 'name', 'ASC');
        if (!empty($result) && is_array($result)) {
            $affiliatedSchools = array_filter($result, function($school) {
                return ($school['status'] ?? null) === 'active';
            });
            foreach ($affiliatedSchools as &$school) {
                $schoolName = $school['name'];
                foreach ($staticSchools as $staticSchool) {
                    if ($staticSchool['name'] === $schoolName) {
                        if (empty($school['logo'])) $school['logo'] = $staticSchool['logo'];
                        if (empty($school['location'])) $school['location'] = $staticSchool['location'];
                        if (empty($school['facebook_url'])) $school['facebook_url'] = $staticSchool['facebook_url'];
                        break;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Affiliated schools fetch error: " . $e->getMessage());
        $affiliatedSchools = [];
    }
}

$schoolsToShow = !empty($affiliatedSchools) ? $affiliatedSchools : $staticSchools;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Affiliated Schools - IECEP-LSC</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        :root {
            --primary-dark: #0B1D4A;
            --primary-main: #1A3A8A;
            --primary-light: #2D4A9A;
            --accent-gold: #C5A059;
            --accent-soft: #F8F3E6;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.5;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-main) 50%, var(--primary-light) 100%);
            color: var(--white);
            padding: 100px var(--space-4) 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('public/uploads/features/1776563415_hero.png') center/cover no-repeat;
            opacity: 0.08;
            pointer-events: none;
        }
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
            background: linear-gradient(to right, #fff, #e0e7ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: none;
        }
        .page-header p {
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Container */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 4rem 1.5rem;
        }

        /* Accordion Group */
        .accordion-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Accordion Item (Card) */
        .accordion-item {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        .accordion-item:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-gold);
        }

        /* Accordion Header */
        .accordion-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            cursor: pointer;
            background: var(--white);
            transition: background 0.2s ease;
        }
        .accordion-header:hover {
            background: var(--gray-50);
        }
        .accordion-header[aria-expanded="true"] {
            background: linear-gradient(to right, var(--gray-50), var(--white));
            border-bottom: 1px solid var(--gray-200);
        }
        .accordion-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            color: var(--primary-dark);
        }
        .accordion-title i {
            color: var(--accent-gold);
            font-size: 1.25rem;
        }
        .accordion-icon {
            color: var(--accent-gold);
            transition: transform 0.3s ease;
        }
        .accordion-header[aria-expanded="true"] .accordion-icon {
            transform: rotate(180deg);
        }

        /* Accordion Content */
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
            background: var(--white);
        }
        .accordion-content[aria-expanded="true"] {
            max-height: 400px; /* enough for content */
            transition: max-height 0.5s ease-in-out;
        }
        .accordion-body {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        /* School Info Layout */
        .school-info {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: center;
        }
        .school-logo-wrapper {
            flex-shrink: 0;
            width: 90px;
            height: 90px;
            background: var(--gray-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            box-shadow: var(--shadow-sm);
        }
        .school-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }
        .school-details {
            flex: 1;
            min-width: 200px;
        }
        .school-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }
        .school-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
        }
        .school-location i {
            color: var(--accent-gold);
            width: 1rem;
        }
        .btn-facebook {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #1877F2, #0D5BB5);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }
        .btn-facebook:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            filter: brightness(1.05);
        }
        .btn-facebook.disabled {
            background: var(--gray-200);
            color: var(--gray-600);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }
        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 { font-size: 1.8rem; }
            .page-header p { font-size: 0.95rem; }
            .container { padding: 2rem 1rem; }
            .accordion-header { padding: 1rem; }
            .accordion-title { font-size: 0.9rem; }
            .school-info { flex-direction: column; text-align: center; }
            .school-location { justify-content: center; }
            .school-logo-wrapper { width: 70px; height: 70px; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-header">
        <h1>Affiliated Schools</h1>
        <p>The IECEP-LSC brings together affiliated higher education institutions, united in connection, collaboration, and shared purpose.</p>
    </section>

    <div class="container">
        <?php if (!empty($schoolsToShow)): ?>
            <div class="accordion-group">
                <?php foreach ($schoolsToShow as $index => $school): 
                    $logo = !empty($school['logo']) ? htmlspecialchars($school['logo']) : '/IECEP-LSC-MEMSYS/public/assets/icons/default-school.png';
                    $facebook = !empty($school['facebook_url']) ? htmlspecialchars($school['facebook_url']) : '';
                ?>
                    <div class="accordion-item">
                        <div class="accordion-header" 
                             role="button" 
                             tabindex="0" 
                             aria-expanded="false"
                             onclick="toggleAccordion(this)">
                            <div class="accordion-title">
                                <i class="fas fa-university"></i>
                                <span><?php echo htmlspecialchars($school['name']); ?></span>
                            </div>
                            <i class="fas fa-chevron-down accordion-icon"></i>
                        </div>
                        <div class="accordion-content" aria-expanded="false">
                            <div class="accordion-body">
                                <div class="school-info">
                                    <div class="school-logo-wrapper">
                                        <img src="<?php echo $logo; ?>" 
                                             alt="<?php echo htmlspecialchars($school['name']); ?>" 
                                             class="school-logo"
                                             onerror="this.src='/IECEP-LSC-MEMSYS/public/assets/icons/default-school.png';">
                                    </div>
                                    <div class="school-details">
                                        <div class="school-name"><?php echo htmlspecialchars($school['name']); ?></div>
                                        <div class="school-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($school['location'] ?? 'Laguna, Philippines'); ?></span>
                                        </div>
                                        <?php if ($facebook): ?>
                                            <a href="<?php echo $facebook; ?>" target="_blank" rel="noopener noreferrer" class="btn-facebook">
                                                <i class="fab fa-facebook-f"></i> Visit Facebook Page
                                            </a>
                                        <?php else: ?>
                                            <span class="btn-facebook disabled">
                                                <i class="fas fa-link"></i> No Link Available
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-university"></i>
                <h3>No Affiliated Schools Yet</h3>
                <p>Check back soon for updates on our partner institutions.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleAccordion(header) {
            const isExpanded = header.getAttribute('aria-expanded') === 'true';
            const content = header.nextElementSibling;
            
            // Close all other accordions
            document.querySelectorAll('.accordion-header').forEach(h => {
                if (h !== header) {
                    h.setAttribute('aria-expanded', 'false');
                    const otherContent = h.nextElementSibling;
                    if (otherContent) otherContent.setAttribute('aria-expanded', 'false');
                }
            });
            
            // Toggle current
            header.setAttribute('aria-expanded', !isExpanded);
            if (content) content.setAttribute('aria-expanded', !isExpanded);
        }
        
        // Optional: allow keyboard Enter/Space to trigger
        document.querySelectorAll('.accordion-header').forEach(header => {
            header.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleAccordion(header);
                }
            });
        });
    </script>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>