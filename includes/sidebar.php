<?php
require_once __DIR__ . '/../bootstrap.php';
// Dynamic Sidebar - Unified White/Light Theme
// ONLY the currently active page is colored; the rest are clean white.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load role configuration
require_once __DIR__ . '/../includes/role-config.php';

$base_public_url = defined('PUBLIC_URL') ? PUBLIC_URL : (defined('BASE_URL') ? BASE_URL . '/public' : '/IECEP-LSC-MEMSYS/public');
$base_root_url = defined('BASE_URL') ? BASE_URL : '/IECEP-LSC-MEMSYS';

if (!function_exists('buildSidebarLink')) {
    function buildSidebarLink(string $url, string $publicBase): string {
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return htmlspecialchars($url, ENT_QUOTES);
        }
        if (strpos($url, '/') === 0) {
            return htmlspecialchars((defined('BASE_URL') ? BASE_URL : '') . $url, ENT_QUOTES);
        }
        return htmlspecialchars(rtrim($publicBase, '/') . '/' . ltrim($url, '/'), ENT_QUOTES);
    }
}

// Get user info
$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$role = $_SESSION['role'] ?? 
         $user['role'] ?? 
         $user['user_metadata']['role'] ?? 
         'school_officer';

$user_name = $user['user_metadata']['full_name'] ?? $_SESSION['user_name'] ?? $user['name'] ?? $user['email'] ?? 'User';
$user_email = $user['email'] ?? $_SESSION['user_email'] ?? '';
$avatar_url = $user['user_metadata']['avatar_url'] ?? '';

// Get role configuration
$roleConfig = getRoleConfig($role);
if (!$roleConfig) {
    $role = 'school_officer';
    $roleConfig = getRoleConfig($role);
}

$menu_config = [
    'title' => $roleConfig['title'] ?? 'Admin Portal',
    'badge' => $roleConfig['role_display'] ?? 'Admin Portal',
    'items' => $roleConfig['nav_items'] ?? []
];

// Unified Portal Title Mapping
$portal_names = [
    'super_admin' => 'Admin Portal',
    'admin' => 'Admin Portal',
    'school_officer' => 'School Officer Portal',
    'member' => 'Member Portal'
];

$portal_title = $portal_names[$role] ?? ($roleConfig['title'] ?? 'Admin Portal');

if (!function_exists('isMenuItemActive')) {
    function isMenuItemActive($item_url) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        
        // Strip prefixes to get exact relative path from public root
        $clean_script = preg_replace('#^.*?/public/#', '', $script);
        $clean_script = preg_replace('#^.*?/IECEP-LSC-MEMSYS/#', '', $clean_script);
        $clean_script = trim(parse_url($clean_script, PHP_URL_PATH) ?? '', '/');
        
        $clean_url = preg_replace('#^.*?/public/#', '', $item_url);
        $clean_url = preg_replace('#^.*?/IECEP-LSC-MEMSYS/#', '', $clean_url);
        $clean_url = trim(parse_url($clean_url, PHP_URL_PATH) ?? '', '/');
        
        if (empty($clean_url) || empty($clean_script)) {
            return false;
        }
        
        return ($clean_script === $clean_url);
    }
}
?>

<style>
/* ==========================================================================
   CONSISTENT WHITE SIDEBAR (Single Active Highlight Theme)
   ========================================================================== */
:root {
    --sb-width: 260px;
    --sb-bg: #FFFFFF;
    --sb-navy: #0B1D4A;
    --sb-navy-hover: #1E3A8A;
    --sb-gold: #D4AF37;
    --sb-gold-text: #B8860B;
    --sb-text: #334155;
    --sb-text-muted: #64748B;
    --sb-border: #E2E8F0;
    --sb-hover: #F8FAFC;
    --sb-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

#sidebar {
    width: var(--sb-width);
    background: var(--sb-bg);
    color: var(--sb-text);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transform: translateX(0);
    transition: var(--sb-transition);
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.04);
    border-right: 1px solid var(--sb-border);
    font-family: 'DM Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
}

#sidebar::-webkit-scrollbar {
    width: 4px;
}

#sidebar::-webkit-scrollbar-track {
    background: transparent;
}

#sidebar::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}

/* Header & Brand (Locked to Pure White) */
#sidebar .sidebar-header,
.sidebar-header {
    padding: 1.25rem 1.15rem 1rem !important;
    border-bottom: 1px solid #E2E8F0 !important;
    background: #FFFFFF !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
}

