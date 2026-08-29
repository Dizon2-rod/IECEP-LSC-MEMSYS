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
$raw_role = $_SESSION['role'] ?? 
            $user['role'] ?? 
            $user['user_metadata']['role'] ?? 
            '';

// Standardize / normalize role strictly based on current portal section to guarantee 100% UI consistency
$current_script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');

if (strpos($current_script, '/portal/admin') !== false || strpos($current_script, '/admin/') !== false) {
    $role = 'admin';
} elseif (strpos($current_script, '/portal/school-officer') !== false || strpos($current_script, '/school-officer/') !== false) {
    $role = 'school_officer';
} elseif (strpos($current_script, '/portal/member') !== false || strpos($current_script, '/member/') !== false) {
    $role = 'member';
} else {
    $raw_role = $_SESSION['role'] ?? 
                $user['role'] ?? 
                $user['user_metadata']['role'] ?? 
                '';
    $normalized_role = strtolower(trim((string)$raw_role));
    if (in_array($normalized_role, ['super_admin', 'superadmin', 'eb_president', 'admin', 'administrator', 'admin_officer'])) {
        $role = 'admin';
    } elseif (in_array($normalized_role, ['school_officer', 'officer', 'school_admin', 'school'])) {
        $role = 'school_officer';
    } elseif (in_array($normalized_role, ['member', 'student', 'student_member', 'user'])) {
        $role = 'member';
    } else {
        $role = 'admin';
    }
}

// Retrieve user info from all possible session and cookie sources
$sessionUser = $_SESSION['user'] ?? [];
$userId = $_SESSION['user_id'] ?? $sessionUser['id'] ?? $sessionUser['user_metadata']['sub'] ?? null;

// User Email
$user_email = $_SESSION['email'] ?? 
              $sessionUser['email'] ?? 
              $_SESSION['user_email'] ?? 
              $user['email'] ?? 
              ($_COOKIE['remember_email'] ?? '');

// User Display Name
$user_name = $_SESSION['full_name'] ?? 
             $sessionUser['name'] ?? 
             $sessionUser['full_name'] ?? 
             $sessionUser['user_metadata']['full_name'] ?? 
             $_SESSION['user_name'] ?? 
             $user['name'] ?? 
             $user['user_metadata']['full_name'] ?? 
             '';

if (empty($user_name) && !empty($user_email)) {
    $emailParts = explode('@', $user_email);
    $user_name = ucwords(str_replace(['.', '_', '-'], ' ', $emailParts[0]));
}

if (empty($user_name)) {
    $user_name = ($role === 'admin' || $role === 'super_admin') ? 'IECEP-LSC Administrator' : 'Portal User';
}

// User Avatar Image
$avatar_url = $_SESSION['avatar_url'] ?? 
              $sessionUser['avatar_url'] ?? 
              $sessionUser['user_metadata']['avatar_url'] ?? 
              $sessionUser['profile_image'] ?? 
              $sessionUser['photo_url'] ?? 
              $_SESSION['profile_photo'] ?? 
              $user['user_metadata']['avatar_url'] ?? 
              $user['avatar_url'] ?? 
              '';

// If avatar is not in session, attempt fetching from Supabase user_profiles (production UUIDs only)
if (empty($avatar_url) && !empty($userId) && is_string($userId) && strpos($userId, 'local-') === false) {
    try {
        if (function_exists('getSupabaseClient')) {
            $sb = getSupabaseClient();
            if ($sb && is_object($sb)) {
                $profs = $sb->select('user_profiles', [
                    'select' => 'avatar_url, profile_photo, full_name',
                    'user_id' => 'eq.' . $userId,
                    'limit' => 1
                ]);
                if (is_array($profs) && !empty($profs[0])) {
                    if (!empty($profs[0]['avatar_url'])) {
                        $avatar_url = $profs[0]['avatar_url'];
                        $_SESSION['avatar_url'] = $avatar_url;
                    } elseif (!empty($profs[0]['profile_photo'])) {
                        $avatar_url = $profs[0]['profile_photo'];
                        $_SESSION['avatar_url'] = $avatar_url;
                    }
                    if (empty($_SESSION['full_name']) && !empty($profs[0]['full_name'])) {
                        $user_name = $profs[0]['full_name'];
                        $_SESSION['full_name'] = $user_name;
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Silently continue
    }
}

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

// Unified Portal Badges & Titles
$portal_badges = [
    'super_admin'    => 'Super Admin',
    'admin'          => 'Admin Portal',
    'school_officer' => 'School Officer',
    'member'         => 'Student Member'
];

$portal_title = $portal_badges[$role] ?? ($roleConfig['role_display'] ?? 'Portal');

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
    position: fixed !important;
    left: 0 !important;
    top: 0 !important;
    bottom: 0 !important;
    height: 100% !important;
    height: 100vh !important;
    height: 100dvh !important;
    max-height: 100% !important;
    max-height: 100vh !important;
    max-height: 100dvh !important;
    overflow: hidden !important;
    z-index: 1000;
    display: flex !important;
    flex-direction: column !important;
    justify-content: flex-start !important;
    transform: translateX(0);
    transition: var(--sb-transition);
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.04);
    border-right: 1px solid var(--sb-border);
    font-family: 'DM Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
    box-sizing: border-box !important;
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
    flex: 0 0 auto !important;
    flex-shrink: 0 !important;
    padding: 0.9rem 1rem 0.7rem !important;
    border-bottom: 1px solid #E2E8F0 !important;
    background: #FFFFFF !important;
    z-index: 10 !important;
    box-sizing: border-box !important;
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

/* Nav Menu (Independently scrollable middle section) */
#sidebar .sidebar-nav {
    flex: 1 1 0px !important;
    min-height: 0 !important;
    height: auto !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    overscroll-behavior: contain !important;
    -webkit-overflow-scrolling: touch !important;
    padding: 6px 10px !important;
    box-sizing: border-box !important;
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

/* Footer & Profile (Permanently pinned at bottom) */
#sidebar .sidebar-footer {
    flex-shrink: 0 !important;
    margin-top: auto !important;
    padding: 8px 10px !important;
    padding-bottom: max(10px, env(safe-area-inset-bottom, 10px)) !important;
    border-top: 1px solid var(--sb-border) !important;
    background: #FFFFFF !important;
    z-index: 20 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 6px !important;
    box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.04) !important;
    box-sizing: border-box !important;
}

#sidebar .user-info {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 6px 8px !important;
    background: #F8FAFC !important;
    border-radius: 9px !important;
    border: 1px solid var(--sb-border) !important;
    min-height: 48px !important;
    box-sizing: border-box !important;
}

