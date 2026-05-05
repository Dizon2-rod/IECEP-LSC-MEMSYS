<?php
require_once __DIR__ . '/../auth_check.php';

// Allow school_officer
require_role(['school_officer']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
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
                        <h1>School Officer Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? $user['email']); ?> - <?php echo $role_display; ?></p>
                        <p class="school-info"><?php echo htmlspecialchars($user['user_metadata']['school_name'] ?? 'Your School'); ?></p>
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
                <!-- School Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3>45</h3>
                            <p>Total Members</p>
                            <span class="stat-change positive">+3 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>78%</h3>
                            <p>Attendance Rate</p>
                            <span class="stat-change positive">+5% this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>92%</h3>
                            <p>Compliance Rate</p>
                            <span class="stat-change positive">Above target</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-content">
                            <h3>₱22,500</h3>
                            <p>Fees Collected</p>
                            <span class="stat-change positive">This month</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="/IECEP-LSC-MEMSYS/public/portal/officer/members.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>Manage Members</h3>
                            <p>View and manage school members</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/officer/attendance.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3>Track Attendance</h3>
                            <p>Record and view attendance</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/officer/compliance.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3>Compliance Status</h3>
                            <p>Monitor school compliance</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/officer/profile.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-school"></i>
                            </div>
                            <h3>School Profile</h3>
                            <p>Update school information</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h2>Recent School Activity</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>3 new members</strong> joined this week</p>
                                <span class="activity-time">2 days ago</span>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Chapter meeting</strong> attendance recorded - 85% turnout</p>
                                <span class="activity-time">3 days ago</span>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                                <i class="fas fa-file-check"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Compliance documents</strong> submitted to IECEP-LSC</p>
                                <span class="activity-time">1 week ago</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="upcoming-events">
                    <h2>Upcoming School Events</h2>
                    <div class="events-list">
                        <div class="event-item">
                            <div class="event-date">
                                <div class="date">28</div>
                                <div class="month">APR</div>
                            </div>
                            <div class="event-content">
                                <h3>Monthly Chapter Meeting</h3>
                                <p><i class="fas fa-map-marker-alt"></i> Engineering Building, Room 301</p>
                                <p><i class="fas fa-clock"></i> 3:00 PM - 5:00 PM</p>
                                <button class="btn btn-sm btn-primary">Manage Event</button>
                            </div>
                        </div>
                        
                        <div class="event-item">
                            <div class="event-date">
                                <div class="date">05</div>
                                <div class="month">MAY</div>
                            </div>
                            <div class="event-content">
                                <h3>Technical Workshop</h3>
                                <p><i class="fas fa-map-marker-alt"></i> Electronics Laboratory</p>
                                <p><i class="fas fa-clock"></i> 9:00 AM - 12:00 PM</p>
                                <button class="btn btn-sm btn-outline">View Details</button>
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
