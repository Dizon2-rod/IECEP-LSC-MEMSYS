<?php
require_once __DIR__ . '/../bootstrap.php';
// Prevent multiple inclusions
if (defined('NAVBAR_INCLUDED')) return;
define('NAVBAR_INCLUDED', true);

// Use the SupabaseClient from bootstrap
$supabaseClient = getSupabaseClient();

$affiliatedSchools = [];
if ($supabaseClient) {
    try {
        $affiliatedSchools = $supabaseClient->select('institutions', [
            'name' => 'name',
            'facebook_url' => 'facebook_url',
            'status' => 'eq.active'
        ]);
    } catch (Exception $e) {
        $affiliatedSchools = [];
    }
}

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userRole = $_SESSION['role'] ?? '';
?>
<!-- Header -->
<header class="header">
    <div class="header-container">
        <a href="<?php echo BASE_URL; ?>/" class="logo">
            <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo" class="logo-img" style="height: 40px; width: auto;">
            <span>IECEP-LSC MEMSYS</span>
        </a>
        
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-btn" id="mobileMenuToggle" aria-label="Toggle mobile menu" aria-expanded="false" aria-controls="mobileMenuOverlay">
            <span></span>
            <span></span>
            <span></span>
        </button>
        
        <?php if (!$isLoggedIn): ?>
        <!-- Public Navigation (Desktop) -->
        <nav class="nav" id="desktopNav">
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>/index.php" class="nav-link">Home</a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        Resources <i class="fas fa-chevron-down" style="font-size:0.75rem;margin-left:0.2rem;"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>/iecep-officers.php" class="dropdown-item">IECEP Officers</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/former-presidents.php" class="dropdown-item">Former Presidents</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/iecep-hymn.php" class="dropdown-item">IECEP Hymn</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/awards-distinctions.php" class="dropdown-item">Awards &amp; Distinctions</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        About IECEP-LSC <i class="fas fa-chevron-down" style="font-size:0.75rem;margin-left:0.2rem;"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>/mission-vision.php" class="dropdown-item">Mission and Vision</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/objective.php" class="dropdown-item">Institutional Objectives</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php" class="dropdown-item">Calendar Activity</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/affiliated-schools.php" class="dropdown-item">Affiliated Schools</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/contact.php" class="dropdown-item">Contact IECEP - LSC</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/public/merchandise.php" class="dropdown-item">Merchandise</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <button class="pwa-install-btn" id="pwaInstallBtn" style="display:none;align-items:center;gap:6px;background:rgba(212,175,55,0.15);color:#D4AF37;border:1px solid rgba(212,175,55,0.4);padding:0.4rem 0.85rem;border-radius:50px;font-size:0.8rem;font-weight:700;cursor:pointer;transition:all 0.2s ease;">
                <i class="fas fa-download"></i> Install App
            </button>
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn-login" id="desktopLogin">Login</a>
        </div>
        
        <?php else: ?>
        <!-- Logged-in Navigation (Desktop) -->
        <nav class="nav" id="desktopNav">
            <ul class="nav-links">
                <li><a href="<?php echo PORTAL_URL; ?>/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php" class="nav-link">Calendar</a></li>
                <li class="notification-item">
                    <button class="notification-btn" id="notificationBtn" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationBadge" style="display: none;"></span>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4>Notifications</h4>
                            <button class="mark-all-read" id="markAllRead">Mark all read</button>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-item loading">
                                <p>Loading notifications...</p>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?> <i class="fas fa-chevron-down" style="font-size:0.75rem;"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo PORTAL_URL; ?>/profile.php" class="dropdown-item">Profile</a></li>
                        <li><a href="<?php echo PORTAL_URL; ?>/settings.php" class="dropdown-item">Settings</a></li>
                        <li><hr style="border: none; border-top: 1px solid var(--neutral-200); margin: 8px 0;"></li>
                        <li><a href="<?php echo BASE_URL; ?>/logout.php" class="dropdown-item" style="color: #DC2626;">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════════ Mobile Drawer Navigation -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay" aria-hidden="true">
    <div class="mobile-menu-content">
        <!-- Header -->
        <div class="mobile-menu-header">
            <a href="<?php echo BASE_URL; ?>/" class="mobile-brand-link">
                <div class="mobile-seal-frame">
                    <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP Seal" onerror="this.style.display='none';">
                </div>
                <div class="mobile-brand-text">
                    <span class="mobile-brand-name">IECEP-LSC</span>
                    <span class="mobile-brand-tag">Laguna Student Chapter</span>
                </div>
            </a>
            <button type="button" class="mobile-menu-close" id="mobileMenuClose" aria-label="Close mobile menu">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Navigation Menu -->
        <nav class="mobile-menu-nav">
            <?php if (!$isLoggedIn): ?>
            <div class="mobile-nav-group-title">MAIN NAVIGATION</div>
            <ul class="mobile-nav-list">
                <!-- Home -->
                <li class="mobile-nav-item">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="mobile-nav-card">
                        <div class="mobile-card-iconbox iconbox-navy">
                            <i class="fas fa-house"></i>
                        </div>
                        <div class="mobile-card-info">
                            <span class="mobile-card-title">Main Portal Home</span>
                        </div>
                        <i class="fas fa-arrow-right mobile-card-arrow"></i>
                    </a>
                </li>

                <!-- Resources Dropdown -->
                <li class="mobile-nav-item has-dropdown">
                    <a href="#" class="mobile-nav-card mobile-dropdown-toggle">
                        <div class="mobile-card-iconbox iconbox-gold">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="mobile-card-info">
                            <span class="mobile-card-title">Chapter Resources</span>
                        </div>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </a>
                    <ul class="mobile-submenu">
                        <li>
                            <a href="<?php echo BASE_URL; ?>/iecep-officers.php">
                                <span class="sub-dot"><i class="fas fa-users-gear"></i></span>
                                <span class="sub-text">IECEP Officers</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/former-presidents.php">
                                <span class="sub-dot"><i class="fas fa-crown"></i></span>
                                <span class="sub-text">Former Presidents</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/iecep-hymn.php">
                                <span class="sub-dot"><i class="fas fa-music"></i></span>
                                <span class="sub-text">IECEP Hymn</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/awards-distinctions.php">
                                <span class="sub-dot"><i class="fas fa-trophy"></i></span>
                                <span class="sub-text">Awards &amp; Distinctions</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- About IECEP-LSC Dropdown -->
                <li class="mobile-nav-item has-dropdown">
                    <a href="#" class="mobile-nav-card mobile-dropdown-toggle">
                        <div class="mobile-card-iconbox iconbox-blue">
                            <i class="fas fa-circle-info"></i>
                        </div>
                        <div class="mobile-card-info">
                            <span class="mobile-card-title">About IECEP-LSC</span>
                        </div>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </a>
                    <ul class="mobile-submenu">
                        <li>
                            <a href="<?php echo BASE_URL; ?>/mission-vision.php">
                                <span class="sub-dot"><i class="fas fa-compass"></i></span>
                                <span class="sub-text">Mission &amp; Vision</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/objective.php">
                                <span class="sub-dot"><i class="fas fa-bullseye"></i></span>
                                <span class="sub-text">Institutional Objectives</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/calendar-activity.php">
                                <span class="sub-dot"><i class="fas fa-calendar-days"></i></span>
                                <span class="sub-text">Calendar Activity</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/affiliated-schools.php">
                                <span class="sub-dot"><i class="fas fa-building-columns"></i></span>
                                <span class="sub-text">Affiliated Schools</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/contact.php">
                                <span class="sub-dot"><i class="fas fa-envelope"></i></span>
                                <span class="sub-text">Contact IECEP - LSC</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/public/merchandise.php">
                                <span class="sub-dot"><i class="fas fa-shirt"></i></span>
                                <span class="sub-text">Merchandise Store</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

            <!-- Bottom Actions -->
            <div class="mobile-cta-section">
                <button class="pwa-install-btn mobile-cta-install" id="pwaMobileInstallBtn" style="display:none;">
                    <i class="fas fa-download"></i> Install IECEP App
                </button>
                <a href="<?php echo BASE_URL; ?>/login.php" class="mobile-cta-login">
                    <i class="fas fa-arrow-right-to-bracket"></i>
                    <span>Access Member &amp; Admin Portal</span>
                </a>
            </div>

            <?php else: ?>
            <div class="mobile-nav-group-title">MY ACCOUNT</div>
            <div class="mobile-user-profile-card">
                <div class="mobile-user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="mobile-user-meta">
                    <span class="user-meta-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                    <span class="user-meta-role">Signed In</span>
                </div>
            </div>
            <ul class="mobile-nav-list">
                <li class="mobile-nav-item">
                    <a href="<?php echo PORTAL_URL; ?>/dashboard.php" class="mobile-nav-card">
                        <div class="mobile-card-iconbox iconbox-navy"><i class="fas fa-gauge-high"></i></div>
                        <div class="mobile-card-info"><span class="mobile-card-title">Dashboard</span></div>
                        <i class="fas fa-arrow-right mobile-card-arrow"></i>
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo BASE_URL; ?>/calendar-activity.php" class="mobile-nav-card">
                        <div class="mobile-card-iconbox iconbox-gold"><i class="fas fa-calendar-days"></i></div>
                        <div class="mobile-card-info"><span class="mobile-card-title">Calendar</span></div>
                        <i class="fas fa-arrow-right mobile-card-arrow"></i>
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo PORTAL_URL; ?>/profile.php" class="mobile-nav-card">
                        <div class="mobile-card-iconbox iconbox-blue"><i class="fas fa-user"></i></div>
                        <div class="mobile-card-info"><span class="mobile-card-title">My Profile</span></div>
                        <i class="fas fa-arrow-right mobile-card-arrow"></i>
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo PORTAL_URL; ?>/settings.php" class="mobile-nav-card">
                        <div class="mobile-card-iconbox iconbox-navy"><i class="fas fa-gear"></i></div>
                        <div class="mobile-card-info"><span class="mobile-card-title">Settings</span></div>
                        <i class="fas fa-arrow-right mobile-card-arrow"></i>
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="mobile-nav-card mobile-logout-card">
                        <div class="mobile-card-iconbox iconbox-red"><i class="fas fa-arrow-right-from-bracket"></i></div>
                        <div class="mobile-card-info"><span class="mobile-card-title" style="color:#DC2626;">Sign Out</span></div>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </nav>

        <!-- Footer Seal -->
        <div class="mobile-drawer-footer">
            <span class="drawer-footer-text">IECEP Laguna Student Chapter • MEMSYS v2.4</span>
        </div>
    </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════
   Universal SaaS High-End White Mobile Drawer Design System
   ═══════════════════════════════════════════════════════════ */
