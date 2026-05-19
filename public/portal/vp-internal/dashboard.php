<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * VP Internal Dashboard - Uses Dynamic Sidebar
 */

require_once __DIR__ . '/../auth_check.php';
require_role(['vp_internal', 'admin', 'super_admin']);

require_once __DIR__ . '/../../../includes/paths.php';

$user = get_user_info();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VP Internal Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content main-content-with-sidebar">
            <header class="dashboard-header">
                <h1>VP Internal Dashboard</h1>
                <p>Welcome, <?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'VP Internal'); ?></p>
            </header>
            
            <div class="dashboard-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-school"></i>
                        </div>
                        <div class="stat-content">
                            <h3>0</h3>
                            <p>Active Chapters</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <div class="stat-content">
                            <h3>0</h3>
                            <p>Development Projects</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body" style="padding: 40px; text-align: center;">
                        <i class="fas fa-users" style="font-size: 3rem; color: var(--accent); margin-bottom: 20px;"></i>
                        <h3>VP Internal Portal</h3>
                        <p style="color: var(--neutral-600);">Welcome to the VP Internal Dashboard. Use the sidebar to navigate to different sections.</p>
                        <a href="<?php echo BASE_PUBLIC_URL; ?>/portal/vp-internal/chapter-development.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-seedling"></i> Chapter Development
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
