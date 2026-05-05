<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>IECEP Objective - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary) 100%);
            color: var(--white);
            padding: 140px 1rem var(--space-8);
            text-align: center;
            overflow: hidden;
        }
        .page-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--space-4);
        }
        .content-section {
            max-width: 900px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-4);
        }
        .content-section h2 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        .content-section p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--neutral-700);
            margin-bottom: var(--space-4);
        }
        .objective-box {
            background: var(--white);
            padding: var(--space-6);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            margin-bottom: var(--space-6);
            border-left: 4px solid var(--accent);
        }
        .icon-wrapper {
            width: 48px;
            height: 48px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }
        .icon-wrapper i {
            font-size: 1.5rem;
        }
        .objectives-list {
            list-style: none;
            padding: 0;
        }
        .objectives-list li {
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-4) 0;
            border-bottom: 1px solid var(--neutral-200);
        }
        .objectives-list li:last-child {
            border-bottom: none;
        }
        .objective-number {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }
        .objective-text {
            font-size: 1.1rem;
            line-height: 1.6;
            color: var(--neutral-700);
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
            <h2>
                <div class="icon-wrapper">
                    <i class="fas fa-bullseye"></i>
                </div>
                Institutional Objectives
            </h2>
            <p>
                The Institute of Electronics Engineers of the Philippines - Laguna Student Chapter (IECEP-LSC) 
                is dedicated to the advancement of electronics engineering through education, professional 
                development, and community engagement. Our objectives reflect our commitment to excellence 
                and service.
            </p>
        </div>

        <div class="objectives-list">
            <h2 style="margin-bottom: var(--space-6);">
                <div class="icon-wrapper">
                    <i class="fas fa-list-check"></i>
                </div>
                Key Objectives
            </h2>
            
            <li>
                <span class="objective-number">1</span>
                <p class="objective-text">
                    <strong>Professional Development:</strong> To enhance the technical knowledge and skills of 
                    electronics engineering students through seminars, workshops, and training programs that 
                    align with industry standards and emerging technologies.
                </p>
            </li>
            <li>
                <span class="objective-number">2</span>
                <p class="objective-text">
                    <strong>Academic Excellence:</strong> To promote academic achievement by providing resources, 
                    mentorship, and support systems that help students excel in their studies and research 
                    endeavors.
                </p>
            </li>
            <li>
                <span class="objective-number">3</span>
                <p class="objective-text">
                    <strong>Industry Partnership:</strong> To establish strong connections with electronics 
                    and telecommunications industries, creating opportunities for internships, employment, 
                    and collaborative projects for our members.
                </p>
            </li>
            <li>
                <span class="objective-number">4</span>
                <p class="objective-text">
                    <strong>Ethical Standards:</strong> To instill professional ethics and social responsibility 
                    among future electronics engineers, emphasizing integrity, safety, and sustainable practices.
                </p>
            </li>
            <li>
                <span class="objective-number">5</span>
                <p class="objective-text">
                    <strong>Community Service:</strong> To contribute to nation-building by engaging in 
                    community outreach programs, technology transfer initiatives, and educational campaigns 
                    that benefit society.
                </p>
            </li>
            <li>
                <span class="objective-number">6</span>
                <p class="objective-text">
                    <strong>National Unity:</strong> To foster camaraderie and cooperation among electronics 
                    engineering students across Laguna, strengthening the professional network and promoting 
                    the "One LSC, One IECEP" spirit.
                </p>
            </li>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
