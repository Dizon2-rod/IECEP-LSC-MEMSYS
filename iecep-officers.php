<?php
require_once __DIR__ . '/bootstrap.php';

// -------------------------------------------------------------------
// ALL OFFICERS DATA (Executive Board + Committee Members)
// -------------------------------------------------------------------
$officers = [
    // Adviser
    ['name' => 'Engr. Pauline Aquino', 'position' => 'Chapter Adviser', 'committee' => 'Chapter Adviser', 'is_adviser' => true],
    
    // Executive Board
    ['name' => 'Rhyan Castillo', 'position' => 'President', 'committee' => 'Executive Board'],
    ['name' => 'Janica Asajar', 'position' => 'Vice President Internal', 'committee' => 'Executive Board'],
    ['name' => 'Victor Nosis', 'position' => 'Vice President for Academics', 'committee' => 'Executive Board'],
    ['name' => 'Ma. Cassandra Oreste', 'position' => 'Vice President External', 'committee' => 'Executive Board'],
    ['name' => 'Keith Hanna Colot', 'position' => 'Secretary General', 'committee' => 'Executive Board'],
    ['name' => 'Muyhyidden Barraquias', 'position' => 'Assistant Secretary', 'committee' => 'Executive Board'],
    ['name' => 'James Kelvin Doloeras', 'position' => 'Treasurer', 'committee' => 'Executive Board'],
    ['name' => 'Marjorie Mendoza', 'position' => 'Auditor', 'committee' => 'Executive Board'],
    ['name' => 'Maillah Ameril', 'position' => 'Public Relations Officer 1', 'committee' => 'Executive Board'],
    ['name' => 'Paul John Reyes', 'position' => 'Public Relations Officer 2', 'committee' => 'Executive Board'],
    
    // Technical Committee
    ['name' => 'Aljohn Matthew Dizon', 'position' => 'Committee Head', 'committee' => 'Technical Committee'],
    ['name' => 'Kyn Harper Zuniga', 'position' => 'Committee Member', 'committee' => 'Technical Committee'],
    ['name' => 'Angelica Uri', 'position' => 'Committee Member', 'committee' => 'Technical Committee'],
    ['name' => 'Sam Daniel Turla', 'position' => 'Committee Member', 'committee' => 'Technical Committee'],
    ['name' => 'Albert Pedong', 'position' => 'Committee Member', 'committee' => 'Technical Committee'],
    ['name' => 'Syra Caringal', 'position' => 'Committee Member', 'committee' => 'Technical Committee'],
    
    // Registration Committee
    ['name' => 'Kyn Harper Zuniga', 'position' => 'Committee Head', 'committee' => 'Registration Committee'],
    
    // Marketing Committee
    ['name' => 'Angelica Uri', 'position' => 'Committee Head', 'committee' => 'Marketing Committee'],
    
    // Documentation Committee
    ['name' => 'Fernand Reyes', 'position' => 'Committee Head', 'committee' => 'Documentation Committee'],
    
    // Creatives & Publication
    ['name' => 'Junea Ros Rivera', 'position' => 'Committee Head', 'committee' => 'Creatives & Publication'],
    ['name' => 'Princess Klyde Denise Ballesteros', 'position' => 'Committee Member', 'committee' => 'Creatives & Publication'],
    
    // Logistics Committee
    ['name' => 'Geralyn Sapdin', 'position' => 'Committee Head', 'committee' => 'Logistics Committee'],
    ['name' => 'Kiandra Karingal', 'position' => 'Committee Member', 'committee' => 'Logistics Committee'],
];

function getInitials($name) {
    $words = explode(' ', trim(str_replace(['Engr.', 'Engr', 'Ma.'], '', $name)));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) {
            $initials .= strtoupper($w[0]);
            if (strlen($initials) >= 2) break;
        }
    }
    return $initials ?: 'IE';
}

