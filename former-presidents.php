<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Legacy of Leadership - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:italic,wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0B1D4A;
            --primary-main: #1A3A8A;
            --accent-gold: #C5A059;
            --accent-light: #F9F6EE;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --white: #FFFFFF;
            --shadow-elegant: 0 15px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #FDFDFD;
            color: var(--text-main);
        }

        /* --- HERO SECTION (unchanged, but slightly smaller padding) --- */
        .page-hero {
            position: relative;
            background: var(--primary-dark);
            color: var(--white);
            padding: 120px 0 80px 0;
            text-align: center;
            overflow: hidden;
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: url('public/uploads/features/1776563415_hero.png') center/cover no-repeat;
            opacity: 0.1;
            mix-blend-mode: overlay;
        }
        .page-hero-content {
            position: relative;
            z-index: 2;
            max-width: 700px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        .page-hero h1 {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -0.03em;
        }
        .page-hero p {
            font-size: 1rem;
            font-weight: 300;
            opacity: 0.8;
        }

        /* --- TIMELINE SECTION (compact) --- */
        .legacy-container {
            padding: 60px 0;
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
        }
        
        /* Vertical line - thinner */
        .legacy-container::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 1px;
            background: linear-gradient(to bottom, transparent, var(--accent-gold), transparent);
            transform: translateX(-50%);
        }

        .timeline-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 70px;
            position: relative;
        }

        .timeline-item:nth-child(even) {
            flex-direction: row-reverse;
        }

        /* Timeline Dot - smaller */
        .timeline-dot {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 14px;
            height: 14px;
            background: var(--accent-gold);
            border: 3px solid var(--white);
            border-radius: 50%;
            z-index: 2;
            box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.2);
        }

        /* President Card - smaller padding and width */
        .president-card {
            width: 45%;
            background: var(--white);
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: var(--shadow-elegant);
            transition: var(--transition);
            border: 1px solid #F3F4F6;
            position: relative;
        }
        .president-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px rgba(0,0,0,0.1);
            border-color: var(--accent-gold);
        }

        /* Portrait - smaller */
        .portrait-area {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-main), var(--primary-dark));
            border-radius: 50%;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            border: 3px solid var(--accent-light);
            box-shadow: 0 5px 12px rgba(0,0,0,0.1);
        }

        /* Typography - smaller fonts */
        .term-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--accent-gold);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.3rem;
            display: block;
        }
        .pres-name {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 0.6rem;
            line-height: 1.3;
        }
        .pres-quote {
            font-family: 'Playfair Display', serif;
            font-size: 0.9rem;
            font-style: italic;
            color: var(--text-muted);
            line-height: 1.5;
            position: relative;
            padding-left: 1rem;
        }
        .pres-quote::before {
            content: '"';
            position: absolute;
            left: 0;
            top: -5px;
            font-size: 1.4rem;
            color: var(--accent-gold);
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .legacy-container::before { left: 20px; }
            .timeline-dot { left: 20px; }
            .timeline-item, .timeline-item:nth-child(even) {
                flex-direction: row;
                justify-content: flex-start;
            }
            .president-card {
                width: calc(100% - 60px);
                margin-left: 40px;
                padding: 1.2rem;
            }
            .pres-name { font-size: 1rem; }
            .pres-quote { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-hero">
        <div class="hero-overlay"></div>
        <div class="page-hero-content">
            <h1>Legacy of Leadership</h1>
            <p>A tribute to the visionary presidents who have guided the IECEP-LSC through eras of innovation and growth since 2022.</p>
        </div>
    </section>

    <main class="legacy-container">
        
        <!-- 2024-2025: Most Recent -->
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <article class="president-card">
                <div class="portrait-area"><i class="fas fa-user-tie"></i></div>
                <span class="term-label">2024 — 2025</span>
                <h3 class="pres-name">Rhyan Castillo</h3>
                <p class="pres-quote">"Continuing the tradition of excellence by bridging the gap between academic theory and professional practice."</p>
            </article>
            <div style="width: 45%;"></div>
        </div>

        <!-- 2023-2024 -->
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div style="width: 45%;"></div>
            <article class="president-card">
                <div class="portrait-area"><i class="fas fa-user-tie"></i></div>
                <span class="term-label">2023 — 2024</span>
                <h3 class="pres-name">[President Name]</h3>
                <p class="pres-quote">"Leading with vision and integrity to build a stronger and more inclusive IECEP-LSC community."</p>
            </article>
        </div>

        <!-- 2022-2023 -->
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <article class="president-card">
                <div class="portrait-area"><i class="fas fa-user-tie"></i></div>
                <span class="term-label">2022 — 2023</span>
                <h3 class="pres-name">[President Name]</h3>
                <p class="pres-quote">"Empowering students to excel in electronics engineering through innovation and relentless collaboration."</p>
            </article>
            <div style="width: 45%;"></div>
        </div>

    </main>

    <section style="text-align: center; padding: 40px 2rem; background: var(--accent-light);">
        <div style="max-width: 600px; margin: 0 auto;">
            <h2 style="color: var(--primary-dark); font-weight: 800; font-size: 1.3rem; margin-bottom: 0.5rem;">The Torchbearers</h2>
            <p style="color: var(--text-muted); line-height: 1.6; font-size: 0.85rem;">Each term represents a chapter of growth. The leadership of our former presidents serves as the foundation upon which our current successes are built.</p>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>