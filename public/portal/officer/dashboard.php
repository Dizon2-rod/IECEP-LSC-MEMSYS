<?php
require_once __DIR__ . '/../bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../includes/config.php';
$current_page = basename(__FILE__, '.php');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Role check
if ($_SESSION['role'] !== 'school_officer') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userName = $_SESSION['user']['name'] ?? $_SESSION['full_name'] ?? 'Officer';
$userEmail = $_SESSION['user']['email'] ?? $_SESSION['email'] ?? '';
$schoolName = $_SESSION['user']['school_name'] ?? 'Your School';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Officer Dashboard | IECEP-LSC MEMSYS</title>
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
        .officer-scope {
            font-family: 'Inter', sans-serif;
            padding: 2rem 3rem;
            color: var(--slate-900);
        }

        /* Header Section */
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

        .school-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: var(--navy);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 12px;
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-sm);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 16px;
            background: white;
            border-radius: 50px;
            border: 1px solid var(--slate-200);
            transition: var(--transition);
            cursor: pointer;
        }

        .user-profile:hover {
            border-color: var(--navy);
            background: var(--slate-50);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--slate-100);
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--slate-700);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

        /* Quick Actions */
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
            margin-bottom: 3rem;
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

        /* Recent Activity */
        .activity-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .activity-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--slate-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--slate-50);
            transition: var(--transition);
        }

        .activity-item:last-child { border-bottom: none; }
        .activity-item:hover { background: var(--slate-50); }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.85rem;
        }

        .activity-text {
            flex: 1;
            font-size: 0.875rem;
            color: var(--slate-600);
        }
        .activity-text strong { color: var(--navy); font-weight: 600; }
        .activity-time { font-size: 0.75rem; color: var(--slate-400); }

        @media (max-width: 1024px) {
            .sidebar { width: 80px !important; }
            .main-content { margin-left: 80px !important; width: calc(100% - 80px) !important; }
        }

        @media (max-width: 768px) {
            .officer-scope { padding: 1.5rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Unified Sidebar -->
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="officer-scope">
                
                <!-- Header Section -->
                <header class="page-header">
                    <div class="welcome-section">
                        <h1>School Officer Dashboard</h1>
                        <p class="welcome-text">Welcome back, <strong><?php echo htmlspecialchars($userName); ?></strong></p>
                        <div class="school-badge">
                            <i class="fas fa-school"></i> 
                            <?php echo htmlspecialchars($schoolName); ?>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="user-profile">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/default-avatar.png" alt="User Avatar" class="user-avatar">
                            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: var(--slate-400);"></i>
                        </div>
                    </div>
                </header>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(11, 29, 74, 0.1); color: var(--navy);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3>--</h3>
                            <p>Total Members</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>--</h3>
                            <p>Attendance Rate</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(212, 175, 55, 0.1); color: var(--gold);">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>--</h3>
                            <p>Compliance Rate</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-content">
                            <h3>₱0</h3>
                            <p>Fees Collected</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="section-title"><i class="fas fa-rocket"></i> Quick Actions</div>
                <div class="actions-grid">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/members.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-users"></i></div>
                        <h3>Manage Members</h3>
                        <p>View and manage school members profiles and status.</p>
                    </a>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/attendance.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                        <h3>Track Attendance</h3>
                        <p>Record and view member attendance for activities.</p>
                    </a>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/compliance.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                        <h3>Compliance Status</h3>
                        <p>Monitor school compliance with chapter requirements.</p>
                    </a>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/profile.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-school"></i></div>
                        <h3>School Profile</h3>
                        <p>Update and manage official school information.</p>
                    </a>
                </div>

                <!-- Recent Activity -->
                <div class="section-title"><i class="fas fa-history"></i> Recent School Activity</div>
                <div class="activity-card">
                    <div class="activity-header">
                        <span style="font-weight: 600; color: var(--navy);">Latest Updates</span>
                        <a href="#" style="font-size: 0.75rem; color: var(--navy); text-decoration: none; font-weight: 600;">View All</a>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: rgba(11, 29, 74, 0.1); color: var(--navy);">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="activity-text">
                            <p><strong>No recent activity</strong> found for your school.</p>
                            <span class="activity-time">Start managing your school to see updates here.</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