.mobile-menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #FFFFFF;
    z-index: 99999;
    display: none;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    opacity: 0;
    transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: 'DM Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.mobile-menu-overlay.active {
    display: block !important;
    opacity: 1;
}

.mobile-menu-content {
    max-width: 520px;
    margin: 0 auto;
    padding: 1.15rem 1.15rem 2.5rem;
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background: #FFFFFF;
    box-sizing: border-box;
}

/* Header */
.mobile-menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.95rem;
    border-bottom: 1px solid #E2E8F0;
    margin-bottom: 0.85rem;
}

.mobile-brand-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
}

.mobile-seal-frame {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #FAF9F6;
    border: 1.5px solid rgba(212, 175, 55, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    box-shadow: 0 2px 8px rgba(11, 29, 74, 0.06);
}

.mobile-seal-frame img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.mobile-brand-text {
    display: flex;
    flex-direction: column;
}

.mobile-brand-name {
    font-family: 'Times New Roman', serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0B1D4A;
    line-height: 1.1;
    letter-spacing: 0.02em;
}

.mobile-brand-tag {
    font-size: 0.68rem;
    font-weight: 600;
    color: #94A3B8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.mobile-menu-close {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    color: #64748B;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.mobile-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.25rem 0 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #F1F5F9;
}

/* Navigation Groups */
.mobile-nav-group-title {
    font-size: 0.68rem;
    font-weight: 700;
    color: #94A3B8;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 0.65rem;
    padding-left: 0.25rem;
}

.mobile-nav-list {
    list-style: none;
    padding: 0;
    margin: 0 0 1.25rem 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.mobile-nav-item {
    margin: 0;
}

/* Squircle Nav Cards */
.mobile-nav-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 0.95rem;
    background: #FAFAFA;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    text-decoration: none;
    color: #1E293B;
    transition: all 0.2s ease;
    user-select: none;
}

.mobile-nav-card:hover,
.mobile-nav-card:focus {
    background: #F1F5F9;
    border-color: #CBD5E1;
    color: #0B1D4A;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
}

.mobile-card-iconbox {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.mobile-nav-card:hover .mobile-card-iconbox {
    transform: scale(1.08);
}

.iconbox-navy {
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid rgba(30, 64, 175, 0.15);
}

.iconbox-gold {
    background: #FEF9C3;
    color: #B8860B;
    border: 1px solid rgba(212, 175, 55, 0.3);
}

.iconbox-blue {
    background: #F0FDF4;
    color: #0D9488;
    border: 1px solid rgba(13, 148, 136, 0.2);
}

.iconbox-red {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid rgba(220, 38, 38, 0.2);
}

.mobile-card-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.mobile-card-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #0F172A;
    line-height: 1.25;
}

.mobile-card-sub {
    font-size: 0.72rem;
    font-weight: 500;
    color: #64748B;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
}

.mobile-card-arrow {
    font-size: 0.75rem;
    color: #CBD5E1;
    transition: all 0.2s ease;
}

.mobile-nav-card:hover .mobile-card-arrow {
    color: #0B1D4A;
    transform: translateX(2px);
}

.toggle-icon {
    font-size: 0.75rem;
    color: #94A3B8;
    transition: transform 0.25s ease;
}

/* Submenu Accordion */
.mobile-submenu {
    display: none;
    list-style: none;
    padding: 0.35rem 0.5rem 0.35rem 0.75rem;
    margin: 0.35rem 0 0.5rem 0.65rem;
    border-left: 2.5px solid #D4AF37;
    background: #F8FAFC;
    border-radius: 0 12px 12px 0;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.02);
}

