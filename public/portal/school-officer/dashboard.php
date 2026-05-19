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
    <title>School Officer Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --navy-light: #1e4a8a;
            --gold: #F5A623;
            --gold-dark: #d48f1f;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
            margin-left: 260px;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 767.98px) {
            .main-content {
                margin-left: 0;
            }
        }
        .dashboard-header {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .header-content h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 0.25rem;
        }
        .welcome-message {
            color: var(--gray-500);
            font-size: 0.95rem;
        }
        .school-info {
            color: var(--navy-light);
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-700);
            font-weight: 500;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        .dashboard-content {
            padding: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        .stat-change {
            font-size: 0.8rem;
            font-weight: 600;
        }
        .stat-change.positive { color: var(--success); }
        .quick-actions {
            margin-bottom: 3rem;
        }
        .quick-actions h2, .recent-activity h2, .upcoming-events h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: var(--navy);
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }
        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
            border-color: var(--navy);
        }
        .action-icon {
            font-size: 1.5rem;
            color: var(--navy);
            margin-bottom: 0.75rem;
        }
        .action-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .action-card p {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        .activity-list, .events-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .activity-time {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        .event-item {
            display: flex;
            gap: 1rem;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .event-date {
            background: var(--navy);
            color: white;
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 700;
            min-width: 50px;
        }
        .event-date .date { font-size: 1.5rem; line-height: 1; }
        .event-date .month { font-size: 0.8rem; text-transform: uppercase; }
        .event-content h3 { font-size: 1rem; font-weight: 600; }
        .event-content p { font-size: 0.85rem; color: var(--gray-500); }
        .btn {
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-outline {
            background: white;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }
        .btn-primary {
            background: var(--navy);
            color: white;
        }
        .btn-sm { padding: 0.4rem 1rem; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Unified Sidebar -->
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>School Officer Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($userName); ?></p>
                        <p class="school-info"><?php echo htmlspecialchars($schoolName); ?></p>
                    </div>
                    <div class="header-actions">
                        <div class="user-menu">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/default-avatar.png" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($userName); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <!-- Stats Cards (placeholder – replace with real data from Supabase) -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3>--</h3>
                            <p>Total Members</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>--</h3>
                            <p>Attendance Rate</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>--</h3>
                            <p>Compliance Rate</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-content">
                            <h3>₱0</h3>
                            <p>Fees Collected</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/members.php" class="action-card">
                            <div class="action-icon"><i class="fas fa-users"></i></div>
                            <h3>Manage Members</h3>
                            <p>View and manage school members</p>
                        </a>
                        <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/attendance.php" class="action-card">
                            <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                            <h3>Track Attendance</h3>
                            <p>Record and view attendance</p>
                        </a>
                        <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/compliance.php" class="action-card">
                            <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                            <h3>Compliance Status</h3>
                            <p>Monitor school compliance</p>
                        </a>
                        <a href="/IECEP-LSC-MEMSYS/public/portal/school-officer/profile.php" class="action-card">
                            <div class="action-icon"><i class="fas fa-school"></i></div>
                            <h3>School Profile</h3>
                            <p>Update school information</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity (placeholder) -->
                <div class="recent-activity">
                    <h2>Recent School Activity</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>No recent activity</strong></p>
                                <span class="activity-time">Start managing your school</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>