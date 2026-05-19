<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
require_once __DIR__ . '/../auth_check.php';
require_role(['eb_president', 'super_admin']);

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';

// Load Supabase credentials
$supabaseConfig = require __DIR__ . '/../../../includes/supabase.php';

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Awards & Distinctions - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #0B1D4A; --primary-light: #1E3A6E; --accent: #D4AF37; --accent-hover: #B8960C;
            --neutral-100: #F8FAFC; --neutral-200: #E2E8F0; --neutral-500: #64748B; --neutral-900: #0F172A;
            --white: #FFFFFF; --gold: #FFD700; --silver: #C0C0C0; --bronze: #CD7F32;
            --space-1: 8px; --space-2: 16px; --space-3: 24px; --space-4: 32px; --space-6: 48px; --space-8: 64px; --space-12: 96px;
            --radius-md: 8px; --radius-lg: 16px; --radius-full: 9999px;
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1); --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --transition-base: 200ms ease-in-out;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background: var(--white); color: var(--neutral-900); line-height: 1.6; }
        
        /* Page Hero */
        .page-hero { position: relative; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary) 100%); color: var(--white); padding: 140px var(--space-4) var(--space-12); text-align: center; overflow: hidden; }
        .page-hero::before { content: ''; position: absolute; inset: 0; background: url('<?php echo ASSETS_URL; ?>/uploads/features/1776563415_hero.png') center/cover no-repeat; opacity: 0.15; }
        .page-hero-content { position: relative; z-index: 1; max-width: 800px; margin: 0 auto; }
        .page-hero h1 { font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; margin-bottom: var(--space-3); letter-spacing: -0.02em; }
        .page-hero p { font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto; }

        /* Section Title */
        .section-title { font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 800; color: var(--primary); text-align: center; margin-bottom: var(--space-2); position: relative; }
        .section-title::after { content: ''; display: block; width: 60px; height: 4px; background: linear-gradient(90deg, var(--accent), #F5A623); margin: var(--space-2) auto 0; border-radius: var(--radius-md); }
        .section-subtitle { text-align: center; color: var(--neutral-500); max-width: 600px; margin: 0 auto var(--space-8); font-size: 1.1rem; }

        /* Container */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 var(--space-2); }
        @media (min-width: 768px) { .container { padding: 0 var(--space-4); } }

        /* Section */
        .section { padding: var(--space-12) 0; }

        /* Stats */
        .stats-bar { display: flex; justify-content: center; gap: var(--space-8); margin: var(--space-8) 0; flex-wrap: wrap; }
        .stat-item { text-align: center; }
        .stat-number { font-size: 3rem; font-weight: 800; color: var(--primary); line-height: 1; }
        .stat-label { color: var(--neutral-500); font-size: 0.9rem; margin-top: var(--space-1); }

        /* Awards Grid */
        .awards-grid { display: grid; grid-template-columns: 1fr; gap: var(--space-6); }
        @media (min-width: 640px) { .awards-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .awards-grid { grid-template-columns: repeat(3, 1fr); } }

        /* Award Card */
        .award-card { background: var(--white); border-radius: var(--radius-lg); padding: var(--space-6); box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; transition: all var(--transition-base); border: 1px solid var(--neutral-200); position: relative; overflow: hidden; }
        .award-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--accent), #F5A623); transform: scaleX(0); transition: transform var(--transition-base); }
        .award-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .award-card:hover::before { transform: scaleX(1); }
        .award-icon { font-size: 3.5rem; margin-bottom: var(--space-4); }
        .award-gold .award-icon { color: var(--gold); filter: drop-shadow(0 2px 4px rgba(255,215,0,0.3)); }
        .award-silver .award-icon { color: var(--silver); filter: drop-shadow(0 2px 4px rgba(192,192,192,0.3)); }
        .award-bronze .award-icon { color: var(--bronze); filter: drop-shadow(0 2px 4px rgba(205,127,50,0.3)); }
        .award-badge { display: inline-block; background: linear-gradient(135deg, var(--accent) 0%, #F5A623 100%); color: var(--primary); padding: var(--space-1) var(--space-3); border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-3); }
        .award-title { color: var(--primary); font-weight: 700; font-size: 1.25rem; margin-bottom: var(--space-2); }
        .award-recipient { font-weight: 600; color: var(--neutral-900); font-size: 1.1rem; margin-bottom: var(--space-1); }
        .award-year { color: var(--accent); font-weight: 700; font-size: 0.875rem; margin-bottom: var(--space-3); }
        .award-description { color: var(--neutral-500); font-size: 0.95rem; line-height: 1.6; }

        /* CTA */
        .cta-section { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); padding: var(--space-8) var(--space-4); border-radius: var(--radius-lg); text-align: center; margin-top: var(--space-8); }
        .cta-section h3 { color: var(--white); font-size: 1.75rem; font-weight: 700; margin-bottom: var(--space-3); }
        .cta-section p { color: rgba(255,255,255,0.9); max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Awards & Distinctions</h1>
                        <p class="welcome-message">Manage IECEP-LSC Awards and Recognitions</p>
                    </div>
                    <div class="header-actions">
                        <div class="user-menu">
                            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
            <h1>Awards & Distinctions</h1>
            <p>Celebrating excellence, innovation, and outstanding achievements within IECEP-LSC and the broader electronics engineering community.</p>
        </div>
    </section>
    
    <!-- Awards Section -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Excellence Recognized</h2>
            <p class="section-subtitle">Honoring those who have made exceptional contributions to our field</p>
            
            <!-- Stats -->
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Awards Given</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">25+</div>
                    <div class="stat-label">Distinguished Members</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">10</div>
                    <div class="stat-label">Years of Excellence</div>
                </div>
            </div>
            
            <div class="awards-grid">
                <!-- Outstanding Chapter -->
                <div class="award-card award-gold">
                    <i class="fas fa-trophy award-icon"></i>
                    <span class="award-badge">Chapter Award</span>
                    <h3 class="award-title">Most Outstanding Chapter</h3>
                    <div class="award-recipient">IECEP-LSC Laguna Chapter</div>
                    <div class="award-year">2024</div>
                    <p class="award-description">Recognized for exceptional leadership, member engagement, and significant community impact.</p>
                </div>
                
                <!-- Innovation Award -->
                <div class="award-card award-silver">
                    <i class="fas fa-lightbulb award-icon"></i>
                    <span class="award-badge">Innovation</span>
                    <h3 class="award-title">Excellence in Innovation</h3>
                    <div class="award-recipient">Technical Projects Division</div>
                    <div class="award-year">2024</div>
                    <p class="award-description">For groundbreaking projects and technological advancements that pushed the boundaries.</p>
                </div>
                
                <!-- Community Outreach -->
                <div class="award-card award-bronze">
                    <i class="fas fa-hands-helping award-icon"></i>
                    <span class="award-badge">Service</span>
                    <h3 class="award-title">Best Community Outreach</h3>
                    <div class="award-recipient">Public Relations Team</div>
                    <div class="award-year">2024</div>
                    <p class="award-description">Outstanding commitment to community service, public engagement, and professional development.</p>
                </div>
                
                <!-- Student Member -->
                <div class="award-card">
                    <i class="fas fa-star award-icon" style="color: var(--primary);"></i>
                    <span class="award-badge">Individual</span>
                    <h3 class="award-title">Outstanding Student Member</h3>
                    <div class="award-recipient">[Student Name]</div>
                    <div class="award-year">2024</div>
                    <p class="award-description">Exceptional contributions to chapter activities, academic excellence, and peer mentorship.</p>
                </div>
                
                <!-- Innovator -->
                <div class="award-card">
                    <i class="fas fa-rocket award-icon" style="color: var(--accent);"></i>
                    <span class="award-badge">Innovation</span>
                    <h3 class="award-title">Innovator of the Year</h3>
                    <div class="award-recipient">[Member Name]</div>
                    <div class="award-year">2024</div>
                    <p class="award-description">For developing innovative solutions and demonstrating creative problem-solving.</p>
                </div>
                
                <!-- Leadership -->
                <div class="award-card">
                    <i class="fas fa-crown award-icon" style="color: var(--primary-light);"></i>
                    <span class="award-badge">Leadership</span>
                    <h3 class="award-title">Leadership Excellence</h3>
                    <div class="award-recipient">[Officer Name]</div>
                    <div class="award-year">2024</div>
                    <p class="award-description">Demonstrating exceptional leadership, organizational skills, and dedication.</p>
                </div>
            </div>
            
            <!-- CTA -->
            <div class="cta-section">
                <h3>Nominate Excellence</h3>
                <p>Know someone who deserves recognition? Nominations for next year's awards are now open.</p>
            </div>
        </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>

