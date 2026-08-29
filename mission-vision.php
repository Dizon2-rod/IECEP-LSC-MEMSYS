<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission, Vision &amp; Core Values — IECEP-LSC</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #142A6B;
            --accent: #D4AF37;
            --accent-hover: #C5A059;
            --navy-dark: #07122E;
            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-600: #475569;
            --slate-800: #1E293B;
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-full: 9999px;
            --shadow-card: 0 10px 30px -5px rgba(11, 29, 74, 0.08), 0 4px 10px -2px rgba(11, 29, 74, 0.04);
            --shadow-hover: 0 20px 40px -10px rgba(11, 29, 74, 0.18), 0 8px 16px -4px rgba(212, 175, 55, 0.15);
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F8FAFC;
            color: var(--slate-800);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Page Hero ────────────────────────────────────────── */
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, #07122E 0%, #0B1D4A 55%, #142A6B 100%);
            color: #FFFFFF;
            padding: 120px 1.5rem 60px;
            text-align: center;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 80% 20%, rgba(212, 175, 55, 0.15) 0%, transparent 60%),
                        radial-gradient(circle at 20% 80%, rgba(30, 58, 138, 0.3) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 820px;
            margin: 0 auto;
        }
        .hero-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: clamp(2.2rem, 4.5vw, 3.2rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            color: #FFFFFF;
        }
        .hero-title span {
            background: linear-gradient(135deg, #FFE89E 0%, #D4AF37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.65;
            max-width: 680px;
            margin: 0 auto;
        }

        /* ── Main Container ───────────────────────────────────── */
        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
            flex: 1;
            width: 100%;
        }

        /* ── Mission & Vision Grid ────────────────────────────── */
        .mv-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 4rem;
        }
        @media (min-width: 992px) {
            .mv-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .mv-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2.75rem 2.25rem;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .mv-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .mv-card:hover {
            transform: translateY(-6px);
            border-color: rgba(212, 175, 55, 0.4);
            box-shadow: var(--shadow-hover);
        }
        .mv-card:hover::before {
            opacity: 1;
        }

        .mv-card-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #D4AF37;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 0.5rem;
        }
        .mv-card-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.25rem;
            line-height: 1.3;
        }
        .mv-card-text {
            font-size: 1.02rem;
            color: var(--slate-600);
            line-height: 1.8;
            margin: 0;
            flex: 1;
        }

        /* ── Core Values Section ──────────────────────────────── */
        .values-heading-wrap {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 2.5rem;
        }
        .values-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .values-subtitle {
            color: var(--slate-600);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1.5rem;
        }
        @media (min-width: 640px) {
            .values-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .values-grid { grid-template-columns: repeat(3, 1fr); }
        }

        .value-card {
            background: #FFFFFF;
            border-radius: var(--radius-md);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2rem 1.75rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .value-card:hover {
            transform: translateY(-4px);
            border-color: rgba(212, 175, 55, 0.4);
            box-shadow: var(--shadow-hover);
        }
        .value-name {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .value-desc {
            font-size: 0.9rem;
            color: var(--slate-600);
            line-height: 1.6;
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                Mission, Vision &amp; <span>Core Values</span>
            </h1>
            <p class="hero-desc">
                The foundational charter that inspires our executive programs, student empowerment initiatives, and professional electronics engineering stewardship.
            </p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Mission & Vision -->
    <main class="content-container">
        <section class="mv-grid">
            <!-- Mission Card -->
            <article class="mv-card">
                <span class="mv-card-label">IECEP Mission</span>
                <h2 class="mv-card-title">Our Mission</h2>
                <p class="mv-card-text" style="font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; color: var(--primary);">
                    TO BE THE SHOWCASE OF PROFESSIONAL AND TECHNICAL DEVELOPMENT AND THE PARADIGM OF EXCELLENCE IN APPLYING THE PRINCIPLES OF ELECTRONICS TECHNOLOGY FOR THE ADVANCEMENT OF HUMANITY.
                </p>
            </article>

            <!-- Vision Card -->
            <article class="mv-card">
                <span class="mv-card-label">IECEP Vision</span>
                <h2 class="mv-card-title">Our Vision</h2>
                <p class="mv-card-text" style="font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; color: var(--primary);">
                    THE ORGANIZATION ENVISIONS &ldquo;WORLD-CLASS ORGANIZATION OF HUMANE, COMPETENT, VIRTUOUS AND GLOBALLY-COMPETITIVE ELECTRONICS PROFESSIONALS&rdquo;.
                </p>
            </article>
        </section>

        <!-- Core Values -->
        <section>
            <div class="values-heading-wrap">
                <h2 class="values-title">Core Values</h2>
                <p class="values-subtitle">The fundamental standards and ethical code that govern every initiative of the IECEP Laguna Student Chapter.</p>
            </div>

            <div class="values-grid">
                <div class="value-card">
                    <h3 class="value-name">Excellence</h3>
                    <p class="value-desc">Relentlessly striving for the highest standards in academics, technical research, and organizational execution.</p>
                </div>

                <div class="value-card">
                    <h3 class="value-name">Integrity</h3>
                    <p class="value-desc">Upholding honesty, ethical conduct, transparency, and professional accountability in all chapter affairs.</p>
                </div>

                <div class="value-card">
                    <h3 class="value-name">Innovation</h3>
                    <p class="value-desc">Embracing cutting-edge electronics, AI/IoT integration, and creative problem-solving for modern engineering.</p>
                </div>

                <div class="value-card">
                    <h3 class="value-name">Collaboration</h3>
                    <p class="value-desc">Fostering deep camaraderie, teamwork, and institutional unity among all affiliated HEI student chapters.</p>
                </div>

                <div class="value-card">
                    <h3 class="value-name">Service</h3>
                    <p class="value-desc">Dedicated to community outreach, technology transfer, and contributing meaningfully to nation-building.</p>
                </div>

                <div class="value-card">
                    <h3 class="value-name">Global Competence</h3>
                    <p class="value-desc">Preparing Filipino student engineers to excel and compete confidently in the international technological sphere.</p>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>