.sidebar-header-top {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    background: transparent !important;
}

.sidebar-brand-lockup {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
    flex: 1 !important;
    background: transparent !important;
}

.sidebar-logo-frame {
    width: 42px !important;
    height: 42px !important;
    border-radius: 10px !important;
    background: #FAF9F6 !important;
    border: 1px solid rgba(212, 175, 55, 0.35) !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    padding: 4px !important;
}

.sidebar-logo-frame img {
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
}

.sidebar-brand-meta {
    display: flex !important;
    flex-direction: column !important;
    background: transparent !important;
}

.sidebar-brand-title {
    font-family: 'Times New Roman', serif !important;
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    color: #0B1D4A !important;
    letter-spacing: 0.02em !important;
    line-height: 1.15 !important;
    margin: 0 !important;
}

.sidebar-portal-badge {
    font-size: 0.65rem !important;
    font-weight: 700 !important;
    color: #B8860B !important;
    background: #FEF9C3 !important;
    border: 1px solid rgba(212, 175, 55, 0.3) !important;
    padding: 2px 7px !important;
    border-radius: 4px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    margin-top: 3px !important;
    display: inline-block !important;
    width: fit-content !important;
}

.sidebar-status-strip {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin-top: 0.85rem !important;
    padding: 6px 10px !important;
    background: #F8FAFC !important;
    border-radius: 6px !important;
    border: 1px solid #E2E8F0 !important;
}

.sidebar-status-dot {
    width: 7px !important;
    height: 7px !important;
    border-radius: 50% !important;
    background: #10B981 !important;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25) !important;
    flex-shrink: 0 !important;
}

.sidebar-status-text {
    font-size: 0.64rem !important;
    font-weight: 600 !important;
    color: #64748B !important;
    letter-spacing: 0.02em !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}

/* Nav Menu */
#sidebar .sidebar-nav {
    flex: 1;
    padding: 12px 10px;
}

#sidebar .nav-menu {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

#sidebar .nav-menu li {
    margin: 0;
}

#sidebar .nav-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: var(--sb-text);
    text-decoration: none;
    border-radius: 10px;
    transition: var(--sb-transition);
    font-size: 0.88rem;
    font-weight: 500;
    background: transparent;
    user-select: none;
}

#sidebar .nav-menu a:hover {
    background: var(--sb-hover);
    color: var(--sb-navy);
}

#sidebar .nav-menu a:hover i:first-child {
    color: var(--sb-navy);
}

#sidebar .nav-menu i:first-child {
    width: 20px;
    text-align: center;
    font-size: 0.95rem;
    color: var(--sb-text-muted);
    transition: var(--sb-transition);
    flex-shrink: 0;
}

/* ONLY the selected single file gets the dark blue active color */
#sidebar .nav-menu > li:not(.nav-item-dropdown) > a.active,
#sidebar .nav-submenu a.active {
    background: linear-gradient(135deg, #0B1D4A 0%, #1E3A8A 100%) !important;
    color: #FFFFFF !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 14px rgba(11, 29, 74, 0.22) !important;
}

#sidebar .nav-submenu a.active {
    border-left: 3.5px solid #FDE047 !important;
}

#sidebar .nav-menu > li:not(.nav-item-dropdown) > a.active i:first-child,
#sidebar .nav-submenu a.active i:first-child {
    color: #FDE047 !important;
}

/* Dropdown Parent Header - ALWAYS clean white/transparent, NEVER dark blue */
.nav-item-dropdown > a {
    cursor: pointer;
    background: transparent !important;
    color: var(--sb-text) !important;
    box-shadow: none !important;
}

.nav-item-dropdown > a:hover {
    background: var(--sb-hover) !important;
    color: var(--sb-navy) !important;
}

.nav-item-dropdown.open > a {
    color: var(--sb-navy) !important;
    font-weight: 600;
}

