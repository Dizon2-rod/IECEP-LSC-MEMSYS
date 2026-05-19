<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Mission and Vision - IECEP-LSC MEMSYS</title>
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
        .mission-box, .vision-box {
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
        .values-section h2 { margin-top: var(--space-4); }
        .values-section ul {
            font-size: 0.9rem;
            line-height: 1.7;
            color: var(--neutral-700);
            margin-left: var(--space-4);
        }
        @media (max-width: 640px) {
            .page-hero h1 { font-size: 1.5rem; }
            .content-section h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-hero">
        <div class="page-hero-content">
            <h1>Mission and Vision</h1>
            <p>The guiding principles of IECEP-Laguna Student Chapter</p>
        </div>
    </section>

    <main class="content-section">
        <div class="mission-box">
            <h2><div class="icon-wrapper"><i class="fas fa-bullseye"></i></div> Our Mission</h2>
            <p>To foster excellence in electronics engineering education and practice among students in Laguna, promoting professional development, ethical standards, and technological innovation. We aim to create a collaborative environment that bridges academic learning with industry practices, preparing the next generation of electronics engineers to become leaders and innovators in their field.</p>
        </div>

        <div class="vision-box">
            <h2><div class="icon-wrapper"><i class="fas fa-eye"></i></div> Our Vision</h2>
            <p>To be the premier student organization for electronics engineering in the Philippines, recognized for producing highly competent, socially responsible, and globally competitive professionals who contribute significantly to the advancement of technology and the betterment of society.</p>
        </div>

        <div class="values-section">
            <h2><div class="icon-wrapper"><i class="fas fa-heart"></i></div> Core Values</h2>
            <ul>
                <li><strong>Excellence:</strong> Striving for the highest standards in education and practice</li>
                <li><strong>Integrity:</strong> Upholding ethical principles and professional conduct</li>
                <li><strong>Innovation:</strong> Embracing new ideas and technologies</li>
                <li><strong>Collaboration:</strong> Working together for collective success</li>
                <li><strong>Service:</strong> Contributing to community and nation-building</li>
            </ul>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>