.mobile-submenu.open {
    display: block !important;
}

.mobile-submenu li {
    margin: 0 0 2px 0;
}

.mobile-submenu li a {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.75rem;
    color: #475569;
    text-decoration: none;
    font-size: 0.86rem;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.18s ease;
}

.mobile-submenu li a:hover {
    color: #0B1D4A;
    background: #EEF2F6;
    font-weight: 600;
    padding-left: 0.95rem;
}

.sub-dot {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    color: #B8860B;
    flex-shrink: 0;
}

.sub-text {
    flex: 1;
}

.sub-badge {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sub-badge.gold {
    background: #FEF9C3;
    color: #B8860B;
    border: 1px solid rgba(212, 175, 55, 0.35);
}

.sub-badge.emerald {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid rgba(5, 150, 105, 0.3);
}

/* User Card (Logged-in) */
.mobile-user-profile-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    margin-bottom: 0.85rem;
}

.mobile-user-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B1D4A 0%, #1E3A8A 100%);
    color: #FDE047;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.85rem;
    border: 2px solid rgba(212, 175, 55, 0.4);
    flex-shrink: 0;
}

.mobile-user-meta {
    display: flex;
    flex-direction: column;
}

.user-meta-name {
    font-size: 0.86rem;
    font-weight: 700;
    color: #0F172A;
}