.submenu-arrow {
    font-size: 0.72rem !important;
    color: #94A3B8;
    margin-left: auto;
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-item-dropdown.open .submenu-arrow {
    transform: rotate(90deg);
    color: var(--sb-navy);
}

/* Submenu */
.nav-submenu {
    list-style: none;
    margin: 2px 0 4px 20px;
    padding: 2px 0 2px 10px;
    border-left: 1.5px solid var(--sb-border);
    display: none;
    flex-direction: column;
    gap: 2px;
}

.nav-submenu.show {
    display: flex;
}

.nav-submenu a {
    padding: 7px 12px !important;
    font-size: 0.82rem !important;
    color: var(--sb-text-muted) !important;
    border-radius: 8px !important;
    font-weight: 500 !important;
    gap: 10px !important;
    background: transparent;
}

.nav-submenu a i:first-child {
    font-size: 0.8rem !important;
    width: 16px !important;
    color: #94A3B8 !important;
}

.nav-submenu a:hover {
    color: var(--sb-navy) !important;
    background: var(--sb-hover) !important;
}

.nav-submenu a:hover i:first-child {
    color: var(--sb-navy) !important;
}

/* Footer & Profile */
#sidebar .sidebar-footer {
    padding: 12px 14px 14px;
    border-top: 1px solid var(--sb-border);
    background: var(--sb-bg);
    position: sticky;
    bottom: 0;
    z-index: 2;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

#sidebar .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    background: #F8FAFC;
    border-radius: 9px;
    border: 1px solid var(--sb-border);
}

#sidebar .user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B1D4A 0%, #1E3A8A 100%);
    color: #FDE047;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9rem;
    border: 2px solid rgba(212, 175, 55, 0.4);
    flex-shrink: 0;
}

#sidebar .user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

#sidebar .user-details {
    flex: 1;
    overflow: hidden;
}

#sidebar .user-name {
    font-weight: 700;
    font-size: 0.84rem;
    color: #0F172A;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

#sidebar .user-email {
    font-size: 0.72rem;
    color: var(--sb-gold-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 600;
}

.hidden {
    display: none !important;
}

#sidebar .logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 12px;
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FEE2E2;
    text-decoration: none;
    border-radius: 9px;
    font-size: 0.82rem;
    font-weight: 600;
    transition: var(--sb-transition);
}

#sidebar .logout-btn:hover {
    background: #FEE2E2;
    color: #B91C1C;
    border-color: #FCA5A5;
}

/* Mobile Toggle */
.sidebar-toggle {
    position: fixed;
    top: 14px;
    left: 14px;
    z-index: 1001;
    background: var(--sb-navy);
    color: #FFFFFF;
    border: none;
    border-radius: 9px;
    width: 40px;
    height: 40px;
    font-size: 1.05rem;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(11, 29, 74, 0.25);
    transition: var(--sb-transition);
    display: none;
    align-items: center;
    justify-content: center;
}

.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    z-index: 999;
    display: none;
    opacity: 0;
    transition: opacity 0.25s ease;
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

.main-content {
    margin-left: var(--sb-width);
    transition: margin-left var(--sb-transition);
    padding: 2rem;
    min-height: 100vh;
}

@media (max-width: 767.98px) {
    .sidebar-toggle {
        display: flex;
    }
    #sidebar {
        transform: translateX(-100%);
    }
    #sidebar.open {
        transform: translateX(0);
        box-shadow: 8px 0 30px rgba(0, 0, 0, 0.15);
    }
    .main-content {
        margin-left: 0;
        padding-top: 4.5rem;
    }
}

@media (min-width: 768px) {
    .sidebar-toggle {
        display: none !important;
    }
    .sidebar-overlay {
        display: none !important;
    }
}

/* ─────────────────────────────────────────────────────────────
   Universal SaaS Squircle Pagination Design (< 1 [ 2 ] 3 >)
   ───────────────────────────────────────────────────────────── */
.pagination,
.pagination-pages,
.roster-pagination-bar .pagination-pages,
.pagination-controls {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    list-style: none !important;
    padding: 0 !important;
    margin: 0 !important;
}

.pagination .page-item {
    margin: 0 !important;
    list-style: none !important;
}

.pagination .page-link,
.page-btn,
.pagination-pages button,
.pagination-pages a,
.pagination-controls button,
.pagination-controls a {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 32px !important;
    height: 32px !important;
    padding: 4px 8px !important;
    border-radius: 8px !important;
    font-size: 0.86rem !important;
    font-weight: 600 !important;
    color: #475569 !important;
    background: transparent !important;
    border: 1px solid transparent !important;
    text-decoration: none !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    user-select: none !important;
    box-shadow: none !important;
}

.pagination .page-link:hover,
.page-btn:hover,
.pagination-pages button:hover,
.pagination-pages a:hover,
.pagination-controls button:hover,
.pagination-controls a:hover {
    background: #F1F5F9 !important;
    color: #0F172A !important;
    border-color: #E2E8F0 !important;
}