#sidebar .user-avatar {
    width: 36px !important;
    height: 36px !important;
    border-radius: 50% !important;
    background: linear-gradient(135deg, #0B1D4A 0%, #1E3A8A 100%) !important;
    color: #FDE047 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: 800 !important;
    font-size: 0.9rem !important;
    border: 2px solid rgba(212, 175, 55, 0.4) !important;
    flex-shrink: 0 !important;
    overflow: hidden !important;
}

#sidebar .user-avatar img {
    width: 100% !important;
    height: 100% !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    display: block !important;
}

#sidebar .user-details {
    flex: 1 1 0% !important;
    min-width: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 2px !important;
}

#sidebar .user-name {
    font-weight: 700 !important;
    font-size: 0.8rem !important;
    color: #0F172A !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    line-height: 1.2 !important;
}

#sidebar .user-email {
    font-size: 0.68rem !important;
    color: var(--sb-gold-text) !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    font-weight: 600 !important;
    line-height: 1.2 !important;
}

.user-quick-logout {
    width: 28px !important;
    height: 28px !important;
    border-radius: 6px !important;
    background: #FEF2F2 !important;
    color: #DC2626 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.78rem !important;
    text-decoration: none !important;
    flex-shrink: 0 !important;
    transition: all 0.2s ease !important;
    border: 1px solid #FEE2E2 !important;
}

.user-quick-logout:hover {
    background: #FEE2E2 !important;
    color: #991B1B !important;
    border-color: #FCA5A5 !important;
}

.hidden {
    display: none !important;
}



