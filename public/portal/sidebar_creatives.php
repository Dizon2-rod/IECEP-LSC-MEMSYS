<?php
// sidebar_creatives.php - Creatives Committee Sidebar Navigation
$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$is_head = $user['role'] === 'eb_pro_1';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <img src="/IECEP-LSC-MEMSYS/public/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo" style="width: 32px; height: auto;">
            <h3>IECEP-LSC</h3>
        </div>
        <p>Creatives Committee</p>
        <div class="user-role-badge">
            <i class="fas fa-<?php echo $is_head ? 'crown' : 'palette'; ?>"></i>
            <?php echo $role_display; ?>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li>
                <a href="dashboard.php" 
                   class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="announcements.php" 
                   class="<?php echo $current_page === 'announcements.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </li>
            <li>
                <a href="graphics.php" 
                   class="<?php echo $current_page === 'graphics.php' ? 'active' : ''; ?>">
                    <i class="fas fa-images"></i>
                    <span>Graphics</span>
                </a>
            </li>
            <li>
                <a href="publications.php" 
                   class="<?php echo $current_page === 'publications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Publications</span>
                </a>
            </li>
            <li>
                <a href="features-manager.php" 
                   class="<?php echo $current_page === 'features-manager.php' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span>Homepage Features</span>
                </a>
            </li>
        </ul>
        
        <?php if ($is_head): ?>
        <div class="nav-section">
            <h4>Committee Management</h4>
            <ul class="nav-menu">
                <li>
                    <a href="team.php" 
                       class="<?php echo $current_page === 'team.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Team Members</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" 
                       class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" 
                 alt="User Avatar" class="user-avatar-small">
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'User'); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
        </div>
        <a href="../../login.php?logout=true" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<style>
:root {
    --navy: #0A2F6C;
    --navy-light: #1e4a8a;
    --gold: #F5A623;
    --gold-dark: #d48f1f;
}

.sidebar {
    width: 260px;
    background: var(--navy);
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    z-index: 1000;
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    padding: 20px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    text-align: center;
}

.sidebar-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
    color: white;
}

.sidebar-header p {
    font-size: 0.8rem;
    opacity: 0.7;
    margin: 2px 0 10px;
}

.user-role-badge {
    background: rgba(245, 166, 35, 0.15);
    color: var(--gold);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.sidebar-nav {
    flex: 1;
    padding: 16px 0;
}

.nav-section {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.nav-section h4 {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 16px 10px;
}

.nav-menu {
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-menu li {
    margin-bottom: 1px;
}

.nav-menu a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    transition: all 0.15s ease;
    font-size: 0.875rem;
    font-weight: 500;
    position: relative;
}

.nav-menu a:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
}

.nav-menu a.active {
    background: rgba(245, 166, 35, 0.15);
    color: var(--gold);
    border-left: 2px solid var(--gold);
}

.nav-menu i {
    width: 18px;
    text-align: center;
    font-size: 0.85rem;
}

.sidebar-footer {
    padding: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.user-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 500;
    font-size: 0.8rem;
    color: white;
}

.user-email {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 1px;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.15s ease;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
}
</style>
