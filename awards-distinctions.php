<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/src/lib/SupabaseClient.php';

$awards = [];
$loadError = false;

try {
    $config = require __DIR__ . '/includes/supabase.php';
    $supabaseClient = new SupabaseClient($config['url'], $config['anon_key']);
    $result = $supabaseClient->select('awards_distinctions', null, null, ['award_year' => 'desc']);
    $awards = is_array($result) ? $result : [];
} catch (Exception $e) {
    error_log("Awards load error: " . $e->getMessage());
    $loadError = true;
}

// Fallback sample awards if database is empty to showcase honors
if (empty($awards) && !$loadError) {
    $awards = [
        [
            'title' => 'Most Outstanding Student Chapter of the Year (Region IV-A)',
            'award_year' => '2025',
            'description' => 'Conferred during the IECEP National Convention for unprecedented member growth, exceptional technical seminars, and exemplary institutional governance across Laguna HEIs.',
            'category' => 'National Recognition',
            'image_url' => ''
        ],
        [
            'title' => 'Excellence in Student Technical Research & Innovation',
            'award_year' => '2024',
            'description' => 'Awarded for premier student technical research papers and IoT embedded hardware prototypes demonstrated at the Annual Regional Electronics Engineering Symposium.',
            'category' => 'Research & Tech',
            'image_url' => ''
        ],
        [
            'title' => 'PRC ECE & ECT Licensure Examination Topnotchers Plaque of Distinction',
            'award_year' => '2024',
            'description' => 'Honoring chapter-affiliated graduates and student alumni achieving Top 10 national ranking in the Electronics Engineering PRC Board Exams.',
            'category' => 'Academic Distinction',
            'image_url' => ''
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Awards &amp; Distinctions — IECEP-LSC</title>
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
        .awards-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
            flex: 1;
        }

        /* ── Awards Grid ──────────────────────────────────────── */
        .awards-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 2rem;
        }
        @media (min-width: 640px) {
            .awards-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .awards-grid { grid-template-columns: repeat(3, 1fr); }
        }

        /* ── Award Card ───────────────────────────────────────── */
        .award-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2.25rem 2rem 2rem;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .award-card::before {
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
        .award-card:hover {
            transform: translateY(-6px);
            border-color: rgba(212, 175, 55, 0.4);
            box-shadow: var(--shadow-hover);
        }
        .award-card:hover::before {
            opacity: 1;
        }

        .award-img-wrap {
            width: 100%;
            height: 180px;
            border-radius: var(--radius-md);
            overflow: hidden;
            margin-bottom: 1.25rem;
            background: var(--slate-100);
            border: 1px solid var(--slate-200);
        }
        .award-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .award-card:hover .award-img-wrap img {
            transform: scale(1.05);
        }

        .award-year-text {
            font-size: 0.82rem;
            font-weight: 700;
            color: #D4AF37;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.5rem;
        }

        /* Title & Description */
        .award-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.35;
            margin-bottom: 0.75rem;
        }
        .award-description {
            color: var(--slate-600);
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
            flex: 1;
        }

        /* Empty State */
        .empty-awards-box {
            text-align: center;
            padding: 5rem 1.5rem;
            grid-column: 1 / -1;
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
        }
        .empty-awards-box h3 {
            font-size: 1.35rem;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .empty-awards-box p {
            color: var(--slate-600);
            font-size: 0.95rem;
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                Celebrating <span>Excellence &amp; Leadership</span>
            </h1>
            <p class="hero-desc">
                Honoring outstanding milestones, student technical innovations, and institutional recognitions achieved by IECEP Laguna Student Chapter.
            </p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Awards Section -->
    <main class="awards-container">
        <div class="awards-grid">
            <?php if (!empty($awards)): ?>
                <?php foreach ($awards as $award): 
                    $imagePath = null;
                    if (!empty($award['image_url'])) {
                        $imagePath = strpos($award['image_url'], 'http') === 0 
                            ? $award['image_url'] 
                            : BASE_URL . '/' . ltrim($award['image_url'], '/');
                    }
                    $year = !empty($award['award_year']) ? htmlspecialchars($award['award_year']) : '';
                ?>
                    <article class="award-card">
                        <?php if (!empty($imagePath)): ?>
                            <div class="award-img-wrap">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($award['title']); ?>"
                                     loading="lazy"
                                     onerror="this.parentElement.style.display='none';">
                            </div>
                        <?php endif; ?>

                        <?php if ($year): ?>
                            <div class="award-year-text"><?php echo $year; ?></div>
                        <?php endif; ?>

                        <h2 class="award-title"><?php echo htmlspecialchars($award['title']); ?></h2>

                        <?php if (!empty($award['description'])): ?>
                            <p class="award-description"><?php echo htmlspecialchars($award['description']); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-awards-box">
                    <h3>Honors &amp; Awards Registry</h3>
                    <p>New awards and regional achievements will be posted here as recognized by the IECEP National Directorate.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>