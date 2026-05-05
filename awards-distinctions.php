<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Awards & Distinctions - IECEP-LSC MEMSYS</title>
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

        .awards-section {
            padding: var(--space-12) 0;
            background: var(--neutral-100);
        }

        .awards-subtitle {
            text-align: center;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-bottom: var(--space-2);
        }

        .awards-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-6);
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-2);
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
            padding: var(--space-8) var(--space-6);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
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
            height: 4px;
            background: linear-gradient(90deg, var(--accent), #F5A623);
            transform: scaleX(0);
            transition: transform var(--transition-base);
        }
        .award-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }
        .award-card:hover::before {
            transform: scaleX(1);
        }

        .award-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent) 0%, #F5A623 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 2.5rem;
            margin: 0 auto var(--space-4);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }

        .award-year-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: var(--space-3);
        }

        .award-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: var(--space-3);
        }

        .award-description {
            color: var(--neutral-500);
            font-size: 0.95rem;
            line-height: 1.6;
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
                            <div style="width: 100%; height: 200px; margin-bottom: var(--space-4); overflow: hidden; border-radius: var(--radius-lg);">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($award['title']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;">
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
                    <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-8);">
                        <i class="fas fa-trophy" style="font-size: 3rem; color: var(--neutral-300); margin-bottom: var(--space-4);"></i>
                        <p style="color: var(--neutral-500); font-size: 1.1rem;">No awards have been added yet.</p>
                    </div>
                <?php 
                    endif;
                } catch (Exception $e) {
                ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-8);">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--error); margin-bottom: var(--space-4);"></i>
                        <p style="color: var(--neutral-500); font-size: 1.1rem;">Unable to load awards at this time. Please try again later.</p>
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
