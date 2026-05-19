<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
require_once __DIR__ . '/../auth_check.php';

// Only allow eb_pro_1 (head)
require_role(['eb_pro_1']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$is_head = $user['role'] === 'eb_pro_1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Creatives Committee</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --navy-light: #1e4a8a;
            --gold: #F5A623;
            --gold-dark: #d48f1f;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }
        
        .dashboard-content {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .main-content {
            margin-left: 260px;
        }
        
        .section-header {
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        
        .section-header p {
            color: #94a3b8;
            font-size: 0.875rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--navy);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--navy-light);
        }
        
        .reports-list {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .report-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.15s ease;
        }
        
        .report-item:last-child {
            border-bottom: none;
        }
        
        .report-item:hover {
            background: var(--gray-50);
        }
        
        .report-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: var(--gray-50);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .report-info {
            flex: 1;
        }
        
        .report-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        
        .report-meta {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        .report-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
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
                        <h1>Reports</h1>
                        <p class="welcome-message">View committee activity and performance reports</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary">
                            <i class="fas fa-download"></i>
                            Export Report
                        </button>
                        <div class="user-menu">
                            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="dashboard-content">
                <div class="section-header">
                    <h2>Monthly Reports</h2>
                    <p>View and download committee reports</p>
                </div>
                
                <div class="reports-list">
                    <div class="report-item">
                        <div class="report-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="report-info">
                            <div class="report-title">Activity Report - March 2025</div>
                            <div class="report-meta">PDF • Generated 2 days ago</div>
                        </div>
                        <div class="report-actions">
                            <button class="btn btn-sm btn-outline">View</button>
                            <button class="btn btn-sm btn-outline">Download</button>
                        </div>
                    </div>
                    
                    <div class="report-item">
                        <div class="report-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="report-info">
                            <div class="report-title">Activity Report - February 2025</div>
                            <div class="report-meta">PDF • Generated 1 month ago</div>
                        </div>
                        <div class="report-actions">
                            <button class="btn btn-sm btn-outline">View</button>
                            <button class="btn btn-sm btn-outline">Download</button>
                        </div>
                    </div>
                    
                    <div class="report-item">
                        <div class="report-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="report-info">
                            <div class="report-title">Publication Analytics - Q1 2025</div>
                            <div class="report-meta">PDF • Generated 3 days ago</div>
                        </div>
                        <div class="report-actions">
                            <button class="btn btn-sm btn-outline">View</button>
                            <button class="btn btn-sm btn-outline">Download</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

