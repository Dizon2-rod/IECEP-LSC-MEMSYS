<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Mission and Vision - IECEP-LSC MEMSYS</title>
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
        .mission-box, .vision-box {
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
            <h2>
                <div class="icon-wrapper">
                    <i class="fas fa-bullseye"></i>
                </div>
                Our Mission
            </h2>
            <p>
                To foster excellence in electronics engineering education and practice among students in Laguna, 
                promoting professional development, ethical standards, and technological innovation. We aim to 
                create a collaborative environment that bridges academic learning with industry practices, 
                preparing the next generation of electronics engineers to become leaders and innovators in their field.
            </p>
        </div>

        <div class="vision-box">
            <h2>
                <div class="icon-wrapper">
                    <i class="fas fa-eye"></i>
                </div>
                Our Vision
            </h2>
            <p>
                To be the premier student organization for electronics engineering in the Philippines, recognized 
                for producing highly competent, socially responsible, and globally competitive professionals who 
                contribute significantly to the advancement of technology and the betterment of society.
            </p>
        </div>

        <div class="values-section">
            <h2>
                <div class="icon-wrapper">
                    <i class="fas fa-heart"></i>
                </div>
                Core Values
            </h2>
            <ul style="font-size: 1.1rem; line-height: 2; color: var(--neutral-700); margin-left: var(--space-4);">
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
