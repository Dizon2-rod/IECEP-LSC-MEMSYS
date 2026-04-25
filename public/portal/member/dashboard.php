<?php
require_once __DIR__ . '/../auth_check.php';

// Allow member role
require_role(['member']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - IECEP-LSC MEMSYS</title>
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
            width: 250px;
            background: #0A2F6C;
            color: white;
            padding: 20px 0;
        }

        .sidebar-header {
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h3 {
            font-size: 1.2rem;
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
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.1);
        }

        .nav-menu i {
            width: 20px;
            margin-right: 10px;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #0A2F6C;
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #F5A623;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0A2F6C;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #F5A623;
        }

        .stat-card h3 {
            color: #0A2F6C;
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .content-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .content-card h2 {
            color: #0A2F6C;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>IECEP-LSC</h3>
                <p>Member Portal</p>
            </div>
            <ul class="nav-menu">
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="#"><i class="fas fa-calendar"></i> Events</a></li>
                <li><a href="#"><i class="fas fa-file-invoice"></i> Payments</a></li>
                <li><a href="#"><i class="fas fa-certificate"></i> Certificates</a></li>
                <li><a href="#"><i class="fas fa-bullhorn"></i> Announcements</a></li>
                <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Member Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                    <form method="POST" action="/login.php" style="display: inline;">
                        <button type="submit" name="logout" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Active</h3>
                    <p>Membership Status</p>
                </div>
                <div class="stat-card">
                    <h3>5</h3>
                    <p>Events Attended</p>
                </div>
                <div class="stat-card">
                    <h3>2</h3>
                    <p>Upcoming Events</p>
                </div>
                <div class="stat-card">
                    <h3>Paid</h3>
                    <p>Payment Status</p>
                </div>
            </div>

            <div class="content-card">
                <h2>Welcome to Member Dashboard</h2>
                <p>Here you can manage your profile, view upcoming events, check payment status, and access member-exclusive content.</p>
            </div>
        </div>
    </div>
</body>
</html>
