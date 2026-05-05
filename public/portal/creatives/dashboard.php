<?php
require_once __DIR__ . '/../auth_check.php';

// Allow eb_pro_1 (head) and committee_creatives
require_role(['eb_pro_1', 'committee_creatives']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$is_head = $user['role'] === 'eb_pro_1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creatives Dashboard - IECEP-LSC MEMSYS</title>
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
            padding: 48px;
            max-width: 1400px;
            margin: 0 auto;
            background: var(--gray-50);
            min-height: calc(100vh - 72px);
        }
        
        .main-content {
            margin-left: 260px;
            background: var(--gray-50);
        }
        
        .section-header {
            margin-bottom: 32px;
        }
        
        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .section-header p {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 400;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 48px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card:hover {
            border-color: var(--navy);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 16px;
        }
        
        .stat-content h3 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        
        .stat-content p {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 8px;
        }
        
        .stat-change {
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stat-change.positive {
            color: #10b981;
        }
        
        .stat-change.neutral {
            color: #f59e0b;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 48px;
        }
        
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .action-card:hover {
            border-color: var(--navy);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }
        
        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: var(--gray-50);
            color: var(--navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 12px;
        }
        
        .action-card h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        
        .action-card p {
            font-size: 0.75rem;
            color: #94a3b8;
            line-height: 1.4;
        }
        
        .activity-list {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.15s ease;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background: var(--gray-50);
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content p {
            color: var(--gray-900);
            font-size: 0.875rem;
            margin-bottom: 2px;
        }
        
        .activity-content strong {
            color: var(--navy);
            font-weight: 600;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 640px) {
            .stats-grid,
            .actions-grid {
                grid-template-columns: 1fr;
            }
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
                        <h1>Creatives Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? $user['email']); ?> - <?php echo $role_display; ?></p>
                        <?php if ($is_head): ?>
                            <p class="role-badge">Committee Head</p>
                        <?php endif; ?>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            <span class="badge">2</span>
                        </button>
                        <div class="user-menu">
                            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Stats Cards -->
                <div class="section-header">
                    <h2>Overview</h2>
                    <p>Your creative work and activity summary</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="stat-content">
                            <h3>24</h3>
                            <p>Graphics Created</p>
                            <span class="stat-change positive">+8 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="stat-content">
                            <h3>6</h3>
                            <p>Publications</p>
                            <span class="stat-change positive">+2 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="stat-content">
                            <h3>12</h3>
                            <p>Announcements</p>
                            <span class="stat-change positive">+4 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-content">
                            <h3>3</h3>
                            <p>Pending Requests</p>
                            <span class="stat-change neutral">Due this week</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="section-header">
                    <h2>Quick Actions</h2>
                    <p>Manage your creative content and publications</p>
                </div>
                
                <div class="actions-grid">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/announcements.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <h3>Announcements</h3>
                        <p>Create and publish</p>
                    </a>
                    
                    <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/graphics.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-images"></i>
                        </div>
                        <h3>Graphics Library</h3>
                        <p>Upload and manage</p>
                    </a>
                    
                    <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/publications.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>Publications</h3>
                        <p>Newsletters & docs</p>
                    </a>
                    
                    <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/features-manager.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Homepage Features</h3>
                        <p>Manage homepage</p>
                    </a>
                </div>

                <!-- Recent Activity -->
                <div class="section-header">
                    <h2>Recent Creative Work</h2>
                    <p>Your latest activities and contributions</p>
                </div>
                
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong>Event Poster</strong> for National Electronics Conference created</p>
                            <span class="activity-time">3 hours ago</span>
                        </div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong>Announcement</strong> about upcoming workshop published</p>
                            <span class="activity-time">1 day ago</span>
                        </div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong>Monthly Newsletter</strong> published and distributed</p>
                            <span class="activity-time">2 days ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>
