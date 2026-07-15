<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Awards & Distinctions - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #1E3A6E;
            --accent: #C5A059;
            --white: #FFFFFF;
            --neutral-100: #F9FAFB;
            --neutral-200: #E5E7EB;
            --neutral-300: #D1D5DB;
            --neutral-500: #6B7280;
            --neutral-700: #374151;
            --error: #DC2626;
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-6: 24px;
            --space-8: 32px;
            --space-12: 48px;
            --radius-lg: 12px;
            --radius-full: 9999px;
            --transition-base: 0.25s ease;
        }

        .page-hero {
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            padding: 100px var(--space-4) 60px;
            text-align: center;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('public/uploads/features/1776563415_hero.png') center/cover no-repeat;
            opacity: 0.1;
        }
        .page-hero-content {
            position: relative;
            z-index: 1;
            max-width: 700px;
            margin: 0 auto;
        }
        .page-hero h1 {
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 700;
            color: var(--white);
            margin-bottom: var(--space-2);
            letter-spacing: -0.02em;
        }
        .page-hero p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .awards-section {
            padding: var(--space-12) 0;
            background: var(--neutral-100);
        }

        .awards-subtitle {
            text-align: center;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-bottom: var(--space-2);
        }

        .section-title {
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: var(--space-8);
        }

        .awards-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-4);
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 var(--space-4);
        }
        @media (min-width: 640px) {
            .awards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (min-width: 1024px) {
            .awards-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .award-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
            transition: all var(--transition-base);
            border: 1px solid var(--neutral-200);
            position: relative;
            overflow: hidden;
        }
        .award-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #E2B86B);
            transform: scaleX(0);
            transition: transform var(--transition-base);
        }
        .award-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: var(--accent);
        }
        .award-card:hover::before {
            transform: scaleX(1);
        }

        .award-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent) 0%, #E2B86B 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.8rem;
            margin: 0 auto var(--space-3);
            box-shadow: 0 2px 8px rgba(197, 160, 89, 0.3);
        }

        .award-year-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            padding: 2px 10px;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-2);
        }

        .award-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: var(--space-2);
            line-height: 1.4;
        }

        .award-description {
            color: var(--neutral-500);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* Image container inside card */
        .award-image {
            width: 100%;
            height: 160px;
            margin-bottom: var(--space-3);
            overflow: hidden;
            border-radius: var(--radius-lg);
            background: var(--neutral-100);
        }
        .award-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .award-card:hover .award-image img {
            transform: scale(1.02);
        }

        @media (max-width: 640px) {
            .awards-grid { gap: var(--space-3); }
            .award-card { padding: var(--space-4); }
            .award-title { font-size: 0.95rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-hero">
        <div class="page-hero-content">
            <h1>Awards & Distinctions</h1>
            <p>Celebrating excellence and achievement in electronics engineering</p>
        </div>
    </section>

    <section class="awards-section">
        <div class="container">
            <div class="awards-subtitle">Recognition & Excellence</div>
            <h2 class="section-title">Our Achievements</h2>

            <div class="awards-grid">
                <?php
                require_once __DIR__ . '/src/lib/SupabaseClient.php';
                $config = require __DIR__ . '/includes/supabase.php';
                $supabaseClient = new SupabaseClient($config['url'], $config['anon_key']);
                
                try {
                    $awards = $supabaseClient->select('awards_distinctions', null, null, ['award_year' => 'desc']);
                    
                    if (!empty($awards)):
                        foreach ($awards as $award):
                            $imagePath = null;
                            if (!empty($award['image_url'])) {
                                $imagePath = strpos($award['image_url'], 'http') === 0 
                                    ? $award['image_url'] 
                                    : BASE_URL . '/' . ltrim($award['image_url'], '/');
                            }
                ?>
                    <div class="award-card">
                        <?php if (!empty($imagePath)): ?>
                            <div class="award-image">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($award['title']); ?>">
                            </div>
                        <?php else: ?>
                            <div class="award-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($award['award_year'])): ?>
                            <span class="award-year-badge"><?php echo htmlspecialchars($award['award_year']); ?></span>
                        <?php endif; ?>
                        
                        <h3 class="award-title"><?php echo htmlspecialchars($award['title']); ?></h3>
                        
                        <?php if (!empty($award['description'])): ?>
                            <p class="award-description"><?php echo htmlspecialchars($award['description']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php 
                        endforeach;
                    else:
                ?>
                    <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: var(--space-8);">
                        <i class="fas fa-trophy" style="font-size: 2.5rem; color: var(--neutral-300); margin-bottom: var(--space-4);"></i>
                        <p style="color: var(--neutral-500);">No awards have been added yet.</p>
                    </div>
                <?php 
                    endif;
                } catch (Exception $e) {
                ?>
                    <div class="error-state" style="grid-column: 1 / -1; text-align: center; padding: var(--space-8);">
                        <i class="fas fa-exclamation-circle" style="font-size: 2.5rem; color: var(--error); margin-bottom: var(--space-4);"></i>
                        <p style="color: var(--neutral-500);">Unable to load awards at this time. Please try again later.</p>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>