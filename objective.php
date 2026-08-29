<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Objectives — IECEP-LSC</title>
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
        .objectives-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
            flex: 1;
            width: 100%;
        }

        /* ── Institutional Overview Box ───────────────────────── */
        .overview-box {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2.5rem 2.25rem;
            margin-bottom: 3.5rem;
            position: relative;
            overflow: hidden;
        }
        .overview-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary) 0%, var(--accent) 100%);
        }
        .overview-text-wrap h2 {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .overview-text-wrap p {
            color: var(--slate-600);
            font-size: 1rem;
            line-height: 1.7;
            margin: 0;
        }

        /* ── Objectives Heading ───────────────────────────────── */
        .objectives-heading-wrap {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 2.5rem;
        }
        .objectives-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .objectives-subtitle {
            color: var(--slate-600);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* ── Objectives Cards Grid ────────────────────────────── */
        .objectives-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1.75rem;
        }
        @media (min-width: 640px) {
            .objectives-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .objectives-grid { grid-template-columns: repeat(3, 1fr); }
        }

        .objective-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2.25rem 1.75rem;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .objective-card::before {
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
        .objective-card:hover {
            transform: translateY(-6px);
            border-color: rgba(212, 175, 55, 0.4);
            box-shadow: var(--shadow-hover);
        }
        .objective-card:hover::before {
            opacity: 1;
        }

        .obj-num-badge {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #0B1D4A;
            color: #F8E7A2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 2px 8px rgba(11, 29, 74, 0.2);
            margin-bottom: 1.25rem;
        }

        .obj-card-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.65rem;
            line-height: 1.35;
        }
        .obj-card-desc {
            font-size: 0.92rem;
            color: var(--slate-600);
            line-height: 1.65;
            margin: 0;
            flex: 1;
        }

        /* ── Chapter Banner ───────────────────────────────────── */
        .chapter-synergy-banner {
            margin-top: 4rem;
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            color: #FFFFFF;
            text-align: center;
            box-shadow: 0 15px 35px rgba(11, 29, 74, 0.15);
            border: 1px solid rgba(212, 175, 55, 0.2);
        }
        .synergy-text h3 {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.6rem;
            color: #F8E7A2;
            margin-bottom: 0.5rem;
        }
        .synergy-text p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.95rem;
            margin: 0 auto;
            max-width: 650px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                Institutional <span>Objectives</span>
            </h1>
            <p class="hero-desc">
                Our strategic roadmap for advancing electronics engineering competence, ethical leadership, academic distinction, and community technology transfer across Laguna.
            </p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Objectives Grid -->
    <main class="objectives-container">
        <!-- Overview Statement Box -->
        <section class="overview-box">
            <div class="overview-text-wrap">
                <h2>ARTICLE 2. PURPOSE AND OBJECTIVES</h2>
                <p style="font-size: 1.08rem; font-weight: 700; color: var(--primary); margin-top: 0.35rem;">
                    SECTION 1: The IECEP-LSC aims to:
                </p>
            </div>
        </section>

        <!-- 4 Key Objectives from Constitution -->
        <section>
            <div class="objectives-heading-wrap">
                <h2 class="objectives-title">IECEP-LSC Objectives</h2>
                <p class="objectives-subtitle">The foundational principles that guide every program, initiative, and assembly of the student chapter.</p>
            </div>

            <div class="objectives-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                <!-- a.) Academic Excellence -->
                <article class="objective-card">
                    <div class="obj-num-badge">A</div>
                    <h3 class="obj-card-title">Academic Excellence</h3>
                    <p class="obj-card-desc" style="font-size: 1rem; color: #1E293B;">
                        To promote and uphold academic excellence among its members.
                    </p>
                </article>

                <!-- b.) Student Unity -->
                <article class="objective-card">
                    <div class="obj-num-badge">B</div>
                    <h3 class="obj-card-title">Chapter &amp; Student Unity</h3>
                    <p class="obj-card-desc" style="font-size: 1rem; color: #1E293B;">
                        To develop a sense of unity among the Electronics Engineering students of different schools.
                    </p>
                </article>

                <!-- c.) Community & Institutional Responsibility -->
                <article class="objective-card">
                    <div class="obj-num-badge">C</div>
                    <h3 class="obj-card-title">Sense of Responsibility</h3>
                    <p class="obj-card-desc" style="font-size: 1rem; color: #1E293B;">
                        To instill a sense of responsibility among the Electronics Engineering students in serving the whole academic community in general and the organization in particular.
                    </p>
                </article>

                <!-- d.) Technical Dissemination -->
                <article class="objective-card">
                    <div class="obj-num-badge">D</div>
                    <h3 class="obj-card-title">Technical Information Dissemination</h3>
                    <p class="obj-card-desc" style="font-size: 1rem; color: #1E293B;">
                        To disseminate vital technical information in order to develop a well-rounded professional.
                    </p>
                </article>
            </div>
        </section>

        <!-- Chapter Banner -->
        <div class="chapter-synergy-banner">
            <div class="synergy-text">
                <h3>One LSC. One IECEP. One Vision.</h3>
                <p>Guided by our constitutional purpose and objectives, we continue to unite Laguna Electronics Engineering students toward academic distinction and professional development.</p>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>