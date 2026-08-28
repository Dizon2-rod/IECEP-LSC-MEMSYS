<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legacy of Leadership — Former Presidents — IECEP-LSC</title>
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

        /* ── Timeline Section ─────────────────────────────────── */
        .legacy-container {
            max-width: 1040px;
            margin: 0 auto;
            padding: 4rem 1.5rem 5rem;
            position: relative;
            flex: 1;
            width: 100%;
        }

        /* Golden Spine */
        .legacy-container::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 2rem;
            bottom: 4rem;
            width: 2px;
            background: linear-gradient(180deg, rgba(212,175,55,0.1) 0%, rgba(212,175,55,0.6) 20%, rgba(212,175,55,0.6) 80%, rgba(212,175,55,0.1) 100%);
            transform: translateX(-50%);
        }

        .timeline-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            margin-bottom: 3.5rem;
            width: 100%;
        }
        .timeline-row:last-child {
            margin-bottom: 1rem;
        }
        .timeline-row:nth-child(even) {
            flex-direction: row-reverse;
        }

        /* Center Node */
        .timeline-center-node {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #D4AF37;
            border: 3px solid #FFFFFF;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.25);
            z-index: 2;
        }

        /* President Card */
        .president-card {
            width: 45%;
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2.25rem 2rem;
            position: relative;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .president-card::before {
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
        .president-card:hover {
            transform: translateY(-6px);
            border-color: rgba(212, 175, 55, 0.4);
            box-shadow: var(--shadow-hover);
        }
        .president-card:hover::before {
            opacity: 1;
        }

        .term-period {
            font-size: 0.82rem;
            font-weight: 700;
            color: #D4AF37;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.4rem;
        }
        .pres-name {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }
        .pres-school {
            font-size: 0.85rem;
            color: var(--slate-600);
            margin-bottom: 1.15rem;
        }
        .pres-quote {
            font-family: 'Times New Roman', Arial, serif;
            font-style: italic;
            font-size: 0.95rem;
            color: var(--slate-600);
            line-height: 1.65;
            border-left: 2.5px solid #D4AF37;
            padding-left: 0.85rem;
            margin: 0;
        }

        /* ── Tribute Section ──────────────────────────────────── */
        .tribute-box {
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            color: #FFFFFF;
            text-align: center;
            max-width: 800px;
            margin: 2rem auto 0;
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(212, 175, 55, 0.2);
        }
        .tribute-box h3 {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.5rem;
            color: #F8E7A2;
            margin-bottom: 0.5rem;
        }
        .tribute-box p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.92rem;
            line-height: 1.65;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .legacy-container::before { left: 24px; }
            .timeline-center-node { left: 24px; }
            .timeline-row, .timeline-row:nth-child(even) {
                flex-direction: row;
                justify-content: flex-start;
            }
            .president-card {
                width: calc(100% - 48px);
                margin-left: 48px;
                padding: 1.75rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                Legacy of <span>Executive Leadership</span>
            </h1>
            <p class="hero-desc">
                Honoring the visionary chapter presidents who charted the course of IECEP Laguna Student Chapter with dedication, integrity, and relentless pursuit of excellence.
            </p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Timeline -->
    <main class="legacy-container">
        <!-- 2024–2025 -->
        <div class="timeline-row">
            <div class="timeline-center-node"></div>
            <article class="president-card">
                <div class="term-period">AY 2024 — 2025</div>
                <h2 class="pres-name">Rhyan Castillo</h2>
                <div class="pres-school">Chapter President</div>
                <p class="pres-quote">
                    "Continuing the tradition of regional excellence by bridging academic research with state-of-the-art engineering industry applications."
                </p>
            </article>
            <div style="width: 45%;"></div>
        </div>

        <!-- 2023–2024 -->
        <div class="timeline-row">
            <div class="timeline-center-node"></div>
            <div style="width: 45%;"></div>
            <article class="president-card">
                <div class="term-period">AY 2023 — 2024</div>
                <h2 class="pres-name">Former Chapter President</h2>
                <div class="pres-school">Chapter President</div>
                <p class="pres-quote">
                    "Leading with vision, transparency, and inclusive leadership to expand our student member community across Laguna's HEIs."
                </p>
            </article>
        </div>

        <!-- 2022–2023 -->
        <div class="timeline-row">
            <div class="timeline-center-node"></div>
            <article class="president-card">
                <div class="term-period">AY 2022 — 2023</div>
                <h2 class="pres-name">Founding Chapter President</h2>
                <div class="pres-school">Chapter President</div>
                <p class="pres-quote">
                    "Laying the bedrock of student chapter unity in Laguna and establishing the core principles of electronics engineering stewardship."
                </p>
            </article>
            <div style="width: 45%;"></div>
        </div>

        <!-- Tribute Card -->
        <div class="tribute-box">
            <h3>The Torchbearers of One IECEP</h3>
            <p>Every term represents a transformative chapter of innovation. The stewardship of our former presidents serves as the enduring foundation upon which our contemporary milestones are built.</p>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>