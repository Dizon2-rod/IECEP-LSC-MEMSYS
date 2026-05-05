<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>IECEP Officers - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        /* Page-specific styles */
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
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .officers-section {
            padding: var(--space-12) 0;
            background: var(--neutral-100);
        }

        .officers-subtitle {
            text-align: center;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-bottom: var(--space-2);
        }

        .officers-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-6);
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-2);
        }
        @media (min-width: 640px) {
            .officers-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (min-width: 1024px) {
            .officers-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .officer-card {
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
        .officer-card::before {
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
        .officer-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }
        .officer-card:hover::before {
            transform: scaleX(1);
        }

        .officer-icon {
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

        .officer-role-badge {
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

        .officer-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: var(--space-2);
        }

        .officer-name {
            font-weight: 600;
            color: var(--neutral-900);
            font-size: 1.1rem;
            margin-bottom: var(--space-3);
        }

        .officer-description {
            color: var(--neutral-500);
            font-size: 0.95rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Page Hero -->
    <section class="page-hero">
        <div class="page-hero-content">
            <h1>IECEP Officers</h1>
            <p>Executive Board 2024-2025</p>
        </div>
    </section>

    <!-- Officers Section -->
    <section class="officers-section">
        <div class="container">
            <div class="officers-subtitle">Leadership Team</div>
            <h2 class="section-title">Current Officers</h2>

            <div class="officers-grid">
                <!-- President -->
                <div class="officer-card">
                    <div class="officer-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <span class="officer-role-badge">CHIEF EXECUTIVE</span>
                    <h3 class="officer-title">President</h3>
                    <div class="officer-name">[President Name]</div>
                    <p class="officer-description">Provides strategic leadership and vision, oversees all chapter operations, and represents IECEP-LSC in national and regional engagements.</p>
                </div>

                <!-- VP Internal -->
                <div class="officer-card">
                    <div class="officer-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <span class="officer-role-badge">INTERNAL OPERATIONS</span>
                    <h3 class="officer-title">Vice President Internal</h3>
                    <div class="officer-name">[VP Internal Name]</div>
                    <p class="officer-description">Manages internal affairs, coordinates chapter activities, ensures smooth operations across all departments and committees.</p>
                </div>

                <!-- VP External -->
                <div class="officer-card">
                    <div class="officer-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <span class="officer-role-badge">EXTERNAL RELATIONS</span>
                    <h3 class="officer-title">Vice President External</h3>
                    <div class="officer-name">[VP External Name]</div>
                    <p class="officer-description">Manages external partnerships, industry linkages, and represents IECEP-LSC in external events and collaborative initiatives.</p>
                </div>

                <!-- Secretary -->
                <div class="officer-card">
                    <div class="officer-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <span class="officer-role-badge">DOCUMENTATION & RECORDS</span>
                    <h3 class="officer-title">Secretary</h3>
                    <div class="officer-name">[Secretary Name]</div>
                    <p class="officer-description">Maintains organizational records, ensures effective communication, documents meetings, and manages official correspondence.</p>
                </div>

                <!-- Treasurer -->
                <div class="officer-card">
                    <div class="officer-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <span class="officer-role-badge">FINANCIAL MANAGEMENT</span>
                    <h3 class="officer-title">Treasurer</h3>
                    <div class="officer-name">[Treasurer Name]</div>
                    <p class="officer-description">Manages chapter finances, oversees budget allocation, ensures proper financial reporting, and maintains fiscal responsibility.</p>
                </div>

                <!-- Auditor -->
                <div class="officer-card">
                    <div class="officer-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <span class="officer-role-badge">COMPLIANCE & AUDIT</span>
                    <h3 class="officer-title">Auditor</h3>
                    <div class="officer-name">[Auditor Name]</div>
                    <p class="officer-description">Ensures transparency, reviews financial records, monitors compliance with bylaws, and maintains accountability standards.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
