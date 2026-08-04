<?php
require_once __DIR__ . '/../bootstrap.php';
// Dynamic Sidebar - Adapts to user role
// This file should be included after auth_check.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load role configuration
require_once __DIR__ . '/../includes/role-config.php';

// Base URL variables for maintainability
$base_public_url = '/IECEP-LSC-MEMSYS/public';
$base_root_url = '/IECEP-LSC-MEMSYS';

function buildSidebarLink(string $url, string $publicBase): string {
    if (strpos($url, '/') === 0) {
        return htmlspecialchars($url, ENT_QUOTES);
    }
    return htmlspecialchars(rtrim($publicBase, '/') . '/' . ltrim($url, '/'), ENT_QUOTES);
}

// Get user info with fallbacks
$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];

// Enhanced role detection with multiple fallbacks
$role = $_SESSION['role'] ?? 
         $user['role'] ?? 
         $user['user_metadata']['role'] ?? 
         'school_officer';

$user_name = $user['user_metadata']['full_name'] ?? $_SESSION['user_name'] ?? $user['email'] ?? 'User';
$user_email = $user['email'] ?? $_SESSION['user_email'] ?? '';
$avatar_url = $user['user_metadata']['avatar_url'] ?? '';

// Get current page for active state
// This should be set by each portal page: $current_page = basename(__FILE__, '.php');
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}

// Get role configuration from role-config.php
$roleConfig = getRoleConfig($role);

// Fallback to school_officer if role not found
if (!$roleConfig) {
    $role = 'school_officer';
    $roleConfig = getRoleConfig($role);
}

$menu_config = [
    'title' => $roleConfig['title'] ?? 'Dashboard',
    'badge' => $roleConfig['role_display'] ?? 'User',
    'items' => $roleConfig['nav_items'] ?? []
];

// Dynamic Portal Title Mapping
$portal_names = [
    'admin' => 'Admin Portal',
    'school_officer' => 'School Officer Portal'
];

$portal_title = $portal_names[$role] ?? 'Dashboard';

// Function to check if menu item is active
function isMenuItemActive($item_url, $current_page) {
    $item_page = basename(parse_url($item_url, PHP_URL_PATH), '.php');
    if ($current_page !== $item_page) {
        return false;
    }

    $normalized_item_url = $item_url;
    if (strpos($item_url, '/') !== 0) {
        global $base_public_url;
        $normalized_item_url = rtrim($base_public_url, '/') . '/' . ltrim($item_url, '/');
    }

    $item_path = ltrim(parse_url($normalized_item_url, PHP_URL_PATH) ?? '', '/');
    $script_path = ltrim(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?? '', '/');

    return $item_path !== '' && $script_path !== '' && $script_path === $item_path;
}
?>

<style>
/* Dynamic Sidebar Styles */
:root {
    --sidebar-width: 260px;
    --sidebar-primary: #0B1D4A;
    --sidebar-primary-light: #1E3A6E;
    --sidebar-accent: #D4AF37;
    --sidebar-accent-hover: #B8960C;
    --sidebar-white: #FFFFFF;
    --sidebar-gray-100: #F8FAFC;
    --sidebar-gray-200: #E2E8F0;
    --sidebar-gray-600: #475569;
    --sidebar-gray-700: #334155;
    --sidebar-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --sidebar-shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    --sidebar-transition: all 0.3s ease;
}

#sidebar {
    width: 260px;
    background: var(--sidebar-primary);
    color: var(--sidebar-white);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transform: translateX(0);
    transition: var(--sidebar-transition);
    box-shadow: var(--sidebar-shadow-lg);
}

#sidebar.mobile-hidden {
    transform: translateX(-100%);
}

#sidebar .sidebar-header {
    padding: 28px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    text-align: center;
    background: linear-gradient(135deg, rgba(11, 29, 74, 0.95) 0%, rgba(30, 58, 110, 0.95) 100%);
}

#sidebar .sidebar-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 12px;
}

#sidebar .sidebar-brand img {
    width: 36px;
    height: auto;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

#sidebar .sidebar-brand h3 {
    font-size: 1.4rem;
    font-weight: 800;
    margin: 0;
    color: var(--sidebar-white);
    font-family: 'Inter', sans-serif;
    letter-spacing: -0.02em;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

#sidebar .sidebar-header p {
    font-size: 1.1rem;
    font-weight: 600;
    opacity: 0.95;
    margin: 6px 0 16px;
    font-family: 'Inter', sans-serif;
    color: var(--sidebar-white);
    letter-spacing: 0.01em;
}

#sidebar .user-role-badge {
    background: rgba(196, 154, 0, 0.25);
    color: #C49A00;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Inter', sans-serif;
    border: 1px solid rgba(196, 154, 0, 0.3);
    box-shadow: 0 2px 4px rgba(196, 154, 0, 0.1);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