/* Mobile Toggle & Top Bar */
.mobile-top-bar {
    display: none;
    position: sticky;
    top: 0;
    left: 0;
    right: 0;
    height: 58px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--sb-border);
    z-index: 1000;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.mobile-bar-brand {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.mobile-logo-frame {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #FAF9F6;
    border: 1px solid rgba(212, 175, 55, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3px;
}

.mobile-logo-frame img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.mobile-brand-title {
    font-family: 'Times New Roman', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0B1D4A;
    letter-spacing: 0.02em;
    line-height: 1;
}

.mobile-portal-badge {
    font-size: 0.62rem;
    font-weight: 700;
    color: #B8860B;
    background: #FEF9C3;
    border: 1px solid rgba(212, 175, 55, 0.3);
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sidebar-toggle {
    position: relative !important;
    top: auto !important;
    left: auto !important;
    background: var(--sb-navy) !important;
    color: #FFFFFF !important;
    border: none !important;
    border-radius: 9px !important;
    width: 38px !important;
    height: 38px !important;
    min-width: 38px !important;
    font-size: 1rem !important;
    cursor: pointer !important;
    box-shadow: 0 2px 8px rgba(11, 29, 74, 0.25) !important;
    transition: var(--sb-transition) !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 !important;
    padding: 0 !important;
    flex-shrink: 0 !important;
    z-index: 10 !important;
}

.sidebar-toggle:hover {
    background: var(--sb-navy-hover) !important;
    transform: scale(1.03) !important;
}

.sidebar-close-btn {
    display: none;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
    background: #F8FAFC;
    color: #64748B;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.sidebar-close-btn:hover {
    background: #FEF2F2;
    color: #DC2626;
    border-color: #FECACA;
    transform: scale(1.05);
}

.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(11, 29, 74, 0.45);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 1050;
    display: none;
    opacity: 0;
    transition: opacity 0.28s ease;
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
    box-sizing: border-box;
}

/* Tablet & Mobile Breakpoint (<= 991.98px) */
@media (max-width: 991.98px) {
    .mobile-top-bar {
        display: flex;
    }

    .sidebar-close-btn {
        display: flex;
    }

    #sidebar {
        width: 280px !important;
        max-width: 85vw !important;
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        height: 100% !important;
        height: 100vh !important;
        height: 100dvh !important;
        max-height: 100% !important;
        max-height: 100vh !important;
        max-height: 100dvh !important;
        transform: translateX(-100%) !important;
        z-index: 1100 !important;
        box-shadow: none !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: flex-start !important;
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease !important;
    }

    #sidebar.open {
        transform: translateX(0) !important;
        box-shadow: 12px 0 45px rgba(11, 29, 74, 0.28) !important;
    }

    #sidebar .sidebar-header {
        padding: 0.75rem 0.85rem 0.6rem !important;
    }

    #sidebar .sidebar-status-strip {
        margin-top: 0.45rem !important;
        padding: 4px 8px !important;
    }

    #sidebar .sidebar-nav {
        flex: 1 1 0px !important;
        min-height: 0 !important;
        padding: 4px 8px !important;
    }

    #sidebar .nav-menu a {
        padding: 7px 10px !important;
        font-size: 0.82rem !important;
        gap: 10px !important;
    }

    #sidebar .sidebar-footer {
        flex: 0 0 auto !important;
        flex-shrink: 0 !important;
        margin-top: auto !important;
        padding: 8px 10px !important;
        padding-bottom: max(10px, env(safe-area-inset-bottom, 10px)) !important;
        background: #FFFFFF !important;
    }

    #sidebar .user-info {
        padding: 5px 8px !important;
        min-height: 44px !important;
    }

    .main-content {
        margin-left: 0 !important;
        padding: 1.25rem 0.85rem !important;
        width: 100% !important;
        max-width: 100vw !important;
        box-sizing: border-box !important;
    }
}

@media (min-width: 992px) {
    .mobile-top-bar {
        display: none !important;
    }

    .sidebar-close-btn {
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

<!-- Mobile Top Sticky Navigation Bar -->
<div class="mobile-top-bar" id="mobileTopBar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="mobile-bar-brand">
        <div class="mobile-logo-frame">
            <img src="<?php echo $base_public_url; ?>/assets/icons/iecep-logo.png" alt="IECEP-LSC Seal" onerror="this.style.display='none';">
        </div>
        <span class="mobile-brand-title">IECEP-LSC</span>
        <span class="mobile-portal-badge"><?php echo htmlspecialchars($portal_title); ?></span>
    </div>
</div>

<!-- Mobile Overlay Backdrop -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Drawer -->
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
            <!-- Mobile Close (X) Button -->
            <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
                <i class="fas fa-xmark"></i>
            </button>
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
                <?php if (!empty($avatar_url)): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="<?php echo htmlspecialchars($user_name); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span style="display:none;"><?php echo strtoupper(substr($user_name ?: ($user_email ?: 'A'), 0, 1)); ?></span>
                <?php else: ?>
                    <span><?php echo strtoupper(substr($user_name ?: ($user_email ?: 'A'), 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <div class="user-name" title="<?php echo htmlspecialchars($user_name); ?>"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-email" title="<?php echo htmlspecialchars($user_email); ?>"><?php echo htmlspecialchars($user_email); ?></div>
            </div>
            <a href="<?php echo $base_root_url; ?>/login.php?logout=true" class="user-quick-logout" title="Sign Out" aria-label="Sign Out">
                <i class="fas fa-arrow-right-from-bracket"></i>
            </a>
        </div>
        <div class="sidebar-pwa-actions">
            <button type="button" id="install-btn" class="install-btn hidden">Install App</button>
            <div id="offline-status" class="offline-status hidden">Offline mode available</div>
        </div>
    </div>
</aside>

<script>
if (typeof window.sidebarInitialized === 'undefined') {
    window.sidebarInitialized = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
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

        if (sidebarCloseBtn) {
            sidebarCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
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

        // Touch Swipe Handling on Mobile
        let touchStartX = 0;
        let touchEndX = 0;

        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            const swipeDistance = touchEndX - touchStartX;

            // Swipe Left to Close Drawer
            if (sidebar && sidebar.classList.contains('open') && swipeDistance < -60) {
                closeSidebar();
            }
            // Edge Swipe Right (< 35px from left edge) to Open Drawer
            else if (sidebar && !sidebar.classList.contains('open') && touchStartX < 35 && swipeDistance > 60) {
                openSidebar();
            }
        }, { passive: true });
        
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

            // Auto-close sidebar on mobile after clicking any direct navigation link
            const navLinks = sidebar.querySelectorAll('.nav-menu a:not(.dropdown-toggle)');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 991) {
                        setTimeout(closeSidebar, 150);
                    }
                });
            });
        }
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991) {
                if (sidebar) sidebar.classList.remove('open');
                if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
}
</script>
