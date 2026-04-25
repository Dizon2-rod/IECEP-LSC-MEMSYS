<?php
// sidebar_registration.php - Registration Committee Sidebar Navigation
$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$is_head = $user['role'] === 'eb_vp_internal';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <img src="/IECEP-LSC-MEMSYS/public/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo" style="width: 32px; height: auto;">
            <h3>IECEP-LSC</h3>
        </div>
        <p>Registration Committee</p>
        <div class="user-role-badge">
            <i class="fas fa-<?php echo $is_head ? 'crown' : 'users'; ?>"></i>
            <?php echo $role_display; ?>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/registration/dashboard.php" 
                   class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/registration/pending-affiliations.php" 
                   class="<?php echo $current_page === 'pending-affiliations.php' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Pending Affiliations</span>
                    <?php if (rand(0, 10) > 7): ?>
                        <span class="badge">8</span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/registration/pending-members.php" 
                   class="<?php echo $current_page === 'pending-members.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Pending Members</span>
                    <?php if (rand(0, 10) > 7): ?>
                        <span class="badge">34</span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/registration/affiliated-schools.php" 
                   class="<?php echo $current_page === 'affiliated-schools.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>Affiliated Schools</span>
                </a>
            </li>
            <?php if ($is_head): ?>
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/registration/compliance.php" 
                   class="<?php echo $current_page === 'compliance.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Compliance</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <?php if ($is_head): ?>
        <div class="nav-section">
            <h4>Committee Management</h4>
            <ul class="nav-menu">
                <li>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/registration/team.php" 
                       class="<?php echo $current_page === 'team.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-friends"></i>
                        <span>Team Members</span>
                    </a>
                </li>
                <li>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/registration/reports.php" 
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
        <a href="/IECEP-LSC-MEMSYS/public/login.php?logout=true" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<style>
.sidebar {
    width: 280px;
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
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.sidebar-header h3 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
    color: white;
}

.sidebar-header p {
    font-size: 0.9rem;
    opacity: 0.8;
    margin: 4px 0 12px;
}

.user-role-badge {
    background: rgba(245, 166, 35, 0.2);
    color: var(--gold);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.sidebar-nav {
    flex: 1;
    padding: 20px 0;
}

.nav-section {
    margin-top: 32px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.nav-section h4 {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 20px 12px;
}

.nav-menu {
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-menu li {
    margin-bottom: 2px;
}

.nav-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.95rem;
    font-weight: 500;
    position: relative;
}

.nav-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.nav-menu a.active {
    background: rgba(245, 166, 35, 0.2);
    color: var(--gold);
    border-left: 3px solid var(--gold);
}

.nav-menu i {
    width: 20px;
    text-align: center;
    font-size: 0.9rem;
}

.nav-menu .badge {
    margin-left: auto;
    background: #ef4444;
    color: white;
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 600;
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.user-avatar-small {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: white;
}

.user-email {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 2px;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.3);
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