$adviser = $officers[0];
$executiveBoard = array_filter($officers, fn($o) => $o['committee'] === 'Executive Board');
$committees = [
    'Technical Committee' => [
        'desc' => 'Oversees system architectures, digital IDs, technical symposiums, and engineering masterclasses.',
        'members' => array_filter($officers, fn($o) => $o['committee'] === 'Technical Committee')
    ],
    'Registration Committee' => [
        'desc' => 'Validates student rosters, affiliation kits, and institutional membership accreditation.',
        'members' => array_filter($officers, fn($o) => $o['committee'] === 'Registration Committee')
    ],
    'Marketing Committee' => [
        'desc' => 'Spearheads sponsorship drives, inter-school partnerships, and institutional branding outreach.',
        'members' => array_filter($officers, fn($o) => $o['committee'] === 'Marketing Committee')
    ],
    'Documentation Committee' => [
        'desc' => 'Maintains the official historical archive, convention coverage, and institutional photo documentation.',
        'members' => array_filter($officers, fn($o) => $o['committee'] === 'Documentation Committee')
    ],
    'Creatives & Publication' => [
        'desc' => 'Produces flagship publication materials, event branding, digital graphics, and UI design assets.',
        'members' => array_filter($officers, fn($o) => $o['committee'] === 'Creatives & Publication')
    ],
    'Logistics Committee' => [
        'desc' => 'Manages on-ground convention venues, equipment, event operations, and physical resource dispatch.',
        'members' => array_filter($officers, fn($o) => $o['committee'] === 'Logistics Committee')
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Leadership &amp; Directorate — IECEP-LSC</title>
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
            --radius-lg: 18px;
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
        .officers-container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
            flex: 1;
            width: 100%;
        }

        /* ── Adviser Spotlight Card ───────────────────────────── */
        .adviser-spotlight-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2.25rem 2rem;
            margin-bottom: 3.5rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            position: relative;
            overflow: hidden;
        }
        .adviser-spotlight-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary) 0%, var(--accent) 100%);
        }
        .adviser-avatar-box {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            border: 2px solid #D4AF37;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #F8E7A2;
            font-size: 1.5rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .adviser-role-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #D4AF37;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.25rem;
        }
        .adviser-details h3 {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.65rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .adviser-desc {
            color: var(--slate-600);
            font-size: 0.92rem;
            line-height: 1.6;
            margin: 0;
        }

        /* ── Section Dividers ─────────────────────────────────── */
        .section-division-header {
            margin: 3rem 0 1.75rem;
            border-bottom: 2px solid var(--slate-200);
            padding-bottom: 0.85rem;
        }
        .section-division-header h2 {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        /* ── Officers Grid ────────────────────────────────────── */
        .officers-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        @media (min-width: 640px) {
            .officers-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .officers-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (min-width: 1280px) {
            .officers-grid { grid-template-columns: repeat(4, 1fr); }
        }

        /* ── Officer Card ─────────────────────────────────────── */
        .officer-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2rem 1.4rem 1.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .officer-card::before {
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
        .officer-card:hover {
            transform: translateY(-6px);
            border-color: rgba(212, 175, 55, 0.4);
            box-shadow: var(--shadow-hover);
        }
        .officer-card:hover::before {
            opacity: 1;
        }

        .officer-avatar-box {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            border: 2px solid #D4AF37;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #F8E7A2;
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 1.15rem;
        }
        .officer-name {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.3;
            margin-bottom: 0.35rem;
        }
        .officer-position {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--slate-600);
        }

        @media (max-width: 640px) {
            .adviser-spotlight-card {
                flex-direction: column;
                text-align: center;
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
                Executive Leadership &amp; <span>Directorate</span>
            </h1>
            <p class="hero-desc">
                Meet the dedicated student officers and committee leads steering the programs of IECEP Laguna Student Chapter.
            </p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Main Officers List -->
    <main class="officers-container">
        <!-- Adviser Spotlight -->
        <section class="adviser-spotlight-card">
            <div class="adviser-avatar-box">
                <?php echo getInitials($adviser['name']); ?>
            </div>
            <div class="adviser-details">
                <div class="adviser-role-label">Chapter Adviser</div>
                <h3><?php echo htmlspecialchars($adviser['name']); ?></h3>
                <p class="adviser-desc">
                    Guiding the Laguna Student Chapter with faculty mentorship, professional regulatory compliance, and strategic alignment with the IECEP National Board of Directors.
                </p>
            </div>
        </section>

        <!-- Executive Board -->
        <section>
            <div class="section-division-header">
                <h2>Executive Board</h2>
            </div>
            <div class="officers-grid">
                <?php foreach ($executiveBoard as $off): ?>
                    <article class="officer-card">
                        <div class="officer-avatar-box">
                            <?php echo getInitials($off['name']); ?>
                        </div>
                        <h3 class="officer-name"><?php echo htmlspecialchars($off['name']); ?></h3>
                        <div class="officer-position"><?php echo htmlspecialchars($off['position']); ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Working Committees -->
        <?php foreach ($committees as $commName => $commData): ?>
            <section>
                <div class="section-division-header">
                    <h2><?php echo $commName; ?></h2>
                </div>
                <p style="color:var(--slate-600); font-size:0.92rem; margin:-0.75rem 0 1.5rem; line-height:1.5;">
                    <?php echo $commData['desc']; ?>
                </p>
                <div class="officers-grid">
                    <?php foreach ($commData['members'] as $off): ?>
                        <article class="officer-card">
                            <div class="officer-avatar-box">
                                <?php echo getInitials($off['name']); ?>
                            </div>
                            <h3 class="officer-name"><?php echo htmlspecialchars($off['name']); ?></h3>
                            <div class="officer-position"><?php echo htmlspecialchars($off['position']); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>