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
                        <li><a href="<?php echo BASE_URL; ?>/public/blockchain-explorer.php" class="dropdown-item">Blockchain Explorer</a></li>
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
                        <li><a href="<?php echo BASE_URL; ?>/contact.php" class="dropdown-item">Contact Secretariat</a></li>
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
        <div class="mobile-menu-header">
            <a href="<?php echo BASE_URL; ?>/" class="logo" style="color:#ffffff;">
                <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo" style="height: 36px; width: auto;">
                <span style="font-size:1rem;color:#ffffff;">IECEP-LSC</span>
            </a>
            <button type="button" class="mobile-menu-close" id="mobileMenuClose" aria-label="Close mobile menu">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="mobile-menu-nav">
            <?php if (!$isLoggedIn): ?>
            <ul>
                <li>
                    <a href="<?php echo BASE_URL; ?>/index.php">
                        <i class="fas fa-house" style="width:20px;color:#D4AF37;"></i> Home
                    </a>
                </li>

                <!-- Resources Dropdown -->
                <li>
                    <a href="#" class="mobile-dropdown-toggle">
                        <span><i class="fas fa-folder-open" style="width:20px;color:#D4AF37;"></i> Resources</span>
                        <i class="fas fa-chevron-down toggle-icon" style="font-size:0.8rem;"></i>
                    </a>
                    <ul class="mobile-submenu">
                        <li><a href="<?php echo BASE_URL; ?>/iecep-officers.php"><i class="fas fa-users-gear" style="margin-right:6px;"></i> IECEP Officers</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/former-presidents.php"><i class="fas fa-crown" style="margin-right:6px;"></i> Former Presidents</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/iecep-hymn.php"><i class="fas fa-music" style="margin-right:6px;"></i> IECEP Hymn</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/awards-distinctions.php"><i class="fas fa-trophy" style="margin-right:6px;"></i> Awards &amp; Distinctions</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/public/blockchain-explorer.php"><i class="fas fa-link" style="margin-right:6px;"></i> Blockchain Explorer</a></li>
                    </ul>
                </li>

                <!-- About IECEP-LSC Dropdown -->
                <li>
                    <a href="#" class="mobile-dropdown-toggle">
                        <span><i class="fas fa-circle-info" style="width:20px;color:#D4AF37;"></i> About IECEP-LSC</span>
                        <i class="fas fa-chevron-down toggle-icon" style="font-size:0.8rem;"></i>
                    </a>
                    <ul class="mobile-submenu">
                        <li><a href="<?php echo BASE_URL; ?>/mission-vision.php"><i class="fas fa-compass" style="margin-right:6px;"></i> Mission &amp; Vision</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/objective.php"><i class="fas fa-bullseye" style="margin-right:6px;"></i> Institutional Objectives</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php"><i class="fas fa-calendar-days" style="margin-right:6px;"></i> Calendar Activity</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/affiliated-schools.php"><i class="fas fa-building-columns" style="margin-right:6px;"></i> Affiliated Schools</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/contact.php"><i class="fas fa-envelope" style="margin-right:6px;"></i> Contact Secretariat</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/public/merchandise.php"><i class="fas fa-shirt" style="margin-right:6px;"></i> Merchandise</a></li>
                    </ul>
                </li>
            </ul>

            <div style="margin-top: 1.5rem; padding: 0 0.5rem; display:flex; flex-direction:column; gap:0.6rem;">
                <button class="pwa-install-btn mobile-cta-btn" id="pwaMobileInstallBtn" style="display:none; background:linear-gradient(135deg,#D4AF37 0%,#C5A059 100%); color:#0B1D4A; border:none; width:100%; cursor:pointer;">
                    <i class="fas fa-download" style="margin-right: 0.5rem;"></i> Install IECEP App
                </button>
                <a href="<?php echo BASE_URL; ?>/login.php" class="mobile-cta-btn">
                    <i class="fas fa-right-to-bracket" style="margin-right: 0.5rem;"></i> Portal Login
                </a>
            </div>

            <?php else: ?>
            <ul>
                <li><a href="<?php echo PORTAL_URL; ?>/dashboard.php"><i class="fas fa-gauge" style="width:20px;color:#D4AF37;"></i> Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php"><i class="fas fa-calendar-days" style="width:20px;color:#D4AF37;"></i> Calendar</a></li>
                <li><a href="<?php echo PORTAL_URL; ?>/profile.php"><i class="fas fa-user" style="width:20px;color:#D4AF37;"></i> My Profile</a></li>
                <li><a href="<?php echo PORTAL_URL; ?>/settings.php"><i class="fas fa-gear" style="width:20px;color:#D4AF37;"></i> Settings</a></li>
                <li><a href="<?php echo BASE_URL; ?>/logout.php" style="color:#EF4444;"><i class="fas fa-power-off" style="width:20px;"></i> Logout</a></li>
            </ul>
            <?php endif; ?>
        </nav>
    </div>
</div>

<style>
/* Enhanced Mobile Drawer Styles */
.mobile-menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(7, 18, 46, 0.96);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 99999;
    display: none;
    overflow-y: auto;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.mobile-menu-overlay.active {
    display: block !important;
    opacity: 1;
}
.mobile-menu-content {
    max-width: 480px;
    margin: 0 auto;
    padding: 1.5rem 1.25rem 3rem;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
.mobile-menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    margin-bottom: 1.5rem;
}
.mobile-menu-close {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #FFFFFF;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.mobile-menu-close:hover {
    background: #D4AF37;
    color: #07122E;
    transform: rotate(90deg);
}
.mobile-menu-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.mobile-menu-nav > ul > li {
    margin-bottom: 0.5rem;
}
.mobile-menu-nav > ul > li > a {
    color: #FFFFFF;
    text-decoration: none;
    font-size: 1.05rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.2s ease;
}
.mobile-menu-nav > ul > li > a:hover,
.mobile-menu-nav > ul > li > a:focus {
    background: rgba(212, 175, 55, 0.15);
    border-color: rgba(212, 175, 55, 0.35);
    color: #F8E7A2;
}
.mobile-submenu {
    display: none;
    list-style: none;
    padding: 0.5rem 0 0.5rem 1.25rem;
    margin: 0.25rem 0 0.5rem;
    border-left: 2px solid rgba(212, 175, 55, 0.4);
}
.mobile-submenu.open {
    display: block !important;
}
.mobile-submenu li a {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    font-size: 0.95rem;
    padding: 0.55rem 0.75rem;
    display: block;
    border-radius: 8px;
    transition: all 0.2s ease;
}
.mobile-submenu li a:hover {
    color: #F8E7A2;
    background: rgba(255, 255, 255, 0.08);
    padding-left: 1rem;
}
.mobile-cta-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 0.9rem 1.5rem;
    background: linear-gradient(135deg, #FFE89E 0%, #D4AF37 100%);
    color: #07122E;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    border-radius: 9999px;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.35);
    transition: all 0.2s ease;
}
.mobile-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.5);
    filter: brightness(1.05);
}

/* Hamburger button animation - Compact & Sleek */
.mobile-menu-btn {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 3.5px;
    width: 28px;
    height: 28px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    z-index: 100;
}
@media (min-width: 768px) {
    .mobile-menu-btn {
        display: none !important;
    }
}
.mobile-menu-btn span {
    display: block;
    width: 18px;
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
