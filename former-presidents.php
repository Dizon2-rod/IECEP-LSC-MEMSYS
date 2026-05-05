<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Former Presidents - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary) 100%);
            color: var(--white);
            padding: 140px var(--space-4) var(--space-12);
            text-align: center;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('public/uploads/features/1776563415_hero.png') center/cover no-repeat;
            opacity: 0.15;
        }
        .page-hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease-out;
        }
        .page-hero h1 {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 800;
            margin-bottom: var(--space-3);
            letter-spacing: -0.02em;
        }
        .page-hero p {
            font-size: 1.25rem;
            opacity: 0.95;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .presidents-section {
            padding: var(--space-12) 0;
            background: var(--neutral-100);
        }

        .presidents-subtitle {
            text-align: center;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-bottom: var(--space-2);
        }

        .presidents-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-6);
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-2);
        }
        @media (min-width: 640px) {
            .presidents-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (min-width: 1024px) {
            .presidents-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .president-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--space-8) var(--space-6);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            transition: all var(--transition-base);
            border: 1px solid var(--neutral-200);
            position: relative;
            overflow: hidden;
        }
        .president-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), #F5A623);
            transform: scaleX(0);
            transition: transform var(--transition-base);
        }
        .president-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }
        .president-card:hover::before {
            transform: scaleX(1);
        }

        .president-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 2rem;
            margin: 0 auto var(--space-4);
            box-shadow: 0 4px 15px rgba(11, 29, 74, 0.3);
        }

        .president-term-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent) 0%, #F5A623 100%);
            color: var(--primary);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: var(--space-3);
        }

        .president-name {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: var(--space-2);
        }

        .president-term {
            font-weight: 600;
            color: var(--neutral-700);
            font-size: 1rem;
            margin-bottom: var(--space-3);
        }

        .president-quote {
            color: var(--neutral-500);
            font-size: 0.95rem;
            line-height: 1.6;
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-hero">
        <div class="page-hero-content">
            <h1>Former Presidents</h1>
            <p>Honoring the leaders who shaped IECEP-LSC's legacy</p>
        </div>
    </section>

    <section class="presidents-section">
        <div class="container">
            <div class="presidents-subtitle">Leadership Legacy</div>
            <h2 class="section-title">Past Presidents</h2>

            <div class="presidents-grid">
                <div class="president-card">
                    <div class="president-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="president-term-badge">2023-2024</span>
                    <h3 class="president-name">[President Name]</h3>
                    <div class="president-term">President 2023-2024</div>
                    <p class="president-quote">"Leading with vision and integrity to build a stronger IECEP-LSC community."</p>
                </div>

                <div class="president-card">
                    <div class="president-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="president-term-badge">2022-2023</span>
                    <h3 class="president-name">[President Name]</h3>
                    <div class="president-term">President 2022-2023</div>
                    <p class="president-quote">"Empowering students to excel in electronics engineering through innovation and collaboration."</p>
                </div>

                <div class="president-card">
                    <div class="president-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="president-term-badge">2021-2022</span>
                    <h3 class="president-name">[President Name]</h3>
                    <div class="president-term">President 2021-2022</div>
                    <p class="president-quote">"Building bridges between academia and industry for future engineers."</p>
                </div>

                <div class="president-card">
                    <div class="president-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="president-term-badge">2020-2021</span>
                    <h3 class="president-name">[President Name]</h3>
                    <div class="president-term">President 2020-2021</div>
                    <p class="president-quote">"Navigating challenges with resilience and strengthening our chapter's foundation."</p>
                </div>

                <div class="president-card">
                    <div class="president-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="president-term-badge">2019-2020</span>
                    <h3 class="president-name">[President Name]</h3>
                    <div class="president-term">President 2019-2020</div>
                    <p class="president-quote">"Fostering excellence in education and professional development for all members."</p>
                </div>

                <div class="president-card">
                    <div class="president-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="president-term-badge">2018-2019</span>
                    <h3 class="president-name">[President Name]</h3>
                    <div class="president-term">President 2018-2019</div>
                    <p class="president-quote">"Establishing the groundwork for future generations of electronics engineers."</p>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
