<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/paths.php';

// Load schools mapping
$schoolsMapping = require __DIR__ . '/includes/data/schools_mapping.php';

$supabase = new \App\Lib\Supabase();
$affiliatedSchools = [];
try {
    $result = $supabase->from('affiliated_schools')
        ->select('*')
        ->eq('status', 'active')
        ->order('name', true)
        ->get(true);
    $affiliatedSchools = $result['data'] ?? [];
} catch (Exception $e) {
    $affiliatedSchools = [];
}

// Convert mapping to use ASSETS_URL
$schoolLogos = [];
foreach ($schoolsMapping as $name => $path) {
    $schoolLogos[$name] = ASSETS_URL . '/icons/' . basename($path);
}

// Static list of affiliated schools if database is empty
$staticSchools = [
    [
        'name' => 'Colegio de San Juan de Letrán',
        'logo' => ASSETS_URL . '/icons/LETRAN.png',
        'facebook_url' => 'https://www.facebook.com/LetranCalamba',
        'member_count' => 150,
        'created_at' => '2020-01-15'
    ],
    [
        'name' => 'Laguna State Polytechnic University - Santa Cruz Campus',
        'logo' => ASSETS_URL . '/icons/LSPU-SCC.png',
        'facebook_url' => 'https://www.facebook.com/LSPUSantaCruz',
        'member_count' => 200,
        'created_at' => '2019-06-20'
    ],
    [
        'name' => 'Laguna State Polytechnic University - San Pablo City Campus',
        'logo' => ASSETS_URL . '/icons/LSPU-SPCC.png',
        'facebook_url' => 'https://www.facebook.com/LSPUSanPablo',
        'member_count' => 180,
        'created_at' => '2019-08-10'
    ],
    [
        'name' => 'Mapua Malayan Colleges Laguna',
        'logo' => ASSETS_URL . '/icons/MMCL.webp',
        'facebook_url' => 'https://www.facebook.com/MMCLaguna',
        'member_count' => 120,
        'created_at' => '2020-03-25'
    ],
    [
        'name' => 'Polytechnic University of the Philippines - Santa Rosa Campus',
        'logo' => ASSETS_URL . '/icons/PUP-STA ROSA.png',
        'facebook_url' => 'https://www.facebook.com/PUPSantaRosa',
        'member_count' => 160,
        'created_at' => '2019-11-15'
    ],
    [
        'name' => 'Pamantasan ng Cabuyao',
        'logo' => ASSETS_URL . '/icons/UC-PNC.png',
        'facebook_url' => 'https://www.facebook.com/PamantasanNgCabuyao',
        'member_count' => 140,
        'created_at' => '2020-05-30'
    ],
    [
        'name' => 'University of Perpetual Help System Dalta - Calamba Campus',
        'logo' => ASSETS_URL . '/icons/UPHSD.png',
        'facebook_url' => 'https://www.facebook.com/UPHSDCalamba',
        'member_count' => 190,
        'created_at' => '2019-07-12'
    ],
    [
        'name' => 'University of Perpetual Help System Jonelta - Biñán Campus',
        'logo' => ASSETS_URL . '/icons/UPHSL-BINAN.png',
        'facebook_url' => 'https://www.facebook.com/UPHSLBinan',
        'member_count' => 170,
        'created_at' => '2019-09-05'
    ]
];

