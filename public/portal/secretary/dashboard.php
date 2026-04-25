<?php
require_once __DIR__ . '/../auth_check.php';

// Allow eb_secretary_general
require_role(['eb_secretary_general']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar_secretary.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Secretary Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? $user['email']); ?> - <?php echo $role_display; ?></p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            <span class="badge">7</span>
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
                <!-- Secretary Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="stat-content">
                            <h3>24</h3>
                            <p>Announcements Sent</p>
                            <span class="stat-change positive">+6 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                        <div class="stat-content">
                            <h3>89%</h3>
                            <p>Read Receipt Rate</p>
                            <span class="stat-change positive">+3% this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>156</h3>
                            <p>Documents Processed</p>
                            <span class="stat-change positive">+12 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3>1,247</h3>
                            <p>Total Recipients</p>
                            <span class="stat-change positive">+45 this month</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="/IECEP-LSC-MEMSYS/public/portal/secretary/announcements.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <h3>Send Announcements</h3>
                            <p>Create and send announcements</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/secretary/read-receipts.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <h3>Track Read Receipts</h3>
                            <p>Monitor announcement engagement</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/secretary/documents.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3>Document Management</h3>
                            <p>Manage official documents</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Announcements -->
                <div class="recent-activity">
                    <h2>Recent Announcements</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>National Electronics Conference</strong> registration now open</p>
                                <span class="activity-time">2 hours ago</span>
                                <div class="receipt-stats">
                                    <span class="receipt-rate">92% read</span>
                                    <span class="receipt-count">1,148 / 1,247</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Monthly Chapter Meeting</strong> schedule announcement</p>
                                <span class="activity-time">1 day ago</span>
                                <div class="receipt-stats">
                                    <span class="receipt-rate">87% read</span>
                                    <span class="receipt-count">1,085 / 1,247</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>New Membership Benefits</strong> announcement</p>
                                <span class="activity-time">3 days ago</span>
                                <div class="receipt-stats">
                                    <span class="receipt-rate">95% read</span>
                                    <span class="receipt-count">1,185 / 1,247</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>
