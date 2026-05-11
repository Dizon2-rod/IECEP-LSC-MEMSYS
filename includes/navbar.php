<?php
// Prevent multiple inclusions
if (defined('NAVBAR_INCLUDED')) return;
define('NAVBAR_INCLUDED', true);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/../includes/paths.php';

$supabase = new \App\Lib\Supabase();
$affiliatedSchools = [];
try {
    $result = $supabase->from('institutions')
        ->select('name,facebook_url')
        ->eq('status', 'active')
        ->order('name', true)
        ->get(true);
    $affiliatedSchools = $result['data'] ?? [];
} catch (Exception $e) {
    $affiliatedSchools = [];
}

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userRole = $_SESSION['role'] ?? '';
?>
<!-- Header -->
<header class="header">
    <div class="header-container">
        <a href="<?php echo BASE_URL; ?>/" class="logo">
            <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo" class="logo-img">
            <span>IECEP-LSC MEMSYS</span>
        </a>
        
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-btn" id="mobileMenuToggle" aria-label="Toggle mobile menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        
        <?php if (!$isLoggedIn): ?>
        <!-- Public Navigation -->
        <nav class="nav" id="desktopNav">
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        Resources <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>/board-of-trustees.php" class="dropdown-item">Board of Trustees</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/iecep-officers.php" class="dropdown-item">IECEP Officers</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/former-presidents.php" class="dropdown-item">Former Presidents</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/iecep-hymn.php" class="dropdown-item">IECEP Hymn</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/awards-distinctions.php" class="dropdown-item">Awards & Distinctions</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        About IECEP-LSC <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>/mission-vision.php" class="dropdown-item">IECEP Mission and Vision</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/objective.php" class="dropdown-item">IECEP Objective</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php" class="dropdown-item">Calendar Activity</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/affiliated-schools.php" class="dropdown-item">Affiliated Schools</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/contact.php" class="dropdown-item">Contact Us</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        
        <a href="<?php echo BASE_URL; ?>/login.php" class="btn-login" id="desktopLogin">Login</a>
        
        <?php else: ?>
        <!-- Logged-in Navigation -->
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
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?> <i class="icon icon-chevron-down"></i>
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

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay">
    <div class="mobile-menu-content">
        <div class="mobile-menu-header">
            <div class="logo">
                <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo" class="logo-img" style="width: 40px; height: 40px;">
                <span>IECEP-LSC MEMSYS</span>
            </div>
            <button class="mobile-menu-close" id="mobileMenuClose" aria-label="Close mobile menu">
                <i class="icon icon-xmark"></i>
            </button>
        </div>
        
        <nav class="mobile-menu-nav">
            <?php if (!$isLoggedIn): ?>
                <!-- Public Mobile Menu -->
                <ul>
                    <li class="mobile-dropdown">
                        <a href="#" class="mobile-dropdown-toggle">
                            Home <i class="icon icon-chevron-down"></i>
                        </a>
                        <ul class="mobile-submenu">
                            <li><a href="<?php echo BASE_URL; ?>/board-of-trustees.php">Board of Trustees</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/iecep-officers.php">IECEP Officers</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/former-presidents.php">Former Presidents</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/iecep-hymn.php">IECEP Hymn</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/awards-distinctions.php">Awards & Distinctions</a></li>
                        </ul>
                    </li>
                    <li class="mobile-dropdown">
                        <a href="#" class="mobile-dropdown-toggle">
                            Resources <i class="icon icon-chevron-down"></i>
                        </a>
                        <ul class="mobile-submenu">
                            <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php">Calendar Activity</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/affiliated-schools.php">Affiliated Schools</a></li>
                        </ul>
                    </li>
                    <li class="mobile-dropdown">
                        <a href="#" class="mobile-dropdown-toggle">
                            About IECEP-LSC <i class="icon icon-chevron-down"></i>
                        </a>
                        <ul class="mobile-submenu">
                            <li><a href="<?php echo BASE_URL; ?>/mission-vision.php">IECEP Mission and Vision</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/objective.php">IECEP Objective</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/contact.php">Contact Us</a></li>
                        </ul>
                    </li>
                    <li><a href="<?php echo BASE_URL; ?>/login.php" class="mobile-cta-btn">Login</a></li>
                </ul>
            <?php else: ?>
                <!-- Logged-in Mobile Menu -->
                <ul>
                    <li><a href="<?php echo PORTAL_URL; ?>/dashboard.php">Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php">Calendar</a></li>
                    <li class="mobile-dropdown">
                        <a href="#" class="mobile-dropdown-toggle">
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?> <i class="icon icon-chevron-down"></i>
                        </a>
                        <ul class="mobile-submenu">
                            <li><a href="<?php echo PORTAL_URL; ?>/profile.php">Profile</a></li>
                            <li><a href="<?php echo PORTAL_URL; ?>/settings.php">Settings</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/logout.php" style="color: #DC2626;">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            <?php endif; ?>
        </nav>
    </div>
</div>

<script>
// Mobile Menu Functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
    const mobileMenuClose = document.getElementById('mobileMenuClose');
    const mobileDropdownToggles = document.querySelectorAll('.mobile-dropdown-toggle');
    
    function openMobileMenu() {
        mobileMenuOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeMobileMenu() {
        mobileMenuOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    mobileMenuToggle.addEventListener('click', openMobileMenu);
    mobileMenuClose.addEventListener('click', closeMobileMenu);
    mobileMenuOverlay.addEventListener('click', function(e) {
        if (e.target === mobileMenuOverlay) {
            closeMobileMenu();
        }
    });
    
    // Mobile dropdown toggles
    mobileDropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            const isOpen = submenu.style.display === 'block';
            
            // Close all other submenus
            document.querySelectorAll('.mobile-submenu').forEach(menu => {
                menu.style.display = 'none';
            });
            document.querySelectorAll('.mobile-dropdown-toggle i').forEach(icon => {
                icon.classList.remove('icon-chevron-up');
                icon.classList.add('icon-chevron-down');
            });
            
            // Toggle current submenu
            if (!isOpen) {
                submenu.style.display = 'block';
                this.querySelector('i').classList.remove('icon-chevron-down');
                this.querySelector('i').classList.add('icon-chevron-up');
            }
        });
    });
    
    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenuOverlay.classList.contains('active')) {
            closeMobileMenu();
        }
    });
});
</script>