// Use database data if available, otherwise use static data
$schoolsToShow = !empty($affiliatedSchools) ? $affiliatedSchools : $staticSchools;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Affiliated Schools - IECEP-LSC</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            padding: 120px var(--space-4) var(--space-12);
            text-align: center;
            margin-top: 64px;
        }
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: var(--space-3);
        }
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-4);
        }

        /* Accordion */
        .accordion {
            max-width: 900px;
            margin: 0 auto;
        }
        .accordion-item {
            background: var(--white);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .accordion-header {
            padding: var(--space-4) var(--space-6);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all var(--transition-base);
            background: var(--white);
        }
        .accordion-header:hover {
            background: var(--neutral-100);
        }
        .accordion-header.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
        }
        .accordion-title {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        .school-logo {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            object-fit: contain;
            background: var(--white);
            padding: 4px;
        }
        .accordion-icon {
            font-size: 1.2rem;
            transition: transform var(--transition-base);
        }
        .accordion-header.active .accordion-icon {
            transform: rotate(180deg);
        }
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .accordion-content.active {
            max-height: 500px;
        }
        .accordion-body {
            padding: var(--space-6);
            border-top: 1px solid var(--neutral-200);
        }
        .school-details {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-4);
        }
        @media (min-width: 640px) {
            .school-details {
                grid-template-columns: 1fr 1fr;
            }
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--neutral-700);
        }
        .detail-item i {
            color: var(--accent);
            width: 20px;
        }
        .school-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            background: #1877F2;
            color: var(--white);
            text-decoration: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            margin-top: var(--space-4);
            transition: all var(--transition-base);
        }
        .school-btn:hover {
            background: #1465D6;
        }
        .school-btn.disabled {
            background: var(--neutral-200);
            color: var(--neutral-500);
            cursor: not-allowed;
        }
        .school-btn.disabled:hover {
            background: var(--neutral-200);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--space-12) var(--space-4);
        }
        .empty-state i {
            font-size: 4rem;
            color: var(--neutral-200);
            margin-bottom: var(--space-4);
        }
        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--neutral-700);
            margin-bottom: var(--space-2);
        }
        .empty-state p {
            color: var(--neutral-500);
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <h1>Affiliated Schools</h1>
        <p>The 𝗜𝗘𝗖𝗘𝗣-𝗟𝗦𝗖 brings together affiliated higher education institutions, united in one frame through connection, collaboration, and shared purpose.</p>
    </section>

    <!-- Schools Accordion -->
    <div class="container">
        <?php if (!empty($schoolsToShow)): ?>
            <div class="accordion">
                <?php $index = 1; foreach ($schoolsToShow as $school): ?>
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <div class="accordion-title">
                                <?php 
                                $schoolName = $school['name'];
                                $logoPath = isset($schoolLogos[$schoolName]) ? $schoolLogos[$schoolName] : ASSETS_URL . '/icons/iecep-logo.png';
                                ?>
                                <img src="<?php echo $logoPath; ?>" alt="<?php echo htmlspecialchars($schoolName); ?>" class="school-logo">
                                <span><?php echo htmlspecialchars($schoolName); ?></span>
                            </div>
                            <i class="fas fa-chevron-down accordion-icon"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="accordion-body">
                                <div class="school-details">
                                    <div class="detail-item">
                                        <i class="fas fa-university"></i>
                                        <span><?php echo htmlspecialchars($school['name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span>
                                            <?php 
                                            $memberCount = $school['member_count'] ?? 0;
                                            echo $memberCount > 0 ? $memberCount . ' Registered Members' : 'Active Member';
                                            ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-circle-check"></i>
                                        <span>Status: Active</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Member since: <?php echo !empty($school['created_at']) ? date('Y', strtotime($school['created_at'])) : 'N/A'; ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($school['facebook_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($school['facebook_url']); ?>" target="_blank" rel="noopener noreferrer" class="school-btn">
                                        <i class="fab fa-facebook"></i> Visit Facebook Page
                                    </a>
                                <?php else: ?>
                                    <span class="school-btn disabled">
                                        <i class="fas fa-link"></i> No Facebook Link Available
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php $index++; endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-university"></i>
                <h3>No Affiliated Schools Yet</h3>
                <p>Check back soon for updates on our affiliated institutions.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleAccordion(header) {
            const content = header.nextElementSibling;
            const isActive = header.classList.contains('active');
            
            // Close all accordions
            document.querySelectorAll('.accordion-header').forEach(h => {
                h.classList.remove('active');
                h.nextElementSibling.classList.remove('active');
            });
            
            // Open clicked accordion if it wasn't active
            if (!isActive) {
                header.classList.add('active');
                content.classList.add('active');
            }
        }
    </script>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
