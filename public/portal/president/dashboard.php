<?php
/**
 * President Dashboard - Uses Dynamic Sidebar
 */

require_once __DIR__ . '/../auth_check.php';
require_role(['president', 'eb_president', 'admin', 'super_admin']);

require_once __DIR__ . '/../../../includes/paths.php';

$user = get_user_info();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>President Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content main-content-with-sidebar">
            <header class="dashboard-header">
                <h1>President Dashboard</h1>
                <p>Welcome, <?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'President'); ?></p>
            </header>
            
            <div class="dashboard-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3>0</h3>
                            <p>Total Members</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3>0</h3>
                            <p>Affiliated Schools</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="stat-content">
                            <h3>0</h3>
                            <p>Upcoming Events</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body" style="padding: 40px; text-align: center;">
                        <i class="fas fa-crown" style="font-size: 3rem; color: var(--accent); margin-bottom: 20px;"></i>
                        <h3>President Portal</h3>
                        <p style="color: var(--neutral-600);">Welcome to the President Dashboard. Use the sidebar to navigate to different sections.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
