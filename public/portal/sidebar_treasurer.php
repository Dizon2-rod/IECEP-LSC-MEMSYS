<?php
// sidebar_treasurer.php - Treasurer Sidebar Navigation
$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <img src="/IECEP-LSC-MEMSYS/public/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo" style="width: 32px; height: auto;">
            <h3>IECEP-LSC</h3>
        </div>
        <p>Treasurer Office</p>
        <div class="user-role-badge">
            <i class="fas fa-coins"></i>
            <?php echo $role_display; ?>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/dashboard.php" 
                   class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/collect-fees.php" 
                   class="<?php echo $current_page === 'collect-fees.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Collect Fees</span>
                </a>
            </li>
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/transactions.php" 
                   class="<?php echo $current_page === 'transactions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transactions</span>
                </a>
            </li>
            <li>
                <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/reports.php" 
                   class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Financial Reports</span>
                </a>
            </li>
        </ul>
        
        <div class="nav-section">
            <h4>Blockchain & Marketing</h4>
            <ul class="nav-menu">
                <li>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/blockchain.php" 
                       class="<?php echo $current_page === 'blockchain.php' ? 'active' : ''; ?>">
                        <i class="fas fa-link"></i>
                        <span>Blockchain</span>
                    </a>
                </li>
                <li>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/marketing/dashboard.php" 
                       class="<?php echo strpos($_SERVER['REQUEST_URI'], 'marketing') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Marketing Committee</span>
                        <span class="nav-note">Head</span>
                    </a>
                </li>
            </ul>
        </div>
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

.nav-note {
    margin-left: auto;
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 8px;
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
