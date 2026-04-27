<?php
require_once __DIR__ . '/../auth_check.php';

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(), '', time() - 42000, '/');
    setcookie('PHPSESSID', '', time() - 42000, '/');
    header('Location: /IECEP-LSC-MEMSYS/login.php');
    exit;
}

// Allow eb_president and super_admin to access this page
require_role(['eb_president', 'super_admin']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #334155;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: #0A2F6C;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h3 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li {
            margin-bottom: 5px;
        }

        .nav-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            font-size: 0.95rem;
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.1);
        }

        .nav-menu i {
            width: 20px;
            margin-right: 12px;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #0A2F6C;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header h1::before {
            content: '👑';
            font-size: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #0A2F6C, #1e4a8a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #dc2626;
        }

        .stat-card h3 {
            color: #dc2626;
            font-size: 2.2rem;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .content-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .content-card h2 {
            color: #0A2F6C;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }

        .btn-logout {
            background: #dc2626;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-logout:hover {
            background: #b91c1c;
        }

        .alert {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>IECEP-LSC</h3>
                <p>Super Admin Panel</p>
            </div>
            <ul class="nav-menu">
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#"><i class="fas fa-users-cog"></i> System Management</a></li>
                <li><a href="#"><i class="fas fa-graduation-cap"></i> Schools</a></li>
                <li><a href="#"><i class="fas fa-user-shield"></i> All Users</a></li>
                <li><a href="#"><i class="fas fa-calendar"></i> Events</a></li>
                <li><a href="#"><i class="fas fa-file-invoice"></i> Payments</a></li>
                <li><a href="#"><i class="fas fa-chart-line"></i> Reports</a></li>
                <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                <li style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
                    <a href="?action=logout" style="color: #dc2626;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Super Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                    <button onclick="window.location.href='?action=logout'" class="btn-logout" style="cursor: pointer; border: none; background: none;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>

            <div class="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Super Admin Access:</strong> You have full system control including user management, school administration, and system operations.
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>9</h3>
                    <p>Total Schools</p>
                </div>
                <div class="stat-card">
                    <h3>450+</h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <h3>12</h3>
                    <p>Active Events</p>
                </div>
                <div class="stat-card">
                    <h3>₱250K</h3>
                    <p>Total Transactions</p>
                </div>
            </div>

            <div class="content-card">
                <h2>System Overview</h2>
                <p>As Super Admin, you have complete control over the IECEP-LSC MEMSYS. Monitor all activities, manage user roles, oversee school affiliations, and ensure system integrity.</p>
                
                <h3 style="margin-top: 20px; color: #0A2F6C;">Quick Actions</h3>
                <ul style="margin-left: 20px; line-height: 1.8;">
                    <li>🏫 Manage school affiliations</li>
                    <li>👥 Approve/reject applications</li>
                    <li>📊 Generate system reports</li>
                    <li>⚙️ Configure system settings</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
