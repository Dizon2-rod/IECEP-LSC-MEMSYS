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
    <title>IECEP Officers - IECEP-LSC MEMSYS</title>
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

        /* Officers Grid */
        .officers-grid { 
            display: grid; 
            grid-template-columns: 1fr; 
            gap: var(--space-6); 
        }
        @media (min-width: 640px) { 
            .officers-grid { grid-template-columns: repeat(2, 1fr); } 
        }
        @media (min-width: 1024px) { 
            .officers-grid { grid-template-columns: repeat(3, 1fr); } 
        }

        /* Officer Card - Premium Style */
        .officer-card { 
            background: var(--white); 
            border-radius: var(--radius-lg); 
            padding: var(--space-6); 
            text-align: center; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
            transition: all var(--transition-base);
            border: 1px solid var(--neutral-200);
            position: relative;
            overflow: hidden;
        }
        .officer-card::before {
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
        .officer-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 12px 24px rgba(0,0,0,0.12); 
        }
        .officer-card:hover::before {
            transform: scaleX(1);
        }
        .officer-avatar { 
            width: 120px; 
            height: 120px; 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); 
            border-radius: 50%; 
            margin: 0 auto var(--space-4); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: var(--white); 
            font-size: 3rem;
            position: relative;
            box-shadow: 0 4px 15px rgba(11, 29, 74, 0.3);
        }
        .officer-avatar::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            opacity: 0;
            transition: opacity var(--transition-base);
        }
        .officer-card:hover .officer-avatar::after {
            opacity: 1;
        }
        .officer-role {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent) 0%, #F5A623 100%);
            color: var(--primary);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-2);
        }
        .officer-name { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: var(--neutral-900);
            margin-bottom: var(--space-2);
        }
        .officer-description {
            color: var(--neutral-500);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Stats Bar */
        .stats-bar { 
            display: flex; 
            justify-content: center; 
            gap: var(--space-8); 
            margin: var(--space-8) 0; 
            flex-wrap: wrap; 
        }
        .stat-item { 
            text-align: center; 
        }
        .stat-number { 
            font-size: 3rem; 
            font-weight: 800; 
            color: var(--primary); 
            line-height: 1;
        }
        .stat-label { 
            color: var(--neutral-500); 
            font-size: 0.9rem; 
            margin-top: var(--space-1);
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: var(--space-8) var(--space-4);
            border-radius: var(--radius-lg);
            text-align: center;
            margin-top: var(--space-8);
        }
        .cta-section h3 {
            color: var(--white);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: var(--space-3);
        }
        .cta-section p {
            color: rgba(255,255,255,0.9);
            max-width: 600px;
            margin: 0 auto var(--space-4);
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            background: var(--accent);
            color: var(--primary);
            padding: var(--space-3) var(--space-6);
            border-radius: var(--radius-full);
            text-decoration: none;
            font-weight: 700;
            transition: all var(--transition-base);
        }
        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
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
                        <h1>IECEP Officers</h1>
                        <p class="welcome-message">Manage IECEP-LSC Officers information</p>
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
                <h2>Leadership Team 2024-2025</h2>
                <p>Committed to excellence in service and professional development</p>

                <!-- Stats -->
                <div class="stats-bar">
                    <div class="stat-item">
                        <div class="stat-number">15+</div>
                        <div class="stat-label">Active Officers</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">8</div>
                        <div class="stat-label">School Chapters</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Members Served</div>
                    </div>
                </div>

                <div class="officers-grid">
                    <!-- President -->
                    <div class="officer-card">
                        <div class="officer-avatar"><i class="fas fa-crown"></i></div>
                        <span class="officer-role">President</span>
                        <h3 class="officer-name">[President Name]</h3>
                        <p class="officer-description">Leading with vision and strategic direction, ensuring organizational growth and member welfare.</p>
                    </div>

                    <!-- VP Internal -->
                    <div class="officer-card">
                        <div class="officer-avatar"><i class="fas fa-users-cog"></i></div>
                        <span class="officer-role">VP Internal</span>
                        <h3 class="officer-name">[VP Internal Name]</h3>
                        <p class="officer-description">Coordinating internal affairs and fostering collaboration among all affiliated institutions.</p>
                    </div>

                    <!-- VP External -->
                    <div class="officer-card">
                        <div class="officer-avatar"><i class="fas fa-handshake"></i></div>
                        <span class="officer-role">VP External</span>
                    <h3 class="officer-name">[VP External Name]</h3>
                    <p class="officer-description">Building bridges with industry partners and expanding IECEP-LSC's professional network.</p>
                </div>
                
                <!-- Treasurer -->
                <div class="officer-card">
                    <div class="officer-avatar"><i class="fas fa-chart-line"></i></div>
                    <span class="officer-role">Treasurer</span>
                    <h3 class="officer-name">[Treasurer Name]</h3>
                    <p class="officer-description">Managing resources with integrity and ensuring sustainable financial operations.</p>
                </div>
                
                <!-- Secretary -->
                <div class="officer-card">
                    <div class="officer-avatar"><i class="fas fa-file-signature"></i></div>
                    <span class="officer-role">Secretary</span>
                    <h3 class="officer-name">[Secretary Name]</h3>
                    <p class="officer-description">Maintaining accurate records and facilitating seamless organizational communication.</p>
                </div>
                
                <!-- PRO -->
                <div class="officer-card">
                    <div class="officer-avatar"><i class="fas fa-bullhorn"></i></div>
                    <span class="officer-role">PRO</span>
                    <h3 class="officer-name">[PRO Name]</h3>
                    <p class="officer-description">Amplifying our message and showcasing IECEP-LSC achievements to the world.</p>
                </div>
                
                <!-- Auditor -->
                <div class="officer-card">
                    <div class="officer-avatar"><i class="fas fa-balance-scale"></i></div>
                    <span class="officer-role">Auditor</span>
                    <h3 class="officer-name">[Auditor Name]</h3>
                    <p class="officer-description">Ensuring transparency and accountability in all organizational processes.</p>
                </div>
                
                <!-- Business Manager -->
                <div class="officer-card">
                    <div class="officer-avatar"><i class="fas fa-briefcase"></i></div>
                    <span class="officer-role">Business Manager</span>
                    <h3 class="officer-name">[Business Manager Name]</h3>
                    <p class="officer-description">Driving innovative projects and sustainable initiatives for organizational growth.</p>
                </div>
                
                <!-- Creatives Head -->
                <div class="officer-card">
                    <div class="officer-avatar"><i class="fas fa-palette"></i></div>
                    <span class="officer-role">Creatives Head</span>
                    <h3 class="officer-name">[Creatives Head Name]</h3>
                    <p class="officer-description">Crafting compelling visuals and creative content that represents our brand.</p>
                </div>
            </div>
            
            <!-- CTA -->
            <div class="cta-section">
                <h3>Want to Join Our Team?</h3>
                <p>We're always looking for passionate individuals who want to make a difference in the electronics engineering community.</p>
                <a href="<?php echo BASE_URL; ?>/contact-submit.php" class="btn-primary">
                    <i class="fas fa-envelope"></i> Contact Us
                </a>
            </div>
        </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>

