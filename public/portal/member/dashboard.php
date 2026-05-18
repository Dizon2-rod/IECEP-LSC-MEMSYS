<?php
require_once __DIR__ . '/../auth_check.php';

// Load path configuration
require_once __DIR__ . '/../../../includes/paths.php';

// Allow member role
require_role(['member']);

// Set current page for sidebar active state
$current_page = 'dashboard';

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$displayName = $_SESSION['full_name'] ?? $_SESSION['email'] ?? $user['user_metadata']['full_name'] ?? $user['email'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard | IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    
    <style>
        :root {
            --navy: #0B1D4A;
            --navy-light: #1E3A6E;
            --gold: #D4AF37;
            --gold-hover: #B8860B;
            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-400: #94A3B8;
            --slate-600: #475569;
            --slate-900: #0F172A;
            --success: #10B981;
            --warning: #F59E0B;
            --info: #3B82F6;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* --- SIDEBAR STABILITY FIX --- */
        .sidebar { 
            width: 260px !important; 
            position: fixed !important; 
            left: 0; 
            top: 0; 
            bottom: 0; 
            z-index: 1000; 
            transition: none !important; 
        }

        .main-content { 
            margin-left: 260px !important; 
            width: calc(100% - 260px) !important; 
            min-height: 100vh;
            transition: none !important;
            background-color: var(--slate-50);
        }

        /* --- PAGE LAYOUT --- */
        .member-scope {
            font-family: 'Inter', sans-serif;
            padding: 2rem 3rem;
            color: var(--slate-900);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 3rem;
        }

        .welcome-section h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--navy);
            margin: 0;
            letter-spacing: -0.025em;
        }

        .welcome-text {
            color: var(--slate-600);
            font-size: 1rem;
            margin-top: 6px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            color: var(--slate-600);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 12px;
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-sm);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--navy);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--navy);
            margin: 0;
        }

        .stat-content p {
            color: var(--slate-600);
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0;
        }

        /* Welcome Hero Card */
        .hero-card {
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            padding: 2.5rem;
            border-radius: 24px;
            color: white;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .hero-card::after {
            content: '';
            position: absolute;
            right: -50px;
            bottom: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .hero-card h2 {
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 1rem 0;
        }

        .hero-card p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 600px;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        /* Quick Links Grid */
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0.8;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
        }

        .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--slate-200);
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: var(--shadow-sm);
        }

        .action-card:hover {
            background: var(--navy);
            border-color: var(--navy);
            transform: translateY(-2px);
        }

        .action-card:hover h3, 
        .action-card:hover p, 
        .action-card:hover .action-icon {
            color: white !important;
        }

        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--slate-100);
            color: var(--navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .action-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--slate-900);
            margin: 0;
        }

        .action-card p {
            font-size: 0.85rem;
            color: var(--slate-600);
            margin: 0;
            line-height: 1.5;
        }

        @media (max-width: 1024px) {
            .sidebar { width: 80px !important; }
            .main-content { margin-left: 80px !important; width: calc(100% - 80px) !important; }
        }

        @media (max-width: 768px) {
            .member-scope { padding: 1.5rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Unified Sidebar -->
        <?php include_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="member-scope">
                
                <!-- Header Section -->
                <header class="page-header">
                    <div class="welcome-section">
                        <h1>Member Dashboard</h1>
                        <p class="welcome-text">Welcome back, <strong><?php echo htmlspecialchars($displayName); ?></strong></p>
                        <div class="role-badge">
                            <i class="fas fa-id-card"></i> <?php echo $role_display; ?>
                        </div
                    </div>
                </header>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Active</h3>
                            <p>Membership Status</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>2024</h3>
                            <p>Member Since</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(11, 29, 74, 0.1); color: var(--navy);">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>12</h3>
                            <p>Events Attended</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(212, 175, 55, 0.1); color: var(--gold);">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="stat-content">
                            <h3>8</h3>
                            <p>Certificates Earned</p>
                        </div>
                    </div>
                </div>

                <!-- Welcome Hero Card -->
                <div class="hero-card">
                    <h2>Welcome to Your Member Portal</h2>
                    <p>Manage your membership details, update your profile information, and track your affiliation status within the IECEP-LSC community.</p>
                </div>

                <!-- Quick Access Grid -->
                <div class="section-title"><i class="fas fa-rocket"></i> Member Workstation</div>
                <div class="actions-grid">
                    <a href="<?php echo PORTAL_URL; ?>/member/profile.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-user-cog"></i></div>
                        <h3>Update Profile</h3>
                        <p>Keep your personal and contact information up to date.</p>
                    </a>
                    <a href="<?php echo PORTAL_URL; ?>/member/affiliation.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-building"></i></div>
                        <h3>View Affiliation</h3>
                        <p>Check and manage your school or organizational affiliation.</p>
                    </a>
                    <a href="<?php echo PORTAL_URL; ?>/member/certificates.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-certificate"></i></div>
                        <h3>My Certificates</h3>
                        <p>Access and download your earned activity certificates.</p>
                    </a>
                    <a href="<?php echo PORTAL_URL; ?>/member/events.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-calendar-day"></i></div>
                        <h3>Upcoming Events</h3>
                        <p>Stay updated with the latest chapter activities.</p>
                    </a>
                </div
            </div>
        </main>
    </div>
</body>
</html>
