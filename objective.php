<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>IECEP Objective - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #1E3A6E;
            --accent: #C5A059;
            --white: #FFFFFF;
            --neutral-200: #E5E7EB;
            --neutral-700: #374151;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --space-6: 24px;
            --space-8: 32px;
            --shadow-md: 0 4px 10px rgba(0,0,0,0.08);
        }
        .page-hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            padding: 80px 1rem 50px;
            text-align: center;
        }
        .page-hero h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: var(--space-2); color: var(--white); }
        .page-hero p { font-size: 0.9rem; opacity: 0.9; }
        .content-section { max-width: 850px; margin: 0 auto; padding: var(--space-8) var(--space-4); }
        .content-section h2 {
            color: var(--primary);
            font-size: 1.4rem;
            margin-bottom: var(--space-3);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        .content-section p { font-size: 0.95rem; line-height: 1.6; color: var(--neutral-700); margin-bottom: var(--space-3); }
        .objective-box {
            background: var(--white);
            padding: var(--space-5);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            margin-bottom: var(--space-5);
            border-left: 3px solid var(--accent);
        }
        .icon-wrapper {
            width: 40px;
            height: 40px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }
        .icon-wrapper i { font-size: 1.2rem; }
        .objectives-list { list-style: none; padding: 0; margin-top: var(--space-4); }
        .objectives-list li {
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
            padding: var(--space-3) 0;
            border-bottom: 1px solid var(--neutral-200);
        }
        .objectives-list li:last-child { border-bottom: none; }
        .objective-number {
            width: 30px;
            height: 30px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .objective-text { font-size: 0.95rem; line-height: 1.5; color: var(--neutral-700); }
        @media (max-width: 640px) {
            .page-hero h1 { font-size: 1.5rem; }
            .content-section h2 { font-size: 1.2rem; }
            .objective-number { width: 26px; height: 26px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-hero">
        <div class="page-hero-content">
            <h1>IECEP Objective</h1>
            <p>Our commitment to advancing electronics engineering in Laguna</p>
        </div>
    </section>

    <main class="content-section">
        <div class="objective-box">
            <h2><div class="icon-wrapper"><i class="fas fa-bullseye"></i></div> Institutional Objectives</h2>
            <p>The Institute of Electronics Engineers of the Philippines - Laguna Student Chapter (IECEP-LSC) is dedicated to the advancement of electronics engineering through education, professional development, and community engagement. Our objectives reflect our commitment to excellence and service.</p>
        </div>

        <h2 style="margin-top: var(--space-2);"><div class="icon-wrapper"><i class="fas fa-list-check"></i></div> Key Objectives</h2>
        <ul class="objectives-list">
            <li><span class="objective-number">1</span><div class="objective-text"><strong>Professional Development:</strong> To enhance the technical knowledge and skills of electronics engineering students through seminars, workshops, and training programs that align with industry standards and emerging technologies.</div></li>
            <li><span class="objective-number">2</span><div class="objective-text"><strong>Academic Excellence:</strong> To promote academic achievement by providing resources, mentorship, and support systems that help students excel in their studies and research endeavors.</div></li>
            <li><span class="objective-number">3</span><div class="objective-text"><strong>Industry Partnership:</strong> To establish strong connections with electronics and telecommunications industries, creating opportunities for internships, employment, and collaborative projects for our members.</div></li>
            <li><span class="objective-number">4</span><div class="objective-text"><strong>Ethical Standards:</strong> To instill professional ethics and social responsibility among future electronics engineers, emphasizing integrity, safety, and sustainable practices.</div></li>
            <li><span class="objective-number">5</span><div class="objective-text"><strong>Community Service:</strong> To contribute to nation-building by engaging in community outreach programs, technology transfer initiatives, and educational campaigns that benefit society.</div></li>
            <li><span class="objective-number">6</span><div class="objective-text"><strong>National Unity:</strong> To foster camaraderie and cooperation among electronics engineering students across Laguna, strengthening the professional network and promoting the "One LSC, One IECEP" spirit.</div></li>
        </ul>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>