#sidebar .sidebar-actions {
    display: flex;
    justify-content: center;
    margin-top: 16px;
}

#sidebar .sidebar-nav {
    flex: 1;
    padding: 20px 0;
}

#sidebar .nav-menu {
    list-style: none;
    margin: 0;
    padding: 0;
}

#sidebar .nav-menu li {
    margin-bottom: 2px;
}

#sidebar .nav-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: var(--sidebar-transition);
    font-size: 0.95rem;
    font-weight: 500;
    font-family: 'Inter', sans-serif;
    position: relative;
}

#sidebar .nav-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--sidebar-white);
}

#sidebar .nav-menu a.active {
    background: rgba(196, 154, 0, 0.15);
    color: var(--sidebar-accent);
    border-left: 4px solid #C49A00;
    font-weight: 600;
    position: relative;
}

#sidebar .nav-menu a.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #C49A00 0%, #D4AF37 100%);
    box-shadow: 0 0 8px rgba(196, 154, 0, 0.4);
}

#sidebar .nav-menu i {
    width: 20px;
    text-align: center;
    font-size: 0.9rem;
}

#sidebar .sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

#sidebar .user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

#sidebar .user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #C49A00 0%, #D4AF37 100%);
    color: var(--sidebar-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    font-family: 'Inter', sans-serif;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    position: relative;
    overflow: hidden;
}

#sidebar .user-avatar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 100%);
    border-radius: 50%;
}

#sidebar .user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

#sidebar .user-details {
    flex: 1;
}

#sidebar .user-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--sidebar-white);
    font-family: 'Inter', sans-serif;
}

#sidebar .user-email {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 2px;
    font-family: 'Inter', sans-serif;
}

#sidebar .sidebar-pwa-actions {
    margin-bottom: 12px;
}

#sidebar .logout-btn {
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
    transition: var(--sidebar-transition);
    font-family: 'Inter', sans-serif;
}

#sidebar .logout-btn:hover {
    background: rgba(239, 68, 68, 0.3);
    color: #f87171;
}

/* Mobile Toggle Button */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1001;
    background: var(--sidebar-primary);
    color: var(--sidebar-white);
    border: none;
    border-radius: 8px;
    padding: 0.75rem;
    cursor: pointer;
    box-shadow: var(--sidebar-shadow);
    transition: var(--sidebar-transition);
}

.sidebar-toggle:hover {
    background: var(--sidebar-primary-light);
}

/* Mobile Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: var(--sidebar-transition);
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
    visibility: visible;
}

/* Main content wrapper – desktop */
.main-content {
    margin-left: var(--sidebar-width);
    transition: margin-left var(--sidebar-transition);
    padding: 2rem;
    min-height: 100vh;
}

/* Mobile: hide sidebar off-canvas */
@media (max-width: 767.98px) {
    #sidebar {
        transform: translateX(-100%);
    }
    #sidebar.open {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
    }
}

@media (min-width: 768px) {
    .sidebar-toggle {
        display: none;
    }
    
    .sidebar-overlay {
        display: none !important;
    }
}
</style>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <img src="<?php echo $base_public_url; ?>/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo" style="width: 36px; height: auto; flex-shrink: 0;">
            <h3>IECEP-LSC</h3>
        </div>
        <p><?php echo htmlspecialchars($portal_title); ?></p>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <?php foreach ($menu_config['items'] as $item): ?>
                <li>
                    <a href="<?php echo buildSidebarLink($item['url'], $base_public_url); ?>" 
                       class="<?php echo isMenuItemActive($item['url'], $current_page) ? 'active' : ''; ?>">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php if ($avatar_url): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="User Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
            </div>
        </div>
        <div class="sidebar-pwa-actions">
            <button type="button" id="install-btn" class="install-btn hidden">Install App</button>
            <div id="offline-status" class="offline-status hidden">Offline mode available</div>
        </div>
        <a href="<?php echo $base_root_url; ?>/login.php?logout=true" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
// Prevent script duplication
if (typeof window.sidebarInitialized === 'undefined') {
    window.sidebarInitialized = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        // ============================================
        // SIDEBAR STATE MANAGEMENT
        // ============================================
        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function toggleSidebar() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        
        // Toggle sidebar
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
        
        // ============================================
        // SIDEBAR NAVIGATION - STABLE STATE
        // ============================================
        // Ensure clicking nav links doesn't break sidebar state
        const navLinks = sidebar.querySelectorAll('.nav-menu a');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                // On mobile, close sidebar after clicking a link
                if (window.innerWidth <= 767) {
                    // Small delay to allow navigation to start
                    setTimeout(closeSidebar, 100);
                }
            });
        });
        
        // ============================================
        // PREVENT SIDEBAR STATE CORRUPTION
        // ============================================
        // Ensure sidebar is visible on desktop regardless of mobile state
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767) {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
}
</script>