.user-meta-role {
    font-size: 0.68rem;
    color: #64748B;
    font-weight: 600;
}

/* CTA Buttons Section */
.mobile-cta-section {
    margin-top: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.mobile-cta-login {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 0.85rem 1.25rem;
    background: linear-gradient(135deg, #0B1D4A 0%, #1E3A8A 100%);
    color: #FFFFFF !important;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.92rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(11, 29, 74, 0.2);
    transition: all 0.2s ease;
    border: 1px solid rgba(212, 175, 55, 0.3);
    box-sizing: border-box;
}

.mobile-cta-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 29, 74, 0.3);
    color: #FFFFFF !important;
}

.mobile-cta-install {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 0.8rem 1.25rem;
    background: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%);
    color: #0B1D4A !important;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.9rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    box-shadow: 0 3px 12px rgba(212, 175, 55, 0.3);
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.mobile-cta-install:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 16px rgba(212, 175, 55, 0.45);
}

/* Footer note */
.mobile-drawer-footer {
    margin-top: auto;
    padding-top: 1.5rem;
    text-align: center;
    border-top: 1px solid #F1F5F9;
}

.drawer-footer-text {
    font-size: 0.68rem;
    font-weight: 500;
    color: #94A3B8;
}

/* Hamburger button animation - Compact & Sleek */
.mobile-menu-btn {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 3.5px;
    width: 32px;
    height: 32px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    cursor: pointer;
    padding: 0;
    z-index: 100;
    transition: all 0.2s ease;
}

.mobile-menu-btn:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
}

@media (min-width: 768px) {
    .mobile-menu-btn {
        display: none !important;
    }
}

.mobile-menu-btn span {
    display: block;
    width: 16px;
    height: 2px;
    background: #0B1D4A;
    border-radius: 2px;
    transition: all 0.25s ease;
}
</style>

<script>
// Mobile Menu Controller
(function() {
    function initMobileMenu() {
        const toggleBtn = document.getElementById('mobileMenuToggle');
        const overlay   = document.getElementById('mobileMenuOverlay');
        const closeBtn  = document.getElementById('mobileMenuClose');
        const dropdowns = document.querySelectorAll('.mobile-dropdown-toggle');

        if (!toggleBtn || !overlay) return;

        function openMenu() {
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');
            toggleBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (overlay.classList.contains('active')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                closeMenu();
            });
        }

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeMenu();
            }
        });

        // Dropdown toggles inside mobile menu
        dropdowns.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const submenu = this.nextElementSibling;
                const icon = this.querySelector('.toggle-icon');
                if (!submenu) return;

                const isOpen = submenu.classList.contains('open');

                // Close other submenus
                document.querySelectorAll('.mobile-submenu').forEach(sm => sm.classList.remove('open'));
                document.querySelectorAll('.mobile-dropdown-toggle .toggle-icon').forEach(ic => {
                    ic.classList.remove('fa-chevron-up');
                    ic.classList.add('fa-chevron-down');
                });

                if (!isOpen) {
                    submenu.classList.add('open');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                }
            });
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('active')) {
                closeMenu();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
})();
</script>
