<?php
require_once __DIR__ . '/../auth_check.php';
require_once INCLUDES_PATH . 'config.php';

// Access Control: Committee Head and Creatives members
require_role(['eb_pro_1', 'committee_creatives']);
$current_page = 'dashboard';

$user = get_user_info();
$displayName = $_SESSION['full_name'] ?? $_SESSION['email'] ?? $user['user_metadata']['full_name'] ?? $user['email'] ?? 'Creative';
$role_display = get_role_display_name($user['role']);
$is_head = $user['role'] === 'eb_pro_1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creatives Dashboard | IECEP-LSC</title>
    
    <!-- 1. ADD SUPABASE CDN HERE (This fixes the 'createClient' error) -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">

    <style>
        :root {
            --c-navy: #0B1D4A;
            --c-navy-light: #1E3A6E;
            --c-gold: #D4AF37;
            --c-gold-hover: #B8860B;
            --c-slate-50: #F8FAFC;
            --c-slate-100: #F1F5F9;
            --c-slate-200: #E2E8F0;
            --c-slate-400: #94A3B8;
            --c-slate-600: #475569;
            --c-slate-900: #0F172A;
            --c-success: #10B981;
            --c-warning: #F59E0B;
            --c-error: #EF4444;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar { 
            width: 260px !important; 
            position: fixed !important; 
            left: 0; 
            top: 0; 
            bottom: 0; 
            z-index: 1000; 
            transition: none !important; 
        }

        .main-content { 
            margin-left: 260px !important; 
            width: calc(100% - 260px) !important; 
            min-height: 100vh;
            transition: none !important;
        }

        .creatives-scope {
            font-family: 'Inter', sans-serif;
            background-color: var(--c-slate-50);
            padding: 2rem 3rem;
            color: var(--c-slate-900);
        }

        .creatives-scope .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 3rem;
        }

        .creatives-scope .welcome-section h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--c-navy);
            margin: 0;
            letter-spacing: -0.025em;
        }

        .creatives-scope .welcome-text {
            color: var(--c-slate-600);
            font-size: 1rem;
            margin-top: 6px;
        }

        .creatives-scope .head-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, var(--c-gold), var(--c-gold-hover));
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 12px;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
        }

        .creatives-scope .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .creatives-scope .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--c-slate-200);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: var(--transition);
        }

        .creatives-scope .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--c-navy);
        }

        .creatives-scope .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .creatives-scope .stat-details h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--c-navy);
            margin: 0;
        }

        .creatives-scope .stat-details p {
            color: var(--c-slate-600);
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0;
        }

        .creatives-scope .stat-trend {
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 4px;
            display: block;
        }
        .creatives-scope .trend-up { color: var(--c-success); }
        .creatives-scope .trend-neutral { color: var(--c-warning); }

        .creatives-scope .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--c-navy);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0.8;
        }

        .creatives-scope .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 3rem;
        }

        .creatives-scope .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--c-slate-200);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: var(--shadow-sm);
        }

        .creatives-scope .action-card:hover {
            background: var(--c-navy);
            border-color: var(--c-navy);
            transform: translateY(-2px);
        }

        .creatives-scope .action-card:hover h3, 
        .creatives-scope .action-card:hover p, 
        .creatives-scope .action-card:hover .action-icon {
            color: white !important;
        }

        .creatives-scope .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--c-slate-100);
            color: var(--c-navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .creatives-scope .action-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--c-slate-900);
            margin: 0;
        }

        .creatives-scope .action-card p {
            font-size: 0.85rem;
            color: var(--c-slate-600);
            margin: 0;
            line-height: 1.5;
        }

        .creatives-scope .activity-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--c-slate-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .creatives-scope .activity-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--c-slate-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .creatives-scope .activity-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--c-slate-50);
            transition: var(--transition);
        }

        .creatives-scope .activity-item:last-child { border-bottom: none; }
        .creatives-scope .activity-item:hover { background: var(--c-slate-50); }

        .creatives-scope .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.85rem;
        }

        .creatives-scope .activity-text {
            flex: 1;
            font-size: 0.875rem;
            color: var(--c-slate-600);
        }
        .creatives-scope .activity-text strong { color: var(--c-navy); font-weight: 600; }
        .creatives-scope .activity-time { font-size: 0.75rem; color: var(--c-slate-400); }

        .btn-notification {
            padding: 8px 16px;
            border-radius: 10px;
            border: 1px solid var(--c-slate-200);
            background: white;
            cursor: pointer;
            position: relative;
            transition: var(--transition);
            color: var(--c-slate-600);
        }
        .btn-notification:hover { background: var(--c-slate-100); }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--c-error);
            color: white;
            font-size: 0.6rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        .notification-wrapper {
            position: relative;
            display: inline-block;
        }

        .notification-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 350px;
            background: white;
            border-radius: 16px;
            border: 1px solid var(--c-slate-200);
            box-shadow: var(--shadow-lg);
            z-index: 2000;
            display: none; 
            flex-direction: column;
            overflow: hidden;
            animation: slideIn 0.2s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .notification-dropdown.active {
            display: flex;
        }

        .notif-header {
            padding: 1rem 1.25rem;
            background: var(--c-slate-50);
            border-bottom: 1px solid var(--c-slate-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notif-header span {
            font-weight: 700;
            color: var(--c-navy);
            font-size: 0.9rem;
        }

        .mark-all-read {
            font-size: 0.7rem;
            color: var(--c-navy);
            text-decoration: none;
            font-weight: 600;
            opacity: 0.7;
            transition: var(--transition);
        }

        .mark-all-read:hover { opacity: 1; text-decoration: underline; }

        .notif-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notif-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--c-slate-50);
            display: flex;
            gap: 12px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .notif-item:hover { background: var(--c-slate-50); }

        .notif-item.unread {
            background: rgba(212, 175, 55, 0.05);
            border-left: 3px solid var(--c-gold);
        }

        .notif-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--c-slate-100);
            color: var(--c-navy);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.85rem;
        }

        .notif-content {
            flex: 1;
        }

        .notif-content p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--c-slate-600);
            line-height: 1.4;
        }

        .notif-content .time {
            font-size: 0.7rem;
            color: var(--c-slate-400);
            display: block;
            margin-top: 4px;
        }

        .notif-loading {
            padding: 2rem;
            text-align: center;
            color: var(--c-slate-400);
            font-size: 0.875rem;
        }

        .notif-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid var(--c-slate-200);
            background: white;
        }

        .notif-footer a {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--c-navy);
            text-decoration: none;
        }

        @media (max-width: 1024px) {
            .sidebar { width: 80px !important; }
            .main-content { margin-left: 80px !important; width: calc(100% - 80px) !important; }
        }

        @media (max-width: 768px) {
            .creatives-scope { padding: 1.5rem; }
            .creatives-scope .page-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="creatives-scope">
            
            <header class="page-header">
                <div class="welcome-section">
                    <h1>Creatives Dashboard</h1>
                    <p class="welcome-text">Welcome back, <strong><?php echo htmlspecialchars($displayName); ?></strong> &bull; <?php echo $role_display; ?></p>
                    <?php if ($is_head): ?>
                        <span class="head-badge"><i class="fas fa-crown"></i> Committee Head</span>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <div class="notification-wrapper">
                        <button class="btn-notification" id="notificationBtn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notif-count">0</span>
                        </button>
                        
                        <div class="notification-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <span>Notifications</span>
                                <a href="#" id="markAllRead" class="mark-all-read">Mark all as read</a>
                            </div>
                            <div class="notif-list" id="notifList">
                                <div class="notif-loading">
                                    <i class="fas fa-circle-notch fa-spin"></i> Loading...
                                </div>
                            </div>
                            <div class="notif-footer">
                                <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/notifications.php">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="section-title"><i class="fas fa-chart-pie"></i> Work Overview</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(212, 175, 55, 0.15); color: var(--c-gold);">
                        <i class="fas fa-palette"></i>
                    </div>
                    <div class="stat-details">
                        <h3>24</h3>
                        <p>Graphics Created</p>
                        <span class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> +8 this month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(30, 58, 110, 0.15); color: var(--c-navy);">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stat-details">
                        <h3>6</h3>
                        <p>Publications</p>
                        <span class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> +2 this month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: var(--c-success);">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-details">
                        <h3 id="announcements-count">12</h3>
                        <p>Announcements</p>
                        <span class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> +4 this month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: var(--c-error);">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-details">
                        <h3>3</h3>
                        <p>Pending Requests</p>
                        <span class="stat-trend trend-neutral"><i class="fas fa-clock"></i> Due this week</span>
                    </div>
                </div>
            </div>

            <div class="section-title"><i class="fas fa-rocket"></i> Workstation Quick-Links</div>
            <div class="action-grid">
                <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/announcements.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-bullhorn"></i></div>
                    <h3>Announcements</h3>
                    <p>Draft and publish official chapter updates.</p>
                </a>
                <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/graphics.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-images"></i></div>
                    <h3>Graphics Library</h3>
                    <p>Manage and organize the visual asset database.</p>
                </a>
                <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/publications.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-file-alt"></i></div>
                    <h3>Publications</h3>
                    <p>Create monthly newsletters and digital reports.</p>
                </a>
                <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/features-manager.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-star"></i></div>
                    <h3>Homepage Features</h3>
                    <p>Control which highlights appear on the landing page.</p>
                </a>
            </div>

            <div class="section-title"><i class="fas fa-history"></i> Recent Creative Activity</div>
            <div class="activity-card">
                <div class="activity-header">
                    <span style="font-weight: 600; color: var(--c-navy);">Latest Contributions</span>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/activity-log.php" style="font-size: 0.75rem; color: var(--c-navy); text-decoration: none; font-weight: 600;">View All</a>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: rgba(212, 175, 55, 0.15); color: var(--c-gold);">
                        <i class="fas fa-image"></i>
                    </div>
                    <div class="activity-text">
                        <p><strong>Event Poster</strong> for National Electronics Conference created</p>
                        <span class="activity-time">3 hours ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: rgba(30, 58, 110, 0.15); color: var(--c-navy);">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="activity-text">
                        <p><strong>Announcement</strong> about upcoming workshop published</p>
                        <span class="activity-time">1 day ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: rgba(16, 185, 129, 0.15); color: var(--c-success);">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="activity-text">
                        <p><strong>Monthly Newsletter</strong> published and distributed</p>
                        <span class="activity-time">2 days ago</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        window.IECEP_CONFIG = {
            SUPABASE_URL: <?php echo json_encode(SUPABASE_URL); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(SUPABASE_ANON_KEY); ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            // --- Realtime Announcements ---
            window.addEventListener('realtime:creatives_announcements', function(event) {
                const { action } = event.detail;
                if (action === 'INSERT') {
                    const countElement = document.getElementById('announcements-count');
                    if (countElement) {
                        countElement.textContent = (parseInt(countElement.textContent) || 0) + 1;
                    }
                }
            });

            // --- Notification System Logic ---
            const notifBtn = document.getElementById('notificationBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const notifList = document.getElementById('notifList');
            const notifCount = document.getElementById('notif-count');

            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('active');
                if (notifDropdown.classList.contains('active')) {
                    fetchNotifications();
                }
            });

            document.addEventListener('click', () => {
                notifDropdown.classList.remove('active');
            });

            notifDropdown.addEventListener('click', (e) => e.stopPropagation());

            async function fetchNotifications() {
                try {
                    // Note: Using global 'supabase' object created inside realtime.js
                    if (!window.supabase) {
                        throw new Error("Supabase client not initialized");
                    }

                    const { data, error } = await supabase
                        .from('notifications')
                        .select('*')
                        .order('created_at', { ascending: false })
                        .limit(10);

                    if (error) throw error;

                    if (!data || data.length === 0) {
                        notifList.innerHTML = '<div class="notif-loading">No new notifications</div>';
                        notifCount.textContent = '0';
                        notifCount.style.display = 'none';
                        return;
                    }

                    const unreadCount = data.filter(n => !n.is_read).length;
                    notifCount.textContent = unreadCount;
                    notifCount.style.display = unreadCount > 0 ? 'flex' : 'none';

                    notifList.innerHTML = data.map(notif => `
                        <a href="${notif.link || '#'}" class="notif-item ${notif.is_read ? '' : 'unread'}">
                            <div class="notif-icon">
                                <i class="fas ${notif.icon || 'fa-info-circle'}"></i>
                            </div>
                            <div class="notif-content">
                                <p>${notif.message}</p>
                                <span class="time">${timeAgo(notif.created_at)}</span>
                            </div>
                        </a>
                    `).join('');
                } catch (err) {
                    notifList.innerHTML = `<div class="notif-loading" style="color:red">Error: ${err.message}</div>`;
                }
            }

            function timeAgo(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const seconds = Math.floor((now - date) / 1000);
                if (seconds < 60) return 'Just now';
                if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
                if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
                return date.toLocaleDateString();
            }

            document.getElementById('markAllRead').addEventListener('click', async (e) => {
                e.preventDefault();
                try {
                    if (!window.supabase) return;
                    const { error } = await supabase
                        .from('notifications')
                        .update({ is_read: true })
                        .eq('is_read', false);
                    
                    if (error) throw error;
                    notifCount.style.display = 'none';
                    fetchNotifications();
                } catch (err) {
                    console.error('Error updating notifications:', err);
                }
            });
        });
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/js/realtime.js" defer></script>
</body>
</html>
