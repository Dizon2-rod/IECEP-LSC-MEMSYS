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
    <title>Former Presidents - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Design Tokens - Matching index.php */
        :root {
            --primary: #0B1D4A;
            --primary-light: #1E3A6E;
            --accent: #D4AF37;
            --accent-hover: #B8960C;
            --neutral-100: #F8FAFC;
            --neutral-200: #E2E8F0;
            --neutral-300: #CBD5E1;
            --neutral-500: #64748B;
            --neutral-700: #334155;
            --neutral-900: #0F172A;
            --white: #FFFFFF;
            --text-dark: #0F172A;
            --space-1: 8px;
            --space-2: 16px;
            --space-3: 24px;
            --space-4: 32px;
            --space-6: 48px;
            --space-8: 64px;
            --space-12: 96px;
            --radius-md: 8px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-full: 9999px;
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --transition-base: 200ms ease-in-out;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--white); 
            color: var(--text-dark); 
            line-height: 1.6; 
        }

        /* Page Hero */
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
            background: url('<?php echo ASSETS_URL; ?>/uploads/features/1776563415_hero.png') center/cover no-repeat;
            opacity: 0.15;
        }
        .page-hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }
        .page-hero h1 { 
            font-size: clamp(2rem, 5vw, 3rem); 
            font-weight: 800; 
            margin-bottom: var(--space-3);
            letter-spacing: -0.02em;
        }
        .page-hero p { 
            font-size: 1.25rem; 
            opacity: 0.9; 
            max-width: 600px;
            margin: 0 auto;
        }

        /* Section Title */
        .section-title { 
            font-size: clamp(1.75rem, 4vw, 2.5rem); 
            font-weight: 800; 
            color: var(--primary); 
            text-align: center; 
            margin-bottom: var(--space-2); 
            position: relative; 
        }
        .section-title::after { 
            content: ''; 
            display: block; 
            width: 60px; 
            height: 4px; 
            background: linear-gradient(90deg, var(--accent), #F5A623); 
            margin: var(--space-2) auto 0; 
            border-radius: var(--radius-md); 
        }

        /* Container */
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 var(--space-2); 
        }
        @media (min-width: 768px) { 
            .container { padding: 0 var(--space-4); } 
        }

        /* Section */
        .section { 
            padding: var(--space-12) 0; 
        }

        /* Timeline */
        .timeline { 
            position: relative; 
            padding: var(--space-4) 0; 
        }
        .timeline::before { 
            content: ''; 
            position: absolute; 
            left: 50%; 
            transform: translateX(-50%); 
            width: 3px; 
            height: 100%; 
            background: linear-gradient(180deg, var(--accent), var(--primary-light)); 
            border-radius: var(--radius-full);
        }
        .timeline-item { 
            position: relative; 
            margin-bottom: var(--space-8); 
        }
        .timeline-item:nth-child(odd) .timeline-content { 
            margin-left: 0; 
            margin-right: 55%; 
            text-align: right; 
        }
        .timeline-item:nth-child(even) .timeline-content { 
            margin-left: 55%; 
            margin-right: 0; 
            text-align: left; 
        }
        .timeline-dot { 
            position: absolute; 
            left: 50%; 
            transform: translateX(-50%); 
            width: 24px; 
            height: 24px; 
            background: var(--accent); 
            border-radius: 50%; 
            border: 4px solid var(--white); 
            box-shadow: var(--shadow-md);
            z-index: 2;
        }
        .timeline-content { 
            background: var(--white); 
            padding: var(--space-6); 
            border-radius: var(--radius-lg); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
            border: 1px solid var(--neutral-200);
            transition: all var(--transition-base);
        }
        .timeline-content:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .timeline-year { 
            color: var(--accent); 
            font-weight: 700; 
            font-size: 1rem; 
            margin-bottom: var(--space-1); 
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .timeline-name { 
            color: var(--primary); 
            font-weight: 700; 
            font-size: 1.5rem; 
            margin-bottom: var(--space-2);
        }
        .timeline-description {
            color: var(--neutral-500);
            font-size: 1rem;
            line-height: 1.6;
        }
        .timeline-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            margin-bottom: var(--space-3);
        }
        .timeline-item:nth-child(odd) .timeline-icon {
            margin-left: auto;
        }
        @media (max-width: 768px) {
            .timeline::before { left: 20px; }
            .timeline-dot { left: 20px; }
            .timeline-item:nth-child(odd) .timeline-content,
            .timeline-item:nth-child(even) .timeline-content { 
                margin-left: 60px; 
                margin-right: 0; 
                text-align: left; 
            }
            .timeline-item:nth-child(odd) .timeline-icon {
                margin-left: 0;
            }
        }

        /* Legacy Banner */
        .legacy-banner {
            background: linear-gradient(135deg, var(--neutral-100) 0%, var(--white) 100%);
            padding: var(--space-8);
            border-radius: var(--radius-lg);
            text-align: center;
            margin-top: var(--space-8);
            border: 1px solid var(--neutral-200);
        }
        .legacy-banner i {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: var(--space-3);
        }
        .legacy-banner h3 {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: var(--space-2);
        }
        .legacy-banner p {
            color: var(--neutral-500);
            max-width: 700px;
            margin: 0 auto;
        }
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
                        <h1>Former Presidents</h1>
                        <p class="welcome-message">IECEP-LSC Former Presidents and Leadership History</p>
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
            <h1>Former Presidents</h1>
            <p>Honoring the visionary leaders who shaped IECEP-LSC's journey and laid the foundation for our continued success.</p>
        </div>
    </section>
    
    <!-- Timeline Section -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Legacy of Leadership</h2>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-icon"><i class="fas fa-crown"></i></div>
                        <div class="timeline-year">2023-2024</div>
                        <div class="timeline-name">[Recent Former President]</div>
                        <p class="timeline-description">Continued to expand membership and strengthen industry partnerships, leading IECEP-LSC to new heights of excellence.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-icon"><i class="fas fa-crown"></i></div>
                        <div class="timeline-year">2022-2023</div>
                        <div class="timeline-name">[Former President Name]</div>
                        <p class="timeline-description">Led the organization through challenging times with resilience, innovation, and unwavering commitment to member welfare.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-icon"><i class="fas fa-crown"></i></div>
                        <div class="timeline-year">2021-2022</div>
                        <div class="timeline-name">[Former President Name]</div>
                        <p class="timeline-description">Pioneered digital transformation initiatives, modernizing organizational processes and expanding online engagement.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-icon"><i class="fas fa-crown"></i></div>
                        <div class="timeline-year">2020-2021</div>
                        <div class="timeline-name">[Former President Name]</div>
                        <p class="timeline-description">Navigated the organization through the unprecedented challenges of the pandemic era with adaptive leadership.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-icon"><i class="fas fa-crown"></i></div>
                        <div class="timeline-year">2019-2020</div>
                        <div class="timeline-name">[Former President Name]</div>
                        <p class="timeline-description">Established foundational partnerships with educational institutions across Laguna, setting the stage for future growth.</p>
                    </div>
                </div>
            </div>
            
            <!-- Legacy Banner -->
            <div class="legacy-banner">
                <i class="fas fa-quote-left"></i>
                <h3>A Legacy of Excellence</h3>
                <p>Each president has contributed uniquely to IECEP-LSC's growth, leaving behind a legacy of dedication, innovation, and service. Their collective leadership has shaped us into the organization we are today.</p>
            </div>
        </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>

