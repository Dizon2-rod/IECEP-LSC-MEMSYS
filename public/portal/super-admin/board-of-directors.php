<?php
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
    <title>Board of Directors - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Page Header - Hero Style */
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
        .section-subtitle { 
            text-align: center; 
            color: var(--neutral-500); 
            max-width: 600px; 
            margin: 0 auto var(--space-8); 
            font-size: 1.1rem; 
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

        /* Director Cards Grid */
        .directors-grid { 
            display: grid; 
            grid-template-columns: 1fr; 
            gap: var(--space-6); 
            max-width: 1000px;
            margin: 0 auto;
        }
        @media (min-width: 768px) { 
            .directors-grid { 
                grid-template-columns: repeat(2, 1fr); 
            } 
        }

        /* Director Card */
        .director-card { 
            background: var(--white); 
            border-radius: var(--radius-lg); 
            overflow: hidden; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
            transition: all var(--transition-base); 
            border: 1px solid var(--neutral-200);
            display: flex;
            flex-direction: column;
        }
        .director-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 12px 24px rgba(0,0,0,0.12); 
        }
        .director-image { 
            height: 200px; 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        .director-image::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(11,29,74,0.8) 0%, rgba(30,58,110,0.6) 100%);
        }
        .director-image i { 
            font-size: 4rem; 
            opacity: 0.8;
            position: relative;
            z-index: 1;
        }
        .director-badge {
            position: absolute;
            top: var(--space-3);
            right: var(--space-3);
            background: var(--accent);
            color: var(--primary);
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            z-index: 2;
        }
        .director-content { 
            padding: var(--space-6); 
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .director-title { 
            font-size: 0.875rem; 
            font-weight: 600; 
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-1);
        }
        .director-name { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: var(--neutral-900); 
            margin-bottom: var(--space-3);
        }
        .director-description { 
            color: var(--neutral-500); 
            font-size: 1rem; 
            line-height: 1.6;
            flex: 1;
        }

        /* Leadership Quote Section */
        .leadership-quote {
            background: linear-gradient(135deg, var(--neutral-100) 0%, var(--white) 100%);
            padding: var(--space-8);
            border-radius: var(--radius-lg);
            text-align: center;
            margin-top: var(--space-8);
            border: 1px solid var(--neutral-200);
        }
        .leadership-quote i {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: var(--space-3);
        }
        .leadership-quote p {
            font-size: 1.25rem;
            font-style: italic;
            color: var(--neutral-700);
            max-width: 800px;
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
                        <h1>Board of Directors</h1>
                        <p class="welcome-message">Manage IECEP-LSC Board of Directors</p>
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
            <h1>Board of Directors</h1>
            <p>Meet the visionary leaders guiding IECEP-LSC towards excellence in electronics engineering education and professional development.</p>
        </div>
    </section>
    
    <!-- Board Members Section -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Executive Board 2024-2025</h2>
            <p class="section-subtitle">Strategic leadership and governance driving our mission forward</p>
            
            <div class="directors-grid">
                <!-- President -->
                <div class="director-card">
                    <div class="director-image">
                        <span class="director-badge">President</span>
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="director-content">
                        <div class="director-title">Chief Executive</div>
                        <h3 class="director-name">[President Name]</h3>
                        <p class="director-description">Provides strategic leadership and vision, ensuring the organization achieves its mission in electronics engineering education and professional development.</p>
                    </div>
                </div>
                
                <!-- VP Internal -->
                <div class="director-card">
                    <div class="director-image">
                        <span class="director-badge">VP Internal</span>
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="director-content">
                        <div class="director-title">Internal Operations</div>
                        <h3 class="director-name">[VP Internal Name]</h3>
                        <p class="director-description">Manages internal operations, chapter development, and coordinates activities across all affiliated institutions within IECEP-LSC.</p>
                    </div>
                </div>
                
                <!-- VP External -->
                <div class="director-card">
                    <div class="director-image">
                        <span class="director-badge">VP External</span>
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="director-content">
                        <div class="director-title">External Relations</div>
                        <h3 class="director-name">[VP External Name]</h3>
                        <p class="director-description">Builds strategic partnerships with industry leaders, government agencies, and professional organizations to expand IECEP-LSC's reach.</p>
                    </div>
                </div>
                
                <!-- Treasurer -->
                <div class="director-card">
                    <div class="director-image">
                        <span class="director-badge">Treasurer</span>
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="director-content">
                        <div class="director-title">Financial Management</div>
                        <h3 class="director-name">[Treasurer Name]</h3>
                        <p class="director-description">Oversees financial resources, ensures fiscal responsibility, and manages budgeting for all organizational activities and initiatives.</p>
                    </div>
                </div>
                
                <!-- Secretary -->
                <div class="director-card">
                    <div class="director-image">
                        <span class="director-badge">Secretary</span>
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div class="director-content">
                        <div class="director-title">Documentation & Records</div>
                        <h3 class="director-name">[Secretary Name]</h3>
                        <p class="director-description">Maintains organizational records, ensures effective communication, and documents all board decisions and proceedings.</p>
                    </div>
                </div>
                
                <!-- Auditor -->
                <div class="director-card">
                    <div class="director-image">
                        <span class="director-badge">Auditor</span>
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="director-content">
                        <div class="director-title">Compliance & Audit</div>
                        <h3 class="director-name">[Auditor Name]</h3>
                        <p class="director-description">Ensures transparency, reviews financial records, and verifies compliance with organizational policies and procedures.</p>
                    </div>
                </div>
            </div>
            
            <!-- Leadership Quote -->
            <div class="leadership-quote">
                <i class="fas fa-quote-left"></i>
                <p>"Leadership is not about being in charge. It's about taking care of those in your charge. Together, we build a stronger IECEP-LSC for future generations of electronics engineers."</p>
            </div>
        </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>

