<?php
require_once __DIR__ . '/../auth_check.php';

// Allow eb_vp_internal (head) and committee_registration
require_role(['eb_vp_internal', 'committee_registration']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$is_head = $user['role'] === 'eb_vp_internal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Registration Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? $user['email']); ?> - <?php echo $role_display; ?></p>
                        <?php if ($is_head): ?>
                            <p class="role-badge">Committee Head</p>
                        <?php endif; ?>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            <span class="badge">5</span>
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
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3>8</h3>
                            <p>Pending Affiliations</p>
                            <span class="stat-change neutral">Awaiting review</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3>34</h3>
                            <p>Pending Members</p>
                            <span class="stat-change neutral">Awaiting approval</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>156</h3>
                            <p>Approved This Month</p>
                            <span class="stat-change positive">+12% from last month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-school"></i>
                        </div>
                        <div class="stat-content">
                            <h3>24</h3>
                            <p>Affiliated Schools</p>
                            <span class="stat-change positive">+2 this month</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="/IECEP-LSC-MEMSYS/public/portal/registration/pending-affiliations.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <h3>Review Affiliations</h3>
                            <p>Process pending school affiliation applications</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/registration/pending-members.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>Approve Members</h3>
                            <p>Review and approve member applications</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/registration/affiliated-schools.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h3>Manage Schools</h3>
                            <p>View and manage affiliated schools</p>
                        </a>
                        
                        <?php if ($is_head): ?>
                        <a href="/IECEP-LSC-MEMSYS/public/portal/registration/compliance.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3>Compliance Overview</h3>
                            <p>Monitor registration compliance</p>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Applications -->
                <div class="recent-activity">
                    <h2>Recent Applications</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Technological University of the Philippines</strong> submitted affiliation application</p>
                                <span class="activity-time">2 hours ago</span>
                                <div class="activity-actions">
                                    <button class="btn btn-sm btn-primary">Review</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>15 new member applications</strong> from De La Salle University</p>
                                <span class="activity-time">5 hours ago</span>
                                <div class="activity-actions">
                                    <button class="btn btn-sm btn-primary">Review</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                <i class="fas fa-circle-check"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>University of Santo Tomas</strong> affiliation approved</p>
                                <span class="activity-time">1 day ago</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Committee Performance -->
                <?php if ($is_head): ?>
                <div class="performance-metrics">
                    <h2>Committee Performance</h2>
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <h3>Average Processing Time</h3>
                            <div class="metric-value">2.3 days</div>
                            <div class="metric-trend positive">↓ 0.5 days from last month</div>
                        </div>
                        <div class="metric-card">
                            <h3>Approval Rate</h3>
                            <div class="metric-value">87%</div>
                            <div class="metric-trend positive">↑ 3% from last month</div>
                        </div>
                        <div class="metric-card">
                            <h3>Backlog Status</h3>
                            <div class="metric-value">42 applications</div>
                            <div class="metric-trend negative">↑ 8 from last week</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>