.pagination .page-item.active .page-link,
.pagination .page-link.active,
.page-btn.active,
.pagination-pages button.active,
.pagination-pages a.active,
.pagination-controls button.active,
.pagination-controls a.active {
    background: #1E293B !important;
    color: #FFFFFF !important;
    font-weight: 700 !important;
    border-radius: 9px !important;
    border-color: #1E293B !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.18) !important;
}

.pagination .page-item.disabled .page-link,
.page-btn:disabled,
.pagination-pages button:disabled,
.pagination-controls button:disabled {
    color: #CBD5E1 !important;
    background: transparent !important;
    border-color: transparent !important;
    cursor: not-allowed !important;
    opacity: 0.45 !important;
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
        <div class="sidebar-header-top">
            <div class="sidebar-brand-lockup">
                <div class="sidebar-logo-frame">
                    <img src="<?php echo $base_public_url; ?>/assets/icons/iecep-logo.png" alt="IECEP-LSC Seal" onerror="this.style.display='none';">
                </div>
                <div class="sidebar-brand-meta">
                    <span class="sidebar-brand-title">IECEP-LSC</span>
                    <span class="sidebar-portal-badge"><?php echo htmlspecialchars($portal_title); ?></span>
                </div>
            </div>
        </div>
        
        <div class="sidebar-status-strip">
            <span class="sidebar-status-dot"></span>
            <span class="sidebar-status-text">MEMSYS – Laguna Student Chapter</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <?php foreach ($menu_config['items'] as $item): ?>
                <?php if (!empty($item['children'])): ?>
                    <?php 
                        $hasActiveChild = false;
                        foreach ($item['children'] as $child) {
                            if (isMenuItemActive($child['url'])) {
                                $hasActiveChild = true;
                                break;
                            }
                        }
                    ?>
                    <li class="nav-item-dropdown <?php echo $hasActiveChild ? 'open' : ''; ?>">
                        <a href="javascript:void(0);" class="dropdown-toggle">
                            <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($item['label']); ?></span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="nav-submenu <?php echo $hasActiveChild ? 'show' : ''; ?>">
                            <?php foreach ($item['children'] as $child): ?>
                                <li>
                                    <a href="<?php echo buildSidebarLink($child['url'], $base_public_url); ?>"
                                       class="<?php echo isMenuItemActive($child['url']) ? 'active' : ''; ?>">
                                        <i class="fas <?php echo htmlspecialchars($child['icon']); ?>"></i>
                                        <span><?php echo htmlspecialchars($child['label']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo buildSidebarLink($item['url'], $base_public_url); ?>" 
                           class="<?php echo isMenuItemActive($item['url']) ? 'active' : ''; ?>">
                            <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($item['label']); ?></span>
                        </a>
                    </li>
                <?php endif; ?>
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
if (typeof window.sidebarInitialized === 'undefined') {
    window.sidebarInitialized = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function openSidebar() {
            if (sidebar) sidebar.classList.add('open');
            if (sidebarOverlay) sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function toggleSidebar() {
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
        
        // Dropdown accordion toggles
        if (sidebar) {
            const dropdownToggles = sidebar.querySelectorAll('.nav-item-dropdown > a');
            dropdownToggles.forEach(function(toggle) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const parent = toggle.closest('.nav-item-dropdown');
                    if (parent) {
                        const submenu = parent.querySelector('.nav-submenu');
                        if (submenu) {
                            const isOpen = parent.classList.contains('open');
                            
                            // Close other open dropdowns for a clean accordion effect
                            sidebar.querySelectorAll('.nav-item-dropdown.open').forEach(function(other) {
                                if (other !== parent) {
                                    other.classList.remove('open');
                                    const otherSub = other.querySelector('.nav-submenu');
                                    if (otherSub) otherSub.classList.remove('show');
                                }
                            });

                            if (isOpen) {
                                parent.classList.remove('open');
                                submenu.classList.remove('show');
                            } else {
                                parent.classList.add('open');
                                submenu.classList.add('show');
                            }
                        }
                    }
                });
            });

            const navLinks = sidebar.querySelectorAll('.nav-menu a:not(.dropdown-toggle)');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 767) {
                        setTimeout(closeSidebar, 150);
                    }
                });
            });
        }
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767) {
                if (sidebar) sidebar.classList.remove('open');
                if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
}
</script>
