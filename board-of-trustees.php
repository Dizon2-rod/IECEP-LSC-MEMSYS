<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Board of Trustees - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        /* Page-specific styles for Board of Trustees */
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary) 100%);
            color: var(--white);
            padding: 140px 1rem var(--space-8);
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
            max-width: 900px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease-out;
        }
        .page-hero h1 {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 800;
            margin-bottom: var(--space-4);
            letter-spacing: -0.02em;
        }
        .page-hero p {
            font-size: 1.25rem;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Executive Board Section */
        .board-section {
            padding: var(--space-12) 0;
            background: var(--neutral-100);
        }

        .board-subtitle {
            text-align: center;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-bottom: var(--space-2);
        }

        /* Trustee Cards Grid */
        .trustees-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-6);
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-2);
        }
        @media (min-width: 640px) {
            .trustees-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (min-width: 1024px) {
            .trustees-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Trustee Card */
        .trustee-card {
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
        .trustee-card::before {
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
        .trustee-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }
        .trustee-card:hover::before {
            transform: scaleX(1);
        }

        .trustee-icon {
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

        /* Mobile Responsiveness */
        @media (max-width: 575.98px) {
            .page-hero {
                padding: 120px 1rem 3rem;
            }
            .page-hero h1 {
                font-size: 2rem;
            }
            .page-hero p {
                font-size: 1rem;
            }
            .trustees-grid {
                gap: 1.5rem;
                padding: 0 1rem;
            }
            .trustee-card {
                padding: 2rem 1.5rem;
            }
            .trustee-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }
        
        @media (min-width: 576px) and (max-width: 767.98px) {
            .page-hero {
                padding: 130px 1.5rem 4rem;
            }
            .trustees-grid {
                gap: 2rem;
            }
        }

        .trustee-role-badge {
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

        .trustee-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: var(--space-2);
        }

        .trustee-name {
            font-weight: 600;
            color: var(--neutral-900);
            font-size: 1.1rem;
            margin-bottom: var(--space-3);
        }

        .trustee-description {
            color: var(--neutral-500);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Quote Section */
        .quote-section {
            padding: var(--space-12) 0;
            background: var(--white);
        }

        .quote-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 var(--space-2);
            text-align: center;
        }

        blockquote {
            font-family: 'Times New Roman', serif;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-style: italic;
            color: var(--primary);
            line-height: 1.6;
            position: relative;
            padding: var(--space-8) 0;
        }

        blockquote::before {
            content: '"';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            font-size: 4rem;
            color: var(--accent);
            opacity: 0.3;
            font-family: Georgia, serif;
        }

        blockquote cite {
            display: block;
            margin-top: var(--space-4);
            font-size: 1rem;
            color: var(--neutral-500);
            font-style: normal;
            font-weight: 600;
        }

        blockquote cite::before {
            content: '— ';
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Page Hero -->
    <section class="page-hero">
        <div class="page-hero-content">
            <h1>Board of Trustees</h1>
            <p>Meet the visionary leaders guiding IECEP-LSC towards excellence in electronics engineering education and professional development.</p>
        </div>
    </section>

    <!-- Executive Board Section -->
    <section class="board-section">
        <div class="container">
            <div class="board-subtitle">Leadership Team</div>
            <h2 class="section-title">EXECUTIVE BOARD 2024-2025</h2>

            <div class="trustees-grid">
                <!-- President -->
                <div class="trustee-card">
                    <div class="trustee-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <span class="trustee-role-badge">CHIEF EXECUTIVE</span>
                    <h3 class="trustee-title">President</h3>
                    <div class="trustee-name">[President Name]</div>
                    <p class="trustee-description">Provides strategic leadership and vision, oversees all chapter operations, and represents IECEP-LSC in national and regional engagements.</p>
                </div>

                <!-- VP Internal -->
                <div class="trustee-card">
                    <div class="trustee-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <span class="trustee-role-badge">INTERNAL OPERATIONS</span>
                    <h3 class="trustee-title">Vice President Internal</h3>
                    <div class="trustee-name">[VP Internal Name]</div>
                    <p class="trustee-description">Manages internal affairs, coordinates chapter activities, ensures smooth operations across all departments and committees.</p>
                </div>

                <!-- VP External -->
                <div class="trustee-card">
                    <div class="trustee-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <span class="trustee-role-badge">EXTERNAL RELATIONS</span>
                    <h3 class="trustee-title">Vice President External</h3>
                    <div class="trustee-name">[VP External Name]</div>
                    <p class="trustee-description">Manages external partnerships, industry linkages, and represents IECEP-LSC in external events and collaborative initiatives.</p>
                </div>

                <!-- Secretary -->
                <div class="trustee-card">
                    <div class="trustee-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <span class="trustee-role-badge">DOCUMENTATION & RECORDS</span>
                    <h3 class="trustee-title">Secretary</h3>
                    <div class="trustee-name">[Secretary Name]</div>
                    <p class="trustee-description">Maintains organizational records, ensures effective communication, documents meetings, and manages official correspondence.</p>
                </div>

                <!-- Auditor -->
                <div class="trustee-card">
                    <div class="trustee-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <span class="trustee-role-badge">COMPLIANCE & AUDIT</span>
                    <h3 class="trustee-title">Auditor</h3>
                    <div class="trustee-name">[Auditor Name]</div>
                    <p class="trustee-description">Ensures transparency, reviews financial records, monitors compliance with bylaws, and maintains accountability standards.</p>
                </div>

                <!-- Treasurer -->
                <div class="trustee-card">
                    <div class="trustee-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <span class="trustee-role-badge">FINANCIAL MANAGEMENT</span>
                    <h3 class="trustee-title">Treasurer</h3>
                    <div class="trustee-name">[Treasurer Name]</div>
                    <p class="trustee-description">Manages chapter finances, oversees budget allocation, ensures proper financial reporting, and maintains fiscal responsibility.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quote Section -->
    <section class="quote-section">
        <div class="quote-container">
            <blockquote>
                Leadership is not about being in charge. It's about taking care of those in your charge. Together, we build a stronger IECEP-LSC for future generations of electronics engineers.
                <cite>IECEP-LSC Leadership Philosophy</cite>
            </blockquote>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
