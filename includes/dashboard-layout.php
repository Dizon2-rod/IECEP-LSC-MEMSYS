<?php
/**
 * Unified Dashboard Layout Template
 * Used by all role-based dashboards
 */

// Ensure paths are loaded
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../includes/paths.php';
}

/**
 * Render the complete dashboard page
 * 
 * @param array $config Configuration array containing:
 *   - title: Page title
 *   - role: User role (eb_president, admin, member, etc.)
 *   - role_display: Display name for the role
 *   - nav_items: Array of navigation items ['icon', 'label', 'url', 'active']
 *   - content: Main content HTML
 *   - stats: Array of stat cards ['value', 'label', 'color']
 *   - show_alert: Whether to show role-specific alert
 *   - alert_message: Custom alert message (optional)
 */
function renderDashboard(array $config): void {
    $title = $config['title'] ?? 'Dashboard';
    $role = $config['role'] ?? 'member';
    $role_display = $config['role_display'] ?? 'Member';
    $nav_items = $config['nav_items'] ?? [];
    $content = $config['content'] ?? '';
    $stats = $config['stats'] ?? [];
    $show_alert = $config['show_alert'] ?? false;
    $alert_message = $config['alert_message'] ?? '';
    
    // Get user info if function exists
    $user_name = 'User';
    $user_email = '';
    $user_initial = 'U';
    if (function_exists('get_user_info')) {
        $user = get_user_info();
        $user_name = $user['user_metadata']['full_name'] ?? $user['email'] ?? 'User';
        $user_email = $user['email'] ?? '';
        $user_initial = strtoupper(substr($user_name, 0, 1));
    }
    
    // Determine if this is a super admin view
    $is_super = in_array($role, ['eb_president', 'super_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #0B1D4A;
            --navy-light: #1E3A6E;
            --gold: #D4AF37;
            --gold-hover: #B8960C;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --gray-700: #334155;
            --white: #ffffff;
            --danger: #dc2626;
            --danger-light: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
            --info: #3b82f6;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Mobile Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--navy);
            color: var(--white);
            border: none;
            border-radius: var(--radius-md);
            padding: 0.75rem;
            cursor: pointer;
            box-shadow: var(--shadow-md);
        }

        /* Responsive Sidebar */
        .sidebar {
            width: 280px;
            background: var(--navy);
            color: var(--white);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            transform: translateX(0);
        }

        .sidebar.mobile-hidden {
            transform: translateX(-100%);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            padding: 2rem;
        }

        .main-content.sidebar-expanded {
            margin-left: 0;
        }

        
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .sidebar-brand img {
            width: 32px;
            height: auto;
        }

        .sidebar-brand h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            color: var(--white);
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 4px 0 12px;
        }

        .user-role-badge {
            background: rgba(212, 175, 55, 0.2);
            color: var(--gold);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
        }

        .nav-section {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-section h4 {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 20px 12px;
        }

        .nav-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-menu li {
            margin-bottom: 2px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            font-weight: 500;
            border-left: 3px solid transparent;
        }

        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
        }

        .nav-menu a.active {
            background: rgba(212, 175, 55, 0.15);
            color: var(--gold);
            border-left-color: var(--gold);
        }

        .nav-menu i {
            width: 20px;
            text-align: center;
            font-size: 0.9rem;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--gold), var(--gold-hover));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-weight: 700;
            font-size: 0.9rem;
        }

        .user-details {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--white);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            background: rgba(220, 38, 38, 0.2);
            color: var(--danger-light);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: rgba(220, 38, 38, 0.3);
            color: #f87171;
        }

        /* Mobile Menu Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--navy);
            color: var(--white);
            border: none;
            padding: 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            background: var(--white);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header h1 {
            color: var(--navy);
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-greeting {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-greeting span {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--gold);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card h3 {
            color: var(--navy);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-card p {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .stat-card.danger {
            border-left-color: var(--danger);
        }

        .stat-card.danger h3 {
            color: var(--danger);
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-card.success h3 {
            color: var(--success);
        }

        .stat-card.info {
            border-left-color: var(--info);
        }

        .stat-card.info h3 {
            color: var(--info);
        }

        /* Content Card */
        .content-card {
            background: var(--white);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .content-card h2 {
            color: var(--navy);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .content-card h3 {
            color: var(--navy);
            font-size: 1.1rem;
            font-weight: 600;
            margin: 20px 0 12px;
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
        }

        .alert-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }

        .alert-success {
            background: #d1fae5;
            border: 1px solid #6ee7b7;
            color: #065f46;
        }

        .alert i {
            font-size: 1.1rem;
            margin-top: 2px;
        }

        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--navy);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--navy-light);
        }

        .btn-gold {
            background: var(--gold);
            color: var(--navy);
        }

        .btn-gold:hover {
            background: var(--gold-hover);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 80px 20px 20px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.3rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-card {
                padding: 20px;
            }
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        @media (max-width: 768px) {
            .sidebar-overlay.active {
                display: block;
            }
        }

        /* Mobile Responsive Styles */
        @media (max-width: 767.98px) {
            .sidebar-toggle {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .sidebar-overlay {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }
            
            .sidebar-overlay.active {
                opacity: 1;
                visibility: visible;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                padding: 1rem 0;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .alert {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
        
        @media (min-width: 768px) and (max-width: 991.98px) {
            .main-content {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo">
                    <h3>IECEP-LSC</h3>
                </div>
                <p><?php echo htmlspecialchars($role_display); ?> Panel</p>
                <div class="user-role-badge">
                    <i class="fas fa-crown"></i>
                    <?php echo htmlspecialchars($role_display); ?>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <?php foreach ($nav_items as $item): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                           class="<?php echo !empty($item['active']) ? 'active' : ''; ?>">
                            <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($item['label']); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?php echo $user_initial; ?></div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>
                    <?php if ($is_super): ?>👑<?php endif; ?>
                    <?php echo htmlspecialchars($title); ?>
                </h1>
                <div class="page-header-actions">
                    <div id="connection-status" class="connection-status">
                        <i class="fas fa-wifi"></i>
                        <span>Online</span>
                    </div>
                    <div class="user-greeting">
                        <span>Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                    </div>
                </div>
            </div>

            <?php if ($show_alert && $alert_message): ?>
            <div class="alert <?php echo $is_super ? 'alert-warning' : 'alert-info'; ?>">
                <i class="fas <?php echo $is_super ? 'fa-exclamation-triangle' : 'fa-info-circle'; ?>"></i>
                <div>
                    <strong><?php echo $is_super ? 'Super Admin Access:' : 'Welcome!'; ?></strong>
                    <?php echo htmlspecialchars($alert_message); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($stats)): ?>
            <div class="stats-grid">
                <?php foreach ($stats as $stat): ?>
                <div class="stat-card <?php echo !empty($stat['color']) ? htmlspecialchars($stat['color']) : ''; ?>">
                    <h3><?php echo htmlspecialchars($stat['value']); ?></h3>
                    <p><?php echo htmlspecialchars($stat['label']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php echo $content; ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Prevent body scroll when sidebar is open
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
        
        // Close sidebar when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }
            
            // Handle escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar.classList.contains('active')) {
                        toggleSidebar();
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php
}
